<?php 
//http://stackoverflow.com/questions/26690047/how-to-programatically-set-the-category-for-a-new-woocommerce-product-creation

class Category {

	public $category_id_field 						= 'ID';
	public $category_id_parent_field 				= 'ID_PARENT';
	protected $category_field_name 					= 'section_name';
	protected $category_field_description 			= 'section_description';
	protected $category_field_description_short 	= 'section_description_short';
	protected $category_field_image 				= 'section_image';
	protected $category_field_order 				= 'section_order';
	protected $category_images_sizes 				= array();

	private $sl_data_schema 						= array();
	public $comp_id;
	public $default_cat_id 							= 0;

	protected $media_class;

	protected $debugg_level;

	public function __construct()
	{
		global $debbug_level;
		$this->debbug_level = $debbug_level ?? 0;
	}


	public function set_class_field_value($field_name, $field_value){

		$this->$field_name = $field_value;

	}

	/**
	 * Function set connector's data schema.
	 * @param array $sl_data_schema 		connector's data schema
	 * @return void
	 */
	public function set_data_schema($sl_data_schema){

		$this->sl_data_schema = $sl_data_schema;

	}

	/**
	* Function to get Sales Layer category params data.
	* @param  array $sl_language 					Sales Layer connector language
	* @return array $category_params       			category params to store
	*/
	public function getCategoryParamsToStore($sl_language){

		$category_params = $category_images_sizes = [];

		$data_schema = json_decode($this->sl_data_schema, 1);
	    $schema = $data_schema['catalogue'];

	    if (!empty($schema['fields'][$this->category_field_image]['image_sizes'])) {

	        $category_field_images_sizes = $schema['fields'][$this->category_field_image]['image_sizes'];
	        $ordered_image_sizes = order_array_img($category_field_images_sizes);

	        foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

	            $category_images_sizes[] = $img_size;

	        }

	    } else if (!empty($schema['fields']['image_sizes'])) {

	        $category_field_images_sizes = $schema['fields']['image_sizes'];
	        $ordered_image_sizes = order_array_img($category_field_images_sizes);

	        foreach ($ordered_image_sizes as $img_size => $img_dimensions) {

	            $category_images_sizes[] = $img_size;

	        }

	    } else {

	        $category_images_sizes = ['IMD', 'THM', 'TH'];

	    }

	    $category_params['category_fields']['category_images_sizes'] = $category_images_sizes;

        $field_names = ['category_field_name',
    					'category_field_description',
    					'category_field_order',
    					'category_field_image',
    				];

		foreach ($field_names as $field_name){

    	    if (isset($schema['fields'][$this->$field_name]) && $schema['fields'][$this->$field_name]['has_multilingual']) {

    	        $this->$field_name .= '_'.$sl_language;

    	    }

            $category_params['category_fields'][$field_name] = $this->$field_name;
        
        }

		return $category_params;

	}

	/**
	* Function to prepare Sales Layer categories data.
	* @param  array $category_data                 	categories data to organize
	* @return array $category_data       			categories data to store
	*/
	public function prepareCategoryDataToStore($category_data) {

		if (!empty($category_data)){

	        $time_ini_reorganize_categories = microtime(1);
	        $category_data = $this->reorganize_categories($category_data);
	        sl_debbug('### reorganize_categories: '.(microtime(1) - $time_ini_reorganize_categories).' seconds.');
	        
	    }

	    return $category_data;

	}

	/**
	 * Function to synchronize a stored category.
	 * @param array $category 		category to synchronize
	 * @return string 				result of synchronization
	 */
	public function sync_stored_category($category){

		$time_ini_category_core_data = microtime(1);

		$sl_category_id        = $category[$this->category_id_field];
		$sl_category_parent_id = $category[$this->category_id_parent_field];
		$category_data         = $category['data'];

		if ($sl_category_parent_id != '0') {
			
			$wp_parent_category = find_saleslayer_term('product_cat' , $sl_category_parent_id, $this->comp_id);

			if(!$wp_parent_category) {
				
				sl_debbug('## Error. SL ID: '.$sl_category_id.' : '.$category_data[$this->category_field_name].' - Error creating the category, category parent not found.');
				return 'item_not_updated';
			}

			$category_parent_id = $wp_parent_category['term_id'];

		}else{

			$category_parent_id = $this->default_cat_id;

		}

		$wp_category = find_saleslayer_term('product_cat' , $sl_category_id, $this->comp_id);

		if (!$wp_category){
			
			$wp_category = $this->find_category_by_name($category_data[$this->category_field_name], $sl_category_id, $this->comp_id);
			if (!$wp_category){

				$time_ini_create_category = microtime(1);
				$this->create_category($sl_category_id, $this->comp_id, $category_parent_id, $category_data);
				sl_debbug('## time_create_category: '.(microtime(1) - $time_ini_create_category).' seconds.', 'timer');
			
			}
			
			$wp_category = find_saleslayer_term('product_cat' , $sl_category_id, $this->comp_id);

			if (!$wp_category){
				sl_debbug('## Error. SL ID: '.$sl_category_id.' : '.$category_data[$this->category_field_name].' - Error while creating the category.');
				return 'item_not_updated';
			
			}
		
		}

		if ($this->debbug_level) sl_debbug(" > Updating category ID: $sl_category_id (parent: $sl_category_parent_id)");

		if ($this->debbug_level > 1) sl_debbug(" Name ({$this->category_field_name}): ".$category_data[$this->category_field_name]);

		$category_modified = false;
		$category_data_modified = array();

		if ($wp_category['name'] != $category_data[$this->category_field_name]){
		
			$category_data_modified['name'] = $category_data[$this->category_field_name];
			$category_data_modified['slug'] = sanitize_title($category_data[$this->category_field_name]);
			$category_modified = true;
		
		}

		if (wp_specialchars_decode($wp_category['description']) != $category_data[$this->category_field_description]){
		
			$category_data_modified['description'] = $category_data[$this->category_field_description];
			$category_modified = true;
			
		};

		if ($wp_category['parent'] != $category_parent_id){
		
			$category_data_modified['parent'] = $category_parent_id;
			$category_modified = true;
		
		}

		if (isset($category_data[$this->category_field_order]) && (!isset($wp_category['order']) || (isset($wp_category['order']) && $wp_category['order'] != $category_data[$this->category_field_order]))){

			sl_update_woocommerce_term_meta($wp_category['term_id'], 'order', $category_data[$this->category_field_order]);
			
		};

		sl_debbug('## time_category_core_data: '.(microtime(1) - $time_ini_category_core_data).' seconds.', 'timer');

		$time_ini_category_images = microtime(1);
		
		if (!empty($category_data[$this->category_field_image])) {

			if (is_null($this->media_class)) $this->media_class = Media_class::get_instance();

			$sl_category_images = $category_data[$this->category_field_image];

			if(count($sl_category_images) > 0) {

				$wp_thumbnail_id = $wp_category_image_name = $wp_category_image_size = '';

                if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
                    $wp_thumbnail_id = get_term_meta($wp_category['term_id'], 'thumbnail_id', true);
                }else{
                    $wp_thumbnail_id = get_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', true);
                }

				if (!in_array($wp_thumbnail_id, array('', 0, null, false))){
				
					$wp_category_image_url = wp_get_attachment_url($wp_thumbnail_id);
					$wp_parse_category_image_url = pathinfo($wp_category_image_url);
					$wp_category_image_name = $wp_parse_category_image_url['basename'];
					$wp_category_image_size = $this->media_class->read_image_file_size($wp_category_image_url);
				
				}

				foreach ($this->category_images_sizes as $img_format) {

					foreach ($sl_category_images as $sl_category_image) {
						
						if (!empty($sl_category_image[$img_format])) {

							$image_url = $sl_category_image[$img_format];
							$filesize_image = $this->media_class->read_image_file_size($image_url);
							if (!$filesize_image){ continue; }

							$parse_url_image = pathinfo($image_url);
							$parse_url_image_basename = urldecode($parse_url_image['basename']);
							
							if ($parse_url_image_basename == $wp_category_image_name){
								
								if (!$wp_category_image_size || ($wp_category_image_size !== false && $wp_category_image_size !== $filesize_image)){

									if (!$this->media_class->update_media($image_url, $wp_thumbnail_id, true)){
								
										continue;
								
									}
								
								}
							
							}else{

								$thumb_id = $this->media_class->get_thumbnail_id_by_title($parse_url_image_basename);
								
								if ($thumb_id === 0){
								
									$new_wp_thumbnail_id = $this->media_class->fetch_media($image_url, $wp_category['term_id'], true);
									sl_update_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', $new_wp_thumbnail_id);

								}else{

									$wp_category_image_url = wp_get_attachment_url($thumb_id);
									$wp_category_image_size = $this->media_class->read_image_file_size($wp_category_image_url);

									if (!$wp_category_image_size || ($wp_category_image_size !== false && $wp_category_image_size !== $filesize_image)){
								
										if (!$this->media_class->update_media($image_url, $thumb_id, true)){

											continue;
								
										}
								
									}

									sl_update_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', $thumb_id);

								}

								if (!in_array($wp_thumbnail_id, array('', 0, null, false))){ $this->media_class->delete_media($wp_thumbnail_id); }

							}

							break 2;

						}

					}

				}

			}

		}
		sl_debbug('## time_category_images: '.(microtime(1) - $time_ini_category_images).' seconds.', 'timer');
		
		$time_ini_category_save = microtime(1);
		if ($category_modified){
		
			try {

				$resultado = wp_update_term($wp_category['term_id'], 'product_cat', $category_data_modified);

				if( is_wp_error( $resultado ) ) {

					sl_debbug('## Error. sync_category category_modified: '.$resultado->get_error_message());

				}		
				
				if ($this->debbug_level) sl_debbug("Category updated!");

			} catch (\Exception $e) {

				if ($this->debbug_level) sl_debbug('## Error. SL ID: '.$sl_category_id.' : '.$category_data[$this->category_field_name].' - '.$e->getMessage());
	            return 'item_not_updated';

			}
			
		}
		
		sl_debbug('## time_category_save: '.(microtime(1) - $time_ini_category_save).' seconds.', 'timer');

		return 'item_updated';
		
	}

	/**
	 * Function to create a category.
	 * @param string $sl_category_id 		Sales Layer id
	 * @param string $comp_id				Sales Layer company id
	 * @param string $category_parent_id 	id of parent category
	 * @param array $sl_category_data 		category data
	 * @return boolean 						result of creation
	 */
	public function create_category($sl_category_id, $comp_id, $category_parent_id, $sl_category_data){

		$category = wp_insert_term(
			$sl_category_data[$this->category_field_name],
			'product_cat',
			array(
				'description' => $sl_category_data[$this->category_field_description],
				'parent' => $category_parent_id,
				'slug' => sanitize_title($sl_category_data[$this->category_field_name]),
			)
		);

		if (!is_wp_error($category)){
		
			if (is_object($category)){

				$category_id = isset( $category->term_id ) ? $category->term_id : 0;

			}else{

				$category_id = isset( $category['term_id'] ) ? $category['term_id'] : 0;

			}

			
			if ($category_id){

				sl_update_woocommerce_term_meta($category_id, 'saleslayerid', $sl_category_id);
				sl_update_woocommerce_term_meta($category_id, 'saleslayercompid', $comp_id);

				if ($this->debbug_level) sl_debbug("Category created!");

				return true;

			}

		}else{

			sl_debbug('## Error. create_category: '.$category->get_error_message());

		}

		return false;

	}

	/**
	 * Function to find a category by name.
	 * @param string $category_name 			category name
	 * @param string $category_id 				Sales Layer id
	 * @param string $comp_id 					Sales Layer company id
	 * @return boolean 							result of finding
	 */
	public function find_category_by_name($category_name, $category_id, $comp_id){

		$wp_category = sl_find_unassigned_product_cat_terms_by_name($category_name, ARRAY_A);

		if (!empty($wp_category)){

			sl_update_woocommerce_term_meta($wp_category['term_id'], 'saleslayerid', $category_id);
			sl_update_woocommerce_term_meta($wp_category['term_id'], 'saleslayercompid', $comp_id);

			return true;

		}

		return false;

	}

	/**
	 * Function to delete a stored category.
	 * @param  array $category_to_delete 		SL category id to delete
	 * @return string  							result of delete
	 */
	public function delete_stored_category ($category_to_delete) {

		sl_debbug('Deleting category with SL id: '.$category_to_delete.' comp_id: '.$this->comp_id);
		
		$wp_category = find_saleslayer_term('product_cat' , $category_to_delete, $this->comp_id);
		
		if ($wp_category){

		    if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
                $wp_thumbnail_id = get_term_meta($wp_category['term_id'], 'thumbnail_id', true);
            }else{
                $wp_thumbnail_id = get_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', true);
            }
		
			if (wp_delete_term( $wp_category['term_id'], 'product_cat')){

				if ($wp_thumbnail_id != ''){

					if (is_null($this->media_class)) $this->media_class = Media_class::get_instance();

					if (!in_array($wp_thumbnail_id, array('', 0, null, false))){ $this->media_class->delete_media($wp_thumbnail_id); }

				}

			}

		}else{

			sl_debbug('## Error. The category with id: '.$category_to_delete.' does not exist.');
			return 'item_not_deleted';

		}

		return 'item_deleted';

	}

	/**
	 * Function to reorganize categories by its parents
	 *
	 * @param array $categories data
	 * @return array $new_categories reorganized data
	 */
	private function reorganize_categories($categories){
			
		$new_categories = array();

		if (count($categories) > 0){

			$counter = 0;
			$first_level = $first_clean = true;
			$categories_loaded = array();
			
			do{

				$level_categories = $this->get_level_categories($categories, $categories_loaded, $first_level);
				
				if (!empty($level_categories)){
				
					$counter = 0;
					$first_level = false;
				
					foreach ($categories as $keyCat => $category) {
						
						if (isset($level_categories[$category[$this->category_id_field]])){
							
							array_push($new_categories, $category);
							$categories_loaded[$category[$this->category_id_field]] = 0;
							unset($categories[$keyCat]);
				
						}

					}

				}else{

					$counter++;

				}

				if ($counter == 3){
			
					if ($first_clean && !empty($categories)){

						$categories_not_loaded_ids = array_flip(array_column($categories, $this->category_id_field));
			
						foreach ($categories as $keyCat => $category) {
							
							if (!is_array($category[$this->category_id_parent_field])){
							
								$category_parent_ids = array($category[$this->category_id_parent_field]);
							
							}else{
							
								$category_parent_ids = array($category[$this->category_id_parent_field]);
							
							}

							$has_any_parent = false;
							
							foreach ($category_parent_ids as $category_parent_id) {
								
								if (isset($categories_not_loaded_ids[$category_parent_id])){

									$has_any_parent = true;
									break;

								} 

							}

							if (!$has_any_parent){

								$category[$this->category_id_parent_field] = 0;

								array_push($new_categories, $category);
								$categories_loaded[$category[$this->category_id_field]] = 0;
								unset($categories[$keyCat]);
							
								$counter = 0;
								$first_clean = $first_level = false;

							}

						}

					}else{

						break;

					}

				}

			}while (count($categories) > 0);	
		
		}

		return $new_categories;

	}

	/**
	 * Function to get categories by its root level
	 *
	 * @param array $categories data
	 * @return array $level_categories categories that own to that level
	 */
	private function get_level_categories($categories, $categories_loaded, $first = false){

		$level_categories = array();

		if ($first){

			foreach ($categories as $category) {
				
				if (!is_array($category[$this->category_id_parent_field]) && $category[$this->category_id_parent_field] == 0){
		
					$level_categories[$category[$this->category_id_field]] = 0;
				
				}

			}

		}else{

			foreach ($categories as $category) {
				
				if (!is_array($category[$this->category_id_parent_field])){
				
					$category_parent_ids = array($category[$this->category_id_parent_field]);
				
				}else{
				
					$category_parent_ids = array($category[$this->category_id_parent_field]);
				
				}

				$parents_loaded = true;
				foreach ($category_parent_ids as $category_parent_id) {
					
					if (!isset($categories_loaded[$category_parent_id])){

						$parents_loaded = false;
						break;
					} 
				}

				if ($parents_loaded){

					$level_categories[$category[$this->category_id_field]] = 0;

				}

			}

		}

		return $level_categories;

	}

}