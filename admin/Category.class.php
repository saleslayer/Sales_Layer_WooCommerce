<?php

/**
 * Category synchronization service for Sales Layer → WooCommerce.
 *
 * Handles mapping, creation, update and deletion of product categories,
 * including image management and parent/order relationships.
 */
//http://stackoverflow.com/questions/26690047/how-to-programatically-set-the-category-for-a-new-woocommerce-product-creation

class Category
{

    public $category_id_field = 'ID';
    public $category_id_parent_field = 'ID_PARENT';
    protected $category_field_name = 'section_name';
    protected $category_field_description = 'section_description';
    protected $category_field_description_short = 'section_description_short';
    protected $category_field_image = 'section_image';
    protected $category_field_order = 'section_order';
    protected $category_images_sizes = array();

    private $sl_data_schema = array();
    public $comp_id;
    public $default_cat_id = 0;

    protected $media_class;

    protected $debug_level;

    /** Blog ID being processed in the current queue row (0 in single-site mode). */
    protected $sync_blog_id = 0;

    /** Language code being processed in the current queue row ('' in legacy mode). */
    protected $sync_lang_code = '';

    public function __construct()
    {
        global $debug_level;
        $this->debug_level = $debug_level ?? 0;
    }

    /**
     * Dynamically set class property by name.
     *
     * @param string $field_name Property name
     * @param mixed  $field_value Property value
     * @return void
     */
    public function set_class_field_value($field_name, $field_value)
    {

        $this->$field_name = $field_value;

    }

    /**
     * Build a debug context prefix for log messages.
     * Returns e.g. "Blog_id: {1} - Lang: {ES} - " when context is set,
     * or an empty string in legacy single-site / single-language mode.
     *
     * @return string
     */
    protected function getDebugContext(): string
    {
        $parts = [];
        if (!empty($this->sync_blog_id)) {
            $parts[] = 'Blog_id: {' . $this->sync_blog_id . '}';
        }
        if ($this->sync_lang_code !== '') {
            $parts[] = 'Lang: {' . strtoupper($this->sync_lang_code) . '}';
        }
        return !empty($parts) ? implode(' - ', $parts) . ' - ' : '';
    }

    /**
     * Set connector data schema (JSON string).
     * @param string $sl_data_schema Connector data schema (JSON)
     * @return void
     */
    public function set_data_schema($sl_data_schema)
    {

        $this->sl_data_schema = $sl_data_schema;

    }

    /**
     * Build category parameter list to store based on schema/language.
     * @param array $sl_language Sales Layer connector language configuration
     * @return array Category params to persist
     */
    public function getCategoryParamsToStore($sl_language)
    {

        $category_params = $category_images_sizes = [];

        $data_schema = json_decode($this->sl_data_schema, true);
        $schema = $data_schema['catalogue'];

        if (!empty($schema['fields'][$this->category_field_image]['image_sizes'])) {
            $category_field_images_sizes = $schema['fields'][$this->category_field_image]['image_sizes'];
            $ordered_image_sizes = order_array_img($category_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                $category_images_sizes[] = $img_size;
            }
        } elseif (!empty($schema['fields']['image_sizes'])) {
            $category_field_images_sizes = $schema['fields']['image_sizes'];
            $ordered_image_sizes = order_array_img($category_field_images_sizes);

            foreach ($ordered_image_sizes as $img_size => $img_dimensions) {
                $category_images_sizes[] = $img_size;
            }
        } else {
            $category_images_sizes = ['IMD', 'THM', 'TH'];
        }

        $category_params['category_fields']['category_images_sizes'] = $category_images_sizes;

        $field_names = [
            'category_field_name',
            'category_field_description',
            'category_field_order',
            'category_field_image',
        ];

        foreach ($field_names as $field_name) {
            // Use a local variable to avoid mutating the instance property.
            // This allows getCategoryParamsToStore() to be called multiple times
            // with different languages on the same instance (required for multilang queuing).
            $resolved = $this->$field_name;
            if (
                isset($schema['fields'][$resolved])
                && $schema['fields'][$resolved]['has_multilingual']
            ) {
                $resolved .= '_' . $sl_language;
            }

            $category_params['category_fields'][$field_name] = $resolved;
        }

        return $category_params;

    }

    /**
     * Prepare categories payload to be stored (reorganize + normalize).
     * @param array $category_data Categories data received from connector
     * @return array Categories data ready to persist
     */
    public function prepareCategoryDataToStore($category_data)
    {

        if (!empty($category_data)) {
            $time_ini_reorganize_categories = microtime(true);
            $category_data = $this->reorganize_categories($category_data);
            sl_debug(
                '### reorganize_categories: ' . (microtime(true) - $time_ini_reorganize_categories) . ' seconds.'
            );
        }

        return $category_data;

    }

    /**
     * Synchronize a stored category with WooCommerce.
     * @param array $category Category to synchronize
     * @return string Result code (e.g., 'item_updated', 'item_not_updated')
     */
    public function sync_stored_category($category)
    {

        $time_ini_category_core_data = microtime(true);

        $sl_category_id        = $category[$this->category_id_field];
        $sl_category_parent_id = $category[$this->category_id_parent_field];
        $category_data         = $category['data'];

        // In multilang mode each queue row carries '_sl_language' (WP/Polylang lang code).
        // Empty string means legacy single-language mode — behaviour is unchanged.
        $lang = $category['_sl_language'] ?? '';

        if ($sl_category_parent_id != '0') {
            // In multilang mode, find the parent category for this specific language.
            $wp_parent_category = find_saleslayer_term('product_cat', $sl_category_parent_id, $this->comp_id, $lang);

            if (!$wp_parent_category) {
                sl_debug(
                    '## Error. ' . $this->getDebugContext()
                    . 'SL ID: ' . $sl_category_id . ' : ' . $category_data[$this->category_field_name]
                    . ' - Error creating the category, category parent not found.'
                );

                return 'item_not_updated';
            }

            $category_parent_id = $wp_parent_category['term_id'];
        } else {
            $category_parent_id = $this->default_cat_id;
        }

        // Find the specific language variant of this category.
        $wp_category = find_saleslayer_term('product_cat', $sl_category_id, $this->comp_id, $lang);

        if (!$wp_category) {
            // Name-based fallback only makes sense in legacy mode.
            // In multilang mode it would match the wrong language variant (same name).
            if ($lang === '') {
                $wp_category = $this->find_category_by_name(
                    $category_data[$this->category_field_name],
                    $sl_category_id,
                    $this->comp_id
                );
            }

            if (!$wp_category) {
                $time_ini_create_category = microtime(true);
                // Pass $lang so create_category() can set slyr_wc_lang meta immediately
                // and handle the slug-disambiguation when term_exists is returned.
                $this->create_category($sl_category_id, $this->comp_id, $category_parent_id, $category_data, $lang);
                sl_debug(
                    '## time_create_category: ' . (microtime(true) - $time_ini_create_category) . ' seconds.',
                    'timer'
                );
            }

            $wp_category = find_saleslayer_term('product_cat', $sl_category_id, $this->comp_id, $lang);

            if (!$wp_category) {
                sl_debug(
                    '## Error. ' . $this->getDebugContext()
                    . 'SL ID: ' . $sl_category_id . ' : ' . $category_data[$this->category_field_name]
                    . ' - Error while creating the category.'
                );

                return 'item_not_updated';
            }
        }

        if ($this->debug_level) {
            sl_debug(" > Updating category ID: $sl_category_id (parent: $sl_category_parent_id)");
        }

        if ($this->debug_level > 1) {
            sl_debug(" Name ({$this->category_field_name}): " . $category_data[$this->category_field_name]);
        }

        $category_modified = false;
        $category_data_modified = array();

        if ($wp_category['name'] != $category_data[$this->category_field_name]) {
            $category_data_modified['name'] = $category_data[$this->category_field_name];
            $category_data_modified['slug'] = sanitize_title($category_data[$this->category_field_name]);
            $category_modified = true;
        }

        if (wp_specialchars_decode($wp_category['description']) != $category_data[$this->category_field_description]) {
            $category_data_modified['description'] = $category_data[$this->category_field_description];
            $category_modified = true;
        };

        if ($wp_category['parent'] != $category_parent_id) {
            $category_data_modified['parent'] = $category_parent_id;
            $category_modified = true;
        }

        if (
            isset($category_data[$this->category_field_order])
            && (!isset($wp_category['order'])
                || (isset($wp_category['order'])
                    && $wp_category['order'] != $category_data[$this->category_field_order]))
        ) {
            sl_update_woocommerce_term_meta(
                $wp_category['term_id'],
                'order',
                $category_data[$this->category_field_order]
            );
        };

        sl_debug(
            '## time_category_core_data: ' . (microtime(true) - $time_ini_category_core_data) . ' seconds.',
            'timer'
        );

        $time_ini_category_images = microtime(true);

        if (!empty($category_data[$this->category_field_image])) {
            if (is_null($this->media_class)) {
                $this->media_class = Media_class::get_instance();
            }

            $sl_category_images = $category_data[$this->category_field_image];

            if (count($sl_category_images) > 0) {
                $wp_thumbnail_id = $wp_category_image_name = $wp_category_image_size = '';

                if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
                    $wp_thumbnail_id = get_term_meta($wp_category['term_id'], 'thumbnail_id', true);
                } else {
                    $wp_thumbnail_id = get_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', true);
                }

                if (!in_array($wp_thumbnail_id, array('', 0, null, false))) {
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
                            if (!$filesize_image) {
                                continue;
                            }

                            $parse_url_image = pathinfo($image_url);
                            $parse_url_image_basename = urldecode($parse_url_image['basename']);

                            if ($parse_url_image_basename == $wp_category_image_name) {
                                if (
                                    !$wp_category_image_size
                                    || ($wp_category_image_size !== false && $wp_category_image_size !== $filesize_image)
                                ) {
                                    if (!$this->media_class->update_media($image_url, $wp_thumbnail_id, true)) {
                                        continue;
                                    }
                                }
                            } else {
                                $thumb_id = $this->media_class->get_thumbnail_id_by_title($parse_url_image_basename);

                                if ($thumb_id === 0) {
                                    $new_wp_thumbnail_id = $this->media_class->fetch_media(
                                        $image_url,
                                        $wp_category['term_id'],
                                        true
                                    );
                                    sl_update_woocommerce_term_meta(
                                        $wp_category['term_id'],
                                        'thumbnail_id',
                                        $new_wp_thumbnail_id
                                    );
                                } else {
                                    $wp_category_image_url = wp_get_attachment_url($thumb_id);
                                    $wp_category_image_size = $this->media_class->read_image_file_size($wp_category_image_url);

                                    if (
                                        !$wp_category_image_size
                                        || ($wp_category_image_size !== false
                                            && $wp_category_image_size !== $filesize_image)
                                    ) {
                                        if (!$this->media_class->update_media($image_url, $thumb_id, true)) {
                                            continue;
                                        }
                                    }

                                    sl_update_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', $thumb_id);
                                }

                                if (!in_array($wp_thumbnail_id, array('', 0, null, false))) {
                                    $this->media_class->delete_media($wp_thumbnail_id);
                                }
                            }

                            break 2;
                        }
                    }
                }
            }
        }

        sl_debug(
            '## time_category_images: ' . (microtime(true) - $time_ini_category_images) . ' seconds.',
            'timer'
        );

        $time_ini_category_save = microtime(true);

        if ($category_modified) {
            try {
                $resultado = wp_update_term($wp_category['term_id'], 'product_cat', $category_data_modified);

                if (is_wp_error($resultado)) {
                    sl_debug('## Error. ' . $this->getDebugContext() . 'sync_category category_modified: ' . $resultado->get_error_message());
                }

                if ($this->debug_level) {
                    sl_debug('Category updated!');
                }
            } catch (\Exception $e) {
                if ($this->debug_level) {
                    sl_debug(
                        '## Error. ' . $this->getDebugContext()
                        . 'SL ID: ' . $sl_category_id . ' : ' . $category_data[$this->category_field_name]
                        . ' - ' . $e->getMessage()
                    );
                }

                return 'item_not_updated';
            }
        }

        sl_debug(
            '## time_category_save: ' . (microtime(true) - $time_ini_category_save) . ' seconds.',
            'timer'
        );

        // Multilang: assign the Polylang language and link all existing language variants
        // as a Polylang translation group. Idempotent — safe to call on every sync.
        if ($lang !== '' && class_exists('MultilangHelper')) {
            $multilangHelper = new MultilangHelper();
            $multilangHelper->set_category_language($wp_category['term_id'], $lang);
            sl_update_woocommerce_term_meta($wp_category['term_id'], 'slyr_wc_lang', $lang);
            $multilangHelper->link_all_term_translations('product_cat', $sl_category_id, $this->comp_id);
        }

        return 'item_updated';

    }

    /**
     * Create a WooCommerce category and assign Sales Layer metadata.
     *
     * @param string $sl_category_id Sales Layer id
     * @param string $comp_id Sales Layer company id
     * @param string $category_parent_id Parent category term id
     * @param array  $sl_category_data Category data
     * @return bool True on success, false otherwise
     */
    public function create_category($sl_category_id, $comp_id, $category_parent_id, $sl_category_data, string $lang = '')
    {
        $category_name = $sl_category_data[$this->category_field_name];
        $base_slug     = sanitize_title($category_name);

        $category = wp_insert_term(
            $category_name,
            'product_cat',
            array(
                'description' => $sl_category_data[$this->category_field_description],
                'parent'      => $category_parent_id,
                'slug'        => $base_slug,
            )
        );

        // In multilang mode, two language variants can share the same category name
        // (e.g. "Sport" in ES and "Sport" in EN). WordPress enforces term-name uniqueness
        // within a taxonomy, so the second insert returns WP_Error: term_exists.
        // We retry with a language-disambiguated slug (e.g. 'sport-en') to let both
        // coexist. The visible name stays identical; only the internal slug differs.
        if (is_wp_error($category) && $lang !== '' && $category->get_error_code() === 'term_exists') {
            $lang_slug = $base_slug . '-' . $lang;
            $category  = wp_insert_term(
                $category_name,
                'product_cat',
                array(
                    'description' => $sl_category_data[$this->category_field_description],
                    'parent'      => $category_parent_id,
                    'slug'        => $lang_slug,
                )
            );
        }

        if (!is_wp_error($category)) {
            if (is_object($category)) {
                $category_id = isset($category->term_id) ? $category->term_id : 0;
            } else {
                $category_id = isset($category['term_id']) ? $category['term_id'] : 0;
            }

            if ($category_id) {
                sl_update_woocommerce_term_meta($category_id, 'saleslayerid', $sl_category_id);
                sl_update_woocommerce_term_meta($category_id, 'saleslayercompid', $comp_id);

                // Tag the term with its language variant for lang-aware queries.
                if ($lang !== '') {
                    sl_update_woocommerce_term_meta($category_id, 'slyr_wc_lang', $lang);
                }

                if ($this->debug_level) {
                    sl_debug('Category created!');
                }

                return true;
            }
        } else {
            sl_debug('## Error. ' . $this->getDebugContext() . 'create_category: ' . $category->get_error_message());
        }

        return false;
    }

    /**
     * Find an unassigned WooCommerce category by name and assign Sales Layer metadata.
     *
     * @param string $category_name Category name
     * @param string $category_id Sales Layer id
     * @param string $comp_id Sales Layer company id
     * @return bool True if found and assigned, false otherwise
     */
    public function find_category_by_name($category_name, $category_id, $comp_id)
    {
        $wp_category = sl_find_unassigned_product_cat_terms_by_name($category_name, ARRAY_A);

        if (!empty($wp_category)) {
            sl_update_woocommerce_term_meta($wp_category['term_id'], 'saleslayerid', $category_id);
            sl_update_woocommerce_term_meta($wp_category['term_id'], 'saleslayercompid', $comp_id);

            return true;
        }

        return false;
    }

    /**
     * Delete a stored category (and its thumbnail attachment if present).
     *
     * @param array $category_to_delete SL category id to delete
     * @return string Result of delete
     */
    public function delete_stored_category($category_to_delete)
    {
        sl_debug('Deleting category with SL id: ' . $category_to_delete . ' comp_id: ' . $this->comp_id);

        // In multilang mode there is one WP term per language variant for each SL category.
        // find_all_saleslayer_terms() returns ALL of them (no lang filter) so that every
        // variant is permanently deleted in a single operation.
        // In legacy mode (no multilang) it also returns the one existing term, so the
        // behaviour is identical to the previous single-find approach.
        $wp_categories = find_all_saleslayer_terms('product_cat', $category_to_delete, $this->comp_id);

        if (empty($wp_categories)) {
            sl_debug('## Error. ' . $this->getDebugContext() . 'The category with id: ' . $category_to_delete . ' does not exist.');

            return 'item_not_deleted';
        }

        foreach ($wp_categories as $wp_category) {
            if (SLYR_WP_DEPRECATE_WOOCOMMERCE_TERM_META) {
                $wp_thumbnail_id = get_term_meta($wp_category['term_id'], 'thumbnail_id', true);
            } else {
                $wp_thumbnail_id = get_woocommerce_term_meta($wp_category['term_id'], 'thumbnail_id', true);
            }

            if (wp_delete_term($wp_category['term_id'], 'product_cat')) {
                if ($wp_thumbnail_id != '') {
                    if (is_null($this->media_class)) {
                        $this->media_class = Media_class::get_instance();
                    }

                    if (!in_array($wp_thumbnail_id, array('', 0, null, false))) {
                        $this->media_class->delete_media($wp_thumbnail_id);
                    }
                }
            }
        }

        return 'item_deleted';
    }

    /**
     * Reorganize categories by parent relations.
     *
     * @param array $categories Categories data
     * @return array Reorganized data
     */
    private function reorganize_categories($categories)
    {
        $new_categories = array();

        if (count($categories) > 0) {
            $counter = 0;
            $first_level = true;
            $first_clean = true;
            $categories_loaded = array();

            do {
                $level_categories = $this->get_level_categories($categories, $categories_loaded, $first_level);

                if (!empty($level_categories)) {
                    $counter = 0;
                    $first_level = false;

                    foreach ($categories as $keyCat => $category) {
                        if (isset($level_categories[$category[$this->category_id_field]])) {
                            array_push($new_categories, $category);
                            $categories_loaded[$category[$this->category_id_field]] = 0;
                            unset($categories[$keyCat]);
                        }
                    }
                } else {
                    $counter++;
                }

                if ($counter == 3) {
                    if ($first_clean && !empty($categories)) {
                        $categories_not_loaded_ids = array_flip(array_column($categories, $this->category_id_field));

                        foreach ($categories as $keyCat => $category) {
                            if (!is_array($category[$this->category_id_parent_field])) {
                                $category_parent_ids = array($category[$this->category_id_parent_field]);
                            } else {
                                $category_parent_ids = array($category[$this->category_id_parent_field]);
                            }

                            $has_any_parent = false;

                            foreach ($category_parent_ids as $category_parent_id) {
                                if (isset($categories_not_loaded_ids[$category_parent_id])) {
                                    $has_any_parent = true;
                                    break;
                                }
                            }

                            if (!$has_any_parent) {
                                $category[$this->category_id_parent_field] = 0;

                                array_push($new_categories, $category);
                                $categories_loaded[$category[$this->category_id_field]] = 0;
                                unset($categories[$keyCat]);

                                $counter = 0;
                                $first_clean = false;
                                $first_level = false;
                            }
                        }
                    } else {
                        break;
                    }
                }
            } while (count($categories) > 0);
        }

        return $new_categories;
    }

    /**
     * Get categories for the current root/level.
     *
     * @param array $categories Categories data
     * @param array $categories_loaded Loaded category ids map
     * @param bool  $first Whether we are selecting the first/root level
     * @return array Categories that belong to that level
     */
    private function get_level_categories($categories, $categories_loaded, bool $first = false)
    {
        $level_categories = array();

        if ($first) {
            foreach ($categories as $category) {
                if (!is_array($category[$this->category_id_parent_field]) && $category[$this->category_id_parent_field] == 0) {
                    $level_categories[$category[$this->category_id_field]] = 0;
                }
            }
        } else {
            foreach ($categories as $category) {
                if (!is_array($category[$this->category_id_parent_field])) {
                    $category_parent_ids = array($category[$this->category_id_parent_field]);
                } else {
                    $category_parent_ids = array($category[$this->category_id_parent_field]);
                }

                $parents_loaded = true;

                foreach ($category_parent_ids as $category_parent_id) {
                    if (!isset($categories_loaded[$category_parent_id])) {
                        $parents_loaded = false;
                        break;
                    }
                }

                if ($parents_loaded) {
                    $level_categories[$category[$this->category_id_field]] = 0;
                }
            }
        }

        return $level_categories;
    }

}