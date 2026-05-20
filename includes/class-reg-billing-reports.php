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
            COALESCE(SUM(total), 0) AS total_invoiced,
            COALESCE(SUM(amount_paid), 0) AS total_collected,
            COALESCE(SUM(balance), 0) AS total_receivables,
            COALESCE(SUM(CASE WHEN due_date < CURDATE() AND balance > 0 AND status NOT IN ('paid', 'cancelled') THEN balance ELSE 0 END), 0) AS total_overdue
            FROM " . self::t( 'olama_invoices' ) . "
            WHERE {$where}";

        if ( ! empty( $params ) ) {
            $row = $wpdb->get_row( $wpdb->prepare( $query, ...$params ) );
        } else {
            $row = $wpdb->get_row( $query );
        }

        return [
            'total_invoiced'    => (float) ( $row->total_invoiced ?? 0 ),
            'total_collected'   => (float) ( $row->total_collected ?? 0 ),
            'total_receivables' => (float) ( $row->total_receivables ?? 0 ),
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
        $where = "status NOT IN ('paid','cancelled') AND balance > 0 AND due_date < CURDATE()";
        if ( $year_id > 0 ) {
            $where .= " AND academic_year_id = %d";
            $params[] = $year_id;
        }

        $query = "SELECT 
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) <= 30 THEN balance ELSE 0 END) AS band_30,
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 31 AND 60 THEN balance ELSE 0 END) AS band_60,
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 61 AND 90 THEN balance ELSE 0 END) AS band_90,
            SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) > 90 THEN balance ELSE 0 END) AS band_90_plus
            FROM " . self::t( 'olama_invoices' ) . "
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
}
