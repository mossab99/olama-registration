<?php
/**
 * Pending bank/electronic payment review.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$notice = '';
if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    check_admin_referer( 'olama_payment_review_settings', 'olama_payment_review_nonce' );
    update_option( 'olama_bank_transfer_immediate_posting', ! empty( $_POST['bank_transfer_immediate'] ) ? '1' : '0' );
    update_option( 'olama_epayment_immediate_posting', ! empty( $_POST['epayment_immediate'] ) ? '1' : '0' );
    update_option( 'olama_cheque_financial_effect', sanitize_key( $_POST['cheque_financial_effect'] ?? 'on_receive' ) === 'on_clear' ? 'on_clear' : 'on_receive' );
    $notice = __( 'تم حفظ إعدادات مراجعة الدفعات.', 'olama-registration' );
}

$pending = Olama_Reg_Payment_Method_Details::get_pending_payments();
$bank_immediate = get_option( 'olama_bank_transfer_immediate_posting', '1' ) === '1';
$epayment_immediate = get_option( 'olama_epayment_immediate_posting', '1' ) === '1';
$cheque_effect = get_option( 'olama_cheque_financial_effect', 'on_receive' );
$money = static function ( $amount ): string {
    return number_format( (float) $amount, 2 );
};
?>
<div class="wrap olama-reg-wrap" dir="rtl">
    <div class="olama-reg-page-header">
        <h1>
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'مراجعة التحويلات والمدفوعات الإلكترونية', 'olama-registration' ); ?>
        </h1>
    </div>

    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>
    <?php if ( $notice ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <div class="olama-reg-section">
        <h3 class="olama-reg-section-title">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e( 'إعدادات المراجعة', 'olama-registration' ); ?>
        </h3>
        <form method="post" class="olama-reg-grid olama-reg-grid--compact">
            <?php wp_nonce_field( 'olama_payment_review_settings', 'olama_payment_review_nonce' ); ?>
            <label style="display:flex; gap:8px; align-items:center; font-weight:800;">
                <input type="checkbox" name="bank_transfer_immediate" value="1" <?php checked( $bank_immediate ); ?>>
                <?php esc_html_e( 'اعتماد التحويلات البنكية فوراً عند التسجيل', 'olama-registration' ); ?>
            </label>
            <label style="display:flex; gap:8px; align-items:center; font-weight:800;">
                <input type="checkbox" name="epayment_immediate" value="1" <?php checked( $epayment_immediate ); ?>>
                <?php esc_html_e( 'اعتماد الدفعات الإلكترونية فوراً عند التسجيل', 'olama-registration' ); ?>
            </label>
            <div class="olama-reg-field">
                <label><?php esc_html_e( 'الأثر المالي للشيكات', 'olama-registration' ); ?></label>
                <select name="cheque_financial_effect">
                    <option value="on_receive" <?php selected( $cheque_effect, 'on_receive' ); ?>><?php esc_html_e( 'عند الاستلام', 'olama-registration' ); ?></option>
                    <option value="on_clear" <?php selected( $cheque_effect, 'on_clear' ); ?>><?php esc_html_e( 'عند التحصيل', 'olama-registration' ); ?></option>
                </select>
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button type="submit" class="olama-reg-btn olama-reg-btn--secondary">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'حفظ الإعدادات', 'olama-registration' ); ?>
                </button>
            </div>
        </form>
    </div>

    <div class="olama-reg-section">
        <h3 class="olama-reg-section-title">
            <span class="dashicons dashicons-clock"></span>
            <?php esc_html_e( 'دفعات قيد المراجعة', 'olama-registration' ); ?>
        </h3>
        <div class="olama-reg-table-wrap">
            <table class="olama-reg-fin-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'رقم السند', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'التاريخ', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الفاتورة', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الطريقة', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'الحساب', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المرجع', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المبلغ', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'المستلم', 'olama-registration' ); ?></th>
                        <th><?php esc_html_e( 'إجراء', 'olama-registration' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $pending ) ) : ?>
                        <tr><td colspan="9" class="olama-reg-empty-state"><?php esc_html_e( 'لا توجد دفعات قيد المراجعة حالياً.', 'olama-registration' ); ?></td></tr>
                    <?php else : foreach ( $pending as $payment ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( Olama_Reg_Billing_Payment::get_receipt_number( $payment ) ); ?></strong></td>
                            <td><?php echo esc_html( $payment->payment_date ); ?></td>
                            <td><?php echo esc_html( $payment->invoice_number ?: '—' ); ?></td>
                            <td><?php echo esc_html( $payment->method ); ?></td>
                            <td><?php echo esc_html( $payment->account_name ?: '—' ); ?></td>
                            <td><?php echo esc_html( $payment->reference ?: '—' ); ?></td>
                            <td class="olama-reg-text--success"><?php echo esc_html( $money( $payment->amount ) ); ?></td>
                            <td><?php echo esc_html( $payment->received_by_name ?: '—' ); ?></td>
                            <td style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="button button-small button-primary olama-reg-confirm-payment-btn" data-id="<?php echo esc_attr( $payment->id ); ?>">
                                    <?php esc_html_e( 'اعتماد', 'olama-registration' ); ?>
                                </button>
                                <button type="button" class="button button-small olama-reg-reject-payment-btn" data-id="<?php echo esc_attr( $payment->id ); ?>">
                                    <?php esc_html_e( 'رفض', 'olama-registration' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
