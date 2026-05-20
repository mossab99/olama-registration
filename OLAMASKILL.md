---
name: olama-wp-module
description: >
  Build new WordPress admin modules for the Olama Stores plugin. Use this skill
  whenever the user asks to create, extend, or refactor any PHP view, admin page,
  settings screen, dashboard widget, or backend tool inside the Olama Stores plugin.
  Trigger on phrases like "new module", "admin page", "new tab", "new report",
  "add a screen", "build a tool", "new section in wp-admin", or any request
  involving a .php view file, a new CSS file, or a new JS file for the plugin.
  This skill is MANDATORY before writing any PHP, CSS, or JS for Olama Stores —
  even for small changes. It encodes hard-won conventions that are not obvious
  from the codebase alone.
---

# Olama Stores — Admin Module Development Guide

Standards, patterns, and architecture rules for every new module in the Olama Stores WordPress plugin. Based on the production `order-estimation` module.

---

## Pre-Coding Checklist (MUST complete before writing any code)

- [ ] Read this entire SKILL.md first
- [ ] Identify the module slug (e.g. `inventory-report`, `size-chart`) — used as prefix everywhere
- [ ] Confirm file locations follow the structure in §1
- [ ] Confirm all strings are i18n-wrapped (§3.2)
- [ ] Confirm JS data comes via JSON hydration block, not inline PHP (§4)
- [ ] Confirm no CDN scripts — only `wp_enqueue_script` (§3.4)
- [ ] Confirm ABSPATH check is first line of every PHP file (§3.1)
- [ ] Confirm JS is wrapped in IIFE (§4.2)
- [ ] Check for anti-patterns before submitting (§7)

---

## 1. Plugin File Structure

```
olama-stores/
├── admin/
│   ├── views/
│   │   ├── order-estimation.php       ← existing reference module
│   │   └── [module-slug].php          ← new module view goes here
│   └── assets/
│       ├── css/
│       │   └── os-[module-slug].css   ← dedicated stylesheet per module
│       └── js/
│           └── os-[module-slug].js    ← dedicated script per module
```

**Rules:**
- One `.php` view file, one `.css` file, one `.js` file per module
- View file handles PHP logic at top, HTML in body, enqueue calls at the very bottom
- Never embed CSS or JS inline in the view file (except the JSON hydration `<script>` block)

---

## 2. CSS Design System

### 2.1 CSS Variables

Every module stylesheet declares these at `:root`. Use the module prefix instead of `est-`, but **keep the exact values**:

> **Legacy note:** The original `order-estimation` module uses `--est-` prefixed variables (e.g. `--est-primary`). All new modules **MUST** use `--os-` instead. If you encounter `--est-` anywhere in existing code, treat it as legacy — do not replicate it.

```css
:root {
    --os-primary:      #2271b1;
    --os-primary-dk:   #135e96;
    --os-accent:       #00b9eb;
    --os-success:      #00a32a;
    --os-warning:      #d63638;
    --os-bg:           #f0f6fc;
    --os-card-bg:      #fff;
    --os-border:       #c3c4c7;
    --os-radius:       8px;
    --os-shadow:       0 2px 8px rgba(0,0,0,.08);
    --os-shadow-hover: 0 6px 20px rgba(0,0,0,.13);
    --os-font:         -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
```

### 2.2 Class Naming Convention

All classes use the pattern: `os-[module-slug]-[element]`

```
os-inv-card          ← card in the inventory module
os-inv-table         ← table in the inventory module
os-inv-stat-total    ← stat element in the inventory module
```

Never use generic class names like `.card`, `.table`, `.header` — they collide with WP core.

### 2.3 Component Patterns (copy these exactly)

**Page Wrapper:**
```html
<div class="wrap os-wrap" id="os-[module]-page">
    <h1 class="os-page-title">
        <span class="dashicons dashicons-[icon]"></span>
        <?php esc_html_e( 'Page Title', 'olama-stores' ); ?>
    </h1>
    ...
</div>
```

**Tab Navigation:**
```html
<nav class="os-[module]-tabs" id="os-[module]-tab-nav">
    <button class="os-[module]-tab active" data-tab="tab-one">
        <span class="dashicons dashicons-edit"></span>
        <?php esc_html_e( 'Tab One', 'olama-stores' ); ?>
    </button>
</nav>
<div class="os-[module]-tab-content active" id="tab-one">
    <!-- content -->
</div>
```

Tab CSS pattern (always position: relative; bottom: -2px on active to merge with border):
```css
.os-[module]-tab.active {
    background: var(--os-card-bg);
    border-bottom-color: var(--os-card-bg);
    color: var(--os-primary);
    position: relative;
    bottom: -2px;
}
```

**Card Component:**
```html
<div class="os-[module]-card">
    <div class="os-[module]-card-header">
        <span class="dashicons dashicons-[icon]"></span>
        <?php esc_html_e( 'Card Title', 'olama-stores' ); ?>
        <div class="os-[module]-card-header-actions">
            <!-- action buttons go here -->
        </div>
    </div>
    <div class="os-[module]-card-body">
        <!-- content -->
    </div>
    <div class="os-[module]-card-footer">
        <!-- primary actions go here -->
    </div>
</div>
```

Card header always uses this gradient — do not deviate:
```css
.os-[module]-card-header {
    background: linear-gradient(135deg, var(--os-primary) 0%, var(--os-primary-dk) 100%);
    color: #fff;
}
```

**Notice / Empty State:**
```html
<div class="os-[module]-notice">
    <span class="dashicons dashicons-info"></span>
    <?php esc_html_e( 'Descriptive message.', 'olama-stores' ); ?>
</div>
```
```css
.os-[module]-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 18px 20px;
    background: #f0f6fc;
    border-left: 4px solid var(--os-primary);
    border-radius: 0 var(--os-radius) var(--os-radius) 0;
}
```

**Spinner:**
```html
<span class="os-[module]-spinner"></span>
```
```css
.os-[module]-spinner {
    display: inline-block;
    width: 18px; height: 18px;
    border: 3px solid rgba(34,113,177,.3);
    border-top-color: var(--os-primary);
    border-radius: 50%;
    animation: os-spin .7s linear infinite;
    vertical-align: middle;
}
@keyframes os-spin { to { transform: rotate(360deg); } }
```

### 2.4 Layout Grid

```css
.os-[module]-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
@media (max-width: 1100px) {
    .os-[module]-grid-2 { grid-template-columns: 1fr; }
}
```

### 2.5 Responsive Tables

**Always** wrap large multi-column tables in a scroll container:
```html
<div style="overflow-x: auto;">
    <table class="os-[module]-table">...</table>
</div>
```

### 2.6 Print Styles

Every module stylesheet must include a print block that hides WP chrome:
```css
@media print {
    #adminmenu, #adminmenumain, #wpadminbar, #wpfooter,
    .os-[module]-tabs, .os-[module]-card-footer,
    .os-[module]-card-header-actions,
    .no-print { display: none !important; }
    body { background: #fff; }
    .os-[module]-card { box-shadow: none; page-break-inside: avoid; }
}
```

---

## 3. PHP Architecture

### 3.1 File Header (mandatory first two lines)

```php
<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
```

### 3.2 Internationalization — Zero Exceptions

| Context | Function to use |
|---|---|
| Echoed HTML text | `esc_html_e( 'String', 'olama-stores' )` |
| HTML attribute value | `esc_attr_e( 'String', 'olama-stores' )` |
| In a PHP variable | `__( 'String', 'olama-stores' )` |
| In JS via JSON block | `__( 'String', 'olama-stores' )` inside `wp_json_encode()` |

**Never** write a bare English string in any output context.

### 3.3 Nonces

**Creating (in the view file):**
```php
// One nonce per logical action group
$module_nonce = wp_create_nonce( 'os_[module]_nonce' );
```

Pass to JS via the JSON hydration block (§4), never via `wp_localize_script`.

**Verifying (in every AJAX handler):**

This step is mandatory — never process AJAX data without it:
```php
add_action( 'wp_ajax_os_[module]_action', function () {
    if ( ! check_ajax_referer( 'os_[module]_nonce', 'nonce', false ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'olama-stores' ) ) );
    }

    // ... handler logic ...

    wp_send_json_success( $result );
} );
```

`check_ajax_referer()` with `false` as the third argument prevents it from dying automatically, giving you control over the JSON error response format.

### 3.4 Script & Style Enqueueing

Always at the **bottom** of the view file, after all HTML:

```php
<?php
wp_enqueue_script(
    'os-[module]',
    OS_URL . 'admin/assets/js/os-[module].js',
    array( 'jquery' ),
    OS_VERSION,
    true   // load in footer
);

wp_enqueue_style(
    'os-[module]',
    OS_URL . 'admin/assets/css/os-[module].css',
    array(),
    OS_VERSION
);
```

**Never** use hardcoded `<script src="...">` or `<link rel="stylesheet">` tags. Never load from CDN — download vendor libs to `assets/js/vendor/` and enqueue them properly.

---

## 4. JavaScript: Data Hydration Pattern

This is the most critical architectural rule. **Never** use inline `<?php echo ?>` inside `<script>` blocks.

### 4.1 PHP Side — JSON Block

Place this just before the enqueue calls, after all HTML:

```php
<script id="os-[module]-data" type="application/json">
<?php echo wp_json_encode( array(
    'nonce'    => $module_nonce,
    'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
    'i18n'     => array(
        'errorGeneric'   => __( 'An error occurred. Please try again.', 'olama-stores' ),
        'saved'          => __( 'Saved successfully!', 'olama-stores' ),
        'confirmDelete'  => __( 'Are you sure you want to delete this?', 'olama-stores' ),
        'loading'        => __( 'Loading…', 'olama-stores' ),
        // Add all JS-facing strings here
    ),
    'state'    => $initial_state_from_db,
    // Any other PHP → JS data
) ); ?>
</script>
```

### 4.2 JS Side — IIFE Wrapper + Data Bootstrap

Every JS file follows this structure exactly:

```javascript
(function ($) {
    'use strict';

    // ── Bootstrap ────────────────────────────────────────────────────────────
    const el = document.getElementById('os-[module]-data');
    if (!el) return;

    const APP   = JSON.parse(el.textContent || '{}');
    const AJAX  = APP.ajaxUrl || '';
    const NONCE = APP.nonce   || '';
    const I18N  = APP.i18n    || {};
    let   STATE = APP.state   || {};

    // ── Tab Switching ────────────────────────────────────────────────────────
    $(document).on('click', '.os-[module]-tab', function () {
        const target = $(this).data('tab');
        $('.os-[module]-tab').removeClass('active');
        $('.os-[module]-tab-content').removeClass('active');
        $(this).addClass('active');
        $('#' + target).addClass('active');
    });

    // ── AJAX Helper ──────────────────────────────────────────────────────────
    // Always use this instead of raw $.post().
    // PHP side MUST use wp_send_json_success($data) / wp_send_json_error($data).
    // Responses are always JSON: { success: true/false, data: ... }
    function ajaxRequest(action, data, $btn) {
        if ($btn) { $btn.prop('disabled', true).prepend('<span class="os-[module]-spinner"></span> '); }

        return $.post(AJAX, Object.assign({ action, nonce: NONCE }, data), null, 'json')
            .done(function (res) {
                if (!res.success) {
                    alert(res.data?.message || I18N.errorGeneric);
                }
            })
            .fail(function () {
                alert(I18N.errorGeneric);
            })
            .always(function () {
                if ($btn) { $btn.prop('disabled', false).find('.os-[module]-spinner').remove(); }
            });
    }

    // ── Your module logic below ───────────────────────────────────────────────
    // Always use I18N.errorGeneric instead of hardcoded 'An error occurred.'
    // Always use ajaxRequest() instead of raw $.post()

})(jQuery);
```

### 4.3 Async UI Feedback Pattern

When any async action starts, immediately:
1. Disable the triggering button
2. Show a spinner inside the button
3. On completion, re-enable and remove spinner

This is handled by the `ajaxRequest()` helper above. Never fire an AJAX call without this feedback.

---

## 5. Iconography

Use **only** WordPress Dashicons. Never use Font Awesome, Material Icons, or SVG icon libraries.

Reference: https://developer.wordpress.org/resource/dashicons/

Common icons used in Olama Stores modules:

| Context | Dashicon class |
|---|---|
| Calculate / run | `dashicons-calculator` |
| Save / draft | `dashicons-saved` |
| Export spreadsheet | `dashicons-media-spreadsheet` |
| Print | `dashicons-printer` |
| Reset / undo | `dashicons-undo` |
| Settings | `dashicons-admin-settings` |
| Students / groups | `dashicons-groups` |
| Table / data | `dashicons-editor-table` |
| Info / notice | `dashicons-info` |
| Warning | `dashicons-warning` |
| Chart / analytics | `dashicons-chart-bar` |

---

## 6. WordPress Integration Patterns

### 6.1 Reading/Writing Persistent Data

```php
// Drafts and config: WordPress Options API
$data   = get_option( 'os_[module]_data', array() );
update_option( 'os_[module]_data', $sanitized_data );

// Short-lived cache: Transients API
$cached = get_transient( 'os_[module]_cache' );
if ( false === $cached ) {
    // compute...
    set_transient( 'os_[module]_cache', $result, HOUR_IN_SECONDS );
}
```

### 6.2 Olama School Integration (if needed)

```php
// Active academic year
$active_year = class_exists( 'Olama_School_Academic' )
    ? Olama_School_Academic::get_active_year()
    : null;

// Grades list
$grades = class_exists( 'Olama_School_Grade' )
    ? Olama_School_Grade::get_grades()
    : array();
```

Always guard with `class_exists()`. Never assume the school module is active.

---

## 7. Anti-Patterns — Never Do These

| ❌ Wrong | ✅ Right |
|---|---|
| `<script>var nonce = '<?php echo $nonce; ?>';</script>` | Use JSON hydration block (§4.1) |
| `alert('Please fill in the form.')` | Use `alert(I18N.fillForm)` from hydration block |
| `<script src="https://cdn.jsdelivr.net/...">` | Enqueue from `assets/js/vendor/` |
| `<style>.my-card { ... }</style>` inline in PHP | Put in dedicated `.css` file |
| Bare `$.post(AJAX, data)` | Use `ajaxRequest()` helper with spinner + `.done`/`.fail` |
| AJAX handler with no nonce check | Always call `check_ajax_referer()` first |
| `echo json_encode($data); die();` in AJAX handler | Use `wp_send_json_success()` / `wp_send_json_error()` |
| CSS variables with `--est-` prefix in new files | Use `--os-` prefix only; `--est-` is legacy |
| `echo 'Error: ' . $message;` | `echo esc_html( $message );` |
| Generic class names `.card`, `.table` | Module-prefixed `os-[module]-card` |
| Multiple `wp_enqueue_script` calls mid-template | One block at bottom of view file |
| Missing `overflow-x: auto` on wide tables | Always wrap in scroll container |
| Missing ABSPATH check | First line of every PHP file |

---

## 8. Checklist Before Submitting Code

Run through these before presenting any code to the user:

**PHP View File:**
- [ ] ABSPATH check on line 1
- [ ] All output strings use `esc_html_e()` or `esc_attr_e()`
- [ ] Nonce created and passed to JSON block
- [ ] No inline `<style>` or `<script src>` tags
- [ ] JSON hydration block present with all i18n strings
- [ ] Enqueue calls at bottom, after all HTML

**PHP AJAX Handlers:**
- [ ] Every handler calls `check_ajax_referer()` before processing data
- [ ] All responses use `wp_send_json_success()` or `wp_send_json_error()`
- [ ] Error messages are translated strings, not hardcoded English

**CSS File:**
- [ ] `:root` CSS variables use `--os-` prefix (not legacy `--est-`)
- [ ] `:root` CSS variables declared with correct values
- [ ] All classes use `os-[module]-` prefix
- [ ] Card header uses primary gradient
- [ ] Responsive breakpoints for grid at 1100px and tables at 782px
- [ ] Print media query present

**JavaScript File:**
- [ ] Wrapped in `(function ($) { 'use strict'; })(jQuery);`
- [ ] Data bootstrapped from JSON block, not from `wp_localize_script` globals
- [ ] All user-facing strings from `I18N` object
- [ ] AJAX calls use the `ajaxRequest()` helper (includes `'json'` dataType, `.done`/`.fail` handlers)
- [ ] Tab switching uses data-tab pattern
- [ ] No hardcoded English strings anywhere
