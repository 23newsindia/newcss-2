<?php
class MACP_Admin {
    private $redis;

    public function __construct($redis) {
        $this->redis = $redis;
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Cache Settings',
            'Cache Settings',
            'manage_options',
            'macp-settings',
            [$this, 'render_settings_page'],
            'dashicons-performance',
            100
        );

        add_submenu_page(
            'macp-settings',
            'Debug Information',
            'Debug Info',
            'manage_options',
            'macp-debug',
            [$this, 'render_debug_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'macp-') === false) {
            return;
        }

        wp_enqueue_style(
            'macp-admin',
            plugins_url('assets/css/admin.css', MACP_PLUGIN_FILE)
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        if (isset($_POST['macp_save_settings'])) {
            check_admin_referer('macp_save_settings_nonce');
            
            $options = [
                'macp_enable_redis',
                'macp_enable_html_cache',
                'macp_enable_gzip',
                'macp_minify_html',
                'macp_minify_css',
                'macp_minify_js',
                'macp_remove_unused_css',
                'macp_process_external_css',
                'macp_enable_js_defer',
                'macp_enable_js_delay'
            ];

            foreach ($options as $option) {
                update_option($option, isset($_POST[$option]) ? 1 : 0);
            }

            // Save JavaScript exclusions
            if (isset($_POST['macp_excluded_scripts'])) {
                $excluded = array_filter(array_map('trim', explode("\n", $_POST['macp_excluded_scripts'])));
                update_option('macp_excluded_scripts', $excluded);
            }

            if (isset($_POST['macp_deferred_scripts'])) {
                $deferred = array_filter(array_map('trim', explode("\n", $_POST['macp_deferred_scripts'])));
                update_option('macp_deferred_scripts', $deferred);
            }

            // Save CSS exclusions
            if (isset($_POST['macp_css_safelist'])) {
                $safelist = array_filter(array_map('trim', explode("\n", $_POST['macp_css_safelist'])));
                MACP_CSS_Config::save_safelist($safelist);
            }

            if (isset($_POST['macp_css_excluded_patterns'])) {
                $patterns = array_filter(array_map('trim', explode("\n", $_POST['macp_css_excluded_patterns'])));
                MACP_CSS_Config::save_excluded_patterns($patterns);
            }
        }

        $settings = [
            'redis' => get_option('macp_enable_redis', 1),
            'html_cache' => get_option('macp_enable_html_cache', 1),
            'gzip' => get_option('macp_enable_gzip', 1),
            'minify_html' => get_option('macp_minify_html', 0),
            'minify_css' => get_option('macp_minify_css', 0),
            'minify_js' => get_option('macp_minify_js', 0),
            'remove_unused_css' => get_option('macp_remove_unused_css', 0),
            'process_external_css' => get_option('macp_process_external_css', 0),
            'enable_js_defer' => get_option('macp_enable_js_defer', 0),
            'enable_js_delay' => get_option('macp_enable_js_delay', 0)
        ];

        include MACP_PLUGIN_DIR . 'templates/admin-page.php';
        include MACP_PLUGIN_DIR . 'templates/css-exclusions.php';
        include MACP_PLUGIN_DIR . 'templates/js-optimization.php';
    }

    public function render_debug_page() {
        if (!current_user_can('manage_options')) return;
        
        require_once MACP_PLUGIN_DIR . 'includes/class-macp-debug-utility.php';
        $status = MACP_Debug_Utility::check_plugin_status();
        
        include MACP_PLUGIN_DIR . 'templates/debug-page.php';
    }
}