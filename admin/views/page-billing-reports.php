<?php
/**
 * Billing Reports and Analytics Dashboard.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$active_tab = sanitize_key( $_GET['report_tab'] ?? 'dashboard' );
$action     = sanitize_text_field( $_GET['action'] ?? '' );

$year_id = absint( $_GET['year_id'] ?? 0 );
if ( ! $year_id && class_exists( 'Olama_School_Academic' ) ) {
    $active_y = Olama_School_Academic::get_active_year();
    if ( $active_y ) {
        $year_id = (int) $active_y->id;
    }
}

$years = [];
if ( class_exists( 'Olama_School_Academic' ) ) {
    $years = Olama_School_Academic::get_years();
}

$method_labels = Olama_Reg_Billing_Reports::get_method_labels();
$available_methods = Olama_Reg_Billing_Reports::get_available_payment_methods();
$cashiers = Olama_Reg_Billing_Reports::get_cashier_options();

$cash_filters = [
    'year_id'    => $year_id,
    'date_from'  => sanitize_text_field( $_GET['date_from'] ?? '' ),
    'date_to'    => sanitize_text_field( $_GET['date_to'] ?? '' ),
    'method'     => sanitize_text_field( $_GET['method'] ?? '' ),
    'cashier_id' => absint( $_GET['cashier_id'] ?? 0 ),
];
$cash_report = Olama_Reg_Billing_Reports::get_cash_register_report( $cash_filters );
$cash_rows = $cash_report['rows'];
$cash_summary = $cash_report['summary'];

$money = static function ( $amount ): string {
    return number_format( (float) $amount, 2 );
};

$year_label = __( 'جميع الأعوام الدراسية', 'olama-registration' );
foreach ( $years as $y ) {
    if ( (int) $y->id === (int) $year_id ) {
        $year_label = $y->year_name;
        break;
    }
}

$build_url = static function ( array $args = [] ): string {
    return esc_url( add_query_arg( $args, admin_url( 'admin.php' ) ) );
};

$export_args = [
    'page'       => 'olama-registration-reports',
    'report_tab' => 'cash_register',
    'year_id'    => $year_id,
    'date_from'  => $cash_report['filters']['date_from'],
    'date_to'    => $cash_report['filters']['date_to'],
    'method'     => $cash_report['filters']['method'],
    'cashier_id' => $cash_report['filters']['cashier_id'],
];

if ( $action === 'export_cash_register_excel' ) {
    nocache_headers();
    header( 'Content-Type: application/vnd.ms-excel; charset=UTF-8' );
    header( 'Content-Disposition: attachment; filename="cash-register-report-' . date( 'Y-m-d' ) . '.xls"' );
    echo "\xEF\xBB\xBF";
    ?>
    <html lang="ar" dir="rtl">
    <head><meta charset="UTF-8"></head>
    <body>
    <table border="1">
        <thead>
            <tr>
                <th><?php esc_html_e( 'رقم السند', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'اسم الطالب', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'رقم الطالب', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'تاريخ القبض', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'المبلغ المقبوض', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'رقم المرجع', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></th>
                <th><?php esc_html_e( 'المستلم', 'olama-registration' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $cash_rows as $row ) : ?>
                <tr>
                    <td><?php echo esc_html( '#' . $row->id ); ?></td>
                    <td><?php echo esc_html( $row->student_name ?: '—' ); ?></td>
                    <td><?php echo esc_html( $row->student_identifier ?: '—' ); ?></td>
                    <td><?php echo esc_html( $row->payment_date ); ?></td>
                    <td><?php echo esc_html( $method_labels[ $row->method ] ?? $row->method ); ?></td>
                    <td><?php echo esc_html( $money( $row->amount ) ); ?></td>
                    <td><?php echo esc_html( $row->reference ?: '—' ); ?></td>
                    <td><?php echo esc_html( $row->notes ?: '—' ); ?></td>
                    <td><?php echo esc_html( $row->received_by_name ?: '—' ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </body>
    </html>
    <?php
    exit;
}

if ( $action === 'print_cash_register' || $action === 'export_cash_register_pdf' ) {
    $school_settings = get_option( 'olama_school_settings', [] );
    $school_name = $school_settings['school_name_ar'] ?? get_bloginfo( 'name' );
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title><?php esc_html_e( 'جرد الصندوق', 'olama-registration' ); ?></title>
        <style>
            body { font-family: Tajawal, Arial, sans-serif; color:#1a1a2e; margin:28px; direction:rtl; }
            .no-print { margin-bottom:16px; text-align:left; }
            button { background:#E8920A; color:#fff; border:0; border-radius:6px; padding:9px 18px; font-weight:700; cursor:pointer; }
            h1 { margin:0 0 4px; font-size:24px; }
            .meta { color:#64748b; margin-bottom:18px; }
            .summary { display:grid; grid-template-columns:repeat(5, 1fr); gap:8px; margin:18px 0; }
            .box { border:1px solid #E0C090; background:#FFF8E7; padding:10px; border-radius:6px; }
            .box strong { display:block; font-size:17px; margin-top:4px; }
            table { width:100%; border-collapse:collapse; font-size:12px; }
            th, td { border:1px solid #d8dee9; padding:7px; text-align:right; vertical-align:top; }
            th { background:#1A1A2E; color:#fff; }
            @media print { .no-print { display:none; } body { margin:0; } }
        </style>
    </head>
    <body>
        <div class="no-print">
            <button onclick="window.print();"><?php echo $action === 'export_cash_register_pdf' ? esc_html__( 'حفظ / طباعة PDF', 'olama-registration' ) : esc_html__( 'طباعة', 'olama-registration' ); ?></button>
        </div>
        <h1><?php esc_html_e( 'جرد الصندوق', 'olama-registration' ); ?></h1>
        <div class="meta">
            <?php echo esc_html( $school_name ); ?> |
            <?php esc_html_e( 'العام الدراسي:', 'olama-registration' ); ?> <?php echo esc_html( $year_label ); ?> |
            <?php esc_html_e( 'من:', 'olama-registration' ); ?> <?php echo esc_html( $cash_report['filters']['date_from'] ?: '—' ); ?> |
            <?php esc_html_e( 'إلى:', 'olama-registration' ); ?> <?php echo esc_html( $cash_report['filters']['date_to'] ?: '—' ); ?>
        </div>
        <div class="summary">
            <div class="box"><?php esc_html_e( 'إجمالي النقدي', 'olama-registration' ); ?><strong><?php echo esc_html( $money( $cash_summary['cash'] ) ); ?></strong></div>
            <div class="box"><?php esc_html_e( 'إجمالي الشيكات', 'olama-registration' ); ?><strong><?php echo esc_html( $money( $cash_summary['cheque'] ) ); ?></strong></div>
            <div class="box"><?php esc_html_e( 'إجمالي الدفع الإلكتروني', 'olama-registration' ); ?><strong><?php echo esc_html( $money( $cash_summary['online'] ) ); ?></strong></div>
            <div class="box"><?php esc_html_e( 'الإجمالي العام', 'olama-registration' ); ?><strong><?php echo esc_html( $money( $cash_summary['total'] ) ); ?></strong></div>
            <div class="box"><?php esc_html_e( 'عدد الحركات', 'olama-registration' ); ?><strong><?php echo esc_html( (int) $cash_summary['transaction_count'] ); ?></strong></div>
        </div>
        <?php olama_reg_render_cash_register_table( $cash_rows, $method_labels, $money ); ?>
        <?php if ( $action === 'export_cash_register_pdf' ) : ?>
            <script>window.addEventListener('load', function(){ window.print(); });</script>
        <?php endif; ?>
    </body>
    </html>
    <?php
    exit;
}

$metrics = Olama_Reg_Billing_Reports::get_summary_metrics( $year_id );
$monthly = Olama_Reg_Billing_Reports::get_monthly_collections( $year_id );
$methods = Olama_Reg_Billing_Reports::get_payment_method_breakdown( $year_id );
$aging   = Olama_Reg_Billing_Reports::get_aging_receivables( $year_id );

if ( ! function_exists( 'olama_reg_render_cash_register_table' ) ) {
function olama_reg_render_cash_register_table( array $rows, array $method_labels, callable $money ): void {
    ?>
    <div class="olama-reg-table-wrap">
        <table class="olama-reg-fin-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'رقم السند', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'اسم الطالب', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'رقم الطالب', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'تاريخ القبض', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'المبلغ المقبوض', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'رقم المرجع', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'ملاحظات', 'olama-registration' ); ?></th>
                    <th><?php esc_html_e( 'المستلم', 'olama-registration' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rows ) ) : ?>
                    <tr>
                        <td colspan="9" class="olama-reg-empty-state">
                            <?php esc_html_e( 'لا توجد حركات قبض مطابقة للفلاتر المحددة.', 'olama-registration' ); ?>
                        </td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rows as $row ) : ?>
                        <tr>
                            <td><strong>#<?php echo esc_html( $row->id ); ?></strong></td>
                            <td><?php echo esc_html( $row->student_name ?: '—' ); ?></td>
                            <td><span class="olama-reg-uid-badge"><?php echo esc_html( $row->student_identifier ?: '—' ); ?></span></td>
                            <td><?php echo esc_html( $row->payment_date ); ?></td>
                            <td><?php echo esc_html( $method_labels[ $row->method ] ?? $row->method ); ?></td>
                            <td class="olama-reg-text--success"><?php echo esc_html( $money( $row->amount ) ); ?></td>
                            <td><?php echo esc_html( $row->reference ?: '—' ); ?></td>
                            <td><?php echo esc_html( $row->notes ?: '—' ); ?></td>
                            <td><?php echo esc_html( $row->received_by_name ?: '—' ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
}
?>
<div class="wrap olama-reg-wrap" dir="rtl">
    <div class="olama-reg-page-header">
        <h1>
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'التقارير المالية والتحليلات', 'olama-registration' ); ?>
        </h1>
        <?php if ( $year_id ) : ?>
            <span class="olama-reg-badge olama-reg-badge--info" style="padding:8px 18px; font-size:13px;">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html( $year_label ); ?>
            </span>
        <?php endif; ?>
    </div>

    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>

    <div class="olama-reg-report-tabs no-print">
        <a class="<?php echo $active_tab === 'dashboard' ? 'active' : ''; ?>" href="<?php echo $build_url( [ 'page' => 'olama-registration-reports', 'report_tab' => 'dashboard', 'year_id' => $year_id ] ); ?>">
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'لوحة التقارير', 'olama-registration' ); ?>
        </a>
        <a class="<?php echo $active_tab === 'cash_register' ? 'active' : ''; ?>" href="<?php echo $build_url( [ 'page' => 'olama-registration-reports', 'report_tab' => 'cash_register', 'year_id' => $year_id ] ); ?>">
            <span class="dashicons dashicons-portfolio"></span>
            <?php esc_html_e( 'جرد الصندوق', 'olama-registration' ); ?>
        </a>
    </div>

    <?php if ( $active_tab === 'cash_register' ) : ?>
        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-filter"></span>
                <?php esc_html_e( 'فلاتر جرد الصندوق', 'olama-registration' ); ?>
            </h3>
            <form method="get" class="olama-reg-grid olama-reg-grid--compact">
                <input type="hidden" name="page" value="olama-registration-reports">
                <input type="hidden" name="report_tab" value="cash_register">
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'العام الدراسي', 'olama-registration' ); ?></label>
                    <select name="year_id">
                        <option value="0"><?php esc_html_e( 'جميع الأعوام الدراسية', 'olama-registration' ); ?></option>
                        <?php foreach ( $years as $y ) : ?>
                            <option value="<?php echo esc_attr( $y->id ); ?>" <?php selected( $year_id, $y->id ); ?>><?php echo esc_html( $y->year_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'من تاريخ', 'olama-registration' ); ?></label>
                    <input type="date" name="date_from" value="<?php echo esc_attr( $cash_report['filters']['date_from'] ); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'إلى تاريخ', 'olama-registration' ); ?></label>
                    <input type="date" name="date_to" value="<?php echo esc_attr( $cash_report['filters']['date_to'] ); ?>">
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></label>
                    <select name="method">
                        <option value=""><?php esc_html_e( 'جميع طرق الدفع', 'olama-registration' ); ?></option>
                        <?php foreach ( $available_methods as $method_key => $method_label ) : ?>
                            <option value="<?php echo esc_attr( $method_key ); ?>" <?php selected( $cash_report['filters']['method'], $method_key ); ?>><?php echo esc_html( $method_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field">
                    <label><?php esc_html_e( 'أمين الصندوق', 'olama-registration' ); ?></label>
                    <select name="cashier_id">
                        <option value="0"><?php esc_html_e( 'جميع المستخدمين', 'olama-registration' ); ?></option>
                        <?php foreach ( $cashiers as $cashier ) : ?>
                            <option value="<?php echo esc_attr( $cashier->ID ); ?>" <?php selected( $cash_report['filters']['cashier_id'], $cashier->ID ); ?>><?php echo esc_html( $cashier->display_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-field" style="justify-content:flex-end;">
                    <button type="submit" class="olama-reg-btn olama-reg-btn--primary">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'عرض التقرير', 'olama-registration' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="olama-reg-metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            <div class="olama-reg-metric-card olama-reg-metric-card--success">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي النقدي', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $cash_summary['cash'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card olama-reg-metric-card--warning">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-media-spreadsheet"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الشيكات', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $cash_summary['cheque'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card olama-reg-metric-card--primary">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-smartphone"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الدفع الإلكتروني', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $cash_summary['online'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-bank"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي التحويلات', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $cash_summary['bank_transfer'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card olama-reg-metric-card--success">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-chart-line"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي المقبوضات', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $cash_summary['total'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-list-view"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'عدد الحركات', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( (int) $cash_summary['transaction_count'] ); ?></div>
                </div>
            </div>
        </div>

        <div class="olama-reg-fin-toolbar no-print">
            <strong><?php esc_html_e( 'تفاصيل حركات القبض', 'olama-registration' ); ?></strong>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                <a class="olama-reg-btn olama-reg-btn--secondary" href="<?php echo $build_url( array_merge( $export_args, [ 'action' => 'export_cash_register_excel' ] ) ); ?>">
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php esc_html_e( 'Excel', 'olama-registration' ); ?>
                </a>
                <a class="olama-reg-btn olama-reg-btn--secondary" target="_blank" href="<?php echo $build_url( array_merge( $export_args, [ 'action' => 'export_cash_register_pdf' ] ) ); ?>">
                    <span class="dashicons dashicons-pdf"></span>
                    <?php esc_html_e( 'PDF', 'olama-registration' ); ?>
                </a>
                <a class="olama-reg-btn olama-reg-btn--secondary" target="_blank" href="<?php echo $build_url( array_merge( $export_args, [ 'action' => 'print_cash_register' ] ) ); ?>">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'طباعة', 'olama-registration' ); ?>
                </a>
            </div>
        </div>

        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-portfolio"></span>
                <?php esc_html_e( 'جرد الصندوق', 'olama-registration' ); ?>
            </h3>
            <?php olama_reg_render_cash_register_table( $cash_rows, $method_labels, $money ); ?>
        </div>
    <?php else : ?>
        <div class="olama-reg-filter-bar">
            <form method="get" class="olama-reg-filter-form">
                <input type="hidden" name="page" value="olama-registration-reports">
                <input type="hidden" name="report_tab" value="dashboard">
                <div class="olama-reg-filter-group">
                    <label><?php esc_html_e( 'العام الدراسي المستهدف:', 'olama-registration' ); ?></label>
                    <select name="year_id" class="olama-reg-filter-input">
                        <option value="0"><?php esc_html_e( 'جميع الأعوام الدراسية', 'olama-registration' ); ?></option>
                        <?php foreach ( $years as $y ) : ?>
                            <option value="<?php echo esc_attr( $y->id ); ?>" <?php selected( $year_id, $y->id ); ?>>
                                <?php echo esc_html( $y->year_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="olama-reg-filter-group">
                    <button type="submit" class="olama-reg-btn olama-reg-btn--primary">
                        <span class="dashicons dashicons-chart-bar"></span>
                        <?php esc_html_e( 'عرض التقرير', 'olama-registration' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="olama-reg-metrics-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
            <div class="olama-reg-metric-card olama-reg-metric-card--primary">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-media-text"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الرسوم المفوترة', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $metrics['total_invoiced'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card olama-reg-metric-card--success">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-money-alt"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي المبالغ المحصلة', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $metrics['total_collected'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card olama-reg-metric-card--warning">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-analytics"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'ذمم مدينة مستحقة', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $metrics['total_receivables'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card olama-reg-metric-card--danger">
                <div class="olama-reg-metric-icon"><span class="dashicons dashicons-warning"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الذمم المتأخرة', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value"><?php echo esc_html( $money( $metrics['total_overdue'] ) ); ?></div>
                </div>
            </div>
            <div class="olama-reg-metric-card" style="background:#f1f5f9; border-left:4px solid #94a3b8;">
                <div class="olama-reg-metric-icon" style="background:#e2e8f0; color:#64748b;"><span class="dashicons dashicons-tag"></span></div>
                <div class="olama-reg-metric-content">
                    <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الخصومات الممنوحة', 'olama-registration' ); ?></div>
                    <div class="olama-reg-metric-value" style="color:#475569;"><?php echo esc_html( $money( $metrics['total_discount'] ) ); ?></div>
                </div>
            </div>
        </div>

        <div class="olama-reg-split-grid">
            <div class="olama-reg-section" style="margin:0;">
                <h3 class="olama-reg-section-title"><span class="dashicons dashicons-calendar-alt"></span><?php esc_html_e( 'حركة التحصيل الشهري', 'olama-registration' ); ?></h3>
                <div class="olama-reg-table-wrap">
                    <table class="olama-reg-fin-table">
                        <thead><tr><th><?php esc_html_e( 'الشهر', 'olama-registration' ); ?></th><th><?php esc_html_e( 'المبلغ المحصل', 'olama-registration' ); ?></th></tr></thead>
                        <tbody>
                            <?php if ( empty( $monthly ) ) : ?>
                                <tr><td colspan="2" class="olama-reg-empty-state"><?php esc_html_e( 'لا توجد حركات تحصيل في الفترة المحددة.', 'olama-registration' ); ?></td></tr>
                            <?php else : foreach ( $monthly as $m ) : ?>
                                <tr><td><strong><?php echo esc_html( $m['month'] ); ?></strong></td><td class="olama-reg-text--success"><?php echo esc_html( $money( $m['amount'] ) ); ?></td></tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="olama-reg-section" style="margin:0;">
                <h3 class="olama-reg-section-title"><span class="dashicons dashicons-bank"></span><?php esc_html_e( 'توزيع طرق الدفع والسداد', 'olama-registration' ); ?></h3>
                <div class="olama-reg-table-wrap">
                    <table class="olama-reg-fin-table">
                        <thead><tr><th><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></th><th><?php esc_html_e( 'عدد الحركات', 'olama-registration' ); ?></th><th><?php esc_html_e( 'إجمالي التحصيل', 'olama-registration' ); ?></th></tr></thead>
                        <tbody>
                            <?php if ( empty( $methods ) ) : ?>
                                <tr><td colspan="3" class="olama-reg-empty-state"><?php esc_html_e( 'لا توجد حركات سداد مسجلة.', 'olama-registration' ); ?></td></tr>
                            <?php else : foreach ( $methods as $met ) : ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $method_labels[ $met['method'] ] ?? $met['method'] ); ?></strong></td>
                                    <td><?php echo esc_html( $met['count'] ); ?></td>
                                    <td class="olama-reg-text--success"><?php echo esc_html( $money( $met['amount'] ) ); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="olama-reg-section">
            <h3 class="olama-reg-section-title"><span class="dashicons dashicons-clock"></span><?php esc_html_e( 'أعمار الديون والذمم المتأخرة السداد', 'olama-registration' ); ?></h3>
            <div class="olama-reg-aging-grid">
                <div class="olama-reg-aging-card olama-reg-aging-card--30"><div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر (1 - 30 يوم)', 'olama-registration' ); ?></div><div class="olama-reg-aging-val"><?php echo esc_html( $money( $aging['band_30'] ) ); ?></div></div>
                <div class="olama-reg-aging-card olama-reg-aging-card--60"><div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر (31 - 60 يوم)', 'olama-registration' ); ?></div><div class="olama-reg-aging-val"><?php echo esc_html( $money( $aging['band_60'] ) ); ?></div></div>
                <div class="olama-reg-aging-card olama-reg-aging-card--90"><div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر (61 - 90 يوم)', 'olama-registration' ); ?></div><div class="olama-reg-aging-val"><?php echo esc_html( $money( $aging['band_90'] ) ); ?></div></div>
                <div class="olama-reg-aging-card olama-reg-aging-card--90-plus"><div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر جداً (أكثر من 90 يوم)', 'olama-registration' ); ?></div><div class="olama-reg-aging-val"><?php echo esc_html( $money( $aging['band_90_plus'] ) ); ?></div></div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
    .olama-reg-wrap .olama-reg-report-tabs {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin: 0 0 18px;
    }
    .olama-reg-wrap .olama-reg-report-tabs a {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 10px 16px;
        border: 1px solid var(--reg-border);
        border-radius: var(--reg-radius-sm);
        background: #fff;
        color: var(--reg-text);
        text-decoration: none;
        font-weight: 800;
        box-shadow: var(--reg-shadow-sm);
    }
    .olama-reg-wrap .olama-reg-report-tabs a.active,
    .olama-reg-wrap .olama-reg-report-tabs a:hover {
        border-color: var(--reg-primary);
        background: var(--reg-primary-light);
        color: var(--reg-primary-dark);
    }
</style>
