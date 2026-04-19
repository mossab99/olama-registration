<?php
/**
 * Admin — menu registration, asset enqueue, page routing
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Olama_Reg_Admin {

    public function __construct() {
        add_action( 'admin_menu',             [ $this, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts',  [ $this, 'enqueue_assets' ] );
    }

    // ── Menu ──────────────────────────────────────────────────────────────────

    public function register_menu(): void {

        add_menu_page(
            __( 'Olama Registration', 'olama-registration' ),
            __( 'التسجيل', 'olama-registration' ),
            'manage_options',
            'olama-registration',
            [ $this, 'render_families' ],
            'dashicons-id-alt',
            26
        );

        add_submenu_page(
            'olama-registration',
            __( 'Families', 'olama-registration' ),
            __( 'العائلات', 'olama-registration' ),
            'manage_options',
            'olama-registration',
            [ $this, 'render_families' ]
        );

        add_submenu_page(
            'olama-registration',
            __( 'Students', 'olama-registration' ),
            __( 'الطلاب', 'olama-registration' ),
            'manage_options',
            'olama-registration-students',
            [ $this, 'render_students' ]
        );
    }

    // ── Page Renderers ────────────────────────────────────────────────────────

    public function render_families(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Unauthorized', 'olama-registration' ) );

        // Print action — output the print view and exit
        $action     = sanitize_text_field( $_GET['action'] ?? '' );
        $family_uid = sanitize_text_field( $_GET['family_uid'] ?? '' );

        if ( $action === 'print' && $family_uid ) {
            include OLAMA_REG_PATH . 'admin/views/partial-print-card.php';
            return;
        }

        include OLAMA_REG_PATH . 'admin/views/page-family-list.php';
    }

    public function render_students(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( __( 'Unauthorized', 'olama-registration' ) );
        include OLAMA_REG_PATH . 'admin/views/page-student-list.php';
    }

    // ── Assets ────────────────────────────────────────────────────────────────

    public function enqueue_assets( string $hook ): void {

        $reg_pages = [ 'toplevel_page_olama-registration', 'registration_page_olama-registration-students' ];
        if ( ! in_array( $hook, $reg_pages, true ) ) return;

        // Select2
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0'
        );
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            [ 'jquery' ],
            '4.1.0',
            true
        );

        // WP datepicker (already bundled)
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style(
            'jquery-ui-theme',
            'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css',
            [],
            '1.13.2'
        );

        // WP Media Library uploader
        wp_enqueue_media();

        // Plugin CSS
        wp_enqueue_style(
            'olama-reg',
            OLAMA_REG_URL . 'assets/css/olama-reg.css',
            [],
            OLAMA_REG_VERSION
        );

        // Plugin JS
        wp_enqueue_script(
            'olama-reg',
            OLAMA_REG_URL . 'assets/js/olama-reg.js',
            [ 'jquery', 'select2', 'jquery-ui-datepicker' ],
            OLAMA_REG_VERSION,
            true
        );

        // Get academic years for dropdowns
        $academic_years = [];
        if ( class_exists( 'Olama_School_Academic' ) ) {
            $years = Olama_School_Academic::get_years();
            foreach ( (array) $years as $y ) {
                $academic_years[] = [ 'id' => $y->id, 'name' => $y->year_name ];
            }
        }

        wp_localize_script( 'olama-reg', 'olamaReg', [
            'ajaxurl'        => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'olama_reg_nonce' ),
            'pluginUrl'      => OLAMA_REG_URL,
            'academicYears'  => $academic_years,
            'strings'        => [
                'saving'          => __( 'جاري الحفظ...', 'olama-registration' ),
                'saved'           => __( 'تم الحفظ بنجاح.', 'olama-registration' ),
                'error'           => __( 'حدث خطأ. يرجى المحاولة مجدداً.', 'olama-registration' ),
                'confirmDelete'   => __( 'هل أنت متأكد من حذف هذا السجل؟', 'olama-registration' ),
                'confirmDeact'    => __( 'هل تريد إلغاء تفعيل هذه العائلة؟', 'olama-registration' ),
                'selectPhoto'     => __( 'اختر صورة', 'olama-registration' ),
                'addRow'          => __( 'إضافة سطر', 'olama-registration' ),
                'noStudents'      => __( 'لا يوجد طلاب مسجلين في هذه العائلة.', 'olama-registration' ),
                'blacklistWarn'   => __( 'يرجى إدخال سبب القائمة السوداء.', 'olama-registration' ),
                'maxStudents'     => __( 'وصل عدد الطلاب للحد الأقصى (99).', 'olama-registration' ),
                'studentAdded'    => __( 'تم إضافة الطالب بنجاح.', 'olama-registration' ),
                'familyCreated'   => __( 'تم إنشاء العائلة برقم: ', 'olama-registration' ),
            ],
        ] );
    }
}
