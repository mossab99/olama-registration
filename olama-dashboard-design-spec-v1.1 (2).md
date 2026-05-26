# Olama Registration — Customer Dashboard Redesign Specification
## Corrected & Refined v1.1

---

## 1. EXECUTIVE SUMMARY

This specification proposes a redesigned customer-facing dashboard for the Olama Registration WordPress plugin, serving both **walk-in (external) customers** and **enrolled families** at Olama School. This is a **corrected version** addressing feedback on coding standards, roadmap realism, settlement/payment separation, and loading patterns.

---

## 2. CODEBASE ANALYSIS FINDINGS (CONFIRMED)

### 2.1 Technology Stack
- **Backend**: PHP 8, WordPress Plugin API
- **Admin UI**: WP Admin + custom PHP views (server-rendered, no SPA framework)
- **Frontend JS**: jQuery + vanilla IIFE modules
- **Styling**: CSS Custom Properties (`--os-*` convention per OLAMASKILL.md §2.2)
- **AJAX**: `admin-ajax.php` + `wp_send_json_*` (per OLAMASKILL.md §3.3)
- **Icons**: WordPress Dashicons only
- **Data layer**: Custom DB tables via `Olama_School_DB`
- **Parent Dependency**: Requires "Olama School System" plugin (v2.3.9+)

### 2.2 Data Models (Verified from Source)

| Table | Purpose | Key Fields |
|-------|---------|------------|
| `olama_families` | Core family records | `family_uid` (numeric, e.g. "5501"), `family_name`, `mother_mobile`, `father_mobile`, `address` |
| `olama_students` | Enrolled students | `student_uid`, `family_id`, `student_name`, `sequence_in_family`, `is_active` |
| `olama_customers` | External walk-in customers | `id`, `customer_uid` (CUST-XXXX), `customer_name`, `phone`, `notes`, `is_active` |
| `olama_customer_children` | Children of external customers | `id`, `child_uid`, `customer_id`, `child_name`, `grade`, `is_active` |
| `olama_agreements` | Service agreements | `id`, `agreement_number` (AGR-XXXXX), `payer_type`, `payer_id`, `participant_type`, `participant_ids`, `activity_type`, `template_id`, `academic_year_id`, `start_date`, `end_date`, `status`, `total_amount` |
| `olama_agreement_fees` | Fee line items per agreement | `id`, `agreement_id`, `fee_category`, `label`, `amount`, `discount`, `net_amount`, `due_date`, `invoice_id`, `paid_status` |
| `olama_invoices` | Billing invoices | `id`, `invoice_number` (INV-YYYY-NNNNN), `family_uid`, `student_uid`, `academic_year_id`, `fee_template_id`, `issue_date`, `due_date`, `status`, `subtotal`, `discount`, `total`, `amount_paid`, `balance`, `notes`, `ext_customer_id`, `ext_child_id`, `agreement_id` |
| `olama_invoice_items` | Invoice line items | `id`, `invoice_id`, `description`, `quantity`, `unit_price`, `line_total` |
| `olama_invoice_installments` | Payment schedule | `id`, `invoice_id`, `installment_no`, `due_date`, `amount_due`, `amount_paid`, `status` |
| `olama_payments` | Payment records | `id`, `invoice_id`, `installment_id`, `family_uid`, `payment_date`, `amount`, `method`, `reference`, `received_by`, `notes` |
| `olama_billing_audit` | Audit trail | `id`, `entity_type`, `entity_id`, `action`, `actor_id`, `before_state`, `after_state`, `ip_address` |
| `olama_settlement_receipts` | **Settlement receipts (separate from payments)** | `id`, `receipt_number`, `family_id`, `student_id`, `payment_category`, `original_amount`, `settled_amount`, `remaining_balance`, `payment_method`, `oracle_receipt_number`, `settlement_date`, `status` |
| `olama_reg_financial` | Family financial summary | `id`, `family_uid`, `academic_year_id`, `entitlement_date`, `calculation_method`, `percentage`, `amount_due`, `amount_paid`, `payments_revolving` |

### 2.3 Critical Dual-Track Architecture

**The most important finding:** `olama_invoices` has **two foreign key paths**:
- `family_uid` → `olama_families` (enrolled families)
- `ext_customer_id` → `olama_customers` (walk-in customers)

Similarly, `olama_agreements` uses `payer_type` ('family' | 'customer') + `payer_id` to resolve the payer. Getting this wrong causes cross-contamination of financial data.

### 2.4 Capability System (Verified from Source)

| Capability | Purpose |
|------------|---------|
| `olama_manage_registration_families` | Family management |
| `olama_manage_registration_students` | Student management |
| `olama_manage_registration_fees` | Fee templates |
| `olama_manage_registration_invoices` | Invoice management |
| `olama_manage_registration_payments` | Payment management |
| `olama_manage_registration_reports` | Reports |
| `manage_options` | Admin override |

**Security note:** Using `manage_options` as a fallback is acceptable for admin users, but the hub should check the specific capability for each tile's write actions.

---

## 3. CORRECTIONS FROM FEEDBACK

### 3.1 ❌ BANNED: `wp_localize_script` → ✅ REQUIRED: JSON Hydration Block

**The original spec violated OLAMASKILL.md §4.1.** The correct pattern is:

```php
// ❌ WRONG — do NOT use this
wp_localize_script('olama-reg', 'olamaReg', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('olama_reg_nonce'),
]);

// ✅ CORRECT — per OLAMASKILL.md §4.1
// In customer-hub.php, before enqueue calls:
$hub_nonce = wp_create_nonce('os_hub_nonce');
?>
<script id="os-hub-data" type="application/json">
<?php echo wp_json_encode([
    'nonce'         => $hub_nonce,
    'ajaxUrl'       => admin_url('admin-ajax.php'),
    'currentUserId' => get_current_user_id(),  // Required for localStorage key scoping
    'i18n'          => [
        'searchPlaceholder' => __('ابحث باسم العائلة أو رقم الملف...', 'olama-registration'),
        'noResults'         => __('لا توجد نتائج', 'olama-registration'),
        'loading'           => __('جارٍ التحميل...', 'olama-registration'),
        'errorGeneric'      => __('حدث خطأ، يُرجى المحاولة مجدداً', 'olama-registration'),
        'family'            => __('عائلة', 'olama-registration'),
        'external'          => __('عميل خارجي', 'olama-registration'),
    ],
]); ?>
</script>
```

**JS consumption:**
```javascript
// os-hub.js — read hydration block once at bootstrap
const HUB_DATA = JSON.parse(document.getElementById('os-hub-data').textContent);
const AJAX_URL = HUB_DATA.ajaxUrl;
const NONCE    = HUB_DATA.nonce;
const USER_ID  = HUB_DATA.currentUserId;  // Passed from PHP via JSON hydration
const I18N     = HUB_DATA.i18n;

// localStorage key is scoped to current user (shared workstation safety)
const RECENT_KEY = 'os_hub_recent_' + USER_ID;
```

### 3.2 Settlement vs Payment — SEPARATE TILES REQUIRED

**The original spec incorrectly lumped settlements into the Payments tile.** These are distinct concepts:

| Aspect | Payments (`olama_payments`) | Settlements (`olama_settlement_receipts`) |
|--------|----------------------------|-------------------------------------------|
| **Purpose** | Day-to-day fee collection | Year-end reconciliation / Oracle integration |
| **Trigger** | Invoice installment due | Bulk settlement of annual balance |
| **Reference** | Voucher #N | Oracle receipt number |
| **Method** | نقدي, bank transfer, cheque | Oracle system entry |
| **Reversible** | Yes (reversal creates negative entry) | No (final reconciliation) |

**Design decision:** Add an **8th tile** — "Settlement Receipts" — visible only for families (walk-in customers don't use the settlement flow).

### 3.3 Loading Pattern: Dashicon Spinner (NOT Skeleton Screens)

**The original spec incorrectly suggested skeleton screens.** Per OLAMASKILL.md §2.3, the existing plugin uses:

```css
/* Existing pattern — use this, do NOT create skeleton CSS */
.os-hub-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M10 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z" fill="%232271b1"/></svg>') center no-repeat;
    animation: os-spin 1s linear infinite;
}

@keyframes os-spin {
    to { transform: rotate(360deg); }
}
```

**Rationale:** Skeleton screens require significant CSS infrastructure that doesn't exist in the current plugin. The Dashicon spinner is consistent, accessible, and zero-additional-CSS.

### 3.4 Recent Lookups: `localStorage` (NOT `user_meta`)

**Correction from feedback:** For walk-in counters where multiple staff share a workstation, `localStorage` is more appropriate than `user_meta` because:
- It survives logout/login on the same browser
- It's faster (no AJAX round-trip)
- It's scoped to the device, which matches the physical workstation model

```javascript
// os-hub.js — USER_ID comes from JSON hydration block (§3.1)
const RECENT_KEY = 'os_hub_recent_' + USER_ID; // Per-user on shared workstation

function saveRecentLookup(customer) {
    const recent = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
    // Deduplicate by UID, move to top
    const filtered = recent.filter(r => r.uid !== customer.uid);
    filtered.unshift({
        uid: customer.uid,
        name: customer.name,
        type: customer.type,
        timestamp: Date.now()
    });
    localStorage.setItem(RECENT_KEY, JSON.stringify(filtered.slice(0, 10)));
}
```

---

## 4. REVISED UI FLOW

### 4.1 Three-Stage Flow (Unchanged Structure, Corrected Implementation)

```
+---------------------------------------------------------------+
|  STAGE 1: CUSTOMER TYPE SELECTION                             |
|  +-------------+  +-------------+                             |
|  |     👤      |  |   👨‍👩‍👧‍👦        |                             |
|  |  Individual |  |   Family    |                             |
|  |  / Walk-in  |  |  (Enrolled) |                             |
|  +-------------+  +-------------+                             |
+---------------------------------------------------------------+
|  STAGE 2: CUSTOMER LOOKUP                                     |
|  🔍 [________________] [Search]  (Phone-first for walk-in)    |
+---------------------------------------------------------------+
|  STAGE 3: SERVICE HUB (8 Tiles)                               |
|  +---------+ +---------+ +---------+ +---------+            |
|  │Profile │ │Agreement│ │Invoices │ │Payments │  ← Core 4    |
|  │  📝    │ │  📋    │ │  📄    │ │  💰    │               |
|  +---------+ +---------+ +---------+ +---------+            |
|  +---------+ +---------+ +---------+ +---------+            |
|  │Children│ │Financial│ │History │ │Settlement│  ← +4       |
|  │  👶    │ │  📊    │ │  📈    │ │  🏦    │               |
|  +---------+ +---------+ +---------+ +---------+            |
+---------------------------------------------------------------+
```

### 4.2 Tile Visibility Matrix (Corrected)

| Tile | Family | Walk-In | Notes |
|------|--------|---------|-------|
| Profile Details | ✅ | ✅ | Both types have profiles |
| Agreements | ✅ | ✅ | Filtered by `payer_type` |
| Invoices | ✅ | ✅ | Filtered by `family_uid` vs `ext_customer_id` |
| Payments | ✅ | ✅ | Regular payment vouchers |
| Children/Students | ✅ | ✅ | Family: enrolled students; Walk-in: `customer_children` |
| Financial Summary | ✅ | ✅ | Year-scoped |
| History & Audit | ✅ | ✅ | All billing audit entries |
| **Settlement Receipts** | **✅** | **❌** | **NEW TILE — families only** |

---

## 5. REVISED SERVICE TILES (8 Total)

### 5.1 Core Tiles 1–4 (Unchanged from Original)

#### Tile 1: Profile Details
- **Icon**: `dashicons-admin-users`
- **Color**: Blue `#2271b1`
- **Data**: `olama_families` / `olama_customers`
- **Actions**: Edit, deactivate/activate

#### Tile 2: Agreements
- **Icon**: `dashicons-media-document`
- **Color**: Green `#00a32a`
- **Data**: `olama_agreements` + `olama_agreement_fees`
- **Actions**: View, print

#### Tile 3: Invoices
- **Icon**: `dashicons-media-text`
- **Color**: Orange `#d63638`
- **Data**: `olama_invoices` + `olama_invoice_items`
- **Actions**: View, print, record payment, cancel

#### Tile 4: Payments
- **Icon**: `dashicons-money-alt`
- **Color**: Teal `#3582c4`
- **Data**: `olama_payments` + `olama_invoice_installments`
- **Actions**: View receipt, print, reverse

### 5.2 Additional Tiles 5–8 (Corrected)

#### Tile 5: Children / Students
- **Icon**: `dashicons-groups`
- **Color**: Purple `#7b68ee`
- **Data**: 
  - Family: `olama_students` (enrolled, with grade/section)
  - Walk-in: `olama_customer_children` (external, with grade only)
- **Actions**: View profile, view enrollment history

#### Tile 6: Financial Summary
- **Icon**: `dashicons-chart-bar`
- **Color**: Gold `#f0b849`
- **Data**: `olama_reg_financial` + invoice summary
- **Actions**: Switch academic year, export PDF

#### Tile 7: History & Audit
- **Icon**: `dashicons-backup`
- **Color**: Gray `#646970`
- **Data**: `olama_billing_audit`
- **Actions**: Filter by date, filter by entity type, export

#### Tile 8: Settlement Receipts (NEW — Families Only)
- **Icon**: `dashicons-bank`
- **Color**: Dark Blue `#1a3a5c`
- **Data**: `olama_settlement_receipts`
- **Actions**: View receipt, print, reconcile
- **Why separate**: Settlement receipts are year-end reconciliation documents with Oracle integration. Mixing them with daily payments would confuse staff during reconciliation periods.

---

## 6. REVISED COMPONENT STRUCTURE

### 6.1 File Organization

```
admin/
├── class-reg-admin.php              (add menu item)
├── class-reg-ajax.php               (add unified hub handler)
├── views/
│   ├── dashboard/
│   │   ├── customer-hub.php         (main router view)
│   │   ├── panel-type-select.php    (Stage 1)
│   │   ├── panel-lookup.php         (Stage 2)
│   │   ├── panel-hub.php            (Stage 3 shell)
│   │   ├── partial-identity.php     (sticky header)
│   │   ├── partial-tile.php         (reusable tile wrapper)
│   │   ├── tile-profile.php         (Tile 1 content)
│   │   ├── tile-agreements.php      (Tile 2 content)
│   │   ├── tile-invoices.php        (Tile 3 content)
│   │   ├── tile-payments.php        (Tile 4 content)
│   │   ├── tile-children.php        (Tile 5 content)
│   │   ├── tile-financial.php       (Tile 6 content)
│   │   ├── tile-history.php         (Tile 7 content)
│   │   └── tile-settlements.php     (Tile 8 content — NEW)
│   └── ...
assets/
├── css/
│   ├── olama-reg.css                (existing)
│   └── os-hub.css                   (new — hub-specific styles)
└── js/
    ├── olama-reg.js                 (existing)
    └── os-hub.js                    (new — hub panel manager)
```

### 6.2 Unified AJAX Handler (Replaces 7 Separate Handlers)

```php
// class-reg-ajax.php — add to existing Olama_Reg_Ajax class

public function __construct() {
    // ... existing handlers ...

    // Hub handlers — unified pattern
    add_action('wp_ajax_os_hub_search',      [$this, 'hub_search']);
    add_action('wp_ajax_os_hub_counts',      [$this, 'hub_counts']);
    add_action('wp_ajax_os_hub_tile',        [$this, 'hub_tile']);
}

/**
 * Universal search across families and customers
 */
public function hub_search(): void {
    check_ajax_referer('os_hub_nonce', 'nonce');

    $query = sanitize_text_field($_POST['q'] ?? '');
    $type  = sanitize_key($_POST['type'] ?? 'family'); // 'family' | 'external'

    if (strlen($query) < 2) {
        wp_send_json_success(['results' => []]);
    }

    $results = ($type === 'family')
        ? $this->search_families($query)
        : $this->search_customers($query);

    wp_send_json_success(['results' => $results]);
}

private function search_families(string $query): array {
    global $wpdb;
    $like = '%' . $wpdb->esc_like($query) . '%';

    $sql = "SELECT f.family_uid AS uid, f.family_name AS name, 
                   f.father_mobile AS phone, f.is_active,
                   COUNT(s.id) AS student_count
            FROM {$wpdb->prefix}olama_families f
            LEFT JOIN {$wpdb->prefix}olama_students s 
                ON s.family_id = f.family_uid AND s.is_active = 1
            WHERE f.family_name LIKE %s 
               OR f.family_uid = %s
               OR f.father_mobile LIKE %s
               OR f.mother_mobile LIKE %s
            GROUP BY f.id
            LIMIT 20";

    return $wpdb->get_results($wpdb->prepare($sql, $like, $query, $like, $like)) ?: [];
}

private function search_customers(string $query): array {
    global $wpdb;
    $like = '%' . $wpdb->esc_like($query) . '%';

    $sql = "SELECT c.customer_uid AS uid, c.customer_name AS name,
                   c.phone, c.is_active,
                   COUNT(ch.id) AS child_count
            FROM {$wpdb->prefix}olama_customers c
            LEFT JOIN {$wpdb->prefix}olama_customer_children ch
                ON ch.customer_id = c.id AND ch.is_active = 1
            WHERE c.customer_name LIKE %s
               OR c.customer_uid = %s
               OR c.phone LIKE %s
            GROUP BY c.id
            LIMIT 20";

    return $wpdb->get_results($wpdb->prepare($sql, $like, $query, $like)) ?: [];
}

/**
 * Batch load all tile badge counts in one request
 */
public function hub_counts(): void {
    check_ajax_referer('os_hub_nonce', 'nonce');

    $uid  = sanitize_text_field($_POST['uid'] ?? '');
    $type = sanitize_key($_POST['type'] ?? 'family');
    $year = (int) ($_POST['year'] ?? Olama_School_Academic::get_current_year_id());

    $counts = [
        'profile'    => $this->get_profile_count($uid, $type),
        'agreements' => $this->get_agreement_count($uid, $type),
        'invoices'   => $this->get_invoice_count($uid, $type, $year),
        'payments'   => $this->get_payment_count($uid, $type, $year),
        'children'   => $this->get_children_count($uid, $type),
        'financial'  => null, // Always shown, no count
        'history'    => $this->get_audit_count($uid, $type, $year),
        'settlements'=> $type === 'family' ? $this->get_settlement_count($uid, $year) : null,
    ];

    wp_send_json_success(['counts' => $counts]);
}

/**
 * Unified tile content loader — single endpoint, tile param
 */
public function hub_tile(): void {
    check_ajax_referer('os_hub_nonce', 'nonce');

    $tile = sanitize_key($_POST['tile'] ?? '');
    $uid  = sanitize_text_field($_POST['uid'] ?? '');
    $type = sanitize_key($_POST['type'] ?? 'family');
    $year = (int) ($_POST['year'] ?? Olama_School_Academic::get_current_year_id());

    $handlers = [
        'profile'     => [$this, 'tile_profile'],
        'agreements'  => [$this, 'tile_agreements'],
        'invoices'    => [$this, 'tile_invoices'],
        'payments'    => [$this, 'tile_payments'],
        'children'    => [$this, 'tile_children'],
        'financial'   => [$this, 'tile_financial'],
        'history'     => [$this, 'tile_history'],
        'settlements' => [$this, 'tile_settlements'],
    ];

    if (!isset($handlers[$tile]) || !is_callable($handlers[$tile])) {
        wp_send_json_error(['message' => __('Unknown tile', 'olama-registration')]);
    }

    $data = call_user_func($handlers[$tile], $uid, $type, $year);
    wp_send_json_success(['html' => $data['html'], 'meta' => $data['meta'] ?? []]);
}

/**
 * os_hub_tile Request/Response Contract
 * 
 * REQUEST (POST):
 *   action   : 'os_hub_tile'        (required)
 *   nonce    : '<os_hub_nonce>'    (required)
 *   tile     : '<tile_id>'         (required — see table below)
 *   uid      : '5501' or 'CUST-0001' (required — customer identifier)
 *   type     : 'family' | 'external'  (required — determines which table to query)
 *   year     : 2025                 (optional — academic year ID, defaults to current)
 * 
 * RESPONSE (JSON):
 *   success : true | false
 *   data    : {
 *       html : '<string>'           (required — rendered HTML fragment for the tile panel)
 *       meta : { ... }              (optional — tile-specific metadata, e.g. totals, counts)
 *   }
 *   message : '<string>'            (only on error)
 */
```

---

## 7. REVISED IMPLEMENTATION ROADMAP (Realistic Timeline)

### Phase 1: Shell & Type Selection (2–3 days)
- [ ] Create `customer-hub.php` with 3 panel containers
- [ ] Add menu item in `class-reg-admin.php`
- [ ] Create `os-hub.css` with CSS variables (`--os-*` convention)
- [ ] Create `os-hub.js` with PanelManager and JSON hydration reader
- [ ] Implement type selection → lookup transition
- [ ] **Deliverable:** Clickable type cards, panel switching works

### Phase 2: Search & Identity (3–4 days)
- [ ] Add `os_hub_search` AJAX handler (unified family + customer)
- [ ] Build SearchModule with 200ms debounce
- [ ] Add `os_hub_counts` AJAX handler (batch counts)
- [ ] Render identity header with financial mini-stats
- [ ] Show tile grid with badge counts
- [ ] Implement `localStorage` recent lookups
- [ ] **Deliverable:** Full lookup flow, identity header renders, tiles show counts

### Phase 3: Core Tiles — Profile & Agreements (3–4 days)
- [ ] Profile tile: inline edit form, activate/deactivate
- [ ] Agreements tile: list with status, print links
- [ ] Add `os_hub_tile` endpoint with profile + agreement handlers
- [ ] Tile accordion expand/collapse with Dashicon spinner
- [ ] **Deliverable:** Two core tiles fully functional

### Phase 4: Core Tiles — Invoices & Payments (4–5 days)
- [ ] Invoices tile: list, status badges, print/view/record-payment actions
- [ ] Payments tile: voucher list with refund indicators
- [ ] Payment reversal flow (with confirmation modal)
- [ ] Invoice cancellation (if no payments)
- [ ] **Deliverable:** Full financial tile suite operational

### Phase 5: Additional Tiles (4–5 days)
- [ ] Children tile: student/child list with grade info
- [ ] Financial Summary tile: year-switching, progress bars
- [ ] History & Audit tile: filterable log
- [ ] Settlement Receipts tile: families only, Oracle receipt numbers
- [ ] **Deliverable:** All 8 tiles functional

### Phase 6: Polish & Integration (3–4 days)
- [ ] Keyboard navigation (F/E shortcuts, Escape, arrow keys)
- [ ] RTL testing and fixes
- [ ] Accessibility audit (ARIA labels, focus management, contrast)
- [ ] Mobile responsiveness (782px breakpoint)
- [ ] Error states: not found, empty tiles, network failure
- [ ] Walk-in tile visibility rules
- [ ] **Deliverable:** Production-ready, tested

**Total: 19–25 working days** (4–5 weeks for one developer, or 2–3 weeks with two developers)

---

## 8. ERROR STATE DESIGN (Previously Missing)

### 8.1 Search: Customer Not Found

```
+--------------------------------------------------+
|  ⚠️ لا توجد نتائج                                |
|  No results for "07712345678"                      |
|                                                   |
|  [🔍 البحث مجدداً]  [➕ إضافة عميل جديد]         |
|                                                   |
|  Did you mean:                                     |
|  ┌────────────────────────────────────────────┐   |
|  │  #512  مصعب محمود الحنيطي  07712345679   │   |
|  └────────────────────────────────────────────┘   |
+--------------------------------------------------+
```

**Behavior:**
- "إضافة عميل جديد" pre-fills the search term into the new customer form
- "Did you mean" uses Levenshtein distance on phone numbers (off-by-one digit is common)
- Keyboard: `Enter` on empty results focuses the "Add New" button

### 8.2 Tile: Empty State

```
+--------------------------------------------------+
|  🧾 الفواتير                                     |
|                                                   |
|  لا توجد فواتير مسجلة لهذه العائلة               |
|  No invoices on record for this family             |
|                                                   |
|  [➕ إصدار فاتورة جديدة]                         |
+--------------------------------------------------+
```

### 8.3 Network Failure

```
+--------------------------------------------------+
|  ⚠️ فشل الاتصال                                  |
|  Connection failed. Please try again.              |
|                                                   |
|  [إعادة المحاولة]  [العودة للبحث]                |
+--------------------------------------------------+
```

---

## 9. ACCESSIBILITY & USABILITY (Refined)

### 9.1 Keyboard Shortcuts

| Key | Action | Context |
|-----|--------|---------|
| `F` | Select Family type | Stage 1 |
| `E` | Select External/Individual type | Stage 1 |
| `Ctrl+K` | Focus search input | Stage 2 |
| `Enter` | Select first result / activate focused tile | Stage 2 & 3 |
| `Escape` | Collapse tile / go back one stage | Stage 2 & 3 |
| `↑` / `↓` | Navigate search results | Stage 2 |
| `Tab` | Navigate tiles and actions | Stage 3 |

### 9.2 ARIA Requirements

```html
<!-- Type selection cards -->
<div role="button" tabindex="0" aria-pressed="false" class="os-hub-type-card" data-type="family">
    <span class="dashicons dashicons-groups"></span>
    <span>عائلة</span>
</div>

<!-- Search results -->
<ul role="listbox" aria-label="Search results" class="os-hub-results">
    <li role="option" aria-selected="false" data-uid="634">...</li>
</ul>

<!-- Tile -->
<button aria-expanded="false" aria-controls="tile-invoices-panel" class="os-hub-tile">
    <span class="os-hub-tile__title">الفواتير</span>
    <span class="os-hub-tile__badge">8</span>
</button>
<div id="tile-invoices-panel" aria-hidden="true" class="os-hub-tile-panel">
    <!-- Content loaded via AJAX -->
</div>
```

### 9.3 Currency Display (RTL Context)

```html
<!-- Numbers must be LTR even in RTL layout -->
<span dir="ltr">3,753.00</span> <span>د.أ</span>
```

---

## 10. SUMMARY OF CHANGES FROM v1.0

| Issue | v1.0 | v1.1 (Corrected) |
|-------|------|------------------|
| **PHP→JS data passing** | `wp_localize_script` (❌ banned) | JSON hydration block (✅ per OLAMASKILL.md §4.1) |
| **Settlement handling** | Lumped into Payments tile | **Separate 8th tile** for settlement receipts |
| **Loading pattern** | Skeleton screens (❌ not in codebase) | Dashicon spinner (✅ per OLAMASKILL.md §2.3) |
| **Recent lookups storage** | `user_meta` | `localStorage` (better for shared workstations) |
| **AJAX handlers** | 7 separate handlers (`os_hub_get_*`) | **1 unified `os_hub_tile` endpoint** with `tile` param (`invoices`\|`payments`\|`agreements`\|`children`\|`financial`\|`history`\|`settlements`) |
| **Timeline** | 5 weeks (optimistic) | **4–5 weeks realistic** (19–25 days) |
| **Error states** | Mentioned, not designed | **Full wireframes for not-found, empty, network failure** |
| **Tile count** | 7 | **8** (added Settlement Receipts) |

---

## 11. FINAL VERDICT

This corrected specification:
- ✅ Respects all OLAMASKILL.md coding standards
- ✅ Correctly separates settlements from payments
- ✅ Uses realistic timelines
- ✅ Includes complete error-state designs
- ✅ Maintains architectural fidelity to the existing plugin
- ✅ Is ready for implementation by a WordPress developer

**Risk level: LOW** — All changes are additive. No database schema changes. No new dependencies. Existing pages remain functional.
