<?php
/**
 * Billing Reports and Analytics Dashboard
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$year_id = absint( $_GET['year_id'] ?? 0 );

// If no year selected, load active year as default
if ( ! $year_id && class_exists( 'Olama_School_Academic' ) ) {
    $active_y = Olama_School_Academic::get_active_year();
    if ( $active_y ) $year_id = (int) $active_y->id;
}

$metrics    = Olama_Reg_Billing_Reports::get_summary_metrics( $year_id );
$monthly    = Olama_Reg_Billing_Reports::get_monthly_collections( $year_id );
$methods    = Olama_Reg_Billing_Reports::get_payment_method_breakdown( $year_id );
$aging      = Olama_Reg_Billing_Reports::get_aging_receivables( $year_id );

// Get academic years for filter dropdown
$years = [];
if ( class_exists( 'Olama_School_Academic' ) ) {
    $years = Olama_School_Academic::get_years();
}
?>
<div class="wrap olama-reg-wrap" dir="rtl">

    <div class="olama-reg-page-header">
        <h1>
            <span class="dashicons dashicons-chart-bar"></span>
            <?php esc_html_e( 'التقارير المالية والتحليلات', 'olama-registration' ); ?>
        </h1>
        <?php if ( $year_id ): ?>
        <span class="olama-reg-badge olama-reg-badge--info" style="padding:8px 18px; font-size:13px;">
            <span class="dashicons dashicons-calendar-alt"></span>
            <?php
            foreach ( $years as $y ) {
                if ( (int)$y->id === $year_id ) {
                    echo esc_html( $y->year_name );
                    break;
                }
            }
            ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Notice area -->
    <div id="olama-reg-notice" class="olama-reg-notice" style="display:none;"></div>

    <!-- ── ACADEMIC YEAR SELECTOR ──────────────────────────────────── -->
    <div class="olama-reg-filter-bar">
        <form method="get" class="olama-reg-filter-form">
            <input type="hidden" name="page" value="olama-registration-reports">

            <div class="olama-reg-filter-group">
                <label><?php esc_html_e( 'العام الدراسي المستهدف:', 'olama-registration' ); ?></label>
                <select name="year_id" class="olama-reg-filter-input">
                    <option value="0"><?php esc_html_e( 'جميع الأعوام الدراسية', 'olama-registration' ); ?></option>
                    <?php foreach ( $years as $y ): ?>
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

    <!-- ── METRICS INDICATOR CARDS ──────────────────────────────────── -->
    <div class="olama-reg-metrics-grid">

        <div class="olama-reg-metric-card olama-reg-metric-card--primary">
            <div class="olama-reg-metric-icon">
                <span class="dashicons dashicons-media-text"></span>
            </div>
            <div class="olama-reg-metric-content">
                <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الرسوم المفوترة', 'olama-registration' ); ?></div>
                <div class="olama-reg-metric-value"><?php echo esc_html( number_format( $metrics['total_invoiced'], 2 ) ); ?></div>
            </div>
        </div>

        <div class="olama-reg-metric-card olama-reg-metric-card--success">
            <div class="olama-reg-metric-icon">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
            <div class="olama-reg-metric-content">
                <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي المبالغ المحصلة', 'olama-registration' ); ?></div>
                <div class="olama-reg-metric-value"><?php echo esc_html( number_format( $metrics['total_collected'], 2 ) ); ?></div>
            </div>
        </div>

        <div class="olama-reg-metric-card olama-reg-metric-card--warning">
            <div class="olama-reg-metric-icon">
                <span class="dashicons dashicons-analytics"></span>
            </div>
            <div class="olama-reg-metric-content">
                <div class="olama-reg-metric-title"><?php esc_html_e( 'ذمم مدينة مستحقة', 'olama-registration' ); ?></div>
                <div class="olama-reg-metric-value"><?php echo esc_html( number_format( $metrics['total_receivables'], 2 ) ); ?></div>
            </div>
        </div>

        <div class="olama-reg-metric-card olama-reg-metric-card--danger">
            <div class="olama-reg-metric-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="olama-reg-metric-content">
                <div class="olama-reg-metric-title"><?php esc_html_e( 'إجمالي الذمم المتأخرة', 'olama-registration' ); ?></div>
                <div class="olama-reg-metric-value"><?php echo esc_html( number_format( $metrics['total_overdue'], 2 ) ); ?></div>
            </div>
        </div>

    </div>

    <!-- ── SECONDARY CONTENT GRID ──────────────────────────────────── -->
    <div class="olama-reg-split-grid">

        <!-- Monthly Collections Trend -->
        <div class="olama-reg-section" style="margin:0;">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e( 'حركة التحصيل الشهري', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-fin-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'الشهر', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'المبلغ المحصل', 'olama-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $monthly ) ): ?>
                            <tr>
                                <td colspan="2" class="olama-reg-empty-state"><?php esc_html_e( 'لا يوجد حركات تحصيل في الفترة المحددة.', 'olama-registration' ); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ( $monthly as $m ): ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $m['month'] ); ?></strong></td>
                                    <td class="olama-reg-badge--active" style="font-weight:700;"><?php echo esc_html( number_format( $m['amount'], 2 ) ); ?> د.أ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment Methods Breakdown -->
        <div class="olama-reg-section" style="margin:0;">
            <h3 class="olama-reg-section-title">
                <span class="dashicons dashicons-bank"></span>
                <?php esc_html_e( 'توزيع طرق الدفع والسداد', 'olama-registration' ); ?>
            </h3>
            <div class="olama-reg-table-wrap">
                <table class="olama-reg-fin-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'طريقة الدفع', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'عدد الحركات', 'olama-registration' ); ?></th>
                            <th><?php esc_html_e( 'إجمالي التحصيل', 'olama-registration' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $methods ) ): ?>
                            <tr>
                                <td colspan="3" class="olama-reg-empty-state"><?php esc_html_e( 'لا يوجد حركات سداد مسجلة.', 'olama-registration' ); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ( $methods as $met ): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <?php
                                            $meth_labels = [
                                                'cash'          => 'نقدي (كاش)',
                                                'bank_transfer' => 'تحويل بنكي',
                                                'cheque'        => 'شيك',
                                                'online'        => 'دفع إلكتروني',
                                            ];
                                            echo esc_html( $meth_labels[ $met['method'] ] ?? $met['method'] );
                                            ?>
                                        </strong>
                                    </td>
                                    <td><?php echo esc_html( $met['count'] ); ?></td>
                                    <td style="color:var(--reg-success); font-weight:700;"><?php echo esc_html( number_format( $met['amount'], 2 ) ); ?> د.أ</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ── AGING RECEIVABLES BAND ──────────────────────────────────── -->
    <div class="olama-reg-section">
        <h3 class="olama-reg-section-title">
            <span class="dashicons dashicons-clock"></span>
            <?php esc_html_e( 'أعمار الديون والذمم المتأخرة السداد', 'olama-registration' ); ?>
        </h3>
        <div class="olama-reg-aging-grid">

            <div class="olama-reg-aging-card olama-reg-aging-card--30">
                <div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر (1 - 30 يوم)', 'olama-registration' ); ?></div>
                <div class="olama-reg-aging-val"><?php echo esc_html( number_format( $aging['band_30'], 2 ) ); ?></div>
            </div>

            <div class="olama-reg-aging-card olama-reg-aging-card--60">
                <div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر (31 - 60 يوم)', 'olama-registration' ); ?></div>
                <div class="olama-reg-aging-val"><?php echo esc_html( number_format( $aging['band_60'], 2 ) ); ?></div>
            </div>

            <div class="olama-reg-aging-card olama-reg-aging-card--90">
                <div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر (61 - 90 يوم)', 'olama-registration' ); ?></div>
                <div class="olama-reg-aging-val"><?php echo esc_html( number_format( $aging['band_90'], 2 ) ); ?></div>
            </div>

            <div class="olama-reg-aging-card olama-reg-aging-card--90-plus">
                <div class="olama-reg-aging-title"><?php esc_html_e( 'متأخر جداً (أكثر من 90 يوم)', 'olama-registration' ); ?></div>
                <div class="olama-reg-aging-val"><?php echo esc_html( number_format( $aging['band_90_plus'], 2 ) ); ?></div>
            </div>

        </div>
    </div>

</div>
