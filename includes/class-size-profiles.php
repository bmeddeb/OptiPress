<?php
/**
 * Size Profiles
 *
 * Admin UI to manage OptiPress image size profiles (name, width, height, crop, format).
 * These define responsive image sizes for different screen sizes and layout requirements.
 * Stores in 'optipress_size_profiles' option.
 * Provides a filter so Thumbnailer can consume saved sizes.
 *
 * @package OptiPress
 */

namespace OptiPress;

defined( 'ABSPATH' ) || exit;

/**
 * Size_Profiles class
 *
 * Manages image size profiles configuration for responsive images.
 */
final class Size_Profiles {
	/**
	 * Singleton instance
	 *
	 * @var Size_Profiles|null
	 */
	private static $instance = null;

	/**
	 * Option name for storing size profiles
	 */
	const OPTION = 'optipress_size_profiles';

	/**
	 * Get singleton instance
	 *
	 * @return Size_Profiles
	 */
	public static function get_instance() {
		return self::$instance ?? ( self::$instance = new self() );
	}

	/**
	 * Constructor - registers hooks
	 */
	private function __construct() {
		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_menu', array( $this, 'add_submenu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}

		// Feed profiles to the Thumbnailer
		add_filter( 'optipress_thumbnailer_profiles', array( $this, 'profiles_for_thumbnailer' ) );
	}

	/**
	 * Add submenu page
	 */
	public function add_submenu() {
		// Add as submenu under OptiPress
		add_submenu_page(
			'optipress',
			__( 'OptiPress Image Sizes', 'optipress' ),
			__( 'Image Sizes', 'optipress' ),
			'manage_options',
			'optipress-thumbnails',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'optipress_size_profiles_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_profiles' ),
			)
		);
		add_settings_section(
			'optipress_size_profiles_section',
			__( 'Image Size Profiles', 'optipress' ),
			function () {
				$global_opts = get_option( 'optipress_options', array() );
				$global_format = isset( $global_opts['format'] ) ? strtoupper( $global_opts['format'] ) : 'WebP';
				echo '<p>' . esc_html__( 'Define responsive image sizes for OptiPress to generate. These are used for different screen sizes and layout requirements. Name must be lowercase letters, numbers, and underscores.', 'optipress' ) . '</p>';
				echo '<p><strong>' . esc_html__( 'Format:', 'optipress' ) . '</strong> ' . sprintf(
					/* translators: %s is the current Image Optimization format (WebP or AVIF) */
					esc_html__( 'Choose "Auto" to use your Image Optimization format (%s), or select a specific format for each size. Advanced formats (TIFF, PSD, etc.) always use the Auto format.', 'optipress' ),
					'<strong>' . esc_html( $global_format ) . '</strong>'
				) . '</p>';
			},
			'optipress-thumbnails'
		);
		add_settings_field(
			'optipress_size_profiles_field',
			__( 'Profiles', 'optipress' ),
			array( $this, 'render_profiles_field' ),
			'optipress-thumbnails',
			'optipress_size_profiles_section'
		);

		// Seed defaults on first run
		$existing = get_option( self::OPTION, null );
		if ( null === $existing ) {
			update_option( self::OPTION, $this->default_profiles() );
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'optipress_page_optipress-thumbnails' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'optipress-size-profiles-css',
			plugins_url( 'assets/css/size-profiles.css', OPTIPRESS_PLUGIN_FILE ),
			array(),
			OPTIPRESS_VERSION
		);
		wp_enqueue_script(
			'optipress-size-profiles-js',
			plugins_url( 'assets/js/size-profiles.js', OPTIPRESS_PLUGIN_FILE ),
			array( 'jquery' ),
			OPTIPRESS_VERSION,
			true
		);
		wp_localize_script(
			'optipress-size-profiles-js',
			'OptiPressSizes',
			array(
				'rowTemplate' => $this->row_template(),
			)
		);
	}

	/**
	 * Get default size profiles
	 *
	 * @return array Default size profiles.
	 */
	private function default_profiles() {
		return array(
			array( 'name' => 'thumbnail',    'width' => 150,  'height' => 150,  'crop' => true,  'format' => 'auto' ),
			array( 'name' => 'medium',       'width' => 300,  'height' => 0,    'crop' => false, 'format' => 'auto' ),
			array( 'name' => 'medium_large', 'width' => 768,  'height' => 0,    'crop' => false, 'format' => 'auto' ),
			array( 'name' => 'large',        'width' => 1024, 'height' => 0,    'crop' => false, 'format' => 'auto' ),
			array( 'name' => 'xl',           'width' => 1600, 'height' => 0,    'crop' => false, 'format' => 'auto' ),
		);
	}

	/**
	 * Convert saved profiles to format expected by Thumbnailer
	 *
	 * @param array $defaults Default profiles from Thumbnailer.
	 * @return array Profiles for Thumbnailer.
	 */
	public function profiles_for_thumbnailer( $defaults ) {
		$rows = get_option( self::OPTION, array() );
		$out  = array();
		foreach ( $rows as $r ) {
			$name = isset( $r['name'] ) ? strtolower( sanitize_key( $r['name'] ) ) : '';
			if ( ! $name ) {
				continue;
			}
			$w = max( 0, (int) ( $r['width']  ?? 0 ) );
			$h = max( 0, (int) ( $r['height'] ?? 0 ) );
			$crop = ! empty( $r['crop'] );
			$fmt = $this->normalize_format( $r['format'] ?? 'inherit' );
			$out[ $name ] = array( 'width' => $w, 'height' => $h, 'crop' => $crop, 'format' => $fmt );
		}
		// If empty or malformed, fall back to defaults coming from Thumbnailer
		return ! empty( $out ) ? $out : $defaults;
	}

	/**
	 * Normalize format value to allowed options
	 *
	 * @param string $fmt Format value.
	 * @return string Normalized format.
	 */
	private function normalize_format( $fmt ) {
		$fmt = strtolower( (string) $fmt );
		$allowed = array( 'auto', 'inherit', 'avif', 'webp', 'jpeg', 'png' );
		// Convert legacy 'inherit' to 'auto'
		if ( 'inherit' === $fmt ) {
			$fmt = 'auto';
		}
		return in_array( $fmt, $allowed, true ) ? $fmt : 'auto';
	}

	/**
	 * Render settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'OptiPress Image Sizes', 'optipress' ); ?></h1>
			<form method="post" action="options.php" id="optipress-size-profiles-form">
				<?php
				settings_fields( 'optipress_size_profiles_group' );
				do_settings_sections( 'optipress-thumbnails' );
				submit_button( __( 'Save Image Sizes', 'optipress' ) );
				?>
			</form>
			<p class="description"><?php echo esc_html__( 'Tip: Set height=0 for auto height (maintains aspect ratio). Enable "crop" to force exact dimensions with center cropping.', 'optipress' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Get human-readable format label
	 *
	 * @param string $fmt Format value.
	 * @return string Human-readable label.
	 */
	private function human_format_label( $fmt ) {
		$fmt = strtolower( $fmt );
		// Convert legacy 'inherit' to 'auto'
		if ( 'inherit' === $fmt ) {
			$fmt = 'auto';
		}

		// Get global format for 'auto' display
		if ( 'auto' === $fmt ) {
			$global_opts = get_option( 'optipress_options', array() );
			$global_format = isset( $global_opts['format'] ) ? strtoupper( $global_opts['format'] ) : 'WebP';
			return $global_format;
		}

		$map = array(
			'avif'    => 'AVIF',
			'webp'    => 'WebP',
			'jpeg'    => 'JPEG',
			'png'     => 'PNG',
		);
		return $map[ $fmt ] ?? 'Auto';
	}

	/**
	 * Build hint text for a size profile
	 *
	 * @param int    $w    Width.
	 * @param int    $h    Height.
	 * @param bool   $crop Whether to crop.
	 * @param string $fmt  Format.
	 * @return string Hint text.
	 */
	private function build_hint( $w, $h, $crop, $fmt ) {
		$fmt_label = $this->human_format_label( $fmt );
		if ( $w <= 0 && $h <= 0 ) {
			return sprintf(
				/* translators: %s is output format label */
				esc_html__( 'No-op (set width or height). Output: %s.', 'optipress' ),
				esc_html( $fmt_label )
			);
		}
		if ( $w > 0 && $h === 0 ) {
			return sprintf(
				/* translators: 1: width, 2: output format */
				esc_html__( 'Resize to %1$d px wide (height auto). Output: %2$s.', 'optipress' ),
				$w,
				esc_html( $fmt_label )
			);
		}
		if ( $w === 0 && $h > 0 ) {
			return sprintf(
				/* translators: 1: height, 2: output format */
				esc_html__( 'Resize to %1$d px tall (width auto). Output: %2$s.', 'optipress' ),
				$h,
				esc_html( $fmt_label )
			);
		}
		// both > 0
		if ( $crop ) {
			return sprintf(
				/* translators: 1: width, 2: height, 3: output format */
				esc_html__( 'Cover crop to %1$d×%2$d (center). Output: %3$s.', 'optipress' ),
				$w,
				$h,
				esc_html( $fmt_label )
			);
		}
		return sprintf(
			/* translators: 1: width, 2: height, 3: output format */
			esc_html__( 'Fit inside %1$d×%2$d (keep aspect). Output: %3$s.', 'optipress' ),
			$w,
			$h,
			esc_html( $fmt_label )
		);
	}

	/**
	 * Render profiles field
	 */
	public function render_profiles_field() {
		$rows = get_option( self::OPTION, $this->default_profiles() );
		echo '<table class="widefat striped" id="optipress-size-profiles-table">';
		echo '<thead><tr><th>' . esc_html__( 'Name', 'optipress' ) . '</th><th>' . esc_html__( 'Width', 'optipress' ) . '</th><th>' . esc_html__( 'Height', 'optipress' ) . '</th><th>' . esc_html__( 'Crop', 'optipress' ) . '</th><th>' . esc_html__( 'Format', 'optipress' ) . '</th><th>' . esc_html__( 'Actions', 'optipress' ) . '</th></tr></thead>';
		echo '<tbody id="optipress-size-profiles-body">';
		foreach ( $rows as $i => $r ) {
			// Ensure old rows get default 'auto' on first render
			if ( ! isset( $r['format'] ) ) {
				$r['format'] = 'auto';
			}
			// Convert legacy 'inherit' to 'auto'
			if ( isset( $r['format'] ) && 'inherit' === $r['format'] ) {
				$r['format'] = 'auto';
			}
			echo $this->row_html( $i, $r );
		}
		echo '</tbody>';
		echo '</table>';
		echo '<p><button type="button" class="button" id="optipress-add-size">' . esc_html__( 'Add Image Size', 'optipress' ) . '</button></p>';
	}

	/**
	 * Generate HTML for a single row
	 *
	 * @param int   $index Row index.
	 * @param array $row   Row data.
	 * @return string HTML for row.
	 */
	private function row_html( $index, $row ) {
		$name   = isset( $row['name'] )   ? esc_attr( $row['name'] )   : '';
		$width  = isset( $row['width'] )  ? (int) $row['width']        : 0;
		$height = isset( $row['height'] ) ? (int) $row['height']       : 0;
		$crop   = ! empty( $row['crop'] ) ? 'checked'                  : '';
		$crop_b = ! empty( $row['crop'] );
		$format = isset( $row['format'] ) ? strtolower( (string) $row['format'] ) : 'auto';
		// Convert legacy 'inherit' to 'auto'
		if ( 'inherit' === $format ) {
			$format = 'auto';
		}

		// Get global format for hint
		$global_opts = get_option( 'optipress_options', array() );
		$global_format = isset( $global_opts['format'] ) ? strtoupper( $global_opts['format'] ) : 'WebP';

		$opts   = array(
			'auto'    => sprintf( 'Auto (%s)', $global_format ),
			'avif'    => 'AVIF',
			'webp'    => 'WebP',
			'jpeg'    => 'JPEG',
			'png'     => 'PNG',
		);

		$hint_text = $this->build_hint( $width, $height, $crop_b, $format );

		$html  = '<tr class="optipress-size-row"';
		$html .= ' data-index="' . esc_attr( $index ) . '"';
		$html .= '>';
		$html .= '<td><input type="text" name="' . self::OPTION . '[' . $index . '][name]" value="' . $name . '" pattern="[a-z0-9_]{2,32}" required /></td>';
		$html .= '<td><input class="optipress-w" type="number" name="' . self::OPTION . '[' . $index . '][width]" value="' . esc_attr( $width ) . '" min="0" step="1" /></td>';
		$html .= '<td><input class="optipress-h" type="number" name="' . self::OPTION . '[' . $index . '][height]" value="' . esc_attr( $height ) . '" min="0" step="1" /></td>';
		$html .= '<td><label><input class="optipress-crop" type="checkbox" name="' . self::OPTION . '[' . $index . '][crop]" value="1" ' . $crop . ' /> ' . esc_html__( 'Crop', 'optipress' ) . '</label></td>';
		// Format select
		$html .= '<td><select class="optipress-fmt" name="' . self::OPTION . '[' . $index . '][format]">';
		foreach ( $opts as $val => $label ) {
			$sel = ( $format === $val ) ? 'selected' : '';
			$html .= '<option value="' . esc_attr( $val ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select>';
		// Live hint (prefilled from PHP for no-JS)
		$html .= '<div class="optipress-size-hint" aria-live="polite">' . esc_html( $hint_text ) . '</div>';
		$html .= '</td>';
		$html .= '<td class="optipress-actions-cell"><button type="button" class="button button-small button-link-delete delete-size">' . esc_html__( 'Delete', 'optipress' ) . '</button></td>';
		$html .= '</tr>';
		return $html;
	}

	/**
	 * Generate row template for JavaScript
	 *
	 * @return string HTML template with {i} placeholder.
	 */
	private function row_template() {
		// Placeholder {i} replaced in JS
		return $this->row_html( '{i}', array( 'name' => '', 'width' => 0, 'height' => 0, 'crop' => false, 'format' => 'auto' ) );
	}

	/**
	 * Sanitize profiles on save
	 *
	 * @param array $value Raw input value.
	 * @return array Sanitized profiles.
	 */
	public function sanitize_profiles( $value ) {
		$clean = array();
		if ( is_array( $value ) ) {
			foreach ( $value as $row ) {
				$name = isset( $row['name'] ) ? strtolower( sanitize_key( $row['name'] ) ) : '';
				if ( ! $name ) {
					continue;
				}
				$width  = max( 0, (int) ( $row['width']  ?? 0 ) );
				$height = max( 0, (int) ( $row['height'] ?? 0 ) );
				$crop   = ! empty( $row['crop'] ) ? 1 : 0;
				$fmt    = $this->normalize_format( $row['format'] ?? 'inherit' );
				$clean[] = array( 'name' => $name, 'width' => $width, 'height' => $height, 'crop' => $crop, 'format' => $fmt );
			}
		}
		// Deduplicate by name
		$by_name = array();
		foreach ( $clean as $r ) {
			$by_name[ $r['name'] ] = $r;
		}
		return array_values( $by_name );
	}
}
