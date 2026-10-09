<?php
/**
 * logout.php
 * Ends the session. Uses POST + CSRF so another site cannot log you out.
 * This file has no HTML: it is the target of the Logout button in the nav bar.
 */

// STEP 1: Security checks: logged in, POST request, valid CSRF token.
require_once __DIR__ . '/includes/auth.php';
require_login();
require_post();
verify_csrf();

// STEP 2: Forget the member.
// Empty the session data, delete the session cookie, destroy the session.
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// STEP 3: Start a fresh, empty session just to carry the goodbye message,
//         then go to the login page.
session_start();
flash('success', 'Paalam! You have been logged out.');
redirect('login.php');
