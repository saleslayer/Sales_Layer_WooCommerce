<?php

/**
 * Compare two names after normalizing (lowercase and underscores to spaces).
 * @param string $wp_name WordPress name
 * @param string $sl_name Sales Layer name
 * @return bool True if names are equal after normalization, false otherwise
 */
function compareNames($wp_name, $sl_name)
{
    $new_wp_name = strtolower(str_replace('_', ' ', $wp_name));
    $new_sl_name = strtolower(str_replace('_', ' ', $sl_name));

    if (levenshtein($new_wp_name, $new_sl_name) == 0) {
        return true;
    } else {
        return false;
    }
}

/**
 * Normalize table keys when schema is multilingual.
 * @param array $tables Tables to organize
 * @param array $tableStructure Table structure definition
 * @return array Organized tables
 */
function organizeTablesIndex($tables, $tableStructure)
{
    foreach ($tableStructure as $keyStruct => $fieldStruct) {
        if (isset($fieldStruct['multilingual_name'])) {
            foreach ($tables as $keyTab => $fieldTable) {
                if (array_key_exists($fieldStruct['multilingual_name'], $fieldTable['data'])) {
                    $tables[$keyTab]['data'][$keyStruct] = $tables[$keyTab]['data'][$fieldStruct['multilingual_name']];
                    unset($tables[$keyTab]['data'][$fieldStruct['multilingual_name']]);
                }
            }
        }
    }

    return $tables;
}

/**
 * Sort comparator by image area (descending).
 * @param array $img_a First image
 * @param array $img_b Second image
 * @return int Comparison result (<0, =0, >0)
 */
function sortByDimension($img_a, $img_b)
{
    $area_a = $img_a['width'] * $img_a['height'];
    $area_b = $img_b['width'] * $img_b['height'];

    return strnatcmp($area_b, $area_a);
}

/**
 * Order an image size map by area (keeping 'ORG' first).
 * @param array $array_img Images to order
 * @return array Ordered images
 */
function order_array_img($array_img)
{
    uasort($array_img, 'sortByDimension');

    if (isset($array_img['ORG'])) {
        $org_array_img = array();
        $org_array_img['ORG'] = $array_img['ORG'];
        foreach ($array_img as $keySize => $valSize) {
            if ($keySize == 'ORG') { continue; }
            $org_array_img[$keySize] = $valSize;
        }
        return $org_array_img;
    }

    return $array_img;
}

/**
 * Wrapper around get_term_by that also loads term meta into the result.
 * @param string $field Field
 * @param string $value Value
 * @param string $taxonomy Taxonomy
 * @param string $output Output type (OBJECT|ARRAY_A)
 * @param string $filter Filter
 * @return object|array|false Term with meta or false when not found
 */
function sl_get_term_by($field, $value, string $taxonomy = '', string $output = OBJECT, string $filter = 'raw')
{
    if ($term = get_term_by($field, $value, $taxonomy, $output, $filter)) {

        if ($output == OBJECT){

            $term_id = $term->term_id;

                } else {

            $term_id = $term['term_id'];

        }

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
            $term_meta = get_term_meta($term_id, '', true);
        } else {
            $term_meta = get_woocommerce_term_meta($term_id, '', true);
        }

        if (!empty($term_meta)) {
            foreach ($term_meta as $field_name => $meta) {
                if (is_array($meta) && count($meta) == 1) {
                    $meta_value = $meta[0];
                } else {
                    $meta_value = $meta;
                }

                if ($output == OBJECT) {

                    $term->$field_name = $meta_value;

                } else {

                    $term[$field_name] = $meta_value;
                
                }

            }

        }

        return $term;

    }

    return false;

}

/**
 * Find an unassigned product_cat term by name (no Sales Layer meta).
 * @param string $value Term name to search
 * @param string $output Output type (OBJECT|ARRAY_A)
 * @return object|array|false Term without SL meta or false
 */
function sl_find_unassigned_product_cat_terms_by_name($value, string $output = OBJECT)
{
    $meta_query = array(
        array('key' => 'saleslayerid', 'compare' => 'NOT EXISTS'),
        array('key' => 'saleslayercompid', 'compare' => 'NOT EXISTS')
    );
    
   	$terms = get_terms(
        array(
            'hide_empty' => false,
            'taxonomy' => 'product_cat',
            'meta_query' => $meta_query,
            'name' => $value,
            'orderby' => 'term_id',
            'order' => 'asc',
        )
    );

        if (!empty($terms)) {

   		$term = reset($terms);

            if ($output == OBJECT) {

            $term_id = $term->term_id;

            } else {

            $term = $term->to_array();
            $term_id = $term['term_id'];

            }

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
            $term_meta = get_term_meta($term_id, '', true);
        } else {
            $term_meta = get_woocommerce_term_meta($term_id, '', true);
        }

        if (!empty($term_meta)) {

            foreach ($term_meta as $field_name => $meta) {

                    if (is_array($meta) && count($meta) == 1) {
                    $meta_value = $meta[0];
                    } else {
                    $meta_value = $meta;
                }

                    if ($output == OBJECT) {

                    $term->$field_name = $meta_value;

                    } else {

                    $term[$field_name] = $meta_value;
                
                }

            }

        }

        return $term;

   	}

    return false;

}

/**
 * Find a WP term by Sales Layer identifiers and include term meta.
 *
 * When $lang is provided and a multilang plugin is active, the search is narrowed
 * to the specific language variant identified by the slyr_wc_lang term meta.
 * Omitting $lang (or passing '') preserves the original behaviour.
 *
 * Note: term meta keys use no underscore prefix (saleslayerid, saleslayercompid, slyr_wc_lang),
 * following the existing convention used throughout the plugin for WP terms.
 *
 * @param string      $taxonomy        WP taxonomy (e.g. 'product_cat')
 * @param string|null $saleslayerid    Sales Layer term ID
 * @param string|null $saleslayercompid Sales Layer company ID
 * @param string      $lang            Language code to filter by (e.g. 'es', 'en').
 *                                     Empty string disables the language filter (default).
 * @return array|false                 Term with merged meta, or false if not found
 */
function find_saleslayer_term($taxonomy, ?string $saleslayerid = null, ?string $saleslayercompid = null, string $lang = '')
{

    $meta_query = array();

    if (!is_null($saleslayerid)) {

        array_push($meta_query, array('key' => 'saleslayerid', 'value' => $saleslayerid, 'compare' => '='));

    }

    if (!is_null($saleslayercompid)) {

        array_push($meta_query, array('key' => 'saleslayercompid', 'value' => $saleslayercompid, 'compare' => '='));

    }

    // Narrow search to a specific language variant when a multilang plugin is active.
    // Term meta keys have no underscore prefix — consistent with saleslayerid / saleslayercompid.
    // Only applies when $lang is explicitly provided — preserves legacy behaviour otherwise.
    if ($lang !== '' && function_exists('slyr_detect_active_multilang_plugin') && slyr_detect_active_multilang_plugin()) {

        array_push($meta_query, array('key' => 'slyr_wc_lang', 'value' => $lang, 'compare' => '='));

    }

   	$terms = get_terms(
   		array(
   			'hide_empty' => false,
            'taxonomy' => $taxonomy,
            'meta_query' => $meta_query
        )
   	);

       if (is_wp_error($terms)) {

           sl_debug('## Error. find_saleslayer_term: ' . $terms->get_error_message());
 
       } elseif (!empty($terms)) {

   		$term = json_decode(json_encode($terms[0]), true);

   		$term_meta = get_term_meta( $term['term_id'], '', true );

           if (!empty($term_meta)) {

   			foreach ($term_meta as $term_meta_field => $term_meta_value) {
   				
               if (is_array($term_meta_value) && count($term_meta_value) == 1) {
            
                    $term[$term_meta_field] = $term_meta_value[0];
            
               } else {
            
                    $term[$term_meta_field] = $term_meta_value;
            
                }

   			}

   		}
        	
        return $term;

   	}

    return false;
}

/**
 * Update WooCommerce term meta (handles deprecated functions gracefully).
 * @param string $term_id 		term id
 * @param string $meta_key 		meta key
 * @param string $meta_value 		meta value
 * @param string $prev_value 		prev value
 * @return void
 */
function sl_update_woocommerce_term_meta($term_id, $meta_key, $meta_value, string $prev_value = '')
{
    $resultado = $tipo = '';

    if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
        $term_meta_exists = get_term_meta($term_id, $meta_key, true);
    } else {
        $term_meta_exists = get_woocommerce_term_meta($term_id, $meta_key, true);
    }

    if ($term_meta_exists === false) {
        $tipo = 'add';

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
            $resultado = add_term_meta($term_id, $meta_key, $meta_value, false);
        } else {
            $resultado = add_woocommerce_term_meta($term_id, $meta_key, $meta_value, false);
        }
    } else {
        $tipo = 'update';

        if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
            $resultado = update_term_meta($term_id, $meta_key, $meta_value, $prev_value);
        } else {
            $resultado = update_woocommerce_term_meta($term_id, $meta_key, $meta_value, $prev_value);
        }
    }

    if (is_wp_error($resultado)) {
        sl_debug('## Error. sl_update_woocommerce_term_meta ' . $tipo . ': ' . $resultado->get_error_message());
    }
}

/**
 * Get a page by title and include its meta.
 * @param string $page_title Page title
 * @param string $post_type Post type
 * @param string $output Output type (OBJECT|ARRAY_A|ARRAY_N)
 * @return object|array|false Page (with meta) or false
 */
function sl_get_page_by_title($page_title, $post_type, string $output = OBJECT)
{
    $args = array(
        'post_type' => $post_type,
        'title'     => $page_title,
        'post_status' => 'publish',
        'posts_per_page' => 1
    );
    $query = new WP_Query($args);

    if ($query->have_posts()) {	
        $page = $query->posts[0];
        if ($output === OBJECT) {
            $page = get_post($page->ID);
        } elseif ($output === ARRAY_A) {
            $page = get_post($page->ID, ARRAY_A);
        } elseif ($output === ARRAY_N) {
            $page = get_post($page->ID, ARRAY_N);
        }
        $page = add_meta_to_post($page, $output);
        return $page;
    }

    return false;
}

/**
 * Find a product by Sales Layer identifiers and include meta.
 * @param string $saleslayerid Sales Layer id
 * @param string $saleslayercompid Sales Layer company id
 * @param string $output Output type (OBJECT|ARRAY_A)
 * @return object|array|false Post (with meta) or false
 */
/**
 * Find a WooCommerce product by its Sales Layer identifiers.
 *
 * When $lang is provided and a multilang plugin is active, the search is narrowed
 * to the specific language variant identified by the _slyr_wc_lang post meta.
 * Omitting $lang (or passing '') preserves the original behaviour: returns the
 * first product matching $saleslayerid + $saleslayercompid, regardless of language.
 *
 * @param string|null $saleslayerid    Sales Layer product ID
 * @param string|null $saleslayercompid Sales Layer company ID
 * @param string      $lang            Language code to filter by (e.g. 'es', 'en').
 *                                     Empty string disables the language filter (default).
 * @param string      $output          Return format: 'ARRAY_A' (default) or OBJECT
 * @return array|object|false          Post with merged meta, or false if not found
 */
function find_saleslayer_product(
    ?string $saleslayerid = null,
    ?string $saleslayercompid = null,
    string $lang = '',
    string $output = 'ARRAY_A'
) {

    $meta_query = array();

    if (!is_null($saleslayerid)){

        array_push($meta_query, array('key' => '_saleslayerid', 'value' => $saleslayerid, 'compare' => '='));

    }

    if (!is_null($saleslayercompid)){

        array_push($meta_query, array('key' => '_saleslayercompid', 'value' => $saleslayercompid, 'compare' => '='));

    }

    // Narrow search to a specific language variant when a multilang plugin is active.
    // Only applies when $lang is explicitly provided — preserves legacy behaviour otherwise.
    if ($lang !== '' && function_exists('slyr_detect_active_multilang_plugin') && slyr_detect_active_multilang_plugin()) {

        array_push($meta_query, array('key' => '_slyr_wc_lang', 'value' => $lang, 'compare' => '='));

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

        if (is_wp_error($posts)) {

        sl_debug('## Error. find_saleslayer_product: '.$posts->get_error_message());

        } elseif (!empty($posts)) {

            if ($output !== OBJECT) {
    	
    		$wp_post = json_decode(json_encode($posts[0]), true);
    	
            } else {
    	
    		$wp_post = $posts[0];
    	
    	}

    	$wp_post = add_meta_to_post($wp_post, $output);
    	
    	return $wp_post;    

    }
    
    return false;

}

/**
 * Add post meta fields into a post structure.
 * @param object|array $post Post object or array
 * @param string $output Output type (OBJECT|ARRAY_A)
 * @return object|array Post with meta fields merged
 */
function add_meta_to_post($post, string $output = OBJECT)
{

    if ($output == OBJECT) {

        $post_id = $post->ID;

    } else {

        $post_id = $post['ID'];

    }

    $post_meta = get_post_meta($post_id, '', true);
    if (!empty($post_meta)) {
        foreach ($post_meta as $field_name => $meta) {
            if (is_array($meta) && count($meta) == 1) {
                $meta_value = $meta[0];
            } else {
                $meta_value = $meta;
            }

            if ($output == OBJECT) {

                $post->$field_name = $meta_value;

                            } else {

                $post[$field_name] = $meta_value;
            
            }

        }

    }

    return $post;

}

/**
 * Update a post meta field.
 * @param  int|string $post_id Post ID
 * @param  string $meta_key Meta key
 * @param  string $meta_value Meta value (optional)
 * @param  string $prev_value prev value
 * @return void
 */
function sl_update_post_meta($post_id, $meta_key, $meta_value, string $prev_value = '')
{

    $resultado = update_post_meta($post_id, $meta_key, $meta_value, $prev_value);

    if (is_wp_error($resultado)) {

        sl_debug('## Error. sl_update_post_meta: '.$resultado->get_error_message());

    }

    unset($resultado);

}

/**
 * Delete a post meta field (WordPress signature compliant).
 * @param  string $post_id    term id
 * @param  string $meta_key   meta key
 * @param  string $meta_value meta value
 * @return void
 */
function sl_delete_post_meta($post_id, $meta_key, array|string|int $meta_value = '')
{

    // Respect WordPress signature: delete_post_meta(post_id, meta_key, meta_value = '')
    $resultado = delete_post_meta($post_id, $meta_key, $meta_value);

    if (is_wp_error($resultado)) {
        sl_debug('## Error. sl_delete_post_meta: ' . $resultado->get_error_message());
    }

    unset($resultado);

}

/**
 * Update a WordPress post with KSES filters temporarily disabled.
 * @param  array $postarr Post data array for wp_update_post
 * @param  bool $wp_error Whether to return WP_Error on failure
 * @return void
 */
function sl_wp_update_post(array $postarr = array(), bool $wp_error = false)
{

    remove_filter('content_save_pre', 'wp_filter_post_kses');
    remove_filter('content_filtered_save_pre', 'wp_filter_post_kses');

    $resultado = wp_update_post($postarr, $wp_error);

    add_filter('content_save_pre', 'wp_filter_post_kses');
    add_filter('content_filtered_save_pre', 'wp_filter_post_kses');

    if (is_wp_error($resultado)) {

        sl_debug('## Error. sl_wp_update_post: '.$resultado->get_error_message());

    }

}

/**
 * Set object terms.
 * @param  int|string $object_id Object ID
 * @param  array $terms Terms
 * @param  string $taxonomy Taxonomy
 * @param  bool $append Append to existing terms
 * @return void
 */
function sl_wp_set_object_terms($object_id, $terms, $taxonomy, bool $append = false)
{
    
    $resultado = wp_set_object_terms($object_id, $terms, $taxonomy, $append);

    if (is_wp_error($resultado)) {

        sl_debug('## Error. sl_wp_set_object_terms: '.$resultado->get_error_message());

    }

}

/**
 * Set post terms.
 * @param  int|string $post_id Post ID
 * @param  array $tags Tags
 * @param  string $taxonomy Taxonomy
 * @param  bool $append Append to existing terms
 * @return void
 */
function sl_wp_set_post_terms(int $post_id = 0, array|string $tags = '', string $taxonomy = 'post_tag', bool $append = false)
{
    
    $resultado = wp_set_post_terms($post_id, $tags, $taxonomy, $append);

    if (is_wp_error($resultado)) {

        sl_debug('## Error. sl_wp_set_post_terms: '.$resultado->get_error_message());

    }

}

/**
 * Normalize a value into 'yes' or 'no'.
 * @param mixed $value Value to check
 * @return string 'yes' or 'no'
 */
function sl_validate_boolean($value)
{
    
    if (is_array($value)) {

        if (!empty($value)) {
            
            $value = reset($value);
        
        } else {

            return 'no';
        
        }

    }

    if (is_bool($value)) {
    
        if ($value == true) {
    	
    		return 'yes';
    	
        } else {
    	
    		return 'no';
    	
    	}
    
   	}

    if ((is_string($value) && in_array(strtolower($value), array('false', '0', 'no'))) || (is_numeric($value) && $value === 0)) {
    
        return 'no';
   	
   	}

    if ((is_string($value) && in_array(strtolower($value), array('true', '1', 'yes', 'si'))) || (is_numeric($value) && $value === 1)) {
   	
   		return 'yes';
   	
   	}   	

   	return 'no';

}

/**
 * Find a product variation by Sales Layer identifiers and include meta.
 * @param string $saleslayerid Sales Layer id
 * @param string $saleslayercompid Sales Layer company id
 * @param string $saleslayerformatid Sales Layer format id
 * @param string $output Output type (OBJECT|ARRAY_A)
 * @return object|array|false Post (with meta) or false
 */
/**
 * Find a WooCommerce product variation by its Sales Layer identifiers.
 *
 * When $lang is provided and a multilang plugin is active, the search is narrowed
 * to the specific language variant identified by the _slyr_wc_lang post meta.
 * Omitting $lang (or passing '') preserves the original behaviour.
 *
 * @param string|null $saleslayerid     Sales Layer parent product ID
 * @param string|null $saleslayercompid Sales Layer company ID
 * @param string|null $saleslayerformatid Sales Layer format ID
 * @param string      $lang             Language code to filter by (e.g. 'es', 'en').
 *                                      Empty string disables the language filter (default).
 * @param string      $output           Return format: 'ARRAY_A' (default) or OBJECT
 * @return array|object|false           Post with merged meta, or false if not found
 */
function find_saleslayer_format(
    ?string $saleslayerid = null,
    ?string $saleslayercompid = null,
    ?string $saleslayerformatid = null,
    string $lang = '',
    string $output = 'ARRAY_A'
) {

    $meta_query = array();

    if (!is_null($saleslayerid)) {

        array_push($meta_query, array('key' => '_saleslayerid', 'value' => $saleslayerid, 'compare' => '='));

    }

    if (!is_null($saleslayercompid)) {

        array_push($meta_query, array('key' => '_saleslayercompid', 'value' => $saleslayercompid, 'compare' => '='));

    }

    if (!is_null($saleslayerformatid)) {

        array_push($meta_query, array('key' => '_saleslayerformatid', 'value' => $saleslayerformatid, 'compare' => '='));

    }

    // Narrow search to a specific language variant when a multilang plugin is active.
    // Only applies when $lang is explicitly provided — preserves legacy behaviour otherwise.
    if ($lang !== '' && function_exists('slyr_detect_active_multilang_plugin') && slyr_detect_active_multilang_plugin()) {

        array_push($meta_query, array('key' => '_slyr_wc_lang', 'value' => $lang, 'compare' => '='));

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

    if (is_wp_error($posts)) {

        sl_debug('## Error. find_saleslayer_format: '.$posts->get_error_message());

        } elseif (!empty($posts)) {
    	
        if ($output !== OBJECT) {
    	
    		$wp_post = json_decode(json_encode($posts[0]), true);
    	
        } else {
    	
    		$wp_post = $posts[0];
    	
    	}

    	$wp_post = add_meta_to_post($wp_post, $output);

    	return $wp_post;
    
    }

    return false;

}

/**
 * Get all WC products and variations with key meta fields, keyed by SKU.
 * @return array Products/variations keyed by SKU with selected meta
 */
function get_all_products_and_variations()
{

    $wp_posts = get_posts(
        array(
            'post_type' => array('product', 'product_variation'),
            'post_status' => array('publish', 'pending', 'draft', 'private', 'trash'),
            'orderby' => 'post_type',
            'order' => 'asc',
            'numberposts' => -1,
        )
    );

    if (is_wp_error($wp_posts)) {

        sl_debug('## Error. get_all_products_and_variations: '.$wp_posts->get_error_message());

    }

    $wp_posts_data = array();
    
        if (!empty($wp_posts)) {

        $meta_fields = array('_sku' => 'sku', '_saleslayerid' => 'saleslayerid', '_saleslayercompid' => 'saleslayercompid', '_saleslayerformatid' => 'saleslayerformatid');

        foreach ($wp_posts as $key => $wp_read_post) {
            
            $item_has_sku = false;

            if ($wp_read_post !== OBJECT) {
            
                $wp_post = json_decode(json_encode($wp_read_post), true);
            
            } else {
            
                $wp_post = $wp_read_post;
            
            }
            
            $wp_post_id = $wp_post['ID'];
            $wp_posts_data[$wp_post_id]['post_id'] = $wp_post_id;
            $wp_posts_data[$wp_post_id]['post_title'] = $wp_post['post_title'];
            $wp_posts_data[$wp_post_id]['post_type'] = $wp_post['post_type'];				

            $post_meta = get_post_meta($wp_post_id, '', true);

            if (!empty($post_meta)) {

                foreach ($meta_fields as $woo_meta_field => $meta_field_name) {
                    
                    if (isset($post_meta[$woo_meta_field])) {

                        $meta_value = '';

                        if (is_array($post_meta[$woo_meta_field]) && !empty($post_meta[$woo_meta_field])) {

                            $meta_value = $post_meta[$woo_meta_field][0];

                        } else {
                            $meta_value = $post_meta[$woo_meta_field];

                        }

                        if ($meta_value != '') {

                            $wp_posts_data[$wp_post_id][$meta_field_name] = $meta_value;

                            if ($meta_field_name == 'sku') { $item_has_sku = true; }

                        } elseif ($meta_field_name == 'sku') {

                            break;

                        } else {

                            $wp_posts_data[$wp_post_id][$meta_field_name] = 0;

                        }

                    } else {

                        if ($meta_field_name != 'sku') {

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
 * Pre-process items by SKU to remap SKUs and detect conflicts.
 * @param string $type Item type ('product'|'product_variation')
 * @param int $comp_id Sales Layer company id
 * @param array $items Items to pre-process
 * @return array Items that won't be synchronized (with reasons)
 */
function pre_process_by_skus($type, $comp_id, $items)
{

    if ($type == 'product_variation') {

        $form_class = new Format();
    
    } else {

        $prod_class = Product::get_instance();
        
    }

    if (!in_array($type, array('product', 'product_variation'))) {

        sl_debug('## Error. pre_process_by_skus - Type '.$type.' inválido.');

    }

    $wp_posts = get_all_products_and_variations();

    foreach ($items as $keyItem => $item) {

        if ($type == 'product_variation') {
    
            if (!isset($item['data'][$form_class->format_field_sku]) || (isset($item['data'][$form_class->format_field_sku]) && $item['data'][$form_class->format_field_sku] == '')){

                continue;

            } else {

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

                            if ($type == 'product_variation') {

                                $wp_format = find_saleslayer_format($sl_item_data['saleslayerid'], $comp_id, $sl_item_data['saleslayerformatid']);

                                 if ($wp_format) {

                                    ($sl_item_type == 'product') ? $sl_type_message = 'Product with SL data - ID: '.$sl_item_data['saleslayerid'] : $sl_type_message = 'Product format with SL data - ID: '.$sl_item_data['saleslayerformatid'];
                                    ($wp_post_data['post_type'] == 'product') ? $wp_type_message = 'product': $wp_type_message = 'product format';
                                    $error_message = $sl_type_message.' SKU: '.$sl_item_sku." hasn't been synchronized because the SKU is already in use by another ".$wp_type_message.' with WOO data - ID: '.$wp_post_data['post_id'].' SKU: '.$wp_post_data['sku'].' Title: '.$wp_post_data['post_title'];
                                    $not_to_sync_items[] = array('array_index' => $sl_item_data['idx_array'], 'error_message' => $error_message);

                                }

                            } else {

                                $wp_product = find_saleslayer_product($sl_item_data['saleslayerid'], $comp_id);
                                
                                if ($wp_product) {

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

                            if ($wp_format) {

        		        		$wp_format_old_sku = $wp_format['_sku'];

        		        		$counter = 0;
        		        		sl_update_post_meta($wp_format['ID'], '_sku', $sl_item_sku);
        		        		// Guard: old SKU may not exist in $wp_posts if the variation was
        		        		// created without a SKU or was already reassigned in a previous pass.
        		        		if (isset($wp_posts[$wp_format_old_sku])) {
        		        		    $wp_post = $wp_posts[$wp_format_old_sku];
        		        		    $wp_post['sku'] = $sl_item_sku;
        		        		    unset($wp_posts[$wp_format_old_sku]);
        		        		    $wp_posts[$sl_item_sku] = $wp_post;
        		        		}
        		        	
        		        	}

                        } else {

                        	$wp_product = find_saleslayer_product($sl_item_data['saleslayerid'], $comp_id);

                            if ($wp_product) {

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

                if (!empty($sl_items_to_update)) {

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
 * Execute a SQL statement using $wpdb with optional parameters.
 * @param string $type Query type ('read'|'insert'|'update'|'delete')
 * @param string $query SQL statement (with placeholders when using $params)
 * @param array $params Parameters bound to placeholders in $query
 * @return array|int|true|false Array for reads; affected rows (int|true) for writes; false on failure
 */
/**
 * Return ALL WooCommerce products that match a Sales Layer ID + company ID.
 *
 * Unlike find_saleslayer_product() — which returns only the first match — this
 * function returns every post (all language variants) so that delete/disable
 * operations can affect ALL of them in a multilang environment.
 *
 * @param string $sl_id    Sales Layer item ID
 * @param string $comp_id  Sales Layer company ID
 * @return array[] Array of post arrays with merged meta (may be empty)
 */
function find_all_saleslayer_products(string $sl_id, string $comp_id): array
{
    $posts = get_posts([
        'post_type'      => 'product',
        'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_saleslayerid',     'value' => $sl_id,   'compare' => '='],
            ['key' => '_saleslayercompid', 'value' => $comp_id, 'compare' => '='],
        ],
    ]);

    if (is_wp_error($posts) || empty($posts)) {
        return [];
    }

    $result = [];
    foreach ($posts as $post) {
        $post_array = json_decode(json_encode($post), true);
        $result[]   = add_meta_to_post($post_array, 'ARRAY_A');
    }

    return $result;
}

/**
 * Return ALL taxonomy terms that match a Sales Layer ID + company ID.
 *
 * Unlike find_saleslayer_term() — which returns only the first match — this
 * function returns every term (all language variants) so that delete operations
 * can affect ALL of them in a multilang environment.
 *
 * @param string $taxonomy WordPress taxonomy (e.g. 'product_cat')
 * @param string $sl_id    Sales Layer item ID
 * @param string $comp_id  Sales Layer company ID
 * @return array[] Array of term arrays with merged meta (may be empty)
 */
function find_all_saleslayer_terms(string $taxonomy, string $sl_id, string $comp_id): array
{
    $terms = get_terms([
        'hide_empty' => false,
        'taxonomy'   => $taxonomy,
        'number'     => 0,
        'meta_query' => [
            'relation' => 'AND',
            ['key' => 'saleslayerid',     'value' => $sl_id,   'compare' => '='],
            ['key' => 'saleslayercompid', 'value' => $comp_id, 'compare' => '='],
        ],
    ]);

    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    $result = [];
    foreach ($terms as $term) {
        $result[] = json_decode(json_encode($term), true);
    }

    return $result;
}

/**
 * Return ALL product variations that match a Sales Layer format ID + company ID.
 *
 * Unlike find_saleslayer_format() — which returns only the first match — this
 * function returns every variation (all language variants) so that delete/disable
 * operations can affect ALL of them in a multilang environment.
 *
 * @param string $comp_id       Sales Layer company ID
 * @param string $sl_format_id  Sales Layer format ID
 * @return array[] Array of post arrays with merged meta (may be empty)
 */
function find_all_saleslayer_formats(string $comp_id, string $sl_format_id): array
{
    $posts = get_posts([
        'post_type'      => 'product_variation',
        'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            ['key' => '_saleslayercompid',   'value' => $comp_id,      'compare' => '='],
            ['key' => '_saleslayerformatid', 'value' => $sl_format_id, 'compare' => '='],
        ],
    ]);

    if (is_wp_error($posts) || empty($posts)) {
        return [];
    }

    $result = [];
    foreach ($posts as $post) {
        $post_array = json_decode(json_encode($post), true);
        $result[]   = add_meta_to_post($post_array, 'ARRAY_A');
    }

    return $result;
}

function sl_connection_query($type, $query, array $params = array())
{

    global $wpdb;

    try {
        // Prepare SQL when parameters are provided
        $safe_sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        if ($type === 'read') {
            $resultado = $wpdb->get_results($safe_sql, ARRAY_A);

            if ($resultado && strpos($query, 'sl_cuenta_registros') !== false) {
                if (isset($resultado[0])) { $resultado = $resultado[0]; }
            }
        } else {
            // write operations: insert/update/delete
            $resultado = $wpdb->query($safe_sql);

            // For delete operations, 0 affected rows still means success
            if ($type === 'delete' && $resultado === 0) { $resultado = true; }
        }
    } catch (\Exception $e) {
        if (!empty($params)) {
            sl_debug('## Error. SL SQL type: ' . $type . ' - query: ' . $query . ' - params: ' . print_r($params, true));
        } else {
            sl_debug('## Error. SL SQL type: ' . $type . ' - query: ' . $query);
        }
        sl_debug('## Error. SL SQL error message: ' . $e->getMessage());
    }

    if (!$resultado) {
        return false;
    }
    return $resultado;
}
