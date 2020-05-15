<?php 
// http://lukasznowicki.info/insert-new-woocommerce-product-programmatically/

class Product {
	
	public $product_id_field = 'ID';
	public $product_id_catalogue_field = 'ID_catalogue';

	//Define every product attribute
	protected $product_field_name         			= 'product_name';
	protected $product_field_description  			= 'product_description';
	protected $product_field_description_short 		= 'product_description_short';
	protected $product_field_image        			= 'product_image';
	public 	  $product_field_sku          			= 'product_sku';
	protected $product_field_status					= 'product_status';
	protected $product_field_stock					= 'product_stock';
	protected $product_field_manage_stock			= 'product_manage_stock';
	protected $product_field_stock_status			= 'product_stock_status';
	protected $product_field_menu_order				= 'product_menu_order';
	protected $product_field_weight					= 'product_weight';
	protected $product_field_length					= 'product_length';
	protected $product_field_width					= 'product_width';
	protected $product_field_height					= 'product_height';
	protected $product_field_purchase_note			= 'product_purchase_note';
	protected $product_field_regular_price			= 'product_regular_price';
	protected $product_field_sale_price				= 'product_sale_price';
	protected $product_field_tags					= 'product_tags';
	protected $product_field_downloadable			= 'product_downloadable';
	protected $product_field_virtual				= 'product_virtual';
	protected $product_field_shipping_class			= 'product_shipping_class';
	protected $product_field_related_references		= 'related_products_references';
	protected $product_field_crosssell_references	= 'crosssell_products_references';
	protected $product_field_upsell_references		= 'upsell_products_references';
	protected $product_field_grouping_references	= 'grouping_product_references';
	protected $product_images_sizes       			= array();
	protected $product_additional_fields 			= array();

	private $sl_data_schema = array();

	private static $product;

	public $products_attributes_data = array();
	public $comp_id;

	protected $media_field_names = array();
	protected $media_class;
	
	/**
	 * Function to get instance of the class.
	 * @return self
	 */
	public static function &get_instance(){

		if( is_null(self::$product ) ){
		
			self::$product = new Product();
		
		}
		
		return self::$product;

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
	* Function to store Sales Layer products data.
	* @param  array $products              		products data to organize
	* @param  array $sl_language 				Sales Layer connector language
	* @return array $products_data_to_store     products data to store
	*/
	public function prepare_product_data_to_store($products, $sl_language){

		$connector = Connector::get_instance();

	    $product_data_to_store = array();

	    $fixed_product_fields = array($this->product_id_field, $this->product_id_catalogue_field, $this->product_field_name, $this->product_field_description, $this->product_field_description_short, $this->product_field_regular_price, $this->product_field_sale_price, $this->product_field_image, $this->product_field_sku, $this->product_field_status, $this->product_field_stock, $this->product_field_manage_stock, $this->product_field_stock_status, $this->product_field_menu_order, $this->product_field_weight, $this->product_field_length, $this->product_field_width, $this->product_field_height, $this->product_field_purchase_note, $this->product_field_tags, $this->product_field_downloadable, $this->product_field_virtual, $this->product_field_related_references, $this->product_field_crosssell_references, $this->product_field_upsell_references, $this->product_field_grouping_references, $this->product_field_shipping_class);

		$data_schema = json_decode($this->sl_data_schema, 1);
	    $schema      = $data_schema['products'];

	    $this->product_images_sizes = array();

	    if (!empty($schema['fields'][$this->product_field_image]['image_sizes'])) {
	    	$product_field_images_sizes = $schema['fields'][$this->product_field_image]['image_sizes'];
	    	$ordered_image_sizes = order_array_img($product_field_images_sizes);
	    	foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
	    		$this->product_images_sizes[] = $img_size;
	    	}

	    } else if (!empty($schema['fields']['image_sizes'])) {

	    	$product_field_images_sizes = $schema['fields']['image_sizes'];
	    	$ordered_image_sizes = order_array_img($product_field_images_sizes);
	    	foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
	    		$this->product_images_sizes[] = $img_size;
	    	}

	    } else {

	    	$this->product_images_sizes[] = 'IMD';

	    }

	    if (SLYR_WC_DEBBUG > 1) sl_debbug('Product image sizes: '.implode(', ', (array)$this->product_images_sizes));

	    $product_data_to_store['product_fields']['product_images_sizes'] = $this->product_images_sizes;

	    $field_names = ['product_field_name',
						'product_field_description',
						'product_field_description_short',
						'product_field_regular_price',
						'product_field_sale_price',
						'product_field_image',
						'product_field_sku',
						'product_field_status',
						'product_field_stock',
						'product_field_manage_stock',
						'product_field_stock_status',
						'product_field_menu_order',
						'product_field_weight',
						'product_field_length',
						'product_field_width',
						'product_field_height',
						'product_field_purchase_note',
						'product_field_tags',
						'product_field_downloadable',
						'product_field_virtual',
						'product_field_related_references',
						'product_field_crosssell_references',
						'product_field_upsell_references',
						'product_field_grouping_references',
						'product_field_shipping_class'
					];

	    foreach ($field_names as $field_name){

		    if (isset($schema['fields'][$this->$field_name]) && $schema['fields'][$this->$field_name]['has_multilingual']) {

		        $this->$field_name .= '_'.$sl_language;

		    }

	        $product_data_to_store['product_fields'][$field_name] = $this->$field_name;
	    
	    }

	    foreach ($schema['fields'] as $field_name => $field_props) {
			
			if (!in_array($field_name, $fixed_product_fields)) {
				
				if (isset($field_props['has_multilingual']) && $field_props['has_multilingual']) {

					$product_data_to_store['product_additional_fields'][$field_name] = $field_name.'_'.$connector->conn_data['languages'];
				
					if (strtolower($field_props['type']) == 'image' || strtolower($field_props['type']) == 'file'){

						$product_data_to_store['products_media_field_names'][$field_name] = $field_name.'_'.$connector->conn_data['languages'];

					}

				}else{
				
					$product_data_to_store['product_additional_fields'][$field_name] = $field_name;
				
					if (strtolower($field_props['type']) == 'image' || strtolower($field_props['type']) == 'file'){

						$product_data_to_store['products_media_field_names'][$field_name] = $field_name;

					}

				}

			}

		}
		
		if (SLYR_WC_DEBBUG > 1 and count($product_data_to_store['product_additional_fields']) > 0) {
		    
		    sl_debbug("Product additional fields: ".print_r($product_data_to_store['product_additional_fields'], 1));

		}   

		$time_ini_pre_process_products = microtime(1);
		$result_pre_process = pre_process_by_skus('product', $connector->conn_data['comp_id'], $products);
	    
		if (!empty($result_pre_process)){

			foreach ($result_pre_process as $result_row) {
				
				if (!isset($product_data_to_store['not_synced_products'])){ 

					$product_data_to_store['not_synced_products'] = array();

				}

				$product_data_to_store['not_synced_products'][$products[$result_row['array_index']][$this->product_id_field]] = $result_row['error_message'];
				unset($products[$result_row['array_index']]);
		
			}

		}
	    sl_debbug('### pre_process_products: '.(microtime(1) - $time_ini_pre_process_products).' seconds.', 'timer');

	    if (!empty($products)){

	        foreach ($products as $keyProd => $product) {

                if (empty($product[$this->product_id_catalogue_field])){

                	if (!isset($product_data_to_store['not_synced_products'])){ 

                		$product_data_to_store['not_synced_products'] = array();

                	}

                	$product_data_to_store['not_synced_products'][$product[$this->product_id_field]] = 'Product '.$product['data'][$this->product_field_name].' with SL ID '.$product[$this->product_id_field].' has no categories.';
                    unset($products[$keyProd]);

                }

            }

            if (!empty($products)){

                $product_data_to_store['product_data'] = $products;

            }

	    }
	    
	    return $product_data_to_store;

	}

	/**
	 * Sync stored product
	 * @param  array $product 	product data
	 * @return string          	product updated correctly or not
	 */
	public function sync_stored_product($product){

		$time_ini_product_core_data = microtime(1);

		$sl_product_id        	= $product[$this->product_id_field];
		$sl_product_parent_ids	= $product[$this->product_id_catalogue_field];
		$product_data        	= $product['data'];

		$wp_category_ids = array();

		if (!is_array($sl_product_parent_ids)){

			$sl_product_parent_ids = array($sl_product_parent_ids);
		
		}

		foreach ($sl_product_parent_ids as $sl_product_parent_id) {
			
			if (intval($sl_product_parent_id) != 0){							

				do {

					$wp_product_parent_category = find_saleslayer_term('product_cat' , $sl_product_parent_id, $this->comp_id);

					if ($wp_product_parent_category){
						
						$wp_parent_category_id = $wp_product_parent_category['term_id'];
						$wp_parent_category_parent_id = $wp_product_parent_category['parent'];

						if (!isset($wp_category_ids[$wp_parent_category_id])){ 
							$wp_category_ids[$wp_parent_category_id] = 0; 
						}
						
						if ($wp_parent_category_parent_id != 0){

							$wp_parent_category_parent = sl_get_term_by( 'term_id', $wp_parent_category_parent_id, 'product_cat', ARRAY_A);

							if (isset($wp_parent_category_parent['saleslayerid']) && !in_array($wp_parent_category_parent['saleslayerid'], array(null, '', 0))){

								$sl_product_parent_id = $wp_parent_category_parent['saleslayerid'];
							
							}

						}else{
							
							$sl_product_parent_id = 0;
						
						}

					}else{
						
						$sl_product_parent_id = 0;
					
					}

					if (is_null($sl_product_parent_id) || $sl_product_parent_id === 0){ 
						
						break;

					}

				} while ($sl_product_parent_id !== 0);

			}
		
		}

		$wp_category_ids = array_keys($wp_category_ids);
	    $wp_product = find_saleslayer_product($sl_product_id, $this->comp_id);
		
		if (!$wp_product){
		
			$wp_product = $this->find_product_by_sku_or_name($product_data[$this->product_field_sku], $product_data[$this->product_field_name], $sl_product_id, $this->comp_id);
			if (!$wp_product){
				$time_ini_product_create = microtime(1);
				$this->create_product($sl_product_id, $this->comp_id, $wp_category_ids, $product_data);
				sl_debbug('## time_product_create: '.(microtime(1) - $time_ini_product_create).' seconds.', 'timer');
			}
			
			$wp_product = find_saleslayer_product($sl_product_id, $this->comp_id);

			if (!$wp_product){
			
				sl_debbug('## Error. SL ID: '.$sl_product_id.' : '.$product_data[$this->product_field_name]." - The product could not been created.");
				return 'item_not_updated';
			
			}
		
		}

		$wp_product_type = '';
		$wp_product_type_term = wp_get_object_terms( $wp_product['ID'], 'product_type', array('fields' => 'slugs') );
		if (is_array($wp_product_type_term) && isset($wp_product_type_term[0])){
			$wp_product_type = $wp_product_type_term[0];
		}
		
		if (SLYR_WC_DEBBUG) sl_debbug(" > Updating product ID: $sl_product_id (categories: ".print_r($wp_category_ids,1).")");

		if (SLYR_WC_DEBBUG > 1) sl_debbug(" Name ({$this->product_field_name}): ".$product_data[$this->product_field_name]);

		$product_modified = false;
		$product_data_modified = array('ID' => $wp_product['ID']);

		//Product basic data
		if (!isset($wp_product['post_title']) || (isset($wp_product['post_title']) && $wp_product['post_title'] != $product_data[$this->product_field_name])){
			
			$product_data_modified['post_title'] = $product_data[$this->product_field_name];
			$product_data_modified['post_name'] = sanitize_title($product_data[$this->product_field_name]);
			// $product_data_modified['guid'] = get_permalink($wp_product['ID']);
			// $product_modify_guid = true;
			$product_modified = true;
		
		}

		if (!isset($wp_product['post_content']) || (isset($wp_product['post_content']) && $wp_product['post_content'] != $product_data[$this->product_field_description])){
			
			$product_data_modified['post_content'] = $product_data[$this->product_field_description];
			$product_modified = true;
		
		}

		if (!isset($wp_product['post_excerpt']) || (isset($wp_product['post_excerpt']) && $wp_product['post_excerpt'] != $product_data[$this->product_field_description_short])){
			
			$product_data_modified['post_excerpt'] = $product_data[$this->product_field_description_short];
			$product_modified = true;
		
		}

		if (isset($product_data[$this->product_field_menu_order])){

			$sl_menu_order = $product_data[$this->product_field_menu_order];

			if (!is_numeric($sl_menu_order) && filter_var($sl_menu_order, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_menu_order = filter_var($sl_menu_order, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
			
			}

			if (!isset($wp_product['menu_order']) || (isset($wp_product['menu_order']) && $wp_product['menu_order'] != $sl_menu_order)){

				$product_data_modified['menu_order'] = $sl_menu_order;
				$product_modified = true;

			}
		
		}


		if (isset($product_data[$this->product_field_status])){

			$sl_status = $product_data[$this->product_field_status];
			
			if (is_array($sl_status)) $sl_status = reset($sl_status);
			
			if ($sl_status !== '' && !is_numeric($sl_status)){

				$sl_status = trim(strtolower($sl_status));
				if (in_array($sl_status, array('publicado', 'publicada', 'published'))) $sl_status = 'publish';
				if (in_array($sl_status, array('privado', 'privada'))) $sl_status = 'private';
				if ($sl_status == 'borrador') $sl_status = 'draft';
				if ($sl_status == 'pendiente') $sl_status = 'pending';
				if ($sl_status == 'papelera') $sl_status = 'trash';

				$wp_status = array('publish', 'private', 'draft', 'pending', 'trash');
				
				if (in_array( $sl_status , $wp_status) && $sl_status != $wp_product['post_status']){

					$product_data_modified['post_status'] = $sl_status;
					$product_modified = true;

				}

			}
		
		}

		if ($product_modified){

			sl_wp_update_post($product_data_modified, true);

		}

		if (!isset($wp_product['_product_version']) || (isset($wp_product['_product_version']) && $wp_product['_product_version'] != WC_VERSION)){

			sl_update_post_meta( $wp_product['ID'], '_product_version', WC_VERSION );

		}

		//Product meta data
		if (isset($product_data[$this->product_field_sku]) && (!isset($wp_product['_sku']) || (isset($wp_product['_sku']) && $wp_product['_sku'] != $product_data[$this->product_field_sku]))){

			sl_update_post_meta( $wp_product['ID'], '_sku', $product_data[$this->product_field_sku]);

		}

		if (isset($product_data[$this->product_field_purchase_note]) && (!isset($wp_product['_purchase_note']) || (isset($wp_product['_purchase_note']) && $wp_product['_purchase_note'] != $product_data[$this->product_field_purchase_note]))){

			sl_update_post_meta( $wp_product['ID'], '_purchase_note', $product_data[$this->product_field_purchase_note]);

		}

		if (isset($product_data[$this->product_field_weight])){

			$sl_weight = $product_data[$this->product_field_weight];

			if (!is_numeric($sl_weight) && filter_var($sl_weight, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_weight = filter_var($sl_weight, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				
			}

			if (!isset($wp_product['_weight']) || (isset($wp_product['_weight']) && $wp_product['_weight'] != $sl_weight)){

				sl_update_post_meta( $wp_product['ID'], '_weight', $sl_weight);

			}

		}

		if (isset($product_data[$this->product_field_length])){

			$sl_length = $product_data[$this->product_field_length];

			if (!is_numeric($sl_length) && filter_var($sl_length, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_length = filter_var($sl_length, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

			}

			if (!isset($wp_product['_length']) || (isset($wp_product['_length']) && $wp_product['_length'] != $sl_length)){

				sl_update_post_meta( $wp_product['ID'], '_length', $sl_length);

			}

		}

		if (isset($product_data[$this->product_field_width])){

			$sl_width = $product_data[$this->product_field_width];

			if (!is_numeric($sl_width) && filter_var($sl_width, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_width = filter_var($sl_width, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				
			}

			if (!isset($wp_product['_width']) || (isset($wp_product['_width']) && $wp_product['_width'] != $sl_width)){

				sl_update_post_meta( $wp_product['ID'], '_width', $sl_width);

			}

		}

		if (isset($product_data[$this->product_field_height])){

			$sl_height = $product_data[$this->product_field_height];

			if (!is_numeric($sl_height) && filter_var($sl_height, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION)){
				
				$sl_height = filter_var($sl_height, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
				
			}

			if (!isset($wp_product['_height']) || (isset($wp_product['_height']) && $wp_product['_height'] != $sl_height)){

				sl_update_post_meta( $wp_product['ID'], '_height', $sl_height);

			}

		}

		if (isset($product_data[$this->product_field_downloadable])){
			
			$sl_downloadable = sl_validate_boolean($product_data[$this->product_field_downloadable]);
			
			if ((isset($wp_product['_downloadable']) && $wp_product['_downloadable'] != $sl_downloadable) || (!isset($wp_product['_downloadable']))){

				sl_update_post_meta( $wp_product['ID'], '_downloadable', $sl_downloadable);
			
			}

		}

		if (isset($product_data[$this->product_field_virtual])){
			
			$sl_virtual = sl_validate_boolean($product_data[$this->product_field_virtual]);
			
			if ((isset($wp_product['_virtual']) && $wp_product['_virtual'] != $sl_virtual) || (!isset($wp_product['_virtual']))){

				sl_update_post_meta( $wp_product['ID'], '_virtual', $sl_virtual);
			
			}

		}

        if (isset($product_data[$this->product_field_shipping_class])){

			if (taxonomy_exists('product_shipping_class')){

				$shipping_class = Shipping_class::get_instance();

				$wp_product_shipping_class = '';

				$wp_product_shipping_class_term = wp_get_object_terms( $wp_product['ID'], 'product_shipping_class');
				if (is_array($wp_product_shipping_class_term) && isset($wp_product_shipping_class_term[0])){
					$wp_product_shipping_class_term = $wp_product_shipping_class_term[0];
				}
				
				if (is_object($wp_product_shipping_class_term)){
				
					$wp_product_shipping_class = isset( $wp_product_shipping_class_term->term_id ) ? $wp_product_shipping_class_term->term_id : 0;
				
				}else if (is_array($wp_product_shipping_class_term) && isset($wp_product_shipping_class_term[0]) && isset($wp_product_shipping_class_term[0]['term_id'])){
				
					$wp_product_shipping_class = $wp_product_shipping_class_term[0]['term_id'];
				
				}

	        	$sl_product_shipping_class = '';

	        	if (is_array($product_data[$this->product_field_shipping_class]) && !empty($product_data[$this->product_field_shipping_class])){

	        		$sl_product_shipping_class = reset($product_data[$this->product_field_shipping_class]);

	        	}else if (!is_array($product_data[$this->product_field_shipping_class]) && $product_data[$this->product_field_shipping_class] != ''){

	        		$sl_product_shipping_class = $product_data[$this->product_field_shipping_class];

	        	}

	        	$sl_id_product_shipping_class = '';
	    		
	    		if ($sl_product_shipping_class != ''){

	        		if (is_numeric($sl_product_shipping_class)){

	        			if (isset($shipping_class->shipping_classes[$sl_product_shipping_class])){

	        				$sl_id_product_shipping_class = $sl_product_shipping_class;

	        			}
	                
			        }else{

			        	$sl_product_shipping_class_lower = trim(strtolower($sl_product_shipping_class));
	    		
	    		    	foreach ($shipping_class->shipping_classes as $shipping_class_term_id => $shipping_class_data) {
			        		
			        		if ($shipping_class_data['name'] == $sl_product_shipping_class || $shipping_class_data['slug'] == $sl_product_shipping_class || strtolower($shipping_class_data['name']) == $sl_product_shipping_class_lower || strtolower($shipping_class_data['slug']) == $sl_product_shipping_class_lower){

			        			$sl_id_product_shipping_class = $shipping_class_term_id;
			        			break;

			        		}

			        	}
	            	
	            	}

	            	if ($sl_id_product_shipping_class == ''){

	            		$sl_id_product_shipping_class = $shipping_class->create_shipping_class($sl_product_shipping_class);

	            	}

	            }

	    		if ($wp_product_shipping_class != $sl_id_product_shipping_class){

	            	if ($sl_id_product_shipping_class == '' && $wp_product_shipping_class != ''){

	            		wp_delete_object_term_relationships( $wp_product['ID'], 'product_shipping_class' );
	            		
	            	}else if ($sl_id_product_shipping_class != ''){

	            		sl_wp_set_object_terms( $wp_product['ID'], $sl_id_product_shipping_class, 'product_shipping_class');
	            		
	            	}

	            }

			}else{

				sl_debbug('## Error. Product shipping class taxonomy does not exist.');

			}

        }

		$wp_price = $wp_sale_price = $wp_regular_price = false;

		if (isset($product_data[$this->product_field_sale_price])){

			$sl_sale_price = $product_data[$this->product_field_sale_price];

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
		
		if ($wp_sale_price === false && isset($wp_product['_sale_price'])){

			$wp_sale_price = $wp_product['_sale_price'];

		}
		
		if (isset($product_data[$this->product_field_regular_price])){

			$sl_regular_price = $product_data[$this->product_field_regular_price];

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

		if ($wp_regular_price === false && isset($wp_product['_regular_price'])){

			$wp_regular_price = $wp_product['_regular_price'];

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
		
		if ($wp_sale_price !== false && (!isset($wp_product['_sale_price']) || (isset($wp_product['_sale_price']) && $wp_product['_sale_price'] !== $wp_sale_price))){

			sl_update_post_meta( $wp_product['ID'], '_sale_price', $wp_sale_price);

		}

		if ($wp_regular_price !== false && (!isset($wp_product['_regular_price']) || (isset($wp_product['_regular_price']) && $wp_product['_regular_price'] !== $wp_regular_price))){

			sl_update_post_meta( $wp_product['ID'], '_regular_price', $wp_regular_price);

		}

		if ($wp_price !== false && (!isset($wp_product['_price']) || (isset($wp_product['_price']) && $wp_product['_price'] !== $wp_price))){

			sl_update_post_meta( $wp_product['ID'], '_price', $wp_price);
			
		}

		$check_stock = true;
	
		if (isset($product_data[$this->product_field_manage_stock])){

			$sl_product_manage_stock = $product_data[$this->product_field_manage_stock];

			if (is_array($sl_product_manage_stock) && !empty($sl_product_manage_stock)){
				
				$sl_product_manage_stock = reset($sl_product_manage_stock);

				if (!is_bool($sl_product_manage_stock)){
				
					$sl_product_manage_stock = trim(strtolower($sl_product_manage_stock));
				
				}
		
			}else if (!is_array($sl_product_manage_stock) && $sl_product_manage_stock !== '' && !is_bool($sl_product_manage_stock)){
				
				$sl_product_manage_stock = trim(strtolower($sl_product_manage_stock));
		
			}
			
			if ($sl_product_manage_stock !== ''){

				$sl_manage_stock = sl_validate_boolean($sl_product_manage_stock);
				
				if (!isset($wp_product['_manage_stock']) || (isset($wp_product['_manage_stock']) && $wp_product['_manage_stock'] != $sl_manage_stock)){
		
					sl_update_post_meta( $wp_product['ID'], '_manage_stock', $sl_manage_stock);
				
				}

				if ($sl_manage_stock == 'no'){
		
					$check_stock = false;

					if (!isset($wp_product['_stock']) || (isset($wp_product['_stock']) && !in_array($wp_product['_stock'], array('', null)))){
		
						sl_update_post_meta( $wp_product['ID'], '_stock', '');

						if (!isset($product_data[$this->product_field_stock_status]) || (isset($product_data[$this->product_field_stock_status]) && $product_data[$this->product_field_stock_status] === '')){
		
							if (!isset($wp_product['_stock_status']) || (isset($wp_product['_stock_status']) && $wp_product['_stock_status'] !== 'outofstock')){
		
								sl_update_post_meta( $wp_product['ID'], '_stock_status', 'outofstock');

							}

						}

					}			

					if (isset($product_data[$this->product_field_stock_status])){

						$sl_product_stock_status = $product_data[$this->product_field_stock_status];

						if (is_array($sl_product_stock_status) && !empty($sl_product_stock_status)){
						
							$sl_product_stock_status = trim(strtolower(reset($sl_product_stock_status)));
						
						}else if (!is_array($sl_product_stock_status) && $sl_product_stock_status !== ''){

							$sl_product_stock_status = trim(strtolower($sl_product_stock_status));
						
						}
						
						if ($sl_product_stock_status !== ''){

							$product_stock_status = 'outofstock';

							if (preg_match('~(reservado|backorder)~', strtolower($sl_product_stock_status))) {
							
							    $product_stock_status = 'onbackorder';
							
							}

							if (preg_match('~(existencias|in)~', strtolower($sl_product_stock_status))) {
							
							    $product_stock_status = 'instock';
							
							}

							if (preg_match('~(agotado|out)~', strtolower($sl_product_stock_status))) {
								
								$product_stock_status = 'outofstock';
							
							}

							if (!isset($wp_product['_stock_status']) || (isset($wp_product['_stock_status']) && $product_stock_status != $wp_product['_stock_status'])){

								sl_update_post_meta( $wp_product['ID'], '_stock_status', $product_stock_status);

							}

						}

					}

				}

			}

		}

		if ($check_stock){

			if (isset($product_data[$this->product_field_stock]) && $product_data[$this->product_field_stock] !== ''){

				$sl_stock = $product_data[$this->product_field_stock];

				$sl_stock_not_num = false;

				if (!is_numeric($sl_stock)){

					$sl_stock_not_num = true;
					//wc_stock_amount to remove decimals as commas instead of dots 
					$sl_stock = wc_stock_amount($sl_stock);

				}

				if (is_numeric($sl_stock) && (!$sl_stock_not_num || ($sl_stock_not_num && $sl_stock !== 0))){

					//wc_stock_amount to delete decimals
					$sl_stock = wc_stock_amount($sl_stock);

					if (!isset($wp_product['_manage_stock']) || (isset($wp_product['_manage_stock']) && $wp_product['_manage_stock'] == 'no')){
						
						sl_update_post_meta( $wp_product['ID'], '_manage_stock', 'yes'); 
					
					}

					if (!isset($wp_product['_stock']) || (isset($wp_product['_stock']) && $wp_product['_stock'] != $sl_stock)){
						
						if ((!isset($wp_product['_stock_status']) || (isset($wp_product['_stock_status']) && $wp_product['_stock_status'] == 'outofstock')) && $sl_stock > 0){
						
							sl_update_post_meta( $wp_product['ID'], '_stock_status', 'instock'); 
						
						}else if ((!isset($wp_product['_stock_status']) || (isset($wp_product['_stock_status']) && $wp_product['_stock_status'] == 'instock')) && $sl_stock <= 0){
						
							sl_update_post_meta( $wp_product['ID'], '_stock_status', 'outofstock');
						
						}
						
						sl_update_post_meta( $wp_product['ID'], '_stock', $sl_stock);

					}

				}

			}
		
		}

		$product_categories = wp_get_object_terms( $wp_product['ID'],  'product_cat', array('fields' => 'ids'));
		
		asort($product_categories);
		asort($wp_category_ids);
		
		if (array_values($wp_category_ids) !== array_values($product_categories)){
		
			sl_wp_set_object_terms( $wp_product['ID'], $wp_category_ids, 'product_cat');
		
		}

		//Product tags
		if (isset($product_data[$this->product_field_tags])){

			$sl_tags = $product_data[$this->product_field_tags];
			
			if ((is_array($sl_tags) && !empty($sl_tags)) || (!is_array($sl_tags) && $sl_tags != '')){
				
				if (!is_array($sl_tags)){

					if (strpos($sl_tags, ',')){
						$sl_tags = explode(',', $sl_tags);
					}else{
						$sl_tags = array($sl_tags);
					}

				}

				sl_wp_set_post_terms( $wp_product['ID'], $sl_tags, 'product_tag' );

			}
		
		}

		$linked_fields = array($this->product_field_related_references => '_related_ids', $this->product_field_crosssell_references => '_crosssell_ids', $this->product_field_upsell_references => '_upsell_ids', $this->product_field_grouping_references => '_children');
		
		$linked_product_data = array();

		foreach ($linked_fields as $field_sales => $linked_type) {

			$wp_product_linked_ids = get_post_meta($wp_product['ID'], $linked_type, true);
	        if (!is_array($wp_product_linked_ids)){ 

	        	$wp_product_linked_ids = array();

	        }else{

	        	asort($wp_product_linked_ids);

	        }
		    
		    if (isset($product_data[$field_sales])){

	            $linked_references = array();

		        if (is_array($product_data[$field_sales]) && !empty($product_data[$field_sales])){
		            
		            $linked_references = $product_data[$field_sales];

		        }else if (!is_array($product_data[$field_sales]) && $product_data[$field_sales] != ''){

		            if (strpos($product_data[$field_sales], ',')){
		            
		                $linked_references = explode(',', $product_data[$field_sales]);

		            }else{
		            
		                $linked_references = array($product_data[$field_sales]);
		            
		            }

		        }

		        if (empty($linked_references)){ continue; }
		        
		        $linked_product_data[$wp_product['ID']][] = array('linked_type' => $linked_type, 'linked_references' => $linked_references);

		    }

		}

		if (!empty($linked_product_data)){

		    $sql_query_to_insert = " INSERT INTO ".SLYR_WC_syncdata_table.
		                            " ( sync_type, item_type, item_data, sync_params ) VALUES ".
		                            " ('update', 'product_links', '".json_encode($linked_product_data)."', '')";

		    sl_connection_query('insert', $sql_query_to_insert);

		}

		sl_debbug('## time_product_core_data: '.(microtime(1) - $time_ini_product_core_data).' seconds.', 'timer');

		$time_ini_product_images = microtime(1);
		//Product images
		if (!empty($product_data[$this->product_field_image])){

			if (is_null($this->media_class)) $this->media_class = Media_class::get_instance();

			$old_wp_thumbnail_id = $wp_thumbnail_id = $wp_product_thumbnail_name = $wp_product_thumbnail_filesize = $wp_gallery_ids = '';
			
			$sl_product_images = $product_data[$this->product_field_image];
			
			if(count($sl_product_images) > 0) {

				if (isset($wp_product['_thumbnail_id'])){
					// $time_ini_read_thubmnail_id = microtime(1);
					$old_wp_thumbnail_id = $wp_thumbnail_id = $wp_product['_thumbnail_id'];
					if (is_array($wp_thumbnail_id) && isset($wp_thumbnail_id[0])){ 
					
						$old_wp_thumbnail_id = $wp_thumbnail_id = $wp_thumbnail_id[0];
					
					}
					// sl_debbug('# time_read_thubmnail_id: '.(microtime(1) - $time_ini_read_thubmnail_id).' seconds.', 'timer');

				}

				if (!in_array($wp_thumbnail_id, array('', 0, null, false))){
					
					// $time_ini_read_thubmnail_data = microtime(1);
					$wp_product_thumbnail_url = wp_get_attachment_url($wp_thumbnail_id);
					$wp_parse_product_thumbnail_url = pathinfo($wp_product_thumbnail_url);
					$wp_product_thumbnail_name = $wp_parse_product_thumbnail_url['basename'];
					$wp_product_thumbnail_filesize = $this->media_class->read_image_file_size($wp_product_thumbnail_url);
					// sl_debbug('# time_read_thubmnail_data: '.(microtime(1) - $time_ini_read_thubmnail_data).' seconds.', 'timer');
				
				}

				isset($wp_product['_product_image_gallery']) ? $wp_product_image_gallery = $wp_product['_product_image_gallery'] : $wp_product_image_gallery = '';	
				$wp_product_image_gallery_data = array();

				if ($wp_product_image_gallery != ''){

					// $time_ini_read_image_gallery_data = microtime(1);
					$wp_product_image_gallery_ids = explode(',', $wp_product_image_gallery);
					
					foreach ($wp_product_image_gallery_ids as $keyPI => $wp_product_image_gallery_id) {

						$wp_product_image_gallery_url = wp_get_attachment_url($wp_product_image_gallery_id);
						$wp_parse_product_image_gallery_url = pathinfo($wp_product_image_gallery_url);
						$wp_product_image_gallery_name = $wp_parse_product_image_gallery_url['basename'];
						$wp_product_image_gallery_filesize = $this->media_class->read_image_file_size($wp_product_image_gallery_url);

						if (!isset($wp_product_image_gallery_data[$wp_product_image_gallery_id])){ 

							$wp_product_image_gallery_data[$wp_product_image_gallery_id] = array('image_name' => $wp_product_image_gallery_name, 'filesize_image' => $wp_product_image_gallery_filesize);
						
						}

					}
					// sl_debbug('# time_read_image_gallery_data: '.(microtime(1) - $time_ini_read_image_gallery_data).' seconds.', 'timer');
					
				}

				$main_image = reset($sl_product_images);
				$new_product_image_gallery_ids = array();
				
				foreach ($this->product_images_sizes as $img_format) {

					// $time_ini_image_size = microtime(1);

					foreach ($sl_product_images as $sl_product_image) {
						
						// $time_ini_product_image = microtime(1);
						if (!empty($sl_product_image[$img_format])){

							$image_url = $sl_product_image[$img_format];
							$filesize_image = $this->media_class->read_image_file_size($image_url);
							if (!$filesize_image){ continue; }
							
							$parse_url_image = pathinfo($image_url);
							$parse_url_image_basename = urldecode($parse_url_image['basename']);
							
							if ($sl_product_image == $main_image){
							
								// $time_ini_process_main_image = microtime(1);
								if ($parse_url_image_basename == $wp_product_thumbnail_name){
							
									if ($wp_product_thumbnail_filesize !== false && $wp_product_thumbnail_filesize == $filesize_image){
							
										continue;

									}else{
							
										if (!$this->media_class->update_media($image_url, $wp_thumbnail_id, true)){
											$wp_thumbnail_id = '';
										}

									}

								}else{
							
									$thumb_id = $this->media_class->get_thumbnail_id_by_title($parse_url_image_basename);
							
									if ($thumb_id === 0){
							
										$wp_thumbnail_id = $this->media_class->fetch_media($image_url, $wp_product['ID'], true);
									
									}else{

										$wp_thumbnail_id = $thumb_id;
										$wp_thumbnail_url = wp_get_attachment_url($wp_thumbnail_id);
										$wp_product_thumbnail_filesize = $this->media_class->read_image_file_size($wp_thumbnail_url);
							
										if (!$wp_product_thumbnail_filesize || ($wp_product_thumbnail_filesize !== false && $wp_product_thumbnail_filesize !== $filesize_image)){
							
											if (!$this->media_class->update_media($image_url, $wp_thumbnail_id, true)){
												$wp_thumbnail_id = '';
											}
							
										}

									}

								}

								if ($wp_thumbnail_id === false){ $wp_thumbnail_id = ''; }
								// sl_debbug('# time_process_main_image: '.(microtime(1) - $time_ini_process_main_image).' seconds.', 'timer');

							}else{
								
								// $time_ini_process_image_gallery = microtime(1);
								$image_gallery_found = false;

								if (!empty($wp_product_image_gallery_data)){

									foreach ($wp_product_image_gallery_data as $image_id => $wp_product_image_data) {
										
										if ($parse_url_image_basename == $wp_product_image_data['image_name']){
									
											$image_gallery_found = true;

											if (!$wp_product_image_data['filesize_image'] || ($wp_product_image_data['filesize_image'] !== false && $wp_product_image_data['filesize_image'] !== $filesize_image)){

												$result_update = $this->media_class->update_media($image_url, $image_id);
												if (!$result_update){ continue; }

											}

											$new_product_image_gallery_ids[] = $image_id;

										}

									}

								}

								if (!$image_gallery_found){

									$thumb_id = $this->media_class->get_thumbnail_id_by_title($parse_url_image_basename);

									if ($thumb_id === 0){

										$thumb_id = $this->media_class->fetch_media($image_url, $wp_product['ID']);
									
									}else{

										$wp_thumbnail_url = wp_get_attachment_url($thumb_id);
										$wp_product_thumbnail_filesize = $this->media_class->read_image_file_size($wp_thumbnail_url);
								
										if (!$wp_product_thumbnail_filesize || ($wp_product_thumbnail_filesize !== false && $wp_product_thumbnail_filesize !== $filesize_image)){

											$thumb_id = $this->media_class->update_media($image_url, $thumb_id);

										}

									}

									if ($thumb_id !== false){

										$new_product_image_gallery_ids[] = $thumb_id;
									
									}

								}
								// sl_debbug('# time_process_image_gallery: '.(microtime(1) - $time_ini_process_image_gallery).' seconds.', 'timer');

							}

						}
						// sl_debbug('# time_product_image: '.(microtime(1) - $time_ini_product_image).' seconds.', 'timer');

					}

					// sl_debbug('# time_image_size '.$img_format.': '.(microtime(1) - $time_ini_image_size).' seconds.', 'timer');
				}

				if (!empty($new_product_image_gallery_ids)){

					asort($new_product_image_gallery_ids);
					$wp_gallery_ids = implode(',', $new_product_image_gallery_ids);

				}

			}

			if (!isset($wp_product['_thumbnail_id']) || (isset($wp_product['_thumbnail_id']) && ((!is_array($wp_product['_thumbnail_id']) && $wp_product['_thumbnail_id'] != $wp_thumbnail_id) || is_array($wp_product['_thumbnail_id']) && $wp_product['_thumbnail_id'][0] != $wp_thumbnail_id))){

				// $time_ini_update_thumbnail_id = microtime(1);
				sl_update_post_meta( $wp_product['ID'], '_thumbnail_id', $wp_thumbnail_id );
				// sl_debbug('# time_update_thumbnail_id: '.(microtime(1) - $time_ini_update_thumbnail_id).' seconds.', 'timer');

			}

			if (!isset($wp_product['_product_image_gallery']) || (isset($wp_product['_product_image_gallery']) && $wp_product['_product_image_gallery'] != $wp_gallery_ids)){
				
				// $time_ini_update_product_image_gallery = microtime(1);
				sl_update_post_meta( $wp_product['ID'], '_product_image_gallery', $wp_gallery_ids );
				// sl_debbug('# time_update_product_image_gallery: '.(microtime(1) - $time_ini_update_product_image_gallery).' seconds.', 'timer');

			}

			if (!in_array($old_wp_thumbnail_id, array('', 0, null, false)) && $old_wp_thumbnail_id != $wp_thumbnail_id){ $this->media_class->delete_media($old_wp_thumbnail_id); }

			if (!empty($wp_product_image_gallery_ids)){
				// $time_ini_delete_images = microtime(1);

				$excess_product_image_gallery_ids = array_diff($wp_product_image_gallery_ids, $new_product_image_gallery_ids);
				if (!empty($excess_product_image_gallery_ids)){

					foreach ($excess_product_image_gallery_ids as $excess_product_image_gallery_id){

						if (!in_array($excess_product_image_gallery_id, array('', 0, null, false))){ 

							$this->media_class->delete_media($excess_product_image_gallery_id); 

						}

					}

				}
				// sl_debbug('# time_delete_images: '.(microtime(1) - $time_ini_delete_images).' seconds.', 'timer');

			}

		}
		sl_debbug('## time_product_images: '.(microtime(1) - $time_ini_product_images).' seconds.', 'timer');

		//Product attributes
		$time_ini_product_attributes = microtime(1);
		$this->sync_product_attributes($wp_product['ID'], $product_data, $sl_product_id);
		sl_debbug('## time_product_attributes: '.(microtime(1) - $time_ini_product_attributes).' seconds.', 'timer');
		
		if (SLYR_WC_DEBBUG) sl_debbug("Product updated!");

		return 'item_updated';

	}

	public function sync_stored_product_links($all_linked_product_data){

		foreach ($all_linked_product_data as $wp_product_id => $linked_product_data) {
		
			$wp_product = wc_get_product($wp_product_id);
		
			if (!$wp_product){ 
				sl_debbug('## Error. Product with WP ID does not exist: '.$wp_product_id);
				continue; 
			}

			$wp_product_type = $wp_product->get_type();
			$wp_product_sku = $wp_product->get_sku();
			
			foreach ($linked_product_data as $linked_idx => $linked_data) {

				$linked_type = $linked_data['linked_type'];
				$wp_product_linked_ids = get_post_meta($wp_product_id, $linked_type, true);
		        
		        if (!is_array($wp_product_linked_ids)){ 

		        	$wp_product_linked_ids = array();

		        }else{

		        	asort($wp_product_linked_ids);

		        }

		        $linked_product_ids = array();

	        	foreach ($linked_data['linked_references'] as $linked_reference) {

		        	if ($linked_type == '_children' && $linked_reference == $wp_product_sku){

		        	    sl_debbug('## Error. Grouping product reference is the same as the current product: '.$linked_reference);
		        	    continue;

		        	}

		        	// Auto-populate wp_wc_product_meta_lookup table?
		        	// $wp_linked_product_id = wc_get_product_id_by_sku($linked_reference);
		        	$wp_linked_product_id = $this->sl_find_product_id_by_sku($linked_reference);
		        	if ($wp_linked_product_id != 0 && !in_array($wp_linked_product_id, $linked_product_ids)){

		        		$linked_product_ids[] = $wp_linked_product_id;

		        	}

		        }

		        if (!empty($linked_product_ids)){ asort($linked_product_ids); }

	            if ($linked_type == '_children'){

	            	if (!empty($linked_product_ids) && $wp_product_type !== 'grouped'){
	            
	            		sl_wp_set_object_terms( $wp_product_id, 'grouped', 'product_type' );

		            }else if (empty($linked_product_data) && $wp_product_type == 'grouped'){
		        
		        		sl_wp_set_object_terms( $wp_product_id, 'simple', 'product_type' );

			    	}
	            	
	            }

	            if (array_values($wp_product_linked_ids) !== array_values($linked_product_ids)){
	        	
	        		sl_update_post_meta($wp_product_id, $linked_type, $linked_product_ids);
	        	
	        	}

	        }

	    }

	}

	/**
	 * Function to synchronize product attributes.
	 * @param  integer $product_id    	WP product id
	 * @param  array $product_data   	product data
	 * @param  integer $sl_product_id 	SL product id
	 * @return void                
	 */
	public function sync_product_attributes($product_id, $product_data, $sl_product_id){

		$wp_product = wc_get_product( $product_id );
		
		$wp_product_attributes = get_post_meta( $product_id, '_product_attributes' );
		
		$wp_product_attributes_count = 0;
		if (!empty($wp_product_attributes)){

			$wp_product_attributes = $wp_product_attributes[0];
			$wp_product_attributes_count = count($wp_product_attributes);
			$wp_product_attributes_used = array();

		}

		if (count($this->product_additional_fields) > 0) {
			
			foreach($this->product_additional_fields as $field_name => $field_name_lan) {

				$attribute_values = array();
				$field_name_sanitized = sanitize_title($field_name);

				if (isset($product_data[$field_name_lan]) && ((is_array($product_data[$field_name_lan]) && !empty($product_data[$field_name_lan])) || (!is_array($product_data[$field_name_lan]) && $product_data[$field_name_lan] != ''))){	

					$attribute_taxonomy_name = wc_attribute_taxonomy_name($field_name_sanitized);

					if (!taxonomy_exists($attribute_taxonomy_name)){

						$attribute_taxonomy_name = '';

						if (strpos($field_name_sanitized, '_') !== false){

							$field_name_sanitized_hyphen = str_replace('_', '-', $field_name_sanitized);
							$attribute_taxonomy_name_hyphen = wc_attribute_taxonomy_name($field_name_sanitized_hyphen);

							if (taxonomy_exists($attribute_taxonomy_name_hyphen)){

								$field_name_sanitized = $field_name_sanitized_hyphen;
								$attribute_taxonomy_name = $attribute_taxonomy_name_hyphen;

							}

						}

					}

					if ($attribute_taxonomy_name !== ''){

						if (isset($this->media_field_names[$field_name])){
											        
					        foreach ($product_data[$field_name_lan] as $hash) {
					        
					            foreach ($hash as $file) {
					        
					        		$attribute_values[] = urldecode($file);
					        
					            }
					        
					        }

					    }else{

							if (is_array($product_data[$field_name_lan])){
								
								$attribute_values = $product_data[$field_name_lan];
								
							}else{

								$attribute_values = array($product_data[$field_name_lan]);
								
							}
					    	
					    }

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

							$attribute_values_to_update[] = $attribute_value_sanitized;

						}

						$wp_existing_attributes = wp_get_object_terms( $product_id,  $attribute_taxonomy_name, array('fields' => 'slugs'));
						
						$is_update = true;
						if (!empty($wp_existing_attributes)){
							
							asort($wp_existing_attributes);
							asort($attribute_values_to_update);

							if (array_values($wp_existing_attributes) == array_values($attribute_values_to_update)){
							
								$is_update = false;
								
							}

						}

						if ($is_update){

							sl_wp_set_object_terms( $product_id, $attribute_values_to_update, $attribute_taxonomy_name);
					
						}

						if (empty($wp_product_attributes) || !isset($wp_product_attributes[$field_name_sanitized])){
							
							$wp_product_attributes[$attribute_taxonomy_name] = array(
							        'name' 			=> $attribute_taxonomy_name,
							        'position'      => 0,
							        'is_visible'    => 1,
							        'is_variation'  => 0,
							        'is_taxonomy'   => 1
							);
					
						}
						
						$wp_product_attributes_used[] = $attribute_taxonomy_name;
					
					}else{

						$attribute_value = '';
												
					    if (isset($this->media_field_names[$field_name])){
					        
					        foreach ($product_data[$field_name_lan] as $hash) {
					        
					            foreach ($hash as $file) {
					        
					                if ($attribute_value == ''){
					        
					                	$attribute_value = urldecode($file);
					        
					                }else{
					        
					                	$attribute_value.= '|'.urldecode($file);
					        
					                }
					        
					            }
					        
					        }

					    }else{

							if (is_array($product_data[$field_name_lan]) && !empty($product_data[$field_name_lan])){
								
								$attribute_value = implode('|', $product_data[$field_name_lan]);
								
							}else{

								$attribute_value = $product_data[$field_name_lan];
								
							}
					    	
					    }

						if (!empty($wp_product_attributes) && isset($wp_product_attributes[$field_name_sanitized])){
						
							$wp_product_attributes[$field_name_sanitized]['value'] = $attribute_value;
						
						}else{
							
							$wp_product_attributes[$field_name_sanitized] = array(
									'name' 			=> htmlspecialchars(stripslashes($field_name)),
							        'value'         => $attribute_value,
							        'position'      => 0,
							        'is_visible'    => 1,
							        'is_variation'  => 0,
							        'is_taxonomy'   => 0
							);

						}

						$wp_product_attributes_used[] = $field_name_sanitized;

					}

				}

			}

			foreach ($wp_product_attributes as $keyPA => $wp_product_attribute){

				if (!in_array($wp_product_attribute['name'], $wp_product_attributes_used) && $wp_product_attribute['is_variation'] != 1){
					
					unset($wp_product_attributes[$keyPA]);
					if (substr($keyPA, 0, 3) == 'pa_'){
					
						wp_delete_object_term_relationships( $product_id, $keyPA );
					}

				}
		
			}
				
			$pos = 0;
			foreach ($wp_product_attributes as $keyPA => $wp_product_attribute) {
			
				$wp_product_attributes[$keyPA]['position'] = $pos;
				$pos++;
			
			}

			sl_update_post_meta( $product_id, '_product_attributes', $wp_product_attributes );
			
		}

	}

	/**
	 * Function to create a product.
	 * @param string $sl_product_id 		Sales Layer id
	 * @param string $comp_id				Sales Layer company id
	 * @param string $category_ids 			product category ids
	 * @param array $sl_product_data 		product data
	 * @return boolean 						result of creation
	 */
	public function create_product($sl_product_id, $comp_id, $category_ids, $sl_product_data){

		($sl_product_data[$this->product_field_description] != '') ? $post_content = $sl_product_data[$this->product_field_description] : $post_content = 'Product '.$sl_product_id.' description.';

		$product_id = wp_insert_post( array(
		    'post_title' => $sl_product_data[$this->product_field_name],
		    'post_name' => sanitize_title($sl_product_data[$this->product_field_name]),
		    'post_content' => $post_content,
		    'post_status' => 'publish',
		    'post_type' => 'product',
		) );

		if( is_wp_error( $product_id ) ) {

			sl_debbug('## Error. create_product: '.$product_id->get_error_message());

		}else if ($product_id){

			sl_wp_set_object_terms( $product_id, 'simple', 'product_type' );

			sl_update_post_meta($product_id, '_saleslayerid', $sl_product_id);
			sl_update_post_meta($product_id, '_saleslayercompid', $comp_id);

			sl_update_post_meta($product_id, '_stock_status', 'outofstock');
			sl_update_post_meta($product_id, '_manage_stock', 'no' );

			if (SLYR_WC_DEBBUG) sl_debbug("Product created!");
			return true;

		}

		return false;

	}

	/**
	 * Function to find a product by sku or name.
	 * @param string $product_sku 				product sku
	 * @param string $product_name  			product name
	 * @param string $product_id 				Sales Layer id
	 * @param string $comp_id 					Sales Layer company id
	 * @return boolean 							result of finding
	 */
	public function find_product_by_sku_or_name($product_sku, $product_name, $product_id, $comp_id){

		if (!in_array($product_sku, array('', null))){

			if ($this->find_product_by_sku($product_sku, $product_id, $comp_id)){
		
				return true;

			}

		}

		if (!in_array($product_name, array('', null))){

			if ($this->find_product_by_name($product_name, $product_id, $comp_id)){
		
				return true;

			}

		}

		return false;

		

	}

	/**
	 * Function to find a product by sku.
	 * @param string $product_sku 				product sku
	 * @param string $product_id 				Sales Layer id
	 * @param string $comp_id 					Sales Layer company id
	 * @return boolean 							result of finding
	 */
	public function find_product_by_sku($product_sku, $product_id, $comp_id){

		$posts = get_posts(
			array(
			    'post_type' => 'product',
			    'post_status' => array('publish', 'pending', 'draft', 'private', 'trash'),
			    'meta_query' => array(
			    	array(
				    	'key' => '_sku',
				    	'value' => $product_sku,
				    	'compare' => '='
			    	)
			    ),
	    	)
		);

		if( is_wp_error( $posts ) ) {

			sl_debbug('## Error. find_product_by_sku: '.$posts->get_error_message());

		}else{

		    if (!empty($posts)){
		    	
		    	$wp_product = json_decode(json_encode($posts[0]), true);
		    	$sku = get_post_meta($wp_product['ID'], '_sku', true);
				$wp_saleslayerid = get_post_meta($wp_product['ID'], '_saleslayerid', true);
				$wp_saleslayercompid = get_post_meta($wp_product['ID'], '_saleslayercompid', true);
				
				if (in_array($wp_saleslayerid, array(0,'',null)) && in_array($wp_saleslayercompid, array(0,'',null))){
				
					sl_update_post_meta($wp_product['ID'], '_saleslayerid', $product_id);
					sl_update_post_meta($wp_product['ID'], '_saleslayercompid', $comp_id);

					sl_wp_update_post(array('ID' => $wp_product['ID'], 'post_status' => 'publish'), true);
				
					return true;

				}else if ($wp_saleslayerid == $product_id && $wp_saleslayercompid == $comp_id){

					return true;

				}

			}

		}

		return false;

	}

	/**
	 * Function to find a product id by sku.
	 * @param string $product_sku 				product sku
	 * @return boolean 							result of finding
	 */
	public function sl_find_product_id_by_sku($product_sku){

		$posts = get_posts(
			array(
			    'post_type' => 'product',
			    'post_status' => array('publish', 'pending', 'draft', 'private', 'trash'),
			    'meta_query' => array(
			    	array(
				    	'key' => '_sku',
				    	'value' => $product_sku,
				    	'compare' => '='
			    	)
			    ),
	    	)
		);

		if( is_wp_error( $posts ) ) {

			sl_debbug('## Error. sl_find_product_id_by_sku: '.$posts->get_error_message());

		}else{

		    if (!empty($posts)){
		    	
		    	$wp_product = json_decode(json_encode($posts[0]), true);
		    	return $wp_product['ID'];
		    
			}

		}

		return 0;

	}

	/**
	 * Function to find a product by name.
	 * @param string $product_name 				product name
	 * @param string $product_id 				Sales Layer id
	 * @param string $comp_id 					Sales Layer company id
	 * @return boolean 							result of finding
	 */
	public function find_product_by_name($product_name, $product_id, $comp_id){

		$wp_product = sl_get_page_by_title($product_name, 'ARRAY_A', 'product');

		if( is_wp_error( $wp_product ) ) {

			sl_debbug('## Error. find_product_by_name: '.$wp_product->get_error_message());

		}else{

			if (!empty($wp_product)){
				
				$wp_saleslayerid = get_post_meta($wp_product['ID'], '_saleslayerid', true);
				$wp_saleslayercompid = get_post_meta($wp_product['ID'], '_saleslayercompid', true);
				
				if (in_array($wp_saleslayerid, array(0,'',null)) && in_array($wp_saleslayercompid, array(0,'',null))){
					
					sl_update_post_meta($wp_product['ID'], '_saleslayerid', $product_id);
					sl_update_post_meta($wp_product['ID'], '_saleslayercompid', $comp_id);
				
					sl_wp_update_post(array('ID' => $wp_product['ID'], 'post_status' => 'publish'), true);

					return true;

				}else if ($wp_saleslayerid == $product_id && $wp_saleslayercompid == $comp_id){

					return true;

				}

			}

		}

		return false;

	}

	/**
	 * Function to delete a stored product.
	 * @param  array $product_to_delete 		SL product id to delete
	 * @return string  							result of delete
	 */
	public function delete_stored_product ($product_to_delete) {

		sl_debbug('Disabling product with SL id: '.$product_to_delete.' comp_id: '.$this->comp_id. '. Setting it to draft status.');

		$wp_product = find_saleslayer_product($product_to_delete, $this->comp_id);
		if ($wp_product){

			sl_delete_post_meta($wp_product['ID'], '_saleslayerid');
			sl_delete_post_meta($wp_product['ID'], '_saleslayercompid');

			sl_wp_update_post(array('ID' => $wp_product['ID'], 'post_status' => 'draft'), true);
			
		}else{

			sl_debbug('## Error. The product with id: '.$product_to_delete.' does not exist.');
			return 'item_not_deleted';

		}

		return 'item_deleted';

	}

}