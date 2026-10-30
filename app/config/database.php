<?php
/**
 * Database Connection Helper (PDO Singleton)
 * N.A Fresh Fruits & Coconuts
 */

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                $cleanMsg = self::sanitizeErrorMessage($e->getMessage());
                throw new Exception("Unable to connect to MySQL database '" . DB_NAME . "': " . $cleanMsg);
            }
        }
        return self::$instance;
    }

    public static function resetConnection(): void {
        self::$instance = null;
    }

    public static function testConnection(string $host, string $port, string $dbname, string $user, string $pass, bool $createDb = false): array {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ];

        // 1. Direct connection test to the target database first
        try {
            $dsnWithDb = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $pdo = new PDO($dsnWithDb, $user, $pass, $options);
            
            // Verify query permissions on this database
            $pdo->query("SHOW TABLES");

            return [
                'success' => true,
                'error' => null,
                'db_exists' => true,
                'db_created' => false
            ];
        } catch (PDOException $e) {
            $errCode = (int)$e->getCode();
            $errMsg = $e->getMessage();

            // 1049: Unknown database
            if ($errCode === 1049 || str_contains($errMsg, '1049') || str_contains($errMsg, 'Unknown database')) {
                if ($createDb) {
                    try {
                        $dsnNoDb = "mysql:host={$host};port={$port};charset=utf8mb4";
                        $pdoRoot = new PDO($dsnNoDb, $user, $pass, $options);
                        $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        
                        // Re-test connection to newly created database
                        new PDO("mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4", $user, $pass, $options);
                        return [
                            'success' => true,
                            'error' => null,
                            'db_exists' => true,
                            'db_created' => true
                        ];
                    } catch (PDOException $createErr) {
                        return [
                            'success' => false,
                            'error' => "Database '{$dbname}' does not exist, and user '{$user}' does not have CREATE DATABASE privileges. In your hosting cPanel, please open 'MySQL Databases', create the database '{$dbname}', add user '{$user}', and grant 'ALL PRIVILEGES'.",
                            'db_exists' => false
                        ];
                    }
                }

                return [
                    'success' => false,
                    'error' => "Database '{$dbname}' does not exist on this MySQL server. In your hosting cPanel, please create database '{$dbname}' and assign user '{$user}' with 'ALL PRIVILEGES'.",
                    'db_exists' => false
                ];
            }

            // 1044: Access denied for user to database
            if ($errCode === 1044 || str_contains($errMsg, '1044') || str_contains($errMsg, 'Access denied for user') && str_contains($errMsg, 'to database')) {
                return [
                    'success' => false,
                    'error' => "Access denied for user '{$user}' to database '{$dbname}'. In your hosting cPanel, please navigate to 'MySQL Databases', scroll to 'Add User to Database', select user '{$user}' and database '{$dbname}', and grant 'ALL PRIVILEGES'.",
                    'db_exists' => true
                ];
            }

            // 1045: Authentication failed (bad password or username)
            if ($errCode === 1045 || str_contains($errMsg, '1045') || str_contains($errMsg, 'using password')) {
                return [
                    'success' => false,
                    'error' => "Authentication failed for user '{$user}'. Please verify your MySQL username and password.",
                    'db_exists' => false
                ];
            }

            // 2002: Connection refused / host unreachable
            if ($errCode === 2002 || str_contains($errMsg, '2002') || str_contains($errMsg, 'Connection refused')) {
                return [
                    'success' => false,
                    'error' => "Could not connect to MySQL server at '{$host}:{$port}'. Verify host name and port (usually 'localhost' or '127.0.0.1' on cPanel).",
                    'db_exists' => false
                ];
            }

            return [
                'success' => false,
                'error' => "Connection error: " . self::sanitizeErrorMessage($errMsg),
                'db_exists' => false
            ];
        }
    }

    public static function isReady(): bool {
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->query("SHOW TABLES LIKE 'products'");
            return (bool)$stmt->fetchColumn();
        } catch (Exception $e) {
            return false;
        }
    }

    public static function sanitizeErrorMessage(string $msg): string {
        // Strip out passwords and sensitive paths
        $msg = preg_replace('/(using password: )(YES|NO)/i', '$1[PROTECTED]', $msg);
        $msg = preg_replace('/password=\S+/i', 'password=[PROTECTED]', $msg);
        return $msg;
    }
}
