<?php
/**
 * Print View for Settlement Receipt
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$id = (int) ( $_GET['id'] ?? 0 );
$receipt = Olama_Reg_Settlement::get_receipt( $id );

if ( ! $receipt ) {
    wp_die( __( 'Receipt not found.', 'olama-registration' ) );
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php esc_html_e( 'طباعة إيصال تسوية', 'olama-registration' ); ?></title>
    <style>
        body {
            font-family: 'Tajawal', sans-serif, Arial;
            background: #fff;
            color: #000;
            margin: 0;
            padding: 20px;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #333;
            padding: 30px;
            border-radius: 8px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #ccc;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .header p {
            margin: 0;
            color: #666;
            font-size: 14px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .info-table th, .info-table td {
            padding: 10px;
            border: 1px solid #eee;
            text-align: right;
        }
        .info-table th {
            background-color: #f9f9f9;
            width: 25%;
        }
        .amount-section {
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin: 30px 0;
            padding: 20px;
            background: #f1f1f1;
            border-radius: 8px;
        }
        .disclaimer {
            margin-top: 40px;
            padding: 20px;
            background: #fff8e5;
            border: 1px dashed #d63638;
            color: #d63638;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
        }
        .footer {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 40%;
            text-align: center;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
            .receipt-container { border: none; }
            .disclaimer { background: transparent !important; color: #000 !important; border: 1px dashed #000 !important; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align:center; margin-bottom: 20px;">
    <button onclick="window.print();" style="padding: 10px 20px; font-size: 16px; cursor: pointer;"><?php esc_html_e( 'طباعة الإيصال', 'olama-registration' ); ?></button>
</div>

<div class="receipt-container">
    <div class="header">
        <h1>إيصال تسوية مؤقت / Settlement Receipt</h1>
        <p>رقم الإيصال: <strong><?php echo esc_html( $receipt->receipt_number ); ?></strong></p>
        <p>التاريخ: <?php echo esc_html( date_i18n( get_option('date_format'), strtotime($receipt->created_at) ) ); ?></p>
    </div>

    <div class="amount-section">
        المبلغ الإجمالي / Total Amount: <?php echo number_format( $receipt->original_amount, 2 ); ?>
    </div>

    <table class="info-table">
        <tr>
            <th>رقم العائلة:</th>
            <td><?php echo esc_html( $receipt->family_id ); ?></td>
        </tr>
        <tr>
            <th>فئة الدفع:</th>
            <td><?php echo esc_html( $receipt->payment_category ); ?></td>
        </tr>
        <tr>
            <th>طريقة الدفع:</th>
            <td><?php echo esc_html( $receipt->payment_method ); ?></td>
        </tr>
        <?php if ( ! empty( $receipt->notes ) ) : ?>
        <tr>
            <th>ملاحظات:</th>
            <td><?php echo nl2br( esc_html( $receipt->notes ) ); ?></td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>الحالة:</th>
            <td>
                <?php 
                if ( $receipt->status === 'pending_settlement' ) echo 'بانتظار التسوية';
                elseif ( $receipt->status === 'settled' ) echo 'تمت التسوية';
                elseif ( $receipt->status === 'cancelled' ) echo 'ملغي';
                else echo esc_html( $receipt->status );
                ?>
            </td>
        </tr>
    </table>

    <div class="disclaimer">
        "This payment was collected temporarily and will be officially settled in the central Oracle financial system later."<br><br>
        "تم تحصيل هذه الدفعة مؤقتاً وسيتم تسويتها رسمياً في نظام أوراكل المالي المركزي لاحقاً."
    </div>

    <div class="footer">
        <div class="signature-box">
            توقيع المستلم / Receiver Signature
        </div>
        <div class="signature-box">
            توقيع دافع الرسوم / Payer Signature
        </div>
    </div>
</div>

</body>
</html>
