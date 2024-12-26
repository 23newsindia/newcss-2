<?php
class MACP_JS_Optimizer {
    private $excluded_scripts = [];
    private $deferred_scripts = [];
    private $admin_paths = [
        'wp-admin',
        'wp-login.php',
        'admin-ajax.php'
    ];

    public function __construct() {
        add_action('init', [$this, 'initialize_settings'], 5);
    }

    public function initialize_settings() {
        // Initialize settings
        $this->excluded_scripts = array_filter(array_map('trim', (array)get_option('macp_excluded_scripts', [])));
        $this->deferred_scripts = array_filter(array_map('trim', (array)get_option('macp_deferred_scripts', [
            'jquery-core',
            'jquery-migrate'
        ])));
        
        // Add filters only if optimization is enabled
        if (get_option('macp_enable_js_defer', 0)) {
            add_filter('script_loader_tag', [$this, 'process_script_tag'], 10, 3);
        }

        // Add delay script only if delay is enabled
        if (get_option('macp_enable_js_delay', 0)) {
            add_filter('script_loader_tag', [$this, 'process_script_tag'], 10, 3);
            add_action('wp_footer', [$this, 'add_delay_script'], 99999);
        }
    }


    public function process_script_tag($tag, $handle, $src) {
        // Skip processing for admin pages
        if ($this->is_admin_page()) {
            return $tag;
        }

        // Skip excluded scripts
        if ($this->is_script_excluded($src)) {
            return $tag;
        }

        // Apply defer if enabled and script is in deferred list
        if (get_option('macp_enable_js_defer', 0) && in_array($handle, $this->deferred_scripts)) {
            if (strpos($tag, 'defer') === false) {
                $tag = str_replace(' src=', ' defer src=', $tag);
            }
            return $tag;
        }

        // Apply delay if enabled and script is not deferred
        if (get_option('macp_enable_js_delay', 0) && !in_array($handle, $this->deferred_scripts)) {
            if (strpos($tag, 'type="text/javascript"') !== false) {
                $tag = str_replace('type="text/javascript"', 'type="rocketlazyloadscript"', $tag);
            } else {
                $tag = str_replace('<script', '<script type="rocketlazyloadscript"', $tag);
            }
            $tag = str_replace(' src=', ' data-rocket-src=', $tag);
            return $tag;
        }

        return $tag;
    }

    private function is_admin_page() {
        foreach ($this->admin_paths as $path) {
            if (strpos($_SERVER['REQUEST_URI'], $path) !== false) {
                return true;
            }
        }
        return false;
    }

    private function is_script_excluded($src) {
        foreach ($this->excluded_scripts as $excluded_script) {
            if (!empty($excluded_script) && strpos($src, $excluded_script) !== false) {
                return true;
            }
        }
        return false;
    }

    public function add_delay_script() {
        ?>
<script type="text/javascript">
class RocketLazyLoadScripts {
    constructor() {
        this.triggerEvents = ["keydown", "mousedown", "mousemove", "touchmove", "touchstart", "touchend", "wheel"];
        this.userEventHandler = this._triggerListener.bind(this);
        this._addEventListener(this);
    }

    _addEventListener(t) {
        this.triggerEvents.forEach(e => window.addEventListener(e, t.userEventHandler, {passive: !0}));
    }

    _triggerListener() {
        this._removeEventListener(this);
        this._loadEverythingNow();
    }

    _removeEventListener(t) {
        this.triggerEvents.forEach(e => window.removeEventListener(e, t.userEventHandler, {passive: !0}));
    }

    _loadEverythingNow() {
        document.querySelectorAll("script[type=rocketlazyloadscript]").forEach(t => {
            t.setAttribute("type", "text/javascript");
            let src = t.getAttribute("data-rocket-src");
            if (src) {
                t.removeAttribute("data-rocket-src");
                t.setAttribute("src", src);
            }
        });
    }
}

window.addEventListener('DOMContentLoaded', function() {
    new RocketLazyLoadScripts();
});
</script>
        <?php
    }
}