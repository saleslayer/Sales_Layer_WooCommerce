<?php 

include_once(SLYR_WC__PLUGIN_DIR.'admin/lib/SalesLayer-Conn.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/Connector.class.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/Category.class.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/Product.class.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/Format.class.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/general_functions.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/media_functions.php');

class Synchronize {
	
	protected       $sl_time_ini_sync_data_process;
	protected       $max_execution_time                 = 240;//110;
	protected       $sync_data_flag;
	protected       $end_process;
	protected       $initialized_vars                   = false;
	protected       $sql_items_delete                   = array();
	protected       $category_fields                    = array();
	protected       $product_fields                     = array();
	protected       $product_format_fields              = array();

    protected 		$sql_to_insert                      = array();
    protected		$sql_to_insert_limit 				= 20;
    protected       $syncdata_pid;
    protected 		$processing_data					= false;
    protected 		$counters_info 						= array();

    protected		$cat_class;
    protected		$prod_class;
    protected		$form_class;

	public function __construct () {

	    global $wpdb;
		$this->db = $wpdb;
		
	}

	/**
	 * Function to get data schema from the connector images.
	 * @param class $slconn 		Sales Layer connector
	 * @return array 				connector's schema
	 */
	private function get_data_schema ($slconn) {

	    $info = $slconn->get_response_table_information();
	    $schema = array();

	    if (is_array($info) && !empty($info)){

		    foreach ($info as $table => $data) {

		        if (isset($data['table_joins'])) {

		            $schema[$table]['table_joins']=$data['table_joins'];
		        }

		        if (isset($data['fields'])) {

		            foreach ($data['fields'] as $field => $struc) {

		                if (isset($struc['has_multilingual']) and $struc['has_multilingual']) {

		                    if (!isset($schema[$table][$field])) {

		                        $schema[$table]['fields'][$struc['basename']] = array(

		                            'type'            =>$struc['type'],
		                            'has_multilingual'=>1,
		                            'multilingual_name' => $field
		                        );

		                        if ($struc['type']=='image') {

		                            $schema[$table]['fields']['image_sizes']=$struc['image_sizes'];
		                        }
		                    }

		                } else {

		                    $schema[$table]['fields'][$field]=$struc;
		                }
		            }
		        }
		    }

		}
		
	    if (SLYR_WC_DEBBUG > 2) sl_debbug('Schema: '.print_r($schema, 1));

	    return $schema;
	}

	/**
	 * Function to check current process time to avoid exceding the limit.
	 * @return void
	 */
	private function check_process_time(){

	    $current_process_time = microtime(1) - $this->sl_time_ini_sync_data_process;
	    if ($current_process_time >= $this->max_execution_time){

	    	if (file_exists($this->sync_data_flag)){
		        unlink($this->sync_data_flag);
		    }
	        $this->end_process = true;

	    }

	}

	/**
	 * Function to initialize catalogue vars to load before synchronizing.
	 * @return void
	 */
	private function initialize_vars(){

	    if (!$this->initialized_vars){
	        
	        $this->category_fields = array('category_field_name', 'category_field_description', 'category_field_description_short', 'category_field_image', 'category_images_sizes');
            $this->product_fields = array('product_field_name', 'product_field_description', 'product_field_description_short', 'product_field_image', 'product_field_sku', 'product_field_stock', 'product_field_manage_stock', 'product_field_stock_status', 'product_field_menu_order', 'product_field_weight', 'product_field_length', 'product_field_width', 'product_field_height', 'product_field_purchase_note', 'product_field_regular_price', 'product_field_sale_price', 'product_field_tags', 'product_field_downloadable', 'product_field_virtual', 'product_images_sizes', 'product_additional_fields', 'image_extensions');
            $this->product_format_fields = array('format_field_sku', 'format_field_description', 'format_field_regular_price', 'format_field_sale_price', 'format_field_stock', 'format_manage_stock', 'format_stock_status', 'format_field_weight', 'format_field_length', 'format_field_width', 'format_field_height', 'format_field_enabled', 'format_field_downloadable', 'format_field_virtual', 'format_field_image', 'format_images_sizes', 'format_additional_fields', 'parent_product_attributes', 'image_extensions');
	        $this->initialized_vars = true;

	    }

	}

	/**
	 * Function to check sql rows to delete from sync data table.
	 * @return void
	 */
	private function check_sql_items_delete($force_delete = false){

	    if (count($this->sql_items_delete) >= 20 || ($force_delete && count($this->sql_items_delete) > 0)){
	        
	        $sql_items_to_delete = implode(',', $this->sql_items_delete);
	        
	        $sql_delete = " DELETE FROM ".SLYR_WC_syncdata_table." WHERE id IN (".$sql_items_to_delete.")";
	        sl_connection_query('delete', $sql_delete);

	        $this->sql_items_delete = array();

	    }

	}

	/**
	 * Function to check sync data pid flag in database and delete kill it if the process is stuck.
	 * @return void
	 */
	private function check_sync_data_flag(){

    	$items_to_process = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table);
    	
    	if (isset($items_to_process['sl_cuenta_registros']) && $items_to_process['sl_cuenta_registros'] > 0){

	        $current_flag = sl_connection_query('read', " SELECT * FROM ".SLYR_WC_syncdata_flag_table." ORDER BY id DESC LIMIT 1");
	        $now = strtotime('now');
	        $date_now = date('Y-m-d H:i:s', $now);

	        if (!empty($current_flag) && isset($current_flag[0])){

	        	$current_flag = $current_flag[0];
	            
	            if ($current_flag['syncdata_pid'] == 0){
	            
	                $sl_query_flag_to_update = " UPDATE ".SLYR_WC_syncdata_flag_table.
	                                        " SET syncdata_pid = ".$this->syncdata_pid.", syncdata_last_date = '".$date_now."'".
	                                        " WHERE id = ".$current_flag['id'];
	            
	                sl_connection_query('update', $sl_query_flag_to_update);

	            }else{

	                $interval  = abs($now - strtotime($current_flag['syncdata_last_date']));
	                $minutes   = round($interval / 60);
	        
	                if ($minutes < 10){
	                
	                    sl_debbug('Data is already being processed.', 'syncdata');
	                    $this->end_process = $this->processing_data = true;

	                }else{
	                    
	                    if ($this->syncdata_pid === $current_flag['syncdata_pid']){
	                    	
	                        sl_debbug('Pid is the same as current.', 'syncdata');

	                    }

	                    $flag_pid_is_alive = $this->has_pid_alive($current_flag['syncdata_pid']);
	                    
	                    if ($flag_pid_is_alive > 1){
	                    
	                        try{

	                            sl_debbug('Killing pid: '.$current_flag['syncdata_pid'], 'syncdata');
	                            shell_exec("kill -9 ".$current_flag['syncdata_pid']);
	                    
	                        }catch(\Exception $e){
	                    
	                            sl_debbug('## Error. Exception killing pid '.$current_flag['syncdata_pid'].': '.print_r($e->getMessage(),1), 'syncdata');
	                    
	                        }
	                    }
	                    
	                    $sl_query_flag_to_update = " UPDATE ".SLYR_WC_syncdata_flag_table.
	                                            " SET syncdata_pid = ".$this->syncdata_pid.", syncdata_last_date = '".$date_now."'".
	                                            " WHERE id = ".$current_flag['id'];

	                    sl_connection_query('update', $sl_query_flag_to_update);
	                   
	                }

	            }
	            

	        }else{
	        	
	            $sl_query_flag_to_insert = " INSERT INTO ".SLYR_WC_syncdata_flag_table.
	                                     " ( syncdata_pid, syncdata_last_date) VALUES ".
	                                     "('".$this->syncdata_pid."', '".$date_now."')";
	            
	            sl_connection_query('insert', $sl_query_flag_to_insert);

	        }

	    }

	}

	/**
	* Function to disable sync data pid flag in database.
	* @return void
	*/
	private function disable_sync_data_flag(){

	    $current_flag = sl_connection_query('read', " SELECT * FROM ".SLYR_WC_syncdata_flag_table." ORDER BY id DESC LIMIT 1");
	    
	    if (!empty($current_flag) && isset($current_flag[0])){

	        $sl_query_flag_to_update = " UPDATE ".SLYR_WC_syncdata_flag_table.
	                                " SET syncdata_pid = 0".
	                                " WHERE id = ".$current_flag[0]['id'];
	        sl_connection_query('update', $sl_query_flag_to_update);

	    }

	}

	/**
	 * Function to load syncdata counters into a class variable.
	 * @return void
	 */
	private function load_syncdata_counters(){

		$counters_info = sl_connection_query('read', " SELECT * FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'info' AND item_type = 'counters'");
		
		if (!empty($counters_info) && isset($counters_info[0])){
			
			$this->counters_info['id'] = $counters_info[0]['id'];
			$this->counters_info['info'] = json_decode(stripslashes($counters_info[0]['item_data']),1);
		
		}

	}

	/**
	 * Function to update syncdata counters in database.
	 * @param  string $table       			table of counter
	 * @param  string $type_update 			type of counter
	 * @return void
	 */
	private function update_syncdata_counters($table, $type_update){

		$table_idx = '';
		switch ($table) {
			case 'category':
				$table_idx = 'catalogue';
				break;
			
			case 'product':
				$table_idx = 'products';
				break;

			case 'product_format':
				$table_idx = 'product_formats';
				break;

			case 'product_links':
				$table_idx = 'product_links';
				break;
			
			default:
				
				break;
		}
		
		if ($table_idx === ''){ return false; }

		if (!empty($this->counters_info)){

			if ($table_idx == 'product_links' && !isset($this->counters_info['info'][$table_idx][$type_update]['total'])){

				$product_links_total_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'update' AND item_type = 'product_links'");
			
			    if (isset($product_links_total_count['sl_cuenta_registros']) && $product_links_total_count['sl_cuenta_registros'] > 0){

					$this->counters_info['info'][$table_idx][$type_update]['total'] = $product_links_total_count['sl_cuenta_registros'];

				}

			}

			if (!isset($this->counters_info['info'][$table_idx][$type_update]['processed'])){

				$this->counters_info['info'][$table_idx][$type_update]['processed'] = 0;

			}

			$this->counters_info['info'][$table_idx][$type_update]['processed']++;
			
			$sl_query_counters_to_update = " UPDATE ".SLYR_WC_syncdata_table.
			                        " SET item_data = '".addslashes(json_encode($this->counters_info['info']))."'".
			                        " WHERE id = ".$this->counters_info['id'];

			sl_connection_query('update', $sl_query_counters_to_update);
		
		}

	} 

	/**
	 * Function to synchronize Sales Layer connectors data stored in sync data table.
	 * @return void
	 */
	public function sync_data_connectors(){

	    $this->syncdata_pid = getmypid();
	    $this->sl_time_ini_sync_data_process = microtime(1);
	    
	    sl_debbug("==== Sync Data INIT ".date('Y-m-d H:i:s')." ====", 'syncdata');
	    
	    try{

	        //Clear exceeded attemps
	        $sql_delete = " DELETE FROM ".SLYR_WC_syncdata_table." WHERE sync_tries >= 3";

	        sl_connection_query('delete', $sql_delete);

	    }catch(\Exception $e){

	        sl_debbug('## Error. Clearing exceeded attemps: '.$e->getMessage(), 'syncdata');

	    }

	    $this->end_process = false;

	    $this->check_sync_data_flag();

	    if (!$this->end_process){

	    	$this->load_syncdata_counters();
	        
	        $result = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table);
	        
	        if (isset($result['sl_cuenta_registros']) && $result['sl_cuenta_registros'] > 0){

	        	$this->cat_class = new Category();
	        	// $prod_class = new Product();
	        	$this->prod_class = Product::get_instance();
	        	$this->form_class = new Format();

	            try {

	                $sql_check_try = 0;	                
	            	
	            	do{

	                
		                $sql_items_to_delete = " SELECT * FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'delete' AND sync_tries < 3 ORDER BY item_type ASC, sync_tries ASC, id ASC";

		                $items_to_delete = sl_connection_query('read', $sql_items_to_delete);
		                
		                if (!empty($items_to_delete)){
		                    
		                	$this->initialize_vars();

		                	$sql_check_try++;

		                    foreach ($items_to_delete as $item_to_delete) {
		                        
		                        $this->check_process_time();
		                        $this->check_sql_items_delete();

		                        if ($this->end_process){
		                        	
		                            sl_debbug('Breaking syncdata process due to time limit.', 'syncdata');
		                            break;

		                        }else{

		                            $sync_tries = $item_to_delete['sync_tries'];

		                            $sync_params = json_decode(stripslashes($item_to_delete['sync_params']),1);
		                            
		                            $item_data = json_decode(stripslashes($item_to_delete['item_data']),1);
		                            
		                            $sl_id = $item_data['sl_id'];
		                            
		                            switch ($item_to_delete['item_type']) {
		                                case 'category':

		                                    $this->cat_class->comp_id = $sync_params['conn_params']['comp_id'];
		                                    $result_delete = $this->cat_class->delete_stored_category($sl_id);
		                                    
		                                    break;
		                                case 'product':
		                                    
		                                    $this->prod_class->comp_id = $sync_params['conn_params']['comp_id'];
		                                    $result_delete = $this->prod_class->delete_stored_product($sl_id);

		                                    break;
		                                case 'product_format':
		                                    
		                                    $this->form_class->comp_id = $sync_params['conn_params']['comp_id'];
		                                    $result_delete = $this->form_class->delete_stored_product_format($sl_id);
		                                    
		                                    break;
		                                default:
		                                    
		                                    sl_debbug('## Error. Incorrect item: '.print_R($item_to_delete,1), 'syncdata');
		                                    break;
		                            }
		                            
		                            switch ($result_delete) {
		                                case 'item_not_deleted':
		                                    
		                                    $sync_tries++;

		                                    $sql_update = " UPDATE ".SLYR_WC_syncdata_table." SET sync_tries = ".$sync_tries." WHERE id = ".$item_to_delete['id'];

		                                    sl_connection_query('update', $sql_update);

		                                    if ($sync_tries == 3){
		                            
		                                    	$this->update_syncdata_counters($item_to_delete['item_type'], 'delete');

		                                    }

		                                    break;
		                                
		                                default:
		                                    
		                                    $this->update_syncdata_counters($item_to_delete['item_type'], 'delete');
		                                    $this->sql_items_delete[] = $item_to_delete['id'];
		                                    break;

		                            }

		                        }

		                    }


		                }else{

		                	break;

		                }
	    
					}while($sql_check_try < 3);

	            } catch (\Exception $e) {

	                sl_debbug('## Error. Deleting syncdata process: '.$e->getMessage(), 'syncdata');

	            }

	            $indexes = array('category', 'product', 'product_format', 'product_links');//, 'product__images');
	            
	            foreach ($indexes as $index) {
	                
	                $sql_check_try = 0;	                

	                do{

	                    $sql_items_to_update = " SELECT * FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'update' and item_type = '".$index."' and sync_tries < 4 ORDER BY item_type ASC, sync_tries ASC, id ASC";

	                    $items_to_update = sl_connection_query('read', $sql_items_to_update);

	                    if (!empty($items_to_update)){

	                        $sql_check_try++;

	                        $this->update_items($items_to_update);

	                    }else{

	                        break;

	                    }
	                    
	                    if ($this->end_process){

	                        break 2;

	                    }

	                }while($sql_check_try < 3);
	                
	            }

	        }
	        
	    }

	    $this->check_sql_items_delete(true);
	    
	    if (!$this->end_process){

	    	$items_processing = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table." WHERE sync_type in('delete','update') and sync_tries <= 2");
	    
	        if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] == 0){

	            $counters_data = sl_connection_query('read', " SELECT * FROM ".SLYR_WC_syncdata_table." WHERE sync_type = 'info' AND item_type = 'counters'");
	     
	            if (!empty($counters_data) && isset($counters_data[0])){
	               	
	                $this->sql_items_delete[] = $counters_data[0]['id'];
	                $this->check_sql_items_delete(true);

	            }

	        }

	    }

	    if (!$this->processing_data){

		    try{

		    	$this->disable_sync_data_flag();

		    }catch(\Exception $e){

		        sl_debbug('## Error. Deleting sync_data_flag: '.$e->getMessage(), 'syncdata');

		    }
	    	
	    }

	    sl_debbug('### time_all_syncdata_process: '.(microtime(1) - $this->sl_time_ini_sync_data_process).' seconds.', 'syncdata');
	    
	    sl_debbug("==== Sync Data END ====", 'syncdata');
	    
	}

	/**
	 * Function to update items depending on type.
	 * @return void
	 */
	private function update_items($items_to_update){

	    $this->initialize_vars();
	    
	    foreach ($items_to_update as $item_to_update) {
	        
	        $this->check_process_time();
	        $this->check_sql_items_delete();

	        if ($this->end_process){

	            sl_debbug('Breaking syncdata process due to time limit.', 'syncdata');
	            break;

	        }else{
	            
	            $sync_tries = $item_to_update['sync_tries'];
	            
	            if ($item_to_update['sync_params'] != ''){

	                $sync_params = json_decode($item_to_update['sync_params'],1);

	            }
	    
	            $item_data = json_decode($item_to_update['item_data'],1);
	            
	            if ($item_data == ''){
	            
	                sl_debbug("## Error. Decoding item's data: ".print_R($item_to_update['item_data'],1), 'syncdata');
	                $result_update = '';
	            
	            }else{
	                
	                switch ($item_to_update['item_type']) {
	                    case 'category':
	                        
	                        $this->cat_class->comp_id = $sync_params['conn_params']['comp_id'];

	                        foreach ($this->category_fields as $category_field) {
	                            
	                            if (isset($sync_params['category_fields'][$category_field])){

	                                $this->cat_class->set_class_field_value($category_field, $sync_params['category_fields'][$category_field]);

	                            }

	                        }
	                        
	                        $time_ini_sync_stored_category = microtime(1);
	                        sl_debbug(' >> Category synchronization initialized << ');
	                        $result_update = $this->cat_class->sync_stored_category($item_data);
	                        sl_debbug(' >> Category synchronization finished << ');
	                        sl_debbug('#### time_sync_stored_category: '.(microtime(1) - $time_ini_sync_stored_category).' seconds.', 'timer');
	                        break;
	                    
	                    case 'product':
	                        
	                        $this->prod_class->comp_id = $sync_params['conn_params']['comp_id'];
	                        
	                        foreach ($this->product_fields as $product_field) {
	                            
	                            if (isset($sync_params['product_fields'][$product_field])){

	                            	$this->prod_class->set_class_field_value($product_field, $sync_params['product_fields'][$product_field]);
	                                
	                            }

	                        }
	                        
	                        if (isset($sync_params['product_additional_fields']) && !empty($sync_params['product_additional_fields'])){

	                        	$product_additional_fields = array();
	                            foreach ($sync_params['product_additional_fields'] as $field_name => $field_name_value) {
	                                
	                                $product_additional_fields[$field_name] = $field_name_value;

	                            }

								$this->prod_class->set_class_field_value('product_additional_fields', $product_additional_fields);

	                        }

	                        if (isset($sync_params['products_media_field_names']) && !empty($sync_params['products_media_field_names'])){

	                        	$this->prod_class->set_class_field_value('media_field_names', $sync_params['products_media_field_names']);

	                        }
	                        
	                        $time_ini_sync_stored_product = microtime(1);
	                        sl_debbug(' >> Product synchronization initialized << ');
	                        $result_update = $this->prod_class->sync_stored_product($item_data);
	                        sl_debbug(' >> Product synchronization finished << ');
	                        sl_debbug('#### time_sync_stored_product: '.(microtime(1) - $time_ini_sync_stored_product).' seconds.', 'timer');
	                        break;

	                    case 'product_format':
	                        
	                        $this->form_class->comp_id = $sync_params['conn_params']['comp_id'];
	                        
	                        foreach ($this->product_format_fields as $product_format_field) {
	                            
	                            if (isset($sync_params['format_fields'][$product_format_field])){

	                            	$this->form_class->set_class_field_value($product_format_field, $sync_params['format_fields'][$product_format_field]);

	                            }

	                        }

                            if (isset($sync_params['format_additional_fields']) && !empty($sync_params['format_additional_fields'])){

                            	$format_additional_fields = array();
                                foreach ($sync_params['format_additional_fields'] as $field_name => $field_name_value) {
                                    
                                    $format_additional_fields[$field_name] = $field_name_value;

                                }

                            	$this->form_class->set_class_field_value('format_additional_fields', $format_additional_fields);

                            }

	                        $time_ini_sync_stored_product_format = microtime(1);
	                        sl_debbug(' >> Format synchronization initialized << ');
	                        $result_update = $this->form_class->sync_stored_product_format($item_data);
	                        sl_debbug(' >> Format synchronization finished << ');
	                        sl_debbug('#### time_sync_stored_product_format: '.(microtime(1) - $time_ini_sync_stored_product_format).' seconds.', 'timer');
	                        break;

	                    case 'product_links':
	                        
	                        $time_ini_sync_stored_product_links = microtime(1);
	                        sl_debbug(' >> Product links synchronization initialized << ');
	                        $this->prod_class->sync_stored_product_links($item_data);
	                        sl_debbug(' >> Product links synchronization finished << ');
	                        $result_update = 'item_updated';
	                        sl_debbug('#### time_sync_stored_product_links: '.(microtime(1) - $time_ini_sync_stored_product_links).' seconds.', 'timer');
	                        break;

	                    default:
	                        
	                        sl_debbug('## Error. Incorrect item: : '.print_R($item_to_update,1), 'syncdata');
	                        break;
	                }

	            }


	            switch ($result_update) {
	                case 'item_not_updated':
	                    
	                    $sync_tries++;
	                    
	                    if ($sync_tries == 2 && $item_to_update['item_type'] == 'category'){

	                        $item_data['catalogue_parent_id'] = 0;
	                        
	                        $sql_update = " UPDATE ".SLYR_WC_syncdata_table.
	                                                " SET sync_tries = ".$sync_tries.", ".
	                                                " item_data = '".json_encode($item_data)."'".
	                                                " WHERE id = ".$item_to_update['id'];

							sl_connection_query('update', $sql_update);

	                    }else{

	                        $sql_update = " UPDATE ".SLYR_WC_syncdata_table.
	                                                " SET sync_tries = ".$sync_tries.
	                                                " WHERE id = ".$item_to_update['id'];

	                        sl_connection_query('update', $sql_update);

	                        if ($sync_tries == 3){

	                        	$this->update_syncdata_counters($item_to_update['item_type'], 'sync');
	                        	
	                        }

	                    }

	                    break;
	                
	                default:
	                    
	                    $this->update_syncdata_counters($item_to_update['item_type'], 'sync');
	                    $this->sql_items_delete[] = $item_to_update['id'];
	                    break;

	            }

	        }

	    }

	    $this->check_sql_items_delete(true);

	}

	/**
	 * Function to store connector's data into sync data table.
	 * @param  string $connector_id SL connector id.
	 * @param  string $secret_key   SL secret key
	 * @return string messages to show in front
	 */
	public function store_sync_data ($connector_id, $secret_key) {

		$items_processing = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".SLYR_WC_syncdata_table);

		if (isset($items_processing['sl_cuenta_registros']) && $items_processing['sl_cuenta_registros'] > 0){
	    
	        sl_debbug("There are still ".$items_processing['sl_cuenta_registros']." items processing, wait until is finished and synchronize again.");
	        return '<div class="dialog dialog-warning">There are still '.$items_processing['sl_cuenta_registros'].' items processing, wait until is finished and synchronize again.</div>';

	    }

		$time_ini_all_process = microtime(1);

		sl_debbug("==== Store Sync Data INIT ====");

		$sync_params = $arrayReturn = array();
	
		$connector = Connector::get_instance();

		$this->cat_class = new Category();
		// $prod_class = new Product();
		$this->prod_class = Product::get_instance();
		$this->form_class = new Format();

		$last_update = $connector->get_info($connector_id, 'last_update');

		$conn_data = array();
		$conn_data['default_cat_id'] = $this->cat_class->get_default_cat_id();
		
		$slconn = new SalesLayer_Conn ($connector_id, $secret_key);
		$slconn->set_group_multicategory(true);

		if (is_null($last_update)){
			$slconn->get_info();
		}else{
			$slconn->get_info($last_update);
		}
		
		$get_response_table_data  = $slconn->get_response_table_data();

		if ($slconn->get_response_languages_used()){

		    $get_response_default_language = $slconn->get_response_default_language();
		    $get_response_languages_used   = $slconn->get_response_languages_used();
		    $get_response_languages_used   = implode(',', $get_response_languages_used);

		    $conn_data['default_language'] = $get_response_default_language;
		    $conn_data['languages'] = $get_response_languages_used;

		}
		
		$conn_data['comp_id'] = $slconn->get_response_company_ID();
		
		$conn_data['updater_version'] = $slconn->get_response_api_version();
        $conn_data['last_sync'] = date('Y-m-d H:i:s', strtotime('now'));
		$last_update = $slconn->get_response_time();

		if (!is_null($last_update)){ $conn_data['last_update'] = $last_update; }
        if (!is_null($auto_sync) && $auto_sync != ''){ $conn_data['auto_sync'] = $auto_sync; }
		$connector->update_connector($connector_id, $conn_data);

		$sync_params['conn_params']['comp_id'] = $conn_data['comp_id'];
		$sync_params['conn_params']['connector_id'] = $connector_id;
		
		set_time_limit('0');
		
		$get_data_schema = $this->get_data_schema($slconn);

		if ($get_data_schema) {

		    $sl_data_schema = json_encode($get_data_schema);

		    $this->cat_class->set_data_schema($sl_data_schema);
		    $this->prod_class->set_data_schema($sl_data_schema);
		    $this->form_class->set_data_schema($sl_data_schema);

		}
		
		$synchronization_messages = array();
		$div_messages = '';

	    if ($get_response_table_data) {

	        $time_ini_all_store_process = microtime(1);
	        $synchronization_messages['success'][] = "Connector ID: ".$connector_id." - Synchronization executed successfully!";

	        foreach ($get_response_table_data as $nombre_tabla => $data_tabla) {

	            if (count($data_tabla['deleted']) > 0) {

	                $deleted_data = $data_tabla['deleted'];

	                if (count($deleted_data) > 0) {

	                    $sync_type = 'delete';
	                    $time_ini_store_items_delete = microtime(1);

	                    switch ($nombre_tabla) {
	                        case 'catalogue':
	                            
	                            $item_type = 'category';

	                            $arrayReturn['categories_to_delete'] = count($deleted_data);
	                            sl_debbug('Total count of delete categories to store: '.count($deleted_data));
	                            if (SLYR_WC_DEBBUG > 1)  sl_debbug('Delete categories data to store: '.print_r($deleted_data,1));

	                            foreach ($deleted_data as $delete_category_id) {
	                                
	                                $item_data['sl_id'] = $delete_category_id;
	                                $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
	                                $this->insert_syncdata_sql();

	                            }

	                            break;
	                        case 'products':

	                            $item_type = 'product';

	                            $arrayReturn['products_to_delete'] = count($deleted_data);
	                            sl_debbug('Total count of delete products to store: '.count($deleted_data));
	                            if (SLYR_WC_DEBBUG > 1)  sl_debbug('Delete products data to store: '.print_r($deleted_data,1));

	                            foreach ($deleted_data as $delete_product_id) {
	                                
	                                $item_data['sl_id'] = $delete_product_id;
	                                $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
	                                $this->insert_syncdata_sql();

	                            }

	                            break;
	                        case 'product_formats':

	                            $item_type = 'product_format';
	                            
	                            $arrayReturn['product_formats_to_delete'] = count($deleted_data);
	                            sl_debbug('Total count of delete product formats to store: '.count($deleted_data));
	                            if (SLYR_WC_DEBBUG > 1)  sl_debbug('Delete product formats data to store: '.print_r($deleted_data,1));

	                            foreach ($deleted_data as $delete_product_format_id) {
	                                 
	                                $item_data['sl_id'] = $delete_product_format_id;
	                                $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes(json_encode($item_data))."', '".addslashes(json_encode($sync_params))."')";
	                                $this->insert_syncdata_sql();

	                            }

	                            break;
	                        default:

	                            sl_debbug('## Error. Deleting, table '.$nombre_tabla.' not recognized.');

	                            break;
	                    }

	                    sl_debbug('#### time_store_items_delete - '.$item_type.': '.(microtime(1) - $time_ini_store_items_delete).' seconds.');

	                }

	                $this->insert_syncdata_sql(true);

	            }

	            $modified_data = $data_tabla['modified'];
	            $sync_type = 'update';
	            $time_ini_store_items_update = microtime(1);

	            switch ($nombre_tabla) {
	                case 'catalogue':

	                	$item_type = 'category';
	                    
	                    $arrayReturn['categories_to_sync'] = count($modified_data);
	                    sl_debbug('Total count of sync categories to store: '.count($modified_data));
	                    if (SLYR_WC_DEBBUG > 1) sl_debbug('Sync categories data to store: '.print_r($modified_data,1));
	                    

	                    $category_data_to_store = $this->cat_class->prepare_category_data_to_store($modified_data);
	                    if (isset($category_data_to_store['category_data']) && !empty($category_data_to_store['category_data'])){

	                        $categories_to_sync = $category_data_to_store['category_data'];
	                        unset($category_data_to_store['category_data']);

	                        $category_params = array_merge($category_data_to_store, $sync_params);

	                        foreach ($categories_to_sync as $category_to_sync) {
	                            
	                            $item_data_to_insert = html_entity_decode(json_encode($category_to_sync));
	                            $sync_params_to_insert = json_encode($category_params);

	                            $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
	                            $this->insert_syncdata_sql();

	                        }
	                        
	                    }

	                    break;
	                case 'products':

	                	$item_type = 'product';
	                    
	                    $arrayReturn['products_to_sync'] = count($modified_data);
	                    sl_debbug('Total count of sync products to store: '.count($modified_data));
	                    if (SLYR_WC_DEBBUG > 1) sl_debbug('Sync products data to store: '.print_r($modified_data,1));

	                    $product_data_to_store = $this->prod_class->prepare_product_data_to_store($modified_data);
	                    if (isset($product_data_to_store['product_data']) && !empty($product_data_to_store['product_data'])){

	                        $products_to_sync = $product_data_to_store['product_data'];
	                        unset($product_data_to_store['product_data']);
	                        $product_params = array_merge($product_data_to_store, $sync_params);

	                        foreach ($products_to_sync as $product_to_sync) {

	                            $item_data_to_insert = html_entity_decode(json_encode($product_to_sync));
	                            $sync_params_to_insert = json_encode($product_params);

	                            $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
	                            $this->insert_syncdata_sql();
	                            
	                        }
	                        
	                    }

	                    break;
	                case 'product_formats':
	                    
	                	$item_type = 'product_format';

	                    $arrayReturn['product_formats_to_sync'] = count($modified_data);
	                    sl_debbug('Total count of sync product formats to store: '.count($modified_data));
	                    if (SLYR_WC_DEBBUG > 1) sl_debbug('Product formats data: '.print_r($modified_data,1));
	                    
	                    $product_format_data_to_store = $this->form_class->prepare_product_format_data_to_store($modified_data);

	                    if (isset($product_format_data_to_store['not_synced_formats']) && !empty($product_format_data_to_store['not_synced_formats'])){
	                    	$arrayReturn['product_formats_not_synced'] = $product_format_data_to_store['not_synced_formats'];
	                    	unset($product_format_data_to_store['not_synced_formats']);
	                    }

	                    if (isset($product_format_data_to_store['product_format_data']) && !empty($product_format_data_to_store['product_format_data'])){

	                        $product_formats_to_sync = $product_format_data_to_store['product_format_data'];
	                        unset($product_format_data_to_store['product_format_data']);
	                    	$arrayReturn['product_formats_to_sync'] = count($product_formats_to_sync);

	                        $product_format_params = array_merge($product_format_data_to_store, $sync_params);

	                        foreach ($product_formats_to_sync as $product_format_to_sync) {
	                            
	                            $item_data_to_insert = html_entity_decode(json_encode($product_format_to_sync));
	                            $sync_params_to_insert = json_encode($product_format_params);
	                            
	                            $this->sql_to_insert[] = "('".$sync_type."', '".$item_type."', '".addslashes($item_data_to_insert)."', '".addslashes($sync_params_to_insert)."')";
	                            $this->insert_syncdata_sql();

	                        }
	                        
	                    }else{

	                    	$arrayReturn['product_formats_to_sync'] = 0;

	                	}

	                    break;
	                default:

	                    $item_type = '';
	                    sl_debbug('## Error. Synchronizing, table '.$nombre_tabla.' not recognized.');

	                    break;
	            }

	            
	            $this->insert_syncdata_sql(true);

	            sl_debbug('#### time_store_items_update - '.$item_type.': '.(microtime(1) - $time_ini_store_items_update).' seconds.');

	        }

	        $error_data = '';

	        $table_indexes = array('categories', 'products', 'product_formats');
	        $sync_indexes = array('_to_delete', '_to_sync', '_not_synced');

	        $counters_info = array();
	        
	        foreach ($sync_indexes as $sync_index) {
	        	
	        	$sync_index_name = str_replace('_', ' ', $sync_index);

		        foreach($table_indexes as $table_index){
		            
		            $table_index_name = str_replace('_', ' ', $table_index);

		        	if ($sync_index != '_not_synced'){

		        		if ($arrayReturn[$table_index.$sync_index] != 0){

		        			$new_table_index = $table_index;
		        			if ($table_index == 'categories'){ $new_table_index = 'catalogue'; }
		        			$counters_info[$new_table_index][trim(str_replace('_to_', '', $sync_index))]['total'] = $arrayReturn[$table_index.$sync_index];
		        		
		        		}

			        	if (isset($arrayReturn[$table_index.$sync_index])){
			        	    
			        	    $synchronization_messages['success'][] = 'Total '.$table_index_name.' stored '.$sync_index_name.': '.$arrayReturn[$table_index.$sync_index];
			        	    
			        	}

			        }else{

    		            if (isset($arrayReturn[$table_index.$sync_index]) && !empty($arrayReturn[$table_index.$sync_index])){

    		            	$synchronization_messages['warning'][] = 'Total '.$table_index_name.' not stored to synchronize by errors: '.count($arrayReturn[$table_index.$sync_index]);
    		                
    	        	    	foreach ($arrayReturn[$table_index.$sync_index] as $not_synced_message) {
    	            	        
    	            	        if ($error_data == ''){ 
    	            	        
    	            	            $error_data = $not_synced_message."\n";
    	            	        
    	            	        }else{
    	            	        
    	            	            $error_data .= $not_synced_message."\n";
    	            	        
    	            	        }

    	        	    	}    		                
    		                
    		            }

			        }
		            
		        }

	        }

	        if (!empty($counters_info)){

	        	$this->sql_to_insert[] = "('info', 'counters', '".addslashes(json_encode($counters_info))."', '".addslashes(json_encode($sync_params))."')";
		        $this->insert_syncdata_sql(true);

	        }

	        if ($error_data != ''){

		    	$error_data = 'Synchronization date: '.date('Y-m-d H:i:s', strtotime('now'))."\n".$error_data;
		        $error_file = SLYR_WC__LOGS_DIR.'/_error_debbug_log_saleslayer_'.date('Y-m-d').'.dat';
		        
    	    	$new_file = false;
    	    	if (!file_exists($error_file)){ $new_file = true; }

		        file_put_contents($error_file, $error_data, FILE_APPEND);
		        if ($new_file){ chmod($error_file, 0777); }

		        $synchronization_messages['warning'][] = 'Errors can be found in '.$error_file;
		       
		    }

	    }else{	    	

	    	$synchronization_messages['warning'][] = "Connector ID: ".$connector_id." - No information to synchronize!";

	    }
	    
	    if (!empty($synchronization_messages)){

	    	foreach ($synchronization_messages as $wp_message_type => $wp_messages) {
	    	    
    	        if (!empty($wp_messages)){

    	            $type_messages = '';

    	            foreach ($wp_messages as $wp_message) {

    	                if ($wp_message != ''){ 
    	        
    	                    if ($type_messages == ''){

    	                        $type_messages = $wp_message."<br>";
    	                        
    	                    }else{

    	                        $type_messages .= $wp_message."<br>";
    	                    
    	                    }

    	                }

    	            }

    	            if ($type_messages != ''){

    	                if ($div_messages == ''){

    	                    $div_messages = '<div class="dialog dialog-'.$wp_message_type.'">'.$type_messages.'</div>';
    	                    
    	                }else{

    	                    $div_messages .= '<div class="dialog dialog-'.$wp_message_type.'">'.$type_messages.'</div>';
    	                
    	                }

    	            }

    	        }

	    	}

	    }

	    sl_debbug('##### time_all_store_process: '.(microtime(1) - $time_ini_all_store_process).' seconds.');

	    sl_debbug("==== Store Sync Data END ====");

	    return $div_messages;

	}

	/**
	 * Function to insert sync data into the database.
	 * @param  boolean $force_insert             forces sql to be inserted
	 * @return void
	 */
	private function insert_syncdata_sql($force_insert = false){

	    if (!empty($this->sql_to_insert) && (count($this->sql_to_insert) >= $this->sql_to_insert_limit || $force_insert)){

	        $sql_to_insert = implode(',', $this->sql_to_insert);
	        
	        try{

	            $sql_query_to_insert = " INSERT INTO ".SLYR_WC_syncdata_table.
	                                             " ( sync_type, item_type, item_data, sync_params ) VALUES ".
	                                             $sql_to_insert;

				sl_connection_query('insert', $sql_query_to_insert);	                                             
	            
	        }catch(\Exception $e){

	            sl_debbug('## Error. Insert syncdata SQL query: '.$sql_query_to_insert);
	            sl_debbug('## Error. Insert syncdata SQL message: '.$e->getMessage());

	        }

	        $this->sql_to_insert = array();
	        
	    }

	}

	/**
	 * Function to search the pid and return if it's still running or not
	 * @param  integer  $pid  pid to search
	 * @return boolean        status of pid running
	 */
	public function has_pid_alive ($pid) {

	    if ($pid) {

	        if (strtolower(substr(PHP_OS, 0, 3)) == 'win') {

	            $wmi = new \COM('winmgmts://');
	            $prc = $wmi->ExecQuery("SELECT ProcessId FROM Win32_Process WHERE ProcessId='$pid'");

	            if (count($prc) > 0) { $i = 0; foreach ($prc as $a) { ++$i; }}

	            if (SLYR_WC_DEBBUG > 2){ sl_debbug("Searching active process pid '$pid' by Windows. Is active? ".($i > 0 ? 'Yes' : 'No')); }

	            return ($i > 0 ? true : false);

	        } else if (function_exists('posix_getpgid')) {

	            if (SLYR_WC_DEBBUG > 2) { sl_debbug("Searching active process pid '$pid' by posix_getpgid. Is active? ".(posix_getpgid($pid) ? 'Yes' : 'No')); }

	            return (posix_getpgid($pid) ? true : false);

	        } else {

	            if (SLYR_WC_DEBBUG > 2) { sl_debbug("Searching active process pid '$pid' by ps -p. Is active? ".(shell_exec("ps -p $pid | wc -l") > 1 ? 'Yes' : 'No')); }

	            if (shell_exec("ps -p $pid | wc -l") > 1) { return true; }

	        }
	    }

	    return false;
	    
	}

}