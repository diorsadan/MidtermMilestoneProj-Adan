<?php
/**
 * classes/Category.php
 * Read-only list of recipe categories (Ulam, Sabaw, Merienda...).
 *
 * Used by: recipes.php (filter dropdown), recipe_create.php and recipe_edit.php
 * (dropdown + checking that the chosen category exists). Table: categories.
 */

declare(strict_types=1);

class Category
{
    // Constructor: receives the ONE shared PDO connection from the page.
    // "private PDO $pdo" (PHP 8 shorthand) creates the property $this->pdo
    // and stores the connection in it automatically.
    public function __construct(private PDO $pdo)
    {
    }

    /** All categories, sorted by name, for the dropdowns. */
    public function all(): array
    {
        // No user input here, so a plain query() is safe.
        return $this->pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
    }

    /** Used by validation: is this a real category id? */
    public function exists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return (bool) $stmt->fetchColumn();
    }
}
