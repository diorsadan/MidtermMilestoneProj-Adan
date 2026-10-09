<?php
/**
 * classes/Comment.php
 * Add, update, delete, and list comments on a recipe.
 *
 * Used by: recipe.php (forRecipe), comment_add.php (add),
 * comment_edit.php (find, update), comment_delete.php (find, delete). Table: comments.
 */

declare(strict_types=1);

class Comment
{
    // Constructor: receives the ONE shared PDO connection from the page.
    // "private PDO $pdo" (PHP 8 shorthand) creates the property $this->pdo
    // and stores the connection in it automatically.
    public function __construct(private PDO $pdo)
    {
    }

    /** @return int the new comment id */
    public function add(int $recipeId, int $userId, string $body): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO comments (recipe_id, user_id, body) VALUES (?, ?, ?)');
        $stmt->execute([$recipeId, $userId, $body]);
        return (int) $this->pdo->lastInsertId();
    }

    /** One comment (with author name), or null if it does not exist. */
    public function find(int $commentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cm.*, u.username
               FROM comments cm
               JOIN users u ON u.id = cm.user_id
              WHERE cm.id = ?'
        );
        $stmt->execute([$commentId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Edit a comment. OWNERSHIP CHECK on the server: the WHERE requires both
     * the comment id AND the logged-in member's id, so nobody can edit
     * someone else's comment by changing the id in the form.
     *
     * @return bool false if not found or not the member's comment
     */
    public function update(int $commentId, int $userId, string $body): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE comments SET body = ?, updated_at = NOW() WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$body, $commentId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** Delete with the same ownership check. */
    public function delete(int $commentId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM comments WHERE id = ? AND user_id = ?');
        $stmt->execute([$commentId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /** All comments of a recipe, oldest first (reads like a conversation). */
    public function forRecipe(int $recipeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cm.id, cm.user_id, cm.body, cm.created_at, cm.updated_at, u.username
               FROM comments cm
               JOIN users u ON u.id = cm.user_id
              WHERE cm.recipe_id = ?
              ORDER BY cm.created_at ASC, cm.id ASC'
        );
        $stmt->execute([$recipeId]);
        return $stmt->fetchAll();
    }
}
