<?php
/*
Plugin Name:    SalesLayer WooCommerce
Plugin URI:     http://support.saleslayer.com/
Description:    Plugin que permite sincronizar tu catalogo desde SalesLayer a WooCommerce.
Version:        2.4
Author:         Sales Layer
Author URI:     http://saleslayer.com/
License:        GPL2
License URI:    https://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:    saleslayer_woocommerce
WC requires at least: 3.0.0
WC tested up to: 4.1.0
*/

if ( PHP_SESSION_NONE === session_status() ) {
    session_start();
}

defined( 'ABSPATH' ) or die( '¡Sin trampas!' );
// require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');

?>
<?php
/*  Copyright 2016-2018  Sales Layer   (email : alexis@saleslayer.com, pedro.moreno@saleslayer.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
?>
<?php

if (!defined('SLYR_WC__PLUGIN_DIR')) define('SLYR_WC__PLUGIN_DIR', plugin_dir_path(__FILE__));
if (!defined('SLYR_WC__LOGS_DIR')) define('SLYR_WC__LOGS_DIR', SLYR_WC__PLUGIN_DIR.'/logs/');
include_once(SLYR_WC__PLUGIN_DIR.'settings.php');

if (!defined('SLYR_TIME_INI_PROCESS')) define('SLYR_TIME_INI_PROCESS', microtime(true));

register_activation_hook( __FILE__, 'slyr_wc_activate' );

add_action('init','slyr_wc_plugin_init');

function sl_debbug($msg, $type = ''){

    if (SLYR_WC_DEBBUG > 0){

        WP_Filesystem();

        global $wp_filesystem;

        $error_write = false;
        if (strpos($msg, '## Error.') !== false){
            $error_write = true;
            $error_file = SLYR_WC__LOGS_DIR.'_error_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
        }

        switch ($type) {
            case 'timer':
                $file = SLYR_WC__LOGS_DIR.'/_debbug_log_saleslayer_timers_'.date('Y-m-d').'.dat';
                break;

            case 'autosync':
                $file = SLYR_WC__LOGS_DIR.'/_debbug_log_saleslayer_auto_sync_'.date('Y-m-d').'.dat';
                break;

            case 'syncdata':
                $file = SLYR_WC__LOGS_DIR.'/_debbug_log_saleslayer_syncdata_'.date('Y-m-d').'.dat';
                break;

            case 'mediameta':
                $file = SLYR_WC__LOGS_DIR.'/_debbug_log_saleslayer_media_meta_'.date('Y-m-d').'.dat';
                break;

            default:
                $file = SLYR_WC__LOGS_DIR.'/_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
                break;
        }

        $new_file = false;
        if (!file_exists($file)){ $new_file = true; }

        $mem = sprintf("%05.2f", (memory_get_usage(true)/1024)/1024);

        $pid = getmypid();

        $time_end_process = round(microtime(true) - SLYR_TIME_INI_PROCESS);

        file_put_contents($file, "pid:{$pid} - mem:{$mem} - time:{$time_end_process} - $msg".PHP_EOL, FILE_APPEND);

        if ($new_file){  
            if (!is_null($wp_filesystem)){
                $wp_filesystem->chmod($file);
            }else{
                chmod($file, 0777);
            }
        }

        if ($error_write){

            $new_error_file = false;
            
            if (!file_exists($error_file)){ $new_error_file = true; }

            file_put_contents($error_file, "pid:{$pid} - mem:{$mem} - time:{$time_end_process} - $msg".PHP_EOL, FILE_APPEND);
            
            if ($new_error_file){
                if (!is_null($wp_filesystem)){
                    $wp_filesystem->chmod($error_file);
                }else{
                    chmod($error_file, 0777);
                }
            }

        }

    }

}

function slyr_wc_activate(){

    /**
     * Check if WooCommerce is active
     **/
    if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        //TODO: Integrate not compatible activation message with wordpress admin layout
        wp_die( __( 'This plugin needs WooComerce plugin installed and activated', 'my-plugin' ) );
    }
}

//    Init
function slyr_wc_plugin_init(){

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Connector.class.php');
    
    $connector = new Connector();

    // $connector->check_table();
    $connector->check_version();

    // 1. Create menu options list and hook them to the stylesheets and scripts
    add_action( 'admin_menu', 'slyr_wc_menu' );

    // 2. Load styles and scripts
    add_action( 'wp_enqueue_scripts', 'slyr_wc_enqueue_stylesheets' );
    add_action( 'wp_enqueue_scripts', 'slyr_wc_enqueue_scripts'     );

}

function slyr_wc_enqueue_stylesheets(){

    // Register Bootstrap and flat ui styles
    if (is_admin()) {
        
        wp_register_style('sl_wc_style_admin', plugin_dir_url( __FILE__ ).'css/style_admin.css');
        wp_enqueue_style('sl_wc_style_admin');

        wp_register_style('sl_wc_bootstrap_min', plugin_dir_url( __FILE__ ).'css/bootstrap.min.css');
        wp_enqueue_style('sl_wc_bootstrap_min');

    }

}

function slyr_wc_enqueue_scripts(){

    if (is_admin()){

        //Cargamos nuestro jquery porque el jQuery de WP solo funciona con el comando jQuery, no $
        $scripts = array('jquery-3.5.0.min');//, 'bootstrap');
        
        if (!empty($scripts)){   
        
            foreach($scripts as $script ){
                wp_register_script('slyr_wc_script_'.$script, plugin_dir_url( __FILE__ ).'js/'.$script.'.js',array('jquery'), null, true);
                wp_enqueue_script ('slyr_wc_script_'.$script);
            }

        }

    }

}

function slyr_wc_menu() {
    
    $menu_pages[]= add_menu_page( SLYR_WC_name.' Options', SLYR_WC_name, 'manage_options', 'slyr_wc_menu', 'slyr_wc_how_to_start',
                                  $icon_url=plugin_dir_url( __FILE__ ).'images/'.SLYR_WC_name_icon);

    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('How to Start?'),   __('How to Start?'),    'manage_options', 'slyr_wc_menu',           'slyr_wc_how_to_start');
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('Add Connector'),   __('Add Connector'),    'manage_options', 'slyr_wc_add_connector',  'slyr_wc_add_connector' );
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('Connectors'),      __('Connectors'),       'manage_options', 'slyr_wc_connectors',     'slyr_wc_connectors' );
    
    //  Adding style to each menu
    foreach($menu_pages as $page){
        add_action( 'admin_print_styles-' . $page, 'slyr_wc_enqueue_stylesheets');
        add_action( 'admin_print_scripts-'. $page, 'slyr_wc_enqueue_scripts');
    } 
}

function slyr_wc_how_to_start() {
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    ob_start();
    include_once(SLYR_WC__PLUGIN_DIR.'howto_view.php');
    $howto = ob_get_clean();
    echo '<div id="howto">'.$howto.'</div>'; 
}

function slyr_wc_add_connector(){

    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }else{
      

        if (isset($_POST['connector_id']) && !empty($_POST['connector_id']) && isset($_POST['secret_key']) && !empty($_POST['secret_key'])) {
            
            $result_check_plugins_requirements = check_plugin_requirements();

            if ($result_check_plugins_requirements['error'] === 0){
                
                if (!class_exists('SalesLayer_Conn_Woo')) include_once(SLYR_WC__PLUGIN_DIR.'admin/lib/SalesLayer-Conn-Woo.php');
                
                $connector_id = $_POST['connector_id'];
                $secret_key = $_POST['secret_key'];
                $connector = new Connector();

                if (!$connector->check_connector($connector_id)){
                    
                    $slconn = new SalesLayer_Conn_Woo ($connector_id, $secret_key);
                    $slconn->set_URL_connection(SLYR_WC_url_API);
                    $slconn->set_group_multicategory(true);
                    $slconn->set_parents_category_tree(true);
                    $slconn->set_same_parent_variants_modifications(true);
                    $slconn->get_info();

                    if (!$slconn->has_response_error()) {

                        if ($response_connector_schema = $slconn->get_response_connector_schema()) {

                            $response_connector_type = $response_connector_schema['connector_type'];
                            
                            if ($response_connector_type == SLYR_WC_connector_type) {
                            
                                if ($connector->add_connector($connector_id, $secret_key)){

                                    write_session_message("Connector added successfully!", 'success');

                                    synchronize_connector($connector_id, $secret_key);

                                    global $pagenow;
                                    if($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'slyr_wc_add_connector'){
                                        wp_redirect(admin_url('/admin.php?page=slyr_wc_connectors', 'http'), 301);
                                        exit;
                                    }
                                }
                                write_session_message("Error when creating the connector.", 'warning');

                            }else{
                                write_session_message("Invalid Sales Layer connector type.", 'warning');
                            }
                        }
                    }else{
                        write_session_message($slconn->get_response_error_message(), 'warning');
                    }
                }else{
                    write_session_message("The connector already exists.", 'warning');
                }
            }
        }
        include_once(SLYR_WC__PLUGIN_DIR.'add_connector_view.php');
    }
    
}

function slyr_wc_connectors(){
    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }else{
        $connector = new Connector();
        
        if (isset($_POST['delete_conn']) && !empty($_POST['delete_conn'])){
            if (!$connector->delete_connector($_POST['delete_conn'])){
                write_session_message('Error when deleting the connector: '.$_POST['delete_conn'], 'warning');
            }
        }

        $connectors = $connector->get_connector();
 
        if (empty($connectors)){
 
            global $pagenow;
            if($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'slyr_wc_connectors'){
                wp_redirect(admin_url('/admin.php?page=slyr_wc_add_connector', 'http'), 301);
                write_session_message("There aren't any connectors.", 'warning');
                exit;
            }
 
        }else{
            $connectors = json_decode(json_encode($connectors), true);
            include_once(SLYR_WC__PLUGIN_DIR.'connectors_view.php');

        }
    }
}

function slyr_wc_plugin_uninstall() {

    global $wpdb;

    // Delete any options starting with slyr
    $wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'slyr_wc%'");

    // Delete all saleslayer tables
    // $deleteTables = array('slyr_catalogue', 'slyr_locations', 'slyr_products', 'slyr_product_formats', 'slyr___api_config', 'slyr_filter');

    // foreach ($deleteTables as $table) { $wpdb->query("DROP TABLE IF EXISTS $table"); }
}

register_uninstall_hook(__FILE__, 'slyr_wc_plugin_uninstall');

function show_session_messages(){
    
    if (isset($_SESSION['wp_messages'])){
    
        ksort($_SESSION['wp_messages']);
        
        foreach ($_SESSION['wp_messages'] as $wp_message_type => $wp_messages) {
        
            if (!empty($wp_messages)){
                $div_messages = '';
                foreach ($wp_messages as $wp_message) {
                    if ($wp_message != ''){
                        if ($div_messages == ''){
                            $div_messages = $wp_message."<br>";
                        }else{
                            $div_messages .= $wp_message."<br>";
                        }
                    }
                }

                if ($div_messages != ''){
                    echo '<div class="dialog dialog-'.$wp_message_type.'">'.$div_messages.'</div>';
                }
            }
        }
    }

    clear_session_message();

}

function write_session_message($message, $type = 'warning'){
    $_SESSION['wp_messages'][$type][] = $message;
}

function clear_session_message($type = null){

    if (isset($_SESSION['wp_messages']) && !empty($_SESSION['wp_messages'])){

        switch ($type) {
            case null:
                foreach ($_SESSION['wp_messages'] as $wp_message_type => $wp_message) {
                    unset($_SESSION['wp_messages'][$wp_message_type]);
                }
                break;
            default:
                unset($_SESSION['wp_messages'][$type]);
                break;
        }

    }

}

function synchronize_connector($connector_id, $secret_key){

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Synchronize.class.php');

    $sync_class = new Synchronize();
    $return_message = $sync_class->store_sync_data($connector_id, $secret_key);

    return $return_message;
    
}

function sl_wc_process_pending_meta(){

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Media_class.class.php');

    $media_class = new Media_class();
    $return_message = $media_class->process_pending_meta();

    echo json_encode(array('error' => 0, 'message' => '<div class="dialog dialog-warning">'.$return_message.'</div>'));

    wp_die();

}

add_filter( 'cron_schedules', 'media_meta_add_cron_schedule' );
function media_meta_add_cron_schedule( $schedules ) {
    $schedules[SLYR_WC_media_meta_minutes_interval] = array(
        'interval' => SLYR_WC_media_meta_minutes_start * 60,
        'display'  => __( 'Once every '.SLYR_WC_media_meta_minutes_start.' minutes' ),
    );
 
    return $schedules;
}
 
if (!wp_next_scheduled( 'sl_wc_media_meta_schedule' ) ) {
    wp_schedule_event( time(), SLYR_WC_media_meta_minutes_interval, 'sl_wc_media_meta_schedule' );
}
add_action('sl_wc_media_meta_schedule', 'sl_wc_process_pending_meta');

function check_plugin_requirements(){

    if (!extension_loaded('curl')){

        return array('error' => 1, 'message' => '<div class="dialog dialog-warning">You need to activate curl extension in order to make the plugin work.</div>');

    }
    
    return array('error' => 0);

}

add_action('wp_ajax_sl_wc_synchronize_connector', 'sl_wc_synchronize_connector');

function sl_wc_synchronize_connector(){

    $connector_id = $_POST['connector_id'];
    $secret_key = $_POST['secret_key'];
    
    if (isset($connector_id) && !empty($connector_id)){

        $result_check_plugins_requirements = check_plugin_requirements();
        
        if ($result_check_plugins_requirements['error'] === 0){
            $result_check_plugins_requirements['message'] = synchronize_connector($connector_id, $secret_key);
        }
        echo json_encode($result_check_plugins_requirements);
        wp_die();
    }
}

add_action('wp_ajax_sl_wc_update_conn_field', 'update_conn_field_action');

function update_conn_field_action(){

    $connector_id = $_GET['connector_id'];
    $field_name = $_GET['field_name'];
    $field_value = $_GET['field_value'];
    $field_names = array('cnf_id' => 'Configuration ID', 'conn_code' => 'Connector code', 'conn_secret' => 'Connector secret', 'root_category' => 'Default Category', 'comp_id' => 'Company ID', 'last_update' => 'Last update', 'default_language' => 'Default language', 'languages' => 'Languages', 'conn_extra' => 'Connector extra information', 'updater_version' => 'Updater version',  'avoid_stock_update' => 'Avoid stock update', 'auto_sync' => 'Auto Sync');
    $array_return = array();

    $connector = new Connector();
    $result_update = $connector->update_conn_field($connector_id, $field_name, $field_value);

    switch ($result_update) {
        case 'error_forbidden':
            
            $array_return['message_type'] = 'warning';
            $array_return['message'] = 'Forbidden field to update: '.$field_names[$field_name].'.';
            break;
        case 'error_update':
            
            $array_return['message_type'] = 'warning';
            $array_return['message'] = 'Error updating field: '.$field_names[$field_name].'.';
            break;
        default:
            
            $array_return['message_type'] = 'success';
            $array_return['message'] = 'Field '.$field_names[$field_name].' has been updated successfully.';
            break;
    }

    echo json_encode($array_return);
    wp_die();

}

/**
 * Function to check and synchronize Sales Layer connectors with auto-synchronization enabled.
 * @return void
 */
function sl_wc_auto_sync_connectors(){

    sl_debbug("==== AUTOSync INIT ".date('Y-m-d H:i:s')." ====", 'autosync');

    $sl_time_ini_auto_sync_process = microtime(1);
    
    $result_message = array();

    try {

        global $wpdb;

        $connector = $wpdb->get_results(" SELECT conn_code, conn_secret, ".  
                                            " UNIX_TIMESTAMP(last_sync) as last_update_unix ".
                                            " FROM ".SLYR_WC_connector_table." WHERE auto_sync > 0 ".
                                            " AND ((last_sync is null) or (last_sync is not null and UNIX_TIMESTAMP(last_sync) < ( UNIX_TIMESTAMP() - ( auto_sync * 3600 )))) ".
                                            " ORDER BY last_update_unix ASC, auto_sync DESC LIMIT 1 ", ARRAY_A);
        
        if (!empty($connector)){
     
            $conn_code = $connector[0]['conn_code'];
            $conn_secret = $connector[0]['conn_secret'];

            sl_debbug("Connector to auto-synchronize: ".$conn_code, 'autosync');
            
            $time_ini_cron_sync = microtime(1);
            
            $time_random = rand(20,50);
            sleep($time_random);
            $result_message['message'] = synchronize_connector($conn_code, $conn_secret);
            
            sl_debbug("#### time_random: ".$time_random.' seconds.', 'autosync');
            sl_debbug("#### time_cron_sync: ".(microtime(1) - $time_ini_cron_sync - $time_random).' seconds.', 'autosync');

        }else{

            sl_debbug("Currently there aren't connectors to synchronize or there aren't any configured connectors with auto-sync.", 'autosync');

        }
    } catch (\Exception $e) {

        sl_debbug('Error autosync process: '.$e->getMessage(), 'autosync');

    }

    sl_debbug('##### time_all_autosync_process: '.(microtime(1) - $sl_time_ini_auto_sync_process).' seconds.', 'autosync');

    sl_debbug("==== AUTOSync END ====", 'autosync');

    if (!empty($result_message)){

        echo json_encode($result_message);

    }

    wp_die();

}

add_filter( 'cron_schedules', 'auto_sync_add_cron_schedule' );
function auto_sync_add_cron_schedule( $schedules ) {
    $schedules[SLYR_WC_auto_sync_minutes_interval] = array(
        'interval' => SLYR_WC_auto_sync_minutes_start * 60,
        'display'  => __( 'Once every '.SLYR_WC_auto_sync_minutes_start.' minutes' ),
    );
 
    return $schedules;
}
 
if (!wp_next_scheduled( 'sl_wc_auto_sync_schedule' ) ) {
    wp_schedule_event( time(), SLYR_WC_auto_sync_minutes_interval, 'sl_wc_auto_sync_schedule' );
}
add_action('sl_wc_auto_sync_schedule', 'sl_wc_auto_sync_connectors');

/**
 * Function to synchronize Sales Layer stored connector's data.
 * @return void
 */
function sl_wc_syncdata_connectors(){

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Synchronize.class.php');
    
    $sync_class = new Synchronize();
    $sync_class->sync_data_connectors();

    wp_die();

}

add_filter( 'cron_schedules', 'syncdata_add_cron_schedule' );
function syncdata_add_cron_schedule( $schedules ) {
    $schedules[SLYR_WC_syncdata_minutes_interval] = array(
        'interval' => SLYR_WC_syncdata_minutes_start * 60,
        'display'  => __( 'Once every '.SLYR_WC_syncdata_minutes_start.' minutes' ),
    );
 
    return $schedules;
}

if (!wp_next_scheduled( 'sl_wc_syncdata_schedule')) {
    wp_schedule_event( time(), SLYR_WC_syncdata_minutes_interval, 'sl_wc_syncdata_schedule' );
}
add_action('sl_wc_syncdata_schedule', 'sl_wc_syncdata_connectors');

add_action('wp_ajax_sl_wc_check_process_status', 'sl_wc_check_process_status');

function sl_wc_check_process_status(){
    
    $process_status = array();

    include_once(SLYR_WC__PLUGIN_DIR.'admin/general_functions.php');

    $counters_info_data = sl_connection_query('read', " SELECT * FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'info' AND item_type = 'counters'");
    
    if (!empty($counters_info_data) && isset($counters_info_data[0])){

        $counters_info = json_decode(stripslashes($counters_info_data[0]['item_data']),1);

        $processing_messages = array();

        foreach ($counters_info as $table => $table_data) {
            
            foreach ($table_data as $type_update => $counters) {
                
                if (!isset($processing_messages[$table])){
                    $processing_messages[$table] = array();
                    $processing_messages[$table]['total'] = $processing_messages[$table]['processed'] = 0;
                }

                if (!isset($counters['processed'])){ $counters['processed'] = 0; }

                $processing_messages[$table]['processed'] += $counters['processed'];
                $processing_messages[$table]['total'] += $counters['total'];

            }

        }

        $sync_params = json_decode(stripslashes($counters_info_data[0]['sync_params']),1);
        
        $processing_messages['header'] = 'Synchronizing connector: '.$sync_params['conn_params']['connector_id'];
        $process_status['status'] = 'not_finished';
        $process_status['content'] = $processing_messages;
        $process_status['connector_id'] = $sync_params['conn_params']['connector_id'];

        echo json_encode($process_status);
        wp_die();

    }

    $process_status['status'] = 'finished';
    echo json_encode($process_status);
    wp_die(); // ajax call must die to avoid trailing 0 in your response

}

?>