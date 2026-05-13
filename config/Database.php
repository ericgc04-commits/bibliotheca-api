<?php
/**
 * config/Database.php
 * PDO singleton — call Database::getInstance() to get the shared connection.
 */

class Database {
    private static ?PDO $instance = null;

    /** Returns the shared PDO connection, creating it on first call. */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                // Do not expose credentials in error messages
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
                exit;
            }
        }
        return self::$instance;
    }

    // Prevent instantiation
    private function __construct() {}
    private function __clone() {}
}
