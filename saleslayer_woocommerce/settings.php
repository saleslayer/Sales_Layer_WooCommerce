<?php 

    ini_set('display_errors', 0);
    error_reporting(E_ALL ^ E_NOTICE);
    
    define('SLYR_WC_version',       "1.9");

    global $wp_version;
    if (version_compare($wp_version,'4.5','>=')) {
        define('SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META', true);
    }else{
        define('SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META', false);
    }

    define('SLYR_WC_company_name',         'Sales Layer');
    define('SLYR_WC_name',                 SLYR_WC_company_name.' Woo');

    if (SLYR_WC_company_name == 'Sales Layer'){

        define('SLYR_WC_name_logo',            'logo_head_saleslayer.png');
        define('SLYR_WC_name_icon',            'icon_saleslayer.png');
        define('SLYR_WC_logo',                 'cat_image_saleslayer.png');

    }else{

        define('SLYR_WC_name_logo',            'logo_head_connector.png');
        define('SLYR_WC_name_icon',            'icon_connector.png');
        define('SLYR_WC_logo',                 'cat_image_connector.png');
        
    }

    define('SLYR_WC_connector_table',      'slyr_wc_api_config');
    define('SLYR_WC_syncdata_table',       'slyr_wc_api_syncdata');
    define('SLYR_WC_syncdata_flag_table',  'slyr_wc_api_syncdata_flag');
    define('SLYR_WC_connector_type',       'CN_WOOCOMM');
    define('SLYR_WC_short_code',           'saleslayer_catalog');
    define('SLYR_WC_connector_id',         'slyr_connector_id');
    define('SLYR_WC_connector_key',        'slyr_connector_key');
    define('SLYR_WC_auto_sync_minutes_start',                 15); //WC autosync cron start every (value) minutes
    define('SLYR_WC_auto_sync_minutes_interval',              '15min'); //WC autosync cron start every (value) minutes
    define('SLYR_WC_syncdata_minutes_start',                 5); //WC syncdata cron start every (value) minutes
    define('SLYR_WC_syncdata_minutes_interval',              '5min'); //WC syncdata cron start every (value) minutes
    define('SLYR_WC_url_API',              'api.saleslayer.com/');

    // Avoids wordpress to ask for credentials when testing on localhost
    if (!defined('FS_METHOD')) define('FS_METHOD',                 'direct');

    define('SLYR_WC_DEBBUG',                0);

    if (!defined('SLYR_WC__PLUGIN_DIR')) define('SLYR_WC__PLUGIN_DIR', plugin_dir_path(__FILE__));
    if (!defined('SLYR_WC__LOGS_DIR')) define('SLYR_WC__LOGS_DIR', SLYR_WC__PLUGIN_DIR.'/logs/');

    if (!is_dir(SLYR_WC__LOGS_DIR)){
        mkdir(SLYR_WC__LOGS_DIR, 0775, true);
    }

    // Constructs plugin dirname:
    if (function_exists('plugin_dir_path')) {

        $dirname=explode('/', str_replace('\\', '/', plugin_dir_path( __FILE__ )));

        define('SLYR_WC_PLUGIN_NAME_DIR', $dirname[count($dirname)-2]);
    }