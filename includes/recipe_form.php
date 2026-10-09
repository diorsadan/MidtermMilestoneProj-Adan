<?php
/**
 * includes/recipe_form.php
 * The share/edit recipe form, shared by recipe_create.php and recipe_edit.php
 * so the fields and validation attributes are written only once.
 *
 * Expects: $form (title, description, category_id, ingredients[], steps),
 *          $errors, $categories, $formAction, $submitLabel.
 *
 * Every field has the same 3 parts:
 *   1. the input with HTML rules (required, minlength, maxlength...) and a
 *      data-error message that main.js shows in the browser,
 *   2. value="..." refilled from $form (escaped with e()),
 *   3. a <p class="field-error"> showing the SERVER's error message, if any.
 */

// Copy the ingredient list and pad it with empty boxes.
$ingredientRows = $form['ingredients'];
// Start with at least 3 inputs so the form does not look empty.
while (count($ingredientRows) < 3) {
    $ingredientRows[] = '';
}
?>
<!-- data-validate: main.js checks the form in the browser before sending it. -->
<form method="post" action="<?= e($formAction) ?>" class="recipe-form" data-validate>
    <!-- Hidden CSRF token. -->
    <?= csrf_field() ?>

    <!-- General error, e.g. "your recipe could not be saved" after a rollback. -->
    <?php if (isset($errors['form'])): ?>
        <div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <!-- ===== Title: 3 to 100 characters ===== -->
    <div class="field<?= isset($errors['title']) ? ' has-error' : '' ?>">
        <label for="title">Pangalan ng luto (title)</label>
        <input type="text" id="title" name="title" value="<?= e($form['title']) ?>"
               required minlength="3" maxlength="100" placeholder="e.g. Adobong Manok ni Lola"
               aria-describedby="title-error" data-error="Title must be 3 to 100 characters.">
        <p class="field-error" id="title-error" aria-live="polite"><?= e($errors['title'] ?? '') ?></p>
    </div>

    <!-- ===== Description: 10 to 255 characters (data-counter shows "0 / 255") ===== -->
    <div class="field<?= isset($errors['description']) ? ' has-error' : '' ?>">
        <label for="description">Maikling paglalarawan (short description)</label>
        <textarea id="description" name="description" rows="2"
                  required minlength="10" maxlength="255" data-counter
                  placeholder="What makes this dish special?"
                  aria-describedby="description-error"
                  data-error="Description must be 10 to 255 characters."><?= e($form['description']) ?></textarea>
        <p class="field-error" id="description-error" aria-live="polite"><?= e($errors['description'] ?? '') ?></p>
    </div>

    <!-- ===== Category dropdown, loaded from the categories table =====
         The member's current choice gets "selected". -->
    <div class="field<?= isset($errors['category_id']) ? ' has-error' : '' ?>">
        <label for="category_id">Kategorya (category)</label>
        <select id="category_id" name="category_id" required
                aria-describedby="category_id-error" data-error="Please choose a category from the list.">
            <option value="">— Pumili ng kategorya —</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= (int) $category['id'] ?>"
                    <?= (int) $form['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="field-error" id="category_id-error" aria-live="polite"><?= e($errors['category_id'] ?? '') ?></p>
    </div>

    <!-- ===== Ingredients: 1 to 30 boxes, 2-150 characters each =====
         data-ingredients + data-max are read by main.js (group check + max boxes). -->
    <fieldset class="field ingredient-field<?= isset($errors['ingredients']) ? ' has-error' : '' ?>" data-ingredients
              data-max="<?= Validator::INGREDIENTS_MAX ?>" aria-describedby="ingredients-error">
        <legend>Mga sangkap (ingredients)</legend>
        <p class="hint">One ingredient per box, e.g. "1 kilo manok". 1 to <?= Validator::INGREDIENTS_MAX ?> items, 2–150 characters each.</p>

        <!-- One row per ingredient. name="ingredients[]" (with []) makes PHP
             receive all the boxes as one array: $_POST['ingredients']. -->
        <ol class="ingredient-list" id="ingredient-list">
            <?php foreach ($ingredientRows as $i => $ingredient): ?>
                <li class="ingredient-row">
                    <input type="text" name="ingredients[]" value="<?= e($ingredient) ?>"
                           minlength="2" maxlength="150" aria-label="Ingredient <?= $i + 1 ?>"
                           placeholder="Sangkap #<?= $i + 1 ?>">
                    <button type="button" class="btn-icon ingredient-remove" aria-label="Remove this ingredient" title="Tanggalin">✕</button>
                </li>
            <?php endforeach; ?>
        </ol>

        <!-- Copied by JavaScript when "Add ingredient" is clicked.
             A <template> is not shown on the page and not sent with the form. -->
        <template id="ingredient-template">
            <li class="ingredient-row">
                <input type="text" name="ingredients[]" minlength="2" maxlength="150" aria-label="Ingredient" placeholder="Bagong sangkap">
                <button type="button" class="btn-icon ingredient-remove" aria-label="Remove this ingredient" title="Tanggalin">✕</button>
            </li>
        </template>

        <!-- Adds a new row (main.js section 4). Error for the whole ingredient group below it. -->
        <button type="button" class="btn btn-secondary btn-small" id="add-ingredient">+ Add ingredient</button>
        <p class="field-error" id="ingredients-error" aria-live="polite"><?= e($errors['ingredients'] ?? '') ?></p>
    </fieldset>

    <!-- ===== Steps: at least 20 characters, one step per line ===== -->
    <div class="field<?= isset($errors['steps']) ? ' has-error' : '' ?>">
        <label for="steps">Paraan ng pagluto (cooking steps)</label>
        <p class="hint" id="steps-hint">Write one step per line. Each line becomes a step you can tick off while cooking.</p>
        <textarea id="steps" name="steps" rows="8" required minlength="20" maxlength="10000"
                  placeholder="1. Igisa ang bawang at sibuyas.&#10;2. Ilagay ang manok..."
                  aria-describedby="steps-hint steps-error"
                  data-error="Steps must be at least 20 characters."><?= e($form['steps']) ?></textarea>
        <p class="field-error" id="steps-error" aria-live="polite"><?= e($errors['steps'] ?? '') ?></p>
    </div>

    <!-- ===== Buttons: submit text and Cancel link come from the page that included this form ===== -->
    <div class="form-actions">
        <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
        <a class="btn btn-ghost" href="<?= e($cancelUrl ?? 'recipes.php') ?>">Cancel</a>
    </div>
</form>
