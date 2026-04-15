<?php

if (!defined('ABSPATH')) {
    exit;
}

class VWGK_Hardening
{
    public static function init(): void
    {
        add_action('send_headers', [__CLASS__, 'send_noindex_headers']);
        add_action('template_redirect', [__CLASS__, 'force_login_only']);
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 10, 3);
        add_filter('xmlrpc_enabled', '__return_false');

        add_action('do_feed', [__CLASS__, 'disable_feed'], 1);
        add_action('do_feed_rdf', [__CLASS__, 'disable_feed'], 1);
        add_action('do_feed_rss', [__CLASS__, 'disable_feed'], 1);
        add_action('do_feed_rss2', [__CLASS__, 'disable_feed'], 1);
        add_action('do_feed_atom', [__CLASS__, 'disable_feed'], 1);
    }

    public static function send_noindex_headers(): void
    {
        if (get_option('vwgk_noindex_mode', '1') === '1') {
            header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        }
    }

    public static function force_login_only(): void
    {
        if (is_admin()) {
            return;
        }

        if (get_option('vwgk_login_only_mode', '1') !== '1') {
            return;
        }

        $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $is_login_request = strpos($request_uri, 'wp-login.php') !== false;
        $is_rest_request = defined('REST_REQUEST') && REST_REQUEST;

        if (!is_user_logged_in() && !$is_login_request && !$is_rest_request) {
            wp_safe_redirect(wp_login_url());
            exit;
        }
    }

    public static function login_redirect($redirect_to, $requested_redirect_to, $user): string
    {
        return admin_url();
    }

    public static function disable_feed(): void
    {
        wp_die(__('Feed disabilitati su questo gateway.', 'virtual-world-gate-key'));
    }
}
