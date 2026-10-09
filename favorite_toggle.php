<?php
/**
 * favorite_toggle.php
 * AJAX endpoint called by fetch() in main.js. Saves or removes a favorite
 * and answers with JSON, e.g. {"success": true, "favorited": true, "count": 3}
 *
 * This file never shows a page. JavaScript reads its JSON answer and
 * updates the heart button and the count without reloading the page.
 */

// STEP 1: Tell the error handler in auth.php to answer errors in JSON, not HTML.
define('JSON_ENDPOINT', true); // makes the error handler answer in JSON

// STEP 2: Shared setup + login check.
require_once __DIR__ . '/includes/auth.php';
require_login(true); // guests get a JSON 401 instead of a redirect

// STEP 3: Everything this file prints is JSON.
header('Content-Type: application/json');

/** Send a JSON response and stop. */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

// STEP 4: Only POST is allowed (saving/removing changes data).
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'POST required.'], 405);
}

// STEP 5: CSRF check.
// The CSRF token is sent in the X-CSRF-Token header (and the form body).
if (!csrf_is_valid()) {
    json_response(['success' => false, 'message' => 'Invalid security token. Please reload the page.'], 403);
}

// STEP 6: The recipe must exist.
$recipeId = input_id($_POST, 'recipe_id');
if (!$recipeId || !(new Recipe($pdo))->find($recipeId, current_user_id())) {
    json_response(['success' => false, 'message' => 'Recipe not found.'], 404);
}

// STEP 7: Save it if not saved yet, otherwise remove it.
$favorite  = new Favorite($pdo);
$favorited = $favorite->toggle(current_user_id(), $recipeId);

// STEP 8: Answer with the new state and the new total count for the heart button.
json_response([
    'success'   => true,
    'favorited' => $favorited,
    'count'     => $favorite->count($recipeId),
]);
