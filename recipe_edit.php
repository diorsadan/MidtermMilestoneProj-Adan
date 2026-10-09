<?php
/**
 * recipe_edit.php
 * Edit your own recipe. Recipe::update() updates the recipe, deletes the old
 * ingredients and inserts the new ones in ONE transaction.
 *
 * How this page works:
 *   - GET  -> show the form filled with the recipe's current values.
 *   - POST -> validate the changes, save them, then go back to the recipe.
 */

// STEP 1: Shared setup, then block guests.
require_once __DIR__ . '/includes/auth.php';
require_login();

// STEP 2: Create the class objects this page needs.
$recipeModel   = new Recipe($pdo);
$categoryModel = new Category($pdo);

// STEP 3: Load the recipe being edited.
// The id is in the URL for both GET and POST (form action="recipe_edit.php?id=...").
$recipeId = input_id($_GET, 'id');
$recipe   = $recipeId ? $recipeModel->find($recipeId, current_user_id()) : null;

// Not in the database -> friendly 404 page.
if (!$recipe) {
    show_not_found('That recipe does not exist or was deleted.');
}

// STEP 4: Ownership check.
// Friendly message for non-owners. The REAL protection is the
// WHERE id = ? AND user_id = ? inside Recipe::update().
if ((int) $recipe['user_id'] !== current_user_id()) {
    show_forbidden('You can only edit recipes that you posted.');
}

// STEP 5: Fill the form with the recipe's CURRENT values (used on GET).
$categories = $categoryModel->all();
$errors     = [];
$form       = [
    'title'       => $recipe['title'],
    'description' => $recipe['description'],
    'category_id' => (int) $recipe['category_id'],
    'ingredients' => $recipeModel->ingredients($recipeId),
    'steps'       => $recipe['steps'],
];

// STEP 6: Only runs when the form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 6a. Reject forged requests (CSRF token check).
    verify_csrf();

    // 6b. Replace the current values with what the member submitted
    //     (so the form keeps their changes if there is an error).
    $form = [
        'title'       => input_str($_POST, 'title'),
        'description' => input_str($_POST, 'description'),
        'category_id' => input_id($_POST, 'category_id'),
        'ingredients' => Validator::cleanIngredients($_POST['ingredients'] ?? []),
        'steps'       => input_str($_POST, 'steps'),
    ];

    // 6c. Server-side validation (same rules as when creating).
    $errors = Validator::recipe(
        $form['title'],
        $form['description'],
        $categoryModel->exists($form['category_id']),
        $form['ingredients'],
        $form['steps']
    );

    // 6d. No errors: save the changes in one transaction
    //     (update recipe -> delete old ingredients -> insert new ones).
    if (!$errors) {
        try {
            $saved = $recipeModel->update(
                $recipeId,
                current_user_id(),
                $form['category_id'],
                $form['title'],
                $form['description'],
                $form['steps'],
                $form['ingredients']
            );
            if (!$saved) {
                // Deleted meanwhile, or not the owner: nothing was changed.
                show_forbidden('This recipe could not be updated. It may have been deleted, or it is not yours.');
            }
            // 6e. Success: go back to the recipe page.
            flash('success', 'Na-update na ang recipe! Your changes are saved.');
            redirect('recipe.php?id=' . $recipeId);
        } catch (Throwable $e) {
            // 6f. Failure: the transaction was rolled back, so the old recipe is unchanged.
            error_log('Recipe update failed: ' . $e->getMessage());
            $errors['form'] = 'Sorry, your changes could not be saved. The recipe was left unchanged. Please try again.';
        }
    }
}

// STEP 7: Settings used by the shared form in includes/recipe_form.php.
//         The form is sent back to this same page with the recipe id.
$pageTitle   = 'Edit: ' . $recipe['title'];
$formAction  = 'recipe_edit.php?id=' . $recipeId;
$submitLabel = 'I-save ang pagbabago';
$cancelUrl   = 'recipe.php?id=' . $recipeId;
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Page content: heading + the shared recipe form ===== -->
<section class="panel form-panel">
    <h1>I-edit ang Recipe</h1>
    <p class="muted">Editing “<?= e($recipe['title']) ?>”. Others will see an “edited” label after you save.</p>
    <?php require __DIR__ . '/includes/recipe_form.php'; ?>
</section>
<?php // STEP 8: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
