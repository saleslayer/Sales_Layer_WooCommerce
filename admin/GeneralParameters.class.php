<?php

class GeneralParameters {

	private static $generalParameters;
	private $options_values = [];

	public function __construct ()
	{

	    global $wpdb;
		$this->db = $wpdb;
		
		if (!defined("SLYR_WC_general_params")) {
			define('SLYR_WC_general_params', 'slyr_wooc_general_params');
		}

		$this->checkWPOption();
	}

	/**
	 * Function to get instance of the class.
	 * @return self
	 */
	public static function &get_instance_singleton()
	{

		if (is_null(self::$generalParameters )) {
		
			self::$generalParameters = new GeneralParameters();
		
		}
		
		return self::$generalParameters;

	}

	/**
	 * Function to insert Sales Layer Woo Plugin General Parameters.
	 * @return void
	 */
	public function insertFirstTimeWPOption()
	{
		$default_options = [
			'API_version' => '1.18',
			'pagination' => '500',
			'debbug_level' => '0'
    	];

		$option_values_insert_query = "INSERT INTO wp_options (option_name, option_value, autoload) " .
						 			  "VALUES ('" . SLYR_WC_general_params . "', '" .
									  json_encode($default_options) . "', 'no');";
		$this->db->query($option_values_insert_query);
	}

	/**
	 * Function to check if Sales Layer table exists.
	 * @return void
	 */
	public function checkWPOption()
	{
		
		$option_values_query = "SELECT * FROM wp_options WHERE option_name = '" . SLYR_WC_general_params . "';";
		$row_options_values_gp = json_decode(json_encode($this->db->get_row($option_values_query)), true);

		if ($row_options_values_gp) {

			$this->options_values = json_decode($row_options_values_gp['option_value'], true);

		} else {

			$this->insertFirstTimeWPOption();

		}
	}

	/**
	 * Function to get options row.
	 * @return array Sales-Layer Woo Plugin - General Parameters
	 */
	public function getWPOptionsGeneralParameters()
	{

		$this->checkWPOption();		
		return $this->options_values;

	}

	/**
	 * Function add a connector to the Sales Layer table.
	 * @param string $connector_id 				connector id
	 * @param string $secret_key 				key of the connector
	 * @return string							result of addition
	 */
	public function updateGeneralParameter($field_name, $field_value)
	{

		if (empty($field_name) || empty($field_value)) return 'error_update';
		
		$this->options_values[$field_name] = $field_value;
		if (!$this->updateWPOptionsGeneralParameters()) return 'error_update';

		return 'success';

	}

	/**
	 * Function to delete a connector from the Sales Layer table.
	 * @param string $connector_id 				connector id
	 * @return string 							result of delete
	 */
	public function deleteGeneralParameter($field_name)
	{

		if (empty($field_name)) return 'error_update';

		unset($this->options_value[$field_name]);
		if (!$this->updateWPOptionsGeneralParameters()) return 'error_update';

		return 'success';
		
	}

	/**
	 * Function to update connector information.
	 * @return db-update answer
	 */
	public function updateWPOptionsGeneralParameters()
	{
		
		$result = $this->db->update('wp_options',
									['option_value' => json_encode($this->options_values)],
									['option_name' => SLYR_WC_general_params]);
		return $result;

	}

	/**
	 * Function to get field information from a connector.
	 * @param string $connector_id 				connector id
	 * @param string $field_name 				field to obtain information
	 * @return string || boolean 				information of the field
	 */
	public function getInfo($field_name)
	{

		$options = $this->getWPOptionsGeneralParameters();

		if (isset($options[$field_name])){
			
			return $options[$field_name];

		}

		return false;

	}

}