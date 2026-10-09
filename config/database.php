<?php
/**
 * config/database.php
 *
 * The ONE place in the whole project that creates a PDO connection.
 * Every page gets the connection through Database::connect() (included by
 * includes/auth.php), and every class receives it through its constructor.
 */

declare(strict_types=1);

class Database
{
    private const HOST    = 'localhost';
    private const DB_NAME = 'kusina_db';
    private const USER    = 'root';
    private const PASS    = '';

    /** Shared connection so a request never opens more than one. */
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . self::HOST . ';dbname=' . self::DB_NAME . ';charset=utf8mb4';

            $options = [
                // Throw a PDOException on every SQL error, so try/catch and
                // transactions (rollBack) work reliably.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Use REAL prepared statements: the SQL and the values are sent
                // to MySQL separately, so user input can never become SQL code.
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Rows come back as ['column' => value] arrays.
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // rowCount() on UPDATE returns rows MATCHED by the WHERE, not
                // only rows whose values changed. This lets the ownership checks
                // (WHERE id = ? AND user_id = ?) tell "not yours" apart from
                // "saved without changes".
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
                // XAMPP's MariaDB is not in strict mode by default, so text that
                // is too long would be silently cut off. Strict mode makes MySQL
                // throw an error instead (which then triggers our rollBack()).
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'",
            ];

            try {
                self::$pdo = new PDO($dsn, self::USER, self::PASS, $options);
            } catch (PDOException $e) {
                // Log the real reason for the developer, show a friendly message
                // to the visitor. Raw DB errors are never shown on screen.
                error_log('Database connection failed: ' . $e->getMessage());
                http_response_code(500);
                exit('Sorry, the kitchen is closed right now (database unavailable). Please try again later.');
            }
        }

        return self::$pdo;
    }
}
