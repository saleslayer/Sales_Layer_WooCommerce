<?php 

    ini_set('display_errors', '0');
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_reporting(E_ALL);
    } else {
        error_reporting(E_ALL ^ E_NOTICE);
    }
    
    define('SLYR_WC_version', "2.5.3");
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

    define('SLYR_WC_connector_table', 'slyr_wc_api_config');
    define('SLYR_WC_syncdata_table', 'slyr_wc_api_syncdata');
    define('SLYR_WC_syncdata_flag_table', 'slyr_wc_api_syncdata_flag');

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

    if (!defined('WPDB_PREFIX')){
        global $wpdb;
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

    // Constructs plugin dirname:
    if (function_exists('plugin_dir_path')) {
        $dirname=explode('/', str_replace('\\', '/', plugin_dir_path( __FILE__ )));
        define('SLYR_WC_PLUGIN_NAME_DIR', $dirname[count($dirname)-2]);
    }
