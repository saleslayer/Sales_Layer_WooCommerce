<?php

require_once(ABSPATH . 'wp-load.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');
include_once(SLYR_WC__PLUGIN_DIR.'admin/general_functions.php');

class Media_class{

	private static $instance;

	protected	$db;
	protected	$stored_url_files_sizes             = array();
	protected	$sl_time_ini_media_meta_process;
	protected	$max_media_meta_execution_time		= 240;
	protected	$end_media_meta_process;

	function __construct () {

		global $wpdb;
		$this->db = $wpdb;

	}

	public static function &get_instance () {

		if ( is_null( self::$instance ) ) {
			
			self::$instance = new Media_class();
		
		}
		
		return self::$instance;
	
	}

	/**
	 * Function get filename from url.
	 * @param string $image_url 	url
	 * @return string || boolean 	file name from url
	 */
	public function get_image_url_filename($image_url){

		// $time_ini_get_image_url_filename = microtime(1);

		$image_url_info = pathinfo($image_url);
		$filename = rawurldecode($image_url_info['basename']);
		$filename = str_replace(["#", "%"], ["_U23_", "_U25_"], $filename);

		// sl_debbug('# time_get_image_url_filename: '.(microtime(1) - $time_ini_get_image_url_filename).' seconds.', 'timer');

		return $filename;

	}

	/**
	 * Function to get encoded basename url
	 * @param  string $url 		url to encode
	 * @return string      		url with encoded basename
	 */
	protected function get_encoded_url($url){

		// $time_ini_get_encoded_url = microtime(1);

		$image_url_info = pathinfo($url);
		$filename_encoded = $image_url_info['basename'];            
		$filename_encoded = rawurlencode(rawurldecode($filename_encoded));
		
		// sl_debbug('# time_get_encoded_url: '.(microtime(1) - $time_ini_get_encoded_url).' seconds.', 'timer');

		return $image_url_info['dirname'].'/'.$filename_encoded;

	}

	/**
	 * Function to fetch media.
	 * @param string $file_url		url from the file to fetch
	 * @param string $post_id 		post id to assign the file
	 * @return boolean 				result of the fetch
	 */
	public function fetch_media($file_url, $post_id, $process_meta = false) {

		$time_ini_fetch_media = microtime(1);
		
		// $time_ini_new_filename = microtime(1);

		$new_filename = $this->get_image_url_filename($file_url);

		if (!$new_filename || !$post_id){
			
			return false;
		
		}

		// sl_debbug('# fetch_media() - time_new_filename: '.(microtime(1) - $time_ini_new_filename).' seconds.', 'timer');

		sl_debbug(" > Importing media: ".$file_url.($process_meta ? ' - Processing Meta' : ''));

		//directory to import to	
		$artDir = 'wp-content/uploads/importedmedia/';
		
		// $time_ini_mkdir = microtime(1);
		//if the directory doesn't exist, create it	
		if(!file_exists(ABSPATH.$artDir)) {
			mkdir(ABSPATH.$artDir);
		}
		// sl_debbug('# fetch_media() - time_mkdir: '.(microtime(1) - $time_ini_mkdir).' seconds.', 'timer');

		$file_url_encoded = $this->get_encoded_url($file_url);

		// $time_ini_copy = microtime(1);
		copy($file_url_encoded, ABSPATH.$artDir.$new_filename);
		// sl_debbug('# fetch_media() - time_copy: '.(microtime(1) - $time_ini_copy).' seconds.', 'timer');

		// $time_ini_prepare_data = microtime(1);
		$siteurl = get_option('siteurl');
		$file_info = getimagesize(ABSPATH.$artDir.$new_filename);

		//create an array of attachment data to insert into wp_posts table
		$artdata = array(
			'post_author' => 1, 
			'post_date' => current_time('mysql'),
			'post_date_gmt' => current_time('mysql'),
			'post_title' => $new_filename, 
			'post_status' => 'inherit',
			'comment_status' => 'closed',
			'ping_status' => 'closed',
			'post_name' => sanitize_title_with_dashes(str_replace("_", "-", $new_filename)),
			'post_modified' => current_time('mysql'),
			'post_modified_gmt' => current_time('mysql'),
			'post_parent' => $post_id,
			'post_type' => 'attachment',
			'guid' => $siteurl.'/'.$artDir.$new_filename,
			'post_mime_type' => $file_info['mime'],
			'post_excerpt' => '',
			'post_content' => ''
		);

		$uploads = wp_upload_dir();
		$save_path = $uploads['basedir'].'/importedmedia/'.$new_filename;
		// sl_debbug('# fetch_media() - time_prepare_data: '.(microtime(1) - $time_ini_prepare_data).' seconds.', 'timer');

		// $time_ini_insert_attachment = microtime(1);
		//insert the database record
		$attach_id = wp_insert_attachment( $artdata, $save_path, $post_id );
		// sl_debbug('# fetch_media() - time_insert_attachment: '.(microtime(1) - $time_ini_insert_attachment).' seconds.', 'timer');

		if ( !is_wp_error($attach_id) ) {

			if (!$process_meta){
				
				$meta_required_data = array('save_path' => rawurlencode($save_path), 'n_try' => 0);
				$result_add = add_post_meta( $attach_id, '_meta_required', json_encode($meta_required_data));
				
				if (!$result_add){

					sl_debbug('## Error. Adding media post meta: '.$save_path);
					return false;

				}

			}else{

				// $time_ini_generate_attachment_metadata = microtime(1);

				if ($attach_data = wp_generate_attachment_metadata( $attach_id, $save_path)) {

					// sl_debbug('# fetch_media() - time_generate_attachment_metadata: '.(microtime(1) - $time_ini_generate_attachment_metadata).' seconds.', 'timer');
					// $time_ini_update_attachment_metadata = microtime(1);
					wp_update_attachment_metadata($attach_id, $attach_data);
					// sl_debbug('# fetch_media() - time_update_attachment_metadata: '.(microtime(1) - $time_ini_update_attachment_metadata).' seconds.', 'timer');
				
				}else{

		    		// sl_debbug('# update_media() - time_generate_attachment_metadata: '.(microtime(1) - $time_ini_generate_attachment_metadata).' seconds.', 'timer');
		    		sl_debbug('## Error. Generating attachment metadata for file: '.$save_path);

		    	}
		    	
			}

		}else{

			sl_debbug('## Error. Inserting new media attachment: '.$save_path);
			return false;

		}

		sl_debbug('# time_fetch_media: '.(microtime(1) - $time_ini_fetch_media).' seconds.', 'timer');
		return $attach_id;

	}

	/**
	 * Function to update media item
	 * @param  string $file_url      		media url to update
	 * @param  int $attachment_id 			id media item to update
	 * @param  boolean $process_meta  		process media or store for media process cron
	 * @return boolean						result of update
	 */
	public function update_media($file_url, $attachment_id, $process_meta = false){

		$time_ini_update_media = microtime(1);
		
		$new_filename = $this->get_image_url_filename($file_url);

		if (!$new_filename || !$attachment_id){
		    
		    return false;
		
		}

		sl_debbug(" > Updating media: ".$file_url.($process_meta ? ' - Processing Meta' : ''));

		$uploads_path = 'wp-content/uploads/';
		$importedmedia_path = $uploads_path.'importedmedia/';

		// $time_ini_check_existing_image = microtime(1);

		$existing_attachment_metadata = wp_get_attachment_metadata($attachment_id);
		
		if (!empty($existing_attachment_metadata)){

			$main_file_to_delete_path = ABSPATH.$uploads_path.$existing_attachment_metadata['file'];

			try{
		        
		        @unlink($main_file_to_delete_path);
		        
			}catch(\Exception $e){
		    
		    	sl_debbug('## Error. Deleting main media '.$main_file_to_delete_path.': '.$e->getMessage());
		    
		    }

		    if (isset($existing_attachment_metadata['sizes']) && !empty($existing_attachment_metadata['sizes'])){
		   
		        foreach ($existing_attachment_metadata['sizes'] as $keySize => $meta_size){
		        	
		        	$file_to_delete_path = ABSPATH.$importedmedia_path.$meta_size['file'];

		        	try{
		                
		                @unlink($file_to_delete_path);

		        	}catch(\Exception $e){
		            
		            	sl_debbug('## Error. Deleting media '.$file_to_delete_path.': '.$e->getMessage());
		            
		            }

		        }
		        
		    }

		}
		// sl_debbug('# update_media() - time_check_existing_image: '.(microtime(1) - $time_ini_check_existing_image).' seconds.', 'timer');

		try{

			// $time_ini_mkdir = microtime(1);
		    //if the directory doesn't exist, create it 
		    if (!file_exists(ABSPATH.$importedmedia_path)) {

		        mkdir(ABSPATH.$importedmedia_path);

		    }
		    // sl_debbug('# update_media() - time_mkdir: '.(microtime(1) - $time_ini_mkdir).' seconds.', 'timer');

		    $file_url_encoded = $this->get_encoded_url($file_url);
		    
		    // $time_ini_copy_image = microtime(1);
		    copy($file_url_encoded, ABSPATH.$importedmedia_path.$new_filename);
		    // sl_debbug('# update_media() - time_copy_image: '.(microtime(1) - $time_ini_copy_image).' seconds.', 'timer');
		    
		    // $time_ini_update_post = microtime(1);

		    $siteurl = get_option('siteurl');
		    $file_info = getimagesize(ABSPATH.$importedmedia_path.$new_filename);

			$post_data = array(
			 	'ID' => $attachment_id,
			 	'post_title' => $new_filename,
			 	'post_name' => sanitize_title_with_dashes(str_replace("_", "-", $new_filename)),
			 	'guid' => $siteurl.'/'.$importedmedia_path.$new_filename,
			 	'post_mime_type' => $file_info['mime']
			);

			wp_update_post($post_data);
		    // sl_debbug('# update_media() - time_update_post: '.(microtime(1) - $time_ini_update_post).' seconds.', 'timer');

		    $save_path = ABSPATH.$importedmedia_path.$new_filename;

		    if (!$process_meta){

		    	$meta_required_data = array('save_path' => rawurlencode($save_path), 'n_try' => 0);
		    	$result_add = add_post_meta( $attachment_id, '_meta_required', json_encode($meta_required_data));
		    	
		    	if (!$result_add){

		    		sl_debbug('## Error. Adding media post meta: '.$save_path);
		    		return false;

		    	}

		    }else{

		    	// $time_ini_generate_attachment_metadata = microtime(1);
		    		
		    	if ($attach_data = wp_generate_attachment_metadata( $attachment_id, $save_path)) {
		    	    
		    	    // sl_debbug('# update_media() - time_generate_attachment_metadata: '.(microtime(1) - $time_ini_generate_attachment_metadata).' seconds.', 'timer');

		    	    // $time_ini_update_attachment_metadata = microtime(1);

		    	    wp_update_attachment_metadata($attachment_id, $attach_data);
		    	
		    		// sl_debbug('# update_media() - time_update_attachment_metadata: '.(microtime(1) - $time_ini_update_attachment_metadata).' seconds.', 'timer');

		    	}else{

		    		// sl_debbug('# update_media() - time_generate_attachment_metadata: '.(microtime(1) - $time_ini_generate_attachment_metadata).' seconds.', 'timer');
		    		sl_debbug('## Error. Generating attachment metadata for file: '.$save_path);

		    	}

		    }

		    // $time_ini_update_attached_file = microtime(1);
			update_attached_file($attachment_id, $save_path);
		    // sl_debbug('# update_media() - time_update_attached_file: '.(microtime(1) - $time_ini_update_attached_file).' seconds.', 'timer');

		}catch(\Exception $e){

		    sl_debbug('## Error. Updating media: '.$e->getMessage());
		    return false;

		}

		sl_debbug('# time_update_media: '.(microtime(1) - $time_ini_update_media).' seconds.', 'timer');
		return $attachment_id;

	}

	/**
	 * Function to delete media item
	 * @param  int $attachment_id 		id media item to delete
	 * @return boolean                	result of delete
	 */
	public function delete_media($attachment_id){
		
		$time_ini_delete_media = microtime(1);

		sl_debbug(" > Deleting media from attachment_id: $attachment_id");
		
		$uploads_path = 'wp-content/uploads/';
		$importedmedia_path = $uploads_path.'importedmedia/';

		try{

			$termmeta_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".$this->db->prefix."termmeta WHERE meta_key = 'thumbnail_id' AND meta_value = ".$attachment_id);
		
		}catch(\Exception $e){

			sl_debbug('## Error. Reading termmeta_count: '.$e->getMessage());

		}

		if (isset($termmeta_count['sl_cuenta_registros']) && $termmeta_count['sl_cuenta_registros'] > 0){
		
		    return false;
		
		}

		try{

			$postmeta_thumbnail_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".$this->db->prefix."postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = ".$attachment_id);
		
		}catch(\Exception $e){

			sl_debbug('## Error. Reading postmeta_thumbnail_count: '.$e->getMessage());

		}

		if (isset($postmeta_thumbnail_count['sl_cuenta_registros']) && $postmeta_thumbnail_count['sl_cuenta_registros'] > 0){
		
		    return false;
		
		}

		try{

			$postmeta_image_galleries = sl_connection_query('read', " SELECT * FROM ".$this->db->prefix."postmeta WHERE meta_key = '_product_image_gallery' AND meta_value like '%".$attachment_id."%'");

		}catch(\Exception $e){

			sl_debbug('## Error. Reading postmeta_image_galleries: '.$e->getMessage());

		}

		if (!empty($postmeta_image_galleries)){

		    foreach ($postmeta_image_galleries as $posmeta_id => $postmeta_image_gallery){

		        if (strpos($postmeta_image_gallery['meta_value'], ',')){

		            $postmeta_image_gallery = explode(',', $postmeta_image_gallery['meta_value']);

		            foreach ($postmeta_image_gallery as $image_id) {

		               if ($image_id == $attachment_id){

		                  return false;

		              }

		            }

		        }else{

		            if ($postmeta_image_gallery['meta_value'] == $attachment_id){

		                return false;

		            }

		        }

		    }

		}

		try{

			wp_delete_attachment( $attachment_id );

		}catch(\Exception $e){

			sl_debbug('## Error. Deleting attachment: '.$e->getMessage());

		}

		$existing_attachment_metadata = wp_get_attachment_metadata($attachment_id);
		
		if (!empty($existing_attachment_metadata)){

			$main_file_to_delete_path = ABSPATH.$uploads_path.$existing_attachment_metadata['file'];

			try{
		        
		        @unlink($main_file_to_delete_path);
		        
			}catch(\Exception $e){
		    
		    	sl_debbug('## Error. Deleting main media '.$main_file_to_delete_path.': '.$e->getMessage());
		    
		    }

		    if (isset($existing_attachment_metadata['sizes']) && !empty($existing_attachment_metadata['sizes'])){
		   
		        foreach ($existing_attachment_metadata['sizes'] as $keySize => $meta_size){
		        	
		        	$file_to_delete_path = ABSPATH.$importedmedia_path.$meta_size['file'];

		        	try{
		                
		                @unlink($file_to_delete_path);

		        	}catch(\Exception $e){
		            
		            	sl_debbug('## Error. Deleting media '.$file_to_delete_path.': '.$e->getMessage());
		            
		            }

		        }
		        
		    }

		}

		sl_debbug('# time_delete_media: '.(microtime(1) - $time_ini_delete_media).' seconds.', 'timer');
		return true;

	}

	/**
	 * Function to get thumbnail id by title.
	 * @param string $thumbnail_title 	thumbnail title
	 * @return integer 					thumbnail id found
	 */
	public function get_thumbnail_id_by_title($thumbnail_title){
		
		// $time_ini_get_thumbnail_id_by_title = microtime(1);

		$query_thumbnail_args = array(
		    'post_type'      => 'attachment',
		    'post_mime_type' => 'image',
		    'post_status'    => 'inherit',
		    'posts_per_page' => - 1,
		    'title' 		 => $thumbnail_title,
		);
		
		$query_thumbnail = new WP_Query( $query_thumbnail_args );
		
		if (isset($query_thumbnail->posts[0])){
			
			return $query_thumbnail->posts[0]->ID;

		}

		// sl_debbug('# time_get_thumbnail_id_by_title: '.(microtime(1) - $time_ini_get_thumbnail_id_by_title).' seconds.', 'timer');
		return 0;

	}

	/**
	 * Function to read image file size.
	 * @param string $url         	image url to read file size
	 * @return int         			file size of image url or false
	 */
	public function read_image_file_size($url){
		
        if (strpos($url, 'http') !== false){
			
		    try{

		        // $time_ini_check_url_size = microtime(1);
		        
		        if (isset($this->stored_url_files_sizes[$url])){

		            // sl_debbug('# time_check_size_url stored: '.(microtime(1) - $time_ini_check_url_size).' seconds.', 'timer');
		            return $this->stored_url_files_sizes[$url];

		        }else{
		            
		            $image_url_info = pathinfo($url);
		            $filename_encoded = $image_url_info['basename'];	            
		            $filename_encoded = rawurlencode(rawurldecode($filename_encoded));
		            $image_url_encoded = $image_url_info['dirname'].'/'.$filename_encoded;

		            $headers = get_headers($image_url_encoded, TRUE);
		            // sl_debbug('# time_check_size_url: '.(microtime(1) - $time_ini_check_url_size).' seconds.', 'timer');

		        }

		    }catch(\Exception $e){

		        sl_debbug("## Error. Remote image with URL ".$url." couldn't been synchronized: ".$e->getMessage());
		        return false;

		    }

		    if (isset($headers['Content-Length'])){
		       
		       $this->stored_url_files_sizes[$url] = $headers['Content-Length'];
		       return $headers['Content-Length'];
		    
		    }else if (isset($headers['content-length'])){
		    
		        $this->stored_url_files_sizes[$url] = $headers['content-length'];
		        return $headers['content-length'];
		    
		    }
		
		    return false;

		}else{
			
		    try{

		        // $time_ini_check_local_size = microtime(1);
		        $url_filesize = filesize($url);
		        clearstatcache();
		        // sl_debbug('# time_check_size_local: '.(microtime(1) - $time_ini_check_local_size).' seconds.', 'timer');
		        return $url_filesize; 

		    }catch(\Exception $e){

		        sl_debbug("## Notice. Could not read local image with URL ".$url." : ".$e->getMessage());

		    }
		    
		    return false;

		}
		
	}

	/**
	 * Function to process pending attachments meta data.
	 * @return void
	 */
	public function process_pending_meta(){
		
		sl_debbug("==== Media meta process INIT ".date('Y-m-d H:i:s')." ====", 'mediameta');

		$this->sl_time_ini_media_meta_process = microtime(1);
        $this->end_media_meta_process = false;
        $return_message = '';
		
	    $this->check_pending_meta();

		try{

			$sql_meta_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".$this->db->prefix."postmeta WHERE meta_key = '_meta_required' AND meta_value NOT LIKE '%start_meta_process%' LIMIT 1");
		
		}catch(\Exception $e){

			sl_debbug('## Error. Reading sql_meta_count: '.$e->getMessage(), 'mediameta');

		}

		if (isset($sql_meta_count['sl_cuenta_registros']) && $sql_meta_count['sl_cuenta_registros'] > 0){
		
			sl_debbug('Pending media meta items to process: '.$sql_meta_count['sl_cuenta_registros'], 'mediameta');

			$sql_meta_required = " SELECT * FROM ".$this->db->prefix."postmeta WHERE meta_key = '_meta_required' AND meta_value NOT LIKE '%start_meta_process%' LIMIT 1";

	        do{

	        	$this->check_media_meta_process_time();

	        	if ($this->end_media_meta_process){
	        		
	        	    sl_debbug('Breaking media meta process due to time limit.', 'mediameta');
	        	    $return_message = 'Breaking media meta process due to time limit.';
	        	    break;

	        	}else{

		        	$meta_required_to_process = sl_connection_query('read', $sql_meta_required);

		            if ($meta_required_to_process && isset($meta_required_to_process[0])){

		            	$meta_id = $meta_required_to_process[0]['meta_id'];
		                $attach_id = $meta_required_to_process[0]['post_id'];
		            	$meta_data_required_to_process = json_decode(stripslashes($meta_required_to_process[0]['meta_value']),1);
		            	$save_path = rawurldecode($meta_data_required_to_process['save_path']);
		            	$n_try = $meta_data_required_to_process['n_try'];

		            	sl_debbug('File to process media meta: '.$save_path.' - Try: '.$n_try, 'mediameta');

		            	$meta_required_data = array('save_path' => rawurlencode($save_path), 'n_try' => $n_try, 'start_meta_process' => strtotime('now'));
			            if (!$result_update = update_metadata_by_mid( 'post', $meta_id, json_encode($meta_required_data))){

							sl_debbug('## Error. Updating start meta process to item with meta_id: '.$meta_id, 'mediameta');
							
							$this->sl_delete_metadata_by_mid($meta_id);

						}else{

							// $time_ini_generate_attachment_metadata = microtime(1);
			            	if ($attach_data = wp_generate_attachment_metadata( $attach_id, $save_path)) {

			            		// sl_debbug('# process_pending_meta() - time_generate_attachment_metadata: '.(microtime(1) - $time_ini_generate_attachment_metadata).' seconds.', 'mediameta');
			            		// $time_ini_update_attachment_metadata = microtime(1);
			            		wp_update_attachment_metadata($attach_id, $attach_data);
			            		// sl_debbug('# process_pending_meta() - time_update_attachment_metadata: '.(microtime(1) - $time_ini_update_attachment_metadata).' seconds.', 'mediameta');
			            		$this->sl_delete_metadata_by_mid($meta_id);

			            	}else{

			            		// sl_debbug('# process_pending_meta() - time_generate_attachment_metadata: '.(microtime(1) - $time_ini_generate_attachment_metadata).' seconds.', 'mediameta');
			            		sl_debbug('## Error. Generating attachment metadata for file: '.$save_path, 'mediameta');

			            		$n_try++;

			            		if ($n_try <= 2){

				            		$meta_required_data = array('save_path' => rawurlencode($save_path), 'n_try' => $n_try);
	            					if (!$result_update = update_metadata_by_mid( 'post', $meta_id, json_encode($meta_required_data))){
			            			
	            						sl_debbug('## Error. Updating process tries to item with meta_id: '.$meta_id, 'mediameta');

	            						$this->sl_delete_metadata_by_mid($meta_id);

	            				    }

			            		}else{

			            			$this->sl_delete_metadata_by_mid($meta_id);

			            		}

			            	}

			            }

		            }

		        }

	        }while(!empty($meta_required_to_process));

        	if ($return_message == '') $return_message = 'Sales Layer media meta process finished correctly.';

		}else{
			
			sl_debbug('There are no pending media meta items to process.', 'mediameta');
			$return_message = 'There are no pending media meta items to process.';
			
		}

		sl_debbug('### time_process_pending_meta: '.(microtime(1) - $this->sl_time_ini_media_meta_process).' seconds.', 'mediameta');

		sl_debbug("==== Media meta process END ====", 'mediameta');
		
		return $return_message;

	}

	/**
	 * Function to delete metadata by mid
	 * @param  int $meta_id 		meta id
	 * @return void
	 */
	private function sl_delete_metadata_by_mid($meta_id){

		if (!$result_delete = delete_metadata_by_mid( 'post', $meta_id )){

    		sl_debbug('## Error. Deleting item with meta_id '.$meta_id, 'mediameta');
			
		}

	}

	/**
	 * Function to check current process time to avoid exceding the limit.
	 * @return void
	 */
	private function check_media_meta_process_time(){

	    $current_process_time = microtime(1) - $this->sl_time_ini_media_meta_process;

	    if ($current_process_time >= $this->max_media_meta_execution_time){

	        $this->end_media_meta_process = true;

	    }

	}

	/**
	 * Function to check if there is pending meta to process
	 * @return void
	 */
	private function check_pending_meta(){

		$sql_pending_meta = " SELECT * FROM ".$this->db->prefix."postmeta WHERE meta_key = '_meta_required' AND meta_value LIKE '%start_meta_process%'";

		$all_pending_meta = sl_connection_query('read', $sql_pending_meta);
	 
	    if (!empty($all_pending_meta)){

	    	$now = strtotime('now');
	
	    	foreach ($all_pending_meta as $pending_meta) {
	    			
				$meta_id = $pending_meta['meta_id'];
			    $attach_id = $pending_meta['post_id'];
				$pending_meta_data = json_decode(stripslashes($pending_meta['meta_value']),1);
				if (isset($pending_meta_data['start_meta_process'])){

					$interval  = abs($now - $pending_meta_data['start_meta_process']);
					$minutes   = round($interval / 60);
					
					if ($minutes < 10){
					
					    sl_debbug('Less than 10 minutes processing item with meta_id '.$meta_id.', we let it finish.', 'mediameta');

					}else{
						    
						sl_debbug('More than 10 minutes processing item with meta_id '.$meta_id.', we set it to run one last time.', 'mediameta');
		            	$meta_required_data = array('save_path' => $pending_meta_data['save_path'], 'n_try' => 3);
						if (!$result_update = update_metadata_by_mid( 'post', $meta_id, json_encode($meta_required_data))){

							sl_debbug('## Error. Updating last try to pending item with meta_id: '.$meta_id, 'mediameta');

					        if (!$result_delete = $this->sl_delete_metadata_by_mid( 'post', $meta_id )){

					    		sl_debbug('## Error. Deleting pending item with meta_id: '.$meta_id, 'mediameta');

					    	}

						}

					}

				}

	    	}

		}

	}

}