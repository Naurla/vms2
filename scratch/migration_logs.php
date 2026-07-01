<?php
require __DIR__ . '/../includes/config.php';
require __DIR__ . '/../includes/db.php';

$db = getDB();

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS `activity_logs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NULL,
            `action` VARCHAR(255) NOT NULL,
            `details` TEXT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "Created activity_logs table successfully.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
