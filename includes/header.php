<?php
/**
 * includes/header.php
 * Page head + navigation bar. Set $pageTitle before including this file.
 * Every page includes this right before its own HTML.
 */

// Use the page's title, or the site name if the page did not set one.
$pageTitle  = $pageTitle ?? 'Pagkaing Pinoy';
// The file name of the current page, e.g. "recipes.php" (used to highlight the nav link).
$activePage = basename($_SERVER['SCRIPT_NAME'] ?? '');

/** Adds class="active" to the nav link of the current page. */
$navClass = function (array $pages) use ($activePage): string {
    return in_array($activePage, $pages, true) ? ' class="active" aria-current="page"' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Needed so phones render the page at device width (responsive layout). -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- JavaScript reads this token and sends it with the favorite fetch() request. -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> · Pagkaing Pinoy</title>
    <!-- Relative path, so the site works from any subfolder of htdocs. -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<!-- ===== Top navigation bar (stays at the top while scrolling) ===== -->
<header class="site-header">
    <div class="nav-wrap">
        <!-- Logo: goes to the recipe list for members, login page for guests. -->
        <a class="brand" href="<?= is_logged_in() ? 'recipes.php' : 'login.php' ?>">
            <span class="brand-icon" aria-hidden="true">P</span>
            <span class="brand-text">Pagkaing <small>Pinoy</small></span>
        </a>

        <!-- Hamburger button: only visible on phones. main.js opens/closes the menu. -->
        <button class="nav-toggle" type="button" aria-controls="main-nav" aria-expanded="false">
            <span class="nav-toggle-bar" aria-hidden="true"></span>
            <span class="visually-hidden">Menu</span>
        </button>

        <nav id="main-nav" class="main-nav" aria-label="Main">
            <ul>
                <!-- Members see the site links + their name + Logout.
                     Guests only see Login and Register. -->
                <?php if (is_logged_in()): ?>
                    <li><a href="recipes.php"<?= $navClass(['recipes.php', 'recipe.php']) ?>>Mga Recipe</a></li>
                    <li><a href="recipe_create.php"<?= $navClass(['recipe_create.php']) ?>>Magbahagi</a></li>
                    <li><a href="favorites.php"<?= $navClass(['favorites.php']) ?>>Mga Paborito</a></li>
                    <li class="nav-user">
                        <span class="nav-hello">Kumusta, <strong><?= e(current_username()) ?></strong>!</span>
                        <!-- Logging out changes state, so it is a POST form with a CSRF token. -->
                        <form action="logout.php" method="post" class="inline-form">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-small btn-ghost">Logout</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li><a href="login.php"<?= $navClass(['login.php']) ?>>Mag-login</a></li>
                    <li><a href="register.php"<?= $navClass(['register.php']) ?>>Mag-register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<!-- ===== Main content starts here (closed in footer.php) ===== -->
<main class="container">
    <!-- One-time messages from the previous page, e.g. "Your recipe is now posted." -->
    <?php foreach (get_flashes() as $flash): ?>
        <div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
