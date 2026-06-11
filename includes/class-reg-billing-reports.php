<?php
/**
 * Billing reports and analytics class.
 *
 * Tables used:
 *   {prefix}olama_invoices
 *   {prefix}olama_payments
 *   {prefix}olama_invoice_installments
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Billing_Reports {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    /**
     * Get overall key metrics for a given academic year.
     */
    public static function get_summary_metrics( int $year_id ): array {
        global $wpdb;

        $params = [];
        $where  = "status != 'cancelled'";
        if ( $year_id > 0 ) {
            $where .= " AND academic_year_id = %d";
            $params[] = $year_id;
        }

        $query = "SELECT 
            COALESCE(SUM(i.total + COALESCE(adj.debit_total, 0) - COALESCE(adj.credit_total, 0)), 0) AS total_invoiced,
            COALESCE(SUM(i.amount_paid), 0) AS total_collected,
            COALESCE(SUM(i.balance), 0) AS total_receivables,
            COALESCE(SUM(i.discount), 0) AS total_discount,
            COALESCE(SUM(CASE WHEN i.due_date < CURDATE() AND i.balance > 0 AND i.status NOT IN ('paid', 'cancelled') THEN i.balance ELSE 0 END), 0) AS total_overdue
            FROM " . self::t( 'olama_invoices' ) . " i
            LEFT JOIN (
                SELECT invoice_id,
                       SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END) AS debit_total,
                       SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END) AS credit_total
                FROM " . self::t( 'olama_invoice_adjustments' ) . "
                WHERE status = 'issued'
                GROUP BY invoice_id
            ) adj ON adj.invoice_id = i.id
            WHERE " . str_replace( [ 'status', 'academic_year_id' ], [ 'i.status', 'i.academic_year_id' ], $where );

        if ( ! empty( $params ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( $query, ...$params ) );
        } else {
            $row = $wpdb->get_row( $query );
        }

        return [
            'total_invoiced'    => (float) ( $row->total_invoiced ?? 0 ),
            'total_collected'   => (float) ( $row->total_collected ?? 0 ),
            'total_receivables' => (float) ( $row->total_receivables ?? 0 ),
            'total_discount'    => (float) ( $row->total_discount ?? 0 ),
            'total_overdue'     => (float) ( $row->total_overdue ?? 0 ),
        ];
    }

    /**
     * Get monthly collections breakdown.
     */
    public static function get_monthly_collections( int $year_id ): array {
        global $wpdb;

        $params = [];
        $join = "";
        $where = "1=1";

        if ( $year_id > 0 ) {
            $join = " LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = p.invoice_id";
            $where = "i.academic_year_id = %d";
            $params[] = $year_id;
        }

        $query = "SELECT 
            DATE_FORMAT(p.payment_date, '%Y-%m') AS month_label,
            SUM(p.amount) AS amount
            FROM " . self::t( 'olama_payments' ) . " p
            {$join}
            WHERE {$where}
            GROUP BY DATE_FORMAT(p.payment_date, '%Y-%m')
            ORDER BY month_label ASC";

        if ( ! empty( $params ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
        } else {
            $results = $wpdb->get_results( $query );
        }

        $data = [];
        foreach ( (array) $results as $r ) {
            $data[] = [
                'month'  => $r->month_label,
                'amount' => (float) $r->amount,
            ];
        }

        return $data;
    }

    /**
     * Get collection breakdown by payment method.
     */
    public static function get_payment_method_breakdown( int $year_id ): array {
        global $wpdb;

        $params = [];
        $join = "";
        $where = "1=1";

        if ( $year_id > 0 ) {
            $join = " LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = p.invoice_id";
            $where = "i.academic_year_id = %d";
            $params[] = $year_id;
        }

        $query = "SELECT 
            p.method,
            SUM(p.amount) AS total_amount,
            COUNT(*) AS transaction_count
            FROM " . self::t( 'olama_payments' ) . " p
            {$join}
            WHERE {$where}
            GROUP BY p.method";

        if ( ! empty( $params ) ) {
            $results = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
        } else {
            $results = $wpdb->get_results( $query );
        }

        $data = [];
        foreach ( (array) $results as $r ) {
            $data[] = [
                'method' => $r->method,
                'amount' => (float) $r->total_amount,
                'count'  => (int) $r->transaction_count,
            ];
        }

        return $data;
    }

    /**
     * Get aging receivables: 0-30, 31-60, 61-90, 91+ days overdue.
     */
    public static function get_aging_receivables( int $year_id = 0 ): array {
        global $wpdb;

        $params = [];
        $where = "i.status NOT IN ('paid','cancelled') AND (inst.amount_due - inst.amount_paid) > 0 AND inst.due_date < CURDATE()";
        if ( $year_id > 0 ) {
            $where .= " AND i.academic_year_id = %d";
            $params[] = $year_id;
        }

        $query = "SELECT 
            SUM(CASE WHEN DATEDIFF(CURDATE(), inst.due_date) <= 30 THEN (inst.amount_due - inst.amount_paid) ELSE 0 END) AS band_30,
            SUM(CASE WHEN DATEDIFF(CURDATE(), inst.due_date) BETWEEN 31 AND 60 THEN (inst.amount_due - inst.amount_paid) ELSE 0 END) AS band_60,
            SUM(CASE WHEN DATEDIFF(CURDATE(), inst.due_date) BETWEEN 61 AND 90 THEN (inst.amount_due - inst.amount_paid) ELSE 0 END) AS band_90,
            SUM(CASE WHEN DATEDIFF(CURDATE(), inst.due_date) > 90 THEN (inst.amount_due - inst.amount_paid) ELSE 0 END) AS band_90_plus
            FROM " . self::t( 'olama_invoice_installments' ) . " inst
            INNER JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = inst.invoice_id
            WHERE {$where}";

        if ( ! empty( $params ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( $query, ...$params ) );
        } else {
            $row = $wpdb->get_row( $query );
        }

        return [
            'band_30'      => (float) ( $row->band_30 ?? 0 ),
            'band_60'      => (float) ( $row->band_60 ?? 0 ),
            'band_90'      => (float) ( $row->band_90 ?? 0 ),
            'band_90_plus' => (float) ( $row->band_90_plus ?? 0 ),
        ];
    }

    public static function get_method_labels(): array {
        return [
            'cash'          => __( 'نقدي', 'olama-registration' ),
            'cheque'        => __( 'شيك بنكي', 'olama-registration' ),
            'online'        => __( 'دفع إلكتروني', 'olama-registration' ),
            'bank_transfer' => __( 'تحويل بنكي', 'olama-registration' ),
        ];
    }

    public static function get_available_payment_methods(): array {
        global $wpdb;

        $rows = $wpdb->get_col(
            "SELECT DISTINCT method FROM " . self::t( 'olama_payments' ) . "
             WHERE method IS NOT NULL AND method != ''
             ORDER BY method ASC"
        ) ?: [];

        $labels = self::get_method_labels();
        $methods = [];
        foreach ( $rows as $method ) {
            $methods[ $method ] = $labels[ $method ] ?? $method;
        }

        foreach ( $labels as $method => $label ) {
            if ( ! isset( $methods[ $method ] ) ) {
                $methods[ $method ] = $label;
            }
        }

        return $methods;
    }

    public static function get_cashier_options(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT DISTINCT u.ID, u.display_name
             FROM " . self::t( 'olama_payments' ) . " p
             INNER JOIN {$wpdb->users} u ON u.ID = p.received_by
             WHERE p.received_by > 0
             ORDER BY u.display_name ASC"
        ) ?: [];
    }

    public static function get_cash_register_report( array $filters = [] ): array {
        global $wpdb;

        $year_id     = absint( $filters['year_id'] ?? 0 );
        $date_from   = self::sanitize_report_date( $filters['date_from'] ?? '' );
        $date_to     = self::sanitize_report_date( $filters['date_to'] ?? '' );
        $method      = sanitize_text_field( $filters['method'] ?? '' );
        $cashier_id  = absint( $filters['cashier_id'] ?? 0 );

        $where  = [ "p.amount > 0", "p.method != 'reversal'" ];
        $params = [];

        if ( $year_id > 0 ) {
            $where[]  = "i.academic_year_id = %d";
            $params[] = $year_id;
        }
        if ( $date_from ) {
            $where[]  = "p.payment_date >= %s";
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[]  = "p.payment_date <= %s";
            $params[] = $date_to;
        }
        if ( $method !== '' ) {
            $where[]  = "p.method = %s";
            $params[] = $method;
        }
        if ( $cashier_id > 0 ) {
            $where[]  = "p.received_by = %d";
            $params[] = $cashier_id;
        }

        $where_sql = implode( ' AND ', $where );

        $query = "SELECT
                p.id,
                p.payment_date,
                p.method,
                p.amount,
                p.reference,
                p.notes,
                p.received_by,
                cs.session_no,
                i.invoice_number,
                i.student_uid,
                i.academic_year_id,
                COALESCE(s.student_name, ec.child_name, f.family_name, c.customer_name, p.family_uid) AS student_name,
                COALESCE(i.student_uid, ec.child_uid, p.family_uid) AS student_identifier,
                u.display_name AS received_by_name
            FROM " . self::t( 'olama_payments' ) . " p
            LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = p.invoice_id
            LEFT JOIN " . self::t( 'olama_students' ) . " s ON s.student_uid = i.student_uid
            LEFT JOIN " . self::t( 'olama_families' ) . " f ON f.family_uid = p.family_uid
            LEFT JOIN " . self::t( 'olama_customers' ) . " c ON c.customer_uid = p.family_uid
            LEFT JOIN " . self::t( 'olama_customer_children' ) . " ec ON ec.id = i.ext_child_id
            LEFT JOIN " . self::t( 'olama_cash_sessions' ) . " cs ON cs.id = p.cash_session_id
            LEFT JOIN {$wpdb->users} u ON u.ID = p.received_by
            WHERE {$where_sql}
            ORDER BY p.payment_date ASC, p.id ASC";

        $rows = $params
            ? ( $wpdb->get_results( $wpdb->prepare( $query, ...$params ) ) ?: [] )
            : ( $wpdb->get_results( $query ) ?: [] );

        $summary = [
            'cash'              => 0.0,
            'cheque'            => 0.0,
            'online'            => 0.0,
            'bank_transfer'     => 0.0,
            'other'             => 0.0,
            'total'             => 0.0,
            'transaction_count' => count( $rows ),
        ];

        foreach ( $rows as $row ) {
            $amount = (float) $row->amount;
            $method_key = (string) $row->method;
            if ( isset( $summary[ $method_key ] ) ) {
                $summary[ $method_key ] += $amount;
            } else {
                $summary['other'] += $amount;
            }
            $summary['total'] += $amount;
        }

        return [
            'rows'    => $rows,
            'summary' => $summary,
            'filters' => [
                'year_id'    => $year_id,
                'date_from'  => $date_from,
                'date_to'    => $date_to,
                'method'     => $method,
                'cashier_id' => $cashier_id,
            ],
        ];
    }

    public static function get_financial_account_options(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, account_code, account_name, type
             FROM " . self::t( 'olama_financial_accounts' ) . "
             WHERE is_active = 1
             ORDER BY type ASC, is_default DESC, account_name ASC"
        ) ?: [];
    }

    public static function get_account_ledger_report( array $filters = [] ): array {
        global $wpdb;

        $account_id = absint( $filters['account_id'] ?? 0 );
        $date_from  = self::sanitize_report_date( $filters['date_from'] ?? '' );
        $date_to    = self::sanitize_report_date( $filters['date_to'] ?? '' );

        $where = [ '1=1' ];
        $params = [];
        if ( $account_id > 0 ) {
            $where[] = 'm.account_id = %d';
            $params[] = $account_id;
        }
        if ( $date_from ) {
            $where[] = 'm.movement_date >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[] = 'm.movement_date <= %s';
            $params[] = $date_to;
        }

        $query = "SELECT
                m.*,
                a.account_code,
                a.account_name,
                a.type AS account_type,
                a.opening_balance,
                p.payment_no,
                p.invoice_id
            FROM " . self::t( 'olama_cash_bank_movements' ) . " m
            LEFT JOIN " . self::t( 'olama_financial_accounts' ) . " a ON a.id = m.account_id
            LEFT JOIN " . self::t( 'olama_payments' ) . " p ON m.source_type = 'payment' AND p.id = m.source_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY m.movement_date ASC, m.id ASC";

        $rows = $params
            ? ( $wpdb->get_results( $wpdb->prepare( $query, ...$params ) ) ?: [] )
            : ( $wpdb->get_results( $query ) ?: [] );

        $running = [];
        foreach ( $rows as $row ) {
            $aid = (int) $row->account_id;
            if ( ! isset( $running[ $aid ] ) ) {
                $running[ $aid ] = (float) ( $row->opening_balance ?? 0 );
            }
            $running[ $aid ] += (string) $row->direction === 'out' ? -1 * (float) $row->amount : (float) $row->amount;
            $row->running_balance = round( $running[ $aid ], 2 );
        }

        return [
            'rows'    => $rows,
            'filters' => [
                'account_id' => $account_id,
                'date_from'  => $date_from,
                'date_to'    => $date_to,
            ],
        ];
    }

    public static function get_receipts_report( array $filters = [] ): array {
        global $wpdb;

        $date_from  = self::sanitize_report_date( $filters['date_from'] ?? '' );
        $date_to    = self::sanitize_report_date( $filters['date_to'] ?? '' );
        $method     = sanitize_text_field( $filters['method'] ?? '' );
        $status     = sanitize_key( $filters['status'] ?? '' );
        $account_id = absint( $filters['account_id'] ?? 0 );

        $where = [ '1=1' ];
        $params = [];
        if ( $date_from ) {
            $where[] = 'p.payment_date >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[] = 'p.payment_date <= %s';
            $params[] = $date_to;
        }
        if ( $method !== '' ) {
            $where[] = 'p.method = %s';
            $params[] = $method;
        }
        if ( $status !== '' ) {
            $where[] = 'p.status = %s';
            $params[] = $status;
        }
        if ( $account_id > 0 ) {
            $where[] = 'p.account_id = %d';
            $params[] = $account_id;
        }

        $query = "SELECT p.*, i.invoice_number, a.account_name, cs.session_no, u.display_name AS received_by_name
            FROM " . self::t( 'olama_payments' ) . " p
            LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = p.invoice_id
            LEFT JOIN " . self::t( 'olama_financial_accounts' ) . " a ON a.id = p.account_id
            LEFT JOIN " . self::t( 'olama_cash_sessions' ) . " cs ON cs.id = p.cash_session_id
            LEFT JOIN {$wpdb->users} u ON u.ID = p.received_by
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY p.payment_date DESC, p.id DESC";

        $rows = $params ? ( $wpdb->get_results( $wpdb->prepare( $query, ...$params ) ) ?: [] ) : ( $wpdb->get_results( $query ) ?: [] );

        return [
            'rows'    => $rows,
            'filters' => compact( 'date_from', 'date_to', 'method', 'status', 'account_id' ),
        ];
    }

    public static function get_allocation_report( array $filters = [] ): array {
        global $wpdb;

        $date_from = self::sanitize_report_date( $filters['date_from'] ?? '' );
        $date_to   = self::sanitize_report_date( $filters['date_to'] ?? '' );

        $where = [ '1=1' ];
        $params = [];
        if ( $date_from ) {
            $where[] = 'pa.allocation_date >= %s';
            $params[] = $date_from;
        }
        if ( $date_to ) {
            $where[] = 'pa.allocation_date <= %s';
            $params[] = $date_to;
        }

        $query = "SELECT pa.*, p.payment_no, p.method, p.status AS payment_status,
                i.invoice_number, inst.installment_no, inst.due_date, inst.amount_due
            FROM " . self::t( 'olama_payment_allocations' ) . " pa
            LEFT JOIN " . self::t( 'olama_payments' ) . " p ON p.id = pa.payment_id
            LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = pa.invoice_id
            LEFT JOIN " . self::t( 'olama_invoice_installments' ) . " inst ON inst.id = pa.installment_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY pa.allocation_date DESC, pa.id DESC";

        $rows = $params ? ( $wpdb->get_results( $wpdb->prepare( $query, ...$params ) ) ?: [] ) : ( $wpdb->get_results( $query ) ?: [] );

        return [
            'rows'    => $rows,
            'filters' => compact( 'date_from', 'date_to' ),
        ];
    }

    public static function get_cheques_report( array $filters = [] ): array {
        global $wpdb;

        $status = sanitize_key( $filters['status'] ?? '' );
        $where = [ '1=1' ];
        $params = [];
        if ( $status !== '' ) {
            $where[] = 'c.status = %s';
            $params[] = $status;
        }

        $query = "SELECT c.*, p.payment_no, p.payment_date, p.reference, p.status AS payment_status,
                i.invoice_number, a.account_name
            FROM " . self::t( 'olama_cheques' ) . " c
            LEFT JOIN " . self::t( 'olama_payments' ) . " p ON p.id = c.payment_id
            LEFT JOIN " . self::t( 'olama_invoices' ) . " i ON i.id = p.invoice_id
            LEFT JOIN " . self::t( 'olama_financial_accounts' ) . " a ON a.id = p.account_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY c.due_date ASC, c.id DESC";

        $rows = $params ? ( $wpdb->get_results( $wpdb->prepare( $query, ...$params ) ) ?: [] ) : ( $wpdb->get_results( $query ) ?: [] );

        return [
            'rows'    => $rows,
            'filters' => [ 'status' => $status ],
        ];
    }

    private static function sanitize_report_date( string $date ): string {
        $date = sanitize_text_field( $date );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
    }
}
