<?php
/**
 * classes/Favorite.php
 * Save / unsave recipes ("Mga Paborito").
 * The favorites table's primary key is (user_id, recipe_id), so a member
 * can save the same recipe only once.
 *
 * Used by: favorite_toggle.php (toggle, count), favorites.php (forUser). Table: favorites.
 */

declare(strict_types=1);

class Favorite
{
    // Constructor: receives the ONE shared PDO connection from the page.
    // "private PDO $pdo" (PHP 8 shorthand) creates the property $this->pdo
    // and stores the connection in it automatically.
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Save the recipe if it is not saved yet, otherwise remove it.
     * @return bool true if the recipe is now a favorite
     */
    public function toggle(int $userId, int $recipeId): bool
    {
        // Try to remove first. If a row was removed, it WAS a favorite.
        $delete = $this->pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND recipe_id = ?');
        $delete->execute([$userId, $recipeId]);
        if ($delete->rowCount() > 0) {
            return false;
        }

        // Otherwise add it. INSERT IGNORE skips the duplicate if two clicks
        // arrive at the same moment.
        $insert = $this->pdo->prepare('INSERT IGNORE INTO favorites (user_id, recipe_id) VALUES (?, ?)');
        $insert->execute([$userId, $recipeId]);
        return true;
    }

    public function isFavorite(int $userId, int $recipeId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND recipe_id = ?');
        $stmt->execute([$userId, $recipeId]);
        return (bool) $stmt->fetchColumn();
    }

    /** How many members saved this recipe. */
    public function count(int $recipeId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM favorites WHERE recipe_id = ?');
        $stmt->execute([$recipeId]);
        return (int) $stmt->fetchColumn();
    }

    /** The member's saved recipes, most recently saved first (same columns as Recipe::search). */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.title, r.description, r.created_at, r.updated_at, r.user_id,
                    u.username, c.name AS category_name,
                    (SELECT COUNT(*) FROM favorites f2 WHERE f2.recipe_id = r.id) AS fav_count,
                    1 AS is_favorite
               FROM favorites f
               JOIN recipes r    ON r.id = f.recipe_id
               JOIN users u      ON u.id = r.user_id
               JOIN categories c ON c.id = r.category_id
              WHERE f.user_id = ?
              ORDER BY f.created_at DESC, r.id DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
