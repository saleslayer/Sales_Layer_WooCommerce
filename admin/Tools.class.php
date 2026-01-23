<?php

include_once(SLYR_WC__PLUGIN_DIR . 'admin/general_functions.php');

/**
 * Tools service.
 *
 * Provides maintenance utilities for Sales Layer WooCommerce integration,
 * including log packaging/deletion and cleanup of pending items/credentials.
 */
class Tools
{

    private static $tools;

    /**
     * Initialize Tools service.
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Get singleton instance.
     * @return Tools Singleton instance
     */
    public static function &get_instance_singleton()
    {
        if (is_null(self::$tools)) {
            self::$tools = new Tools();
        }
        return self::$tools;
    }

    /**
     * Create a zip with existing Sales Layer log files.
     * @return string|false Absolute path to zip file or false when no logs or on failure
     */
    public function downloadSLLogs()
    {
        $log_files = glob(SLYR_WC__LOGS_DIR . '*.dat');
        if (!empty($log_files)) {
            $zip_name = 'sl_logs-' . time() . '.zip';
            $zip_path = SLYR_WC__LOGS_DIR . $zip_name;
            $zip = new ZipArchive();
            if ($zip->open($zip_path, ZipArchive::CREATE) !== true) {
                sl_debug("## Error. Creating SL logs zip file.");
                return false;
            }
            foreach ($log_files as $log_file) {
                $zip->addFile($log_file, basename($log_file));
            }
            $zip->close();
            return $zip_path;
        }
        return false;
    }

    /**
     * Delete all Sales Layer log files.
     * @return bool True if completed (even when nothing to delete), false on failure
     */
    public function deleteSLLogs()
    {

        $log_files = glob(SLYR_WC__LOGS_DIR . '*.dat');
        if (!empty($log_files)) {
            try {
                foreach ($log_files as $log_file) {
                    unlink($log_file);
                }
            } catch (\Exception $e) {
                sl_debug('## Error. Deleting SL logs: ' . $e->getMessage());
                return false;
            }
        }
        return true;
    }

    /**
     * Clear pending sync items from sync data table.
     * @return bool True on success, false on failure
     */
    public function deleteSLPendingItems()
    {
        // $items_processing = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table);
        // if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0){
            if (sl_connection_query('delete', ' DELETE FROM ' . SLYR_WC_syncdata_table) === false) {
                sl_debug("## Error. Deleting SL pending items");
                return false;
            }
                $items_processing = sl_connection_query('read', ' SELECT count(*) as sl_cuenta_registros FROM ' . SLYR_WC_syncdata_table);
                if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0) {
                return false;
            }
        // }
        return true;
    }

    /**
     * Remove Sales Layer credentials (term and post meta keys) across the store.
     * @return bool True on success, false on failure
     */
    public function deleteSLItemsCredentials()
    {
        
        $meta_keys = ['saleslayerid', 'saleslayercompid'];
            foreach ($meta_keys as $meta_key) {
                $meta_key_count = sl_connection_query(
                    'read',
                    ' SELECT count(*) as sl_cuenta_registros FROM ' . WPDB_PREFIX . 'termmeta WHERE meta_key = %s',
                    [$meta_key]
                );
            if (isset($meta_key_count['sl_cuenta_registros']) && $meta_key_count['sl_cuenta_registros'] > 0) {
                $deleted = delete_metadata('term', 0, $meta_key, '', true);
                if ($deleted === false) {
                        sl_debug('## Error. Deleting categories SL credentials: ' . $meta_key);
                    return false;
                }
            }
        }

        $meta_keys = ['_saleslayerid', '_saleslayercompid', '_saleslayerformatid'];
            foreach ($meta_keys as $meta_key) {
                $meta_key_count = sl_connection_query(
                    'read',
                    ' SELECT count(*) as sl_cuenta_registros FROM ' . WPDB_PREFIX . 'postmeta WHERE meta_key = %s',
                    [$meta_key]
                );
            if (isset($meta_key_count['sl_cuenta_registros']) && $meta_key_count['sl_cuenta_registros'] > 0) {
                $deleted = delete_metadata('post', 0, $meta_key, '', true);
                if ($deleted === false) {
                        sl_debug('## Error. Deleting products and variants SL credentials: ' . $meta_key);
                    return false;
                }
            }
        }

        return true;
    }

}