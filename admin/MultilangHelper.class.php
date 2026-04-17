<?php

/**
 * Multiidioma Integration Helper - Agnóstica de Plugin
 *
 * Proporciona métodos auxiliares para interactuar con cualquier plugin de multiidioma:
 * - Listar idiomas disponibles (Polylang, WPML, etc.)
 * - Mapear idiomas de Sales Layer a idiomas del plugin
 * - Crear/actualizar traducciones de productos
 * - Sincronizar términos (categorías) multiidioma
 */
class MultilangHelper
{
    private static $instance;

    public function __construct()
    {
        // Debe haber al menos un plugin de multiidioma activo
        $active = slyr_detect_active_multilang_plugin();
        if (!$active) {
            throw new Exception('No multilang plugin detected (Polylang, etc.)');
        }
    }

    public static function &get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new MultilangHelper();
        }
        return self::$instance;
    }

    /**
     * Obtener lista de idiomas del plugin de multiidioma activo (agnóstica)
     *
     * @return array Ej: [
     *     ['code' => 'en', 'name' => 'English'],
     *     ['code' => 'es', 'name' => 'Español']
     * ]
     */
    public function get_multilang_languages()
    {
        $languages = [];
        $plugin_type = slyr_detect_active_multilang_plugin();
        
        if ($plugin_type === 'polylang') {
            // Use Polylang's language terms directly from taxonomy
            // This is more reliable than using potentially unavailable functions like pll_get_language()
            $lang_terms = get_terms([
                'taxonomy'   => 'language',
                'hide_empty' => false,
                'orderby'    => 'name',
            ]);

            if (is_wp_error($lang_terms)) {
                // Log the error
                error_log('Polylang get_terms error: ' . $lang_terms->get_error_message());
                return $languages;
            }

            foreach ($lang_terms as $term) {
                // Polylang stores language data in term meta
                $locale = get_term_meta($term->term_id, 'pll_locale', true);
                
                $languages[] = [
                    'code' => $term->slug,  // Language code (en, es, fr)
                    'name' => $term->name   // Language name (English, Español, Français)
                ];
            }
        }
        // Agregar soporte para WPML aquí
        // elseif ($plugin_type === 'wpml') { ... }

        return $languages;
    }

    /**
     * Obtener el idioma por defecto del plugin de multiidioma
     *
     * @return string|null Código del idioma por defecto (ej: 'es', 'en'), o null si no hay
     */
    public function get_default_language()
    {
        $plugin_type = slyr_detect_active_multilang_plugin();
        sl_debug('get_default_language() - plugin_type: '.print_r($plugin_type,1));
        
        if ($plugin_type === 'polylang') {
            // Try multiple ways to get Polylang's default language
            
            // Method 1: Using Polylang function (if available)
            if (function_exists('pll_default_language')) {
                $default_lang = pll_default_language();
                sl_debug('get_default_language() - default_lang: '.print_r($default_lang,1));
                if (!empty($default_lang)) {
                    return $default_lang;
                }
            }
            
            // Method 2: Get from all languages and find the default flag
            if (function_exists('pll_languages_list')) {
                $all_languages = pll_languages_list(['fields' => 'slug']);
                sl_debug('get_default_language() - all_languages: '.print_r($all_languages,1));
                if (!empty($all_languages)) {
                    // The first language is typically the default
                    return $all_languages[0];
                }
            }
            
            // Method 3: Query the language terms directly
            $lang_terms = get_terms([
                'taxonomy'   => 'language',
                'hide_empty' => false,
                'orderby'    => 'term_id',  // Polylang orders by ID, first is default
                'order'      => 'ASC',
                'number'     => 1,
            ]);
            sl_debug('get_default_language() - lang_terms: '.print_r($lang_terms,1));

            if (!is_wp_error($lang_terms) && !empty($lang_terms)) {
                return $lang_terms[0]->slug;
            }
            
            // Method 4: Try the option
            $default_lang = get_option('pll_default_lang');
            sl_debug('get_default_language() - default_lang: '.print_r($default_lang,1));
            if (!empty($default_lang)) {
                return $default_lang;
            }
        }/* elseif ($plugin_type === 'wpml') {
            // WPML stores default language in ICL_LANGUAGE_CODE constant or wpml_default_language option
            if (defined('ICL_LANGUAGE_CODE')) {
                return ICL_LANGUAGE_CODE;
            }
            $default_lang = get_option('wpml_default_language');
            if (!empty($default_lang)) {
                return $default_lang;
            }
        }*/
        
        return null;
    }

    /**
     * Asignar idioma a un producto (post) - agnóstica
     *
     * @param int $post_id ID del producto
     * @param string $language Código de idioma (en, es, fr)
     * @param string $plugin_type Tipo de plugin ('polylang', 'wpml', etc.)
     * @return bool
     */
    public function set_product_language($post_id, $language, $plugin_type = null)
    {
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }
        
        if ($plugin_type === 'polylang') {
            if (!function_exists('pll_set_post_language')) {
                return false;
            }
            pll_set_post_language($post_id, $language);
        }
        // Agregar soporte para otros plugins aquí
        
        return true;
    }

    /**
     * Obtener traducción existente de un producto - agnóstica
     *
     * @param int $post_id ID del producto
     * @param string $language Código de idioma objetivo
     * @param string $plugin_type Tipo de plugin ('polylang', 'wpml', etc.)
     * @return int|null ID del post traducido, o null si no existe
     */
    public function get_product_translation($post_id, $language, $plugin_type = null)
    {
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }
        
        if ($plugin_type === 'polylang') {
            if (!function_exists('pll_get_post')) {
                return null;
            }
            return pll_get_post($post_id, $language);
        }
        // Agregar soporte para otros plugins aquí
        
        return null;
    }

    /**
     * Crear traducción vinculada de un producto
     *
     * @param int $original_post_id ID del producto original
     * @param int $translated_post_id ID del producto traducido
     * @param string $original_lang Idioma del original (en, es)
     * @param string $translated_lang Idioma de la traducción
     * @param string $plugin_type Tipo de plugin ('polylang', 'wpml', etc.)
     * @return bool
     */
    public function link_product_translations(
        $original_post_id,
        $translated_post_id,
        $original_lang,
        $translated_lang,
        $plugin_type = null
    ) {
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }
        
        if ($plugin_type === 'polylang') {
            if (!function_exists('pll_save_post_translations')) {
                return false;
            }

            $translations = [
                $original_lang => $original_post_id,
                $translated_lang => $translated_post_id
            ];

            pll_save_post_translations($translations);
        }
        // Agregar soporte para otros plugins aquí
        
        return true;
    }

    /**
     * Sincronizar categoría multiidioma
     *
     * @param int $term_id ID de la categoría (término)
     * @param string $language Código de idioma
     * @param string $plugin_type Tipo de plugin ('polylang', 'wpml', etc.)
     * @return bool
     */
    public function set_category_language($term_id, $language, $plugin_type = null)
    {
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }
        
        if ($plugin_type === 'polylang') {
            if (!function_exists('pll_set_term_language')) {
                return false;
            }
            pll_set_term_language($term_id, $language);
        }
        // Agregar soporte para otros plugins aquí
        
        return true;
    }

    /**
     * Find a product by its Sales Layer identifiers and the language variant.
     *
     * Uses the _slyr_wc_lang post meta set by the sync process to identify
     * which language variant of an SL item corresponds to a given WP product.
     * Mirrors the return format of find_saleslayer_product() (ARRAY_A with meta merged).
     *
     * @param string $sl_id   Sales Layer product ID
     * @param string $comp_id Sales Layer company ID
     * @param string $lang    Language code (e.g. 'es', 'en')
     * @return array|false    Post with meta as associative array, or false if not found
     */
    public function find_product_by_sl_id_and_lang(string $sl_id, string $comp_id, string $lang)
    {
        $posts = get_posts([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_saleslayerid',     'value' => $sl_id,   'compare' => '='],
                ['key' => '_saleslayercompid', 'value' => $comp_id, 'compare' => '='],
                ['key' => '_slyr_wc_lang',     'value' => $lang,    'compare' => '='],
            ],
        ]);

        if (is_wp_error($posts) || empty($posts)) {
            return false;
        }

        $wp_post = json_decode(json_encode($posts[0]), true);

        return add_meta_to_post($wp_post, 'ARRAY_A');
    }

    /**
     * Find a product format (variation) by its Sales Layer identifiers and the language variant.
     *
     * Uses the _slyr_wc_lang post meta set by the sync process to identify
     * which language variant of an SL format corresponds to a given WP product variation.
     * Mirrors the return format of find_saleslayer_format() (ARRAY_A with meta merged).
     *
     * @param string $sl_format_id Sales Layer format ID
     * @param string $comp_id      Sales Layer company ID
     * @param string $lang         Language code (e.g. 'es', 'en')
     * @return array|false         Post with meta as associative array, or false if not found
     */
    public function find_format_by_sl_id_and_lang(string $sl_format_id, string $comp_id, string $lang)
    {
        $posts = get_posts([
            'post_type'      => 'product_variation',
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_saleslayerformatid', 'value' => $sl_format_id, 'compare' => '='],
                ['key' => '_saleslayercompid',   'value' => $comp_id,      'compare' => '='],
                ['key' => '_slyr_wc_lang',        'value' => $lang,         'compare' => '='],
            ],
        ]);

        if (is_wp_error($posts) || empty($posts)) {
            return false;
        }

        $wp_post = json_decode(json_encode($posts[0]), true);

        return add_meta_to_post($wp_post, 'ARRAY_A');
    }

    /**
     * Link all language variants of an SL product as Polylang/WPML translations.
     *
     * Replaces link_product_translations() which only handles pairs (2 products).
     * This method handles N languages (all variants of one SL item) in a single call.
     *
     * @param array       $translations Associative array of language code => WP post ID
     *                                  e.g. ['es' => 42, 'en' => 43, 'de' => 44]
     * @param string|null $plugin_type  Override plugin type detection (optional)
     * @return bool
     */
    public function save_translation_group(array $translations, ?string $plugin_type = null): bool
    {
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }

        if ($plugin_type === 'polylang') {
            if (!function_exists('pll_save_post_translations')) {
                return false;
            }
            pll_save_post_translations($translations);
            return true;
        }

        // Placeholder for WPML support
        // elseif ($plugin_type === 'wpml') { ... }

        return false;
    }

    /**
     * Find a term (category) by its Sales Layer identifiers and the language variant.
     *
     * Uses the slyr_wc_lang term meta set by the sync process to identify
     * which language variant of an SL category corresponds to a given WP term.
     * Mirrors the return format of find_saleslayer_term() (array with meta merged).
     *
     * Note: term meta keys use no underscore prefix (saleslayerid, saleslayercompid),
     * following the existing convention used in Category.class.php.
     *
     * @param string $taxonomy WP taxonomy (e.g. 'product_cat')
     * @param string $sl_id    Sales Layer category ID
     * @param string $comp_id  Sales Layer company ID
     * @param string $lang     Language code (e.g. 'es', 'en')
     * @return array|false     Term with meta as associative array, or false if not found
     */
    public function find_term_by_sl_id_and_lang(string $taxonomy, string $sl_id, string $comp_id, string $lang)
    {
        $terms = get_terms([
            'hide_empty' => false,
            'taxonomy'   => $taxonomy,
            'number'     => 1,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'saleslayerid',    'value' => $sl_id,   'compare' => '='],
                ['key' => 'saleslayercompid','value' => $comp_id, 'compare' => '='],
                ['key' => 'slyr_wc_lang',    'value' => $lang,    'compare' => '='],
            ],
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return false;
        }

        $term      = json_decode(json_encode($terms[0]), true);
        $term_meta = get_term_meta($term['term_id'], '', true);

        if (!empty($term_meta)) {
            foreach ($term_meta as $term_meta_field => $term_meta_value) {
                if (is_array($term_meta_value) && count($term_meta_value) === 1) {
                    $term[$term_meta_field] = $term_meta_value[0];
                } else {
                    $term[$term_meta_field] = $term_meta_value;
                }
            }
        }

        return $term;
    }

    /**
     * Link all language variants of an SL category as Polylang/WPML translations.
     *
     * @param array       $translations Associative array of language code => WP term ID
     *                                  e.g. ['es' => 12, 'en' => 13, 'de' => 14]
     * @param string|null $plugin_type  Override plugin type detection (optional)
     * @return bool
     */
    public function save_term_translation_group(array $translations, ?string $plugin_type = null): bool
    {
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }

        if ($plugin_type === 'polylang') {
            if (!function_exists('pll_save_term_translations')) {
                return false;
            }
            pll_save_term_translations($translations);
            return true;
        }

        // Placeholder for WPML support
        // elseif ($plugin_type === 'wpml') { ... }

        return false;
    }

    /**
     * Validar que una configuración de mapeo de idiomas es válida - agnóstica
     *
     * @param array $language_mappings Ej: ['en' => 'en', 'es' => 'es']
     * @param array $sl_languages Idiomas disponibles de Sales Layer
     * @param string $plugin_type Tipo de plugin ('polylang', 'wpml', etc.)
     * @return array ['valid' => bool, 'errors' => []]
     */
    public function validate_language_mappings($language_mappings, $sl_languages, $plugin_type = null)
    {
        $errors = [];
        
        if (!$plugin_type) {
            $plugin_type = slyr_detect_active_multilang_plugin();
        }
        
        if ($plugin_type === 'polylang') {
            $plugin_langs = pll_languages_list(['fields' => 'locale']);
        }
        // Agregar soporte para otros plugins aquí
        // elseif ($plugin_type === 'wpml') { ... }
        else {
            $plugin_langs = [];
        }

        // Validar cada mapeo: Idioma de SL → Idioma del plugin
        foreach ($language_mappings as $sl_lang => $plugin_lang) {
            if (!in_array($sl_lang, $sl_languages)) {
                $errors[] = "Sales Layer language '{$sl_lang}' not available";
            }
            
            if (!in_array($plugin_lang, $plugin_langs)) {
                $errors[] = "{$plugin_type} language '{$plugin_lang}' not configured";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Find all language variants of a post-based item (product / product_variation)
     * by their Sales Layer identifiers and link them as a Polylang translation group.
     *
     * This is idempotent: calling it after every language variant sync is safe.
     * Nothing is done if fewer than 2 variants with _slyr_wc_lang exist yet.
     *
     * @param string   $sl_id      Sales Layer item ID
     * @param string   $comp_id    Sales Layer company ID
     * @param string[] $post_types WordPress post types to search (default: ['product'])
     * @return bool True if the translation group was saved, false otherwise
     */
    public function link_all_post_translations(string $sl_id, string $comp_id, array $post_types = ['product']): bool
    {
        $posts = get_posts([
            'post_type'      => $post_types,
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_saleslayerid',     'value' => $sl_id,   'compare' => '='],
                ['key' => '_saleslayercompid', 'value' => $comp_id, 'compare' => '='],
                ['key' => '_slyr_wc_lang',     'compare' => 'EXISTS'],
            ],
        ]);

        if (count($posts) <= 1) {
            return false;
        }

        $translations = [];
        foreach ($posts as $post) {
            $lang = get_post_meta($post->ID, '_slyr_wc_lang', true);
            if ($lang) {
                $translations[$lang] = $post->ID;
            }
        }

        return count($translations) > 1 ? $this->save_translation_group($translations) : false;
    }

    /**
     * Find all language variants of a term-based item (e.g. product_cat)
     * by their Sales Layer identifiers and link them as a Polylang translation group.
     *
     * This is idempotent: calling it after every language variant sync is safe.
     * Nothing is done if fewer than 2 variants with slyr_wc_lang exist yet.
     *
     * @param string $taxonomy  WordPress taxonomy (e.g. 'product_cat')
     * @param string $sl_id     Sales Layer item ID
     * @param string $comp_id   Sales Layer company ID
     * @return bool True if the translation group was saved, false otherwise
     */
    public function link_all_term_translations(string $taxonomy, string $sl_id, string $comp_id): bool
    {
        $terms = get_terms([
            'hide_empty' => false,
            'taxonomy'   => $taxonomy,
            'number'     => 0,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'saleslayerid',    'value' => $sl_id,   'compare' => '='],
                ['key' => 'saleslayercompid','value' => $comp_id, 'compare' => '='],
                ['key' => 'slyr_wc_lang',    'compare' => 'EXISTS'],
            ],
        ]);

        if (is_wp_error($terms) || count($terms) <= 1) {
            return false;
        }

        $translations = [];
        foreach ($terms as $term) {
            $lang = get_term_meta($term->term_id, 'slyr_wc_lang', true);
            if ($lang) {
                $translations[$lang] = $term->term_id;
            }
        }

        return count($translations) > 1 ? $this->save_term_translation_group($translations) : false;
    }
}

/**
 * Helper function: Detectar qué plugin de multiidioma está activo
 *
 * @return string|false 'polylang', 'wpml', o false si ninguno
 */
function slyr_detect_active_multilang_plugin()
{
    if (function_exists('pll_languages_list')) {
        return 'polylang';
    }
    
    // if (defined('ICL_LANGUAGE_CODE')) {
    //     return 'wpml';
    // }
    
    return false;
}
