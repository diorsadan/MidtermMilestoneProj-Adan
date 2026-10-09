<?php
/**
 * classes/Recipe.php
 *
 * Create / update (with PDO transactions), delete, find and search recipes.
 *
 * Used by: recipes.php (search), recipe.php (find, ingredients),
 * recipe_create.php (create), recipe_edit.php (find, ingredients, update),
 * recipe_delete.php (delete). Tables: recipes + ingredients (and comments,
 * favorites when deleting).
 */

declare(strict_types=1);

class Recipe
{
    // Constructor: receives the ONE shared PDO connection from the page.
    // "private PDO $pdo" (PHP 8 shorthand) creates the property $this->pdo
    // and stores the connection in it automatically.
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Create a recipe AND its ingredients as one unit of work.
     *
     * TRANSACTION: two tables are written (recipes, then ingredients).
     * beginTransaction() makes MySQL hold all the changes until commit().
     * If ANY insert fails, rollBack() undoes everything, so the database never
     * ends up with a recipe that has no (or only some) ingredients.
     *
     * @return int the new recipe id
     */
    public function create(int $userId, int $categoryId, string $title, string $description, string $steps, array $ingredients): int
    {
        try {
            $this->pdo->beginTransaction();

            // 1) The recipe row. Named placeholders, values bound separately.
            $stmt = $this->pdo->prepare(
                'INSERT INTO recipes (user_id, category_id, title, description, steps)
                 VALUES (:user_id, :category_id, :title, :description, :steps)'
            );
            $stmt->execute([
                ':user_id'     => $userId,
                ':category_id' => $categoryId,
                ':title'       => $title,
                ':description' => $description,
                ':steps'       => $steps,
            ]);
            $recipeId = (int) $this->pdo->lastInsertId();

            // 2) One row per ingredient, linked by recipe_id.
            $this->insertIngredients($recipeId, $ingredients);

            // Everything worked: make the changes permanent.
            $this->pdo->commit();
            return $recipeId;
        } catch (Throwable $e) {
            // Something failed: undo the half-finished work, then let the
            // page decide what to show the user.
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update a recipe and REPLACE its ingredients, all in one transaction:
     * update recipes -> delete old ingredients -> insert new ingredients.
     *
     * OWNERSHIP CHECK: every query uses WHERE id = ? AND user_id = ?, so a
     * member can only edit their own recipe, even if they type another id
     * into the URL or form.
     *
     * @return bool false if the recipe does not exist or is not the member's
     */
    public function update(int $recipeId, int $userId, int $categoryId, string $title, string $description, string $steps, array $ingredients): bool
    {
        try {
            $this->pdo->beginTransaction();

            // 1) Update the recipe row (only if it belongs to this member).
            //    updated_at = NOW() powers the "edited" label.
            $stmt = $this->pdo->prepare(
                'UPDATE recipes
                    SET category_id = :category_id, title = :title, description = :description,
                        steps = :steps, updated_at = NOW()
                  WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                ':category_id' => $categoryId,
                ':title'       => $title,
                ':description' => $description,
                ':steps'       => $steps,
                ':id'          => $recipeId,
                ':user_id'     => $userId,
            ]);

            // No row matched = wrong id or not the owner. Undo and stop.
            // (rowCount counts MATCHED rows thanks to MYSQL_ATTR_FOUND_ROWS.)
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            // 2) Remove the old ingredient list.
            $delete = $this->pdo->prepare('DELETE FROM ingredients WHERE recipe_id = ?');
            $delete->execute([$recipeId]);

            // 3) Insert the new ingredient list.
            $this->insertIngredients($recipeId, $ingredients);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Delete a recipe the member owns. Its ingredients, comments and
     * favorites are removed too. The foreign keys have ON DELETE CASCADE,
     * but we delete them explicitly inside a transaction so the whole
     * clean-up is one all-or-nothing step and easy to follow.
     *
     * @return bool false if the recipe does not exist or is not the member's
     */
    public function delete(int $recipeId, int $userId): bool
    {
        try {
            $this->pdo->beginTransaction();

            // Ownership check first; FOR UPDATE locks the row until commit.
            $check = $this->pdo->prepare('SELECT id FROM recipes WHERE id = ? AND user_id = ? FOR UPDATE');
            $check->execute([$recipeId, $userId]);
            if (!$check->fetchColumn()) {
                $this->pdo->rollBack();
                return false;
            }

            foreach (['ingredients', 'comments', 'favorites'] as $table) {
                // Table names come from this fixed list (never from user input);
                // the recipe id is still a bound parameter.
                $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE recipe_id = ?");
                $stmt->execute([$recipeId]);
            }

            $stmt = $this->pdo->prepare('DELETE FROM recipes WHERE id = ? AND user_id = ?');
            $stmt->execute([$recipeId, $userId]);

            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** One recipe with author, category and favorite info, or null if not found. */
    public function find(int $recipeId, int $viewerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, u.username, c.name AS category_name,
                    (SELECT COUNT(*) FROM favorites f WHERE f.recipe_id = r.id) AS fav_count,
                    EXISTS(SELECT 1 FROM favorites f2 WHERE f2.recipe_id = r.id AND f2.user_id = :viewer) AS is_favorite
               FROM recipes r
               JOIN users u      ON u.id = r.user_id
               JOIN categories c ON c.id = r.category_id
              WHERE r.id = :id'
        );
        $stmt->execute([':viewer' => $viewerId, ':id' => $recipeId]);
        return $stmt->fetch() ?: null;
    }

    /** Ingredient names of a recipe, in the order they were entered. */
    public function ingredients(int $recipeId): array
    {
        $stmt = $this->pdo->prepare('SELECT name FROM ingredients WHERE recipe_id = ? ORDER BY id');
        $stmt->execute([$recipeId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Browse / search / filter, newest first.
     * $keyword matches the title, description OR any ingredient name.
     * Both filters are optional and can be combined.
     */
    public function search(string $keyword, int $categoryId, int $viewerId): array
    {
        $sql = 'SELECT r.id, r.title, r.description, r.created_at, r.updated_at, r.user_id,
                       u.username, c.name AS category_name,
                       (SELECT COUNT(*) FROM favorites f WHERE f.recipe_id = r.id) AS fav_count,
                       EXISTS(SELECT 1 FROM favorites f2 WHERE f2.recipe_id = r.id AND f2.user_id = :viewer) AS is_favorite
                  FROM recipes r
                  JOIN users u      ON u.id = r.user_id
                  JOIN categories c ON c.id = r.category_id
                 WHERE 1 = 1';
        $params = [':viewer' => $viewerId];

        if ($keyword !== '') {
            // The keyword is NOT pasted into the SQL. We only add placeholders
            // here; the value is bound below. % and _ typed by the user are
            // escaped so they are searched literally, not as wildcards.
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $sql .= ' AND (r.title LIKE :kw1
                       OR r.description LIKE :kw2
                       OR EXISTS (SELECT 1 FROM ingredients i WHERE i.recipe_id = r.id AND i.name LIKE :kw3))';
            // Real prepared statements cannot reuse one name, so 3 copies.
            $params[':kw1'] = $like;
            $params[':kw2'] = $like;
            $params[':kw3'] = $like;
        }

        if ($categoryId > 0) {
            $sql .= ' AND r.category_id = :category_id';
            $params[':category_id'] = $categoryId;
        }

        $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT 100';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Insert ingredient rows. Only called inside a transaction above. */
    private function insertIngredients(int $recipeId, array $ingredients): void
    {
        // Prepare once, execute many times with different bound values.
        $stmt = $this->pdo->prepare('INSERT INTO ingredients (recipe_id, name) VALUES (?, ?)');
        foreach ($ingredients as $name) {
            $stmt->execute([$recipeId, $name]);
        }
    }
}
