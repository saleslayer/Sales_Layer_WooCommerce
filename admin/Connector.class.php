<?php

class Connector{

	private static $connector;
	public $conn_data = array();

	public function __construct () {

	    global $wpdb;
		$this->db = $wpdb;
		
	}

	/**
	 * Function to get instance of the class.
	 * @return self
	 */
	public static function &get_instance(){

		if( is_null(self::$connector ) ){
		
			self::$connector = new Connector();
		
		}
		
		return self::$connector;

	}

	/**
	 * Function to create Sales Layer table.
	 * @return void
	 */
	public function create_table(){		
		$this->db->query("CREATE TABLE `".SLYR_WC_connector_table."` (".
                         "`cnf_id` int(11) NOT NULL AUTO_INCREMENT, ".
                         "`conn_code` varchar(32) NOT NULL, ".
                         "`conn_secret` varchar(32) NOT NULL, ".
	  					 "`default_cat_id` int(11) NOT NULL , ".
                         "`comp_id` int(20) NOT NULL, ".
                         "`last_update` datetime DEFAULT NULL, ".
                         "`default_language` varchar(6) NOT NULL, ".
                         "`languages` mediumtext NOT NULL, ".
                         "`conn_extra` mediumtext, ".
                         "`auto_sync` int(3) DEFAULT '0', ".
                         "`last_sync` datetime DEFAULT NULL, ".
                         "PRIMARY KEY (`cnf_id`)".
                         ") ENGINE=MyISAM DEFAULT CHARSET=utf8");
	}
	
	/**
	 * Function to create Sales Layer sync data table.
	 * @return void
	 */
	public function create_syncdata_table(){
		
	    $this->db->query("CREATE TABLE `".SLYR_WC_syncdata_table."` (".
	                     "`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id', ".
	                     "`sync_type` varchar(10) NOT NULL COMMENT 'Sync Type', ".
	                     "`item_type` varchar(30) NOT NULL COMMENT 'Item Type', ".
	                     "`sync_tries` int(11) NOT NULL DEFAULT '0' COMMENT 'Sync Tries', ".
	                     "`item_data` longtext COMMENT 'Item Data', ".
	                     "`sync_params` longtext COMMENT 'Sync Parameters', ".
	                     "PRIMARY KEY (`id`)".
	                     ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Sales Layer Sync Data Table'");
		   
	}

	/**
	 * Function to create Sales Layer sync data flag table.
	 * @return void
	 */
	public function create_syncdata_flag_table(){		

		$this->db->query("CREATE TABLE `".SLYR_WC_syncdata_flag_table."` (".
		                 "`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id', ".
		                 "`syncdata_pid` bigint(20) NOT NULL DEFAULT '0' COMMENT 'Sync Data Pid', ".
		                 "`syncdata_last_date` datetime NOT NULL COMMENT 'Sync Data Last Update', ".
		                 "PRIMARY KEY (`id`)".
		                 ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Sales Layer Sync Data Flag Table'");
	
	}

	/**
	 * Function to check if Sales Layer table exists.
	 * @return void
	 */
	public function check_table(){
		
		if (!($this->db->get_var("SHOW TABLES LIKE '".SLYR_WC_connector_table."'"))){

			$this->create_table();
		
		}

	}

	/**
	 * Function to check if Sales Layer sync data table exists. 
	 * @return void
	 */
	public function check_syncdata_table(){

		if (!($this->db->get_var("SHOW TABLES LIKE '".SLYR_WC_syncdata_table."'"))){

			$this->create_syncdata_table();

		}

	}

	/**
	 * Function to check if Sales Layer sync data flag table exists. 
	 * @return void
	 */
	public function check_syncdata_flag_table(){

		if (!($this->db->get_var("SHOW TABLES LIKE '".SLYR_WC_syncdata_flag_table."'"))){

			$this->create_syncdata_flag_table();

		}

	}

	/**
	 * Function to check Sales Layer plugin version.
	 * @return void
	 */
	public function check_version(){

		$ver = get_option('SLYR_WC_version');

		if ($ver < SLYR_WC_version) {

			$connectors = array();

			if ($this->db->get_var("SHOW TABLES LIKE '".SLYR_WC_connector_table."'")){

				$connectors = $this->db->get_results("SELECT * FROM ".SLYR_WC_connector_table);

			}

			$this->db->query("DROP TABLE IF EXISTS ".SLYR_WC_connector_table);
		    
		    $this->create_table();

			if (count($connectors) > 0){

			    foreach ($connectors as $connector) {
			    
			    	$conn_data = json_decode(json_encode($connector), true);
			    	
			        $this->db->query("INSERT INTO `".SLYR_WC_connector_table."` (conn_code, conn_secret, default_cat_id, comp_id, last_update, default_language, languages, conn_extra, auto_sync, last_sync) VALUES ('".$conn_data['conn_code']."', '".$conn_data['conn_secret']."', '".$conn_data['default_cat_id']."', '".$conn_data['comp_id']."', '".$conn_data['last_update']."', '".$conn_data['default_language']."', '".$conn_data['languages']."', '".$conn_data['conn_extra']."','".(isset($conn_data['auto_sync']) ? $conn_data['auto_sync'] : '')."','".(isset($conn_data['last_sync']) ? $conn_data['last_sync'] : '')."')");

			    }
			}

			update_option('SLYR_WC_version', SLYR_WC_version);

		}else{
			$this->check_table();
		}
		
		$this->check_syncdata_table();
		$this->check_syncdata_flag_table();

	}

	/**
	 * Function to get connector row.
	 * @param string $connector_id 				connector id to get data
	 * @return array 							connector/connectors found
	 */
	public function get_connector($connector_id = null){

		if (!is_null($connector_id) && $this->check_connector($connector_id)){
		
			return $this->db->get_row("SELECT * FROM ".SLYR_WC_connector_table." WHERE conn_code = '".$connector_id."' ");

		}

		return $this->db->get_results('SELECT * FROM '.SLYR_WC_connector_table.' ORDER BY cnf_id');

	}

	/**
	 * Function to check if a connector exists.
	 * @param string $connector_id 				connector id
	 * @return boolean 							result of check
	 */
	public function check_connector($connector_id){

		$connector_exists = $this->db->get_results("SELECT * FROM ".SLYR_WC_connector_table." WHERE conn_code = '".$connector_id."'");

		if (!empty($connector_exists)){
		
			return true;
		
		}

		return false;

	}

	/**
	 * Function add a connector to the Sales Layer table.
	 * @param string $connector_id 				connector id
	 * @param string $secret_key 				key of the connector
	 * @return boolean 							result of addition
	 */
	public function add_connector($connector_id, $secret_key){	
		
		if (!$this->db->query("INSERT INTO `".SLYR_WC_connector_table."` (conn_code, conn_secret, default_cat_id, comp_id, last_update, default_language, languages, conn_extra) VALUES ('".$connector_id."', '".$secret_key."', '0', '0', null, '', '', '')")){
			return false;
		}

		return true;

	}

	/**
	 * Function to delete a connector from the Sales Layer table.
	 * @param string $connector_id 				connector id
	 * @return boolean 							result of delete
	 */
	public function delete_connector($connector_id){

		if ($this->check_connector($connector_id)){

			return $this->db->query("DELETE FROM `".SLYR_WC_connector_table."` WHERE conn_code = '".$connector_id."'");
		
		}

		return false;
		
	}

	/**
	 * Function to update connector information.
	 * @param string $connector_id 				connector id
	 * @param array $data 				data to update
	 * @return void
	 */
	public function update_connector($connector_id, $data = array()){

		$connector = $this->get_connector($connector_id);

		if (!empty($data)){

			$this->conn_data = $data;

			$this->db->update( SLYR_WC_connector_table, $data, array('cnf_id' => $connector->cnf_id));

		}

	}

	/**
	 * Function to get field information from a connector.
	 * @param string $connector_id 				connector id
	 * @param string $field_name 				field to obtain information
	 * @return string || boolean 				information of the field
	 */
	public function get_info($connector_id, $field_name){

		$connector = $this->get_connector($connector_id);

		$conn_data = json_decode(json_encode($connector), true);

		if (isset($conn_data[$field_name])){
			
			return $conn_data[$field_name];

		}

		return false;

	}

	/**
	 * Function to update a connector's field value.
	 * @param  string 	$connector_id 				Sales Layer connector id
	 * @param  string 	$field_name					connector field name field
	 * @param  string   $field_value 				connector field value
	 * @return  boolean 							result of update
	 */
	public function update_conn_field($connector_id, $field_name, $field_value) {

		if ($field_name == 'root_category'){ $field_name = 'default_cat_id'; }
	       
	    $forbidden_fields = array('cnf_id', 'conn_code', 'conn_secret');

	    if (!in_array($field_name, $forbidden_fields)){

	    	if ($field_value !== $this->get_info($connector_id, $field_name)){

	    		try{

	    			$this->update_connector($connector_id, array($field_name => $field_value));
	    			return 'correcto';
	    		
	    		}catch(\Exception $e){

	    			sl_debbug('Error updating connector: '.$connector_id.' field: '.$field_name.' to: '.$field_value.' - '.$e->getMessage(), 'error');
	    			return 'error_update';

	    		}

	    	}else{

	    		return 'correcto';

	    	}

	    }

	    return 'error_forbidden';

	}

}