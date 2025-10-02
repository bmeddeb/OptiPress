<?php
/**
 * Integration Test for OptiPress Library Organizer
 *
 * This file contains integration tests for the organizer system.
 * Run via WP-CLI: wp eval-file includes/organizer/test-integration.php
 *
 * @package OptiPress
 * @since 0.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Integration Test Class
 */
class OptiPress_Organizer_Integration_Test {

	/**
	 * Run all tests.
	 *
	 * @return array Test results.
	 */
	public static function run_tests() {
		$results = array();

		echo "=== OptiPress Organizer Integration Tests ===\n\n";

		// Test 1: Create Item
		$results['create_item'] = self::test_create_item();

		// Test 2: Add Files
		$results['add_files'] = self::test_add_files();

		// Test 3: Query Items
		$results['query_items'] = self::test_query_items();

		// Test 4: Update Item
		$results['update_item'] = self::test_update_item();

		// Test 5: Delete Item
		$results['delete_item'] = self::test_delete_item();

		// Test 6: File System
		$results['file_system'] = self::test_file_system();

		// Summary
		self::print_summary( $results );

		return $results;
	}

	/**
	 * Test item creation.
	 *
	 * @return bool Test result.
	 */
	private static function test_create_item() {
		echo "Test 1: Create Item... ";

		$organizer = optipress_organizer();
		$item_manager = $organizer->get_item_manager();

		$data = array(
			'title'       => 'Test Image Item',
			'description' => 'This is a test item for integration testing.',
			'metadata'    => array(
				'test_key' => 'test_value',
			),
		);

		$item_id = $item_manager->create_item( $data );

		if ( is_wp_error( $item_id ) ) {
			echo "FAILED: " . $item_id->get_error_message() . "\n";
			return false;
		}

		$item = $item_manager->get_item( $item_id );

		if ( ! $item ) {
			echo "FAILED: Could not retrieve created item\n";
			wp_delete_post( $item_id, true );
			return false;
		}

		if ( $item->post_title !== 'Test Image Item' ) {
			echo "FAILED: Title mismatch\n";
			wp_delete_post( $item_id, true );
			return false;
		}

		echo "PASSED (ID: $item_id)\n";

		// Store for cleanup
		set_transient( 'optipress_test_item_id', $item_id, 3600 );

		return true;
	}

	/**
	 * Test file addition.
	 *
	 * @return bool Test result.
	 */
	private static function test_add_files() {
		echo "Test 2: Add Files... ";

		$item_id = get_transient( 'optipress_test_item_id' );

		if ( ! $item_id ) {
			echo "FAILED: No item ID from previous test\n";
			return false;
		}

		$organizer = optipress_organizer();
		$file_manager = $organizer->get_file_manager();

		// Create a test file
		$upload_dir = wp_upload_dir();
		$test_file_path = $upload_dir['basedir'] . '/test-image.txt';
		file_put_contents( $test_file_path, 'Test file content' );

		// Add file to item
		$metadata = array(
			'width'  => 1920,
			'height' => 1080,
		);

		$file_id = $file_manager->add_file( $item_id, $test_file_path, 'original', $metadata );

		if ( is_wp_error( $file_id ) ) {
			echo "FAILED: " . $file_id->get_error_message() . "\n";
			unlink( $test_file_path );
			return false;
		}

		$file = $file_manager->get_file( $file_id );

		if ( ! $file ) {
			echo "FAILED: Could not retrieve created file\n";
			unlink( $test_file_path );
			return false;
		}

		echo "PASSED (File ID: $file_id)\n";

		set_transient( 'optipress_test_file_id', $file_id, 3600 );

		return true;
	}

	/**
	 * Test item querying.
	 *
	 * @return bool Test result.
	 */
	private static function test_query_items() {
		echo "Test 3: Query Items... ";

		$organizer = optipress_organizer();
		$item_manager = $organizer->get_item_manager();

		$query = $item_manager->query_items( array(
			'posts_per_page' => 10,
		) );

		if ( ! $query || ! $query->have_posts() ) {
			echo "FAILED: No items found in query\n";
			return false;
		}

		$count = $query->found_posts;
		echo "PASSED (Found $count items)\n";

		return true;
	}

	/**
	 * Test item update.
	 *
	 * @return bool Test result.
	 */
	private static function test_update_item() {
		echo "Test 4: Update Item... ";

		$item_id = get_transient( 'optipress_test_item_id' );

		if ( ! $item_id ) {
			echo "FAILED: No item ID\n";
			return false;
		}

		$organizer = optipress_organizer();
		$item_manager = $organizer->get_item_manager();

		$result = $item_manager->update_item( $item_id, array(
			'title' => 'Updated Test Item',
		) );

		if ( is_wp_error( $result ) ) {
			echo "FAILED: " . $result->get_error_message() . "\n";
			return false;
		}

		$item = $item_manager->get_item( $item_id );

		if ( $item->post_title !== 'Updated Test Item' ) {
			echo "FAILED: Title not updated\n";
			return false;
		}

		echo "PASSED\n";
		return true;
	}

	/**
	 * Test item deletion.
	 *
	 * @return bool Test result.
	 */
	private static function test_delete_item() {
		echo "Test 5: Delete Item... ";

		$item_id = get_transient( 'optipress_test_item_id' );

		if ( ! $item_id ) {
			echo "FAILED: No item ID\n";
			return false;
		}

		$organizer = optipress_organizer();
		$item_manager = $organizer->get_item_manager();

		$result = $item_manager->delete_item( $item_id, true );

		if ( is_wp_error( $result ) ) {
			echo "FAILED: " . $result->get_error_message() . "\n";
			return false;
		}

		$item = $item_manager->get_item( $item_id );

		if ( $item ) {
			echo "FAILED: Item still exists after deletion\n";
			return false;
		}

		echo "PASSED\n";

		// Cleanup transients
		delete_transient( 'optipress_test_item_id' );
		delete_transient( 'optipress_test_file_id' );

		return true;
	}

	/**
	 * Test file system operations.
	 *
	 * @return bool Test result.
	 */
	private static function test_file_system() {
		echo "Test 6: File System... ";

		$organizer = optipress_organizer();
		$file_system = new OptiPress_Organizer_File_System();

		// Test writability
		if ( ! $file_system->is_writable() ) {
			echo "FAILED: Base directory not writable\n";
			return false;
		}

		// Test directory structure
		$base_dir = $file_system->get_base_directory();

		if ( ! is_dir( $base_dir ) ) {
			echo "FAILED: Base directory does not exist\n";
			return false;
		}

		echo "PASSED\n";
		return true;
	}

	/**
	 * Print test summary.
	 *
	 * @param array $results Test results.
	 */
	private static function print_summary( $results ) {
		$total = count( $results );
		$passed = count( array_filter( $results ) );
		$failed = $total - $passed;

		echo "\n=== Test Summary ===\n";
		echo "Total:  $total\n";
		echo "Passed: $passed\n";
		echo "Failed: $failed\n";

		if ( $failed === 0 ) {
			echo "\n✅ All tests passed!\n";
		} else {
			echo "\n❌ Some tests failed.\n";
		}
	}
}

// Auto-run if called via WP-CLI
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	OptiPress_Organizer_Integration_Test::run_tests();
}
