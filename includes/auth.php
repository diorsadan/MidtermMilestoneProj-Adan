<?php
/**
 * includes/auth.php
 *
 * Included at the top of EVERY page. It:
 *   - starts the session,
 *   - loads the database connection ($pdo) and the classes,
 *   - provides require_login(), e(), CSRF helpers and a few small utilities.
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

// Never print PHP errors to the visitor; they go to the PHP error log instead.
ini_set('display_errors', '0');

if (session_status() === PHP_SESSION_NONE) {
    // HttpOnly: JavaScript cannot read the session cookie.
    // SameSite=Lax: the cookie is not sent on cross-site POSTs (extra CSRF defense).
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Load every class from the classes/ folder when it is first used.
spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../classes/' . $class . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

// The single shared PDO connection (created only in config/database.php).
require_once __DIR__ . '/../config/database.php';
$pdo = Database::connect();

/**
 * Last line of defense: if any exception is not caught, log it and show a
 * friendly message. Raw database error messages are never shown to the user.
 */
set_exception_handler(function (Throwable $e): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (defined('JSON_ENDPOINT')) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode(['success' => false, 'message' => 'Something went wrong. Please try again.']);
        return;
    }
    echo '<p style="font-family:sans-serif;padding:2rem;">Pasensya na! Something went wrong in the kitchen. '
       . '<a href="recipes.php">Go back to the recipes</a>.</p>';
});

/* ------------------------------------------------------------------
 * Output escaping
 * ------------------------------------------------------------------ */

/**
 * Escape text before printing it into HTML. Use this on EVERYTHING a user
 * typed (usernames, titles, comments, form values...). It turns characters
 * like < > " ' & into harmless entities, which blocks XSS attacks.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------
 * Login helpers
 * ------------------------------------------------------------------ */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_username(): string
{
    return (string) ($_SESSION['username'] ?? '');
}

/**
 * Call this at the top of every member-only page. Guests are sent to the
 * login page. For the AJAX endpoint ($json = true) a JSON 401 is returned
 * instead of a redirect, because fetch() cannot follow a login page.
 */
function require_login(bool $json = false): void
{
    if (is_logged_in()) {
        return;
    }
    if ($json) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Please log in first.']);
        exit;
    }
    flash('info', 'Mag-login muna. Please log in to continue.');
    redirect('login.php');
}

/** Log a member in after their password was verified. */
function login_user(array $user): void
{
    // New session id after login stops "session fixation" attacks: an
    // attacker who planted a session id before login cannot reuse it.
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    // Fresh CSRF token for the new logged-in session.
    unset($_SESSION['csrf_token']);
}

/* ------------------------------------------------------------------
 * CSRF protection
 * A random secret is kept in the session and put in every POST form (and
 * the favorite fetch request). A forged request from another site cannot
 * know the secret, so it is rejected.
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden input to paste inside every <form method="post">. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True when the submitted token (form field or X-CSRF-Token header) matches. */
function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    // hash_equals compares in constant time (no timing leaks).
    return is_string($sent) && $sent !== '' && hash_equals(csrf_token(), $sent);
}

/** Stop the request if the CSRF token is missing or wrong. */
function verify_csrf(): void
{
    if (!csrf_is_valid()) {
        flash('error', 'Your session expired or the form was invalid. Please try again.');
        redirect(safe_back_url());
    }
}

/** The previous page if it is on this same site, otherwise the recipe list. */
function safe_back_url(string $fallback = 'recipes.php'): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host    = parse_url($referer, PHP_URL_HOST);
    return ($referer !== '' && $host === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST))
        ? $referer
        : $fallback;
}

/** Only allow POST for pages that change data (delete, save...). */
function require_post(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        flash('error', 'That action must be submitted with a form.');
        redirect('recipes.php');
    }
}

/* ------------------------------------------------------------------
 * Small utilities
 * ------------------------------------------------------------------ */

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Store a one-time message to show on the next page. */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear the one-time messages. */
function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/** Read a positive integer id from GET/POST, or 0 if missing/invalid. */
function input_id(array $source, string $key): int
{
    $value = filter_var($source[$key] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    return $value === false ? 0 : (int) $value;
}

/** Read a string from GET/POST safely (arrays and missing keys become ''). */
function input_str(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

/** "Oct 9, 2026 3:05 PM" */
function format_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    return $ts ? date('M j, Y g:i A', $ts) : '';
}

/** Show a friendly "not found" page (404) and stop. */
function show_not_found(string $message = 'Hindi namin mahanap. We could not find what you were looking for.'): void
{
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/header.php';
    echo '<section class="panel empty-state">'
       . '<h1>Wala dito!</h1>'
       . '<p>' . e($message) . '</p>'
       . '<p><a class="btn btn-primary" href="recipes.php">Back to recipes</a></p>'
       . '</section>';
    require __DIR__ . '/footer.php';
    exit;
}

/** Show a friendly "not allowed" page (403) and stop. */
function show_forbidden(string $message = 'You can only change what you posted yourself.'): void
{
    http_response_code(403);
    $pageTitle = 'Not allowed';
    require __DIR__ . '/header.php';
    echo '<section class="panel empty-state">'
       . '<h1>Bawal po!</h1>'
       . '<p>' . e($message) . '</p>'
       . '<p><a class="btn btn-primary" href="recipes.php">Back to recipes</a></p>'
       . '</section>';
    require __DIR__ . '/footer.php';
    exit;
}
