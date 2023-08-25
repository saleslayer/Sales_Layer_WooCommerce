<?php 

class Format {
	
	public $format_id_field = 'ID';
	public $format_id_products_field = 'ID_products';

	//Define every format attribute
	public    $format_field_sku          			= 'format_sku';
	protected $format_field_description  			= 'format_description';
	protected $format_field_regular_price			= 'format_regular_price';
	protected $format_field_sale_price				= 'format_sale_price';
	protected $format_field_stock					= 'format_stock';
	protected $format_field_manage_stock			= 'format_manage_stock';
	protected $format_field_stock_status			= 'format_stock_status';
	protected $format_field_weight					= 'format_weight';
	protected $format_field_length					= 'format_length';
	protected $format_field_width					= 'format_width';
	protected $format_field_height					= 'format_height';
	protected $format_field_enabled					= 'format_enabled';
	protected $format_field_downloadable			= 'format_downloadable';
	protected $format_field_virtual					= 'format_virtual';
	protected $format_field_shipping_class 			= 'format_shipping_class';
	protected $format_field_image        			= 'format_image';
	protected $format_images_sizes       			= array();
	protected $format_additional_fields 			= array();
	protected $parent_product_attributes 			= array();

	private $sl_data_schema = array();
	public $comp_id;
	
	protected $media_field_names = array();

	protected $media_class;

	protected $debugg_level;

	public function __construct()
	{
		global $debbug_level;
		$this->debbug_level = $debbug_level ?? 0;
	}

	/**
	 * Function set connector's data schema.
	 * @param array $sl_data_schema 		connector's data schema
	 * @return void
	 */
	public function set_data_schema($sl_data_schema){

		$this->sl_data_schema = $sl_data_schema;

	}

	public function set_class_field_value($field_name, $field_value){

		$this->$field_name = $field_value;

	}

	/**
	* Function to get Sales Layer format params data.
	* @param  array $sl_language 					Sales Layer connector language
	* @return array $format_params       			format params to store
	*/
	public function getProductFormatParamsToStore($sl_language){

		$connector = Connector::get_instance();
		
		$format_params = $format_images_sizes = [];

	    $fixed_format_fields = array('ID', 'ID_products', $this->format_field_sku, $this->format_field_description, $this->format_field_regular_price, $this->format_field_sale_price, $this->format_field_stock, $this->format_field_manage_stock, $this->format_field_stock_status, $this->format_field_weight, $this->format_field_length, $this->format_field_width, $this->format_field_height, $this->format_field_enabled, $this->format_field_downloadable, $this->format_field_virtual, $this->format_field_image, $this->format_field_shipping_class);

	    $data_schema = json_decode($this->sl_data_schema, 1);
	    $schema      = $data_schema['product_formats'];

	    if (!empty($schema['fields'][$this->format_field_image]['image_sizes'])) {
	    	$format_field_images_sizes = $schema['fields'][$this->format_field_image]['image_sizes'];
	    	$ordered_image_sizes = order_array_img($format_field_images_sizes);
	    	foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
	    		$format_images_sizes[] = $img_size;
	    	}

	    } else if (!empty($schema['fields']['image_sizes'])) {

	    	$format_field_images_sizes = $schema['fields']['image_sizes'];
	    	$ordered_image_sizes = order_array_img($format_field_images_sizes);
	    	foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
	    		$format_images_sizes[] = $img_size;
	    	}

	    } else {

	    	$format_images_sizes[] = 'IMD';

	    }

	    $format_params['format_fields']['format_images_sizes'] = $format_images_sizes;

        $field_names = ['format_field_sku',
        				'format_field_description',
        				'format_field_regular_price',
        				'format_field_sale_price',
        				'format_field_stock',
        				'format_field_manage_stock',
        				'format_field_stock_status',
        				'format_field_weight',
        				'format_field_length',
        				'format_field_width',
        				'format_field_height',
        				'format_field_enabled',
        				'format_field_downloadable',
        				'format_field_virtual',
        				'format_field_image',
        				'format_field_shipping_class',
    				];

        foreach ($field_names as $field_name){

    	    if (isset($schema['fields'][$this->$field_name]) && $schema['fields'][$this->$field_name]['has_multilingual']) {

    	        $this->$field_name .= '_'.$sl_language;

    	    }

            $format_params['format_fields'][$field_name] = $this->$field_name;
        
        }
		
		foreach ($schema['fields'] as $field_name => $field_props) {
			
			if (!in_array($field_name, $fixed_format_fields)) {
			
				if (!isset($format_params['format_additional_fields'])) $format_params['format_additional_fields'] = [];

				if ($field_props['has_multilingual']) {
		
					$format_params['format_additional_fields'][$field_name] = $field_name.'_'.$connector->conn_data['languages'];
					$this->format_additional_fields[$field_name] = $field_name.'_'.$connector->conn_data['languages'];
					
					if (strtolower($field_props['type']) == 'image' || strtolower($field_props['type']) == 'file'){

						$format_params['product_formats_media_field_names'][$field_name] = $field_name.'_'.$connector->conn_data['languages'];
						$this->media_field_names[$field_name] = $field_name.'_'.$connector->conn_data['languages'];

					}

				}else{
				
					$format_params['format_additional_fields'][$field_name] = $field_name;
					$this->format_additional_fields[$field_name] = $field_name;
				
					if (strtolower($field_props['type']) == 'image' || strtolower($field_props['type']) == 'file'){

						$format_params['product_formats_media_field_names'][$field_name] = $field_name;
						$this->media_field_names[$field_name] = $field_name;

					}

				}
			
			}
		
		}
		
		if ($this->debbug_level > 1 &&
			isset($format_params['format_additional_fields']) &&
			count($format_params['format_additional_fields']) > 0) {
            
            sl_debbug("Format additional fields: ".print_r($format_params['format_additional_fields'], 1));

		}

		return $format_params;

	}

	/**
	* Function to prepare Sales Layer product formats data.
	* @param  array $formats 						  product formats data to organize
	* @return array $product_format_data_to_store     product formats data to store
	*/
	public function prepareProductFormatDataToStore($formats){

		$connector = Connector::get_instance();

		$product_format_data_to_store = [];

		$time_ini_pre_process_formats = microtime(1);
		
		$result_pre_process = pre_process_by_skus('product_variation', $connector->conn_data['comp_id'], $formats);

		if (!empty($result_pre_process)){

			foreach ($result_pre_process as $result_row) {
				
				if (!isset($product_format_data_to_store['not_synced_formats'])){ 

					$product_format_data_to_store['not_synced_formats'] = array();

				}

				$product_format_data_to_store['not_synced_formats'][$formats[$result_row['array_index']][$this->format_id_field]] = $result_row['error_message'];
				unset($formats[$result_row['array_index']]);

			}

		}
	    sl_debbug('### pre_process_formats: '.(microtime(1) - $time_ini_pre_process_formats).' seconds.');

	    $time_ini_reorganize_formats = microtime(1);
		$formats = $this->reorganize_formats_before_store($formats);
	    sl_debbug('### reorganize_formats: '.(microtime(1) - $time_ini_reorganize_formats).' seconds.');

	    if (isset($formats['not_synced_formats']) && !empty($formats['not_synced_formats'])){

	    	foreach ($formats['not_synced_formats'] as $sl_format_id => $error_message) {

	    		$product_format_data_to_store['not_synced_formats'][$sl_format_id] = $error_message;

	    	}

	    	unset($formats['not_synced_formats']);

	    }

	    if (!empty($formats)){

			$formats_to_store = [];

			foreach ($formats as $product_id => $formats_data) {
					
				foreach ($formats_data['formats'] as $format_data) {
					
					$formats_to_store[] = [
						'format_data' => $format_data,
						'sl_attributes' => $formats_data['attributes'],
						'parent_product_attributes' => $formats_data['parent_product_attributes']
					];

				}

			}

			$product_format_data_to_store['product_format_data'] = $formats_to_store;

		}

		return $product_format_data_to_store;

	}

	/**
	 * Function to reorganize product formats.
	 * @param array $formats 		formats to reorganize
	 * @return array 				formats reorganized
	 */
	public function reorganize_formats_before_store($formats){

		if (!empty($this->format_additional_fields)){

			foreach ($formats as $format) {
				
				$sl_parent_product_id	= $format[$this->format_id_products_field];
				$format_data			= $format['data'];

				foreach ($this->format_additional_fields as $format_additional_field => $format_additional_field_lan){

					if (isset($format_data[$format_additional_field_lan])){

						if (isset($this->media_field_names[$format_additional_field])){

							foreach ($format_data[$format_additional_field_lan] as $hash) {
							
							    foreach ($hash as $file) {
							
									$sl_format_value = urldecode($file);
									break 2;
							
							    }
							
							}

						}else{
							
							$sl_format_value = $format_data[$format_additional_field_lan];

							if (is_array($sl_format_value) && !empty($sl_format_value)){
													
								$sl_format_value = reset($sl_format_value);
								
								if (is_array($sl_format_value) && !empty($sl_format_value)){
									    
									$sl_format_value = reset($sl_format_value);
									    
								}
								
							}

						}

						if ((!is_array($sl_format_value) && $sl_format_value !== '') || (is_array($sl_format_value) && !empty($sl_format_value))){

							if (!isset($this->parent_product_attributes[$sl_parent_product_id])){ $this->parent_product_attributes[$sl_parent_product_id] = array(); }

							if (!isset($this->parent_product_attributes[$sl_parent_product_id][$format_additional_field])){

								$this->parent_product_attributes[$sl_parent_product_id][$format_additional_field] = $format_additional_field_lan;

							}

						}

					}

				}

			}

		}

		$new_formats_structure = array(); 

		foreach ($formats as $format) {
			
			$sl_format_id        	= $format[$this->format_id_field];
			$sl_parent_product_id	= $format[$this->format_id_products_field];
			$format_data			= $format['data'];

			$format_ok = true;
			$attribute_data_empty = array();

			if (isset($this->parent_product_attributes[$sl_parent_product_id]) && !empty($this->parent_product_attributes[$sl_parent_product_id])){
			
				foreach ($this->parent_product_attributes[$sl_parent_product_id] as $format_additional_field => $format_additional_field_lan) {
				
					if (isset($format_data[$format_additional_field_lan])){

						if (isset($this->media_field_names[$format_additional_field])){

							foreach ($format_data[$format_additional_field_lan] as $hash) {
							
							    foreach ($hash as $file) {
							
									$sl_format_value = urldecode($file);
									break 2;
							
							    }
							
							}

						}else{
							
							$sl_format_value = $format_data[$format_additional_field_lan];

							if (is_array($sl_format_value) && !empty($sl_format_value)){
													
								$sl_format_value = reset($sl_format_value);
								
								if (is_array($sl_format_value) && !empty($sl_format_value)){
									    
									$sl_format_value = reset($sl_format_value);
									    
								}
								
							}

						}

						if ((!is_array($sl_format_value) && $sl_format_value === '') || (is_array($sl_format_value) && empty($sl_format_value))){

							$attribute_data_empty[] = 'The format attribute '.$format_additional_field.' is empty.'; 
							$format_ok = false;
							continue;

						}

						$format_additional_field_sanitized = sanitize_title($format_additional_field);

						$sl_format_value = sanitize_text_field( wp_unslash( $sl_format_value ) );
						
						$format['data'][$format_additional_field_lan] = $sl_format_value;

						if (!isset($new_formats_structure[$sl_parent_product_id]['attributes'][$format_additional_field_sanitized])){
							$new_formats_structure[$sl_parent_product_id]['attributes'][$format_additional_field_sanitized] = array();
						}

						if (!in_array($sl_format_value, $new_formats_structure[$sl_parent_product_id]['attributes'][$format_additional_field_sanitized])){

							$new_formats_structure[$sl_parent_product_id]['attributes'][$format_additional_field_sanitized][] = $sl_format_value;
							
						}

					}

				}
			
			}else{

				$format_ok = false;

			}

			if ($format_ok){

				$new_formats_structure[$sl_parent_product_id]['formats'][] = $format;

				if (isset($this->parent_product_attributes[$sl_parent_product_id]) && !empty($this->parent_product_attributes[$sl_parent_product_id])){

					$new_formats_structure[$sl_parent_product_id]['parent_product_attributes'] = $this->parent_product_attributes[$sl_parent_product_id];

				}

			}else{

				$error_message = $format_data[$this->format_field_sku]." - The format attribute data is empty/wrong.";
				
				if (!empty($attribute_data_empty)){

					foreach ($attribute_data_empty as $error_msg) {

						$error_message .= "\r\n".$format_data[$this->format_field_sku]." - ".print_R($error_msg,1);
				
					}

				}
				
				$new_formats_structure['not_synced_formats'][$sl_format_id] = $error_message;

			}

		}

		return $new_formats_structure;

	}

	/**
	 * Function to synchronize a stored product format.
	 * @param array $format 		format to synchronize
	 * @return string 				result of synchronization
	 */
	public function sync_stored_product_format($format){

		$time_ini_formats_per_prod = microtime(1);

		$sl_format_id        	= $format['format_data'][$this->format_id_field];
		$sl_parent_product_id	= $format['format_data'][$this->format_id_products_field];
		$format_data			= $format['format_data']['data'];

		// $time_ini_find_saleslayer_parent_product = microtime(1);
		$wp_parent_product = find_saleslayer_product($sl_parent_product_id, $this->comp_id);
		// sl_debbug('## time_find_saleslayer_parent_product: '.(microtime(1) - $time_ini_find_saleslayer_parent_product).' seconds.', 'timer');
		
		if (!$wp_parent_product){

			sl_debbug('## Error. '.$format_data[$this->format_field_sku]." - The format parent does not exist.");
			return 'item_not_updated';

		}else{

			// $time_ini_get_parent_attributes = microtime(1);
			$parent_attributes = $this->get_parent_attributes($wp_parent_product['ID'], $format['sl_attributes']);
			// sl_debbug('## time_get_parent_attributes: '.(microtime(1) - $time_ini_get_parent_attributes).' seconds.', 'timer');
			
		}
		
		// $time_ini_sync_parent_data = microtime(1);
		$this->sync_parent_data($wp_parent_product['ID'], $parent_attributes);
		// sl_debbug('## time_sync_parent_data: '.(microtime(1) - $time_ini_sync_parent_data).' seconds.', 'timer');

		$time_ini_format_core_data = microtime(1);
		
		$sl_format_attributes = array();
		
		if (isset($format['parent_product_attributes']) && !empty($format['parent_product_attributes'])){

			foreach ($format['parent_product_attributes'] as $format_additional_field => $format_additional_field_lan) {

				$format_additional_field_sanitized = sanitize_title($format_additional_field);
				$attribute_taxonomy_name = wc_attribute_taxonomy_name($format_additional_field_sanitized);

				if (!taxonomy_exists($attribute_taxonomy_name)){

					$attribute_taxonomy_name = '';

					if (strpos($format_additional_field_sanitized, '_') !== false){

						$format_additional_field_sanitized_hyphen = str_replace('_', '-', $format_additional_field_sanitized);
						$attribute_taxonomy_name_hyphen = wc_attribute_taxonomy_name($format_additional_field_sanitized_hyphen);

						if (taxonomy_exists($attribute_taxonomy_name_hyphen)){

							$attribute_taxonomy_name = $attribute_taxonomy_name_hyphen;

						}

					}

				}

				if ($attribute_taxonomy_name !== ''){

					$sl_format_attributes['attribute_'.$attribute_taxonomy_name] = sanitize_title($format_data[$format_additional_field_lan]);

				}

			}

		}

		$wp_format = find_saleslayer_format($sl_parent_product_id, $this->comp_id, $sl_format_id);
		if (!$wp_format){
			
			$wp_format = $this->find_format_by_attributes($sl_parent_product_id, $this->comp_id, $sl_format_id, $sl_format_attributes);
			if (!$wp_format){

				$time_ini_format_create = microtime(1);
				$this->create_format($sl_parent_product_id, $this->comp_id, $sl_format_id, $wp_parent_product['ID'], $wp_parent_product['post_title']);
				sl_debbug('## time_format_create: '.(microtime(1) - $time_ini_format_create).' seconds.', 'timer');
			
			}
			
			$wp_format = find_saleslayer_format($sl_parent_product_id, $this->comp_id, $sl_format_id);
			
			if (!$wp_format){
				
				sl_debbug('## Error. '.$format_data[$this->format_field_sku]." - The format could not been created.");
				return 'item_not_updated';
			
			}
		
		}

		if ($this->debbug_level) sl_debbug(" > Updating product format ID: $sl_format_id (parent: $sl_parent_product_id)");

		if ($this->debbug_level > 1) sl_debbug(" SKU ({$this->format_field_sku}): ".$format_data[$this->format_field_sku]);

		//Format attributes
		foreach ($sl_format_attributes as $format_name => $format_value) {
			
			if (!isset($wp_format[$format_name]) || (isset($wp_format[$format_name]) && $wp_format[$format_name] != $format_value)){

				sl_update_post_meta( $wp_format['ID'], $format_name, $format_value );

			}

		}

		//Format post fields
		$updated_format_data = array('ID' => $wp_format['ID']);
		$format_data_modified = false;

		if (isset($format_data[$this->format_field_enabled])){
			
			$sl_status = $format_data[$this->format_field_enabled];
			
			if (is_array($sl_status)) $sl_status = reset($sl_status);
			
			if ($sl_status !== ''){

				if (!is_numeric($sl_status)){

					$sl_status = trim(strtolower($sl_status));
					if (in_array($sl_status, array('enabled', 'habilitado', 'habilitada', 'activated', 'activado', 'activada'))) $sl_status = 'publish';
					if (in_array($sl_status, array('disabled', 'deshabilitado', 'deshabilitada', 'deactivated', 'desactivado', 'desactivada'))) $sl_status = 'private';

				} 
				
				$wp_status = array('publish', 'private');
				
				if (!in_array( $sl_status , $wp_status, true)){
				
					$sl_bool_status = sl_validate_boolean($sl_status);
					
					if ($sl_bool_status == 'yes'){
					
						$sl_status = 'publish';
					
					}else{
						
						$sl_status = 'private';

					}

				}

				if ($sl_status != $wp_format['post_status']){

					$updated_format_data['post_status'] = $sl_status;
					$format_data_modified = true;

				}

			}

		}

		if ($format_data_modified){
			
			sl_wp_update_post($updated_format_data, true);

		}

		//Format meta data
		if (isset($format_data[$this->format_field_sku]) && (!isset($wp_format['_sku']) || (isset($wp_format['_sku']) && $wp_format['_sku'] != $format_data[$this->format_field_sku]))){

			sl_update_post_meta( $wp_format['ID'], '_sku', $format_data[$this->format_field_sku]);

		}
		
		if (isset($format_data[$this->format_field_description]) && (!isset($wp_format['_variation_description']) || (isset($wp_format['_variation_description']) && $wp_format['_variation_description'] != $format_data[$this->format_field_description]))){

			sl_update_post_meta( $wp_format['ID'], '_variation_description', $format_data[$this->format_field_description]);

		}
		
		if (!isset($wp_format['_product_version']) || (isset($wp_format['_product_version']) && $wp_format['_product_version'] != WC_VERSION)){

			sl_update_post_meta( $wp_format['ID'], '_product_version', WC_VERSION );

		}
		
		if (isset($format_data[$this->format_field_weight])){

			$sl_weight = $format_data[$this->format_field_weight];

			if (!is_numeric($sl_weight) && filter_var($sl_weight, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_weight = filter_var($sl_weight, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				
			}

			if (!isset($wp_format['_weight']) || (isset($wp_format['_weight']) && $wp_format['_weight'] != $sl_weight)){

				sl_update_post_meta( $wp_format['ID'], '_weight', $sl_weight);

			}

		}
		
		if (isset($format_data[$this->format_field_length])){

			$sl_length = $format_data[$this->format_field_length];

			if (!is_numeric($sl_length) && filter_var($sl_length, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_length = filter_var($sl_length, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

			}

			if (!isset($wp_format['_length']) || (isset($wp_format['_length']) && $wp_format['_length'] != $sl_length)){

				sl_update_post_meta( $wp_format['ID'], '_length', $sl_length);

			}

		}
		
		if (isset($format_data[$this->format_field_width])){

			$sl_width = $format_data[$this->format_field_width];

			if (!is_numeric($sl_width) && filter_var($sl_width, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_width = filter_var($sl_width, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				
			}

			if (!isset($wp_format['_width']) || (isset($wp_format['_width']) && $wp_format['_width'] != $sl_width)){

				sl_update_post_meta( $wp_format['ID'], '_width', $sl_width);

			}

		}
		
		if (isset($format_data[$this->format_field_height])){

			$sl_height = $format_data[$this->format_field_height];

			if (!is_numeric($sl_height) && filter_var($sl_height, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_height = filter_var($sl_height, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				
			}

			if (!isset($wp_format['_height']) || (isset($wp_format['_height']) && $wp_format['_height'] != $sl_height)){

				sl_update_post_meta( $wp_format['ID'], '_height', $sl_height);

			}

		}
		
		if (isset($format_data[$this->format_field_downloadable])){
			
			$sl_downloadable = sl_validate_boolean($format_data[$this->format_field_downloadable]);
			
			if ((isset($wp_format['_downloadable']) && $wp_format['_downloadable'] != $sl_downloadable) || (!isset($wp_format['_downloadable']))){

				sl_update_post_meta( $wp_format['ID'], '_downloadable', $sl_downloadable);
			
			}

		}
		
		if (isset($format_data[$this->format_field_virtual])){
			
			$sl_virtual = sl_validate_boolean($format_data[$this->format_field_virtual]);
			
			if ((isset($wp_format['_virtual']) && $wp_format['_virtual'] != $sl_virtual) || (!isset($wp_format['_virtual']))){

				sl_update_post_meta( $wp_format['ID'], '_virtual', $sl_virtual);
			
			}

		}
		
		if (isset($format_data[$this->format_field_shipping_class])){

			if (taxonomy_exists('product_shipping_class')){

				$shipping_class = Shipping_class::get_instance();

				$wp_format_shipping_class = '';

				$wp_format_shipping_class_term = wp_get_object_terms( $wp_format['ID'], 'product_shipping_class');
				if (is_array($wp_format_shipping_class_term) && isset($wp_format_shipping_class_term[0])){
					$wp_format_shipping_class_term = $wp_format_shipping_class_term[0];
				}
				
				if (is_object($wp_format_shipping_class_term)){
				
					$wp_format_shipping_class = isset( $wp_format_shipping_class_term->term_id ) ? $wp_format_shipping_class_term->term_id : 0;
				
				}else if (is_array($wp_format_shipping_class_term) && isset($wp_format_shipping_class_term[0]) && isset($wp_format_shipping_class_term[0]['term_id'])){
				
					$wp_format_shipping_class = $wp_format_shipping_class_term[0]['term_id'];
				
				}
			
	        	$sl_format_shipping_class = '';

	        	if (is_array($format_data[$this->format_field_shipping_class]) && !empty($format_data[$this->format_field_shipping_class])){

	        		$sl_format_shipping_class = reset($format_data[$this->format_field_shipping_class]);

	        	}else if (!is_array($format_data[$this->format_field_shipping_class]) && $format_data[$this->format_field_shipping_class] != ''){

	        		$sl_format_shipping_class = $format_data[$this->format_field_shipping_class];

	        	}

	        	$sl_id_format_shipping_class = '';
	    		
	    		if ($sl_format_shipping_class != ''){

	        		if (is_numeric($sl_format_shipping_class)){

	        			if (isset($shipping_class->shipping_classes[$sl_format_shipping_class])){

	        				$sl_id_format_shipping_class = $sl_format_shipping_class;

	        			}
	                
			        }else{

			        	$sl_format_shipping_class_lower = trim(strtolower($sl_format_shipping_class));
	    		
	    		    	foreach ($shipping_class->shipping_classes as $shipping_class_term_id => $shipping_class_data) {
			        		
			        		if ($shipping_class_data['name'] == $sl_format_shipping_class || $shipping_class_data['slug'] == $sl_format_shipping_class || strtolower($shipping_class_data['name']) == $sl_format_shipping_class_lower || strtolower($shipping_class_data['slug']) == $sl_format_shipping_class_lower){

			        			$sl_id_format_shipping_class = $shipping_class_term_id;
			        			break;

			        		}

			        	}
	            	
	            	}

	            	if ($sl_id_format_shipping_class == ''){

	            		$sl_id_format_shipping_class = $shipping_class->create_shipping_class($sl_format_shipping_class);

	            	}

	            }

	    		if ($wp_format_shipping_class != $sl_id_format_shipping_class){

	            	if ($sl_id_format_shipping_class == '' && $wp_format_shipping_class != ''){

	            		wp_delete_object_term_relationships( $wp_format['ID'], 'product_shipping_class' );
	            		
	            	}else if ($sl_id_format_shipping_class != ''){

	            		sl_wp_set_object_terms( $wp_format['ID'], $sl_id_format_shipping_class, 'product_shipping_class');
	            		
	            	}

	            }

			}else{

				sl_debbug('## Error. Product shipping class taxonomy does not exist.');

			}

        }

		$wp_price = $wp_sale_price = $wp_regular_price = false;

		if (isset($format_data[$this->format_field_sale_price])){

			$sl_sale_price = $format_data[$this->format_field_sale_price];

			if ($sl_sale_price === ''){

				$wp_sale_price = $sl_sale_price;

			}else{

				if (!is_numeric($sl_sale_price) && filter_var($sl_sale_price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
					
					$sl_sale_price = filter_var($sl_sale_price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			
				}

				if (!is_numeric($sl_sale_price)){

					$wp_sale_price = false;

				}else{

					$wp_sale_price = $sl_sale_price;

				}

			}

		}

		if ($wp_sale_price === false && isset($wp_format['_sale_price'])){

			$wp_sale_price = $wp_format['_sale_price'];

		}

		if (isset($format_data[$this->format_field_regular_price])){

			$sl_regular_price = $format_data[$this->format_field_regular_price];

			if ($sl_regular_price === ''){

				$wp_regular_price = $sl_regular_price;

			}else{

				if (!is_numeric($sl_regular_price) && filter_var($sl_regular_price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
					
					$sl_regular_price = filter_var($sl_regular_price, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
					
				}

				if (!is_numeric($sl_regular_price)){

					$wp_regular_price = false;

				}else{

					$wp_regular_price = $sl_regular_price;

				}

			}

		}
		
		if ($wp_regular_price === false && isset($wp_format['_regular_price'])){

			$wp_regular_price = $wp_format['_regular_price'];

		}
		
		if ($wp_regular_price !== false){

			if ($wp_regular_price === ''){

				$wp_sale_price = '';
				$wp_price = '';

			}else{

				$wp_price = $wp_regular_price;

			}

		}
		
		if ($wp_sale_price !== false){

			if ($wp_sale_price === ''){

				if ($wp_regular_price !== false){

					$wp_price = $wp_regular_price;

				}

			}else{

				if ($wp_regular_price !== false && $wp_sale_price < $wp_regular_price){

					$wp_price = $wp_sale_price;

				}else{

					$wp_sale_price = '';

				}

			}

		}
		
		$prices_updated = false;
		
		if ($wp_sale_price !== false && (!isset($wp_format['_sale_price']) || (isset($wp_format['_sale_price']) && $wp_format['_sale_price'] !== $wp_sale_price))){

			sl_update_post_meta( $wp_format['ID'], '_sale_price', $wp_sale_price);
			$prices_updated = true;

		}

		if ($wp_regular_price !== false && (!isset($wp_format['_regular_price']) || (isset($wp_format['_regular_price']) && $wp_format['_regular_price'] !== $wp_regular_price))){

			sl_update_post_meta( $wp_format['ID'], '_regular_price', $wp_regular_price);
			$prices_updated = true;

		}

		if ($wp_price !== false && (!isset($wp_format['_price']) || (isset($wp_format['_price']) && $wp_format['_price'] !== $wp_price))){

			sl_update_post_meta( $wp_format['ID'], '_price', $wp_price);
			$prices_updated = true;
			
		}

		if ($prices_updated){

			$time_ini_parent_price = microtime(1);

			try{
				
				// $wp_parent_product = wc_get_product( $wp_parent_product['ID'] );
				// $wp_parent_product->save();

				wc_delete_product_transients( $wp_format['ID'] );
    			wc_delete_product_transients( $wp_parent_product['ID'] );

			}catch(\Exception $e){

				sl_debbug('## Error. Clearing/refreshing the parent product price cache: '.$e->getMessage());

			}

			sl_debbug('## time_parent_price: '.(microtime(1) - $time_ini_parent_price).' seconds.', 'timer');

		}

		$check_stock = true;
		
		if (isset($format_data[$this->format_field_manage_stock])){

			$sl_format_manage_stock = $format_data[$this->format_field_manage_stock];

			if (is_array($sl_format_manage_stock) && !empty($sl_format_manage_stock)){
				
				$sl_format_manage_stock = reset($sl_format_manage_stock);
				
				if (!is_bool($sl_format_manage_stock)){
				
					$sl_format_manage_stock = trim(strtolower($sl_format_manage_stock));
				
				}
			
			}else if (!is_array($sl_format_manage_stock) && $sl_format_manage_stock !== '' && !is_bool($sl_format_manage_stock)){
				
				$sl_format_manage_stock = trim(strtolower($sl_format_manage_stock));
			
			}
		
			if ($sl_format_manage_stock !== ''){

				$sl_manage_stock = sl_validate_boolean($sl_format_manage_stock);
				
				if (!isset($wp_format['_manage_stock']) || (isset($wp_format['_manage_stock']) && $wp_format['_manage_stock'] != $sl_manage_stock)){
		
					sl_update_post_meta( $wp_format['ID'], '_manage_stock', $sl_manage_stock);
				
				}

				if ($sl_manage_stock == 'no'){
		
					$check_stock = false;

					if (!isset($wp_format['_stock']) || (isset($wp_format['_stock']) && !in_array($wp_format['_stock'], array('', null)))){
		
						sl_update_post_meta( $wp_format['ID'], '_stock', '');

						if (!isset($format_data[$this->format_field_stock_status]) || (isset($format_data[$this->format_field_stock_status]) && $format_data[$this->format_field_stock_status] === '')){
		
							if (!isset($wp_format['_stock_status']) || (isset($wp_format['_stock_status']) && $wp_format['_stock_status'] !== 'outofstock')){
		
								sl_update_post_meta( $wp_format['ID'], '_stock_status', 'outofstock');

							}

						}

					}			

					if (isset($format_data[$this->format_field_stock_status])){

						$sl_format_stock_status = $format_data[$this->format_field_stock_status];
						
						if (is_array($sl_format_stock_status) && !empty($sl_format_stock_status)){
						
							$sl_format_stock_status = trim(strtolower(reset($sl_format_stock_status)));
						
						}else if (!is_array($sl_format_stock_status) && $sl_format_stock_status !== ''){

							$sl_format_stock_status = trim(strtolower($sl_format_stock_status));
						
						}
						
						if ($sl_format_stock_status !== ''){

							$format_stock_status = 'outofstock';

							if (preg_match('~(reservado|backorder)~', strtolower($sl_format_stock_status))) {
							
							    $format_stock_status = 'onbackorder';
							
							}

							if (preg_match('~(existencias|in)~', strtolower($sl_format_stock_status))) {
							
							    $format_stock_status = 'instock';
							
							}

							if (preg_match('~(agotado|out)~', strtolower($sl_format_stock_status))) {
								
								$format_stock_status = 'outofstock';
							
							}
							
							if (!isset($wp_format['_stock_status']) || (isset($wp_format['_stock_status']) && $format_stock_status != $wp_format['_stock_status'])){

								sl_update_post_meta( $wp_format['ID'], '_stock_status', $format_stock_status);

							}

						}

					}

				}

			}

		}

		if ($check_stock){

			if (isset($format_data[$this->format_field_stock]) && $format_data[$this->format_field_stock] !== ''){

				$sl_stock = $format_data[$this->format_field_stock];

				$sl_stock_not_num = false;

				if (!is_numeric($sl_stock)){

					$sl_stock_not_num = true;
					//wc_stock_amount to remove decimals as commas instead of dots 
					$sl_stock = wc_stock_amount($sl_stock);

				}

				if (is_numeric($sl_stock) && (!$sl_stock_not_num || ($sl_stock_not_num && $sl_stock !== 0))){

					//wc_stock_amount to delete decimals
					$sl_stock = wc_stock_amount($sl_stock);

					if (!isset($wp_format['_manage_stock']) || (isset($wp_format['_manage_stock']) && $wp_format['_manage_stock'] == 'no')){
						
						sl_update_post_meta( $wp_format['ID'], '_manage_stock', 'yes'); 
					
					}

					if (!isset($wp_format['_stock']) || (isset($wp_format['_stock']) && $wp_format['_stock'] != $sl_stock)){
						
						if ((!isset($wp_format['_stock_status']) || (isset($wp_format['_stock_status']) && $wp_format['_stock_status'] == 'outofstock')) && $sl_stock > 0){
						
							sl_update_post_meta( $wp_format['ID'], '_stock_status', 'instock'); 
						
						}else if ((!isset($wp_format['_stock_status']) || (isset($wp_format['_stock_status']) && $wp_format['_stock_status'] == 'instock')) && $sl_stock <= 0){
						
							sl_update_post_meta( $wp_format['ID'], '_stock_status', 'outofstock');
						
						}
						
						sl_update_post_meta( $wp_format['ID'], '_stock', $sl_stock);

					}

				}

			}
		
		}

		sl_debbug('## time_format_core_data: '.(microtime(1) - $time_ini_format_core_data).' seconds.', 'timer');
				
		$time_ini_format_images = microtime(1);
		//Format images
		if (!empty($format_data[$this->format_field_image])) {

			if (is_null($this->media_class)) $this->media_class = Media_class::get_instance();

			$wp_thumbnail_id = $wp_format_thumbnail_name = $wp_format_thumbnail_filesize = $wp_gallery_ids = '';

			$sl_format_images = $format_data[$this->format_field_image];

			if(count($sl_format_images) > 0) {

				if (isset($wp_format['_thumbnail_id'])){

					$wp_thumbnail_id = $wp_format['_thumbnail_id'];
					if (is_array($wp_thumbnail_id) && isset($wp_thumbnail_id[0])){ 
					
						$wp_thumbnail_id = $wp_thumbnail_id[0];
					
					}

				}

				if (!in_array($wp_thumbnail_id, array('', 0, null, false))){
					
					$wp_format_thumbnail_url = wp_get_attachment_url($wp_thumbnail_id);
					$wp_parse_format_thumbnail_url = pathinfo($wp_format_thumbnail_url);
					$wp_format_thumbnail_name = $wp_parse_format_thumbnail_url['basename'];
					$wp_format_thumbnail_filesize = $this->media_class->read_image_file_size($wp_format_thumbnail_url);
				
				}

				foreach ($this->format_images_sizes as $img_format) {

					foreach ($sl_format_images as $sl_format_image) {
						
						if (!empty($sl_format_image[$img_format])){

							$image_url = $sl_format_image[$img_format];
							$filesize_image = $this->media_class->read_image_file_size($image_url);
							if (!$filesize_image){ continue; }

							$parse_url_image = pathinfo($image_url);
							$parse_url_image_basename = urldecode($parse_url_image['basename']);
									
							if ($parse_url_image_basename == $wp_format_thumbnail_name){

								if (!$wp_format_thumbnail_filesize || ($wp_format_thumbnail_filesize !== false && $wp_format_thumbnail_filesize !== $filesize_image)){

									if (!$this->media_class->update_media($image_url, $wp_thumbnail_id, true)){
								
										continue;
								
									}
								
								}

							}else{

								$thumb_id = $this->media_class->get_thumbnail_id_by_title($parse_url_image_basename);
								
								if ($thumb_id === 0){
								
									$new_wp_thumbnail_id = $this->media_class->fetch_media($image_url, $wp_format['ID'], true);
									sl_update_post_meta($wp_format['ID'], '_thumbnail_id', $new_wp_thumbnail_id);

								}else{

									$wp_thumbnail_url = wp_get_attachment_url($thumb_id);
									$wp_format_thumbnail_filesize = $this->media_class->read_image_file_size($wp_thumbnail_url);
								
									if (!$wp_format_thumbnail_filesize || ($wp_format_thumbnail_filesize !== false && $wp_format_thumbnail_filesize !== $filesize_image)){
								
										if (!$this->media_class->update_media($image_url, $thumb_id, true)){

											continue;
								
										}
								
									}

									sl_update_post_meta($wp_format['ID'], '_thumbnail_id', $thumb_id);

								}

								if (!in_array($wp_thumbnail_id, array('', 0, null, false))){ $this->media_class->delete_media($wp_thumbnail_id); }

							}

							break 2;

						}

					}

				}

			}

		}
		sl_debbug('## time_format_images: '.(microtime(1) - $time_ini_format_images).' seconds.', 'timer');	
		
		sl_debbug('### time_formats_per_prod: '.(microtime(1) - $time_ini_formats_per_prod).' seconds.', 'timer');

		return 'item_updated';

	}

	/**
	 * Function to get a product parent attributes.
	 * @param  integer $wp_product_id  		WP product id
	 * @param  array $sl_attributes  		SL attributes of product format
	 * @return array                 		WP product attributes
	 */
	public function get_parent_attributes($wp_product_id, $sl_attributes){

		foreach ($sl_attributes as $sl_attribute_name => $sl_attributes_values) {

			$attribute_taxonomy_name_exists = false;

			$attribute_taxonomy_name = wc_attribute_taxonomy_name($sl_attribute_name);

			if (!taxonomy_exists($attribute_taxonomy_name)){

				if (strpos($sl_attribute_name, '_') !== false){

					$sl_attribute_name_hyphen = str_replace('_', '-', $sl_attribute_name);
					$attribute_taxonomy_name_hyphen = wc_attribute_taxonomy_name($sl_attribute_name_hyphen);

					if (taxonomy_exists($attribute_taxonomy_name_hyphen)){

						$attribute_taxonomy_name_exists = true;

					}

				}

			}else{

				$attribute_taxonomy_name_exists = true;

			}

			if (!$attribute_taxonomy_name_exists){

				unset($sl_attributes[$sl_attribute_name]);

			}

		}

		$products_attributes_data = array();

		$wp_product = wc_get_product( $wp_product_id );
		$wp_product_attributes = $wp_product->get_attributes();
		
		if (!empty($wp_product_attributes)){

			foreach ($wp_product_attributes as $wp_product_attribute_name => $wp_product_attribute) {
			    
			    $wp_product_attribute_name_sanitized = sanitize_title($wp_product_attribute_name);			
			    
			    if (strpos($wp_product_attribute_name_sanitized, 'pa_') === 0){
			        
			        $cut_wp_product_attribute = substr($wp_product_attribute_name_sanitized, 3, strlen($wp_product_attribute_name_sanitized));
			    	
			    	if (taxonomy_exists($wp_product_attribute_name_sanitized)){

			    	    $products_attributes_data[$cut_wp_product_attribute] = wp_get_object_terms( $wp_product_id,  $wp_product_attribute_name_sanitized, array('fields' => 'names'));

			        }

			    }

			}

		}

		if (!empty($products_attributes_data)){

			foreach ($products_attributes_data as $product_attribute_name => $product_attribute_values) {
				
				if (isset($sl_attributes[$product_attribute_name])){

					$sl_attributes[$product_attribute_name] = array_filter(array_unique(array_merge($sl_attributes[$product_attribute_name], $product_attribute_values)));

				}

			}

		}

		return $sl_attributes;

	}

	/**
	 * Function to find a format by attributes.
	 * @param string $product_id 				Sales Layer id
	 * @param string $comp_id 					Sales Layer company id
	 * @param string $format_id 				Sales Layer format id
	 * @param array  $format_attributes 		format attributes to match
	 * @return boolean 							result of finding
	 */
	public function find_format_by_attributes($product_id, $comp_id, $format_id, $format_attributes){

		$meta_query = array();
		foreach ($format_attributes as $format_name => $format_value) {
			
			array_push($meta_query, array('key' => $format_name, 'value' => $format_value, 'compare' => '='));
			
		}
		
		$posts = get_posts(
			array(
			    'post_type' => 'product_variation',
			    'meta_query' => $meta_query,
			    'post_status' => array('publish', 'pending', 'draft', 'private', 'trash')
	    	)
		);

		if( is_wp_error( $posts ) ) {

			sl_debbug('## Error. find_format_by_attributes: '.$posts->get_error_message());

		}else if (!empty($posts)){
	    	
	    	$wp_format = json_decode(json_encode($posts[0]), true);
	    	
	    	$wp_saleslayerid = get_post_meta($wp_format['ID'], '_saleslayerid', true);
			$wp_saleslayercompid = get_post_meta($wp_format['ID'], '_saleslayercompid', true);
			$wp_saleslayerformatid = get_post_meta($wp_format['ID'], '_saleslayerformatid', true);
			
			if (in_array($wp_saleslayerid, array(0,'',null)) && in_array($wp_saleslayercompid, array(0,'',null)) && in_array($wp_saleslayerformatid, array(0,'',null))){
			
				sl_update_post_meta($wp_format['ID'], '_saleslayerid', $product_id);
				sl_update_post_meta($wp_format['ID'], '_saleslayercompid', $comp_id);
				sl_update_post_meta($wp_format['ID'], '_saleslayerformatid', $format_id);

				sl_wp_update_post(array('ID' => $wp_format['ID'], 'post_status' => 'publish'), true);

				return true;

			}else if ($wp_saleslayerid == $product_id && $wp_saleslayercompid == $comp_id && $wp_saleslayerformatid == $format_id){
			
				return true;

			}

		}

		return false;

	}

	/**
	 * Function to create a format.
	 * @param string $sl_product_id 		Sales Layer id
	 * @param string $comp_id				Sales Layer company id
	 * @param string $sl_format_id 			Sales Layer format id
	 * @param string $parent_id 			format parent id
	 * @param string $parent_title 			format parent title
	 * @return boolean 						result of creation
	 */
	public function create_format($sl_product_id, $comp_id, $sl_format_id, $parent_id, $parent_title){

		$children_args = array(
			'post_parent' => $parent_id,
			'post_type'   => 'product_variation', 
			'post_status' => array('publish', 'pending', 'draft', 'private', 'trash')
		);

		$children = get_children( $children_args );
		$count_children = count($children) + 1;
		
		$post_name = 'product-'.$parent_id.'-variation-'.$count_children;
		
		$format_id = wp_insert_post( array(
		    'post_parent' => $parent_id,
		    'post_status' => 'publish',
		    'post_type' => 'product_variation',
		    'post_name' => $post_name
		) );

		if( is_wp_error( $format_id ) ) {

			sl_debbug('## Error. create_format: '.$format_id->get_error_message());

		}else if ($format_id){
			
			$format_data = array('ID' => $format_id);
			$format_data['post_title'] = 'Variation #'.$format_id.' of '.$parent_title;
			wp_update_post($format_data);

			sl_update_post_meta($format_id, '_saleslayerid', $sl_product_id);
			sl_update_post_meta($format_id, '_saleslayercompid', $comp_id);
			sl_update_post_meta($format_id, '_saleslayerformatid', $sl_format_id);

			if ($this->debbug_level) sl_debbug("Format created!");
			return true;

		}

		return false;

	}

	/**
	 * Function to synchronize parent attributes.
	 * @param string $wp_parent_product 		Parent product
	 * @param string $sl_parent_attributes		Parent attributes to synchronize Sales Layer company id
	 * @return void
	 */
	public function sync_parent_data($wp_parent_product_id, $sl_parent_attributes){

		$wp_parent_type = wp_get_object_terms( $wp_parent_product_id,  'product_type', array('fields' => 'slugs'));

		if ($wp_parent_type[0] !== 'variable'){

			sl_wp_set_object_terms( $wp_parent_product_id, 'variable', 'product_type' );
		
		}
		
		$wp_product_attributes = get_post_meta( $wp_parent_product_id, '_product_attributes' );
		
		$wp_product_attributes_count = 0;
		if (!empty($wp_product_attributes)){

			$wp_product_attributes = $wp_product_attributes[0];
			$wp_product_attributes_count = count($wp_product_attributes);
			$wp_product_attributes_used = array();

		}

		if (count($sl_parent_attributes) > 0){
			
			foreach ($sl_parent_attributes as $attribute_name => $attribute_values){

				$attribute_taxonomy_name = wc_attribute_taxonomy_name($attribute_name);

				if (!taxonomy_exists($attribute_taxonomy_name)){

					$attribute_taxonomy_name = '';

					if (strpos($attribute_name, '_') !== false){

						$attribute_name_hyphen = str_replace('_', '-', $attribute_name);

						$attribute_taxonomy_name_hyphen = wc_attribute_taxonomy_name($attribute_name_hyphen);

						if (taxonomy_exists($attribute_taxonomy_name_hyphen)){

							$attribute_name = $attribute_name_hyphen;
							$attribute_taxonomy_name = $attribute_taxonomy_name_hyphen;

						}

					}

				}

				if ($attribute_taxonomy_name !== ''){

					$attribute_values_to_update = array();

					foreach ($attribute_values as $attribute_value) {

						$attribute_value_sanitized = sanitize_title($attribute_value);

						$found = false;
						$term_exists = term_exists( $attribute_value_sanitized, $attribute_taxonomy_name );

						if ( !is_null($term_exists) && $term_exists !== 0 ) {

							$found = true;

						}

						if (!$found){

							sl_wp_set_object_terms( wc_attribute_taxonomy_id_by_name($attribute_taxonomy_name), $attribute_value, $attribute_taxonomy_name , false);
						
						}

						$attribute_values_to_update[] = $attribute_value;

					}

					$wp_existing_attributes = wp_get_object_terms( $wp_parent_product_id,  $attribute_taxonomy_name, array('fields' => 'names'));
					
					$is_update = true;
					if (!empty($wp_existing_attributes)){

						asort($wp_existing_attributes);
						asort($attribute_values_to_update);

						if (array_values($wp_existing_attributes) == array_values($attribute_values_to_update)){
						
							$is_update = false;
							
						}

					}					

					if ($is_update){
					
						$resultado = sl_wp_set_object_terms( $wp_parent_product_id, $attribute_values_to_update, $attribute_taxonomy_name);

					}

					if (empty($wp_product_attributes) || !isset($wp_product_attributes[$attribute_name])){
				
						$wp_product_attributes[$attribute_taxonomy_name] = array(
						        'name' 			=> $attribute_taxonomy_name,
						        'position'      => 0,
						        'is_visible'    => 1,
						        'is_variation'  => 1,
						        'is_taxonomy'   => 1
						);

					}else{
				
						if ($wp_product_attributes[$attribute_taxonomy_name]['is_variation'] == 0){ $wp_product_attributes[$attribute_taxonomy_name]['is_variation'] = 1; }
						
					}

					$wp_product_attributes_used[] = $attribute_taxonomy_name;
					
				}

			}

		}
		
		$pos = 0;
		foreach ($wp_product_attributes as $keyPA => $wp_product_attribute) {
		
			$wp_product_attributes[$keyPA]['position'] = $pos;
			$pos++;

			if ($wp_product_attribute['is_variation'] == 1){

			    if (empty($sl_parent_attributes)){

			    	$wp_product_attributes[$keyPA]['is_variation'] = 0;
			    	continue;

			    }

			    if (strpos($wp_product_attribute['name'], 'pa_') !== false){
			        
			        $wp_product_attribute_name = substr($wp_product_attribute['name'], 3, strlen($wp_product_attribute['name']));

			    }else{

			    	$wp_product_attribute_name = $wp_product_attribute['name'];

			    }

			    $is_in_sl_parent_atributes = false;

				if (isset($sl_parent_attributes[$wp_product_attribute_name])){

					$is_in_sl_parent_atributes = true;			    

				}

			    if (strpos($wp_product_attribute_name, '_') !== false){

			    	$wp_product_attribute_name_hyphen = str_replace('_', '-', $wp_product_attribute_name);
			    	
			    	if (isset($sl_parent_attributes[$wp_product_attribute_name_hyphen])){

			    		$is_in_sl_parent_atributes = true;			    

			    	}

			    }

			    if (strpos($wp_product_attribute_name, '-') !== false){

			    	$wp_product_attribute_name_lowbar = str_replace('-', '_', $wp_product_attribute_name);
			    	
			    	if (isset($sl_parent_attributes[$wp_product_attribute_name_lowbar])){

			    		$is_in_sl_parent_atributes = true;			    

			    	}

			    }

			    if (!$is_in_sl_parent_atributes){

			    	$wp_product_attributes[$keyPA]['is_variation'] = 0;
			    
				}

			}
		
		}
		
		sl_update_post_meta( $wp_parent_product_id, '_product_attributes', $wp_product_attributes );
		
	}

	/**
	 * Function to delete a stored product format.
	 * @param  array $format_to_delete 			SL format id to delete
	 * @return string  							result of delete
	 */
	public function delete_stored_product_format ($format_to_delete) {

		sl_debbug('Disabling product format with SL id: '.$format_to_delete.' comp_id: '.$this->comp_id. '. Setting it to private status.');

		$wp_format = find_saleslayer_format(null, $connector->conn_data['comp_id'], $format_to_delete);
		
		if ($wp_format){
	
			sl_delete_post_meta($wp_format['ID'], '_saleslayerid');
			sl_delete_post_meta($wp_format['ID'], '_saleslayercompid');
			sl_delete_post_meta($wp_format['ID'], '_saleslayerformatid');

			sl_wp_update_post(array('ID' => $wp_format['ID'], 'post_status' => 'private'), true);

		}else{

			sl_debbug('## Error. The product format with id: '.$format_to_delete.' does not exist.');
			return 'item_not_deleted';

		}

		return 'item_deleted';

	}

}
