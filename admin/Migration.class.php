<?php
declare(strict_types=1);

/**
 * Migration class for converting legacy products/categories/variations to multilang architecture.
 *
 * When the plugin is updated with multilang support, existing items may lack the new
 * _slyr_wc_lang / slyr_wc_lang meta keys. This class provides a one-click migration
 * that assigns the default language and links variants as Polylang translation groups.
 *
 * @package SalesLayer_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

class Migration
{
    /**
     * Execute migration of all legacy products, categories, and variations.
     *
     * Finds all items with _saleslayerid but without _slyr_wc_lang, assigns them
     * the site's default language, and links variants as Polylang translations.
     *
     * Migration order: categories → products → variations
     *
     * @return array ['categories' => int, 'products' => int, 'variations' => int, 'timestamp' => string]
     */
    public function migrate_legacy_items(): array
    {
        $multilang_active = function_exists('slyr_detect_active_multilang_plugin')
            && slyr_detect_active_multilang_plugin();

        $default_lang = $this->get_default_language();

        $counts = [
            'categories' => 0,
            'products'   => 0,
            'variations' => 0,
        ];

        // Migrate categories first
        $counts['categories'] = $this->migrate_categories($default_lang, $multilang_active);

        // Then migrate products
        $counts['products'] = $this->migrate_products($default_lang, $multilang_active);

        // Finally migrate variations
        $counts['variations'] = $this->migrate_variations($default_lang, $multilang_active);

        // Store timestamp of migration
        $counts['timestamp'] = current_time('mysql');

        // Log summary
        $message = sprintf(
            'Migration completed: %d categories, %d products, %d variations at %s',
            $counts['categories'],
            $counts['products'],
            $counts['variations'],
            $counts['timestamp']
        );
        sl_debug($message);

        return $counts;
    }

    /**
     * Get site's default language for multilang setup.
     *
     * @return string Language code (e.g. 'en', 'es', 'de')
     */
    private function get_default_language(): string
    {
        // If Polylang active, use its default language
        if (function_exists('pll_default_language')) {
            $pll_lang = pll_default_language();
            if ($pll_lang) {
                return $pll_lang;
            }
        }

        // Fallback: WordPress site language
        $wp_lang = get_bloginfo('language'); // e.g., 'en-US'
        if ($wp_lang && strpos($wp_lang, '-') !== false) {
            return substr($wp_lang, 0, 2); // Extract 'en' from 'en-US'
        }

        return $wp_lang ?: 'en';
    }

    /**
     * Migrate legacy products to multilang architecture.
     *
     * @param string $default_lang Default language code
     * @param bool   $multilang_active Whether a multilang plugin is active
     * @return int Number of products migrated
     */
    private function migrate_products(string $default_lang, bool $multilang_active): int
    {
        $products = get_posts([
            'post_type'      => 'product',
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_saleslayerid', 'compare' => 'EXISTS'],
                ['key' => '_slyr_wc_lang', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        $count = 0;
        foreach ($products as $product) {
            update_post_meta($product->ID, '_slyr_wc_lang', $default_lang);
            $count++;

            // Link all variants as Polylang translation group if multilang is active
            if ($multilang_active) {
                $sl_id = get_post_meta($product->ID, '_saleslayerid', true);
                $comp_id = get_post_meta($product->ID, '_saleslayercompid', true);

                if ($sl_id && $comp_id && class_exists('MultilangHelper')) {
                    $helper = new MultilangHelper();
                    $helper->link_all_post_translations($sl_id, $comp_id);
                }
            }
        }

        if ($count > 0) {
            sl_debug("Migrated $count products to multilang architecture");
        }

        return $count;
    }

    /**
     * Migrate legacy categories to multilang architecture.
     *
     * @param string $default_lang Default language code
     * @param bool   $multilang_active Whether a multilang plugin is active
     * @return int Number of categories migrated
     */
    private function migrate_categories(string $default_lang, bool $multilang_active): int
    {
        $categories = get_terms([
            'hide_empty' => false,
            'taxonomy'   => 'product_cat',
            'number'     => 0,
            'meta_query' => [
                'relation' => 'AND',
                ['key' => 'saleslayerid', 'compare' => 'EXISTS'],
                ['key' => 'slyr_wc_lang', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        if (is_wp_error($categories)) {
            return 0;
        }

        $count = 0;
        foreach ($categories as $category) {
            update_term_meta($category->term_id, 'slyr_wc_lang', $default_lang);
            $count++;

            // Link all variants as Polylang translation group if multilang is active
            if ($multilang_active) {
                $sl_id = get_term_meta($category->term_id, 'saleslayerid', true);
                $comp_id = get_term_meta($category->term_id, 'saleslayercompid', true);

                if ($sl_id && $comp_id && class_exists('MultilangHelper')) {
                    $helper = new MultilangHelper();
                    $helper->link_all_term_translations('product_cat', $sl_id, $comp_id);
                }
            }
        }

        if ($count > 0) {
            sl_debug("Migrated $count categories to multilang architecture");
        }

        return $count;
    }

    /**
     * Migrate legacy variations to multilang architecture.
     *
     * Assigns language from parent product if available, otherwise uses default.
     *
     * @param string $default_lang Default language code
     * @param bool   $multilang_active Whether a multilang plugin is active
     * @return int Number of variations migrated
     */
    private function migrate_variations(string $default_lang, bool $multilang_active): int
    {
        $variations = get_posts([
            'post_type'      => 'product_variation',
            'post_status'    => ['publish', 'pending', 'draft', 'private', 'trash'],
            'posts_per_page' => -1,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => '_saleslayerid', 'compare' => 'EXISTS'],
                ['key' => '_slyr_wc_lang', 'compare' => 'NOT EXISTS'],
            ],
        ]);

        $count = 0;
        foreach ($variations as $variation) {
            $parent_id = $variation->post_parent;
            $var_lang = $default_lang;

            // Try to get language from parent product
            if ($parent_id > 0) {
                $parent_lang = get_post_meta($parent_id, '_slyr_wc_lang', true);
                if ($parent_lang) {
                    $var_lang = $parent_lang;
                }
            }

            update_post_meta($variation->ID, '_slyr_wc_lang', $var_lang);
            $count++;

            // Link all variants as Polylang translation group if multilang is active
            if ($multilang_active) {
                $sl_id = get_post_meta($variation->ID, '_saleslayerid', true);
                $comp_id = get_post_meta($variation->ID, '_saleslayercompid', true);

                if ($sl_id && $comp_id && class_exists('MultilangHelper')) {
                    $helper = new MultilangHelper();
                    $helper->link_all_post_translations($sl_id, $comp_id, ['product_variation']);
                }
            }
        }

        if ($count > 0) {
            sl_debug("Migrated $count variations to multilang architecture");
        }

        return $count;
    }
}
