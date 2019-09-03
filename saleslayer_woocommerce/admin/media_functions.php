<?php

/**
 * Function get filename from url.
 * @param string $image_url 	url
 * @return string || boolean 	file name from url
 */
function get_image_url_filename($image_url){

	$image_url  = str_replace('https://', 'http://', $image_url);

	if (url_exists($image_url)){

		$image_content_str = @file_get_contents(trim($image_url));

		if ($image_content_str) {

			$image_url_info = pathinfo($image_url);

			$filename = urldecode($image_url_info['basename']);

			return $filename;
			
		}else{

			sl_debbug('## Error. Getting image url file name contents: '.$image_url);

		}
		
	}else{

		sl_debbug('## Error. Checking if url exists: '.$image_url);

	}

	return false;

}

/**
 * Function to fetch media.
 * @param string $file_url		url from the file to fetch
 * @param string $post_id 		post id to assign the file
 * @return boolean 				result of the fetch
 */
function fetch_media($file_url, $post_id) {

	require_once(ABSPATH . 'wp-load.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	
	$new_filename = get_image_url_filename($file_url);

	if (!$new_filename || !$post_id){
		
		return false;
	
	}

	sl_debbug(" > Importing media: $file_url");

	//directory to import to	
	$artDir = 'wp-content/uploads/importedmedia/';

	//if the directory doesn't exist, create it	
	if(!file_exists(ABSPATH.$artDir)) {
		mkdir(ABSPATH.$artDir);
	}

	copy($file_url, ABSPATH.$artDir.$new_filename);

	$siteurl = get_option('siteurl');
	$file_info = getimagesize(ABSPATH.$artDir.$new_filename);

	//create an array of attachment data to insert into wp_posts table
	$artdata = array();
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

	try{

		//insert the database record
		$attach_id = wp_insert_attachment( $artdata, $save_path, $post_id );

		//generate metadata and thumbnails
		if ($attach_data = wp_generate_attachment_metadata( $attach_id, $save_path)) {
			wp_update_attachment_metadata($attach_id, $attach_data);
		}

	}catch(\Exception $e){

		sl_debbug('## Error. Inserting new media '.$save_path.': '.$e->getMessage());
		return false;

	}

	return $attach_id;

}

function update_media($file_url, $attachment_id){

	require_once(ABSPATH . 'wp-load.php');
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	
	$new_filename = get_image_url_filename($file_url);

	if (!$new_filename || !$attachment_id){
	    
	    return false;
	
	}

	sl_debbug(" > Updating media: $file_url");

	$uploads_path = 'wp-content/uploads/';
	$importedmedia_path = $uploads_path.'importedmedia/';	

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

	try{

	    //if the directory doesn't exist, create it 
	    if (!file_exists(ABSPATH.$importedmedia_path)) {

	        mkdir(ABSPATH.$importedmedia_path);

	    }
	    
	    copy($file_url, ABSPATH.$importedmedia_path.$new_filename);

	    $siteurl = get_option('siteurl');
	    $file_info = getimagesize(ABSPATH.$importedmedia_path.$new_filename);

		$post_data = array(
		 	'ID' => $attach_id,
		 	'post_title' => $new_filename,
		 	'post_name' => sanitize_title_with_dashes(str_replace("_", "-", $new_filename)),
		 	'guid' => $siteurl.'/'.$importedmedia_path.$new_filename,
		 	'post_mime_type' => $file_info['mime']
		);

		wp_update_post($post_data);

	    $save_path = ABSPATH.$importedmedia_path.$new_filename;

	    if ($attach_data = wp_generate_attachment_metadata( $attachment_id, $save_path)) {
	        
	        wp_update_attachment_metadata($attachment_id, $attach_data);
	    
	    }
	    
	    wp_generate_attachment_metadata($attachment_id, $save_path );
		update_attached_file($attach_id, $save_path);

	}catch(\Exception $e){

	    sl_debbug('## Error. Updating media: '.$e->getMessage());
	    return false;

	}

	return $attachment_id;

}

function delete_media($attachment_id){

	global $wpdb;
	
	sl_debbug(" > Deleting media from attachment_id: $attachment_id");
	
	$uploads_path = 'wp-content/uploads/';
	$importedmedia_path = $uploads_path.'importedmedia/';

	try{

		$termmeta_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".$wpdb->prefix."termmeta WHERE meta_key = 'thumbnail_id' AND meta_value = ".$attachment_id);
	
	}catch(\Exception $e){

		sl_debbug('## Error. Reading termmeta_count: '.$e->getMessage());

	}

	if (isset($termmeta_count['sl_cuenta_registros']) && $termmeta_count['sl_cuenta_registros'] > 0){
	
	    return false;
	
	}

	try{

		$postmeta_thumbnail_count = sl_connection_query('read', " SELECT count(*) as sl_cuenta_registros FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_thumbnail_id' AND meta_value = ".$attachment_id);
	
	}catch(\Exception $e){

		sl_debbug('## Error. Reading postmeta_thumbnail_count: '.$e->getMessage());

	}

	if (isset($postmeta_thumbnail_count['sl_cuenta_registros']) && $postmeta_thumbnail_count['sl_cuenta_registros'] > 0){
	
	    return false;
	
	}

	try{

		$postmeta_image_galleries = sl_connection_query('read', " SELECT * FROM ".$wpdb->prefix."postmeta WHERE meta_key = '_product_image_gallery' AND meta_value like '%".$attachment_id."%'");

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

	return true;

}

/**
 * Function to get thumbnail id by title.
 * @param string $thumbnail_title 	thumbnail title
 * @return integer 					thumbnail id found
 */
function get_thumbnail_id_by_title($thumbnail_title){

	$query_thumbnail_args = array(
	    'post_type'      => 'attachment',
	    'post_mime_type' => 'image',
	    'post_status'    => 'inherit',
	    'posts_per_page' => - 1,
	    'title' 		 => $thumbnail_title,
	);

	$query_thumbnail = new WP_Query( $query_thumbnail_args );

	if ($query_thumbnail->posts[0]){
		
		return $query_thumbnail->posts[0]->ID;

	}

	return 0;

}

/**
 * Function to check ir the url exists.
 * @param  string $url 		of the image
 * @return boolean            if the url exists
 */
function url_exists ($url) {

	$handle = @fopen($url, 'r');

	if ($handle === false) { return false; }

	fclose($handle);
	return true;
}

/**
 * Function to verify an image url.
 * @param string $url         image url to validate
 * @return $md5_image         md5 of image url or false
 */
function verify_md5_image_url($url){

    $md5_image = false;

    if ($url != ''){

	    try{

	    	$md5_image = md5_file($url);

	    }catch(\Exception $e){

	        sl_debbug("## Error. Couldn't get MD5 from image with URL: ".$url);

	    }

    }else{

    	sl_debbug("## Error. Couldn't get MD5 from image with empty URL: ".$url);

    }

    return $md5_image;

}