/**
 * assets/js/main.js
 * Plain JavaScript (no libraries):
 *   1. Mobile nav toggle
 *   2. "Are you sure?" confirmation before deleting
 *   3. Browser-side form validation with inline error messages
 *   4. Add / remove ingredient inputs
 *   5. Character counters
 *   6. Favorites with fetch() (no page reload)
 *   7. Cooking checklist saved in localStorage
 *
 * NOTE: browser validation is only for convenience. Every rule is checked
 * again in PHP (classes/Validator.php) because the browser can be bypassed.
 */
(function () {
    'use strict';

    /* ---------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------- */

    // Count characters like PHP's mb_strlen (emoji / ñ count as 1).
    function charLength(text) {
        return Array.from(text).length;
    }

    // Small pop-up message at the bottom of the screen.
    function showToast(message) {
        var toast = document.createElement('div');
        toast.className = 'toast';
        toast.setAttribute('role', 'status');
        toast.textContent = message; // textContent = never interpreted as HTML
        document.body.appendChild(toast);
        setTimeout(function () { toast.classList.add('toast-hide'); }, 2600);
        setTimeout(function () { toast.remove(); }, 3100);
    }

    /* ---------------------------------------------------------------
     * 1. Mobile nav toggle
     * ------------------------------------------------------------- */
    var navToggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('main-nav');
    if (navToggle && nav) {
        navToggle.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    /* ---------------------------------------------------------------
     * 2. Confirm before delete (forms with data-confirm="...")
     * ------------------------------------------------------------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    /* ---------------------------------------------------------------
     * 3. Form validation
     * Rules are read from the HTML attributes (required, minlength,
     * maxlength, pattern, type="email", data-match) and the message from
     * data-error. The same rules live in PHP's Validator class.
     * ------------------------------------------------------------- */

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    function setFieldError(field, message) {
        var box = document.getElementById(field.id + '-error');
        var wrapper = field.closest('.field');
        if (box) { box.textContent = message; }
        if (wrapper) { wrapper.classList.toggle('has-error', message !== ''); }
        field.setAttribute('aria-invalid', message ? 'true' : 'false');
    }

    // Returns '' when valid, otherwise the error message.
    function checkField(field) {
        var value = field.hasAttribute('data-no-trim') ? field.value : field.value.trim();
        var message = field.dataset.error || 'Please check this field.';
        var length = charLength(value);

        if (field.required && value === '') { return message; }
        if (value === '') { return ''; }

        var min = parseInt(field.getAttribute('minlength'), 10);
        var max = parseInt(field.getAttribute('maxlength'), 10);
        if (!isNaN(min) && length < min) { return message; }
        if (!isNaN(max) && length > max) { return message; }

        var pattern = field.getAttribute('pattern');
        if (pattern && !new RegExp('^(?:' + pattern + ')$').test(value)) { return message; }

        if (field.type === 'email' && !EMAIL_RE.test(value)) { return message; }

        if (field.dataset.match) {
            var other = field.form.elements[field.dataset.match];
            if (other && other.value !== field.value) { return message; }
        }
        return '';
    }

    // Ingredients are checked as a group: 1..max non-blank, each 2..150 chars.
    function checkIngredients(group) {
        var box = document.getElementById('ingredients-error');
        var max = parseInt(group.dataset.max, 10) || 30;
        var inputs = group.querySelectorAll('input[name="ingredients[]"]');
        var filled = 0;
        var message = '';

        inputs.forEach(function (input, index) {
            var value = input.value.trim();
            var bad = false;
            if (value !== '') {
                filled++;
                var len = charLength(value);
                if (len < 2 || len > 150) {
                    bad = true;
                    if (!message) { message = 'Ingredient #' + (index + 1) + ' must be 2 to 150 characters.'; }
                }
            }
            input.setAttribute('aria-invalid', bad ? 'true' : 'false');
        });

        if (filled < 1) { message = 'Add at least 1 ingredient.'; }
        if (filled > max) { message = 'You can add at most ' + max + ' ingredients.'; }

        if (box) { box.textContent = message; }
        group.classList.toggle('has-error', message !== '');
        return message;
    }

    function validatableFields(form) {
        return Array.prototype.filter.call(form.elements, function (el) {
            return el.id && /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) &&
                el.type !== 'hidden' && el.type !== 'submit' && el.type !== 'button';
        });
    }

    document.querySelectorAll('form[data-validate]').forEach(function (form) {
        // JavaScript is running, so use our inline messages instead of the
        // browser's default bubbles. (Without JS, the HTML attributes still work.)
        form.noValidate = true;

        var fields = validatableFields(form);
        var ingredientGroup = form.querySelector('[data-ingredients]');

        fields.forEach(function (field) {
            // Check when the member leaves a field...
            field.addEventListener('blur', function () {
                setFieldError(field, checkField(field));
            });
            // ...and re-check while typing once it has shown an error.
            field.addEventListener('input', function () {
                if (field.getAttribute('aria-invalid') === 'true') {
                    setFieldError(field, checkField(field));
                }
                // Changing the password re-checks "confirm password".
                var confirmField = form.querySelector('[data-match="' + field.name + '"]');
                if (confirmField && confirmField.value !== '') {
                    setFieldError(confirmField, checkField(confirmField));
                }
            });
        });

        if (ingredientGroup) {
            ingredientGroup.addEventListener('focusout', function () { checkIngredients(ingredientGroup); });
        }

        form.addEventListener('submit', function (event) {
            var firstBad = null;

            fields.forEach(function (field) {
                var message = checkField(field);
                setFieldError(field, message);
                if (message && !firstBad) { firstBad = field; }
            });

            if (ingredientGroup && checkIngredients(ingredientGroup) && !firstBad) {
                firstBad = ingredientGroup.querySelector('input[aria-invalid="true"]') ||
                           ingredientGroup.querySelector('input');
            }

            if (firstBad) {
                event.preventDefault(); // do not send the form; show the messages
                firstBad.focus();
            }
        });
    });

    /* ---------------------------------------------------------------
     * 4. Add / remove ingredient inputs (name="ingredients[]")
     * ------------------------------------------------------------- */
    var ingredientList = document.getElementById('ingredient-list');
    var addIngredientBtn = document.getElementById('add-ingredient');
    var ingredientTemplate = document.getElementById('ingredient-template');

    if (ingredientList && addIngredientBtn && ingredientTemplate) {
        var group = ingredientList.closest('[data-ingredients]');
        var maxIngredients = parseInt(group.dataset.max, 10) || 30;

        // Keep labels/placeholders numbered 1, 2, 3... and the button state right.
        var renumber = function () {
            var rows = ingredientList.querySelectorAll('.ingredient-row');
            rows.forEach(function (row, index) {
                var input = row.querySelector('input');
                input.setAttribute('aria-label', 'Ingredient ' + (index + 1));
                input.placeholder = 'Sangkap #' + (index + 1);
            });
            var full = rows.length >= maxIngredients;
            addIngredientBtn.disabled = full;
            addIngredientBtn.textContent = full ? 'Max ' + maxIngredients + ' ingredients' : '+ Add ingredient';
        };

        addIngredientBtn.addEventListener('click', function () {
            if (ingredientList.children.length >= maxIngredients) { return; }
            var row = ingredientTemplate.content.firstElementChild.cloneNode(true);
            ingredientList.appendChild(row);
            renumber();
            row.querySelector('input').focus();
        });

        // One listener for every remove button, including ones added later.
        ingredientList.addEventListener('click', function (event) {
            var button = event.target.closest('.ingredient-remove');
            if (!button) { return; }
            var row = button.closest('.ingredient-row');
            if (ingredientList.children.length > 1) {
                row.remove();
            } else {
                row.querySelector('input').value = ''; // keep at least one box
            }
            renumber();
            var first = ingredientList.querySelector('input');
            if (first) { first.focus(); }
        });

        renumber();
    }

    /* ---------------------------------------------------------------
     * 5. Character counters for fields with data-counter + maxlength
     * ------------------------------------------------------------- */
    document.querySelectorAll('[data-counter][maxlength]').forEach(function (field) {
        var max = parseInt(field.getAttribute('maxlength'), 10);
        var counter = document.createElement('p');
        counter.className = 'char-counter';
        counter.setAttribute('aria-live', 'polite');
        field.insertAdjacentElement('afterend', counter);

        var update = function () {
            var used = charLength(field.value.trim());
            counter.textContent = used + ' / ' + max;
            counter.classList.toggle('over', used > max);
        };
        field.addEventListener('input', update);
        update();
    });

    /* ---------------------------------------------------------------
     * 6. Favorites: POST to favorite_toggle.php with fetch(), no reload.
     * Server answers {"success": true, "favorited": true, "count": 3}.
     * ------------------------------------------------------------- */
    var csrfMeta = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrfMeta ? csrfMeta.content : '';

    function renderFavoriteButtons(recipeId, favorited, count) {
        document.querySelectorAll('.fav-btn[data-recipe-id="' + recipeId + '"]').forEach(function (btn) {
            btn.classList.toggle('is-favorite', favorited);
            btn.setAttribute('aria-pressed', favorited ? 'true' : 'false');
            btn.setAttribute('aria-label', favorited ? 'Remove from favorites' : 'Save to favorites');
            btn.querySelector('.fav-icon').textContent = favorited ? '♥︎' : '♡'; // ♥ / ♡ (text hearts take the CSS color)
            btn.querySelector('.fav-count').textContent = count;
            var text = btn.querySelector('.fav-text');
            if (text) { text.textContent = favorited ? 'Nasa Paborito' : 'I-save'; }
            btn.classList.remove('pop');
            void btn.offsetWidth; // restart the CSS animation
            btn.classList.add('pop');
        });
    }

    // On "Mga Paborito", an un-saved recipe card slides away.
    function removeFromFavoritesPage(recipeId) {
        var grid = document.querySelector('[data-favorites-page]');
        if (!grid) { return; }
        var btn = grid.querySelector('.fav-btn[data-recipe-id="' + recipeId + '"]');
        var card = btn ? btn.closest('.recipe-card') : null;
        if (!card) { return; }
        card.classList.add('card-leaving');
        setTimeout(function () {
            card.remove();
            if (!grid.querySelector('.recipe-card')) {
                grid.remove();
                var empty = document.getElementById('favorites-empty');
                if (empty) { empty.hidden = false; }
            }
        }, 300);
    }

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('.fav-btn');
        if (!btn || btn.disabled) { return; }

        var recipeId = btn.dataset.recipeId;
        var body = new FormData();
        body.append('recipe_id', recipeId);
        body.append('csrf_token', csrfToken);

        btn.disabled = true; // stop double clicks while waiting

        fetch('favorite_toggle.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken, 'Accept': 'application/json' },
            body: body,
            credentials: 'same-origin'
        })
            .then(function (response) {
                if (response.status === 401) {
                    window.location.href = 'login.php';
                    throw new Error('Not logged in');
                }
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    showToast(data.message || 'Could not update favorites.');
                    return;
                }
                renderFavoriteButtons(recipeId, data.favorited, data.count);
                showToast(data.favorited ? 'Naidagdag sa Mga Paborito' : 'Tinanggal sa Mga Paborito');
                if (!data.favorited) { removeFromFavoritesPage(recipeId); }
            })
            .catch(function (error) {
                if (error.message !== 'Not logged in') {
                    showToast('Network problem. Please try again.');
                }
            })
            .finally(function () {
                btn.disabled = false;
            });
    });

    /* ---------------------------------------------------------------
     * 7. Cooking checklist (my extra feature)
     * Ticked ingredients/steps are crossed out and remembered per recipe
     * in localStorage, so the progress survives a page reload.
     * ------------------------------------------------------------- */
    var checklist = document.querySelector('[data-checklist]');
    if (checklist) {
        var storageKey = checklist.dataset.checklist;
        var boxes = checklist.querySelectorAll('input[type="checkbox"][data-key]');
        var doneEl = checklist.querySelector('.checklist-done');
        var fillEl = checklist.querySelector('.progress-fill');
        var resetBtn = checklist.querySelector('.checklist-reset');

        // localStorage can throw (private mode, blocked storage): fail quietly.
        var load = function () {
            try {
                var saved = JSON.parse(localStorage.getItem(storageKey) || '[]');
                return Array.isArray(saved) ? saved : [];
            } catch (e) {
                return [];
            }
        };
        var save = function (keys) {
            try {
                if (keys.length) {
                    localStorage.setItem(storageKey, JSON.stringify(keys));
                } else {
                    localStorage.removeItem(storageKey);
                }
            } catch (e) { /* storage unavailable: checklist still works for this visit */ }
        };

        var refresh = function () {
            var checkedKeys = [];
            boxes.forEach(function (box) {
                box.closest('li').classList.toggle('is-done', box.checked);
                if (box.checked) { checkedKeys.push(box.dataset.key); }
            });
            if (doneEl) { doneEl.textContent = checkedKeys.length; }
            if (fillEl) {
                var pct = boxes.length ? Math.round((checkedKeys.length / boxes.length) * 100) : 0;
                fillEl.style.width = pct + '%';
            }
            checklist.classList.toggle('all-done', boxes.length > 0 && checkedKeys.length === boxes.length);
            return checkedKeys;
        };

        var savedKeys = load();
        boxes.forEach(function (box) {
            box.checked = savedKeys.indexOf(box.dataset.key) !== -1;
            box.addEventListener('change', function () {
                save(refresh());
                if (checklist.classList.contains('all-done')) {
                    showToast('Luto na! Kain na tayo!');
                }
            });
        });
        refresh();

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                boxes.forEach(function (box) { box.checked = false; });
                save(refresh());
                showToast('Checklist reset.');
            });
        }
    }
})();
