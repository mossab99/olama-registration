<?php
/**
 * Agreement Fees CRUD
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Olama_Reg_Agreement_Fees {

    /**
     * Add a fee row
     */
    public static function add( int $agreement_id, array $data, bool $skip_lock_check = false ): int|false {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';

        if ( ! $skip_lock_check && class_exists( 'Olama_Reg_Agreement_Policy' ) && is_wp_error( Olama_Reg_Agreement_Policy::can_edit_financial_fields( $agreement_id ) ) ) {
            return false;
        }

        $defaults = [
            'agreement_id' => $agreement_id,
            'child_id'     => null,
            'fee_category' => 'general',
            'label'        => '',
            'amount'       => 0,
            'discount'     => 0,
            'net_amount'   => 0,
            'due_date'     => null,
            'invoice_id'   => null,
            'paid_status'  => 'unpaid',
            'sort_order'   => 0,
        ];

        $insert_data = wp_parse_args( $data, $defaults );
        
        // Auto-calculate net if not explicitly provided
        if ( ! isset( $data['net_amount'] ) ) {
            $insert_data['net_amount'] = max( 0, (float) $insert_data['amount'] - (float) $insert_data['discount'] );
        }

        $inserted = $wpdb->insert( $table, $insert_data );

        if ( $inserted ) {
            $fee_id = (int) $wpdb->insert_id;
            Olama_Reg_Agreement::recalculate_total( $agreement_id );
            if ( class_exists( 'Olama_Reg_Agreement_Participants' ) ) {
                Olama_Reg_Agreement_Participants::sync_from_fees( $agreement_id );
            }
            if ( class_exists( 'Olama_Reg_Agreement' ) ) {
                $inserted_fee = self::get( $fee_id );
                Olama_Reg_Agreement::log_audit( $agreement_id, 'fee_added', null, $inserted_fee );
            }
            return $fee_id;
        }

        return false;
    }

    /**
     * Update a fee row
     */
    public static function update( int $fee_id, array $data ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';

        // Check if locked
        $existing = self::get( $fee_id );
        if ( ! $existing || in_array( $existing->paid_status, [ 'invoiced', 'paid' ], true ) ) {
            return false; // Cannot update invoiced/paid rows
        }
        if ( class_exists( 'Olama_Reg_Agreement_Policy' ) && is_wp_error( Olama_Reg_Agreement_Policy::can_edit_financial_fields( (int) $existing->agreement_id ) ) ) {
            return false;
        }

        // Remove un-updatable fields
        unset( $data['id'], $data['agreement_id'], $data['invoice_id'], $data['paid_status'] );

        // Recalculate net_amount if amount or discount changed
        if ( isset( $data['amount'] ) || isset( $data['discount'] ) ) {
            $amt = isset( $data['amount'] ) ? (float) $data['amount'] : (float) $existing->amount;
            $dsc = isset( $data['discount'] ) ? (float) $data['discount'] : (float) $existing->discount;
            $data['net_amount'] = max( 0, $amt - $dsc );
        }

        $updated = $wpdb->update( $table, $data, [ 'id' => $fee_id ] );
        
        if ( $updated !== false ) {
            Olama_Reg_Agreement::recalculate_total( (int) $existing->agreement_id );
            if ( class_exists( 'Olama_Reg_Agreement_Participants' ) ) {
                Olama_Reg_Agreement_Participants::sync_from_fees( (int) $existing->agreement_id );
            }
            if ( class_exists( 'Olama_Reg_Agreement' ) ) {
                $after = self::get( $fee_id );
                Olama_Reg_Agreement::log_audit( (int) $existing->agreement_id, 'fee_updated', $existing, $after );
            }
            return true;
        }

        return false;
    }

    /**
     * Delete a fee row
     */
    public static function delete( int $fee_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';

        $existing = self::get( $fee_id );
        if ( ! $existing || in_array( $existing->paid_status, [ 'invoiced', 'paid' ], true ) ) {
            return false; // Cannot delete invoiced rows
        }
        if ( class_exists( 'Olama_Reg_Agreement_Policy' ) && is_wp_error( Olama_Reg_Agreement_Policy::can_edit_financial_fields( (int) $existing->agreement_id ) ) ) {
            return false;
        }

        $deleted = $wpdb->delete( $table, [ 'id' => $fee_id ] );
        
        if ( $deleted ) {
            Olama_Reg_Agreement::recalculate_total( (int) $existing->agreement_id );
            if ( class_exists( 'Olama_Reg_Agreement_Participants' ) ) {
                Olama_Reg_Agreement_Participants::sync_from_fees( (int) $existing->agreement_id );
            }
            if ( class_exists( 'Olama_Reg_Agreement' ) ) {
                $wpdb->get_results( "SELECT 1" ); // dummy to avoid empty conditional warning
                Olama_Reg_Agreement::log_audit( (int) $existing->agreement_id, 'fee_deleted', $existing, null );
            }
            return true;
        }

        return false;
    }

    /**
     * Get single fee
     */
    public static function get( int $fee_id ): object|null {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $fee_id ) );
    }

    /**
     * Get all fees for an agreement
     */
    public static function get_by_agreement( int $agreement_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';
        return $wpdb->get_results( $wpdb->prepare( 
            "SELECT * FROM {$table} WHERE agreement_id = %d ORDER BY sort_order ASC, id ASC", 
            $agreement_id 
        ) );
    }

    /**
     * Mark fee as invoiced
     */
    public static function mark_invoiced( int $fee_id, int $invoice_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';
        $wpdb->update( $table, [
            'invoice_id'  => $invoice_id,
            'paid_status' => 'invoiced',
        ], [ 'id' => $fee_id ] );
    }

    /**
     * Mark fee as paid (hooked from payment system eventually)
     */
    public static function mark_paid( int $fee_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';
        $wpdb->update( $table, [ 'paid_status' => 'paid' ], [ 'id' => $fee_id ] );
    }

    /**
     * Apply fees from a billing fee template
     */
    public static function apply_template_fees( int $agreement_id, int $template_id ): bool {
        if ( class_exists( 'Olama_Reg_Agreement_Policy' ) && is_wp_error( Olama_Reg_Agreement_Policy::can_edit_financial_fields( $agreement_id ) ) ) {
            return false;
        }

        if ( ! class_exists( 'Olama_Reg_Billing_Fees' ) ) {
            return false;
        }
        $template = Olama_Reg_Billing_Fees::get_template( $template_id );
        if ( ! $template || empty( $template->items ) ) {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'olama_agreement_fees';
        
        // Remove existing unpaid fees before applying the new template
        $wpdb->delete( $table, [
            'agreement_id' => $agreement_id,
            'paid_status'  => 'unpaid'
        ] );

        $sort_order = 0;
        foreach ( $template->items as $item ) {
            $base_amount = (float) ( $item['amount'] ?? 0 );
            self::add( $agreement_id, [
                'fee_category' => 'general',
                'label'        => $item['description'] ?? '',
                'amount'       => $base_amount,
                'discount'     => 0,
                'due_date'     => null,
                'sort_order'   => $sort_order++,
            ] );
        }

        Olama_Reg_Agreement::recalculate_total( $agreement_id );
        if ( class_exists( 'Olama_Reg_Agreement_Participants' ) ) {
            Olama_Reg_Agreement_Participants::sync_from_fees( $agreement_id );
        }
        if ( class_exists( 'Olama_Reg_Agreement' ) ) {
            Olama_Reg_Agreement::log_audit( $agreement_id, 'template_applied', null, (object)[ 'template_id' => $template_id, 'template_name' => $template->template_name ] );
        }
        return true;
    }

    /**
     * Safe fee item cancellation workflow (accounting-aware)
     */
    public static function cancel_fee_item( int $agreement_id, int $fee_id, array $args ): array|\WP_Error {
        global $wpdb;

        $fee = self::get( $fee_id );
        if ( ! $fee ) {
            return new \WP_Error( 'fee_not_found', __( 'بند الرسوم غير موجود.', 'olama-registration' ) );
        }

        if ( (int) $fee->agreement_id !== $agreement_id ) {
            return new \WP_Error( 'fee_mismatch', __( 'بند الرسوم غير مرتبط بهذا العقد.', 'olama-registration' ) );
        }

        $is_locked = false;
        if ( class_exists( 'Olama_Reg_Agreement_Policy' ) ) {
            $lock_check = Olama_Reg_Agreement_Policy::can_edit_financial_fields( $agreement_id );
            if ( is_wp_error( $lock_check ) ) {
                $is_locked = true;
            }
        }
        if ( in_array( $fee->paid_status, [ 'invoiced', 'paid' ], true ) ) {
            $is_locked = true;
        }

        if ( ! $is_locked ) {
            // Case 1: Unlocked - Direct physical delete
            $deleted = self::delete( $fee_id );
            if ( ! $deleted ) {
                return new \WP_Error( 'delete_failed', __( 'فشل حذف البند.', 'olama-registration' ) );
            }

            $agreement = class_exists( 'Olama_Reg_Agreement' ) ? Olama_Reg_Agreement::get( $agreement_id ) : null;
            $payer_id = $agreement ? $agreement->payer_id : '';
            $academic_year_id = $agreement ? (int) $agreement->academic_year_id : 0;

            if ( class_exists( 'Olama_Reg_Agreement_Participants' ) ) {
                Olama_Reg_Agreement_Participants::sync_from_fees( $agreement_id );
            }
            if ( class_exists( 'Olama_Reg_Family_Financial_Summary' ) && ! empty( $payer_id ) ) {
                Olama_Reg_Family_Financial_Summary::invalidate_snapshot( $payer_id, $academic_year_id );
            }

            return [
                'success'    => true,
                'message'    => __( 'تم حذف البند وتحديث إجمالي العقد.', 'olama-registration' ),
                'mode'       => 'direct_delete',
                'fee_status' => 'deleted',
                'reload'     => true,
            ];
        } else {
            // Case 2: Locked - Financial cancellation via amendment
            if ( ! class_exists( 'Olama_Reg_Agreement_Amendment' ) ) {
                return new \WP_Error( 'amendment_module_missing', __( 'وحدة التعديلات المالية غير متوفرة.', 'olama-registration' ) );
            }

            $reason = sanitize_text_field( $args['reason'] ?? '' );
            if ( empty( $reason ) ) {
                return new \WP_Error( 'reason_required', __( 'سبب الإلغاء مطلوب.', 'olama-registration' ) );
            }

            $effective_date = sanitize_text_field( $args['effective_date'] ?? current_time( 'Y-m-d' ) );
            $notes = sanitize_textarea_field( $args['notes'] ?? '' );

            $agreement = class_exists( 'Olama_Reg_Agreement' ) ? Olama_Reg_Agreement::get( $agreement_id ) : null;
            if ( ! $agreement ) {
                return new \WP_Error( 'agreement_not_found', __( 'العقد غير موجود.', 'olama-registration' ) );
            }

            $fee_net = round( (float) $fee->net_amount, 3 );
            $old_total = round( (float) $agreement->total_amount, 3 );
            $new_total = round( $old_total - $fee_net, 3 );

            // Create draft amendment
            $amendment_payload = [
                'amendment_type' => 'decrease_amount',
                'effective_date' => $effective_date,
                'reason'         => sprintf( __( 'إلغاء بند: %s - %s', 'olama-registration' ), $fee->label, $reason ),
                'admin_notes'    => $notes,
                'old_total'      => $old_total,
                'new_total'      => $new_total,
                'lines'          => [
                    [
                        'line_type'         => 'fee_line_change',
                        'related_fee_id'    => $fee_id,
                        'student_id'        => $fee->child_id,
                        'description'       => sprintf( __( 'إلغاء بند: %s', 'olama-registration' ), $fee->label ),
                        'old_amount'        => $fee_net,
                        'new_amount'        => 0.0,
                        'difference_amount' => -$fee_net,
                        'before_state'      => [
                            'fee_id'       => $fee_id,
                            'fee_category' => $fee->fee_category,
                            'label'        => $fee->label,
                            'amount'       => (float) $fee->amount,
                            'discount'     => (float) $fee->discount,
                            'net_amount'   => $fee_net,
                        ],
                        'after_state'       => [
                            'fee_id'       => $fee_id,
                            'fee_category' => $fee->fee_category,
                            'label'        => $fee->label,
                            'amount'       => 0.0,
                            'discount'     => 0.0,
                            'net_amount'   => 0.0,
                        ],
                    ]
                ],
            ];

            $amendment_id = Olama_Reg_Agreement_Amendment::create( $agreement_id, $amendment_payload );
            if ( is_wp_error( $amendment_id ) ) {
                return $amendment_id;
            }

            // Auto-approve the amendment
            $approved = Olama_Reg_Agreement_Amendment::approve( $amendment_id );
            if ( is_wp_error( $approved ) ) {
                return $approved;
            }

            // Auto-post the amendment to trigger credit note creation and recalculate totals
            $posted = Olama_Reg_Agreement_Amendment::post( $amendment_id );
            if ( is_wp_error( $posted ) ) {
                return $posted;
            }

            // Update original fee record cancellation metadata
            $wpdb->update(
                $wpdb->prefix . 'olama_agreement_fees',
                [
                    'status'                    => 'cancelled_by_adjustment',
                    'cancelled_at'              => current_time( 'mysql' ),
                    'cancelled_by'              => get_current_user_id(),
                    'cancellation_reason'       => $reason,
                    'cancellation_amendment_id' => $amendment_id,
                ],
                [ 'id' => $fee_id ]
            );

            $payer_id = $agreement->payer_id;
            $academic_year_id = (int) $agreement->academic_year_id;

            if ( class_exists( 'Olama_Reg_Agreement_Participants' ) ) {
                Olama_Reg_Agreement_Participants::sync_from_fees( $agreement_id );
            }
            if ( class_exists( 'Olama_Reg_Family_Financial_Summary' ) && ! empty( $payer_id ) ) {
                Olama_Reg_Family_Financial_Summary::invalidate_snapshot( $payer_id, $academic_year_id );
            }

            return [
                'success'    => true,
                'message'    => __( 'تم إلغاء البند من خلال تعديل مالي.', 'olama-registration' ),
                'mode'       => 'financial_cancellation',
                'fee_status' => 'cancelled_by_adjustment',
                'reload'     => true,
            ];
        }
    }
}
