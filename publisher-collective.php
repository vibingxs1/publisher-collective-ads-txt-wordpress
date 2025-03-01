<?php

declare(strict_types=1);

/*
Plugin Name: Publisher Collective Ads.Txt
Plugin URI: https://github.com/PathfinderMediaGroup/publisher-collective-ads-txt-wordpress
Description: Installs and frequently updates the ads.txt file for Publisher Collective websites
Version: 2.0.0
Requires PHP: 8.0
Author: Woeler
Author URI: https://www.pathfindermediagroup.com
License: GPL-3
*/

define('PUB_COL_PLUGIN_DIR', plugin_dir_path(__FILE__));
defined('ABSPATH') || exit;

require_once PUB_COL_PLUGIN_DIR . 'assets/php/settings/PublisherCollectiveSettings.php';

add_action('plugins_loaded', 'PublisherCollective::setup');

final class PublisherCollective
{
    public const ADS_TXT_URL_PREFIX = 'https://kumo.network-n.com/adstxt/?domain=';

    public static function setup(): void
    {
        $self = new self();
        (new PublisherCollectiveSettings())->init();
        add_action('wp', [$self, 'pc_cronstarter_activation']);
        add_action('fetch-publisher-collective-ads-txt', [$self, 'fetch_ads_txt']);
        add_filter('query_vars', [$self, 'display_pc_ads_txt']);
    }

    public static function pc_cronstarter_activation(): void
    {
        if (!wp_next_scheduled('fetch-publisher-collective-ads-txt')) {
            wp_schedule_event(time(), 'daily', 'fetch-publisher-collective-ads-txt');
        }
    }

    public static function fetch_ads_txt(): void
    {
        self::get_ads_txt_content_or_cache(true);
    }

    public static function pc_cronstarter_deactivate(): void
    {
        $timestamp = wp_next_scheduled('fetch-publisher-collective-ads-txt');
        wp_unschedule_event($timestamp, 'fetch-publisher-collective-ads-txt');
    }

    public static function pc_cronstarter_activate(): void
    {
        self::get_ads_txt_content_or_cache(true);
    }

    public static function display_pc_ads_txt(array $query_vars): array
    {
        $request = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : false;
        if ('/ads.txt' === $request) {
            header('Content-Type: text/plain');
            echo esc_html(apply_filters('ads_txt_content', self::get_ads_txt_content_or_cache()));
            die();
        }

        return $query_vars;
    }

    private static function getServerName(): ?string
    {
        return self::getDomain()
            ?? $_SERVER['SERVER_NAME']
            ?? $_SERVER['HTTP_HOST']
            ?? null;
    }

    private static function getDomain(): ?string
    {
        if (!empty(get_home_url())) {
            return rtrim(str_replace(['https://', 'http://', 'www.'], '', get_home_url()), '/');
        }

        return null;
    }

    public static function get_ads_txt_content_or_cache(bool $renew = false): mixed
    {
        $data = get_transient('publisher_collective_ads_txt');
        if (empty($data) || $renew) {
            $serverName = self::getServerName();
            $data = wp_remote_retrieve_body(wp_remote_get(
                $serverName ? (self::ADS_TXT_URL_PREFIX . $serverName) : self::ADS_TXT_URL_PREFIX
            ));
            if ($data !== false) {
                set_transient('publisher_collective_ads_txt', $data, 86400);
            }
        }
        if (!empty($data)) {
            $adsTxtExtraParams = get_option('pc-ads-txt-extra-params', null);
            $data .= PHP_EOL.$adsTxtExtraParams;
        }
        return $data;
    }
}

register_deactivation_hook(__FILE__, ['PublisherCollective', 'pc_cronstarter_deactivate']);
register_activation_hook(__FILE__, ['PublisherCollective', 'pc_cronstarter_activate']);
