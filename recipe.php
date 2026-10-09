<?php
/**
 * recipe.php
 * One recipe: details, cooking checklist, favorite button, and comments.
 * Opened with a link like recipe.php?id=5
 */

// STEP 1: Shared setup, then block guests.
require_once __DIR__ . '/includes/auth.php';
require_login();

// STEP 2: Read the recipe id from the URL and load the recipe.
//   If the id is missing, not a number, or not in the database,
//   show a friendly "not found" page instead of a PHP error.
$recipeId    = input_id($_GET, 'id');
$recipeModel = new Recipe($pdo);
$recipe      = $recipeId ? $recipeModel->find($recipeId, current_user_id()) : null;

if (!$recipe) {
    show_not_found('That recipe does not exist or was deleted.');
}

// STEP 3: Load the rest of the data for this recipe.
//   $isOwner -> is the logged-in member the one who posted it? (shows Edit/Delete)
//   $isFav   -> did the logged-in member already save it? (fills the heart)
$ingredients = $recipeModel->ingredients($recipeId);
$comments    = (new Comment($pdo))->forRecipe($recipeId);
$isOwner     = (int) $recipe['user_id'] === current_user_id();
$isFav       = (bool) $recipe['is_favorite'];

// STEP 4: Turn the steps text into a list.
// Steps are stored as text; each non-empty line becomes one step.
// A leading "1." or "2)" typed by the member is removed because the <ol>
// already numbers the steps.
$steps = [];
foreach (preg_split('/\R/u', $recipe['steps']) as $line) {
    $line = trim(preg_replace('/^\s*\d+\s*[.)]\s*/u', '', $line));
    if ($line !== '') {
        $steps[] = $line;
    }
}

// STEP 5: Restore a rejected comment.
// If comment_add.php rejected a comment, it left the text + error here
// so we can show the form again with what the member typed.
$commentForm = $_SESSION['comment_form'][$recipeId] ?? ['body' => '', 'error' => ''];
unset($_SESSION['comment_form'][$recipeId]);

// STEP 6: Name of the localStorage key for the cooking checklist.
// The checklist is saved in localStorage under this key. It includes the
// edit time, so an edited recipe starts with a fresh checklist.
// (The "kusina-" prefix is from the site's old name; it is kept on purpose
// so members' saved checklist progress keeps working.)
$checklistKey = 'kusina-checklist-' . $recipeId . '-' . strtotime($recipe['updated_at'] ?? $recipe['created_at']);

// STEP 7: Show the page. The browser tab shows the recipe title.
$pageTitle = $recipe['title'];
require __DIR__ . '/includes/header.php';
?>
<!-- Back link to the recipe list. -->
<p class="breadcrumb"><a href="recipes.php">← Lahat ng recipe</a></p>

<article class="recipe-detail">
    <!-- ===== Recipe header: category, title, description, author, dates ===== -->
    <header class="panel recipe-header">
        <span class="chip"><?= e($recipe['category_name']) ?></span>
        <h1><?= e($recipe['title']) ?></h1>
        <p class="recipe-desc"><?= e($recipe['description']) ?></p>

        <!-- Author and posting date. The "edited" label only appears when
             updated_at is set (it is filled by Recipe::update()). -->
        <p class="card-meta">
            Ibinahagi ni <strong><?= e($recipe['username']) ?></strong> ·
            <time datetime="<?= e($recipe['created_at']) ?>"><?= e(format_date($recipe['created_at'])) ?></time>
            <?php if (!empty($recipe['updated_at'])): ?>
                <span class="edited-label">edited <?= e(format_date($recipe['updated_at'])) ?></span>
            <?php endif; ?>
        </p>

        <!-- ===== Action buttons ===== -->
        <div class="recipe-actions">
            <!-- Favorite button. main.js listens for clicks on .fav-btn and sends
                 data-recipe-id to favorite_toggle.php with fetch() (no page reload). -->
            <button type="button" class="fav-btn fav-btn-large<?= $isFav ? ' is-favorite' : '' ?>"
                    data-recipe-id="<?= (int) $recipe['id'] ?>"
                    aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                    aria-label="<?= $isFav ? 'Remove from favorites' : 'Save to favorites' ?>">
                <span class="fav-icon" aria-hidden="true"><?= $isFav ? '&#9829;&#xFE0E;' : '&#9825;' ?></span>
                <span class="fav-text"><?= $isFav ? 'Nasa Paborito' : 'I-save' ?></span>
                <span class="fav-count"><?= (int) $recipe['fav_count'] ?></span>
            </button>

            <?php if ($isOwner): ?>
                <!-- Buttons are only SHOWN to the owner; the server checks ownership again. -->
                <a class="btn btn-secondary" href="recipe_edit.php?id=<?= (int) $recipe['id'] ?>">Edit</a>
                <!-- Delete is a POST form (never a plain link) with a CSRF token.
                     data-confirm makes main.js ask "Are you sure?" first. -->
                <form action="recipe_delete.php" method="post" class="inline-form"
                      data-confirm="Sigurado ka ba? Are you sure you want to delete this recipe? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $recipe['id'] ?>">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            <?php endif; ?>
        </div>
    </header>

    <!-- ===== Cooking checklist (my extra feature) =====
         Progress saved per recipe in localStorage (main.js).
         data-checklist holds the storage key made in STEP 6. -->
    <div class="recipe-body" data-checklist="<?= e($checklistKey) ?>">
        <!-- Progress bar: "3 / 12 tapos na" + Reset button. main.js updates the numbers. -->
        <div class="checklist-bar panel">
            <p class="checklist-progress" aria-live="polite">
                <span class="checklist-done">0</span> / <span class="checklist-total"><?= count($ingredients) + count($steps) ?></span> tapos na
            </p>
            <div class="progress-track" aria-hidden="true"><div class="progress-fill"></div></div>
            <button type="button" class="btn btn-small btn-ghost checklist-reset">Reset checklist</button>
        </div>

        <!-- Ingredients with checkboxes. data-key="i0", "i1"... identifies each one in localStorage. -->
        <section class="panel recipe-ingredients">
            <h2>Mga Sangkap</h2>
            <ul class="checklist">
                <?php foreach ($ingredients as $i => $ingredient): ?>
                    <li>
                        <label>
                            <input type="checkbox" data-key="i<?= $i ?>">
                            <span><?= e($ingredient) ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- Steps with checkboxes. data-key="s0", "s1"... CSS adds the step numbers. -->
        <section class="panel recipe-steps">
            <h2>Paraan ng Pagluto</h2>
            <ol class="checklist checklist-steps">
                <?php foreach ($steps as $i => $step): ?>
                    <li>
                        <label>
                            <input type="checkbox" data-key="s<?= $i ?>">
                            <span><?= e($step) ?></span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>
</article>

<!-- ===== Comments ===== -->
<section class="panel comments" id="comments">
    <h2>Mga Komento (<?= count($comments) ?>)</h2>

    <?php if ($comments): ?>
        <!-- List of comments, oldest first. id="comment-5" lets other pages
             link straight to one comment (recipe.php?id=1#comment-5). -->
        <ul class="comment-list">
            <?php foreach ($comments as $comment): ?>
                <li class="comment" id="comment-<?= (int) $comment['id'] ?>">
                    <!-- Author, date, and "edited" label if the comment was changed. -->
                    <p class="comment-meta">
                        <strong><?= e($comment['username']) ?></strong>
                        <time datetime="<?= e($comment['created_at']) ?>"><?= e(format_date($comment['created_at'])) ?></time>
                        <?php if (!empty($comment['updated_at'])): ?>
                            <span class="edited-label">edited <?= e(format_date($comment['updated_at'])) ?></span>
                        <?php endif; ?>
                    </p>
                    <!-- e() escapes the comment; nl2br() runs AFTER escaping to keep line breaks. -->
                    <p class="comment-body"><?= nl2br(e($comment['body'])) ?></p>

                    <!-- Edit/Delete only for the comment's author (checked again on the server). -->
                    <?php if ((int) $comment['user_id'] === current_user_id()): ?>
                        <div class="comment-actions">
                            <a class="btn btn-small btn-ghost" href="comment_edit.php?id=<?= (int) $comment['id'] ?>">Edit</a>
                            <form action="comment_delete.php" method="post" class="inline-form"
                                  data-confirm="Are you sure you want to delete this comment?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
                                <button type="submit" class="btn btn-small btn-ghost btn-ghost-danger">Delete</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p class="muted">Wala pang komento. Be the first to say "Ang sarap!"</p>
    <?php endif; ?>

    <!-- ===== New comment form =====
         Sends to comment_add.php. The hidden recipe_id says which recipe it belongs to.
         After an error, the textarea is refilled with $commentForm['body'] (STEP 5).
         data-counter makes main.js show "0 / 500" under the box. -->
    <form action="comment_add.php" method="post" class="comment-form" id="comment-form" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="recipe_id" value="<?= (int) $recipe['id'] ?>">
        <div class="field<?= $commentForm['error'] ? ' has-error' : '' ?>">
            <label for="body">Mag-iwan ng komento</label>
            <textarea id="body" name="body" rows="3" required maxlength="500" data-counter
                      placeholder="Nasubukan mo na ba? Share your tips!"
                      aria-describedby="body-error"
                      data-error="Comment must be 1 to 500 characters."><?= e($commentForm['body']) ?></textarea>
            <p class="field-error" id="body-error" aria-live="polite"><?= e($commentForm['error']) ?></p>
        </div>
        <button type="submit" class="btn btn-primary">I-post ang komento</button>
    </form>
</section>

<?php // STEP 8: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
