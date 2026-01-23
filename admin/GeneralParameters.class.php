<?php

class GeneralParameters
{

    private static $generalParameters;
    private $options_values = [];

    private $default_options = [
        'API_version' => '1.18',
        'pagination' => '500',
        'debug_level' => '0',
        'all_analytics_data' => '1'
    ];

    public function __construct()
    {
        
        if (!defined("SLYR_WC_general_params")) {
            define('SLYR_WC_general_params', 'slyr_wooc_general_params');
        }
        $this->checkWPOption();
    }

    /**
     * Get the singleton instance.
     *
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
     * Create the WordPress option used to store plugin general parameters.
     * @return void
     */
    public function insertFirstTimeWPOption()
    {

        add_option(SLYR_WC_general_params, json_encode($this->default_options), '', 'no');
    }

    /**
     * Ensure the general parameters option exists and is populated.
     * @return void
     */
    public function checkWPOption()
    {
        
        $row_options_values_gp = get_option(SLYR_WC_general_params, []);
        if ($row_options_values_gp) {
            $this->options_values = json_decode($row_options_values_gp, true);
            foreach ($this->default_options as $default_option_name => $default_option_value){
                if (!isset($this->options_values[$default_option_name])){
                    $this->updateGeneralParameter($default_option_name, $default_option_value);
                }
            }
        }else{
            $this->insertFirstTimeWPOption();
        }
    }

    /**
     * Get options row.
     * @return array Sales-Layer Woo Plugin - General Parameters
     */
    public function getWPOptionsGeneralParameters()
    {

        if (empty($this->options_values)){
            $this->checkWPOption();
        }
        return $this->options_values;
    }

    /**
     * Update a general parameter stored in WordPress options.
     *
     * @param string $field_name  Parameter key.
     * @param mixed  $field_value Parameter value to store.
     * @return string Result status ('success' or 'error_update').
     */
    public function updateGeneralParameter($field_name, $field_value)
    {

        if (empty($field_name) || $field_value === ''){
            return 'error_update';
        }
        
        $this->options_values[$field_name] = $field_value;
        if (!$this->updateWPOptionsGeneralParameters()){
            return 'error_update';
        }

        return 'success';

    }

    /**
     * Delete a general parameter by key.
     *
     * @param string $field_name Parameter key.
     * @return string Result status ('success' or 'error_update').
     */
    public function deleteGeneralParameter($field_name)
    {
        if (empty($field_name)) {
            return 'error_update';
        }

        unset($this->options_values[$field_name]);
        if (!$this->updateWPOptionsGeneralParameters()) {
            return 'error_update';
        }

        return 'success';
    }

    /**
     * Persist general parameters to WordPress options.
     *
     * @return bool True on success, false on failure.
     */
    public function updateWPOptionsGeneralParameters()
    {
        
        $result = update_option(SLYR_WC_general_params, json_encode($this->options_values));									
        return $result;
    }

    /**
     * Get a general parameter value by key.
     *
     * @param string $field_name Parameter key.
     * @return mixed|false Parameter value, or false when not found.
     */
    public function getInfo($field_name)
    {

        $options = $this->getWPOptionsGeneralParameters();

        if (isset($options[$field_name])){			
            return $options[$field_name];
        }

        return false;

    }

    /**
     * Get all general parameters values.
     * @return array 		all general parameters values
     */
    public function getAllGeneralParametersValues()
    {

        $allGeneralParametersValues = [
            'api_versions' => ['1.18', '1.17'],
            'debug_level' => [
                '0' => 'None',
                '1' => 'Error',
                '2' => 'Warning',
                '3' => 'Info',
                '4' => 'Develop'
            ],
            'all_analytics_data' => [
                '0' => 'No',
                '1' => 'Yes'
            ]
        ];

        $base_10k = 2;
        for ($n_item = 0; $n_item < 20; $n_item++){
            if ($n_item == 0) {
                $allGeneralParametersValues['paginations'][] = '500';
            }elseif ($n_item <= 10) {
                $allGeneralParametersValues['paginations'][] = strval($n_item * 1000);
            }else{
                $allGeneralParametersValues['paginations'][] = strval($base_10k * 10000);
                $base_10k++;
            }
        }

        return $allGeneralParametersValues;
    }

}