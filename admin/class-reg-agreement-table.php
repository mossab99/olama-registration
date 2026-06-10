<?php
/**
 * WP_List_Table for Agreements
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Olama_Reg_Agreement_Table extends WP_List_Table
{

    public function __construct()
    {
        parent::__construct([
            'singular' => 'agreement',
            'plural' => 'agreements',
            'ajax' => false,
        ]);
    }

    public function get_columns()
    {
        return [
            'cb' => '<input type="checkbox" />',
            'agreement_number' => __('رقم العقد', 'olama-registration'),
            'payer' => __('الجهة الدافعة', 'olama-registration'),
            'participant' => __('الطلاب المشتركين', 'olama-registration'),
            'activity_type' => __('طبيعة العقد', 'olama-registration'),
            'status' => __('الحالة', 'olama-registration'),
            'invoices' => __('الفواتير المرتبطة', 'olama-registration'),
            'total_amount' => __('اجمالي العقد', 'olama-registration'),
            'collected_amount' => __('المبلغ المحصل', 'olama-registration'),
            'remaining_amount' => __('المبلغ المتبقي', 'olama-registration'),
            'created_at' => __('التاريخ', 'olama-registration'),
        ];
    }

    public function get_sortable_columns()
    {
        return [
            'agreement_number' => ['agreement_number', false],
            'total_amount' => ['total_amount', false],
            'created_at' => ['created_at', true],
        ];
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'activity_type':
            case 'status':
                return esc_html($item->$column_name);
            case 'total_amount':
                return number_format((float) $item->total_amount, 3) . ' JD';
            case 'created_at':
                return date_i18n(get_option('date_format'), strtotime($item->created_at));
            default:
                return '';
        }
    }

    protected function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="agreement_id[]" value="%d" />',
            $item->id
        );
    }

    protected function column_agreement_number($item)
    {
        global $wpdb;
        $edit_url = admin_url('admin.php?page=olama-registration-agreements&action=edit&id=' . $item->id);
        $print_url = admin_url('admin.php?page=olama-registration-agreements&action=print&id=' . $item->id);

        $actions = [
            'edit' => sprintf('<a href="%s">%s</a>', esc_url($edit_url), __('تعديل', 'olama-registration')),
            'print' => sprintf('<a href="%s" target="_blank">%s</a>', esc_url($print_url), __('طباعة', 'olama-registration')),
        ];

        if ($item->status !== 'cancelled') {
            $cancel_url = admin_url('admin.php?page=olama-registration-agreements&action=cancel&id=' . $item->id);
            $actions['cancel'] = sprintf(
                '<a href="%s" style="color:#d63638;" onclick="return confirm(\'%s\');">%s</a>',
                esc_url($cancel_url),
                esc_attr__('هل أنت متأكد من إلغاء وحذف هذا العقد؟', 'olama-registration'),
                __('إلغاء', 'olama-registration')
            );
        }

        return sprintf(
            '<strong><a href="%s">%s</a></strong>%s',
            esc_url($edit_url),
            esc_html($item->agreement_number),
            $this->row_actions($actions)
        );
    }

    protected function column_payer($item)
    {
        $type_label = $item->payer_type === 'family' ? __('عائلة', 'olama-registration') : __('عميل', 'olama-registration');
        return sprintf(
            '%s<br><small class="text-muted">%s</small>',
            esc_html($item->payer_name),
            esc_html($type_label)
        );
    }

    protected function column_participant($item)
    {
        $type_label = $item->participant_type === 'student' ? __('طالب', 'olama-registration') : __('طفل', 'olama-registration');
        return sprintf(
            '%s<br><small class="text-muted">%s</small>',
            esc_html($item->participant_name),
            esc_html($type_label)
        );
    }

    protected function column_status($item)
    {
        $status = $item->status;
        $badge_class = 'badge-secondary';
        $label = $status;

        switch ($status) {
            case 'completed':
                $badge_class = 'badge-primary';
                $label = __('مكتمل', 'olama-registration');
                break;
            case 'cancelled':
                $badge_class = 'badge-danger';
                $label = __('ملغي', 'olama-registration');
                break;
            case 'draft':
            default:
                $badge_class = 'badge-warning';
                $label = __('مسودة', 'olama-registration');
                break;
        }

        return sprintf('<span class="os-badge %s">%s</span>', esc_attr($badge_class), esc_html($label));
    }

    protected function column_invoices($item)
    {
        global $wpdb;
        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT id, invoice_number FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d",
            $item->id
        ));
        if (empty($invoices)) {
            return '-';
        }
        $links = [];
        foreach ($invoices as $inv) {
            $url = admin_url('admin.php?page=olama-registration-invoices&action=view&id=' . (int) $inv->id);
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html($inv->invoice_number) . '</a>';
        }
        return implode('<br>', $links);
    }

    protected function column_collected_amount($item)
    {
        global $wpdb;
        $collected = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount_paid) FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d AND status != 'cancelled'",
            $item->id
        ));
        return '<span style="color:#16a34a; font-weight:700;">' . number_format($collected, 3) . ' JD</span>';
    }

    protected function column_remaining_amount($item)
    {
        global $wpdb;
        $collected = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount_paid) FROM {$wpdb->prefix}olama_invoices WHERE agreement_id = %d AND status != 'cancelled'",
            $item->id
        ));
        $total = (float) $item->total_amount;
        $remaining = max(0, $total - $collected);
        $color = $remaining > 0 ? '#e8920a' : '#16a34a';
        return '<span style="color:' . $color . '; font-weight:700;">' . number_format($remaining, 3) . ' JD</span>';
    }

    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = [$columns, $hidden, $sortable];

        $args = [];
        if (!empty($_REQUEST['status'])) {
            $args['status'] = sanitize_text_field($_REQUEST['status']);
        }
        if (!empty($_REQUEST['activity_type'])) {
            $args['activity_type'] = sanitize_text_field($_REQUEST['activity_type']);
        }

        $data = Olama_Reg_Agreement::get_list($args);

        // Usort if ordered
        $orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'id';
        $order = !empty($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'desc';

        usort($data, function ($a, $b) use ($orderby, $order) {
            $result = strnatcmp((string) $a->$orderby, (string) $b->$orderby);
            return $order === 'asc' ? $result : -$result;
        });

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($data);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page)
        ]);

        $this->items = array_slice($data, (($current_page - 1) * $per_page), $per_page);
    }
}
