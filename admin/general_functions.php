<?php

/**
 * Function to compare two strings.
 * @param  string $mg_name 				Wordpress name
 * @param  string $sl_name  			Sales Layer name
 * @return boolean            			of the comparative
 */
function compareNames($wp_name, $sl_name){
	
	$new_wp_name = strtolower(str_replace('_', ' ', $wp_name)); 
	$new_sl_name = strtolower(str_replace('_', ' ', $sl_name));

	if (levenshtein($new_wp_name, $new_sl_name) == 0){
		return true;
	}else{
		return false;
	}

}

/**
 * Function to organize Sales Layer tables if they're multilingual.
 * @param  array $tables  				tables to organize
 * @param  array $tableStructure 		structure of the tables
 * @return array            			tables organized
 */
function organizeTablesIndex($tables, $tableStructure){
	
	foreach ($tableStructure as $keyStruct => $fieldStruct) {

		if (isset($fieldStruct['multilingual_name'])){
		
			foreach ($tables as $keyTab => $fieldTable) {
		
				if (array_key_exists($fieldStruct['multilingual_name'], $fieldTable['data'])){
		
					$tables[$keyTab]['data'][$keyStruct] = $tables[$keyTab]['data'][$fieldStruct['multilingual_name']];
					unset($tables[$keyTab]['data'][$fieldStruct['multilingual_name']]);
		
				}
		
			}
		
		}
	}

	return $tables;

}

/**
 * Function to sort images by dimension.
 * @param array $img_a 		first image to sort
 * @param array $img_b 		second image to sort
 * @return array 			comparative of the images
 */
function sortByDimension ($img_a, $img_b) {

	$area_a = $img_a['width'] * $img_a['height'];
	$area_b = $img_b['width'] * $img_b['height'];
	
	return strnatcmp($area_b, $area_a);

}

/**
 * Function to order an array of images.
 * @param array $array_img 		images to order
 * @return array 			array of ordered images
 */
function order_array_img ($array_img) {
	
	uasort($array_img, 'sortByDimension');
	
	if (isset($array_img['ORG'])){
		$org_array_img = array();
		$org_array_img['ORG'] = $array_img['ORG'];
		foreach ($array_img as $keySize => $valSize) {
			if ($keySize == 'ORG'){ continue; }
			$org_array_img[$keySize] = $valSize;
		}
		return $org_array_img;
	}
	
	return $array_img;
}

/**
 * Function to modify get_term_by and add term meta.
 * @param string $field 		field
 * @param string $value 		value
 * @param string $taxonomy 		taxonomy
 * @param string $output 		type of output
 * @param string $filter 		filter
 * @return array || boolean 	term found
 */
function sl_get_term_by($field, $value, $taxonomy = '', $output = OBJECT, $filter = 'raw' ){

	if ($term = get_term_by( $field, $value, $taxonomy, $output, $filter)){

		if ($output == OBJECT){

			$term_id = $term->term_id;

		}else{

			$term_id = $term['term_id'];

		}

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
            $term_meta = get_term_meta( $term_id, '', true );
        }else{
            $term_meta = get_woocommerce_term_meta( $term_id, '', true );
        }

        if (!empty($term_meta)){
			foreach ($term_meta as $field_name => $meta) {
				if (is_array($meta) && count($meta) == 1){
					$meta_value = $meta[0];
				}else{
					$meta_value = $meta;
				}

				if ($output == OBJECT){

					$term->$field_name = $meta_value;

				}else{

					$term[$field_name] = $meta_value;
				
				}

			}

		}

		return $term;

	}

	return false;

}

function sl_find_unassigned_product_cat_terms_by_name($value, $output = OBJECT){

	$meta_query = array(array('key' => 'saleslayerid', 'compare' => 'NOT EXISTS'), array('key' => 'saleslayercompid', 'compare' => 'NOT EXISTS'));
	
   	$terms = get_terms(
   		array(
   			'hide_empty' => false,
			'taxonomy' => 'product_cat',
			'meta_query' => $meta_query,
			'name' => $value,
		    'orderby' => 'term_id',
		    'order'		=> 'asc',
		)
   	);

   	if (!is_wp_error($terms) && !empty($terms)){

   		$term = reset($terms);

		if ($output == OBJECT){

			$term_id = $term->term_id;

		}else{

			$term = $term->to_array();
			$term_id = $term['term_id'];

		}

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
            $term_meta = get_term_meta( $term_id, '', true );
        }else{
            $term_meta = get_woocommerce_term_meta( $term_id, '', true );
        }

        if (!empty($term_meta)){

			foreach ($term_meta as $field_name => $meta) {

				if (is_array($meta) && count($meta) == 1){
					$meta_value = $meta[0];
				}else{
					$meta_value = $meta;
				}

				if ($output == OBJECT){

					$term->$field_name = $meta_value;

				}else{

					$term[$field_name] = $meta_value;
				
				}

			}

		}

		return $term;

   	}

	return false;

}

/**
 * Function to find term by Sales Layer identifiers.
 * @param string $taxonomy 			taxonomy
 * @param string $saleslayerid 		Sales Layer id
 * @param string $saleslayercompid 	Sales Layer company id
 * @return array || boolean 	term found
 */
function find_saleslayer_term($taxonomy, $saleslayerid = null, $saleslayercompid = null){

	$meta_query = array();

	if (!is_null($saleslayerid)){

		array_push($meta_query, array('key' => 'saleslayerid', 'value' => $saleslayerid, 'compare' => '='));
	
	}

	if (!is_null($saleslayerid)){
	
		array_push($meta_query, array('key' => 'saleslayercompid', 'value' => $saleslayercompid, 'compare' => '='));
	
	}

   	$terms = get_terms(
   		array(
   			'hide_empty' => false,
			'taxonomy' => $taxonomy,
			'meta_query' => $meta_query
		)
   	);

   	if( is_wp_error( $terms ) ) {

   	    sl_debbug('## Error. find_saleslayer_term: '.$terms->get_error_message());
 
   	}else if (!empty($terms)){

   		$term = json_decode(json_encode($terms[0]), true);

   		$term_meta = get_term_meta( $term['term_id'], '', true );

   		if (!empty($term_meta)){

   			foreach ($term_meta as $term_meta_field => $term_meta_value) {
   				
   				if (is_array($term_meta_value) && count($term_meta_value) == 1){
			
					$term[$term_meta_field] = $term_meta_value[0];
			
				}else{
			
					$term[$term_meta_field] = $term_meta_value;
			
				}

   			}

   		}
	    	
	    return $term;

   	}

    return false;
}

/**
 * Function to update woo_commerce_term_meta.
 * @param string $term_id 		term id
 * @param string $meta_key 		meta key
 * @param string $meta_value 		meta value
 * @param string $prev_value 		prev value
 * @return void
 */
function sl_update_woocommerce_term_meta ($term_id, $meta_key, $meta_value, $prev_value = '' ){

	$resultado = $tipo = '';
	
	if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
        $term_meta_exists = get_term_meta( $term_id, $meta_key, true );
    }else{
        $term_meta_exists = get_woocommerce_term_meta( $term_id, $meta_key, true );
    }

	if ($term_meta_exists === false){
			
		$tipo = 'add';

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
            $resultado = add_term_meta( $term_id, $meta_key, $meta_value, false );
        }else{
            $resultado = add_woocommerce_term_meta( $term_id, $meta_key, $meta_value, false );
        }

	}else{
		
		$tipo = 'update';

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META){
            $resultado = update_term_meta( $term_id, $meta_key, $meta_value, $prev_value );
        }else{
            $resultado = update_woocommerce_term_meta( $term_id, $meta_key, $meta_value, $prev_value );
        }

	}

	if( is_wp_error( $resultado ) ) {

		sl_debbug('## Error. sl_update_woocommerce_term_meta '.$tipo.': '.$resultado->get_error_message());

	}

}

/**
 * Function to modify get_page_by_title and add page meta.
 * @param string $page_title 		title of page
 * @param string $output 			type of output
 * @param string $post_type 		type of post
 * @return array || boolean 		page found
 */
function sl_get_page_by_title($page_title, $output = OBJECT, $post_type){
	

	if ($page = get_page_by_title($page_title, $output, $post_type)){

		$page = add_meta_to_post($page, $output);
		
		return $page;

	}

	return false;

}

/**
 * Function to find post by Sales Layer identifiers.
 * @param string $saleslayerid 		Sales Layer id
 * @param string $saleslayercompid 	Sales Layer company id
 * @param string $output 			type of output
 * @return array || boolean 		post found
 */
function find_saleslayer_product($saleslayerid = null, $saleslayercompid = null, $output = 'ARRAY_A'){

	$meta_query = array();

	if (!is_null($saleslayerid)){

		array_push($meta_query, array('key' => '_saleslayerid', 'value' => $saleslayerid, 'compare' => '='));
	
	}

	if (!is_null($saleslayercompid)){
	
		array_push($meta_query, array('key' => '_saleslayercompid', 'value' => $saleslayercompid, 'compare' => '='));
	
	}

	// * 'publish' - a published post or page
	// * 'pending' - post is pending review
	// * 'draft' - a post in draft status
	// * 'auto-draft' - a newly created post, with no content
	// * 'future' - a post to publish in the future
	// * 'private' - not visible to users who are not logged in
	// * 'inherit' - a revision. see get_children.
	// * 'trash' - post is in trashbin. added with Version 2.9. 

	$posts = get_posts(
		array(
		    'post_type' => 'product',
		    'meta_query' => $meta_query,
		    'post_status' => array('publish', 'pending', 'draft', 'private', 'trash')
    	));

	if( is_wp_error( $posts ) ) {

		sl_debbug('## Error. find_saleslayer_product: '.$posts->get_error_message());

	}else if (!empty($posts)){

    	if ($output !== OBJECT){
    	
    		$wp_post = json_decode(json_encode($posts[0]), true);
    	
    	}else{
    	
    		$wp_post = $posts[0];
    	
    	}

    	$wp_post = add_meta_to_post($wp_post, $output);
    	
    	return $wp_post;    

	}
	
	return false;

}

/**
 * Function to add meta information to a post.
 * @param string $post 			post
 * @param string $output 		type of output
 * @return array || object 		post received
 */
function add_meta_to_post($post, $output = OBJECT){

	if ($output == OBJECT){

		$post_id = $post->ID;

	}else{

		$post_id = $post['ID'];

	}

	$post_meta = get_post_meta( $post_id, '', true );
	if (!empty($post_meta)){
		foreach ($post_meta as $field_name => $meta) {
			if (is_array($meta) && count($meta) == 1){
				$meta_value = $meta[0];
			}else{
				$meta_value = $meta;
			}

			if ($output == OBJECT){

				$post->$field_name = $meta_value;

			}else{

				$post[$field_name] = $meta_value;
			
			}

		}

	}

	return $post;

}

/**
 * Function to update meta to a post.
 * @param  string $post_id    term id
 * @param  string $meta_key   meta key
 * @param  string $meta_value meta value
 * @param  string $prev_value prev value
 * @return void
 */
function sl_update_post_meta($post_id, $meta_key, $meta_value, $prev_value = ''){

	$resultado = update_post_meta($post_id, $meta_key, $meta_value, $prev_value);

	if( is_wp_error( $resultado ) ) {

		sl_debbug('## Error. sl_update_post_meta: '.$resultado->get_error_message());

	}

	unset($resultado);

}

/**
 * Function to delete meta to a post.
 * @param  string $post_id    term id
 * @param  string $meta_key   meta key
 * @param  string $meta_value meta value
 * @return void
 */
function sl_delete_post_meta($post_id, $meta_key, $meta_value = ''){

	$resultado = delete_post_meta($post_id, $meta_key, $meta_value, $prev_value);

	if( is_wp_error( $resultado ) ) {

		sl_debbug('## Error. sl_delete_post_meta: '.$resultado->get_error_message());

	}

	unset($resultado);

}

/**
 * Function to update wp post.
 * @param  array $postarr    posarr
 * @param  boolean $wp_error   wp error
 * @return void
 */
function sl_wp_update_post($postarr = array(), $wp_error = false ){

	$resultado = wp_update_post($postarr, $wp_error);

	if( is_wp_error( $resultado ) ) {

		sl_debbug('## Error. sl_wp_update_post: '.$resultado->get_error_message());

	}

}

/**
 * Function to set object terms.
 * @param  string $object_id    object id
 * @param  array $terms   		terms
 * @param  string $taxonomy 	taxonomy
 * @param  boolean $append 		append
 * @return void
 */
function sl_wp_set_object_terms( $object_id, $terms, $taxonomy, $append = false){
	
	$resultado = wp_set_object_terms($object_id, $terms, $taxonomy, $append);

	if( is_wp_error( $resultado ) ) {

		sl_debbug('## Error. sl_wp_set_object_terms: '.$resultado->get_error_message());

	}

}

/**
 * Function to set post terms.
 * @param  string $post_id    	post id
 * @param  array $tags   		tags
 * @param  string $taxonomy 	taxonomy
 * @param  boolean $append 		append
 * @return void
 */
function sl_wp_set_post_terms( $post_id = 0, $tags = '', $taxonomy = 'post_tag', $append = false){
	
	$resultado = wp_set_post_terms($post_id, $tags, $taxonomy, $append);

	if( is_wp_error( $resultado ) ) {

		sl_debbug('## Error. sl_wp_set_post_terms: '.$resultado->get_error_message());

	}

}

/**
 * Function to validate value and return yes/no.
 * @param string $value 		value to check
 * @return string 				boolean value as string
 */
function sl_validate_boolean($value){
	
	if (is_array($value)){

		if (!empty($value)){
			
			$value = reset($value);
		
		}else{

			return 'no';
		
		}

	}

	if ( is_bool( $value ) ) {
    
    	if ($value == true){
    	
    		return 'yes';
    	
    	}else{
    	
    		return 'no';
    	
    	}
	
   	}

   	if ( ( is_string( $value ) && in_array( strtolower( $value ), array('false', '0', 'no') ) ) || ( is_numeric( $value ) && $value === 0 ) ) {
	
		return 'no';
   	
   	}

   	if ( ( is_string( $value ) && in_array( strtolower( $value ), array('true', '1', 'yes', 'si') ) ) || ( is_numeric( $value ) && $value === 1 ) ) {
   	
   		return 'yes';
   	
   	}   	

   	return 'no';

}

/**
 * Function to find post by Sales Layer identifiers.
 * @param string $saleslayerid 			Sales Layer id
 * @param string $saleslayercompid 		Sales Layer company id
 * @param string $saleslayerformatid	Sales Layer format id
 * @param string $output 				type of output
 * @return array || boolean 		post found
 */
function find_saleslayer_format($saleslayerid = null, $saleslayercompid = null, $saleslayerformatid = null, $output = 'ARRAY_A'){

	$meta_query = array();

	if (!is_null($saleslayerid)){

		array_push($meta_query, array('key' => '_saleslayerid', 'value' => $saleslayerid, 'compare' => '='));
	
	}

	if (!is_null($saleslayercompid)){
	
		array_push($meta_query, array('key' => '_saleslayercompid', 'value' => $saleslayercompid, 'compare' => '='));
	
	}

	if (!is_null($saleslayerformatid)){
	
		array_push($meta_query, array('key' => '_saleslayerformatid', 'value' => $saleslayerformatid, 'compare' => '='));
	
	}

	// * 'publish' - a published post or page
	// * 'pending' - post is pending review
	// * 'draft' - a post in draft status
	// * 'auto-draft' - a newly created post, with no content
	// * 'future' - a post to publish in the future
	// * 'private' - not visible to users who are not logged in
	// * 'inherit' - a revision. see get_children.
	// * 'trash' - post is in trashbin. added with Version 2.9. 

	$posts = get_posts(
		array(
		    'post_type' => 'product_variation',
		    'meta_query' => $meta_query,
		    'post_status' => array('publish', 'pending', 'draft', 'private', 'trash')
    	)
	);

	if( is_wp_error( $posts ) ) {

		sl_debbug('## Error. find_saleslayer_format: '.$posts->get_error_message());

	}else if (!empty($posts)){
    	
    	if ($output !== OBJECT){
    	
    		$wp_post = json_decode(json_encode($posts[0]), true);
    	
    	}else{
    	
    		$wp_post = $posts[0];
    	
    	}

    	$wp_post = add_meta_to_post($wp_post, $output);

    	return $wp_post;
    
    }

    return false;

}

/**
 * Function to obtain all WC products and variations
 * @return array 			array containing products and variations
 */
function get_all_products_and_variations(){

	$wp_posts = get_posts(
		array(
		    'post_type' => array('product', 'product_variation' ),
		    'post_status' => array('publish', 'pending', 'draft', 'private', 'trash'),
		    'orderby' => 'post_type',
		    'order'		=> 'asc',
		    'numberposts' => -1,
    	));

	if( is_wp_error( $wp_posts ) ) {

		sl_debbug('## Error. get_all_products_and_variations: '.$wp_posts->get_error_message());

	}

	$wp_posts_data = array();
	
	if (!empty($wp_posts)){

		$meta_fields = array('_sku' => 'sku', '_saleslayerid' => 'saleslayerid', '_saleslayercompid' => 'saleslayercompid', '_saleslayerformatid' => 'saleslayerformatid');

		foreach ($wp_posts as $key => $wp_read_post) {
			
			$item_has_sku = false;

			if ($wp_read_post !== OBJECT){
			
				$wp_post = json_decode(json_encode($wp_read_post), true);
			
			}else{
			
				$wp_post = $wp_read_post;
			
			}
			
			$wp_post_id = $wp_post['ID'];
			$wp_posts_data[$wp_post_id]['post_id'] = $wp_post_id;
			$wp_posts_data[$wp_post_id]['post_title'] = $wp_post['post_title'];
			$wp_posts_data[$wp_post_id]['post_type'] = $wp_post['post_type'];				

			$post_meta = get_post_meta( $wp_post_id, '', true );

			if (!empty($post_meta)){

				foreach ($meta_fields as $woo_meta_field => $meta_field_name) {
					
					if (isset($post_meta[$woo_meta_field])){

						$meta_value = '';

						if (is_array($post_meta[$woo_meta_field]) && !empty($post_meta[$woo_meta_field])){

							$meta_value = $post_meta[$woo_meta_field][0];

						}else{
							$meta_value = $post_meta[$woo_meta_field];

						}

						if ($meta_value != ''){

							$wp_posts_data[$wp_post_id][$meta_field_name] = $meta_value;

							if ($meta_field_name == 'sku'){ $item_has_sku = true; }

						}else if ($meta_field_name == 'sku'){

							break;

						}else{

							$wp_posts_data[$wp_post_id][$meta_field_name] = 0;

						}

					}else{

						if ($meta_field_name != 'sku'){

							$wp_posts_data[$wp_post_id][$meta_field_name] = 0;

						}

					}

				}

			}

			$wp_item = $wp_posts_data[$wp_post_id];
			unset($wp_posts_data[$wp_post_id]);
			if ($item_has_sku){ $wp_posts_data[$wp_item['sku']] = $wp_item; }

		}

	}

	return $wp_posts_data;

}

/**
 * Function to pre-process by skus
 * @param  string $type    			type of element, product or variation
 * @param  integer $comp_id 		SL company id
 * @param  array $items   			items to pre-process
 * @return array          			items that won't be synchronized
 */
function pre_process_by_skus($type, $comp_id, $items){

	if ($type == 'product_variation'){

		$form_class = new Format();
	
	}else{

		$prod_class = Product::get_instance();
		
	}

	if (!in_array($type, array('product', 'product_variation'))){

		sl_debbug('## Error. pre_process_by_skus - Type '.$type.' inválido.');

	}

	$wp_posts = get_all_products_and_variations();

	foreach ($items as $keyItem => $item) {

		if ($type == 'product_variation'){
	
			if (!isset($item['data'][$form_class->format_field_sku]) || (isset($item['data'][$form_class->format_field_sku]) && $item['data'][$form_class->format_field_sku] == '')){

				continue;

			}else{

				$item_sku = $item['data'][$form_class->format_field_sku];
			}

			$item_id = $item[$form_class->format_id_products_field];
			$item_format_id = $item[$form_class->format_id_field];
	
		}else{

			if (!isset($item['data'][$prod_class->product_field_sku]) || (isset($item['data'][$prod_class->product_field_sku]) && $item['data'][$prod_class->product_field_sku] == '')){

				continue;

			}else{

				$item_sku = $item['data'][$prod_class->product_field_sku];
			}

			$item_id = $item[$prod_class->product_id_field];
			$item_format_id = 0;

		}

		$sl_items_to_update[$item_sku][$type] = array('saleslayerid' => $item_id, 'saleslayerformatid' => $item_format_id, 'idx_array' => $keyItem);

	}

	$not_to_sync_items = array();
	
	if (!empty($sl_items_to_update) && !empty($wp_posts)){

		$counter = 0;

		do{
			
			foreach ($sl_items_to_update as $sl_item_sku => $sl_item_types) {

				foreach ($sl_item_types as $sl_item_type => $sl_item_data) {
					
				    if (isset($wp_posts[$sl_item_sku])){

				    	$wp_post_data = $wp_posts[$sl_item_sku];
				    	$existing_saleslayerid = $wp_post_data['saleslayerid'];
						$existing_saleslayercompid = $wp_post_data['saleslayercompid'];
						$existing_saleslayerformatid = $wp_post_data['saleslayerformatid'];
						
						if ($existing_saleslayerid == 0 && $existing_saleslayercompid == 0 && $existing_saleslayerformatid == 0){

							if ($type == 'product_variation'){

								$wp_format = find_saleslayer_format($sl_item_data['saleslayerid'], $comp_id, $sl_item_data['saleslayerformatid']);

								if ($wp_format){

									($sl_item_type == 'product') ? $sl_type_message = 'Product with SL data - ID: '.$sl_item_data['saleslayerid'] : $sl_type_message = 'Product format with SL data - ID: '.$sl_item_data['saleslayerformatid'];
									($wp_post_data['post_type'] == 'product') ? $wp_type_message = 'product': $wp_type_message = 'product format';
									$error_message = $sl_type_message.' SKU: '.$sl_item_sku." hasn't been synchronized because the SKU is already in use by another ".$wp_type_message.' with WOO data - ID: '.$wp_post_data['post_id'].' SKU: '.$wp_post_data['sku'].' Title: '.$wp_post_data['post_title'];
									$not_to_sync_items[] = array('array_index' => $sl_item_data['idx_array'], 'error_message' => $error_message);

								}

							}else{

								$wp_product = find_saleslayer_product($sl_item_data['saleslayerid'], $comp_id);
								
								if ($wp_product){

									($sl_item_type == 'product') ? $sl_type_message = 'Product with SL data - ID: '.$sl_item_data['saleslayerid'] : $sl_type_message = 'Product format with SL data - ID: '.$sl_item_data['saleslayerformatid'];
									($wp_post_data['post_type'] == 'product') ? $wp_type_message = 'product': $wp_type_message = 'product format';
									$error_message = $sl_type_message.' SKU: '.$sl_item_sku." hasn't been synchronized because the SKU is already in use by another ".$wp_type_message.' with WOO data - ID: '.$wp_post_data['post_id'].' SKU: '.$wp_post_data['sku'].' Title: '.$wp_post_data['post_title'];
									$not_to_sync_items[] = array('array_index' => $sl_item_data['idx_array'], 'error_message' => $error_message);

								}
								
							}

							unset($sl_items_to_update[$sl_item_sku][$sl_item_type]);
							
						}else{

							if ($existing_saleslayerid == $sl_item_data['saleslayerid'] && $existing_saleslayercompid == $comp_id && $existing_saleslayerformatid == $sl_item_data['saleslayerformatid']){

					        	unset($sl_items_to_update[$sl_item_sku][$sl_item_type]);

							}

						}

			        }else{

			        	if ($type == 'product_variation'){

			        		$wp_format = find_saleslayer_format($sl_item_data['saleslayerid'], $comp_id, $sl_item_data['saleslayerformatid']);

        		        	if ($wp_format){

        		        		$wp_format_old_sku = $wp_format['_sku'];

        		        		$counter = 0;
        		        		sl_update_post_meta($wp_format['ID'], '_sku', $sl_item_sku);
        		        		$wp_post = $wp_posts[$wp_format_old_sku];
        						$wp_post['sku'] = $sl_item_sku;
        		        		unset($wp_posts[$wp_format_old_sku]);
        		        		$wp_posts[$sl_item_sku] = $wp_post;
        		        	
        		        	}

			        	}else{

				        	$wp_product = find_saleslayer_product($sl_item_data['saleslayerid'], $comp_id);

				        	if ($wp_product){

				        		$wp_product_old_sku = $wp_product['_sku'];

				        		$counter = 0;
				        		sl_update_post_meta($wp_product['ID'], '_sku', $sl_item_sku);
				        		$wp_post = $wp_posts[$wp_product_old_sku];
								$wp_post['sku'] = $sl_item_sku;
				        		unset($wp_posts[$wp_product_old_sku]);
				        		$wp_posts[$sl_item_sku] = $wp_post;
				        	
				        	}

			        	}

			        	unset($sl_items_to_update[$sl_item_sku][$sl_item_type]);

			        }
			
			    }

			    if (isset($sl_items_to_update[$sl_item_sku]) && empty($sl_items_to_update[$sl_item_sku])){

			    	unset($sl_items_to_update[$sl_item_sku]);

			    }

			}

			$counter++;

			if ($counter == 3){

				if (!empty($sl_items_to_update)){

					foreach ($sl_items_to_update as $sl_item_sku => $sl_item_types) {

						foreach ($sl_item_types as $sl_item_type => $sl_item_data) {
						
							($sl_item_type == 'product') ? $sl_type_message = 'Product with SL data - ID: '.$sl_item_data['saleslayerid'] : $sl_type_message = 'Product format with SL data - ID: '.$sl_item_data['saleslayerformatid'];
							
							$error_message = $sl_type_message.' SKU: '.$sl_item_sku." hasn't been synchronized because the SKU is already in use by another item.";

							$not_to_sync_items[] = array('array_index' => $sl_item_data['idx_array'], 'error_message' => $error_message);

						}

					}

				}

				break;

			}		            
						
		}while (count($sl_items_to_update) > 0);
		
	}

	return $not_to_sync_items;

}

/**
 * Function to execute a sql and commit it.
 * @param  string $type 						type of sql 
 * @param  string $query               			sql to execute
 * @param  array $params  						parameters to execute with sql
 * @return array or false 						result of sql
 */
function sl_connection_query($type, $query, $params = array()){

	global $wpdb;

    try{

    	if ($type == 'read'){

    		if (!empty($params)){

    			$resultado = $wpdb->get_results($query, $params);

    		}else{

    			$resultado = $wpdb->get_results($query);

    		}

    		$resultado = json_decode(json_encode($resultado), true);

    		if ($resultado && strpos($query, 'sl_cuenta_registros') !== false){

    			if (isset($resultado[0])){ $resultado = $resultado[0]; }

			}

    	}else{

    		if (!empty($params)){

    			$resultado = $wpdb->query($query, $params);

    		}else{

    			$resultado = $wpdb->query($query);

    		}

    		$resultado = json_decode(json_encode($resultado), true);

    	}

    }catch(\Exception $e) {
        
        if (!empty($params)){

            sl_debbug('## Error. SL SQL type: '.$type.' - query: '.$query.' - params: '.print_r($params,1));
            
        }else{

            sl_debbug('## Error. SL SQL type: '.$type.' - query: '.$query);
            
        }

        sl_debbug('## Error. SL SQL error message: '.$e->getMessage());

    }

    if (!$resultado){

    	return false;
    
    }else{

    	return $resultado;

    }

}