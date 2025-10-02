<?php
/**
 * System Check Class
 *
 * Detects server capabilities for image processing.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * System_Check class
 *
 * Provides methods to detect available image processing libraries
 * and supported formats.
 */
class System_Check {

	/**
	 * Singleton instance
	 *
	 * @var System_Check
	 */
	private static $instance = null;

	/**
	 * Cached capabilities
	 *
	 * @var array|null
	 */
	private $capabilities = null;

	/**
	 * Get singleton instance
	 *
	 * @return System_Check
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		// Hook into admin notices
		add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
	}

	/**
	 * Check PHP version and capabilities
	 *
	 * @return array {
	 *     PHP version information.
	 *
	 *     @type string $version        Current PHP version.
	 *     @type bool   $meets_minimum  Whether PHP meets minimum requirement (7.4+).
	 *     @type bool   $supports_avif  Whether PHP version supports AVIF in GD (8.1+).
	 * }
	 */
	public function check_php_version() {
		$php_version = PHP_VERSION;

		return array(
			'version'        => $php_version,
			'meets_minimum'  => version_compare( $php_version, '7.4.0', '>=' ),
			'supports_avif'  => version_compare( $php_version, '8.1.0', '>=' ),
		);
	}

	/**
	 * Check GD library availability and supported formats
	 *
	 * @return array {
	 *     GD library information.
	 *
	 *     @type bool   $available       Whether GD library is available.
	 *     @type string $version         GD library version (if available).
	 *     @type bool   $supports_webp   Whether GD supports WebP.
	 *     @type bool   $supports_avif   Whether GD supports AVIF (PHP 8.1+ required).
	 *     @type bool   $supports_jpeg   Whether GD supports JPEG.
	 *     @type bool   $supports_png    Whether GD supports PNG.
	 * }
	 */
	public function check_gd_library() {
		$available = extension_loaded( 'gd' ) && function_exists( 'gd_info' );

		if ( ! $available ) {
			return array(
				'available'       => false,
				'version'         => null,
				'supports_webp'   => false,
				'supports_avif'   => false,
				'supports_jpeg'   => false,
				'supports_png'    => false,
			);
		}

		$gd_info = gd_info();
		$php_check = $this->check_php_version();

		return array(
			'available'       => true,
			'version'         => isset( $gd_info['GD Version'] ) ? $gd_info['GD Version'] : 'Unknown',
			'supports_webp'   => function_exists( 'imagewebp' ),
			'supports_avif'   => $php_check['supports_avif'] && function_exists( 'imageavif' ),
			'supports_jpeg'   => function_exists( 'imagecreatefromjpeg' ) && function_exists( 'imagejpeg' ),
			'supports_png'    => function_exists( 'imagecreatefrompng' ) && function_exists( 'imagepng' ),
		);
	}

	/**
	 * Check Imagick extension availability and supported formats
	 *
	 * @return array {
	 *     Imagick extension information.
	 *
	 *     @type bool   $available       Whether Imagick extension is available.
	 *     @type string $version         Imagick version (if available).
	 *     @type bool   $supports_webp   Whether Imagick supports WebP.
	 *     @type bool   $supports_avif   Whether Imagick supports AVIF.
	 *     @type bool   $supports_jpeg   Whether Imagick supports JPEG.
	 *     @type bool   $supports_png    Whether Imagick supports PNG.
	 * }
	 */
	public function check_imagick_extension() {
		$available = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );

		if ( ! $available ) {
			return array(
				'available'       => false,
				'version'         => null,
				'supports_webp'   => false,
				'supports_avif'   => false,
				'supports_jpeg'   => false,
				'supports_png'    => false,
			);
		}

		try {
			$imagick = new \Imagick();
			$version = $imagick->getVersion();
			$version_string = isset( $version['versionString'] ) ? $version['versionString'] : 'Unknown';

			// Query supported formats
			$formats = \Imagick::queryFormats();

			return array(
				'available'       => true,
				'version'         => $version_string,
				'supports_webp'   => in_array( 'WEBP', $formats, true ),
				'supports_avif'   => in_array( 'AVIF', $formats, true ),
				'supports_jpeg'   => in_array( 'JPEG', $formats, true ) || in_array( 'JPG', $formats, true ),
				'supports_png'    => in_array( 'PNG', $formats, true ),
			);
		} catch ( \Exception $e ) {
			return array(
				'available'       => false,
				'version'         => null,
				'supports_webp'   => false,
				'supports_avif'   => false,
				'supports_jpeg'   => false,
				'supports_png'    => false,
			);
		}
	}

	/**
	 * Check RAW format support (via ImageMagick delegates)
	 *
	 * Checks if ImageMagick has delegates for common RAW camera formats.
	 *
	 * @return array {
	 *     RAW format support information.
	 *
	 *     @type bool  $available       Whether Imagick is available.
	 *     @type array $supported_raw   Array of supported RAW formats.
	 *     @type bool  $has_delegates   Whether any RAW delegates are available.
	 * }
	 */
	public function check_raw_format_support() {
		if ( ! class_exists( 'Imagick' ) ) {
			return array(
				'available'      => false,
				'supported_raw'  => array(),
				'has_delegates'  => false,
			);
		}

		try {
			$formats = \Imagick::queryFormats();

			// Common RAW formats to check
			$raw_formats = array( 'DNG', 'CR2', 'CR3', 'NEF', 'ARW', 'ORF', 'RAF', 'RW2' );
			$supported_raw = array();

			foreach ( $raw_formats as $format ) {
				if ( in_array( $format, $formats, true ) ) {
					$supported_raw[] = $format;
				}
			}

			return array(
				'available'      => true,
				'supported_raw'  => $supported_raw,
				'has_delegates'  => ! empty( $supported_raw ),
			);
		} catch ( \Exception $e ) {
			return array(
				'available'      => false,
				'supported_raw'  => array(),
				'has_delegates'  => false,
			);
		}
	}

	/**
	 * Check JPEG 2000 format support (via OpenJPEG delegate)
	 *
	 * Checks if ImageMagick has OpenJPEG delegate for JPEG 2000 formats.
	 *
	 * @return array {
	 *     JPEG 2000 format support information.
	 *
	 *     @type bool  $available          Whether Imagick is available.
	 *     @type array $supported_jp2      Array of supported JP2 formats.
	 *     @type bool  $has_openjpeg       Whether OpenJPEG delegate is available.
	 *     @type string $install_command   Installation command for the delegate.
	 * }
	 */
	public function check_jp2_format_support() {
		if ( ! class_exists( 'Imagick' ) ) {
			return array(
				'available'        => false,
				'supported_jp2'    => array(),
				'has_openjpeg'     => false,
				'install_command'  => $this->get_install_command( 'openjpeg' ),
			);
		}

		try {
			$formats = \Imagick::queryFormats();

			// JPEG 2000 formats to check
			$jp2_formats = array( 'JP2', 'J2K', 'JPX', 'JPM', 'JPF' );
			$supported_jp2 = array();

			foreach ( $jp2_formats as $format ) {
				if ( in_array( $format, $formats, true ) ) {
					$supported_jp2[] = $format;
				}
			}

			return array(
				'available'        => true,
				'supported_jp2'    => $supported_jp2,
				'has_openjpeg'     => ! empty( $supported_jp2 ),
				'install_command'  => $this->get_install_command( 'openjpeg' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'available'        => false,
				'supported_jp2'    => array(),
				'has_openjpeg'     => false,
				'install_command'  => $this->get_install_command( 'openjpeg' ),
			);
		}
	}

	/**
	 * Check HEIC/HEIF format support (via libheif delegate)
	 *
	 * Checks if ImageMagick has libheif delegate for HEIC/HEIF formats.
	 *
	 * @return array {
	 *     HEIC/HEIF format support information.
	 *
	 *     @type bool  $available          Whether Imagick is available.
	 *     @type array $supported_heif     Array of supported HEIF formats.
	 *     @type bool  $has_libheif        Whether libheif delegate is available.
	 *     @type string $install_command   Installation command for the delegate.
	 * }
	 */
	public function check_heif_format_support() {
		if ( ! class_exists( 'Imagick' ) ) {
			return array(
				'available'        => false,
				'supported_heif'   => array(),
				'has_libheif'      => false,
				'install_command'  => $this->get_install_command( 'libheif' ),
			);
		}

		try {
			$formats = \Imagick::queryFormats();

			// HEIF formats to check
			$heif_formats = array( 'HEIC', 'HEIF' );
			$supported_heif = array();

			foreach ( $heif_formats as $format ) {
				if ( in_array( $format, $formats, true ) ) {
					$supported_heif[] = $format;
				}
			}

			return array(
				'available'        => true,
				'supported_heif'   => $supported_heif,
				'has_libheif'      => ! empty( $supported_heif ),
				'install_command'  => $this->get_install_command( 'libheif' ),
			);
		} catch ( \Exception $e ) {
			return array(
				'available'        => false,
				'supported_heif'   => array(),
				'has_libheif'      => false,
				'install_command'  => $this->get_install_command( 'libheif' ),
			);
		}
	}

	/**
	 * Get installation command for a delegate based on OS
	 *
	 * @param string $delegate Delegate name (openjpeg, libheif, libraw).
	 * @return string Installation command.
	 */
	private function get_install_command( $delegate ) {
		$os = PHP_OS_FAMILY;

		$commands = array(
			'openjpeg' => array(
				'Linux'   => 'sudo apt-get install libopenjp2-7-dev imagemagick',
				'Darwin'  => 'brew install openjpeg imagemagick',
				'Windows' => 'Install ImageMagick with OpenJPEG support from imagemagick.org',
			),
			'libheif'  => array(
				'Linux'   => 'sudo apt-get install libheif-dev imagemagick',
				'Darwin'  => 'brew install libheif imagemagick',
				'Windows' => 'Install ImageMagick with libheif support from imagemagick.org',
			),
			'libraw'   => array(
				'Linux'   => 'sudo apt-get install libraw-dev imagemagick',
				'Darwin'  => 'brew install libraw imagemagick',
				'Windows' => 'Install ImageMagick with libraw support from imagemagick.org',
			),
		);

		if ( isset( $commands[ $delegate ][ $os ] ) ) {
			return $commands[ $delegate ][ $os ];
		}

		return __( 'Install ImageMagick with appropriate delegates for your system', 'optipress' );
	}

	/**
	 * Get all system capabilities
	 *
	 * Returns a comprehensive report of available engines and formats.
	 *
	 * @param bool $force_refresh Force refresh of cached capabilities.
	 * @return array {
	 *     System capabilities.
	 *
	 *     @type array  $php              PHP version information.
	 *     @type array  $gd               GD library information.
	 *     @type array  $imagick          Imagick extension information.
	 *     @type array  $raw              RAW format support information.
	 *     @type array  $jp2              JPEG 2000 format support information.
	 *     @type array  $heif             HEIC/HEIF format support information.
	 *     @type array  $available_engines Available engines ('gd', 'imagick').
	 *     @type array  $formats          Format support by engine.
	 *     @type array  $warnings         Array of warning messages.
	 *     @type array  $errors           Array of error messages.
	 * }
	 */
    public function get_capabilities( $force_refresh = false ) {
		// Return cached capabilities if available and not forcing refresh
		if ( null !== $this->capabilities && ! $force_refresh ) {
			return $this->capabilities;
		}

        $php       = $this->check_php_version();
        $gd        = $this->check_gd_library();
        $imagick   = $this->check_imagick_extension();
        $raw       = $this->check_raw_format_support();
        $jp2       = $this->check_jp2_format_support();
        $heif      = $this->check_heif_format_support();
        $animation = $this->check_animation_support();

		// Determine available engines
		$available_engines = array();
		if ( $gd['available'] ) {
			$available_engines[] = 'gd';
		}
		if ( $imagick['available'] ) {
			$available_engines[] = 'imagick';
		}

		// Determine format support per engine
		$formats = array(
			'webp' => array(),
			'avif' => array(),
		);

		if ( $gd['available'] && $gd['supports_webp'] ) {
			$formats['webp'][] = 'gd';
		}
		if ( $gd['available'] && $gd['supports_avif'] ) {
			$formats['avif'][] = 'gd';
		}
		if ( $imagick['available'] && $imagick['supports_webp'] ) {
			$formats['webp'][] = 'imagick';
		}
		if ( $imagick['available'] && $imagick['supports_avif'] ) {
			$formats['avif'][] = 'imagick';
		}

		// Generate warnings and errors
		$warnings = array();
		$errors   = array();

		if ( ! $php['meets_minimum'] ) {
			$errors[] = sprintf(
				/* translators: 1: Required PHP version, 2: Current PHP version */
				__( 'OptiPress requires PHP %1$s or higher. You are running PHP %2$s.', 'optipress' ),
				'7.4',
				$php['version']
			);
		}

		if ( empty( $available_engines ) ) {
			$errors[] = __( 'No image processing library detected. OptiPress requires either GD or Imagick extension.', 'optipress' );
		}

		if ( ! $php['supports_avif'] && ! empty( $available_engines ) ) {
			$warnings[] = sprintf(
				/* translators: %s: Current PHP version */
				__( 'AVIF support in GD requires PHP 8.1+. You are running PHP %s. Upgrade PHP or use Imagick for AVIF support.', 'optipress' ),
				$php['version']
			);
		}

		if ( $gd['available'] && ! $gd['supports_webp'] ) {
			$warnings[] = __( 'Your GD library does not support WebP format. Please upgrade GD or use Imagick.', 'optipress' );
		}

		if ( empty( $formats['webp'] ) && empty( $formats['avif'] ) && ! empty( $available_engines ) ) {
			$errors[] = __( 'Neither WebP nor AVIF format is supported by your server. Please upgrade your image processing libraries.', 'optipress' );
		}

		// Cache the results
        $this->capabilities = array(
            'php'               => $php,
            'gd'                => $gd,
            'imagick'           => $imagick,
            'raw'               => $raw,
            'jp2'               => $jp2,
            'heif'              => $heif,
            'animation'         => $animation,
            'available_engines' => $available_engines,
            'formats'           => $formats,
            'warnings'          => $warnings,
            'errors'            => $errors,
        );

		return $this->capabilities;
	}

	/**
	 * Check if a specific engine supports a specific format
	 *
	 * @param string $engine Engine name ('gd' or 'imagick').
	 * @param string $format Format name ('webp' or 'avif').
	 * @return bool Whether the engine supports the format.
	 */
	public function engine_supports_format( $engine, $format ) {
		$capabilities = $this->get_capabilities();

		if ( ! isset( $capabilities['formats'][ $format ] ) ) {
			return false;
		}

		return in_array( $engine, $capabilities['formats'][ $format ], true );
	}

	/**
	 * Get the best available engine for a specific format
	 *
	 * @param string $format Format name ('webp' or 'avif').
	 * @return string|null Engine name or null if no engine supports the format.
	 */
	public function get_best_engine_for_format( $format ) {
		$capabilities = $this->get_capabilities();

		if ( ! isset( $capabilities['formats'][ $format ] ) || empty( $capabilities['formats'][ $format ] ) ) {
			return null;
		}

		// Prefer Imagick over GD (generally better quality and performance)
		$supported_engines = $capabilities['formats'][ $format ];

		if ( in_array( 'imagick', $supported_engines, true ) ) {
			return 'imagick';
		}

		if ( in_array( 'gd', $supported_engines, true ) ) {
			return 'gd';
		}

		return null;
	}

	/**
	 * Display admin notices for critical errors and warnings
	 */
	public function display_admin_notices() {
		// Only show on plugin's admin pages or dashboard
		$screen = get_current_screen();
		if ( ! $screen || ( 'dashboard' !== $screen->id && false === strpos( $screen->id, 'optipress' ) ) ) {
			return;
		}

		$capabilities = $this->get_capabilities();

		// Display errors
		if ( ! empty( $capabilities['errors'] ) ) {
			foreach ( $capabilities['errors'] as $error ) {
				printf(
					'<div class="notice notice-error"><p><strong>%s:</strong> %s</p></div>',
					esc_html__( 'OptiPress Error', 'optipress' ),
					esc_html( $error )
				);
			}
		}

		// Display warnings
		if ( ! empty( $capabilities['warnings'] ) ) {
			foreach ( $capabilities['warnings'] as $warning ) {
				printf(
					'<div class="notice notice-warning"><p><strong>%s:</strong> %s</p></div>',
					esc_html__( 'OptiPress Warning', 'optipress' ),
					esc_html( $warning )
				);
			}
		}
	}

	/**
	 * Check if system meets minimum requirements
	 *
	 * @return bool Whether system meets minimum requirements.
	 */
    public function meets_minimum_requirements() {
        $capabilities = $this->get_capabilities();
        return empty( $capabilities['errors'] );
    }

    /**
     * Check animation detection/support status
     *
     * Returns information about detecting animated GIF/WebP and current behavior.
     *
     * @return array {
     *   @type array $detection { 'gif' => bool, 'webp' => bool }
     *   @type string $behavior  Current behavior (e.g., 'skip')
     * }
     */
    public function check_animation_support() {
        $gif_detection  = false;
        $webp_detection = false;

        // If Imagick is available, it can detect multi-frame images
        if ( class_exists( 'Imagick' ) ) {
            $gif_detection  = true;
            // Animated WebP detection depends on build, but frame iteration works where supported
            $webp_detection = true;
        } else {
            // Fallback heuristics exist in converter (signature checks)
            $gif_detection  = true;  // NETSCAPE2.0 marker heuristic
            $webp_detection = true;  // ANIM/ANMF chunk heuristic
        }

        return array(
            'detection' => array(
                'gif'  => (bool) $gif_detection,
                'webp' => (bool) $webp_detection,
            ),
            // Current plugin behavior: skip converting animated images to preserve animation
            'behavior'  => 'skip',
        );
    }
}
