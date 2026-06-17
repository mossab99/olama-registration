<?php
/**
 * Olama Registration Status Labels Helper.
 *
 * @package Olama_Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Status_Labels {

    /**
     * Get Arabic label for any status value.
     *
     * @param string $status   The raw status key (e.g., 'draft', 'posted', 'unpaid')
     * @param string $context  Optional context for disambiguation
     * @return string Arabic label, or the raw status if no mapping found
     */
    public static function label( string $status, string $context = 'general' ): string {
        $status = strtolower( trim( $status ) );
        $labels = self::get_labels( $context );

        if ( isset( $labels[ $status ] ) ) {
            return $labels[ $status ];
        }

        // Fallback to general lookup
        if ( $context !== 'general' ) {
            $general = self::get_labels( 'general' );
            if ( isset( $general[ $status ] ) ) {
                return $general[ $status ];
            }
        }

        return $status;
    }

    /**
     * Get all labels for a given context.
     *
     * @param string $context
     * @return array<string, string>
     */
    public static function all( string $context = 'general' ): array {
        return self::get_labels( $context );
    }

    /**
     * Get CSS class suffix for status badge styling.
     */
    public static function badge_class( string $status, string $context = 'general' ): string {
        $status = strtolower( trim( $status ) );

        switch ( $status ) {
            case 'completed':
            case 'paid':
            case 'posted':
            case 'approved':
            case 'settled':
            case 'cleared':
            case 'confirmed':
            case 'deposited':
            case 'open':
                return 'active';

            case 'draft':
            case 'pending':
            case 'pending_review':
            case 'pending_approval':
            case 'pending_settlement':
            case 'received':
                return 'warning';

            case 'cancelled':
            case 'reversed':
            case 'failed':
            case 'rejected':
            case 'bounced':
            case 'overdue':
                return 'blacklist';

            case 'partially_paid':
            case 'partial':
            case 'invoiced':
                return 'info';

            case 'unpaid':
            default:
                return 'inactive';
        }
    }

    /**
     * Master internal map of Arabic status labels.
     */
    private static function get_labels( string $context ): array {
        $map = [
            'agreement' => [
                'draft'              => 'مسودة',
                'completed'          => 'مكتمل',
                'cancelled'          => 'ملغي',
                'financially_locked' => 'مقفل مالياً',
                'open'               => 'مفتوح',
            ],
            'invoice' => [
                'draft'          => 'مسودة',
                'issued'         => 'صادرة',
                'partially_paid' => 'مدفوعة جزئياً',
                'partial'        => 'مدفوعة جزئياً',
                'paid'           => 'مدفوعة بالكامل',
                'overdue'        => 'متأخرة',
                'cancelled'      => 'ملغاة',
            ],
            'payment' => [
                'draft'          => 'مسودة',
                'posted'         => 'مرحل',
                'reversed'       => 'معكوس',
                'cancelled'      => 'ملغي',
                'pending_review' => 'قيد المراجعة',
                'failed'         => 'فشل',
            ],
            'installment' => [
                'unpaid'   => 'غير مدفوع',
                'paid'     => 'مدفوع',
                'partial'  => 'مدفوع جزئياً',
                'overdue'  => 'متأخرة',
                'pending'  => 'معلق',
            ],
            'amendment' => [
                'draft'            => 'مسودة',
                'posted'           => 'مرحل',
                'approved'         => 'معتمد',
                'pending_approval' => 'بانتظار الاعتماد',
                'rejected'         => 'مرفوض',
                'cancelled'        => 'ملغي',
            ],
            'fee' => [
                'unpaid'                  => 'غير مدفوع',
                'paid'                    => 'مدفوع',
                'invoiced'                => 'مفوتر',
                'active'                  => 'نشط',
                'cancel_pending'          => 'قيد الإلغاء',
                'cancelled_by_adjustment' => 'ملغي بتعديل مالي',
            ],
            'cheque' => [
                'received'  => 'مستلم',
                'cleared'   => 'محصل',
                'bounced'   => 'مرتجع',
                'deposited' => 'مودع',
            ],
            'settlement' => [
                'pending_settlement' => 'بانتظار التسوية',
                'pending'            => 'معلق',
                'settled'            => 'تمت التسوية',
                'cancelled'          => 'ملغي',
            ],
            'payment_method' => [
                'cash'          => 'نقدي',
                'cheque'        => 'شيك',
                'bank_transfer' => 'تحويل بنكي',
                'online'        => 'دفع إلكتروني',
            ],
            'amendment_type' => [
                'correction_error' => 'تصحيح خطأ',
                'discount_change'  => 'تعديل خصم',
                'increase_amount'  => 'زيادة مبلغ',
                'decrease_amount'  => 'خفض مبلغ',
                'add_fee'          => 'إضافة رسم',
            ],
            'cash_session' => [
                'open'           => 'مفتوحة',
                'pending_review' => 'قيد المراجعة',
                'closed'         => 'مغلقة',
                'rejected'       => 'مرفوضة',
            ],
            'general' => [
                'draft'              => 'مسودة',
                'completed'          => 'مكتمل',
                'cancelled'          => 'ملغي',
                'issued'             => 'صادرة',
                'partially_paid'     => 'مدفوعة جزئياً',
                'partial'            => 'مدفوعة جزئياً',
                'paid'               => 'مدفوعة بالكامل',
                'overdue'            => 'متأخرة',
                'unpaid'             => 'غير مدفوع',
                'invoiced'           => 'مفوتر',
                'pending'            => 'معلق',
                'posted'             => 'مرحل',
                'reversed'           => 'معكوس',
                'pending_review'     => 'قيد المراجعة',
                'failed'             => 'فشل',
                'approved'           => 'معتمد',
                'pending_approval'   => 'بانتظار الاعتماد',
                'rejected'           => 'مرفوض',
                'pending_settlement' => 'بانتظار التسوية',
                'settled'            => 'تمت التسوية',
                'received'           => 'مستلم',
                'cleared'            => 'محصل',
                'bounced'            => 'مرتجع',
                'deposited'          => 'مودع',
                'confirmed'          => 'مؤكد',
                'cash'               => 'نقدي',
                'cheque'             => 'شيك',
                'bank_transfer'      => 'تحويل بنكي',
                'online'             => 'دفع إلكتروني',
                'correction_error'        => 'تصحيح خطأ',
                'discount_change'         => 'تعديل خصم',
                'increase_amount'         => 'زيادة مبلغ',
                'decrease_amount'         => 'خفض مبلغ',
                'add_fee'                 => 'إضافة رسم',
                'financially_locked'      => 'مقفل مالياً',
                'open'                    => 'مفتوح',
                'closed'                  => 'مغلق',
                'active'                  => 'نشط',
                'cancel_pending'          => 'قيد الإلغاء',
                'cancelled_by_adjustment' => 'ملغي بتعديل مالي',
            ],
        ];

        return isset( $map[ $context ] ) ? $map[ $context ] : $map['general'];
    }
}
