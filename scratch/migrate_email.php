<?php
define('BASE_PATH', '../');
require_once __DIR__ . '/../includes/db.php';
$db = getDB();

try {
    // Check if column 'username' exists
    $columns = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('username', $columns)) {
        // Change username to email
        $db->exec("ALTER TABLE users CHANGE COLUMN username email VARCHAR(100) NOT NULL");
        echo "Successfully renamed username column to email.\n";
    } else {
        echo "Column 'username' already migrated or does not exist.\n";
    }

    // Drop index uq_username if exists
    try {
        $db->exec("ALTER TABLE users DROP INDEX uq_username");
        echo "Dropped index uq_username.\n";
    } catch (Exception $e) {}

    // Add unique key uq_email
    try {
        $db->exec("ALTER TABLE users ADD UNIQUE KEY uq_email (email)");
        echo "Added unique key uq_email.\n";
    } catch (Exception $e) {}

    // Update users:
    // If user is '__kiosk__', update to 'kiosk@system.local'
    // For other users, if not already formatted as email, append '@system.local'
    $users = $db->query("SELECT id, email FROM users")->fetchAll();
    foreach ($users as $u) {
        $old = $u['email'];
        if ($old === '__kiosk__') {
            $db->prepare("UPDATE users SET email = 'kiosk@system.local' WHERE id = ?")->execute([$u['id']]);
            echo "Updated system kiosk user to kiosk@system.local.\n";
        } elseif (strpos($old, '@') === false) {
            $new = $old . '@system.local';
            $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$new, $u['id']]);
            echo "Updated user '{$old}' to '{$new}'.\n";
        }
    }

    echo "Migration completed successfully!\n";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
