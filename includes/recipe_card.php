<?php
/**
 * includes/recipe_card.php
 * One recipe card for the grid (recipes.php and favorites.php).
 * Expects $recipe (a row from Recipe::search or Favorite::forUser).
 * Every value typed by a member goes through e().
 */

// Did the logged-in member already save this recipe? (1/0 from the SQL -> true/false)
$isFav = (bool) $recipe['is_favorite'];
?>
<article class="recipe-card">
    <!-- Top row: category chip on the left, heart button on the right. -->
    <div class="card-top">
        <span class="chip"><?= e($recipe['category_name']) ?></span>
        <!-- Heart button. main.js sends data-recipe-id to favorite_toggle.php with fetch()
             and then updates the class, the heart, and the count without a reload.
             ♥ = saved, ♡ = not saved. -->
        <button type="button" class="fav-btn<?= $isFav ? ' is-favorite' : '' ?>"
                data-recipe-id="<?= (int) $recipe['id'] ?>"
                aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                aria-label="<?= $isFav ? 'Remove from favorites' : 'Save to favorites' ?>">
            <span class="fav-icon" aria-hidden="true"><?= $isFav ? '&#9829;&#xFE0E;' : '&#9825;' ?></span>
            <span class="fav-count"><?= (int) $recipe['fav_count'] ?></span>
        </button>
    </div>

    <!-- Title (links to the full recipe) and short description. -->
    <h2 class="card-title">
        <a href="recipe.php?id=<?= (int) $recipe['id'] ?>"><?= e($recipe['title']) ?></a>
    </h2>
    <p class="card-desc"><?= e($recipe['description']) ?></p>

    <!-- Author, posting date, and "edited" label if the recipe was changed. -->
    <p class="card-meta">
        ni <?= e($recipe['username']) ?> ·
        <time datetime="<?= e($recipe['created_at']) ?>"><?= e(format_date($recipe['created_at'])) ?></time>
        <?php if (!empty($recipe['updated_at'])): ?>
            <span class="edited-label" title="Edited <?= e(format_date($recipe['updated_at'])) ?>">edited</span>
        <?php endif; ?>
    </p>

    <!-- Link to the full recipe page. -->
    <a class="card-link" href="recipe.php?id=<?= (int) $recipe['id'] ?>">Tingnan ang recipe →</a>
</article>
