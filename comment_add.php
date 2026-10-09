<?php
/**
 * comment_add.php
 * Adds a comment to a recipe (POST only), then returns to the recipe page.
 * This file has no HTML of its own: it only processes the form on recipe.php.
 */

// STEP 1: Security checks, in order:
//   shared setup -> must be logged in -> must be a POST -> CSRF token must match.
require_once __DIR__ . '/includes/auth.php';
require_login();
require_post();
verify_csrf();

// STEP 2: Read the hidden recipe id and the comment text from the form.
$recipeId = input_id($_POST, 'recipe_id');
$body     = input_str($_POST, 'body');

// STEP 3: The recipe must exist before we attach a comment to it.
if (!$recipeId || !(new Recipe($pdo))->find($recipeId, current_user_id())) {
    show_not_found('You tried to comment on a recipe that does not exist.');
}

// STEP 4: Server-side validation (1 to 500 characters).
$errors = Validator::comment($body);

if ($errors) {
    // Send the member back with their text and the error message.
    // recipe.php reads $_SESSION['comment_form'] to refill the textarea.
    $_SESSION['comment_form'][$recipeId] = ['body' => $body, 'error' => $errors['body']];
    redirect('recipe.php?id=' . $recipeId . '#comment-form');
}

// STEP 5: Save the comment, then jump to it on the recipe page (#comment-ID).
$commentId = (new Comment($pdo))->add($recipeId, current_user_id(), $body);
flash('success', 'Salamat sa komento!');
redirect('recipe.php?id=' . $recipeId . '#comment-' . $commentId);
