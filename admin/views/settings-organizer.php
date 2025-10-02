<?php
/**
 * Library Organizer Settings View
 *
 * @package OptiPress
 */

defined( 'ABSPATH' ) || exit;

// Default values
$organizer_enabled    = isset( $options['organizer_enabled'] ) ? $options['organizer_enabled'] : false;
$default_collection   = isset( $options['organizer_default_collection'] ) ? $options['organizer_default_collection'] : 0;

// Get all collections for dropdown
$collections = get_terms( array(
	'taxonomy'   => 'optipress_collection',
	'hide_empty' => false,
	'orderby'    => 'name',
	'order'      => 'ASC',
) );

?>

<div class="optipress-settings-section">
	<h2><?php esc_html_e( 'Library Organizer Settings', 'optipress' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Configure automatic library item creation when advanced format images (RAW, TIFF, PSD, HEIC) are uploaded.', 'optipress' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<!-- Enable Organizer -->
		<tr>
			<th scope="row">
				<label for="optipress_organizer_enabled"><?php esc_html_e( 'Enable Library Organizer', 'optipress' ); ?></label>
			</th>
			<td>
				<label>
					<input type="checkbox" name="optipress_options[organizer_enabled]" id="optipress_organizer_enabled" value="1" <?php checked( $organizer_enabled, true ); ?>>
					<?php esc_html_e( 'Automatically create library items for advanced format uploads', 'optipress' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'When enabled, uploading RAW, TIFF, PSD, or HEIC images will automatically create organized library items with metadata extraction.', 'optipress' ); ?>
				</p>
			</td>
		</tr>

		<!-- Default Collection -->
		<tr>
			<th scope="row">
				<label for="optipress_default_collection"><?php esc_html_e( 'Default Collection', 'optipress' ); ?></label>
			</th>
			<td>
				<select name="optipress_options[organizer_default_collection]" id="optipress_default_collection" class="regular-text">
					<option value="0" <?php selected( $default_collection, 0 ); ?>>
						<?php esc_html_e( 'None (Uncategorized)', 'optipress' ); ?>
					</option>
					<?php if ( ! empty( $collections ) && ! is_wp_error( $collections ) ) : ?>
						<?php foreach ( $collections as $collection ) : ?>
							<option value="<?php echo esc_attr( $collection->term_id ); ?>" <?php selected( $default_collection, $collection->term_id ); ?>>
								<?php echo esc_html( $collection->name ); ?>
							</option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
				<p class="description">
					<?php esc_html_e( 'Automatically assign new uploads to this collection. You can manage collections from the library page.', 'optipress' ); ?>
				</p>
			</td>
		</tr>
	</table>
</div>

<div class="optipress-settings-section">
	<h3><?php esc_html_e( 'How It Works', 'optipress' ); ?></h3>
	<p class="description">
		<?php esc_html_e( 'When you upload an advanced format image:', 'optipress' ); ?>
	</p>
	<ol class="optipress-info-list">
		<li><?php esc_html_e( 'OptiPress creates a web-optimized preview (WebP/AVIF/JPEG)', 'optipress' ); ?></li>
		<li><?php esc_html_e( 'The Library Organizer creates a library item', 'optipress' ); ?></li>
		<li><?php esc_html_e( 'Links the original RAW file and preview together', 'optipress' ); ?></li>
		<li><?php esc_html_e( 'Extracts EXIF metadata (camera, lens, settings, GPS)', 'optipress' ); ?></li>
		<li><?php esc_html_e( 'Extracts IPTC metadata (keywords, copyright, creator)', 'optipress' ); ?></li>
		<li><?php esc_html_e( 'Tracks all generated image sizes (thumbnails, etc.)', 'optipress' ); ?></li>
	</ol>

	<p class="description">
		<?php esc_html_e( 'Supported formats: CR2, CR3, NEF, ARW, DNG, RAF, ORF, RW2, TIFF, PSD, HEIC, HEIF, and more.', 'optipress' ); ?>
	</p>
</div>
