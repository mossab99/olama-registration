<?php
/**
 * Invoice CRUD and lifecycle management.
 *
 * Tables used:
 *   {prefix}olama_invoices
 *   {prefix}olama_invoice_items
 *   {prefix}olama_invoice_installments
 *   {prefix}olama_billing_audit
 */

if (!defined('ABSPATH'))
    exit;

class Olama_Reg_Billing_Invoice
{

    // ── Table helpers ─────────────────────────────────────────────────────────

    private static function t(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    // ── Generate invoice number ───────────────────────────────────────────────

    /**
     * Generate next sequential invoice number for the given year.
     * Format: INV-YYYY-NNNNN
     */
    public static function generate_number(int $year_id): string
    {
        global $wpdb;

        // Resolve 4-digit year from academic_year_id
        $year_label = '';
        if (class_exists('Olama_School_Academic')) {
            $ay = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}olama_academic_years WHERE id = %d",
                $year_id
            ));
            if ($ay && !empty($ay->year_name)) {
                // Extract first 4-digit sequence from the label (e.g. "2024-2025" → 2024)
                preg_match('/(\d{4})/', $ay->year_name, $m);
                $year_label = $m[1] ?? date('Y');
            }
        }
        if (!$year_label) {
            $year_label = date('Y');
        }

        $prefix = 'INV-' . $year_label . '-';

        $max = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(invoice_number, %d) AS UNSIGNED)), 0)
             FROM " . self::t('olama_invoices') . "
             WHERE invoice_number LIKE %s",
            strlen($prefix) + 1,
            $wpdb->esc_like($prefix) . '%'
        ));

        return $prefix . str_pad((string) ($max + 1), 5, '0', STR_PAD_LEFT);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /**
     * Create a new invoice with optional line items.
     *
     * @param array $data {
     *   family_uid, student_uid, academic_year_id, fee_template_id,
     *   issue_date, due_date, status, discount, notes,
     *   items: [ [ description, quantity, unit_price ], ... ],
     *   installments: int
     * }
     * @return int|WP_Error
     */
    public static function create(array $data): int|\WP_Error
    {
        global $wpdb;

        $family_uid = sanitize_text_field($data['family_uid'] ?? '');
        if (!$family_uid) {
            return new \WP_Error('missing_family', __('Family UID is required.', 'olama-registration'));
        }

        $year_id = absint($data['academic_year_id'] ?? 0);
        if (!$year_id) {
            return new \WP_Error('missing_year', __('Academic year is required.', 'olama-registration'));
        }

        $issue_date = self::sanitize_date($data['issue_date'] ?? date('Y-m-d'));
        if (!$issue_date)
            $issue_date = date('Y-m-d');

        $notes = sanitize_textarea_field($data['notes'] ?? '');
        $service_type = sanitize_text_field($data['service_type'] ?? '');
        if ($service_type) {
            $notes = "طبيعة الخدمة: " . $service_type . "\n" . $notes;
        }

        $payload = [
            'invoice_number' => self::generate_number($year_id),
            'family_uid' => $family_uid,
            'student_uid' => sanitize_text_field($data['student_uid'] ?? '') ?: null,
            'academic_year_id' => $year_id,
            'fee_template_id' => absint($data['fee_template_id'] ?? 0) ?: null,
            'ext_customer_id' => absint($data['ext_customer_id'] ?? 0) ?: null,
            'ext_child_id' => absint($data['ext_child_id'] ?? 0) ?: null,
            'agreement_id' => absint($data['linked_agreement_id'] ?? 0) ?: null,
            'issue_date' => $issue_date,
            'due_date' => self::sanitize_date($data['due_date'] ?? '') ?: null,
            'status' => self::valid_status($data['status'] ?? 'draft'),
            'subtotal' => 0.00,
            'discount' => self::safe_decimal($data['discount'] ?? 0),
            'total' => 0.00,
            'amount_paid' => 0.00,
            'notes' => trim($notes),
            'created_by' => get_current_user_id(),
        ];

        $result = $wpdb->insert(self::t('olama_invoices'), $payload);
        if (!$result) {
            return new \WP_Error('db_error', $wpdb->last_error);
        }

        $invoice_id = (int) $wpdb->insert_id;

        // Insert line items
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        foreach ($items as $item) {
            $qty = self::safe_decimal($item['quantity'] ?? 1);
            $unit_price = self::safe_decimal($item['unit_price'] ?? 0);
            $wpdb->insert(self::t('olama_invoice_items'), [
                'invoice_id' => $invoice_id,
                'description' => sanitize_text_field($item['description'] ?? ''),
                'quantity' => $qty,
                'unit_price' => $unit_price,
                'line_total' => round($qty * $unit_price, 2),
            ]);
        }

        self::recalculate_totals($invoice_id);

        // Generate installments if requested
        $installments = absint($data['installments'] ?? 1);
        if ($installments > 1) {
            self::generate_installments($invoice_id, $installments);
        }

        self::log_audit('invoice', $invoice_id, 'created', null, self::get_invoice($invoice_id));

        return $invoice_id;
    }

    // ── Update ────────────────────────────────────────────────────────────────

    /**
     * Update an existing invoice header and optionally its items.
     */
    public static function update(int $id, array $data): bool|\WP_Error
    {
        global $wpdb;

        $before = self::get_invoice($id);
        if (!$before) {
            return new \WP_Error('not_found', __('Invoice not found.', 'olama-registration'));
        }

        $payload = [];

        if (isset($data['family_uid']))
            $payload['family_uid'] = sanitize_text_field($data['family_uid']) ?: null;
        if (isset($data['student_uid']))
            $payload['student_uid'] = sanitize_text_field($data['student_uid']) ?: null;
        if (isset($data['fee_template_id']))
            $payload['fee_template_id'] = absint($data['fee_template_id']) ?: null;
        if (isset($data['ext_customer_id']))
            $payload['ext_customer_id'] = absint($data['ext_customer_id']) ?: null;
        if (isset($data['ext_child_id']))
            $payload['ext_child_id'] = absint($data['ext_child_id']) ?: null;
        if (isset($data['issue_date']))
            $payload['issue_date'] = self::sanitize_date($data['issue_date']) ?: $before->issue_date;
        if (isset($data['due_date']))
            $payload['due_date'] = self::sanitize_date($data['due_date']) ?: null;
        if (isset($data['status']))
            $payload['status'] = self::valid_status($data['status']);
        if (isset($data['discount']))
            $payload['discount'] = self::safe_decimal($data['discount']);
        if (isset($data['notes']))
            $payload['notes'] = sanitize_textarea_field($data['notes']);

        if (!empty($payload)) {
            $result = $wpdb->update(self::t('olama_invoices'), $payload, ['id' => $id]);
            if (false === $result) {
                return new \WP_Error('db_error', $wpdb->last_error);
            }
        }

        // Re-insert items if provided
        if (isset($data['items']) && is_array($data['items'])) {
            $wpdb->delete(self::t('olama_invoice_items'), ['invoice_id' => $id]);
            foreach ($data['items'] as $item) {
                $qty = self::safe_decimal($item['quantity'] ?? 1);
                $unit_price = self::safe_decimal($item['unit_price'] ?? 0);
                $wpdb->insert(self::t('olama_invoice_items'), [
                    'invoice_id' => $id,
                    'description' => sanitize_text_field($item['description'] ?? ''),
                    'quantity' => $qty,
                    'unit_price' => $unit_price,
                    'line_total' => round($qty * $unit_price, 2),
                ]);
            }
            self::recalculate_totals($id);
        }

        self::log_audit('invoice', $id, 'updated', $before, self::get_invoice($id));

        return true;
    }

    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Get a single invoice with its line items and installments.
     */
    public static function get_invoice(int $id): ?object
    {
        global $wpdb;

        $inv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoices') . " WHERE id = %d",
            $id
        ));

        if (!$inv)
            return null;

        $inv->items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoice_items') . " WHERE invoice_id = %d ORDER BY id ASC",
            $id
        )) ?: [];

        $inv->installments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoice_installments') . " WHERE invoice_id = %d ORDER BY installment_no ASC",
            $id
        )) ?: [];

        return $inv;
    }

    /**
     * Get all invoices for a family/year.
     */
    public static function get_family_invoices(string $family_uid, int $year_id): array
    {
        global $wpdb;

        $params = [$family_uid];
        $year_clause = '';
        if ($year_id > 0) {
            $year_clause = ' AND academic_year_id = %d';
            $params[] = $year_id;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoices') . "
             WHERE family_uid = %s {$year_clause}
             ORDER BY issue_date DESC, id DESC",
            ...$params
        )) ?: [];
    }

    /**
     * Get all invoices for an external customer/year.
     */
    public static function get_customer_invoices(int $customer_id, int $year_id): array
    {
        global $wpdb;

        $customer_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_uid FROM {$wpdb->prefix}olama_customers WHERE id = %d",
            $customer_id
        ));
        if (!$customer_uid) {
            $customer_uid = 'CUST-' . str_pad($customer_id, 4, '0', STR_PAD_LEFT);
        }

        $params = [$customer_id, $customer_uid];
        $year_clause = '';
        if ($year_id > 0) {
            $year_clause = ' AND academic_year_id = %d';
            $params[] = $year_id;
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoices') . "
             WHERE ( ext_customer_id = %d OR ( family_uid = %s AND family_uid != '' ) ) {$year_clause}
             ORDER BY issue_date DESC, id DESC",
            ...$params
        )) ?: [];
    }

    /**
     * Lightweight summary for the external customer financial tab.
     */
    public static function get_customer_invoice_summary(int $customer_id, int $year_id): object
    {
        global $wpdb;

        $customer_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT customer_uid FROM {$wpdb->prefix}olama_customers WHERE id = %d",
            $customer_id
        ));
        if (!$customer_uid) {
            $customer_uid = 'CUST-' . str_pad($customer_id, 4, '0', STR_PAD_LEFT);
        }

        $params = [$customer_id, $customer_uid];
        $year_clause = '';
        if ($year_id > 0) {
            $year_clause = ' AND academic_year_id = %d';
            $params[] = $year_id;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS invoice_count,
                COALESCE(SUM(total), 0)       AS total_invoiced,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(balance), 0)     AS balance
             FROM " . self::t('olama_invoices') . "
             WHERE ( ext_customer_id = %d OR ( family_uid = %s AND family_uid != '' ) ) {$year_clause}
               AND status NOT IN ('draft','cancelled')",
            ...$params
        ));

        return $row ?: (object) [
            'invoice_count' => 0,
            'total_invoiced' => 0,
            'total_paid' => 0,
            'balance' => 0,
        ];
    }

    /**
     * Get all overdue invoices (due_date < today, balance > 0, not cancelled).
     */
    public static function get_overdue_invoices(): array
    {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT * FROM " . self::t('olama_invoices') . "
             WHERE due_date < CURDATE()
               AND balance > 0
               AND status NOT IN ('paid','cancelled')
             ORDER BY due_date ASC"
        ) ?: [];
    }

    // ── Status ────────────────────────────────────────────────────────────────

    /**
     * Manually set invoice status (with audit trail).
     */
    public static function set_status(int $id, string $status): bool
    {
        global $wpdb;

        $before = self::get_invoice($id);
        if (!$before)
            return false;

        $status = self::valid_status($status);

        $result = $wpdb->update(
            self::t('olama_invoices'),
            ['status' => $status],
            ['id' => $id]
        );

        if (false !== $result) {
            self::log_audit('invoice', $id, 'status_changed', $before, self::get_invoice($id));
        }

        return false !== $result;
    }

    /**
     * Cancel (Void) an invoice. Blocked if payments exist.
     */
    public static function cancel(int $id): bool|\WP_Error
    {
        global $wpdb;

        $inv = self::get_invoice($id);
        if (!$inv) {
            return new \WP_Error('not_found', __('Invoice not found.', 'olama-registration'));
        }

        if ((float) $inv->amount_paid > 0) {
            return new \WP_Error('has_payments', __('لا يمكن إلغاء فاتورة مسددة جزئياً أو كلياً. يرجى عكس السندات أولاً.', 'olama-registration'));
        }

        if ($inv->status === 'cancelled') {
            return true;
        }

        $wpdb->update(self::t('olama_invoices'), ['status' => 'cancelled'], ['id' => $id]);
        $wpdb->update(self::t('olama_invoice_installments'), ['status' => 'cancelled'], ['invoice_id' => $id]);

        self::log_audit('invoice', $id, 'cancelled', $inv, self::get_invoice($id));

        return true;
    }

    // ── Totals recalculation ──────────────────────────────────────────────────

    /**
     * Recalculate subtotal, total, and auto-update status.
     * Called after any change to items or payments.
     */
    public static function recalculate_totals(int $id): void
    {
        global $wpdb;

        $inv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoices') . " WHERE id = %d",
            $id
        ));

        if (!$inv)
            return;

        // Sum line items
        $subtotal = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(line_total), 0) FROM " . self::t('olama_invoice_items') . " WHERE invoice_id = %d",
            $id
        ));

        $discount = (float) $inv->discount;
        $total = max(0.0, $subtotal - $discount);

        // Sum payments
        $amount_paid = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(amount), 0) FROM " . self::t('olama_payments') . " WHERE invoice_id = %d",
            $id
        ));

        $balance = $total - $amount_paid;

        // Auto status
        $status = $inv->status;
        if ($status !== 'cancelled' && $status !== 'draft') {
            if ($balance <= 0) {
                $status = 'paid';
            } elseif ($amount_paid > 0) {
                $status = 'partial';
            } elseif (!empty($inv->due_date) && $inv->due_date < date('Y-m-d')) {
                $status = 'overdue';
            } else {
                $status = 'issued';
            }
        }

        $wpdb->update(
            self::t('olama_invoices'),
            [
                'subtotal' => round($subtotal, 2),
                'total' => round($total, 2),
                'amount_paid' => round($amount_paid, 2),
                'balance' => round($balance, 2),
                'status' => $status,
            ],
            ['id' => $id]
        );
    }

    // ── Installments ──────────────────────────────────────────────────────────

    /**
     * Generate installment schedule for an invoice.
     * Evenly divides total; remainder cents go to first installment.
     * Due dates are spaced 30 days apart from issue_date.
     */
    public static function generate_installments(int $id, int $count = 0): bool
    {
        global $wpdb;

        $inv = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::t('olama_invoices') . " WHERE id = %d",
            $id
        ));

        if (!$inv)
            return false;

        if ($count < 1) {
            // Read from fee_template if set
            $count = 1;
            if ($inv->fee_template_id) {
                $tpl = $wpdb->get_row($wpdb->prepare(
                    "SELECT installments FROM " . self::t('olama_fee_templates') . " WHERE id = %d",
                    $inv->fee_template_id
                ));
                if ($tpl)
                    $count = max(1, (int) $tpl->installments);
            }
        }

        // Remove existing installments
        $wpdb->delete(self::t('olama_invoice_installments'), ['invoice_id' => $id]);

        if ($count <= 1)
            return true;

        $total = (float) $inv->total;
        $base = floor(($total / $count) * 100) / 100; // truncate to cents
        $remainder = round($total - ($base * $count), 2);
        $issue_date = new \DateTime($inv->issue_date ?: date('Y-m-d'));

        for ($i = 1; $i <= $count; $i++) {
            $due = clone $issue_date;
            $due->modify('+' . (($i) * 30) . ' days');

            $amount = $base;
            if ($i === 1) {
                $amount = round($base + $remainder, 2);
            }

            $wpdb->insert(self::t('olama_invoice_installments'), [
                'invoice_id' => $id,
                'installment_no' => $i,
                'due_date' => $due->format('Y-m-d'),
                'amount_due' => $amount,
                'amount_paid' => 0.00,
                'status' => 'pending',
            ]);
        }

        return true;
    }

    // ── Invoice summary for financial tab ─────────────────────────────────────

    /**
     * Lightweight summary for the family financial tab overlay.
     */
    public static function get_invoice_summary(string $family_uid, int $year_id): object
    {
        global $wpdb;

        $params = [$family_uid];
        $year_clause = '';
        if ($year_id > 0) {
            $year_clause = ' AND academic_year_id = %d';
            $params[] = $year_id;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT
                COUNT(*) AS invoice_count,
                COALESCE(SUM(total), 0)       AS total_invoiced,
                COALESCE(SUM(amount_paid), 0) AS total_paid,
                COALESCE(SUM(balance), 0)     AS balance
             FROM " . self::t('olama_invoices') . "
             WHERE family_uid = %s {$year_clause}
               AND status NOT IN ('draft','cancelled')",
            ...$params
        ));

        return $row ?: (object) [
            'invoice_count' => 0,
            'total_invoiced' => 0,
            'total_paid' => 0,
            'balance' => 0,
        ];
    }

    // ── Audit ─────────────────────────────────────────────────────────────────

    private static function log_audit(
        string $entity_type,
        int $entity_id,
        string $action,
        ?object $before,
        ?object $after
    ): void {
        global $wpdb;

        $wpdb->insert(self::t('olama_billing_audit'), [
            'entity_type' => sanitize_text_field($entity_type),
            'entity_id' => $entity_id,
            'action' => sanitize_text_field($action),
            'actor_id' => get_current_user_id(),
            'before_state' => $before ? wp_json_encode($before) : null,
            'after_state' => $after ? wp_json_encode($after) : null,
            'ip_address' => sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? ''),
        ]);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private static function valid_status(string $s): string
    {
        $allowed = ['draft', 'issued', 'partial', 'paid', 'overdue', 'cancelled'];
        return in_array($s, $allowed, true) ? $s : 'draft';
    }

    private static function sanitize_date(string $val): string
    {
        if (class_exists('Olama_School_Helpers') && method_exists('Olama_School_Helpers', 'sanitize_date')) {
            return (string) Olama_School_Helpers::sanitize_date($val);
        }
        $raw = sanitize_text_field($val);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
    }

    private static function safe_decimal($val): float
    {
        return round((float) $val, 2);
    }
}
