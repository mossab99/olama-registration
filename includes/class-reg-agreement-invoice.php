<?php
/**
 * Agreement to Invoice Bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement_Invoice {

    /**
     * Generate an invoice from selected fee IDs of an agreement
     */
    public static function generate_invoice( int $agreement_id, array $fee_ids ): int|WP_Error {
        $agreement = Olama_Reg_Agreement::get( $agreement_id );
        if ( ! $agreement ) {
            return new WP_Error( 'not_found', 'العقد غير موجود.' );
        }

        // Only active agreements can generate invoices
        if ( $agreement->status !== 'active' ) {
            return new WP_Error( 'not_active', 'يجب أن يكون العقد نشطاً لإصدار فاتورة.' );
        }

        $fees = [];
        foreach ( $fee_ids as $fee_id ) {
            $fee = Olama_Reg_Agreement_Fees::get( (int) $fee_id );
            if ( $fee && $fee->agreement_id == $agreement_id && $fee->paid_status === 'unpaid' ) {
                $fees[] = $fee;
            }
        }

        if ( empty( $fees ) ) {
            return new WP_Error( 'no_fees', 'لم يتم تحديد رسوم صالحة للفوترة.' );
        }

        // Build Invoice Items
        $items = [];
        $total_discount = 0;

        foreach ( $fees as $fee ) {
            $items[] = [
                'description'  => $fee->label ?: $fee->fee_category,
                'quantity'     => 1,
                'unit_price'   => $fee->amount,
            ];
            $total_discount += (float) $fee->discount;
        }

        $invoice_data = [
            'academic_year_id' => $agreement->academic_year_id,
            'issue_date'       => current_time( 'Y-m-d' ),
            'status'           => 'issued',
            'notes'            => 'فاتورة من العقد رقم: ' . $agreement->agreement_number,
            'items'            => $items,
            'discount'         => $total_discount,
            'agreement_id'     => $agreement->id,
        ];

        if ( $agreement->payer_type === 'customer' ) {
            $invoice_data['ext_customer_id'] = $agreement->payer_id;
            
            // Get uid for family_uid field (which is required by the billing system)
            $customer = Olama_Reg_Customer::get( $agreement->payer_id );
            $invoice_data['family_uid'] = $customer ? $customer->customer_uid : 'CUST-' . str_pad( $agreement->payer_id, 4, '0', STR_PAD_LEFT );
            
            if ( $agreement->participant_type === 'child' ) {
                $invoice_data['ext_child_id'] = $agreement->participant_id;
            }
        } else {
            // family
            $invoice_data['family_uid'] = $agreement->payer_id; // For family, payer_id is family_uid (e.g. F001)
            if ( $agreement->participant_type === 'student' ) {
                $invoice_data['student_uid'] = $agreement->participant_id; // participant_id is student_uid
            }
        }

        $invoice_id = Olama_Reg_Billing_Invoice::create( $invoice_data );

        if ( ! is_wp_error( $invoice_id ) && $invoice_id > 0 ) {
            // Mark fees as invoiced
            foreach ( $fees as $fee ) {
                Olama_Reg_Agreement_Fees::mark_invoiced( $fee->id, $invoice_id );
            }
        }

        return $invoice_id;
    }
}
