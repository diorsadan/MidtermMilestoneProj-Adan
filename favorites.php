<?php
/**
 * favorites.php
 * "Mga Paborito": the recipes the logged-in member has saved.
 */

// STEP 1: Shared setup, then block guests.
require_once __DIR__ . '/includes/auth.php';
require_login();

// STEP 2: Get only THIS member's saved recipes (newest saved first).
$recipes = (new Favorite($pdo))->forUser(current_user_id());

// STEP 3: Show the page.
$pageTitle = 'Mga Paborito';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Page heading ===== -->
<section class="hero">
    <h1>Mga Paborito</h1>
    <p>Your saved recipes, ready for your next trip to the palengke.</p>
</section>

<?php if ($recipes): ?>
    <!-- ===== Grid of saved recipes =====
         Same card as the browse page. data-favorites-page tells main.js to
         remove a card from this page when its heart is un-clicked. -->
    <div class="recipe-grid" data-favorites-page>
        <?php foreach ($recipes as $recipe): ?>
            <?php require __DIR__ . '/includes/recipe_card.php'; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- ===== Empty message =====
     Shown right away when the list is empty, or by main.js after the last card is removed.
     (The "hidden" attribute hides it while there are still favorites.) -->
<section class="panel empty-state" id="favorites-empty"<?= $recipes ? ' hidden' : '' ?>>
    <h2>Wala ka pang paborito.</h2>
    <p>Tap the heart on any recipe to save it here.</p>
    <p><a class="btn btn-primary" href="recipes.php">Browse recipes</a></p>
</section>

<?php // STEP 4: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
