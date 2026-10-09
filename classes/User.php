<?php
/**
 * classes/User.php
 * Register, log in, and find members.
 *
 * Used by: register.php (usernameExists, emailExists, register), login.php (login).
 * Table: users.
 */

declare(strict_types=1);

class User
{
    // Constructor: receives the ONE shared PDO connection from the page.
    // "private PDO $pdo" (PHP 8 shorthand) creates the property $this->pdo
    // and stores the connection in it automatically.
    public function __construct(private PDO $pdo)
    {
    }

    public function usernameExists(string $username): bool
    {
        // Prepared statement: the ? placeholder is filled in by MySQL, never
        // glued into the SQL string, so input cannot change the query.
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        return (bool) $stmt->fetchColumn();
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Create a member and return the new id.
     * Returns 0 if the username/email was taken at the same moment by someone
     * else (the UNIQUE keys in the database catch this race).
     */
    public function register(string $username, string $email, string $password): int
    {
        // password_hash() makes a salted bcrypt hash. Only this hash is stored;
        // the plain password is never saved or logged anywhere.
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :hash)'
            );
            $stmt->execute([
                ':username' => $username,
                ':email'    => $email,
                ':hash'     => $hash,
            ]);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // duplicate key
                return 0;
            }
            throw $e;
        }
    }

    /**
     * Check a username-or-email and password. Returns the user row on
     * success, or null if the login is wrong (we never say WHICH part was
     * wrong, so attackers cannot discover valid usernames).
     */
    public function login(string $identifier, string $password): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, username, email, password_hash FROM users WHERE username = :u OR email = :e LIMIT 1'
        );
        $stmt->execute([':u' => $identifier, ':e' => $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            return null;
        }

        // password_verify() hashes the typed password with the stored salt
        // and compares it to the stored hash.
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // If PHP's default algorithm got stronger since this hash was made,
        // quietly upgrade the stored hash.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $update = $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }

        unset($user['password_hash']); // never pass the hash around
        return $user;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
