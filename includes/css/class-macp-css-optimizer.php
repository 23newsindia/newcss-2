<?php
require_once MACP_PLUGIN_DIR . 'includes/css/class-macp-css-config.php';
require_once MACP_PLUGIN_DIR . 'includes/css/class-macp-css-extractor.php';

class MACP_CSS_Optimizer {
    private $cache_dir;

    public function __construct() {
        $this->cache_dir = WP_CONTENT_DIR . '/cache/macp/css/';
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
        }
    }

    public function optimize_css($html) {
        if (!get_option('macp_remove_unused_css', 0)) {
            return $html;
        }

        MACP_Debug::log("Starting CSS optimization");

        $css_files = MACP_CSS_Extractor::extract_css_files($html);
        $inline_styles = MACP_CSS_Extractor::extract_inline_styles($html);

        $all_css = '';
        $processed_files = [];

        foreach ($css_files as $css_url) {
            if ($this->should_process_css($css_url)) {
                $css_content = MACP_CSS_Extractor::get_css_content($css_url);
                if ($css_content) {
                    $all_css .= $css_content . "\n";
                    $processed_files[] = $css_url;
                }
            }
        }

        foreach ($inline_styles as $style) {
            $all_css .= $style . "\n";
        }

        $config = [
            'content' => [['raw' => $html, 'extension' => 'html']],
            'css' => [['raw' => $all_css]],
            'safelist' => MACP_CSS_Config::get_safelist()
        ];

        return $this->process_css($html, $config, $processed_files);
    }

    private function should_process_css($url) {
        if (!get_option('macp_process_external_css', 0) && !MACP_CSS_Extractor::is_local_url($url)) {
            return false;
        }

        foreach (MACP_CSS_Config::get_excluded_patterns() as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        return true;
    }

    private function process_css($html, $config, $processed_files) {
        try {
            $purgecss = new Purgecss($config);
            $purged_css = $purgecss->purge()[0]->css;
            
            $minifier = new MatthiasMullie\Minify\CSS($purged_css);
            $purged_css = $minifier->minify();

            $optimized_file = $this->cache_dir . 'optimized_' . md5($purged_css) . '.css';
            file_put_contents($optimized_file, $purged_css);

            $optimized_url = str_replace(WP_CONTENT_DIR, content_url(), $optimized_file);
            
            foreach ($processed_files as $original_file) {
                $html = str_replace($original_file, $optimized_url, $html);
            }

            $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
            $html = str_replace('</head>', "<link rel='stylesheet' href='{$optimized_url}' />\n</head>", $html);

            MACP_Debug::log("CSS optimization completed successfully");
            return $html;
        } catch (Exception $e) {
            MACP_Debug::log("CSS optimization error: " . $e->getMessage());
            return $html;
        }
    }

    public function clear_css_cache() {
        array_map('unlink', glob($this->cache_dir . '*'));
        MACP_Debug::log("CSS cache cleared");
    }
}