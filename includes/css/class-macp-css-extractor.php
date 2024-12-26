<?php
class MACP_CSS_Extractor {
    public static function extract_css_files($html) {
        preg_match_all('/<link[^>]+href=[\'"]([^\'"]+)[\'"][^>]*>/i', $html, $matches);
        return $matches[1];
    }

    public static function extract_inline_styles($html) {
        preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches);
        return $matches[1];
    }

    public static function get_css_content($url) {
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        }

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            MACP_Debug::log("Failed to fetch CSS from: " . $url);
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    public static function is_local_url($url) {
        $site_url = parse_url(get_site_url(), PHP_URL_HOST);
        $css_host = parse_url($url, PHP_URL_HOST);
        return empty($css_host) || $css_host === $site_url;
    }
}