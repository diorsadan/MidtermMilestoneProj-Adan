<?php
/**
 * recipe_create.php
 * Share a new recipe. The recipe and its ingredients are saved in ONE
 * transaction by Recipe::create().
 *
 * How this page works:
 *   - GET  -> show the empty recipe form.
 *   - POST -> clean + validate the input, save it, then open the new recipe.
 *             On errors, show the form again with the member's input kept.
 */

// STEP 1: Shared setup, then block guests.
require_once __DIR__ . '/includes/auth.php';
require_login();

// STEP 2: Load the categories for the dropdown (and for validation later).
$categoryModel = new Category($pdo);
$categories    = $categoryModel->all();

// STEP 3: Empty form values for the first visit.
$errors = [];
$form   = ['title' => '', 'description' => '', 'category_id' => 0, 'ingredients' => [], 'steps' => ''];

// STEP 4: Only runs when the form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4a. Reject forged requests (CSRF token check).
    verify_csrf();

    // 4b. Read, trim and clean the input. Ingredients: trim + drop blanks.
    //     (ingredients[] in the HTML arrives here as a PHP array.)
    $form = [
        'title'       => input_str($_POST, 'title'),
        'description' => input_str($_POST, 'description'),
        'category_id' => input_id($_POST, 'category_id'),
        'ingredients' => Validator::cleanIngredients($_POST['ingredients'] ?? []),
        'steps'       => input_str($_POST, 'steps'),
    ];

    // 4c. Server-side validation (same rules as main.js).
    //     The category must exist in the categories table.
    $errors = Validator::recipe(
        $form['title'],
        $form['description'],
        $categoryModel->exists($form['category_id']),
        $form['ingredients'],
        $form['steps']
    );

    // 4d. No errors: save recipe + ingredients in one transaction.
    if (!$errors) {
        try {
            $recipeId = (new Recipe($pdo))->create(
                current_user_id(),
                $form['category_id'],
                $form['title'],
                $form['description'],
                $form['steps'],
                $form['ingredients']
            );
            // 4e. Success: show a message and open the new recipe.
            flash('success', 'Salamat sa pagbabahagi! Your recipe is now posted.');
            redirect('recipe.php?id=' . $recipeId);
        } catch (Throwable $e) {
            // 4f. Failure: the transaction was rolled back inside Recipe::create().
            // Log the real error; show a friendly one and keep the form filled.
            error_log('Recipe create failed: ' . $e->getMessage());
            $errors['form'] = 'Sorry, your recipe could not be saved. Nothing was posted. Please try again.';
        }
    }
}

// STEP 5: Settings used by the shared form in includes/recipe_form.php
//         (where to send it, the button text, and where "Cancel" goes).
$pageTitle   = 'Magbahagi ng Recipe';
$formAction  = 'recipe_create.php';
$submitLabel = 'I-post ang recipe';
$cancelUrl   = 'recipes.php';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Page content: heading + the shared recipe form ===== -->
<section class="panel form-panel">
    <h1>Magbahagi ng Recipe</h1>
    <p class="muted">Share a dish your family loves. Fields with errors will be highlighted.</p>
    <?php require __DIR__ . '/includes/recipe_form.php'; ?>
</section>
<?php // STEP 6: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
