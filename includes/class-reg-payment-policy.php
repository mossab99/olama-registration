<?php
/**
 * Central payment policy checks.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Payment_Policy {

    public static function can_create_payment( object $invoice, string $method = 'cash', ?int $account_id = null ): true|\WP_Error {
        $invoice_policy = Olama_Reg_Billing_Invoice::can_record_payment( $invoice );
        if ( is_wp_error( $invoice_policy ) ) {
            return $invoice_policy;
        }

        $valid_methods = [ 'cash', 'bank_transfer', 'cheque', 'online' ];
        if ( ! in_array( $method, $valid_methods, true ) ) {
            return new \WP_Error( 'invalid_payment_method', __( 'Invalid payment method.', 'olama-registration' ) );
        }

        return true;
    }

    public static function can_view_payment( object $payment ): true|\WP_Error {
        return true;
    }

    public static function can_print_payment( object $payment ): true|\WP_Error {
        return true;
    }

    public static function can_update_payment_admin_fields( object $payment ): true|\WP_Error {
        if ( self::is_reversal_row( $payment ) ) {
            return new \WP_Error( 'payment_locked', __( 'Reversal receipts are locked.', 'olama-registration' ) );
        }

        return true;
    }

    public static function can_update_payment_financial_fields( object $payment ): true|\WP_Error {
        return new \WP_Error(
            'payment_financial_locked',
            __( 'Posted receipt vouchers cannot be financially edited. Please reverse the receipt and issue a new one.', 'olama-registration' )
        );
    }

    public static function can_reverse_payment( object $payment ): true|\WP_Error {
        if ( self::is_reversal_row( $payment ) || (float) ( $payment->amount ?? 0 ) <= 0 ) {
            return new \WP_Error( 'already_reversed', __( 'This receipt cannot be reversed.', 'olama-registration' ) );
        }

        $status = (string) ( $payment->status ?? 'posted' );
        if ( $status === 'reversed' ) {
            return new \WP_Error( 'already_reversed', __( 'This receipt has already been reversed.', 'olama-registration' ) );
        }

        if ( ! in_array( $status, [ '', 'posted' ], true ) ) {
            return new \WP_Error( 'payment_not_posted', __( 'Only posted receipts can be reversed.', 'olama-registration' ) );
        }

        return true;
    }

    public static function can_confirm_payment( object $payment ): true|\WP_Error {
        $status = (string) ( $payment->status ?? '' );
        if ( ! in_array( $status, [ 'draft', 'pending_review' ], true ) ) {
            return new \WP_Error( 'payment_not_pending', __( 'Only pending receipts can be confirmed.', 'olama-registration' ) );
        }

        return true;
    }

    public static function can_reject_payment( object $payment ): true|\WP_Error {
        return self::can_confirm_payment( $payment );
    }

    public static function can_open_cash_session( ?int $account_id, ?int $cashier_id ): true|\WP_Error {
        return true;
    }

    public static function can_close_cash_session( object $session ): true|\WP_Error {
        return true;
    }

    public static function can_review_cash_session( object $session ): true|\WP_Error {
        return true;
    }

    public static function can_transfer_between_accounts( ?int $from_account_id, ?int $to_account_id ): true|\WP_Error {
        return true;
    }

    private static function is_reversal_row( object $payment ): bool {
        return (string) ( $payment->method ?? '' ) === 'reversal'
            || (float) ( $payment->amount ?? 0 ) < 0
            || ! empty( $payment->reversed_payment_id );
    }
}
