<?php
/**
 * Engine Registry
 *
 * Manages available image conversion engines and selects the appropriate one.
 *
 * @package OptiPress
 */

namespace OptiPress\Engines;

defined( 'ABSPATH' ) || exit;

/**
 * Engine_Registry class
 *
 * Registers and manages image conversion engines.
 * Provides methods to select the appropriate engine based on user settings
 * and format compatibility.
 */
class Engine_Registry {

	/**
	 * Singleton instance
	 *
	 * @var Engine_Registry
	 */
	private static $instance = null;

	/**
	 * Registered engines
	 *
	 * @var array<string, ImageEngineInterface>
	 */
	private $engines = array();

	/**
	 * Get singleton instance
	 *
	 * @return Engine_Registry
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
		$this->register_default_engines();
	}

	/**
	 * Register default engines (GD and Imagick)
	 */
	private function register_default_engines() {
		$this->register_engine( new GD_Engine() );
		$this->register_engine( new Imagick_Engine() );
	}

	/**
	 * Register an image conversion engine
	 *
	 * @param ImageEngineInterface $engine Engine instance to register.
	 * @return bool Whether registration was successful.
	 */
	public function register_engine( ImageEngineInterface $engine ) {
		$name = $engine->get_name();

		if ( empty( $name ) ) {
			return false;
		}

		$this->engines[ $name ] = $engine;
		return true;
	}

	/**
	 * Get an engine by name
	 *
	 * @param string $name Engine name (e.g., 'gd', 'imagick').
	 * @return ImageEngineInterface|null Engine instance or null if not found.
	 */
	public function get_engine( $name ) {
		return isset( $this->engines[ $name ] ) ? $this->engines[ $name ] : null;
	}

	/**
	 * Get all registered engines
	 *
	 * @return array<string, ImageEngineInterface> Array of engine instances.
	 */
	public function get_all_engines() {
		return $this->engines;
	}

	/**
	 * Get all available (installed and working) engines
	 *
	 * @return array<string, ImageEngineInterface> Array of available engines.
	 */
	public function get_available_engines() {
		$available = array();

		foreach ( $this->engines as $name => $engine ) {
			if ( $engine->is_available() ) {
				$available[ $name ] = $engine;
			}
		}

		return $available;
	}

	/**
	 * Get the best available engine for a specific format
	 *
	 * Preference order: Imagick > GD
	 *
	 * @param string $format Format to check ('webp' or 'avif').
	 * @return ImageEngineInterface|null Engine instance or null if none support the format.
	 */
	public function get_best_engine_for_format( $format ) {
		// Prefer Imagick if available and supports the format
		if ( isset( $this->engines['imagick'] ) ) {
			$imagick = $this->engines['imagick'];
			if ( $imagick->is_available() && $imagick->supports_format( $format ) ) {
				return $imagick;
			}
		}

		// Fallback to GD if available and supports the format
		if ( isset( $this->engines['gd'] ) ) {
			$gd = $this->engines['gd'];
			if ( $gd->is_available() && $gd->supports_format( $format ) ) {
				return $gd;
			}
		}

		// Check other registered engines
		foreach ( $this->engines as $name => $engine ) {
			if ( in_array( $name, array( 'gd', 'imagick' ), true ) ) {
				continue; // Already checked
			}

			if ( $engine->is_available() && $engine->supports_format( $format ) ) {
				return $engine;
			}
		}

		return null;
	}

	/**
	 * Get engine based on plugin settings
	 *
	 * Reads the 'engine' option from plugin settings and returns
	 * the appropriate engine. If set to 'auto', selects the best available engine.
	 *
	 * @param string $format Format that the engine needs to support.
	 * @return ImageEngineInterface|null Engine instance or null if none available.
	 */
	public function get_engine_from_settings( $format ) {
		$options = get_option( 'optipress_options', array() );
		$engine_preference = isset( $options['engine'] ) ? $options['engine'] : 'auto';

		// Auto-detect best engine
		if ( 'auto' === $engine_preference ) {
			return $this->get_best_engine_for_format( $format );
		}

		// Get specific engine
		$engine = $this->get_engine( $engine_preference );

		// Validate engine is available and supports the format
		if ( $engine && $engine->is_available() && $engine->supports_format( $format ) ) {
			return $engine;
		}

		// Fallback to auto-detection if specified engine is not suitable
		return $this->get_best_engine_for_format( $format );
	}

	/**
	 * Validate engine and format compatibility
	 *
	 * @param string $engine_name Engine name to validate.
	 * @param string $format      Format to check.
	 * @return array {
	 *     Validation result.
	 *
	 *     @type bool   $valid   Whether combination is valid.
	 *     @type string $message Error message if not valid.
	 * }
	 */
	public function validate_engine_format( $engine_name, $format ) {
		// Handle 'auto' as always valid
		if ( 'auto' === $engine_name ) {
			$best_engine = $this->get_best_engine_for_format( $format );
			if ( null === $best_engine ) {
				return array(
					'valid'   => false,
					'message' => sprintf(
						/* translators: %s: Format name */
						__( 'No available engine supports %s format.', 'optipress' ),
						strtoupper( $format )
					),
				);
			}
			return array(
				'valid'   => true,
				'message' => sprintf(
					/* translators: 1: Format name, 2: Engine name */
					__( '%1$s format is supported by %2$s engine.', 'optipress' ),
					strtoupper( $format ),
					$best_engine->get_name()
				),
			);
		}

		// Get specific engine
		$engine = $this->get_engine( $engine_name );

		if ( null === $engine ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: %s: Engine name */
					__( 'Engine "%s" is not registered.', 'optipress' ),
					$engine_name
				),
			);
		}

		if ( ! $engine->is_available() ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: %s: Engine name */
					__( 'Engine "%s" is not available on this server.', 'optipress' ),
					$engine_name
				),
			);
		}

		if ( ! $engine->supports_format( $format ) ) {
			return array(
				'valid'   => false,
				'message' => sprintf(
					/* translators: 1: Engine name, 2: Format name */
					__( 'Engine "%1$s" does not support %2$s format.', 'optipress' ),
					$engine_name,
					strtoupper( $format )
				),
			);
		}

		return array(
			'valid'   => true,
			'message' => sprintf(
				/* translators: 1: Engine name, 2: Format name */
				__( 'Engine "%1$s" supports %2$s format.', 'optipress' ),
				$engine_name,
				strtoupper( $format )
			),
		);
	}

	/**
	 * Get engines that support a specific output format
	 *
	 * @param string $format Format to check ('webp' or 'avif').
	 * @return array Array of engine names that support the format.
	 */
	public function get_engines_supporting_format( $format ) {
		$supporting = array();

		foreach ( $this->engines as $name => $engine ) {
			if ( $engine->is_available() && $engine->supports_format( $format ) ) {
				$supporting[] = $name;
			}
		}

		return $supporting;
	}

	/**
	 * Get all supported input image formats from all available engines
	 *
	 * Returns a unified list of MIME types that can be converted by at least
	 * one available engine on this system.
	 *
	 * @return array Array of supported MIME types (deduplicated).
	 */
	public function get_all_supported_input_formats() {
		$all_formats = array();

		foreach ( $this->engines as $engine ) {
			if ( $engine->is_available() ) {
				$engine_formats = $engine->get_supported_input_formats();
				$all_formats = array_merge( $all_formats, $engine_formats );
			}
		}

		// Remove duplicates
		$all_formats = array_unique( $all_formats );

		// Re-index array (remove gaps from array_unique)
		return array_values( $all_formats );
	}

	/**
	 * Check if a MIME type is supported for conversion
	 *
	 * @param string $mime_type MIME type to check (e.g., 'image/jpeg').
	 * @return bool Whether this MIME type can be converted.
	 */
	public function is_mime_type_supported( $mime_type ) {
		$supported_formats = $this->get_all_supported_input_formats();
		return in_array( $mime_type, $supported_formats, true );
	}
}