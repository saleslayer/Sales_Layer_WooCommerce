<?php 

include_once(SLYR_WC__PLUGIN_DIR.'admin/general_functions.php');

class Tools {

	private static $tools;

	/**
	 * Construct function
	 */
	function __construct () {

	}

	/**
	 * Function to get instance of the class.
	 * @return self
	 */
	public static function &get_instance_singleton () {

		if (is_null(self::$tools)) {
			
			self::$tools = new Tools();
		
		}
		
		return self::$tools;
	
	}

	/**
	 * Function to download existing SL logs
	 * @return void
	 */
	public function downloadSLLogs()
	{
		$log_files = glob(SLYR_WC__LOGS_DIR . '*.dat');
		if (!empty($log_files)){
			$zip_name = 'sl_logs-'.time().'.zip';
			$zip_path = SLYR_WC__LOGS_DIR.$zip_name;
			$zip = new ZipArchive();
			if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
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

	public function deleteSLLogs()
	{

		$log_files = glob(SLYR_WC__LOGS_DIR.'*.dat');
		if (!empty($log_files)){
			try{
				foreach ($log_files as $log_file){
					unlink($log_file);
				}
			}catch(Exception $e){
				sl_debug("## Error. Deleting SL logs: ".$e->getMessage());
				return false;
			}
		}
		return true;
	}

	public function deleteSLPendingItems()
	{
		// $items_processing = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table);
		// if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0){
			if (sl_connection_query('delete', " DELETE FROM ".SLYR_WC_syncdata_table) === false){
				sl_debug("## Error. Deleting SL pending items");
				return false;
			}
			$items_processing = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table);
			if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0){
				return false;
			}
		// }
		return true;
	}

	public function deleteSLItemsCredentials()
	{
		
		$meta_keys = ['saleslayerid', 'saleslayercompid'];
		foreach ( $meta_keys as $meta_key ) {
			$meta_key_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".WPDB_PREFIX."termmeta WHERE meta_key = '".$meta_key."'");
			if (isset($meta_key_count['sl_cuenta_registros']) && $meta_key_count['sl_cuenta_registros'] > 0){
				delete_metadata('term', 0, $meta_key, '', true );
				if ($deleted === false) {
					sl_debug("## Error. Deleting categories SL credentials: ".$meta_key);
					return false;
				}
			}
		}

		$meta_keys = ['_saleslayerid', '_saleslayercompid', '_saleslayerformatid'];
		foreach ( $meta_keys as $meta_key ) {
			$meta_key_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".WPDB_PREFIX."postmeta WHERE meta_key = '".$meta_key."'");
			if (isset($meta_key_count['sl_cuenta_registros']) && $meta_key_count['sl_cuenta_registros'] > 0){
				$deleted = delete_metadata( 'post', 0, $meta_key, '', true );
				if ($deleted === false) {
					sl_debug("## Error. Deleting products and variants SL credentials: ".$meta_key);
					return false;
				}
			}
		}

		return true;
	}

}