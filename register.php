<?php
/**
 * register.php
 * Create a new member account. (Public page: no require_login().)
 *
 * How this page works:
 *   - GET  -> show the empty registration form.
 *   - POST -> validate, check that username/email are not taken, save the
 *             new account, then send the member to the login page.
 */

// STEP 1: Load the shared setup: session, database ($pdo), classes, e(), CSRF helpers.
require_once __DIR__ . '/includes/auth.php';

// STEP 2: Logged-in members do not need to register again.
if (is_logged_in()) {
    redirect('recipes.php');
}

// STEP 3: Default values for the first visit.
//   $errors = error messages per field (empty = no errors yet)
//   $old    = what the member typed, used to refill the form after an error
$errors = [];
$old    = ['username' => '', 'email' => ''];

// STEP 4: Only runs when the form was submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 4a. Reject forged requests (CSRF token check).
    verify_csrf();

    // 4b. Read the input. Username and email are trimmed.
    $username = input_str($_POST, 'username');
    $email    = input_str($_POST, 'email');
    // Passwords are NOT trimmed (spaces may be part of the password).
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirm  = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

    // Keep what the member typed (never the password) to refill the form.
    $old = ['username' => $username, 'email' => $email];

    // 4c. Format rules (same rules as the JavaScript in main.js).
    $errors = Validator::registration($username, $email, $password, $confirm);

    // 4d. Uniqueness rules need the database.
    //     (Only checked if the format was already OK.)
    $userModel = new User($pdo);
    if (!isset($errors['username']) && $userModel->usernameExists($username)) {
        $errors['username'] = 'That username is already taken.';
    }
    if (!isset($errors['email']) && $userModel->emailExists($email)) {
        $errors['email'] = 'That email is already registered.';
    }

    // 4e. No errors: save the account. User::register() hashes the password
    //     with password_hash() before storing it.
    if (!$errors) {
        if ($userModel->register($username, $email, $password) > 0) {
            flash('success', 'Salamat! Your account was created. You can now log in.');
            redirect('login.php');
        }
        // Someone took the name/email a split second earlier.
        $errors['username'] = 'That username or email was just taken. Please try another.';
    }
}

// STEP 5: Show the page (header = <head> + nav bar).
$pageTitle = 'Mag-register';
require __DIR__ . '/includes/header.php';
?>
<!-- ===== Registration card ===== -->
<section class="auth-card panel">
    <h1>Sumali sa Pagkaing Pinoy</h1>
    <p class="muted">Create an account to share and save the barangay's favorite recipes.</p>

    <!-- data-validate: main.js checks every field in the browser before sending. -->
    <form method="post" action="register.php" data-validate>
        <!-- Hidden CSRF token. -->
        <?= csrf_field() ?>

        <!-- Username: 3-30 letters, numbers or underscore (pattern="..." is the browser rule).
             "has-error" class turns the box red when the server found an error. -->
        <div class="field<?= isset($errors['username']) ? ' has-error' : '' ?>">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($old['username']) ?>"
                   required minlength="3" maxlength="30" pattern="[A-Za-z0-9_]{3,30}" autocomplete="username"
                   aria-describedby="username-hint username-error"
                   data-error="Username must be 3 to 30 characters: letters, numbers, and underscore only.">
            <p class="hint" id="username-hint">3–30 characters: letters, numbers, underscore.</p>
            <p class="field-error" id="username-error" aria-live="polite"><?= e($errors['username'] ?? '') ?></p>
        </div>

        <!-- Email: type="email" makes the browser check the format. -->
        <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
                   required maxlength="100" autocomplete="email"
                   aria-describedby="email-error"
                   data-error="Please enter a valid email address.">
            <p class="field-error" id="email-error" aria-live="polite"><?= e($errors['email'] ?? '') ?></p>
        </div>

        <!-- Password: at least 8 characters. Never refilled after an error. -->
        <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   required minlength="8" autocomplete="new-password"
                   aria-describedby="password-hint password-error"
                   data-error="Password must be at least 8 characters." data-no-trim>
            <p class="hint" id="password-hint">At least 8 characters.</p>
            <p class="field-error" id="password-error" aria-live="polite"><?= e($errors['password'] ?? '') ?></p>
        </div>

        <!-- Confirm password: data-match="password" tells main.js it must equal the password box. -->
        <div class="field<?= isset($errors['confirm_password']) ? ' has-error' : '' ?>">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   required autocomplete="new-password" data-match="password"
                   aria-describedby="confirm_password-error"
                   data-error="The two passwords do not match." data-no-trim>
            <p class="field-error" id="confirm_password-error" aria-live="polite"><?= e($errors['confirm_password'] ?? '') ?></p>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Mag-register</button>
    </form>

    <!-- Link for members who already have an account. -->
    <p class="auth-switch">May account na? <a href="login.php">Mag-login dito</a>.</p>
</section>
<?php // STEP 6: Close the page (footer + main.js). ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
