<?php
require_once __DIR__ . '/config.php';

/**
 * Returns a shared PDO instance.
 */
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            // Ensure visit_code column exists in visit_logs
            try {
                $pdo->query("SELECT visit_code FROM visit_logs LIMIT 1");
            } catch (PDOException $ex) {
                // Column doesn't exist, let's add it
                $pdo->exec("ALTER TABLE visit_logs ADD COLUMN visit_code VARCHAR(20) NULL UNIQUE");
                // Update any existing records to have a code based on their ID
                $pdo->exec("UPDATE visit_logs SET visit_code = CONCAT('VMS-', id) WHERE visit_code IS NULL");
            }

            // Ensure visit_companions table exists
            try {
                $pdo->query("SELECT 1 FROM visit_companions LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `visit_companions` (
                        `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                        `visit_log_id` INT UNSIGNED  NOT NULL,
                        `full_name`    VARCHAR(100)  NOT NULL,
                        `relationship` VARCHAR(100)  NOT NULL,
                        `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        CONSTRAINT `fk_vc_visit_log` FOREIGN KEY (`visit_log_id`) REFERENCES `visit_logs` (`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }

            // Ensure visitor_restrictions table exists
            try {
                $pdo->query("SELECT 1 FROM visitor_restrictions LIMIT 1");
            } catch (PDOException $ex) {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS `visitor_restrictions` (
                        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        `resident_id` INT UNSIGNED NOT NULL,
                        `requested_by_name` VARCHAR(100) NOT NULL,
                        `requested_by_relation` VARCHAR(50) NOT NULL,
                        `contact_info` VARCHAR(100) NULL,
                        `restriction_date` DATE NOT NULL,
                        `reason` TEXT NOT NULL,
                        `allowed_visitors` TEXT NULL,
                        `status` ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
                        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        CONSTRAINT `fk_vr_resident` FOREIGN KEY (`resident_id`) REFERENCES `residents`(`id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ");
            }
        } catch (PDOException $e) {
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            $msg  = htmlspecialchars($e->getMessage(), ENT_QUOTES);
            die(<<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Database Error</title>
<style>
  body{font-family:sans-serif;background:#f0f4f8;display:flex;align-items:center;
       justify-content:center;min-height:100vh;margin:0}
  .card{background:#fff;border-radius:12px;padding:40px;max-width:560px;
        box-shadow:0 8px 30px rgba(0,0,0,.12);border-top:5px solid #ef4444}
  h2{color:#991b1b;margin-top:0}code{background:#fee2e2;padding:2px 6px;border-radius:4px}
  a{color:#1a5f7a}
</style></head><body>
<div class="card">
  <h2>⚠️ Database Connection Failed</h2>
  <p>Could not connect to MySQL. Please verify <code>includes/config.php</code>.</p>
  <p><strong>Error:</strong> $msg</p>
  <p>Haven't set up the database yet? <a href="{$base}setup.php">Run setup.php</a></p>
</div></body></html>
HTML);
        }
    }

    return $pdo;
}
