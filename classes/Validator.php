<?php
/**
 * classes/Validator.php
 *
 * Server-side validation rules shared by all forms. The browser checks the
 * same rules in assets/js/main.js, but the server NEVER trusts the browser:
 * anyone can turn off JavaScript or send a request by hand.
 *
 * Each method returns an array of error messages keyed by field name.
 * An empty array means the input is valid.
 *
 * Used by: register.php, login.php, recipe_create.php, recipe_edit.php,
 * comment_add.php, comment_edit.php.
 */

declare(strict_types=1);

class Validator
{
    public const INGREDIENTS_MIN = 1;
    public const INGREDIENTS_MAX = 30;

    /** Length in characters (mb_ so "ñ" and emoji count as one character). */
    private static function len(string $value): int
    {
        return mb_strlen($value, 'UTF-8');
    }

    /** Username, email and password format rules (uniqueness is checked with the database by the page). */
    public static function registration(string $username, string $email, string $password, string $confirm): array
    {
        $errors = [];

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
            $errors['username'] = 'Username must be 3 to 30 characters: letters, numbers, and underscore only.';
        }

        if ($email === '' || self::len($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (self::len($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        if ($confirm === '' || $password !== $confirm) {
            $errors['confirm_password'] = 'The two passwords do not match.';
        }

        return $errors;
    }

    /** Login only needs both fields filled in. */
    public static function login(string $identifier, string $password): array
    {
        $errors = [];
        if ($identifier === '') {
            $errors['identifier'] = 'Please enter your username or email.';
        }
        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }
        return $errors;
    }

    /**
     * Trim every ingredient and drop the blank ones.
     * Non-string values (someone tampering with the form) are ignored.
     */
    public static function cleanIngredients($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $clean = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }
        return $clean;
    }

    /**
     * Recipe rules. $ingredients must already be cleaned with cleanIngredients().
     * $categoryExists is the result of Category::exists() for the chosen id.
     */
    public static function recipe(string $title, string $description, bool $categoryExists, array $ingredients, string $steps): array
    {
        $errors = [];

        $titleLen = self::len($title);
        if ($titleLen < 3 || $titleLen > 100) {
            $errors['title'] = 'Title must be 3 to 100 characters.';
        }

        $descLen = self::len($description);
        if ($descLen < 10 || $descLen > 255) {
            $errors['description'] = 'Description must be 10 to 255 characters.';
        }

        if (!$categoryExists) {
            $errors['category_id'] = 'Please choose a category from the list.';
        }

        $count = count($ingredients);
        if ($count < self::INGREDIENTS_MIN) {
            $errors['ingredients'] = 'Add at least 1 ingredient.';
        } elseif ($count > self::INGREDIENTS_MAX) {
            $errors['ingredients'] = 'You can add at most ' . self::INGREDIENTS_MAX . ' ingredients.';
        } else {
            foreach ($ingredients as $i => $ingredient) {
                $len = self::len($ingredient);
                if ($len < 2 || $len > 150) {
                    $errors['ingredients'] = 'Ingredient #' . ($i + 1) . ' must be 2 to 150 characters.';
                    break;
                }
            }
        }

        // The steps column is TEXT (max 65,535 bytes); cap well below that.
        $stepsLen = self::len($steps);
        if ($stepsLen < 20) {
            $errors['steps'] = 'Steps must be at least 20 characters.';
        } elseif ($stepsLen > 10000) {
            $errors['steps'] = 'Steps are too long (max 10,000 characters).';
        }

        return $errors;
    }

    /** Comment: 1 to 500 characters (after trimming). */
    public static function comment(string $body): array
    {
        $len = self::len($body);
        if ($len < 1 || $len > 500) {
            return ['body' => 'Comment must be 1 to 500 characters.'];
        }
        return [];
    }
}
