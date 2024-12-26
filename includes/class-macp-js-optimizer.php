<?php
class MACP_JS_Optimizer {
    private $excluded_scripts = [];
    private $deferred_scripts = [];
    private $delayed_scripts = [];
    private $admin_paths = [
        'wp-admin',
        'wp-login.php',
        'admin-ajax.php'
    ];

    public function __construct() {
        $this->excluded_scripts = get_option('macp_excluded_scripts', []);
        $this->deferred_scripts = get_option('macp_deferred_scripts', [
            'jquery-core',
            'jquery-migrate'
        ]);
        
        add_filter('script_loader_tag', [$this, 'process_script_tag'], 10, 3);
        add_action('wp_footer', [$this, 'add_delay_script'], 99);
    }

    public function process_script_tag($tag, $handle, $src) {
        // Skip processing for admin pages
        if ($this->is_admin_page()) {
            return $tag;
        }

        // Skip excluded pages
        if ($this->is_excluded_page()) {
            return $tag;
        }

        // Check if script should be excluded
        if ($this->is_script_excluded($src)) {
            return $tag;
        }

        // Apply defer if enabled
        if (get_option('macp_enable_js_defer', 0) && in_array($handle, $this->deferred_scripts)) {
            return str_replace(' src', ' defer src', $tag);
        }

        // Apply delay if enabled
        if (get_option('macp_enable_js_delay', 0)) {
            if (strpos($tag, 'type="text/javascript"')) {
                $tag = str_replace('type="text/javascript"', 'type="rocketlazyloadscript"', $tag);
                $tag = str_replace(' src=', ' data-rocket-src=', $tag);
            }
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

    private function is_excluded_page() {
        $excluded_pages = [
            'checkout',
            'cart',
            'my-account'
        ];

        foreach ($excluded_pages as $page) {
            if (is_page($page)) {
                return true;
            }
        }
        return false;
    }

    private function is_script_excluded($src) {
        foreach ($this->excluded_scripts as $excluded_script) {
            if (strpos($src, $excluded_script) !== false) {
                return true;
            }
        }
        return false;
    }

    public function add_delay_script() {
        if (!get_option('macp_enable_js_delay', 0)) {
            return;
        }
        ?>
        <script>
            class RocketLazyLoadScripts {
                constructor() {
                    this.triggerEvents = ["keydown", "mousedown", "mousemove", "touchmove", "touchstart", "touchend", "wheel"];
                    this.userEventHandler = this._triggerListener.bind(this);
                    this.touchStartHandler = this._onTouchStart.bind(this);
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

                _onTouchStart(t) {
                    "HTML" !== t.target.tagName && (window.addEventListener("touchend", this.touchEndHandler), 
                    window.addEventListener("mouseup", this.touchEndHandler), 
                    window.addEventListener("touchmove", this.touchMoveHandler, {passive: !0}),
                    window.addEventListener("mousemove", this.touchMoveHandler), 
                    t.target.addEventListener("click", this.clickHandler), 
                    this._pendingClickStarted())
                }
            }
            RocketLazyLoadScripts.run();
        </script>
        <?php
    }
}