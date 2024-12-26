<?php
/**
 * Handles admin settings operations
 */
class MACP_Admin_Settings {
    private $default_settings = [
        'macp_enable_redis' => 1,
        'macp_enable_html_cache' => 1,
        'macp_enable_gzip' => 1,
        'macp_minify_html' => 0,
        'macp_minify_css' => 0,
        'macp_minify_js' => 0,
        'macp_remove_unused_css' => 0,
        'macp_process_external_css' => 0,
        'macp_enable_js_defer' => 0,
        'macp_enable_js_delay' => 0
    ];

    public function get_all_settings() {
        $settings = [];
        foreach ($this->default_settings as $key => $default) {
            $settings[str_replace('macp_', '', $key)] = get_option($key, $default);
        }
        return $settings;
    }

    public function handle_settings_save() {
        check_admin_referer('macp_save_settings_nonce');
        
        foreach ($this->default_settings as $option => $_) {
            update_option($option, isset($_POST[$option]) ? 1 : 0);
        }

        $this->save_script_settings();
        $this->save_css_settings();
    }

    private function save_script_settings() {
        // Save JavaScript exclusions
        if (isset($_POST['macp_excluded_scripts'])) {
            $excluded = array_filter(array_map('trim', explode("\n", $_POST['macp_excluded_scripts'])));
            update_option('macp_excluded_scripts', $excluded);
        }

        if (isset($_POST['macp_deferred_scripts'])) {
            $deferred = array_filter(array_map('trim', explode("\n", $_POST['macp_deferred_scripts'])));
            update_option('macp_deferred_scripts', $deferred);
        }
    }

    private function save_css_settings() {
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

    public function ajax_save_js_setting() {
        check_ajax_referer('macp_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $option = sanitize_key($_POST['option']);
        $value = (int)$_POST['value'];

        update_option($option, $value);

        // Trigger settings updated action
        do_action('macp_js_settings_updated');

        wp_send_json_success([
            'message' => 'Setting saved successfully'
        ]);
    }
}