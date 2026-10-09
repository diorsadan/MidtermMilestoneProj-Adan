<?php
/**
 * recipes.php
 * Browse recipes (newest first), keyword search, and category filter.
 * Uses GET so a search like recipes.php?q=adobo&category=2 can be shared.
 */

// STEP 1: Shared setup, then block guests (they are sent to login.php).
require_once __DIR__ . '/includes/auth.php';
require_login();

// STEP 2: Read the search box (?q=) and category dropdown (?category=) from the URL.
//   mb_substr() limits the keyword to 100 characters.
//   input_id() returns 0 if the category is missing or not a valid number.
$keyword    = mb_substr(input_str($_GET, 'q'), 0, 100);
$categoryId = input_id($_GET, 'category');

// STEP 3: Get the data from the database through the classes.
//   - all categories for the dropdown
//   - the matching recipes, newest first (see Recipe::search())
$categories = (new Category($pdo))->all();
$recipes    = (new Recipe($pdo))->search($keyword, $categoryId, current_user_id());

// STEP 4: Is the member searching/filtering? Used to show "Clear" and the right empty message.
$isFiltered = $keyword !== '' || $categoryId > 0;

// STEP 5: Show the page.
$pageTitle = 'Mga Recipe';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Page heading ===== -->
<section class="hero">
    <h1>Ano'ng ulam natin ngayon?</h1>
    <p>Mga lutong-bahay mula sa ating mga kapitbahay. Home-cooked recipes from our neighbors.</p>
</section>

<!-- ===== Search + category filter =====
     method="get" puts the values in the URL, so a search can be bookmarked or shared.
     Both values are refilled so the member sees what they searched for. -->
<form class="search-bar panel" method="get" action="recipes.php" role="search">
    <!-- Keyword box (matches title, description, or ingredient names). -->
    <div class="field">
        <label for="q">Hanapin (search)</label>
        <input type="search" id="q" name="q" value="<?= e($keyword) ?>" maxlength="100"
               placeholder="Title, description, or ingredient (e.g. gata)">
    </div>
    <!-- Category dropdown, built from the categories table.
         The chosen category gets "selected" so it stays chosen after searching. -->
    <div class="field">
        <label for="category">Kategorya</label>
        <select id="category" name="category">
            <option value="">Lahat (all)</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- Search button, plus a "Clear" link only when a filter is active. -->
    <div class="search-actions">
        <button type="submit" class="btn btn-primary">Hanapin</button>
        <?php if ($isFiltered): ?>
            <a class="btn btn-ghost" href="recipes.php">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- ===== Result count, e.g. "3 recipes matching "adobo"" ===== -->
<p class="result-count">
    <?= count($recipes) ?> recipe<?= count($recipes) === 1 ? '' : 's' ?>
    <?php if ($keyword !== ''): ?> matching “<strong><?= e($keyword) ?></strong>”<?php endif; ?>
</p>

<?php if ($recipes): ?>
    <!-- ===== Recipe grid =====
         One card per recipe. recipe_card.php uses the $recipe variable of this loop.
         CSS makes this 1 column on phones, 2 on tablets, 3 on desktop. -->
    <div class="recipe-grid">
        <?php foreach ($recipes as $recipe): ?>
            <?php require __DIR__ . '/includes/recipe_card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- ===== Empty state =====
         Different message for "nothing matched your search" vs "no recipes yet". -->
    <section class="panel empty-state">
        <?php if ($isFiltered): ?>
            <h2>Walang nahanap.</h2>
            <p>No recipes match your search. Try another word or category.</p>
        <?php else: ?>
            <h2>Wala pang recipe.</h2>
            <p>Be the first to share a dish with the barangay!</p>
        <?php endif; ?>
        <p><a class="btn btn-primary" href="recipe_create.php">Magbahagi ng recipe</a></p>
    </section>
<?php endif; ?>

<?php // STEP 6: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
