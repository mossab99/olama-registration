<?php
/**
 * Cashier cash session workflow.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Cash_Session {

    private static function t( string $name ): string {
        global $wpdb;
        return $wpdb->prefix . $name;
    }

    public static function open( array $data ): int|\WP_Error {
        global $wpdb;

        $account_id = absint( $data['account_id'] ?? 0 );
        $cashier_id = absint( $data['cashier_id'] ?? get_current_user_id() );
        $date = self::sanitize_date( $data['session_date'] ?? current_time( 'Y-m-d' ) );
        $opening_raw = isset( $data['opening_balance'] ) ? trim( (string) $data['opening_balance'] ) : '';
        $opening = $opening_raw === ''
            ? self::get_default_opening_balance( $account_id, $date )
            : round( (float) $opening_raw, 2 );
        $notes = sanitize_textarea_field( $data['notes'] ?? '' );

        if ( ! $account_id || ! $cashier_id ) {
            return new \WP_Error( 'missing_session_data', __( 'Cash account and cashier are required.', 'olama-registration' ) );
        }
        if ( $opening < 0 ) {
            return new \WP_Error( 'invalid_opening_balance', __( 'Opening balance cannot be negative.', 'olama-registration' ) );
        }

        $account = self::get_account( $account_id );
        if ( ! $account || (string) $account->type !== 'cash' ) {
            return new \WP_Error( 'invalid_cash_account', __( 'Please select an active cash account.', 'olama-registration' ) );
        }

        $policy = Olama_Reg_Payment_Policy::can_open_cash_session( $account_id, $cashier_id );
        if ( is_wp_error( $policy ) ) {
            return $policy;
        }

        $existing = self::get_open_session( $account_id, $cashier_id );
        if ( $existing ) {
            return new \WP_Error( 'session_already_open', __( 'There is already an open cash session for this cashier and cash account.', 'olama-registration' ) );
        }

        if ( ! self::acquire_number_lock() ) {
            return new \WP_Error( 'number_lock_failed', __( 'Could not reserve a cash session number. Please try again.', 'olama-registration' ) );
        }

        $payload = [
            'session_no'               => self::generate_session_no(),
            'account_id'               => $account_id,
            'cashier_id'               => $cashier_id,
            'session_date'             => $date,
            'opened_at'                => current_time( 'mysql' ),
            'opening_balance'          => $opening,
            'expected_closing_balance' => $opening,
            'status'                   => 'open',
            'notes'                    => $notes ?: null,
        ];

        $result = $wpdb->insert( self::t( 'olama_cash_sessions' ), $payload );
        self::release_number_lock();

        if ( ! $result ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        $id = (int) $wpdb->insert_id;
        self::log_audit( 'cash_session', $id, 'cash_session_opened', null, self::get( $id ) );

        return $id;
    }

    public static function close( int $session_id, float $actual_closing_balance, string $notes = '' ): true|\WP_Error {
        global $wpdb;

        $session = self::get( $session_id );
        if ( ! $session ) {
            return new \WP_Error( 'session_not_found', __( 'Cash session not found.', 'olama-registration' ) );
        }
        if ( (string) $session->status !== 'open' ) {
            return new \WP_Error( 'session_not_open', __( 'Only open cash sessions can be closed.', 'olama-registration' ) );
        }

        $policy = Olama_Reg_Payment_Policy::can_close_cash_session( $session );
        if ( is_wp_error( $policy ) ) {
            return $policy;
        }

        $totals = self::calculate_totals( $session_id );
        $actual = round( $actual_closing_balance, 2 );
        if ( $actual < 0 ) {
            return new \WP_Error( 'invalid_actual_balance', __( 'Actual closing balance cannot be negative.', 'olama-registration' ) );
        }
        $difference = round( $actual - $totals['expected'], 2 );

        $updated = $wpdb->update(
            self::t( 'olama_cash_sessions' ),
            [
                'cash_in_total'            => $totals['cash_in'],
                'cash_out_total'           => $totals['cash_out'],
                'expected_closing_balance' => $totals['expected'],
                'actual_closing_balance'   => $actual,
                'difference_amount'        => $difference,
                'closed_at'                => current_time( 'mysql' ),
                'status'                   => 'pending_review',
                'notes'                    => sanitize_textarea_field( $notes ) ?: $session->notes,
            ],
            [ 'id' => $session_id ]
        );

        if ( $updated === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        self::log_audit( 'cash_session', $session_id, 'cash_session_closed', $session, self::get( $session_id ) );

        return true;
    }

    public static function review( int $session_id, string $decision, string $notes = '' ): true|\WP_Error {
        global $wpdb;

        $session = self::get( $session_id );
        if ( ! $session ) {
            return new \WP_Error( 'session_not_found', __( 'Cash session not found.', 'olama-registration' ) );
        }
        if ( (string) $session->status !== 'pending_review' ) {
            return new \WP_Error( 'session_not_pending_review', __( 'Only sessions pending review can be approved or rejected.', 'olama-registration' ) );
        }

        $policy = Olama_Reg_Payment_Policy::can_review_cash_session( $session );
        if ( is_wp_error( $policy ) ) {
            return $policy;
        }

        $status = $decision === 'reject' ? 'rejected' : 'closed';
        $updated = $wpdb->update(
            self::t( 'olama_cash_sessions' ),
            [
                'status'      => $status,
                'reviewed_by' => get_current_user_id(),
                'reviewed_at' => current_time( 'mysql' ),
                'notes'       => sanitize_textarea_field( $notes ) ?: $session->notes,
            ],
            [ 'id' => $session_id ]
        );

        if ( $updated === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        self::log_audit( 'cash_session', $session_id, $status === 'closed' ? 'cash_session_reviewed' : 'cash_session_rejected', $session, self::get( $session_id ) );

        return true;
    }

    public static function attach_payment_to_open_session( int $payment_id ): true|\WP_Error {
        global $wpdb;

        $payment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_payments' ) . " WHERE id = %d",
            $payment_id
        ) );
        if ( ! $payment || (string) $payment->method !== 'cash' || ! empty( $payment->cash_session_id ) ) {
            return true;
        }

        $account_id = (int) ( $payment->account_id ?: Olama_Reg_Cash_Bank_Movement::get_default_account_id( 'cash' ) );
        $payment_date = self::sanitize_date( (string) ( $payment->payment_date ?? current_time( 'Y-m-d' ) ) );
        $session = self::get_open_session( $account_id, (int) $payment->received_by, $payment_date );

        if ( ! $session ) {
            if ( get_option( 'olama_require_cash_session', '0' ) === '1' ) {
                return new \WP_Error( 'missing_cash_session', __( 'Cash receipts require an open cash session for the receipt date.', 'olama-registration' ) );
            }
            return true;
        }

        $updated = $wpdb->update(
            self::t( 'olama_payments' ),
            [ 'cash_session_id' => (int) $session->id ],
            [ 'id' => $payment_id ]
        );

        if ( $updated === false ) {
            return new \WP_Error( 'db_error', $wpdb->last_error );
        }

        return true;
    }

    public static function refresh_totals( int $session_id ): array {
        global $wpdb;

        $totals = self::calculate_totals( $session_id );
        $wpdb->update(
            self::t( 'olama_cash_sessions' ),
            [
                'cash_in_total'            => $totals['cash_in'],
                'cash_out_total'           => $totals['cash_out'],
                'expected_closing_balance' => $totals['expected'],
                'difference_amount'        => isset( $totals['actual'] ) ? round( $totals['actual'] - $totals['expected'], 2 ) : null,
            ],
            [ 'id' => $session_id ]
        );

        return $totals;
    }

    public static function calculate_totals( int $session_id ): array {
        global $wpdb;

        $session = self::get( $session_id );
        if ( ! $session ) {
            return [ 'cash_in' => 0.0, 'cash_out' => 0.0, 'expected' => 0.0 ];
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN direction = 'out' THEN amount ELSE 0 END), 0) AS cash_out
             FROM " . self::t( 'olama_cash_bank_movements' ) . "
             WHERE cash_session_id = %d AND status = 'posted'",
            $session_id
        ) );

        $cash_in = round( (float) ( $row->cash_in ?? 0 ), 2 );
        $cash_out = round( (float) ( $row->cash_out ?? 0 ), 2 );
        $expected = round( (float) $session->opening_balance + $cash_in - $cash_out, 2 );

        $totals = [
            'cash_in'  => $cash_in,
            'cash_out' => $cash_out,
            'expected' => $expected,
        ];
        if ( $session->actual_closing_balance !== null ) {
            $totals['actual'] = (float) $session->actual_closing_balance;
        }

        return $totals;
    }

    public static function get( int $session_id ): ?object {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT s.*, a.account_name, a.account_code, u.display_name AS cashier_name
             FROM " . self::t( 'olama_cash_sessions' ) . " s
             LEFT JOIN " . self::t( 'olama_financial_accounts' ) . " a ON a.id = s.account_id
             LEFT JOIN {$wpdb->users} u ON u.ID = s.cashier_id
             WHERE s.id = %d",
            $session_id
        ) ) ?: null;
    }

    public static function get_open_session( int $account_id, int $cashier_id, ?string $date = null ): ?object {
        global $wpdb;

        if ( $date === null ) {
            return $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM " . self::t( 'olama_cash_sessions' ) . "
                 WHERE account_id = %d AND cashier_id = %d AND status = 'open'
                 ORDER BY id DESC LIMIT 1",
                $account_id,
                $cashier_id
            ) ) ?: null;
        }

        $date = self::sanitize_date( $date );
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_cash_sessions' ) . "
             WHERE account_id = %d AND cashier_id = %d AND session_date = %s AND status = 'open'
             ORDER BY id DESC LIMIT 1",
            $account_id,
            $cashier_id,
            $date
        ) ) ?: null;
    }

    public static function get_sessions( array $filters = [] ): array {
        global $wpdb;

        $where = [ '1=1' ];
        $params = [];
        if ( ! empty( $filters['status'] ) ) {
            $where[] = 's.status = %s';
            $params[] = sanitize_key( $filters['status'] );
        }
        if ( ! empty( $filters['account_id'] ) ) {
            $where[] = 's.account_id = %d';
            $params[] = absint( $filters['account_id'] );
        }

        $sql = "SELECT s.*, a.account_name, u.display_name AS cashier_name
                FROM " . self::t( 'olama_cash_sessions' ) . " s
                LEFT JOIN " . self::t( 'olama_financial_accounts' ) . " a ON a.id = s.account_id
                LEFT JOIN {$wpdb->users} u ON u.ID = s.cashier_id
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY s.session_date DESC, s.id DESC";

        return $params ? ( $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) ) ?: [] ) : ( $wpdb->get_results( $sql ) ?: [] );
    }

    public static function get_cash_accounts(): array {
        global $wpdb;

        return $wpdb->get_results(
            "SELECT id, account_code, account_name
             FROM " . self::t( 'olama_financial_accounts' ) . "
             WHERE type = 'cash' AND is_active = 1
             ORDER BY is_default DESC, account_name ASC"
        ) ?: [];
    }

    private static function get_account( int $account_id ): ?object {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::t( 'olama_financial_accounts' ) . " WHERE id = %d AND is_active = 1",
            $account_id
        ) ) ?: null;
    }

    private static function get_default_opening_balance( int $account_id, string $session_date ): float {
        if ( ! $account_id ) {
            return 0.0;
        }

        $previous_day = date( 'Y-m-d', strtotime( $session_date . ' -1 day' ) );
        return Olama_Reg_Cash_Bank_Movement::get_account_balance( $account_id, $previous_day );
    }

    private static function sanitize_date( string $date ): string {
        $date = sanitize_text_field( $date );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : current_time( 'Y-m-d' );
    }

    private static function acquire_number_lock(): bool {
        global $wpdb;
        $locked = $wpdb->get_var( "SELECT GET_LOCK('olama_reg_cash_session_number', 5)" );

        return (string) $locked === '1';
    }

    private static function release_number_lock(): void {
        global $wpdb;
        $wpdb->get_var( "SELECT RELEASE_LOCK('olama_reg_cash_session_number')" );
    }

    private static function generate_session_no(): string {
        global $wpdb;

        $year = current_time( 'Y' );
        $base = 'CSH-' . $year . '-';
        $latest = (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT session_no
             FROM " . self::t( 'olama_cash_sessions' ) . "
             WHERE session_no LIKE %s
             ORDER BY session_no DESC
             LIMIT 1",
            $wpdb->esc_like( $base ) . '%'
        ) );

        $next = 1;
        if ( preg_match( '/^' . preg_quote( $base, '/' ) . '(\d+)$/', $latest, $matches ) ) {
            $next = (int) $matches[1] + 1;
        }

        return $base . str_pad( (string) $next, 5, '0', STR_PAD_LEFT );
    }

    private static function log_audit( string $entity_type, int $entity_id, string $action, ?object $before, ?object $after ): void {
        global $wpdb;

        $wpdb->insert( self::t( 'olama_billing_audit' ), [
            'entity_type'  => $entity_type,
            'entity_id'    => $entity_id,
            'action'       => $action,
            'actor_id'     => get_current_user_id(),
            'before_state' => $before ? wp_json_encode( $before ) : null,
            'after_state'  => $after ? wp_json_encode( $after ) : null,
            'ip_address'   => sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' ),
        ] );
    }
}
