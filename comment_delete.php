<?php
/**
 * comment_delete.php
 * Deletes your own comment. POST only + CSRF token.
 * This file has no HTML: it is the target of the Delete form on recipe.php.
 */

// STEP 1: Security checks: logged in, POST request, valid CSRF token.
require_once __DIR__ . '/includes/auth.php';
require_login();
require_post();
verify_csrf();

// STEP 2: Load the comment from the hidden id field.
$commentModel = new Comment($pdo);
$commentId    = input_id($_POST, 'id');
$comment      = $commentId ? $commentModel->find($commentId) : null;

// Not in the database -> friendly 404 page.
if (!$comment) {
    show_not_found('That comment does not exist or was already deleted.');
}

// Remember which recipe to return to.
$recipeId = (int) $comment['recipe_id'];

// STEP 3: Delete it, but only if it belongs to the logged-in member.
// Comment::delete() uses WHERE id = ? AND user_id = ? (server-side ownership check).
if ($commentModel->delete($commentId, current_user_id())) {
    flash('success', 'Natanggal ang komento.');
} else {
    flash('error', 'You can only delete your own comments.');
}

// STEP 4: Go back to the comments section of the recipe.
redirect('recipe.php?id=' . $recipeId . '#comments');
