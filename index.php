<?php
/**
 * index.php
 * Entry point: members go to the recipe list, guests to the login page.
 */
require_once __DIR__ . '/includes/auth.php';

redirect(is_logged_in() ? 'recipes.php' : 'login.php');
