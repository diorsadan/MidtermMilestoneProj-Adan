<?php
/**
 * login.php
 * Log in with username OR email + password. (Public page: no require_login().)
 *
 * How this page works:
 *   - GET  request (just opening the page)  -> show the empty login form.
 *   - POST request (the form was submitted) -> check the input, try to log
 *     in, and either go to recipes.php or show the form again with errors.
 */

// STEP 1: Load the shared setup: session, database ($pdo), classes, e(), CSRF helpers.
require_once __DIR__ . '/includes/auth.php';

// STEP 2: A member who is already logged in has no reason to see this page.
if (is_logged_in()) {
    redirect('recipes.php');
}

// STEP 3: Default values for the first visit (no errors, empty username box).
$errors     = [];
$identifier = '';

// STEP 4: Only runs when the form was submitted (method="post").
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4a. Reject the request if the hidden CSRF token is missing or wrong.
    verify_csrf();

    // 4b. Read what the member typed. The username/email is trimmed;
    //     the password is NOT trimmed because spaces can be part of it.
    $identifier = input_str($_POST, 'identifier');
    $password   = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    // 4c. Server-side validation: both boxes must be filled in.
    $errors = Validator::login($identifier, $password);

    // 4d. Only ask the database if validation passed.
    if (!$errors) {
        // User::login() finds the account and checks the password with password_verify().
        $user = (new User($pdo))->login($identifier, $password);

        if ($user) {
            // 4e. Success: regenerates the session id (session_regenerate_id(true))
            //     and stores the member in the session. See includes/auth.php.
            login_user($user);
            flash('success', 'Maligayang pagbabalik, ' . $user['username'] . '!');
            redirect('recipes.php');
        }

        // 4f. Failure: same message for "no such user" and "wrong password",
        //     so attackers cannot find out which usernames exist.
        $errors['form'] = 'Incorrect username/email or password.';
    }
}

// STEP 5: Show the page. header.php prints the <head> and the nav bar.
$pageTitle = 'Mag-login';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Login card ===== -->
<section class="auth-card panel">
    <h1>Tuloy po kayo!</h1>
    <p class="muted">Log in to browse, cook, and share recipes from our barangay.</p>

    <!-- General error box (only shown after a failed login). -->
    <?php if (isset($errors['form'])): ?>
        <div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <!-- data-validate tells main.js to check this form in the browser before sending it. -->
    <form method="post" action="login.php" data-validate>
        <!-- Hidden CSRF token; the server checks it in verify_csrf(). -->
        <?= csrf_field() ?>

        <!-- Username or email field.
             value="..." refills the box after an error (escaped with e()).
             data-error is the message main.js shows if the box is left empty.
             The <p class="field-error"> below it shows the server's error message. -->
        <div class="field<?= isset($errors['identifier']) ? ' has-error' : '' ?>">
            <label for="identifier">Username or email</label>
            <input type="text" id="identifier" name="identifier" value="<?= e($identifier) ?>"
                   required maxlength="100" autocomplete="username" autofocus
                   aria-describedby="identifier-error"
                   data-error="Please enter your username or email.">
            <p class="field-error" id="identifier-error" aria-live="polite"><?= e($errors['identifier'] ?? '') ?></p>
        </div>

        <!-- Password field. It is never refilled after an error (we never print passwords).
             data-no-trim tells main.js not to trim spaces, same as the server. -->
        <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   aria-describedby="password-error"
                   data-error="Please enter your password." data-no-trim>
            <p class="field-error" id="password-error" aria-live="polite"><?= e($errors['password'] ?? '') ?></p>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Mag-login</button>
    </form>

    <!-- Link for visitors who do not have an account yet. -->
    <p class="auth-switch">Wala pang account? <a href="register.php">Mag-register dito</a>.</p>
</section>
<?php // STEP 6: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
