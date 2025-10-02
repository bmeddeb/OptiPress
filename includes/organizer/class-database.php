<?php
/**
 * Database Management for OptiPress Library Organizer
 *
 * Handles creation and management of custom database tables.
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class OptiPress_Organizer_Database
 *
 * Manages custom database tables for download tracking and statistics.
 */
class OptiPress_Organizer_Database {

	/**
	 * Database version for schema updates.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Initialize the class.
	 */
	public function __construct() {
		// Will be called on plugin activation
	}

	/**
	 * Create custom database tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'optipress_downloads';

		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			file_id BIGINT(20) UNSIGNED NOT NULL,
			item_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			ip_address VARCHAR(45) NOT NULL,
			user_agent TEXT,
			download_date DATETIME NOT NULL,
			file_size BIGINT(20) UNSIGNED,
			download_method VARCHAR(50),
			referrer TEXT,
			PRIMARY KEY  (id),
			KEY file_id (file_id),
			KEY item_id (item_id),
			KEY user_id (user_id),
			KEY download_date (download_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version
		update_option( 'optipress_organizer_db_version', self::DB_VERSION );
	}

	/**
	 * Update database schema if needed.
	 *
	 * @param string $from_version Previous version.
	 * @param string $to_version New version.
	 * @return void
	 */
	public function update_schema( $from_version, $to_version ) {
		// Future: Implement schema migration logic when DB version changes
		// Example:
		// if ( version_compare( $from_version, '1.1', '<' ) ) {
		//     $this->migrate_to_1_1();
		// }
	}

	/**
	 * Drop all custom tables (for uninstall only).
	 *
	 * @return void
	 */
	public function drop_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'optipress_downloads';
		$wpdb->query( "DROP TABLE IF EXISTS $table_name" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Delete database version option
		delete_option( 'optipress_organizer_db_version' );
	}

	/**
	 * Check if tables exist and version is current.
	 *
	 * @return bool True if tables exist and are current.
	 */
	public function tables_exist() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'optipress_downloads';
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return $table_exists === $table_name;
	}

	/**
	 * Get current database version.
	 *
	 * @return string Database version.
	 */
	public function get_db_version() {
		return get_option( 'optipress_organizer_db_version', '0' );
	}
}
