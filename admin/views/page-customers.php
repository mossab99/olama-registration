<?php
/**
 * External Customers — Full CRUD List with expandable children
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$per_page = 50;
$offset   = max( 0, (int) ( $_GET['paged'] ?? 0 ) ) * $per_page;
$search   = sanitize_text_field( $_GET['s'] ?? '' );

$customers = Olama_Reg_Customer::get_list( [
    'per_page' => $per_page,
    'offset'   => $offset,
    'search'   => $search,
] );
$total = Olama_Reg_Customer::count( [ 'search' => $search ] );
?>
<div class="olama-customers-wrap" dir="rtl">

    <!-- Header -->
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
        <div>
            <h2 style="margin:0; color:var(--reg-primary); font-size:18px;">
                <span class="dashicons dashicons-businessman" style="vertical-align:middle;"></span>
                العملاء الخارجيون (Walk-in)
            </h2>
            <p style="margin:4px 0 0; color:#64748b; font-size:13px;">إدارة كاملة للعملاء وأبنائهم · <strong><?php echo number_format($total); ?></strong> عميل</p>
        </div>
        <button type="button" class="button button-primary" id="cust_btn_add_new"
                style="background:var(--reg-primary); border-color:var(--reg-primary); display:flex; align-items:center; gap:6px; height:36px;">
            <span class="dashicons dashicons-plus-alt2" style="margin-top:3px;font-size:16px;"></span>
            إضافة عميل جديد
        </button>
    </div>

    <!-- Search bar -->
    <div style="margin-bottom:16px;">
        <input type="text" id="cust_search_input" class="regular-text"
               value="<?php echo esc_attr($search); ?>"
               placeholder="🔍  ابحث بالاسم أو الهاتف أو رقم العميل أو اسم الابن..."
               style="width:380px; padding:6px 12px; border-radius:6px; border:1px solid #cbd5e1;">
    </div>

    <!-- Notices -->
    <div id="cust_notice" style="display:none; padding:10px 16px; border-radius:6px; margin-bottom:12px; font-weight:600;"></div>

    <!-- Table -->
    <div class="olama-reg-box" style="padding:0; overflow:hidden;">
        <table class="wp-list-table widefat fixed striped" id="cust_table" style="border:none;">
            <thead>
                <tr style="background:#f8fafc;">
                    <th style="width:36px; text-align:center;"></th>
                    <th style="width:110px; color:var(--reg-primary);">رقم العميل</th>
                    <th>الاسم</th>
                    <th style="width:140px;">الهاتف</th>
                    <th style="width:75px; text-align:center;">الأبناء</th>
                    <th style="width:105px;">تاريخ الإضافة</th>
                    <th style="width:80px; text-align:center;">الحالة</th>
                    <th style="width:115px; text-align:center;">إجراءات</th>
                </tr>
            </thead>
            <tbody id="cust_tbody">
                <?php if ( empty( $customers ) ) : ?>
                <tr id="cust_no_data_row">
                    <td colspan="8" style="text-align:center; padding:40px; color:#94a3b8;">
                        <span class="dashicons dashicons-businessman" style="font-size:40px; height:40px; width:40px; color:#cbd5e1;"></span>
                        <p style="margin:8px 0 0;">لا يوجد عملاء<?php echo $search ? ' لهذا البحث' : ' بعد'; ?>. <?php echo !$search ? 'انقر على "إضافة عميل جديد" للبدء.' : ''; ?></p>
                    </td>
                </tr>
                <?php else : ?>
                <?php foreach ( $customers as $cust ) :
                    $uid = esc_attr( $cust->customer_uid ?: ( 'CUST-' . str_pad( $cust->id, 4, '0', STR_PAD_LEFT ) ) );
                ?>
                <!-- Customer Row -->
                <tr class="cust-row" data-id="<?php echo esc_attr($cust->id); ?>">
                    <td style="text-align:center; padding:8px 4px;">
                        <button class="button-link cust-toggle-children"
                                data-id="<?php echo esc_attr($cust->id); ?>"
                                title="عرض/إخفاء الأبناء"
                                style="color:var(--reg-primary); padding:2px; width:24px; border-radius:4px; transition:background .15s;">
                            <span class="dashicons dashicons-arrow-down-alt2 cust-toggle-icon" style="font-size:16px;width:16px;height:16px;"></span>
                        </button>
                    </td>
                    <td>
                        <code style="background:#eff6ff; padding:2px 8px; border-radius:4px; font-size:12px; color:var(--reg-primary); font-weight:700;"><?php echo esc_html($uid); ?></code>
                    </td>
                    <td>
                        <strong style="font-size:14px;"><?php echo esc_html($cust->customer_name); ?></strong>
                        <?php if ( $cust->notes ) : ?>
                        <span style="display:block; font-size:11px; color:#94a3b8; margin-top:2px;" title="<?php echo esc_attr($cust->notes); ?>">
                            <?php echo esc_html( mb_substr( $cust->notes, 0, 50 ) . ( mb_strlen($cust->notes) > 50 ? '...' : '' ) ); ?>
                        </span>
                        <?php endif; ?>
                    </td>
                    <td dir="ltr" style="font-family:monospace;"><?php echo esc_html($cust->phone ?: '—'); ?></td>
                    <td style="text-align:center;">
                        <span class="cust-children-count" data-id="<?php echo esc_attr($cust->id); ?>"
                              style="background:#e0f2fe; color:#0369a1; border-radius:12px; padding:2px 10px; font-size:12px; font-weight:700;">
                            <?php echo (int)($cust->children_count ?? 0); ?>
                        </span>
                    </td>
                    <td style="color:#64748b; font-size:12px;">
                        <?php echo esc_html( date_i18n( get_option('date_format'), strtotime($cust->created_at) ) ); ?>
                    </td>
                    <td style="text-align:center;">
                        <?php if ( $cust->is_active ) : ?>
                        <span class="cust-status-badge" data-id="<?php echo esc_attr($cust->id); ?>" style="background:#dcfce7; color:#16a34a; border-radius:10px; padding:2px 8px; font-size:11px; font-weight:700;">نشط</span>
                        <?php else : ?>
                        <span class="cust-status-badge" data-id="<?php echo esc_attr($cust->id); ?>" style="background:#fee2e2; color:#dc2626; border-radius:10px; padding:2px 8px; font-size:11px; font-weight:700;">غير نشط</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <button class="button button-small cust-btn-edit"
                                data-id="<?php echo esc_attr($cust->id); ?>"
                                title="تعديل"
                                style="margin-left:3px;">
                            <span class="dashicons dashicons-edit" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
                        </button>
                        <button class="button button-small cust-btn-delete"
                                data-id="<?php echo esc_attr($cust->id); ?>"
                                data-name="<?php echo esc_attr($cust->customer_name); ?>"
                                title="حذف"
                                style="color:#dc2626; border-color:#fca5a5;">
                            <span class="dashicons dashicons-trash" style="font-size:14px;width:14px;height:14px;vertical-align:middle;"></span>
                        </button>
                    </td>
                </tr>
                <!-- Children Expand Row -->
                <tr class="cust-children-row" data-parent-id="<?php echo esc_attr($cust->id); ?>" style="display:none; background:#f0f9ff;">
                    <td colspan="8" style="padding:0;">
                        <div class="cust-children-container" data-id="<?php echo esc_attr($cust->id); ?>" style="padding:12px 16px 16px 52px;">
                            <!-- Populated via AJAX -->
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ( $total > $per_page ) :
        $pages = ceil( $total / $per_page );
        $current_page = max( 0, (int)( $_GET['paged'] ?? 0 ) );
    ?>
    <div style="margin-top:12px; display:flex; align-items:center; gap:6px; justify-content:flex-end;">
        <?php for ( $i = 0; $i < $pages; $i++ ) : ?>
        <a href="<?php echo esc_url( add_query_arg( ['paged' => $i, 's' => $search] ) ); ?>"
           style="padding:4px 10px; border-radius:4px; text-decoration:none; font-size:13px;
                  <?php echo $i === $current_page ? 'background:var(--reg-primary); color:#fff;' : 'background:#f1f5f9; color:#475569;'; ?>">
            <?php echo ($i + 1); ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>

</div>

<?php
// The modal is in partial-customer-modal.php — include it here
$cust_modal_rendered = true;
include OLAMA_REG_PATH . 'admin/views/partial-customer-modal.php';
?>
