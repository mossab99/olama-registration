<?php
/**
 * Agreements Table Shared View Partial
 *
 * Variables expected:
 * - $agreements: array of agreement objects/rows
 * - $is_hub: boolean flag indicating if this is rendered inside the Customer Hub
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="olama-reg-wrap" style="margin: 0 !important; max-width: none !important; padding: 0 !important; box-shadow: none !important;">
<div class="olama-reg-table-wrap" style="width: 100% !important; overflow-x: auto !important;">
<table class="olama-reg-fin-table">
    <thead>
        <tr>
            <?php if ( ! isset( $is_hub ) || ! $is_hub ) : ?>
                <th style="width: 40px; text-align: center;"><input type="checkbox" id="olama-reg-select-all-agreements" /></th>
            <?php endif; ?>
            <th><?php esc_html_e( 'رقم العقد', 'olama-registration' ); ?></th>
            <?php if ( ! isset( $is_hub ) || ! $is_hub ) : ?>
                <th><?php esc_html_e( 'الجهة الدافعة', 'olama-registration' ); ?></th>
            <?php endif; ?>
            <th><?php esc_html_e( 'الطلاب المشتركين', 'olama-registration' ); ?></th>
            <th><?php esc_html_e( 'طبيعة العقد', 'olama-registration' ); ?></th>
            <th><?php esc_html_e( 'الحالة', 'olama-registration' ); ?></th>
            <th><?php esc_html_e( 'الفواتير المرتبطة', 'olama-registration' ); ?></th>
            <th><?php esc_html_e( 'اجمالي العقد', 'olama-registration' ); ?></th>
            <th><?php esc_html_e( 'المبلغ المحصل', 'olama-registration' ); ?></th>
            <th><?php esc_html_e( 'المبلغ المتبقي', 'olama-registration' ); ?></th>
            <?php if ( ! isset( $is_hub ) || ! $is_hub ) : ?>
                <th><?php esc_html_e( 'التاريخ', 'olama-registration' ); ?></th>
            <?php endif; ?>
            <th style="text-align: center;"><?php esc_html_e( 'الإجراءات', 'olama-registration' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ( empty( $agreements ) ): ?>
            <tr>
                <td colspan="<?php echo ( isset( $is_hub ) && $is_hub ) ? 9 : 12; ?>" class="olama-reg-empty-state">
                    <span class="dashicons dashicons-info"></span><br>
                    <?php esc_html_e( 'لم يتم العثور على أي عقود مطابقة.', 'olama-registration' ); ?>
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ( $agreements as $agr ):
                $badge_class = 'olama-reg-badge--inactive';
                $status_lbl  = esc_html( $agr->status );
                switch ( $agr->status ) {
                    case 'active':
                        $badge_class = 'olama-reg-badge--active';
                        $status_lbl  = __( 'نشط', 'olama-registration' );
                        break;
                    case 'completed':
                        $badge_class = 'olama-reg-badge--info';
                        $status_lbl  = __( 'مكتمل', 'olama-registration' );
                        break;
                    case 'cancelled':
                        $badge_class = 'olama-reg-badge--blacklist';
                        $status_lbl  = __( 'ملغي', 'olama-registration' );
                        break;
                    case 'draft':
                        $badge_class = 'olama-reg-badge--inactive';
                        $status_lbl  = __( 'مسودة', 'olama-registration' );
                        break;
                }
            ?>
                <tr>
                    <?php if ( ! isset( $is_hub ) || ! $is_hub ) : ?>
                        <td style="text-align: center;">
                            <input type="checkbox" name="agreement_id[]" value="<?php echo esc_attr( $agr->id ); ?>" />
                        </td>
                    <?php endif; ?>
                    <td><strong style="letter-spacing:0.3px;"><?php echo esc_html( $agr->agreement_number ); ?></strong></td>
                    <?php if ( ! isset( $is_hub ) || ! $is_hub ) : ?>
                        <td>
                            <?php echo esc_html( $agr->payer_name ); ?>
                            <div style="margin-top:4px; color:var(--reg-text-muted); font-size:12px;">
                                <?php echo $agr->payer_type === 'family' ? esc_html__( 'عائلة', 'olama-registration' ) : esc_html__( 'عميل', 'olama-registration' ); ?>
                            </div>
                        </td>
                    <?php endif; ?>
                    <td>
                        <strong><?php echo esc_html( $agr->participant_name ); ?></strong>
                        <div style="margin-top:4px; color:var(--reg-text-muted); font-size:12px;">
                            <?php echo $agr->participant_type === 'student' ? esc_html__( 'طالب', 'olama-registration' ) : esc_html__( 'طفل', 'olama-registration' ); ?>
                        </div>
                    </td>
                    <td><?php echo esc_html( $agr->activity_type ); ?></td>
                    <td>
                        <span class="olama-reg-badge <?php echo esc_attr( $badge_class ); ?>">
                            <?php echo esc_html( $status_lbl ); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ( empty( $agr->invoices ) ) : ?>
                            <span style="color:var(--reg-text-muted);">-</span>
                        <?php else : ?>
                            <?php
                            $links = [];
                            foreach ( $agr->invoices as $inv ) {
                                $url = admin_url( 'admin.php?page=olama-registration-invoices&action=view&id=' . (int) $inv->id );
                                $links[] = '<a href="' . esc_url( $url ) . '" class="olama-reg-uid-badge" style="display:inline-block; margin-bottom:2px;">' . esc_html( $inv->invoice_number ) . '</a>';
                            }
                            echo implode( '<br>', $links );
                            ?>
                        <?php endif; ?>
                    </td>
                    <td class="olama-reg-text--bold" dir="ltr"><?php echo esc_html( number_format( $agr->total_amount, 3 ) ); ?> <small>JD</small></td>
                    <td style="color:var(--reg-success); font-weight:700;" dir="ltr"><?php echo esc_html( number_format( $agr->collected_amount, 3 ) ); ?> <small>JD</small></td>
                    <?php
                    $remaining_color = $agr->remaining_amount > 0 ? '#e8920a' : 'var(--reg-success)';
                    ?>
                    <td style="color:<?php echo $remaining_color; ?>; font-weight:800;" dir="ltr"><?php echo esc_html( number_format( $agr->remaining_amount, 3 ) ); ?> <small>JD</small></td>
                    <?php if ( ! isset( $is_hub ) || ! $is_hub ) : ?>
                        <td style="color:var(--reg-text-muted);"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $agr->created_at ) ); ?></td>
                    <?php endif; ?>
                    <td style="text-align: center; white-space: nowrap;">
                        <?php if ( isset( $is_hub ) && $is_hub ) : ?>
                            <a href="#" class="button button-small os-hub-edit-agreement" data-id="<?php echo esc_attr( $agr->id ); ?>" style="margin-left: 4px;">
                                <?php esc_html_e( 'تعديل', 'olama-registration' ); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&action=edit&id=' . $agr->id ) ); ?>" class="button button-small" style="margin-left: 4px;">
                                <?php esc_html_e( 'تعديل', 'olama-registration' ); ?>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&action=print&id=' . $agr->id ) ); ?>" target="_blank" class="button button-small" style="margin-left: 4px;">
                            <?php esc_html_e( 'طباعة', 'olama-registration' ); ?>
                        </a>

                        <?php if ( isset( $is_hub ) && $is_hub && ! empty( $agr->invoices ) ) : ?>
                            <div style="margin-top: 6px; padding-top: 6px; border-top: 1px dashed #cbd5e1; display: flex; flex-direction: column; gap: 4px;">
                                <?php foreach ( $agr->invoices as $inv ) : ?>
                                    <button type="button" class="button button-small olama-reg-view-invoice-btn" data-id="<?php echo esc_attr( $inv->id ); ?>" title="<?php esc_attr_e( 'عرض الفاتورة', 'olama-registration' ); ?>" style="background:#0284c7; border-color:#0284c7; color:#fff; display: block; width: 100%; text-align: center; justify-content: center; font-size: 11px; margin-bottom: 2px;">
                                        <?php echo sprintf( esc_html__( 'عرض %s', 'olama-registration' ), esc_html( $inv->invoice_number ) ); ?>
                                    </button>
                                    
                                    <?php if ( (float)$inv->balance > 0 && $inv->status !== 'cancelled' && $inv->status !== 'draft' ) : ?>
                                        <button type="button" class="button button-small os-hub-pay-invoice-btn" data-id="<?php echo esc_attr($inv->id); ?>" title="<?php esc_attr_e( 'دفع الفاتورة', 'olama-registration' ); ?>" style="background:#16a34a; border-color:#16a34a; color:#fff; display: block; width: 100%; text-align: center; justify-content: center; font-size: 11px; margin-bottom: 4px;">
                                            <?php echo sprintf( esc_html__( 'دفع %s', 'olama-registration' ), esc_html( $inv->invoice_number ) ); ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $agr->status !== 'cancelled' && empty( $agr->invoices ) ) : ?>
                            <?php
                            $cancel_url = admin_url( 'admin.php?page=olama-registration-agreements&action=cancel&id=' . $agr->id );
                            if ( isset( $is_hub ) && $is_hub ) {
                                $cancel_url = add_query_arg( 'redirect_to', 'hub', $cancel_url );
                            }
                            ?>
                            <a href="<?php echo esc_url( $cancel_url ); ?>" class="button button-small" style="color:#d63638; text-decoration:none;" onclick="return confirm('<?php echo esc_js( __( 'هل أنت متأكد من إلغاء وحذف هذا العقد؟', 'olama-registration' ) ); ?>');">
                                <?php esc_html_e( 'إلغاء', 'olama-registration' ); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
</div>
</div>
