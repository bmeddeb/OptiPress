<?php
/**
 * Direct database fix for active_plugins
 * This doesn't load WordPress, just connects to MySQL directly
 */

// WordPress database credentials - adjust these
$db_name = 'wordpress';
$db_user = 'root';
$db_pass = 'root';
$db_host = 'localhost';
$table_prefix = 'wp_';

echo "Connecting to database...\n";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected!\n\n";

    // Get active_plugins option
    $stmt = $pdo->prepare("SELECT option_value FROM {$table_prefix}options WHERE option_name = 'active_plugins'");
    $stmt->execute();
    $value = $stmt->fetchColumn();

    echo "Current active_plugins value:\n";
    echo "Raw: " . var_export($value, true) . "\n\n";

    // Try to unserialize
    $plugins = @unserialize($value);

    if ($plugins === false && $value !== 'b:0;') {
        echo "ERROR: Corrupted serialized data!\n";
        echo "Resetting to empty array...\n\n";

        $new_value = serialize(array());
        $stmt = $pdo->prepare("UPDATE {$table_prefix}options SET option_value = ? WHERE option_name = 'active_plugins'");
        $stmt->execute([$new_value]);

        echo "Fixed! active_plugins reset to empty array.\n";
        echo "You can now activate plugins manually from wp-admin.\n";
    } else {
        echo "Unserialized successfully:\n";
        print_r($plugins);

        if (is_array($plugins)) {
            echo "\nTotal active plugins: " . count($plugins) . "\n";

            // Check for optipress
            $has_optipress = false;
            foreach ($plugins as $plugin) {
                if (strpos($plugin, 'optipress') !== false) {
                    $has_optipress = true;
                    echo "OptiPress found: $plugin\n";
                }
            }

            if (!$has_optipress) {
                echo "\nOptiPress not found in active plugins.\n";
                echo "Do you want to add it? (y/n): ";
                $handle = fopen("php://stdin", "r");
                $line = fgets($handle);
                if (trim($line) === 'y') {
                    $plugins[] = 'optipress/optipress.php';
                    $new_value = serialize($plugins);
                    $stmt = $pdo->prepare("UPDATE {$table_prefix}options SET option_value = ? WHERE option_name = 'active_plugins'");
                    $stmt->execute([$new_value]);
                    echo "Added OptiPress!\n";
                }
                fclose($handle);
            }
        }
    }

    echo "\nDone!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    echo "\nPlease update the database credentials at the top of this script.\n";
}
