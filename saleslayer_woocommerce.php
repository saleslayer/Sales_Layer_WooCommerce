<?php
/*
Plugin Name:    Sales Layer WooCommerce
Plugin URI:     http://support.saleslayer.com/
Description:    Plugin that allows you to synchronize your catalogue from Sales Layer to WooCommerce.
Version:        2.6.0
Author:         Sales Layer
Author URI:     http://saleslayer.com/
License:        GPL2
License URI:    https://www.gnu.org/licenses/gpl-2.0.txt
Text Domain:    saleslayer_woocommerce
Requires PHP:   8.0
Requires at least: 6.4
Tested up to:   6.9
Requires Plugins: woocommerce
WC requires at least: 8.2.0
WC tested up to: 10.4.3
*/

defined( 'ABSPATH' ) or die( '¡Sin trampas!' );
require_once(ABSPATH . 'wp-admin/includes/file.php');

// Declaring compatibility with HPOS (High-Performance Order Storage) - MUST go really early
add_action('before_woocommerce_init', function() {
    // Verify that WooCommerce and HPOS functionality are available
    if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

?>
<?php
/*  Copyright 2016-2026  Sales Layer   (email : alexis@saleslayer.com, pedro.moreno@saleslayer.com)

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

if (!defined('SLYR_WC__PLUGIN_FILE')) {
    define('SLYR_WC__PLUGIN_FILE', __FILE__);
}
include_once(plugin_dir_path(__FILE__).'settings.php');
if (!defined('SLYR_TIME_INI_PROCESS')) define('SLYR_TIME_INI_PROCESS', microtime(true));

function sl_debug($msg, string $type = '')
{
    global $debug_level;

    if ($debug_level > 0){

        WP_Filesystem();

        global $wp_filesystem;

        $error_write = false;
        if (strpos($msg, '## Error.') !== false){
            $error_write = true;
            $error_file = SLYR_WC__LOGS_DIR.'_error_debug_log_saleslayer_'.date('Y-m-d').'.dat';
        }

        switch ($type) {
            case 'timer':
                $file = SLYR_WC__LOGS_DIR.'/_debug_log_saleslayer_timers_'.date('Y-m-d').'.dat';
                break;

            case 'autosync':
                $file = SLYR_WC__LOGS_DIR.'/_debug_log_saleslayer_auto_sync_'.date('Y-m-d').'.dat';
                break;

            case 'syncdata':
                $file = SLYR_WC__LOGS_DIR.'/_debug_log_saleslayer_syncdata_'.date('Y-m-d').'.dat';
                break;

            case 'mediameta':
                $file = SLYR_WC__LOGS_DIR.'/_debug_log_saleslayer_media_meta_'.date('Y-m-d').'.dat';
                break;

            default:
                $file = SLYR_WC__LOGS_DIR.'/_debug_log_saleslayer_'.date('Y-m-d').'.dat';
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

/**
 * Check if WooCommerce is active
 **/
function slyr_wc_activate()
{

    // Block per-site activation when WordPress Multisite is enabled.
    // The plugin must be activated at Network level in Multisite environments.
    if (function_exists('is_multisite') && is_multisite() && !is_network_admin()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif; max-width: 600px; margin: 40px auto; padding: 30px; border: 1px solid #ddd; border-radius: 8px; background: #fff;">'
            . '<h2 style="color: #d63638; margin-top: 0;">⚠️ ' . esc_html__('Network Activation Required', 'slyr-wc-plugin') . '</h2>'
            . '<p style="font-size: 15px; line-height: 1.6; color: #333;">'
            . esc_html__('Sales Layer WooCommerce cannot be activated on individual sites when WordPress Multisite is enabled.', 'slyr-wc-plugin')
            . '</p>'
            . '<p style="font-size: 15px; line-height: 1.6; color: #333;">'
            . esc_html__('Please activate this plugin from the Network Admin → Plugins page so it can manage synchronization across all sites.', 'slyr-wc-plugin')
            . '</p>'
            . '<div style="margin-top: 20px; display: flex; gap: 12px;">'
            . '<a href="' . esc_url(network_admin_url('plugins.php')) . '" '
            . 'style="display: inline-block; padding: 10px 20px; background: #2271b1; color: #fff; text-decoration: none; border-radius: 4px; font-size: 14px;">'
            . esc_html__('Go to Network Plugins', 'slyr-wc-plugin')
            . '</a>'
            . '</div>'
            . '</div>',
            esc_html__('Network Activation Required', 'slyr-wc-plugin'),
            array('back_link' => true, 'response' => 403)
        );
    }

    if (is_multisite()) {
        // In Multisite, WooCommerce may be network-activated or active only on specific sites.
        // Do not block activation — the plugin will silently skip sites without WooCommerce.
        $networkPlugins = (array) get_site_option('active_sitewide_plugins', []);
        $isWooNetworkActive = isset($networkPlugins['woocommerce/woocommerce.php']);
        $isWooSiteActive = in_array('woocommerce/woocommerce.php', (array) get_option('active_plugins', []));

        if (!$isWooNetworkActive && !$isWooSiteActive) {
            return;
        }
    } else {
        // Single-site: strict check — WooCommerce must be active.
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                __('This plugin needs WooComerce plugin installed and activated', 'slyr-wc-plugin'),
                __('Activation error', 'slyr-wc-plugin'),
                array('back_link' => true)
            );
        }
    }

    if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '8.2', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            __( 'This plugin requires WooCommerce version 8.2 o higher.', 'slyr-wc-plugin' ),
            __( 'Activation error', 'slyr-wc-plugin' ),
            array(
                'back_link' => true
            )
        );
    }

    if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            __( 'This plugin requires PHP version 8.0 o higher.', 'slyr-wc-plugin' ),
            __( 'Activation error', 'slyr-wc-plugin' ),
            array(
                'back_link' => true
            )
        );
    }

    sl_wc_check_version();
    flush_rewrite_rules();

}
register_activation_hook( __FILE__, 'slyr_wc_activate' );

function slyr_wc_deactivate()
{
    $stored_version = get_site_option('SLYR_WC_latest_version', '');
    if ($stored_version !== '') {
        delete_site_option('SLYR_WC_latest_version');
    }
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'slyr_wc_deactivate');

function slyr_wc_plugin_init()
{

    global $debug_level;
    
    include_once(SLYR_WC__PLUGIN_DIR.'admin/Multiconn.class.php');
    include_once(SLYR_WC__PLUGIN_DIR.'admin/Connector.class.php');
    include_once(SLYR_WC__PLUGIN_DIR.'admin/GeneralParameters.class.php');
    
    $connector = new Connector();
    $general_params = new GeneralParameters();

    $connector->check_version();
    $debug_level = $general_params->getInfo("debug_level");

    // 1. Create menu options list and hook them to the stylesheets and scripts
    add_action( 'admin_menu', 'slyr_wc_menu' );

    // 2. Load styles and scripts
    add_action( 'wp_enqueue_scripts', 'slyr_wc_enqueue_stylesheets' );
    add_action( 'wp_enqueue_scripts', 'slyr_wc_enqueue_scripts'     );

}
add_action('init','slyr_wc_plugin_init');

function slyr_wc_enqueue_stylesheets()
{

    // Register Bootstrap and flat ui styles
    if (is_admin()) {
        
        wp_register_style('sl_wc_style_admin', plugin_dir_url( __FILE__ ).'css/style_admin.css');
        wp_enqueue_style('sl_wc_style_admin');

        wp_register_style('sl_wc_bootstrap_min', plugin_dir_url( __FILE__ ).'css/bootstrap.min.css');
        wp_enqueue_style('sl_wc_bootstrap_min');

    }

}

function slyr_wc_enqueue_scripts()
{

    if (is_admin()){

        $scripts = ['jquery-3.5.0.min', 'version_content'];
        
        if (!empty($scripts)){   
        
            foreach($scripts as $script ){
                wp_register_script('slyr_wc_script_'.$script, plugin_dir_url( __FILE__ ).'js/'.$script.'.js',array('jquery'), null, true);
                wp_enqueue_script ('slyr_wc_script_'.$script);
            }

        }

    }

}

function slyr_wc_menu()
{
    
    $menu_pages[]= add_menu_page( SLYR_WC_name.' Options', SLYR_WC_name, 'manage_options', 'slyr_wc_menu', 'slyr_wc_how_to_start',
                                  $icon_url=plugin_dir_url( __FILE__ ).'images/'.SLYR_WC_name_icon);

    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('How to Start?'),           __('How to Start?'),        'manage_options', 'slyr_wc_menu',           'slyr_wc_how_to_start');
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('General Parameters'),      __('General Parameters'),   'manage_options', 'slyr_wc_general_params', 'slyr_wc_general_params' );
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('Add Connector'),           __('Add Connector'),        'manage_options', 'slyr_wc_add_connector',  'slyr_wc_add_connector' );
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('Connectors'),              __('Connectors'),           'manage_options', 'slyr_wc_connectors',     'slyr_wc_connectors' );
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('Tools'),                   __('Tools'),                'manage_options', 'slyr_wc_tools',          'slyr_wc_tools' );
    $menu_pages[]= add_submenu_page( 'slyr_wc_menu', __('FAQ'),                     __('FAQ'),                  'manage_options', 'slyr_wc_faq',            'slyr_wc_faq' );
        
    foreach($menu_pages as $page){
        add_action( 'admin_print_styles-' . $page, 'slyr_wc_enqueue_stylesheets');
        add_action( 'admin_print_scripts-'. $page, 'slyr_wc_enqueue_scripts');
    } 
}

function slyr_wc_how_to_start()
{
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }

    $template_path = SLYR_WC__PLUGIN_DIR.'views/how_to.html';
    
    if (file_exists($template_path)){
        ob_start();
        include $template_path;
        $how_to_content = ob_get_clean();
        echo wp_kses_post($how_to_content);
        echo getLatestVersionContent(); 
    } else {
        set_transient('slyr_wc_not_found_message', __('How to content not available.', 'text-domain'), 30);
        wp_redirect(admin_url('index.php'), 301);
        exit;
    }

}

function slyr_wc_show_admin_notice()
{
    $transient_messages = [
        'slyr_wc_connectors_success_message' => 'success',
        'slyr_wc_not_found_message' => 'error',
        'slyr_wc_connectors_error_message' => 'error',
        'slyr_wc_no_connectors_message' => 'warning',
        'slyr_wc_error_deleting_connector_message' => 'warning'
    ];
    foreach ($transient_messages as $transient_message => $notice_type) {
        if ($transient_message_content = get_transient($transient_message)){
            printf(
                '<div class="notice notice-'.$notice_type.' is-dismissible"><p>%s</p></div>',
                esc_html($transient_message_content)
            );
            delete_transient($transient_message);
        }
    }
}
add_action('admin_notices', 'slyr_wc_show_admin_notice');

function slyr_wc_general_params()
{
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    } else {
        
        $template_path = SLYR_WC__PLUGIN_DIR.'views/general_params.html';
    
        if (file_exists($template_path)){
            ob_start();
            
            $general_param = new GeneralParameters();
            $general_params = $general_param->getWPOptionsGeneralParameters();
            $allGeneralParametersValues = $general_param->getAllGeneralParametersValues();
            extract($allGeneralParametersValues);
            
            include $template_path;
            $gen_params_content = ob_get_clean();

            echo wp_kses($gen_params_content, getAllowedTags());
            echo getLatestVersionContent(); 

        } else {
            set_transient( 'slyr_wc_not_found_message', __( 'General parameters content not available.', 'text-domain' ), 30 );
            wp_redirect(admin_url('/admin.php?page=slyr_wc_menu'), 301);
            exit;
        }

    }    
}

function slyr_enqueue_general_params_script()
{
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'slyr_wc_general_params' ) {
        wp_register_script(
            'slyr_wc_script_general_params',
            plugin_dir_url( __FILE__ ) . 'js/general_params.js',
            array( 'jquery' ),
            null,
            true
        );
        wp_localize_script('slyr_wc_script_general_params', 'ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        ));
        wp_enqueue_script('slyr_wc_script_general_params');
    }
}
add_action( 'admin_enqueue_scripts', 'slyr_enqueue_general_params_script' );

function slyr_wc_add_connector()
{

    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }else{

        if (isset($_POST['connector_id']) && !empty($_POST['connector_id']) && isset($_POST['secret_key']) && !empty($_POST['secret_key'])) {
            
            $result_check_plugins_requirements = check_plugin_requirements();

            if ($result_check_plugins_requirements['error'] === 0){
                
                if (!class_exists('SalesLayer_Conn_Woo')){
                    include_once(SLYR_WC__PLUGIN_DIR.'admin/lib/SalesLayer-Conn-Woo.php');
                }
                
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

                                    set_transient('slyr_wc_connectors_success_message', __( 'Connector added successfully!', 'text-domain' ), 30 );
                                    slyr_wc_show_admin_notice();

                                    include_once(SLYR_WC__PLUGIN_DIR.'admin/Synchronize.class.php');

                                    $sync_class = new Synchronize();
                                    $return_message = $sync_class->config_connector($connector_id, $secret_key);
                                    
                                    global $pagenow;
                                    if($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'slyr_wc_add_connector'){
                                        set_transient('slyr_wc_add_connector_redirect_message', $return_message, 30);
                                        wp_redirect(admin_url('/admin.php?page=slyr_wc_connectors', 'http'), 301);
                                        exit;
                                    }
                                }
                                set_transient('slyr_wc_connectors_error_message', __( 'Error when creating the connector.', 'text-domain' ), 30 );
                                slyr_wc_show_admin_notice();

                            }else{
                                set_transient('slyr_wc_connectors_error_message', __( 'Invalid Sales Layer connector type.', 'text-domain' ), 30 );
                                slyr_wc_show_admin_notice();
                            }
                        }
                    }else{
                        set_transient('slyr_wc_connectors_error_message', __( $slconn->get_response_error_message(), 'text-domain' ), 30 );
                        slyr_wc_show_admin_notice();
                    }
                }else{
                    set_transient( 'slyr_wc_connectors_error_message', __( 'The connector already exists.', 'text-domain' ), 30 );
                    slyr_wc_show_admin_notice();
                }
            }

        }

        $template_path = SLYR_WC__PLUGIN_DIR.'views/add_connector.html';
    
        if (file_exists($template_path)){       
            ob_start();
            include $template_path;
            $add_conn_content = ob_get_clean();
            echo wp_kses($add_conn_content, getAllowedTags());
            echo getLatestVersionContent();

        } else {
            set_transient( 'slyr_wc_not_found_message', __( 'Add connector content not available.', 'text-domain' ), 30 );
            wp_redirect(admin_url('/admin.php?page=slyr_wc_menu'), 301);
            exit;
        }

    }
    
}

function slyr_wc_connectors()
{
    if ( !current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }else{
        $connector = new Connector();
        
        if (isset($_POST['delete_conn']) && !empty($_POST['delete_conn'])){
            // Purge multiconn references before deleting the connector
            $compId = $connector->get_info($_POST['delete_conn'], 'comp_id');
            if ($compId !== false) {
                $multiconn = Multiconn::get_instance();
                $multiconn->purge_connector($_POST['delete_conn'], (int) $compId);
            }

            if (!$connector->delete_connector($_POST['delete_conn'])){
                set_transient('slyr_wc_error_deleting_connector_message', __( 'Error when deleting the connector: '.$_POST['delete_conn'], 'text-domain' ), 30 );
                slyr_wc_show_admin_notice();
            }
        }

        $connectors = $connector->get_connector();
 
        if (empty($connectors)){
 
            global $pagenow;
            if ($pagenow == 'admin.php' && isset($_GET['page']) && $_GET['page'] == 'slyr_wc_connectors'){
                set_transient( 'slyr_wc_no_connectors_message', __( "There aren't any connectors.", 'text-domain' ), 30 );
                wp_redirect(admin_url('/admin.php?page=slyr_wc_add_connector', 'http'), 301);
                exit;
            }
 
        }else{

            $template_path = SLYR_WC__PLUGIN_DIR.'views/connectors.html';
    
            if (file_exists($template_path)){
                
                $connectors = json_decode(json_encode($connectors), true);
                ob_start();
                include $template_path;
                $conn_content = ob_get_clean();
                echo wp_kses($conn_content, getAllowedTags());

                // Render multisite modal outside wp_kses() — plugin-generated HTML, not user input
                if (slyr_is_multisite_mode()) {
                    ?>
                    <div id="slyr-multisite-overlay" class="slyr-multisite-overlay" style="display:none;">
                        <div class="slyr-multisite-modal">
                            <div class="slyr-multisite-modal-header">
                                <h3>Multisite Synchronization Settings</h3>
                                <button type="button" class="slyr-multisite-close" onclick="closeMultisiteModal()">&times;</button>
                            </div>
                            <div class="slyr-multisite-modal-body">
                                <div id="slyr-multisite-languages-info" class="slyr-multisite-info"></div>
                                <table class="wp-list-table widefat fixed striped" id="slyr-multisite-sites-table">
                                    <thead>
                                        <tr>
                                            <th>Site</th>
                                            <th class="col-modal-active">Active</th>
                                            <th class="col-modal-lang">Language</th>
                                        </tr>
                                    </thead>
                                    <tbody id="slyr-multisite-sites-body">
                                        <tr><td colspan="3">Loading sites...</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="slyr-multisite-modal-footer">
                                <button type="button" class="button" onclick="closeMultisiteModal()">Cancel</button>
                                <button type="button" class="button button-primary" id="slyr-multisite-save">Save Configuration</button>
                            </div>
                            <input type="hidden" id="slyr-multisite-connector-id" value="" />
                        </div>
                    </div>
                    <?php
                }

                echo getLatestVersionContent();
                $add_connector_message = get_transient('slyr_wc_add_connector_redirect_message');  
                if ($add_connector_message) {
                    echo '<script type="text/javascript">
                    var add_conn_message = '.json_encode($add_connector_message).';
                    </script>';
                    delete_transient('slyr_wc_add_connector_redirect_message');
                }
            } else {
                set_transient( 'slyr_wc_not_found_message', __( 'FAQ content not available.', 'text-domain' ), 30 );
                wp_redirect(admin_url('/admin.php?page=slyr_wc_menu'), 301);
                exit;
            }
        }
    }
}

function slyr_enqueue_connectors_script()
{
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'slyr_wc_connectors' ) {
        wp_register_script(
            'slyr_wc_script_connectors',
            plugin_dir_url( __FILE__ ) . 'js/connectors.js',
            array( 'jquery' ),
            null,
            true
        );
        wp_localize_script('slyr_wc_script_connectors', 'ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'is_multisite_mode' => slyr_is_multisite_mode() ? 1 : 0,
            'multisite_nonce' => wp_create_nonce('slyr_multisite_nonce'),
        ));
        wp_enqueue_script('slyr_wc_script_connectors');
    }
}
add_action( 'admin_enqueue_scripts', 'slyr_enqueue_connectors_script' );

function slyr_wc_tools()
{
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    
    $template_path = SLYR_WC__PLUGIN_DIR.'views/tools.html';
    
    if (file_exists($template_path)){
        ob_start();
        include $template_path;
        $tools_content = ob_get_clean();
        echo wp_kses_post($tools_content);
        echo getLatestVersionContent(); 
    } else {
        set_transient( 'slyr_wc_not_found_message', __( 'Tools content not available.', 'text-domain' ), 30 );
        wp_redirect(admin_url('/admin.php?page=slyr_wc_menu'), 301);
        exit;
    }
}

function slyr_enqueue_tools_script()
{
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'slyr_wc_tools' ) {
        wp_register_script(
            'slyr_wc_script_tools',
            plugin_dir_url( __FILE__ ) . 'js/tools.js',
            array( 'jquery' ),
            SLYR_WC_version,
            true
        );

        wp_localize_script('slyr_wc_script_tools', 'ajax_object', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        ));

        wp_enqueue_script('slyr_wc_script_tools');

        wp_register_style('sl_wc_style_tools', plugin_dir_url( __FILE__ ).'css/tools.css');
        wp_enqueue_style('sl_wc_style_tools');

        wp_register_style('sl_wc_fontawesome_min', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css');
        wp_enqueue_style('sl_wc_fontawesome_min');

    }
}
add_action( 'admin_enqueue_scripts', 'slyr_enqueue_tools_script' );

function slyr_wc_faq()
{
    if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    
    $template_path = SLYR_WC__PLUGIN_DIR.'views/faq.html';
    
    if (file_exists($template_path)){
        ob_start();
        include $template_path;
        $faq_content = ob_get_clean();
        echo wp_kses_post($faq_content);
        echo getLatestVersionContent(); 
    } else {
        set_transient( 'slyr_wc_not_found_message', __( 'FAQ content not available.', 'text-domain' ), 30 );
        wp_redirect(admin_url('/admin.php?page=slyr_wc_menu'), 301);
        exit;
    }
}

function slyr_enqueue_faq_script()
{
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'slyr_wc_faq' ) {
        wp_register_script(
            'slyr_wc_script_faq',
            plugin_dir_url( __FILE__ ) . 'js/faq.js',
            array( 'jquery' ),
            null,
            true
        );

        wp_enqueue_script('slyr_wc_script_faq');

        wp_register_style('sl_wc_style_faq', plugin_dir_url( __FILE__ ).'css/faq.css');
        wp_enqueue_style('sl_wc_style_faq');
    }
}
add_action( 'admin_enqueue_scripts', 'slyr_enqueue_faq_script' );

function slyr_wc_plugin_uninstall()
{

    global $wpdb;

    // Delete any options starting with slyr
    $options_table = $wpdb->options;
    $like_prefix = $wpdb->esc_like('slyr_wc') . '%';
    $sql = $wpdb->prepare(
        "DELETE FROM {$options_table} WHERE option_name LIKE %s OR option_name = %s",
        $like_prefix,
        SLYR_WC_general_params
    );
    $wpdb->query($sql);

    // Delete all saleslayer tables
    // $deleteTables = array('slyr_catalogue', 'slyr_locations', 'slyr_products', 'slyr_product_formats', 'slyr___api_config', 'slyr_filter');

    // foreach ($deleteTables as $table) { $wpdb->query("DROP TABLE IF EXISTS $table"); }
}

register_uninstall_hook(__FILE__, 'slyr_wc_plugin_uninstall');

function synchronize_connector($connector_id, $secret_key)
{

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Synchronize.class.php');

    $sync_class = new Synchronize();
    $return_message = $sync_class->store_sync_data($connector_id, $secret_key);
    return $return_message;
    
}

function sl_wc_process_pending_meta()
{

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Media_class.class.php');

    $media_class = new Media_class();
    $return_message = $media_class->process_pending_meta();

    echo json_encode(array('error' => 0, 'message' => '<div class="dialog dialog-warning">'.$return_message.'</div>'));

    wp_die();

}

function media_meta_add_cron_schedule($schedules)
{
    $schedules[SLYR_WC_media_meta_minutes_interval] = array(
        'interval' => SLYR_WC_media_meta_minutes_start * 60,
        'display'  => __( 'Once every '.SLYR_WC_media_meta_minutes_start.' minutes' ),
    );
    
    return $schedules;
}
add_filter( 'cron_schedules', 'media_meta_add_cron_schedule' );
 
if (!wp_next_scheduled( 'sl_wc_media_meta_schedule' ) ) {
    wp_schedule_event( time(), SLYR_WC_media_meta_minutes_interval, 'sl_wc_media_meta_schedule' );
}
add_action('sl_wc_media_meta_schedule', 'sl_wc_process_pending_meta');

function check_plugin_requirements()
{

    if (!extension_loaded('curl')){
        return array('error' => 1, 'message' => '<div class="dialog dialog-warning">You need to activate curl extension in order to make the plugin work.</div>');
    }
    return array('error' => 0);

}

add_action('wp_ajax_sl_wc_synchronize_connector', 'sl_wc_synchronize_connector');

function sl_wc_synchronize_connector()
{

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

add_action('wp_ajax_sl_wc_delete_connector', 'sl_wc_delete_connector_ajax');

/**
 * AJAX handler: Delete a connector and reload the page.
 *
 * Expects POST parameter 'connector_id'.
 *
 * @return void Sends JSON response via wp_send_json_success/wp_send_json_error
 */
function sl_wc_delete_connector_ajax()
{
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $connectorId = isset($_POST['connector_id']) ? sanitize_text_field($_POST['connector_id']) : '';
    if (empty($connectorId)) {
        wp_send_json_error(['message' => 'Missing connector_id']);
        return;
    }

    $connector = new Connector();

    // Purge multiconn references before deleting the connector
    $compId = $connector->get_info($connectorId, 'comp_id');
    if ($compId !== false) {
        $multiconn = Multiconn::get_instance();
        $multiconn->purge_connector($connectorId, (int) $compId);
    }

    if ($connector->delete_connector($connectorId)) {
        wp_send_json_success(['message' => 'Connector deleted successfully']);
    } else {
        wp_send_json_error(['message' => 'Error when deleting the connector: ' . $connectorId]);
    }
}

add_action('wp_ajax_sl_wc_update_conn_field', 'update_conn_field_action');
add_action('wp_ajax_sl_wc_update_general_parameter_field', 'update_general_parameter_field_action');

function update_conn_field_action()
{

    $connector_id = $_GET['connector_id'];
    $field_name = $_GET['field_name'];
    $field_value = $_GET['field_value'];
    $field_names = [
        'cnf_id' => 'Configuration ID',
        'conn_code' => 'Connector code',
        'conn_secret' => 'Connector secret',
        'root_category' => 'Default Category',
        'comp_id' => 'Company ID',
        'last_update' => 'Last update',
        'default_language' => 'Default language',
        'languages' => 'Languages',
        'conn_extra' => 'Connector extra information',
        'auto_sync' => 'Auto Sync'
    ];
    $array_return = [];

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

function update_general_parameter_field_action()
{

    $field_name = $_GET['field_name'];
    $field_value = $_GET['field_value'];
    $field_names = [
        'API_version' => 'API Version',
        'pagination' => 'Pagination',
        'debug_level' => 'Debug Level',
        'all_analytics_data' => 'All Analytics Data',
    ];
    $array_return = [];

    $general_params = new GeneralParameters();
    $result_update = $general_params->updateGeneralParameter($field_name, $field_value);

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

add_action('wp_ajax_sl_wc_execute_tool', 'sl_wc_execute_tool');

function sl_wc_execute_tool()
{

    $toolToExecute = $_POST['tool_to_execute'];
    $response = [];
    include_once(SLYR_WC__PLUGIN_DIR.'admin/Tools.class.php');
    $tools = new Tools();
    
    switch ($toolToExecute) {
        case 'download_sl_logs': 
            $zipPath = $tools->downloadSLLogs();
            if ($zipPath !== false){
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($zipPath) . '"');
                header('Content-Length: ' . filesize($zipPath));
                readfile($zipPath);
                unlink($zipPath);
                exit;
            }else{
                wp_send_json_error([]);
                wp_die();
            }          
            break;
        case 'delete_sl_logs':
            if ($tools->deleteSLLogs() == true){
                $response['message_type'] = 'success';
                $response['message'] = 'SL logs deleted successfully.';
            }else{
                $response['message_type'] = 'success';
                $response['message'] = "Couldn't delete SL logs.";
            }
            break;
        case 'delete_sl_pending_items':
            if ($tools->deleteSLPendingItems() == true){
                $response['message_type'] = 'success';
                $response['message'] = 'SL pending items to process deleted successfully.';
            }else{
                $response['message_type'] = 'error';
                $response['message'] = "Couldn't delete SL pending items to process.";
            }
            break;
        case 'delete_sl_credentials':
            if ($tools->deleteSLItemsCredentials() == true){
                $response['message_type'] = 'success';
                $response['message'] = "Item's SL credentials deleted successfully.";
            }else{
                $response['message_type'] = 'error';
                $response['message'] = "Couldn't delete item's SL credentials.";
            }
            break;
        case 'clean_orphaned_multiconn':
            $result = $tools->cleanOrphanedMulticonnRecords();
            if ($result['success']) {
                $response['message_type'] = 'success';
                $response['message'] = 'Orphaned multiconn records cleaned. Rows deleted: ' . $result['deleted'] . '.';
            } else {
                $response['message_type'] = 'error';
                $response['message'] = "Couldn't clean orphaned multiconn records.";
            }
            break;
        default:
            $response['message_type'] = 'error';
            $response['message'] = 'Unknown tool executed.';
            break;
    }

    echo json_encode($response);
    wp_die();
}

/**
 * Check and synchronize Sales Layer connectors with auto-synchronization enabled.
 * @return void
 */
function sl_wc_auto_sync_connectors()
{

    sl_debug("==== AUTOSync INIT ".date('Y-m-d H:i:s')." ====", 'autosync');

    $sl_time_ini_auto_sync_process = microtime(true);
    
    $return_message = [];

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

            sl_debug("Connector to auto-synchronize: ".$conn_code, 'autosync');
            
            $time_ini_cron_sync = microtime(true);
            
            $time_random = rand(20,50);
            sleep($time_random);
            $return_message['message'] = synchronize_connector($conn_code, $conn_secret);
            
            sl_debug("#### time_random: ".$time_random.' seconds.', 'autosync');
            sl_debug("#### time_cron_sync: ".(microtime(true) - $time_ini_cron_sync - $time_random).' seconds.', 'autosync');

        }else{

            sl_debug("Currently there aren't connectors to synchronize or there aren't any configured connectors with auto-sync.", 'autosync');

        }
    } catch (\Exception $e) {

        sl_debug('Error autosync process: '.$e->getMessage(), 'autosync');

    }

    sl_debug('##### time_all_autosync_process: '.(microtime(true) - $sl_time_ini_auto_sync_process).' seconds.', 'autosync');

    sl_debug("==== AUTOSync END ====", 'autosync');

    if (!empty($return_message)){

        echo json_encode($return_message);

    }

    wp_die();

}

function auto_sync_add_cron_schedule($schedules)
{
    $schedules[SLYR_WC_auto_sync_minutes_interval] = array(
        'interval' => SLYR_WC_auto_sync_minutes_start * 60,
        'display'  => __( 'Once every '.SLYR_WC_auto_sync_minutes_start.' minutes' ),
    );
 
    return $schedules;
}
add_filter( 'cron_schedules', 'auto_sync_add_cron_schedule' );
 
if (!wp_next_scheduled( 'sl_wc_auto_sync_schedule' ) ) {
    wp_schedule_event( time(), SLYR_WC_auto_sync_minutes_interval, 'sl_wc_auto_sync_schedule' );
}
add_action('sl_wc_auto_sync_schedule', 'sl_wc_auto_sync_connectors');

/**
 * Synchronize Sales Layer stored connector's data.
 * @return void
 */
function sl_wc_syncdata_connectors()
{

    include_once(SLYR_WC__PLUGIN_DIR.'admin/Synchronize.class.php');
    
    $sync_class = new Synchronize();
    $sync_class->sync_data_connectors();

    wp_die();

}

function syncdata_add_cron_schedule($schedules)
{
    $schedules[SLYR_WC_syncdata_minutes_interval] = array(
        'interval' => SLYR_WC_syncdata_minutes_start * 60,
        'display'  => __( 'Once every '.SLYR_WC_syncdata_minutes_start.' minutes' ),
    );
 
    return $schedules;
}
add_filter( 'cron_schedules', 'syncdata_add_cron_schedule' );

if (!wp_next_scheduled( 'sl_wc_syncdata_schedule')) {
    wp_schedule_event( time(), SLYR_WC_syncdata_minutes_interval, 'sl_wc_syncdata_schedule' );
}
add_action('sl_wc_syncdata_schedule', 'sl_wc_syncdata_connectors');

add_action('wp_ajax_sl_wc_check_process_status', 'sl_wc_check_process_status');

function sl_wc_check_process_status()
{
    
    $process_status = array();

    include_once(SLYR_WC__PLUGIN_DIR.'admin/general_functions.php');

    $counters_info_data = sl_connection_query('read', " SELECT * FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'info' AND item_type = 'counters'");
    
    if (!empty($counters_info_data) && isset($counters_info_data[0])){

        $counters_info = json_decode(stripslashes($counters_info_data[0]['item_data']), true);

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

        $sync_params = json_decode(stripslashes($counters_info_data[0]['sync_params']), true);
        
        $processing_messages['header'] = 'Synchronizing connector: '.$sync_params['conn_params']['connector_id'];
        $process_status['status'] = 'not_finished';
        $process_status['content'] = $processing_messages;
        $process_status['connector_id'] = $sync_params['conn_params']['connector_id'];

        echo json_encode($process_status);
        wp_die();

    }

    $process_status['status'] = 'finished';
    echo json_encode($process_status);
    wp_die();

}
 
if (!wp_next_scheduled( 'sl_wc_check_version_schedule' ) ) {
    wp_schedule_event( time(), SLYR_WC_check_version_days_start, 'sl_wc_check_version_schedule' );
}
add_action('sl_wc_check_version_schedule', 'sl_wc_check_version');

/**
 * Check plugin version.
 * @return void
 */
function sl_wc_check_version()
{

    $repo_url = "https://api.github.com/repos/saleslayer/Sales_Layer_WooCommerce/releases/latest";
    $response = wp_remote_get($repo_url, array('headers' => array('User-Agent' => 'WordPress')));

    if (is_wp_error($response)) {
        return;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!empty($data['tag_name'])) {
        $latest_version = $data['tag_name'];
        $current_version = SLYR_WC_version;
        $stored_version = get_site_option('SLYR_WC_latest_version', '');
        if (version_compare($latest_version, $current_version, '>') && $latest_version !== $stored_version) {
            update_site_option('SLYR_WC_latest_version', $latest_version);
        } elseif (version_compare($latest_version, $current_version, '<=') && $stored_version !== '') {
            delete_site_option('SLYR_WC_latest_version');
        }
    }
}

// --- Multisite AJAX Endpoints ---

add_action('wp_ajax_slyr_get_network_sites', 'slyr_ajax_get_network_sites');

/**
 * AJAX handler: Return list of sites in the WordPress Multisite network.
 *
 * Response includes blog_id, blogname, and siteurl for each site.
 * Requires 'manage_options' capability and valid nonce.
 *
 * @return void Sends JSON response via wp_send_json_success/wp_send_json_error
 */
function slyr_ajax_get_network_sites()
{
    check_ajax_referer('slyr_multisite_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    if (!is_multisite()) {
        wp_send_json_error(['message' => 'Not a multisite installation']);
        return;
    }

    $sites = get_sites(['number' => 100]);
    $mainSiteBlogId = (int) get_main_site_id();
    $sitesData = array_map(function ($site) use ($mainSiteBlogId) {
        $blogId = (int) $site->blog_id;

        // Check if WooCommerce is active on this site via DB options only.
        // class_exists('WooCommerce') is unreliable after switch_to_blog()
        // because PHP classes remain loaded in memory regardless of blog context.
        switch_to_blog($blogId);
        $siteActivePlugins = (array) get_option('active_plugins', []);
        restore_current_blog();
        $networkActivePlugins = (array) get_site_option('active_sitewide_plugins', []);
        $hasWooCommerce = in_array('woocommerce/woocommerce.php', $siteActivePlugins, true)
            || array_key_exists('woocommerce/woocommerce.php', $networkActivePlugins);

        return [
            'blog_id'         => $blogId,
            'blogname'        => get_blog_option($blogId, 'blogname'),
            'siteurl'         => get_blog_option($blogId, 'siteurl'),
            'has_woocommerce' => $hasWooCommerce,
            'is_main_site'    => ($blogId === $mainSiteBlogId),
        ];
    }, $sites);

    wp_send_json_success(['sites' => $sitesData, 'main_site_id' => $mainSiteBlogId]);
}

add_action('wp_ajax_slyr_save_multisite_config', 'slyr_ajax_save_multisite_config');

/**
 * AJAX handler: Save multisite configuration to a connector's conn_extra column.
 *
 * Expects POST params: connector_id, multisite_enabled (0|1), targets (JSON array).
 * Merges multisite keys into existing conn_extra JSON without overwriting other data.
 *
 * @return void Sends JSON response via wp_send_json_success/wp_send_json_error
 */
function slyr_ajax_save_multisite_config()
{
    check_ajax_referer('slyr_multisite_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $connectorId = isset($_POST['connector_id']) ? sanitize_text_field($_POST['connector_id']) : '';
    if (empty($connectorId)) {
        wp_send_json_error(['message' => 'Missing connector_id']);
        return;
    }

    $connector = new Connector();
    $connData = $connector->get_connector($connectorId);

    if (empty($connData)) {
        wp_send_json_error(['message' => 'Connector not found']);
        return;
    }

    // Decode existing conn_extra preserving other keys
    $connExtra = json_decode($connData->conn_extra ?? '{}', true);
    if (!is_array($connExtra)) {
        $connExtra = [];
    }

    // Sanitize targets — only store active ones (presence in array = active)
    $rawTargets = isset($_POST['targets']) ? json_decode(stripslashes($_POST['targets']), true) : [];
    $activeTargets = [];

    if (is_array($rawTargets)) {
        foreach ($rawTargets as $target) {
            if (!empty($target['active'])) {
                $activeTargets[] = [
                    'blog_id'   => (int) ($target['blog_id'] ?? 0),
                    'lang_code' => sanitize_text_field($target['lang_code'] ?? ''),
                ];
            }
        }
    }

    // Fallback: if no site is active, ensure the main site is included
    if (empty($activeTargets)) {
        $activeTargets[] = [
            'blog_id'   => (int) get_main_site_id(),
            'lang_code' => '',
        ];
    }

    // Remove legacy multisite_enabled key if present
    unset($connExtra['multisite_enabled']);
    $connExtra['multisite_targets'] = $activeTargets;

    $connector->update_connector($connectorId, [
        'conn_extra' => json_encode($connExtra, JSON_UNESCAPED_UNICODE),
    ]);

    wp_send_json_success(['message' => 'Multisite configuration saved']);
}

add_action('wp_ajax_slyr_get_multisite_config', 'slyr_ajax_get_multisite_config');

/**
 * AJAX handler: Retrieve the current multisite configuration for a connector.
 *
 * Returns multisite_enabled flag, multisite_targets array, and available
 * languages from the connector's 'languages' field.
 *
 * @return void Sends JSON response via wp_send_json_success/wp_send_json_error
 */
function slyr_ajax_get_multisite_config()
{
    check_ajax_referer('slyr_multisite_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }

    $connectorId = isset($_GET['connector_id']) ? sanitize_text_field($_GET['connector_id']) : '';
    if (empty($connectorId)) {
        wp_send_json_error(['message' => 'Missing connector_id']);
        return;
    }

    $connector = new Connector();
    $connData = $connector->get_connector($connectorId);

    if (empty($connData)) {
        wp_send_json_error(['message' => 'Connector not found']);
        return;
    }

    $connExtra = json_decode($connData->conn_extra ?? '{}', true);
    if (!is_array($connExtra)) {
        $connExtra = [];
    }

    // Parse available languages from connector's languages field
    $availableLanguages = [];
    if (!empty($connData->languages)) {
        $languagesParts = explode(',', $connData->languages);
        foreach ($languagesParts as $langPart) {
            $langCode = trim($langPart);
            if (!empty($langCode)) {
                $availableLanguages[] = $langCode;
            }
        }
    }

    wp_send_json_success([
        'multisite_targets' => $connExtra['multisite_targets'] ?? [],
        'available_languages' => $availableLanguages,
    ]);
}

// --- End Multisite AJAX Endpoints ---

function getLatestVersionContent()
{

    $latestVersion = get_site_option('SLYR_WC_latest_version', '');
    $latestVersionContent = [];

    if ($latestVersion !== ''){

        $latestVersionContent['div_class'] = 'notice notice-warning is-dismissible';
        $latestVersionContent['div_content'] = 
            '<p>There is a new available version of the plugin. Actual version: '.
            esc_html(get_site_option('SLYR_WC_version')).
            ', new version: '.esc_html($latestVersion).
            ' <a href="https://github.com/saleslayer/Sales_Layer_WooCommerce/releases" target="_blank">Visit here</a>.</p>
            <button type="button" id="notice-dismiss" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>';

    }

    $script = '<script type="text/javascript">
    var versionContent = '.json_encode($latestVersionContent).';
    </script>';
    
    return $script;
}

function getAllowedTags()
{

    return [
        'title' => [
            'data-i18n' => []
        ],
        'header' => [],
        'main' => [
            'id' => []
        ],
        'div' => [
            'id' => [],
            'class' => [],
            'style' => [],
            'role' => [],
            'aria-valuenow' => [],
            'aria-valuemin' => [],
            'aria-valuemax' => []
        ],
        'span' => [
            'class' => [],
            'id' => []
        ],
        'h3' => [],
        'form' => [
            'method' => [],
            'action' => [],
            'id' => [],
            'name' => []
        ],
        'input' => [
            'type' => [],
            'class' => [],
            'placeholder' => [],
            'id' => [],
            'name' => [],
            'autocomplete' => [],
            'required' => [],
            'value' => [],
            'checked' => []
        ],
        'button' => [
            'type' => [],
            'class' => [],
            'connectorid' => [],
            'secretkey' => [],
            'onclick' => [],
            'id' => []
        ],
        'table' => [
            'class' => [],
            'id' => []
        ],
        'thead' => [],
        'tbody' => [
            'id' => []
        ],
        'th' => [
            'class' => []
        ],
        'tr' => [
            'width' => []
        ],
        'td' => [
            'class' => [],
            'colspan' => []
        ],
        'br' => [],
        'strong' => [],
        'b' => [],
        'label' => [
            'class' => [],
            'for' => []
        ],
        'select' => [
            'class' => [],
            'name' => [], 
            'id' => [],
            'onchange' => []
        ],
        'option' => [
            'value' => [],
            'selected' => []
        ] ,
        'p' => [
            'class' => [],
            'id' => []
        ],
        'img' => [
            'alt' => [],
            'src' => []
        ],
        'meta' => [
            'charset' => [],
            'name' => [],
            'content' => []
        ],
        'script' => [
            'type' => []
        ]
    ];
}

?>
