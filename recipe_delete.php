<?php
/**
 * recipe_delete.php
 * Deletes your own recipe. POST only (never a GET link) + CSRF token.
 * This file has no HTML: it is the target of the Delete form on recipe.php.
 */

// STEP 1: Security checks: logged in, POST request, valid CSRF token.
require_once __DIR__ . '/includes/auth.php';
require_login();
require_post();
verify_csrf();

// STEP 2: Read the recipe id from the hidden form field.
$recipeId = input_id($_POST, 'id');

// STEP 3: Try to delete it.
// Recipe::delete() checks ownership with WHERE id = ? AND user_id = ?
// inside a transaction, so other members' recipes cannot be deleted.
if ($recipeId && (new Recipe($pdo))->delete($recipeId, current_user_id())) {
    flash('success', 'Natanggal na ang recipe. The recipe was deleted.');
    redirect('recipes.php');
}

// STEP 4: Not deleted (wrong id or not the owner): explain and go back.
flash('error', 'That recipe could not be deleted. It may not exist, or it is not yours.');
redirect($recipeId ? 'recipe.php?id=' . $recipeId : 'recipes.php');
