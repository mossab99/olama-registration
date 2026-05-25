<?php
/**
 * Agreement Print View
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'Unauthorized', 'olama-registration' ) );
}

$id = (int) ( $_GET['id'] ?? 0 );
$agreement = Olama_Reg_Agreement::get( $id );

if ( ! $agreement ) {
    echo '<p>' . esc_html__( 'العقد غير موجود.', 'olama-registration' ) . '</p>';
    return;
}

$fees    = Olama_Reg_Agreement_Fees::get_by_agreement( $id );
$clauses = Olama_Reg_Agreement_Clauses::get_by_agreement( $id );

// Get Payer Info
$payer_name = $agreement->payer_name;
$payer_phone = '';
if ( $agreement->payer_type === 'customer' ) {
    $cust = Olama_Reg_Customer::get( $agreement->payer_id );
    if ( $cust ) $payer_phone = $cust->phone;
} else {
    global $wpdb;
    $family = $wpdb->get_row( $wpdb->prepare( "SELECT father_phone, mother_phone FROM {$wpdb->prefix}olama_families WHERE family_uid = %s", $agreement->payer_id ) );
    if ( $family ) $payer_phone = $family->father_phone ?: $family->mother_phone;
}

// Ensure JS/CSS only target this page for printing
?>
<style>
    @media print {
        #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .update-nag, .notice { display: none !important; }
        #wpcontent { margin-left: 0 !important; margin-right: 0 !important; padding: 0 !important; }
        .os-print-container { width: 100% !important; border: none !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
        @page { margin: 1.5cm; }
    }
    
    .os-print-container {
        max-width: 800px;
        margin: 20px auto;
        background: #fff;
        padding: 40px;
        border: 1px solid #ccc;
        box-shadow: 0 0 10px rgba(0,0,0,0.1);
        font-family: 'Tahoma', 'Arial', sans-serif;
        color: #000;
        line-height: 1.6;
    }

    .os-print-header {
        text-align: center;
        border-bottom: 2px solid #000;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .os-print-title {
        font-size: 24px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .os-print-meta {
        display: flex;
        justify-content: space-between;
        margin-bottom: 30px;
        font-size: 14px;
    }

    .os-print-section {
        margin-bottom: 30px;
    }

    .os-print-section-title {
        font-size: 18px;
        font-weight: bold;
        border-bottom: 1px solid #000;
        padding-bottom: 5px;
        margin-bottom: 15px;
    }

    .os-print-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 15px;
    }
    .os-print-table th, .os-print-table td {
        border: 1px solid #000;
        padding: 8px;
        text-align: right;
    }
    .os-print-table th {
        background-color: #f1f1f1;
        -webkit-print-color-adjust: exact;
    }

    .os-print-signatures {
        margin-top: 60px;
        display: flex;
        justify-content: space-between;
    }
    .os-print-signatures div {
        width: 40%;
        text-align: center;
        border-top: 1px dashed #000;
        padding-top: 10px;
    }

    .no-print {
        text-align: center;
        margin-bottom: 20px;
    }
</style>

<div class="wrap">
    <div class="no-print">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=olama-registration-agreements&action=edit&id=' . $id ) ); ?>" class="button">&laquo; <?php esc_html_e( 'العودة للعقد', 'olama-registration' ); ?></a>
        <button type="button" class="button button-primary" onclick="window.print();" style="margin-right:10px;">
            <span class="dashicons dashicons-printer" style="margin-top:4px;"></span> <?php esc_html_e( 'طباعة العقد', 'olama-registration' ); ?>
        </button>
    </div>

    <div class="os-print-container" id="os-print-area" dir="rtl">
        <div class="os-print-header">
            <div class="os-print-title"><?php esc_html_e( 'عقد تسجيل / نشاط', 'olama-registration' ); ?></div>
            <div><?php echo esc_html( $agreement->activity_type ); ?></div>
        </div>

        <div class="os-print-meta">
            <div>
                <strong><?php esc_html_e( 'رقم العقد:', 'olama-registration' ); ?></strong> <?php echo esc_html( $agreement->agreement_number ); ?><br>
                <strong><?php esc_html_e( 'تاريخ البداية:', 'olama-registration' ); ?></strong> <?php echo esc_html( $agreement->start_date ); ?>
                <?php if ( $agreement->end_date ) : ?>
                    <br><strong><?php esc_html_e( 'تاريخ النهاية:', 'olama-registration' ); ?></strong> <?php echo esc_html( $agreement->end_date ); ?>
                <?php endif; ?>
            </div>
            <div>
                <strong><?php esc_html_e( 'تاريخ الإصدار:', 'olama-registration' ); ?></strong> <?php echo esc_html( date('Y-m-d', strtotime($agreement->created_at)) ); ?><br>
                <strong><?php esc_html_e( 'الحالة:', 'olama-registration' ); ?></strong> <?php echo esc_html( $agreement->status ); ?>
            </div>
        </div>

        <div class="os-print-section">
            <div class="os-print-section-title"><?php esc_html_e( 'الطرفين', 'olama-registration' ); ?></div>
            <table class="os-print-table">
                <tr>
                    <th style="width: 25%;"><?php esc_html_e( 'الطرف الأول (المركز/المدرسة)', 'olama-registration' ); ?></th>
                    <td><?php esc_html_e( 'نظام علماء', 'olama-registration' ); // Replace with dynamic option if exists ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'الطرف الثاني (الجهة الدافعة)', 'olama-registration' ); ?></th>
                    <td><?php echo esc_html( $payer_name ); ?> <?php echo $payer_phone ? ' ( ' . esc_html( $payer_phone ) . ' ) ' : ''; ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'المشترك', 'olama-registration' ); ?></th>
                    <td><?php echo esc_html( $agreement->participant_name ); ?></td>
                </tr>
            </table>
        </div>

        <div class="os-print-section">
            <div class="os-print-section-title"><?php esc_html_e( 'الرسوم المستحقة', 'olama-registration' ); ?></div>
            <?php if ( $fees ) : ?>
                <table class="os-print-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'الرقم', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'البيان', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'تاريخ الاستحقاق', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'المبلغ (الصافي)', 'olama-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ( $fees as $fee ) : 
                        ?>
                            <tr>
                                <td style="text-align:center;"><?php echo $i++; ?></td>
                                <td><?php echo esc_html( $fee->label ?: $fee->fee_category ); ?></td>
                                <td><?php echo esc_html( $fee->due_date ?: '-' ); ?></td>
                                <td style="text-align:left;"><?php echo number_format( (float) $fee->net_amount, 3 ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" style="text-align:left;"><?php esc_html_e( 'الإجمالي:', 'olama-registration' ); ?></th>
                            <th style="text-align:left;"><?php echo number_format( (float) $agreement->total_amount, 3 ); ?> JD</th>
                        </tr>
                    </tfoot>
                </table>
            <?php else : ?>
                <p><?php esc_html_e( 'لا يوجد رسوم مسجلة لهذا العقد.', 'olama-registration' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="os-print-section">
            <div class="os-print-section-title"><?php esc_html_e( 'البنود والشروط', 'olama-registration' ); ?></div>
            <?php if ( $clauses ) : ?>
                <ol style="padding-right: 20px;">
                    <?php foreach ( $clauses as $clause ) : ?>
                        <li style="margin-bottom: 10px;"><?php echo nl2br( esc_html( $clause->clause_text ) ); ?></li>
                    <?php endforeach; ?>
                </ol>
            <?php else : ?>
                <p><?php esc_html_e( 'لا يوجد بنود مسجلة لهذا العقد.', 'olama-registration' ); ?></p>
            <?php endif; ?>
        </div>

        <div class="os-print-signatures">
            <div>
                <strong><?php esc_html_e( 'الطرف الأول', 'olama-registration' ); ?></strong><br>
                <?php esc_html_e( '( التوقيع / الختم )', 'olama-registration' ); ?>
            </div>
            <div>
                <strong><?php esc_html_e( 'الطرف الثاني', 'olama-registration' ); ?></strong><br>
                <?php esc_html_e( '( التوقيع )', 'olama-registration' ); ?>
            </div>
        </div>
    </div>
</div>
