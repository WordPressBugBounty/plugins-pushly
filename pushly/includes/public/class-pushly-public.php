<?php

if (!defined('ABSPATH')) {
    exit;
}

class Pushly_Public
{
    /**
     * @var Pushly_Public
     */
    private static $_instance;

    public static function instance()
    {
        if (empty(self::$_instance)) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_head', [$this, 'insert_header'], 10);
        add_filter('script_loader_tag', [$this, 'async_enqueue'], 10, 2);
    }

    public function insert_header()
    {
        $sdk_key = null;

        $current_options = get_option('pushly');
        if (!empty($current_options['sdk_key'])) {
            $sdk_key = $current_options['sdk_key'];
            $initialization_disabled = !empty($current_options['initialization_disabled']);
        } else {
            // plugin v1 backwards compat
            $initialization_disabled = false;
            $legacy_options = get_option('pushly_options');
            if (!empty($legacy_options['domain_key'])) {
                $sdk_key = $current_options['domain_key'];
            }
        }

        if ($sdk_key && !$initialization_disabled) {
            wp_enqueue_script('pushly-sdk', 'https://' . PUSHLY__SDK_DOMAIN . '/pushly-sdk.min.js?domain_key=' . rawurlencode($sdk_key), [], false, true);
            require_once PUSHLY__DIR . '/includes/public/views/sdk.php';
            echo build_sdk($sdk_key, PUSHLY__PLUGIN_DIR);
        }
    }

    public function async_enqueue($tag, $handle)
    {
        if ('pushly-sdk' === $handle && false === strpos($tag, 'async')) {
            return str_replace('<script ', '<script async ', $tag);
        }

        return $tag;
    }
}
