<?php
/**
 * Fix active_plugins option corruption
 *
 * Run this from WordPress root:
 * php -r "define('WP_USE_THEMES', false); require 'wp-load.php'; require 'wp-content/plugins/optipress/fix-active-plugins.php';"
 */

echo "Checking active_plugins option...\n";

// Get the option directly from database
global $wpdb;
$active_plugins = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'active_plugins'" );

echo "Raw value: " . var_export($active_plugins, true) . "\n\n";

// Try to unserialize
$unserialized = @unserialize($active_plugins);

if ($unserialized === false && $active_plugins !== 'b:0;') {
    echo "ERROR: Cannot unserialize active_plugins option!\n";
    echo "The value is corrupted.\n\n";

    echo "Fixing by resetting to empty array...\n";

    // Reset to empty array
    $wpdb->update(
        $wpdb->options,
        array('option_value' => serialize(array())),
        array('option_name' => 'active_plugins')
    );

    // Clear cache
    wp_cache_delete('active_plugins', 'options');
    wp_cache_delete('alloptions', 'options');

    echo "Fixed! Please manually re-activate your plugins from wp-admin.\n";
} else {
    echo "active_plugins is valid:\n";
    print_r($unserialized);

    // Check if optipress is in there
    if (is_array($unserialized)) {
        $optipress_active = false;
        foreach ($unserialized as $plugin) {
            if (strpos($plugin, 'optipress') !== false) {
                echo "\nOptiPress is active: $plugin\n";
                $optipress_active = true;
            }
        }

        if (!$optipress_active) {
            echo "\nOptiPress is NOT active. Adding it...\n";
            $unserialized[] = 'optipress/optipress.php';
            update_option('active_plugins', $unserialized);
            echo "Added! OptiPress should now be active.\n";
        }
    }
}

echo "\nDone!\n";
