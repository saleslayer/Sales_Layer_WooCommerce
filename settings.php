<?php

    ini_set('display_errors', '0');
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_reporting(E_ALL);
    } else {
        error_reporting(E_ALL ^ E_NOTICE);
    }

    define('SLYR_WC_version', "2.6.1");
    define('SLYR_WC_latest_version', "");
    define('SLYR_WC_connector_type', 'CN_WOOCOMM');
    define('SLYR_WC_url_API', 'api.saleslayer.com/');

    global $wp_version;
    if (version_compare($wp_version,'4.5','>=')) {
        define('SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META', true);
    }else{
        define('SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META', false);
    }

    define('SLYR_WC_company_name', 'Sales Layer');
    define('SLYR_WC_name', SLYR_WC_company_name.' Woo');

    if (SLYR_WC_company_name == 'Sales Layer'){
        define('SLYR_WC_name_logo', 'logo_head_saleslayer.png');
        define('SLYR_WC_name_icon', 'icon_saleslayer.png');
    }else{
        define('SLYR_WC_name_logo', 'logo_head_connector.png');
        define('SLYR_WC_name_icon', 'icon_connector.png');
    }

    define('SLYR_WC_general_params', 'slyr_wooc_general_params');

    // Base table names (without prefix) - kept for reference
    define('SLYR_WC_connector_table_base', 'slyr_wc_api_config');
    define('SLYR_WC_syncdata_table_base', 'slyr_wc_api_syncdata');
    define('SLYR_WC_syncdata_flag_table_base', 'slyr_wc_api_syncdata_flag');
    define('SLYR_WC_multiconn_table_base', 'slyr_wc_api_multiconn');

    // Backward-compatible constants: now include the WordPress base prefix
    // These tables are GLOBAL (shared across all sites in Multisite)
    // so they use $wpdb->base_prefix (e.g. 'wp_') not $wpdb->prefix (e.g. 'wp_2_')
    global $wpdb;
    define('SLYR_WC_connector_table', $wpdb->base_prefix . SLYR_WC_connector_table_base);
    define('SLYR_WC_syncdata_table', $wpdb->base_prefix . SLYR_WC_syncdata_table_base);
    define('SLYR_WC_syncdata_flag_table', $wpdb->base_prefix . SLYR_WC_syncdata_flag_table_base);
    define('SLYR_WC_multiconn_table', $wpdb->base_prefix . SLYR_WC_multiconn_table_base);

    define('SLYR_WC_auto_sync_minutes_start', 15); //WC autosync cron start every (value) minutes
    define('SLYR_WC_auto_sync_minutes_interval', SLYR_WC_auto_sync_minutes_start.'min'); //WC autosync cron start every (value) minutes
    define('SLYR_WC_syncdata_minutes_start', 5); //WC syncdata cron start every (value) minutes
    define('SLYR_WC_syncdata_minutes_interval', SLYR_WC_syncdata_minutes_start.'min'); //WC syncdata cron start every (value) minutes
    define('SLYR_WC_media_meta_minutes_start', 5); //WC media meta cron start every (value) minutes
    define('SLYR_WC_media_meta_minutes_interval', SLYR_WC_media_meta_minutes_start.'min'); //WC media meta cron start every (value) minutes
    define('SLYR_WC_check_version_days_start', 'daily'); //WC check version cron start every (value) days

    define('SLYR_ANALYTICS_PUBLIC_KEY', 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0NCk1JSUNJakFOQmdrcWhraUc5dzBCQVFFRkFBT0NBZzhBTUlJQ0NnS0NBZ0VBdXlpd2RZdFdFZksrWmdzSkV4d0INCjM5QXVSKzZhYVNqZEVTWFhUbDN6Ty9GeFF0cnJZWlNEVzVqdTdCZ1lxOFpzWitBTWRsQWUvNmd3YkJQOXBWRFYNCnVEUzRDS3Z3Tmp4OEFYdno5SjRQMW8xVTJuSUJ2aVl4aUE2aW1hak5TTkdRVExLMVgreUFoMHA2cEpuT25QU1ANCkJsNVdFQkpIbGVaSTBZbzJPRS9jS2FGeEJoQTlWV0VYUS83K0VEOVVkMVhzQWZLdjcrQmVwTThkc05zZkVCb0gNCkhTVGZwWVIvTnh3M3VkZ29zSWkrcGFzdkpQN1JSMEdyT1FkOGNlUkhOblhJNjlqL0N0WDd3YXZESFl3N0pCZzkNCjE3TmlpVFhka1hGRk1wSzkrdEFJQlJZb0NRUU93L2JsOWY2UE1MazN2Q05ITzdES1VrUmZPc2pNVkdZSUJ0MEYNCnpmMEZDUURvMnZxODVHeVpRejNnbklWUVcxNjNmdW1ZMWo4ZDhIVzBnWUFUUmN5OTU5VmJlZFdNcTRZY1BuZjINCk9uOTMyeHVmRlArSm5lanZnUW5BdjBIM1RWMWxrbkt1N3lvNjhHcVlIbzBYdU1XT2FGaWdaUjFNUjRmMGVBZ3ENCllwcVdGN2VCUzJTYTBySkRRd3NLMWVxVTVjakpjWnE3VmdLbi9wNjFCam9hTWFHQVJycGFBQlM3ZGczRWptSnANCllIbUQ0MnM2V3JocmRBM0VZZXNuZ3NtdGg4MVZTNkxCcDdVUm1VUGpGRStuL0tMMFBHSHZVQ0NrY1pua2Y1TVANCmtNb1NkOXZWZmdiVjhSMmtpMDM4RFNNcGRyREorZHRHeU1FZEsrWGpnaTVZN0JnSkFuejZzYkJBWjRTWWVwTysNClFnQ1hvYUZIRllxY0dhMUJES3lqREUwQ0F3RUFBUT09DQotLS0tLUVORCBQVUJMSUMgS0VZLS0tLS0=');

    // Avoids wordpress to ask for credentials when testing on localhost
    if (!defined('FS_METHOD')) define('FS_METHOD', 'direct');

    /**
     * Get the current WordPress table prefix (dynamic, changes with switch_to_blog()).
     *
     * In Multisite: returns site-specific prefix (e.g. 'wp_2_' for site 2).
     * In Single-site: returns 'wp_' (or whatever is configured).
     *
     * @return string Current WordPress table prefix
     */
    function slyr_get_wpdb_prefix(): string
    {
        global $wpdb;
        return $wpdb->prefix;
    }

    // Backward compatibility: keep WPDB_PREFIX constant for any external code
    // that might reference it, but new code should use slyr_get_wpdb_prefix()
    if (!defined('WPDB_PREFIX')) {
        define('WPDB_PREFIX', $wpdb->prefix);
    }

    $debug_level = 0;

    if (!defined('SLYR_WC__PLUGIN_DIR')) define('SLYR_WC__PLUGIN_DIR', plugin_dir_path(__FILE__));
    if (!defined('SLYR_WC__IMAGES_DIR')) define('SLYR_WC__IMAGES_DIR', plugin_dir_url(__FILE__).'images/');
    if (!defined('SLYR_WC__LOGS_DIR')) define('SLYR_WC__LOGS_DIR', SLYR_WC__PLUGIN_DIR.'logs/');

    if (!is_dir(SLYR_WC__LOGS_DIR)){
        if (function_exists('wp_mkdir_p')){
            wp_mkdir_p(SLYR_WC__LOGS_DIR);
        }else{
            mkdir(SLYR_WC__LOGS_DIR, 0777, true);
        }
    }

    /**
     * Detect the current operation mode of the plugin.
     *
     * Returns 'multisite' when WordPress Multisite is active AND the plugin
     * is network-activated. Returns 'single' otherwise (including when
     * Multisite is active but plugin is activated per-site, which should
     * be blocked by the activation hook but we handle it defensively).
     *
     * @return string 'single' or 'multisite'
     */
    function slyr_get_operation_mode(): string
    {
        if (!function_exists('is_multisite') || !is_multisite()) {
            return 'single';
        }

        // Check if this plugin is network-activated
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // SLYR_WC__PLUGIN_FILE points to the main plugin file
        // (saleslayer_woocommerce.php), which is the basename WordPress
        // stores in active_sitewide_plugins. Using __FILE__ here would
        // resolve to settings.php and never match.
        $pluginFile = defined('SLYR_WC__PLUGIN_FILE') ? SLYR_WC__PLUGIN_FILE : __FILE__;

        if (is_plugin_active_for_network(plugin_basename($pluginFile))) {
            return 'multisite';
        }

        return 'single';
    }

    /**
     * Check if the plugin is running in Multisite mode.
     *
     * Convenience wrapper around slyr_get_operation_mode().
     *
     * @return bool True if running in multisite mode
     */
    function slyr_is_multisite_mode(): bool
    {
        return slyr_get_operation_mode() === 'multisite';
    }

    // Constructs plugin dirname:
    if (function_exists('plugin_dir_path')) {
        $dirname=explode('/', str_replace('\\', '/', plugin_dir_path( __FILE__ )));
        define('SLYR_WC_PLUGIN_NAME_DIR', $dirname[count($dirname)-2]);
    }


    /**
     * Get multisite sync targets from a connector's configuration.
     *
     * Reads the 'multisite_targets' array from the connector's conn_extra
     * JSON column and returns only the active targets.
     *
     * @param array $connector Connector row data from slyr_wc_api_config
     * @return array Array of active targets with blog_id and lang_code keys.
     *               Empty array only if conn_extra has no targets configured.
     */
    function slyr_get_multisite_targets(array $connector): array
    {
        $connExtra = json_decode($connector['conn_extra'] ?? '{}', true);

        $targets = $connExtra['multisite_targets'] ?? [];

        if (empty($targets)) {
            return [];
        }

        // All targets in the array are active by definition (only active ones are stored)
        return $targets;
    }

    /**
     * Build sync target entries for a sync item's sync_params column.
     *
     * Each target entry tracks synchronization state per site:
     *   - blog_id:   WordPress site ID
     *   - lang_code: SalesLayer language code mapped to this site
     *   - synced:    whether this target has been synced (bool)
     *   - error:     error message if sync failed, null otherwise
     *
     * Works in both single-site and multisite modes. In single-site
     * the connector has one target (blog_id 1). Returns an empty array
     * only for legacy connectors without configured targets.
     *
     * @param array $connector Connector configuration row
     * @return array Targets array for sync_params JSON column
     */
    function slyr_build_sync_targets(array $connector): array
    {
        $multisiteTargets = slyr_get_multisite_targets($connector);

        if (empty($multisiteTargets)) {
            return [];
        }

        return array_map(fn($target) => [
            'blog_id'   => (int) $target['blog_id'],
            'lang_code' => $target['lang_code'] ?? '',
            'synced'    => false,
            'error'     => null,
        ], $multisiteTargets);
    }
