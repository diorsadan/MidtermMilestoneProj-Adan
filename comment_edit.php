<?php
/**
 * comment_edit.php
 * Edit your own comment. Shows the form on GET, saves on POST.
 * Opened with a link like comment_edit.php?id=7
 */

// STEP 1: Shared setup, then block guests.
require_once __DIR__ . '/includes/auth.php';
require_login();

// STEP 2: Load the comment from the id in the URL.
$commentModel = new Comment($pdo);
$commentId    = input_id($_GET, 'id');
$comment      = $commentId ? $commentModel->find($commentId) : null;

// Not in the database -> friendly 404 page.
if (!$comment) {
    show_not_found('That comment does not exist or was deleted.');
}

// STEP 3: Ownership check.
// Friendly message for non-owners. The REAL protection is the
// WHERE id = ? AND user_id = ? inside Comment::update().
if ((int) $comment['user_id'] !== current_user_id()) {
    show_forbidden('You can only edit your own comments.');
}

// STEP 4: Default values: the comment's current text, no error yet.
//   $recipeId is used to go back to the right recipe afterwards.
$recipeId = (int) $comment['recipe_id'];
$body     = $comment['body'];
$error    = '';

// STEP 5: Only runs when the form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 5a. Reject forged requests (CSRF token check).
    verify_csrf();

    // 5b. Read and validate the new text (1 to 500 characters).
    $body   = input_str($_POST, 'body');
    $errors = Validator::comment($body);

    // 5c. Valid: save it (sets updated_at = NOW() for the "edited" label)
    //     and jump back to this comment on the recipe page.
    if (!$errors) {
        if ($commentModel->update($commentId, current_user_id(), $body)) {
            flash('success', 'Na-update ang komento.');
            redirect('recipe.php?id=' . $recipeId . '#comment-' . $commentId);
        }
        show_forbidden('This comment could not be updated. It may have been deleted, or it is not yours.');
    }
    // 5d. Invalid: keep the error message to show under the textarea.
    $error = $errors['body'];
}

// STEP 6: Show the page.
$pageTitle = 'Edit comment';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Edit comment form ===== -->
<section class="panel form-panel">
    <h1>I-edit ang komento</h1>
    <p class="muted">Others will see an “edited” label after you save.</p>

    <!-- Sent back to this page with the comment id. data-validate = browser check in main.js. -->
    <form method="post" action="comment_edit.php?id=<?= (int) $commentId ?>" data-validate>
        <?= csrf_field() ?>
        <!-- The textarea is filled with the current (or just-typed) text, escaped with e(). -->
        <div class="field<?= $error ? ' has-error' : '' ?>">
            <label for="body">Komento</label>
            <textarea id="body" name="body" rows="4" required maxlength="500" data-counter
                      aria-describedby="body-error"
                      data-error="Comment must be 1 to 500 characters."><?= e($body) ?></textarea>
            <p class="field-error" id="body-error" aria-live="polite"><?= e($error) ?></p>
        </div>
        <!-- Save, or Cancel back to the comment without changing anything. -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">I-save</button>
            <a class="btn btn-ghost" href="recipe.php?id=<?= $recipeId ?>#comment-<?= (int) $commentId ?>">Cancel</a>
        </div>
    </form>
</section>
<?php // STEP 7: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
