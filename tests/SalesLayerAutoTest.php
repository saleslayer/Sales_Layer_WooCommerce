<?php
/**
 * Optimized SalesLayer Testing System
 * 
 * Features:
 * - Object-oriented architecture following SOLID principles
 * - Separated responsibilities into specialized classes
 * - Eliminated code duplication
 * - Consistent error handling
 * - Improved maintainability and testability
 *
 * @author Peter
 * @version 3.0 - Optimized Architecture
 */

// ============================================================================
// CONFIGURATION AND UTILITIES
// ============================================================================

/**
 * Centralized configuration management
 */
class TestConfig {
    const DEFAULT_WAIT_MINUTES = 10;
    const LOG_DATE_FORMAT = 'Y-m-d H:i:s';
    const REQUIRED_ATTRIBUTES = [
        'color' => ['name' => 'Color', 'slug' => 'pa_color', 'type' => 'select'],
        'size' => ['name' => 'Size', 'slug' => 'pa_size', 'type' => 'select']
    ];
    
    public static function getMockupDataDir(): string {
        return __DIR__ . '/mockup_data/';
    }
    
    public static function getLogsDir(): string {
        return __DIR__ . '/logs/';
    }
    
    public static function getSyncdataTable(): string {
        if (!defined('SLYR_WC_syncdata_table')) {
            throw new Exception("SalesLayer plugin is not active or not installed");
        }
        return SLYR_WC_syncdata_table;
    }
}

/**
 * Centralized logging with levels and formatting
 */
class Logger {
    private string $logFile;
    private bool $quietMode;

    public function __construct(bool $quietMode = true) {
        $this->quietMode = $quietMode;
        $this->logFile = TestConfig::getLogsDir() . 'autotest_' . date('Y-m-d') . '.log';
        $this->ensureLogDirectory();
    }

    public function info(string $message): void {
        $this->log($message, 'INFO');
    }

    public function error(string $message): void {
        $this->log($message, 'ERROR');
    }

    public function debug(string $message): void {
        $this->log($message, 'DEBUG');
    }

    public function warning(string $message): void {
        $this->log($message, 'WARNING');
    }

    private function log(string $message, string $level): void {
        $timestamp = date(TestConfig::LOG_DATE_FORMAT);
        $formatted = "[{$timestamp}] [{$level}] {$message}\n";

        file_put_contents($this->logFile, $formatted, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogDirectory(): void {
        $logDir = TestConfig::getLogsDir();
        if (!is_dir($logDir)) {
            wp_mkdir_p($logDir);
        }
    }
}

/**
 * Database field reader for WooCommerce products and variants
 *
 * Centralizes database access for price and stock fields, providing
 * a unified interface for both simple products and product variations.
 *
 * @author Peter
 * @version 1.0
 */
class WooCommerceDatabaseReader {

    private static array $cache = [];

    /**
     * Get all product meta fields in a single query (optimized)
     *
     * @param int $productId Product or variant ID
     * @return array All meta fields for the product
     */
    public static function getAllMeta(int $productId): array {
        if (isset(self::$cache[$productId])) {
            return self::$cache[$productId];
        }

        global $wpdb;

        $meta_fields = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT meta_key, meta_value FROM {$wpdb->postmeta}
                WHERE post_id = %d AND meta_key IN (
                    '_regular_price', '_sale_price', '_price', '_stock',
                    '_stock_status', '_manage_stock', '_sku', '_weight',
                    '_length', '_width', '_height', '_product_attributes'
                )",
                $productId
            ),
            ARRAY_A
        );

        $result = [];
        foreach ($meta_fields as $field) {
            $result[$field['meta_key']] = $field['meta_value'];
        }

        self::$cache[$productId] = $result;
        return $result;
    }

}

// ============================================================================
// DATABASE MANAGEMENT
// ============================================================================

/**
 * Centralized database operations
 */
class DatabaseManager {
    private $wpdb;
    private string $syncdata_table;
    private Logger $logger;
    
    public function __construct(Logger $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->syncdata_table = TestConfig::getSyncdataTable();
        $this->logger = $logger;
        $this->validateConnection();
    }
    
    /**
     * Validate WordPress database connection
     */
    private function validateConnection(): void {
        if (!isset($this->wpdb) || !is_object($this->wpdb)) {
            throw new Exception("WordPress \$wpdb is not available");
        }

        $this->logger->info("✅ WordPress \$wpdb available (type: " . get_class($this->wpdb) . ")");
        $this->logger->info("🗄️ Database: {$this->wpdb->dbname}");

        // Verify table exists
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare("SHOW TABLES LIKE %s", $this->syncdata_table)
        );

        if ($table_exists !== $this->syncdata_table) {
            throw new Exception("Table {$this->syncdata_table} does not exist. Verify SalesLayer plugin is installed.");
        }

        $this->logger->info("✅ Table {$this->syncdata_table} found");
    }
    
    /**
     * Find product by SalesLayer ID and Company ID
     */
    public function findProductBySalesLayerId(string $sl_id, string $comp_id): ?int {
        $post_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT pm1.post_id
                FROM {$this->wpdb->postmeta} pm1
                INNER JOIN {$this->wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                INNER JOIN {$this->wpdb->posts} p ON pm1.post_id = p.ID
                WHERE pm1.meta_key = '_saleslayerid' AND pm1.meta_value = %s
                AND pm2.meta_key = '_saleslayercompid' AND pm2.meta_value = %s
                AND p.post_type = 'product'",
                $sl_id, $comp_id
            )
        );
        
        return $post_id ? (int) $post_id : null;
    }
    
    /**
     * Find category by SalesLayer ID and Company ID
     */
    public function findCategoryBySalesLayerId(string $sl_id, string $comp_id): ?int {
        $term_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT tm1.term_id
                FROM {$this->wpdb->termmeta} tm1
                INNER JOIN {$this->wpdb->termmeta} tm2 ON tm1.term_id = tm2.term_id
                INNER JOIN {$this->wpdb->term_taxonomy} tt ON tm1.term_id = tt.term_id
                WHERE tm1.meta_key = 'saleslayerid' AND tm1.meta_value = %s
                AND tm2.meta_key = 'saleslayercompid' AND tm2.meta_value = %s
                AND tt.taxonomy = 'product_cat'",
                $sl_id, $comp_id
            )
        );
        
        return $term_id ? (int) $term_id : null;
    }
    
    /**
     * Find variant by SalesLayer ID and Company ID
     */
    public function findVariantBySalesLayerId(string $sl_id, string $comp_id, string $format_id): ?int {
        $post_id = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT pm1.post_id
                FROM {$this->wpdb->postmeta} pm1
                INNER JOIN {$this->wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id
                INNER JOIN {$this->wpdb->postmeta} pm3 ON pm1.post_id = pm3.post_id
                INNER JOIN {$this->wpdb->posts} p ON pm1.post_id = p.ID
                WHERE pm1.meta_key = '_saleslayerid' AND pm1.meta_value = %s
                AND pm2.meta_key = '_saleslayercompid' AND pm2.meta_value = %s
                AND pm3.meta_key = '_saleslayerformatid' AND pm3.meta_value = %s
                AND p.post_type = 'product_variation'",
                $sl_id, $comp_id, $format_id
            )
        );
        
        return $post_id ? (int) $post_id : null;
    }
    
    /**
     * Insert sync record into database
     */
    public function insertSyncRecord(array $data): int {
        $this->logger->debug("💾 Inserting record: {$data['item_type']}");
        
        $result = $this->wpdb->insert($this->syncdata_table, $data);
        
        if ($result === false) {
            $error = "Database insertion failed: " . $this->wpdb->last_error;
            $this->logger->error($error);
            throw new Exception($error);
        }
        
        $insert_id = $this->wpdb->insert_id;
        $this->logger->debug("✅ Record inserted with ID: {$insert_id}");
        
        return $insert_id;
    }
    
    /**
     * Get record count with optional condition
     */
    public function getRecordCount(string $condition = ''): int {
        $sql = "SELECT COUNT(*) FROM {$this->syncdata_table}";
        if ($condition) {
            $sql .= " WHERE {$condition}";
        }
        return (int) $this->wpdb->get_var($sql);
    }

    /**
     * Delete ALL records from the syncdata table
     *
     * @return int|false Number of deleted records or false on error
     */
    public function deleteAllRecords() {
        $this->logger->debug("Executing TRUNCATE on {$this->syncdata_table}");

        // Use TRUNCATE for better performance and to reset auto-increment
        $result = $this->wpdb->query("TRUNCATE TABLE {$this->syncdata_table}");

        if ($result === false) {
            $this->logger->error("Failed to truncate table: " . $this->wpdb->last_error);
            return false;
        }

        $this->logger->debug("Successfully truncated {$this->syncdata_table}");
        return $result;
    }
    
    /**
     * Get table statistics
     */
    public function getTableStats(): array {
        return [
            'total_records' => $this->getRecordCount(),
            'categories' => $this->getRecordCount("item_type = 'category'"),
            'products' => $this->getRecordCount("item_type = 'product'"),
            'variants' => $this->getRecordCount("item_type = 'variant'")
        ];
    }
}

// ============================================================================
// DATA READING AND PROCESSING
// ============================================================================

/**
 * Handles reading and processing of mockup data
 */
class DataReader {
    private Logger $logger;
    private string $mockupDataDir;

    public function __construct(Logger $logger) {
        $this->logger = $logger;
        $this->mockupDataDir = TestConfig::getMockupDataDir();
        $this->validateDataDirectory();
    }

    /**
     * Validate mockup data directory exists
     */
    private function validateDataDirectory(): void {
        if (!is_dir($this->mockupDataDir)) {
            throw new Exception("Mockup data directory not found: {$this->mockupDataDir}");
        }

        $this->logger->info("📁 Mockup data directory: {$this->mockupDataDir}");
    }

    /**
     * Read all mockup data from JSON files
     */
    public function readMockupData(): array {
        $this->logger->info("📖 Reading mockup data...");

        $data = [
            'categories' => $this->readJsonFiles('categories'),
            'products' => $this->readJsonFiles('products'),
            'variants' => $this->readJsonFiles('variants'),
            'counters' => $this->readJsonFiles('counters')
        ];

        $total_files = array_sum(array_map('count', $data));
        $this->logger->info("📊 Total files read: {$total_files}");

        return $data;
    }

    /**
     * Read JSON files from specific subdirectory
     */
    private function readJsonFiles(string $subdir): array {
        $dir = $this->mockupDataDir . $subdir . '/';
        $files = [];

        if (!is_dir($dir)) {
            $this->logger->warning("Directory not found: {$dir}");
            return [];
        }

        $json_files = glob($dir . '*.json');

        foreach ($json_files as $file) {
            $content = file_get_contents($file);
            if ($content === false) {
                $this->logger->error("Failed to read file: {$file}");
                continue;
            }

            $data = json_decode($content, true);
            if ($data === null) {
                $this->logger->error("Invalid JSON in file: {$file}");
                continue;
            }

            // Add test_data flag
            $data['test_data'] = true;
            $files[] = $data;

            $filename = basename($file);
            $this->logger->debug("📁 {$subdir} read: {$filename}");
        }

        $this->logger->info("📊 {$subdir}: " . count($files) . " files");
        return $files;
    }
}

// ============================================================================
// VALIDATION SYSTEM
// ============================================================================

/**
 * Base validator with common functionality
 */
abstract class BaseValidator {
    protected DatabaseManager $db;
    protected Logger $logger;
    protected array $errors = [];

    public function __construct(DatabaseManager $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    protected function addError(string $error): void {
        $this->errors[] = $error;
        $this->logger->error($error);
    }

    protected function validateField(string $field, $expected, $actual): bool {
        // Handle null values
        if ($expected === null && $actual === null) {
            return true;
        }

        // Handle empty string vs null for optional fields
        if (($expected === '' || $expected === null) && ($actual === '' || $actual === null)) {
            return true;
        }

        // For numeric fields, convert to same type for comparison
        if (is_numeric($expected) && is_numeric($actual)) {
            // If both look like integers, compare as integers
            if (strpos($expected, '.') === false && strpos($actual, '.') === false) {
                $expected = intval($expected);
                $actual = intval($actual);
            } else {
                // Compare as floats with small tolerance
                $expectedFloat = floatval($expected);
                $actualFloat = floatval($actual);
                if (abs($expectedFloat - $actualFloat) < 0.01) {
                    return true;
                }
            }
        }

        // Standard comparison
        if ($expected !== $actual) {
            $this->addError("Field {$field}: expected '{$expected}' (" . gettype($expected) . "), found '{$actual}' (" . gettype($actual) . ")");
            return false;
        }

        return true;
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    public function clearErrors(): void {
        $this->errors = [];
    }

    /**
     * Check if a field has meaningful content (not empty, null, or whitespace-only)
     */
    protected function hasContent($value): bool {
        if (is_null($value)) return false;
        if (is_array($value)) return !empty($value);
        if (is_string($value)) return !empty(trim($value));
        if (is_numeric($value)) return true;
        return !empty($value);
    }

    abstract public function validate(array $data): bool;
}

/**
 * Specialized product validator
 */
class ProductValidator extends BaseValidator {
    public function validate(array $productData): bool {
        $this->clearErrors();

        $product = $this->findProduct($productData);
        if (!$product) return false;

        return $this->validateAllFields($product, $productData);
    }

    private function findProduct(array $productData): ?WC_Product {
        $slId = $productData['item_data']['ID'];
        $compId = $productData['sync_params']['conn_params']['comp_id'];

        $postId = $this->db->findProductBySalesLayerId($slId, $compId);
        if (!$postId) {
            $this->addError("Product not found: SL ID {$slId}, Comp ID {$compId}");
            return null;
        }

        $product = wc_get_product($postId);
        if (!$product) {
            $this->addError("Error getting WooCommerce product with post_id: {$postId}");
            return null;
        }

        return $product;
    }

    private function validateAllFields(WC_Product $product, array $productData): bool {
        $data = $productData['item_data']['data'];

        // 🚀 OPTIMIZACIÓN: Una sola consulta para todos los meta datos
        $meta = WooCommerceDatabaseReader::getAllMeta($product->get_id());

        $validations = [
            'name' => fn() => $this->validateName($product, $data),
            'sku' => fn() => $this->validateSku($product, $data, $meta['_sku'] ?? ''),
            'description' => fn() => $this->validateDescription($product, $data),
            'price' => fn() => $this->validatePrice($product, $data, $meta),
            'stock' => fn() => $this->validateStock($product, $data, $meta['_stock'] ?? null),
            'dimensions' => fn() => $this->validateProductDimensions($product, $data, $meta),
            'image' => fn() => $this->validateProductImage($product, $data),
            'purchase_note' => fn() => $this->validateProductPurchaseNote($product, $data, $meta),
            'tags' => fn() => $this->validateProductTags($product, $data),
            'manage_stock' => fn() => $this->validateProductManageStock($product, $data, $meta),
            'product_type' => fn() => $this->validateProductType($product, $data),
            'description_short' => fn() => $this->validateProductDescriptionShort($product, $data, $meta),
            'stock_status' => fn() => $this->validateProductStockStatus($product, $data, $meta),
            'menu_order' => fn() => $this->validateProductMenuOrder($product, $data, $meta),
            'status' => fn() => $this->validateProductStatus($product, $data),
            'shipping_class' => fn() => $this->validateProductShippingClass($product, $data, $meta),
            'product_references' => fn() => $this->validateProductReferences($product, $data),
            'additional_fields' => fn() => $this->validateProductAdditionalFields($product, $data, $meta)
        ];

        $success = true;
        foreach ($validations as $field => $validator) {
            if (!$validator()) {
                $success = false;
            }
        }

        return $success;
    }

    private function validateName(WC_Product $product, array $data): bool {
        if (!isset($data['product_name_es'])) return true;

        return $this->validateField(
            'name',
            $data['product_name_es'],
            $product->get_name()
        );
    }

    private function validateSku(WC_Product $product, array $data, string $actualSku): bool {
        if (!isset($data['product_sku'])) return true;

        return $this->validateField(
            'sku',
            $data['product_sku'],
            $actualSku
        );
    }

    private function validateDescription(WC_Product $product, array $data): bool {
        if (!isset($data['product_description_es'])) return true;

        return $this->validateField(
            'description',
            $data['product_description_es'],
            $product->get_description()
        );
    }

    private function validatePrice(WC_Product $product, array $data, array $meta = []): bool {
        $success = true;

        // Validate regular price
        if (isset($data['product_regular_price'])) {
            if (!$this->validateRegularPrice($product, $data, $meta['_regular_price'] ?? null)) {
                $success = false;
            }
        }

        // Validate sale price
        if (isset($data['product_sale_price'])) {
            if (!$this->validateSalePrice($product, $data, $meta['_sale_price'] ?? null)) {
                $success = false;
            }
        }

        // Validate general price
        if (isset($data['product_price'])) {
            if (!$this->validateGeneralPrice($product, $data, $meta['_price'] ?? null)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate regular price specifically
     */
    private function validateRegularPrice(WC_Product $product, array $data, string $actualPrice): bool {
        if (!isset($data['product_regular_price'])) {
            return true;
        }

        $expectedPrice = $data['product_regular_price'];
        return $this->validateField('regular_price', $expectedPrice, $actualPrice);
    }

    /**
     * Validate sale price specifically
     */
    private function validateSalePrice(WC_Product $product, array $data, string $actualPrice): bool {
        if (!isset($data['product_sale_price'])) {
            return true;
        }

        $expectedPrice = $data['product_sale_price'];
        return $this->validateField('sale_price', $expectedPrice, $actualPrice);
    }

    /**
     * Validate general price specifically
     */
    private function validateGeneralPrice(WC_Product $product, array $data, string $actualPrice): bool {
        if (!isset($data['product_price'])) {
            return true;
        }

        $expectedPrice = $data['product_price'];
        return $this->validateField('price', $expectedPrice, $actualPrice);
    }

    private function validateStock(WC_Product $product, array $data, $actualStock): bool {
        if (!isset($data['product_stock'])) {
            $this->logger->debug("Stock field 'product_stock' not found in data, skipping validation");
            return true;
        }

        $expectedStock = $data['product_stock'];

        // Convertir a int si es necesario
        $actualStock = $actualStock !== null ? (int)$actualStock : null;

        return $this->validateField('stock', $expectedStock, $actualStock);
    }

    /**
     * Validate product dimensions (weight, length, width, height)
     */
    private function validateProductDimensions(WC_Product $product, array $data, array $meta): bool {
        $dimensionFields = [
            'product_weight' => '_weight',
            'product_length' => '_length',
            'product_width' => '_width',
            'product_height' => '_height'
        ];

        $success = true;
        foreach ($dimensionFields as $dataField => $metaKey) {
            if (!isset($data[$dataField]) || empty(trim((string)$data[$dataField]))) continue;

            $expected = (string)$data[$dataField];
            $actual = $meta[$metaKey] ?? '';

            if (!$this->validateField($dataField, $expected, $actual)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate product image
     */
    private function validateProductImage(WC_Product $product, array $data): bool {
        if (!isset($data['product_image_es'])) {
            return true; // Image is optional
        }

        $imageId = $product->get_image_id();
        if (empty($imageId)) {
            $this->addError("Product image not found in WordPress for product: {$product->get_name()}");
            return false;
        }

        $imageUrl = wp_get_attachment_url($imageId);
        if (!$imageUrl) {
            $this->addError("Product image URL not accessible for product: {$product->get_name()}");
            return false;
        }

        return true;
    }

    /**
     * Validate product purchase note
     */
    private function validateProductPurchaseNote(WC_Product $product, array $data, array $meta): bool {
        if (!isset($data['product_purchase_note']) || empty(trim($data['product_purchase_note']))) {
            return true;
        }

        $expected = $data['product_purchase_note'];
        $actual = $meta['_purchase_note'] ?? '';

        return $this->validateField('purchase_note', $expected, $actual);
    }

    /**
     * Validate product tags
     */
    private function validateProductTags(WC_Product $product, array $data): bool {
        if (!isset($data['product_tags']) || empty($data['product_tags'])) {
            return true; // Tags are optional
        }

        $expectedTags = is_array($data['product_tags']) ? $data['product_tags'] : [$data['product_tags']];
        $productTags = wp_get_post_terms($product->get_id(), 'product_tag', ['fields' => 'names']);

        foreach ($expectedTags as $expectedTag) {
            if (!in_array($expectedTag, $productTags)) {
                $this->addError("Product tag '{$expectedTag}' not found in product: {$product->get_name()}");
                return false;
            }
        }

        return true;
    }

    /**
     * Validate product manage stock setting
     */
    private function validateProductManageStock(WC_Product $product, array $data, array $meta): bool {
        if (!isset($data['product_manage_stock']) || empty(trim($data['product_manage_stock']))) {
            return true;
        }

        $expected = $data['product_manage_stock'] === 'yes' ? 'yes' : 'no';
        $actual = $meta['_manage_stock'] ?? 'no';

        return $this->validateField('manage_stock', $expected, $actual);
    }

    /**
     * Validate product type (downloadable, virtual)
     */
    private function validateProductType(WC_Product $product, array $data): bool {
        $success = true;

        // Validate downloadable
        if (isset($data['product_downloadable']) && !empty(trim($data['product_downloadable']))) {
            $expectedDownloadable = $data['product_downloadable'] === 'yes';
            $actualDownloadable = $product->is_downloadable();

            if ($expectedDownloadable !== $actualDownloadable) {
                $this->addError("Product downloadable mismatch: expected " . ($expectedDownloadable ? 'yes' : 'no') . ", found " . ($actualDownloadable ? 'yes' : 'no'));
                $success = false;
            }
        }

        // Validate virtual
        if (isset($data['product_virtual']) && !empty(trim($data['product_virtual']))) {
            $expectedVirtual = $data['product_virtual'] === 'yes';
            $actualVirtual = $product->is_virtual();

            if ($expectedVirtual !== $actualVirtual) {
                $this->addError("Product virtual mismatch: expected " . ($expectedVirtual ? 'yes' : 'no') . ", found " . ($actualVirtual ? 'yes' : 'no'));
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate product description short
     */
    private function validateProductDescriptionShort(WC_Product $product, array $data, array $meta): bool {
        if (!isset($data['product_description_short']) || empty(trim($data['product_description_short']))) {
            return true;
        }

        $expected = $data['product_description_short'];
        $actual = $product->get_short_description();

        return $this->validateField('description_short', $expected, $actual);
    }

    /**
     * Validate product stock status
     */
    private function validateProductStockStatus(WC_Product $product, array $data, array $meta): bool {
        if (!isset($data['product_stock_status']) || empty(trim($data['product_stock_status']))) {
            return true;
        }

        $expected = $data['product_stock_status'];
        $actual = $product->get_stock_status();

        return $this->validateField('stock_status', $expected, $actual);
    }

    /**
     * Validate product menu order
     */
    private function validateProductMenuOrder(WC_Product $product, array $data, array $meta): bool {
        if (!isset($data['product_menu_order']) || empty(trim($data['product_menu_order']))) {
            return true;
        }

        $expected = $data['product_menu_order'];
        $actual = $meta['_menu_order'] ?? '';

        return $this->validateField('menu_order', $expected, $actual);
    }

    /**
     * Validate product status
     */
    private function validateProductStatus(WC_Product $product, array $data): bool {
        if (!isset($data['product_status']) || empty(trim($data['product_status']))) {
            return true;
        }

        $expected = $data['product_status'];
        $actual = $product->get_status();

        return $this->validateField('status', $expected, $actual);
    }

    /**
     * Validate product shipping class
     */
    private function validateProductShippingClass(WC_Product $product, array $data, array $meta): bool {
        if (!isset($data['product_shipping_class']) || empty(trim($data['product_shipping_class']))) {
            return true;
        }

        $expected = $data['product_shipping_class'];
        $actual = $product->get_shipping_class();

        return $this->validateField('shipping_class', $expected, $actual);
    }

    /**
     * Validate product references (related, crosssell, upsell, grouping)
     */
    private function validateProductReferences(WC_Product $product, array $data): bool {
        $referenceFields = [
            'related_products_references' => 'related_ids',
            'crosssell_products_references' => 'cross_sell_ids',
            'upsell_products_references' => 'upsell_ids',
            'grouping_product_references' => 'grouped_products'
        ];

        $success = true;
        foreach ($referenceFields as $dataField => $method) {
            if (!isset($data[$dataField]) || empty($data[$dataField])) continue;

            $expectedRefs = is_array($data[$dataField]) ? $data[$dataField] : [$data[$dataField]];

            // Get actual references from product
            $actualRefs = [];
            if (method_exists($product, "get_{$method}")) {
                $actualRefs = $product->{"get_{$method}"}();
            }

            // Convert to comparable format
            $expectedCount = count($expectedRefs);
            $actualCount = count($actualRefs);

            if ($expectedCount !== $actualCount) {
                $this->addError("Product {$dataField} count mismatch: expected {$expectedCount}, found {$actualCount}");
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate product additional fields (composition, specifications, etc.)
     */
    private function validateProductAdditionalFields(WC_Product $product, array $data, array $meta): bool {
        // Get product attributes from _product_attributes meta
        $productAttributes = $meta['_product_attributes'] ?? '';
        if (is_string($productAttributes)) {
            $productAttributes = maybe_unserialize($productAttributes);
        }
        if (!is_array($productAttributes)) {
            $productAttributes = [];
        }

        $additionalFields = [
            'composition_es' => 'composition',
            'specifications' => 'specifications',
            'texto_500' => 'texto_500'
        ];

        $success = true;
        foreach ($additionalFields as $dataField => $attributeName) {
            if (!isset($data[$dataField]) || !$this->hasContent($data[$dataField])) continue;

            $expected = $data[$dataField];
            $actual = '';

            // Look for the attribute in product attributes
            if (isset($productAttributes[$attributeName]) && isset($productAttributes[$attributeName]['value'])) {
                $actual = $productAttributes[$attributeName]['value'];
            }

            if (!$this->validateField($dataField, $expected, $actual)) {
                $success = false;
            }
        }

        // Validate media fields (file_data, additional_image, tmt_file_test)
        $mediaFields = ['file_data', 'additional_image', 'tmt_file_test'];
        foreach ($mediaFields as $mediaField) {
            if (!isset($data[$mediaField])) continue;

            $expectedMedia = $data[$mediaField];
            if (is_array($expectedMedia) && !empty($expectedMedia)) {
                // Media field has content - validate it exists in meta
                $actualMedia = $meta["_{$mediaField}"] ?? [];
                if (empty($actualMedia)) {
                    $this->addError("Product {$mediaField} not found in WordPress meta");
                    $success = false;
                }
            }
        }

        return $success;
    }

}

/**
 * Specialized category validator
 */
class CategoryValidator extends BaseValidator {
    public function validate(array $categoryData): bool {
        $this->clearErrors();

        $category = $this->findCategory($categoryData);
        if (!$category) return false;

        return $this->validateAllFields($category, $categoryData);
    }

    private function findCategory(array $categoryData): ?WP_Term {
        $slId = $categoryData['item_data']['ID'];
        $compId = $categoryData['sync_params']['conn_params']['comp_id'];

        $termId = $this->db->findCategoryBySalesLayerId($slId, $compId);
        if (!$termId) {
            $this->addError("Category not found: SL ID {$slId}, Comp ID {$compId}");
            return null;
        }

        $term = get_term($termId, 'product_cat');
        if (!$term || is_wp_error($term)) {
            $this->addError("Error getting category with term_id: {$termId}");
            return null;
        }

        return $term;
    }

    private function validateAllFields(WP_Term $category, array $categoryData): bool {
        $data = $categoryData['item_data']['data'];

        $validations = [
            'name' => fn() => $this->validateName($category, $data),
            'description' => fn() => $this->validateDescription($category, $data),
            'slug' => fn() => $this->validateSlug($category),
            'image' => fn() => $this->validateCategoryImage($category, $data),
            'order' => fn() => $this->validateCategoryOrder($category, $data)
        ];

        $success = true;
        foreach ($validations as $field => $validator) {
            if (!$validator()) {
                $success = false;
            }
        }

        return $success;
    }

    private function validateName(WP_Term $category, array $data): bool {
        if (!isset($data['section_name_es'])) return true;

        return $this->validateField(
            'name',
            $data['section_name_es'],
            $category->name
        );
    }

    private function validateDescription(WP_Term $category, array $data): bool {
        if (!isset($data['section_description'])) return true;

        return $this->validateField(
            'description',
            $data['section_description'],
            $category->description
        );
    }

    private function validateSlug(WP_Term $category): bool {
        // Slug is auto-generated, just check it's reasonable
        $expectedSlug = sanitize_title($category->name);
        $similarity = similar_text($category->slug, $expectedSlug);

        if ($similarity < strlen($expectedSlug) * 0.8) {
            $this->addError("Slug similarity too low: expected similar to '{$expectedSlug}', found '{$category->slug}'");
            return false;
        }

        return true;
    }

    /**
     * Validate category image
     */
    private function validateCategoryImage(WP_Term $category, array $data): bool {
        if (!isset($data['section_image'])) {
            return true; // Image is optional
        }

        $thumbnailId = get_term_meta($category->term_id, 'thumbnail_id', true);
        if (empty($thumbnailId)) {
            $this->addError("Category image not found in WordPress for category: {$category->name}");
            return false;
        }

        $imageUrl = wp_get_attachment_url($thumbnailId);
        if (!$imageUrl) {
            $this->addError("Category image URL not accessible for category: {$category->name}");
            return false;
        }

        return true;
    }

    /**
     * Validate category order
     */
    private function validateCategoryOrder(WP_Term $category, array $data): bool {
        if (!isset($data['section_order'])) {
            return true; // Order is optional
        }

        $expectedOrder = $data['section_order'];
        $actualOrder = get_term_meta($category->term_id, 'order', true);

        if ($expectedOrder != $actualOrder) {
            $this->addError("Category order mismatch: expected '{$expectedOrder}', found '{$actualOrder}'");
            return false;
        }

        return true;
    }
}

/**
 * Specialized variant validator
 */
class VariantValidator extends BaseValidator {
    public function validate(array $variantData): bool {
        $this->clearErrors();

        $variant = $this->findVariant($variantData);
        if (!$variant) return false;

        return $this->validateAllFields($variant, $variantData);
    }

    private function findVariant(array $variantData): ?WC_Product_Variation {
        $slId = $variantData['item_data']['format_data']['ID_products'];
        $compId = $variantData['sync_params']['conn_params']['comp_id'];
        $formatId = $variantData['item_data']['format_data']['ID'];

        $postId = $this->db->findVariantBySalesLayerId($slId, $compId, $formatId);
        if (!$postId) {
            $this->addError("Variant not found: SL ID {$slId}, Comp ID {$compId}, Format ID {$formatId}");
            return null;
        }

        $variant = wc_get_product($postId);
        if (!$variant || !$variant instanceof WC_Product_Variation) {
            $this->addError("Error getting variant with post_id: {$postId}");
            return null;
        }

        return $variant;
    }

    private function validateAllFields(WC_Product_Variation $variant, array $variantData): bool {
        $data = $variantData['item_data']['format_data']['data'];

        // 🚀 OPTIMIZACIÓN: Una sola consulta para todos los meta datos
        $meta = WooCommerceDatabaseReader::getAllMeta($variant->get_id());

        $validations = [
            'sku' => fn() => $this->validateSku($variant, $data, $meta['_sku'] ?? ''),
            'price' => fn() => $this->validatePrice($variant, $data, $meta),
            'stock' => fn() => $this->validateStock($variant, $data, $meta['_stock'] ?? null),
            'description' => fn() => $this->validateVariantDescription($variant, $data),
            'dimensions' => fn() => $this->validateVariantDimensions($variant, $data, $meta),
            'image' => fn() => $this->validateVariantImage($variant, $data),
            'manage_stock' => fn() => $this->validateVariantManageStock($variant, $data, $meta),
            'stock_status' => fn() => $this->validateVariantStockStatus($variant, $data, $meta),
            'enabled' => fn() => $this->validateVariantEnabled($variant, $data),
            'product_type' => fn() => $this->validateVariantProductType($variant, $data),
            'shipping_class' => fn() => $this->validateVariantShippingClass($variant, $data, $meta),
            'attributes' => fn() => $this->validateVariantAttributes($variant, $variantData),
            'additional_fields' => fn() => $this->validateVariantAdditionalFields($variant, $data, $meta)
        ];

        $success = true;
        foreach ($validations as $field => $validator) {
            if (!$validator()) {
                $success = false;
            }
        }

        return $success;
    }

    private function validateSku(WC_Product_Variation $variant, array $data): bool {
        if (!isset($data['format_sku']) || !$this->hasContent($data['format_sku'])) {
            return true;
        }

        return $this->validateField(
            'sku',
            $data['format_sku'],
            $variant->get_sku()
        );
    }

    private function validatePrice(WC_Product_Variation $variant, array $data, array $meta = []): bool {
        $success = true;

        // Validate regular price
        if (isset($data['format_regular_price'])) {
            if (!$this->validateVariantRegularPrice($variant, $data, $meta['_regular_price'] ?? null)) {
                $success = false;
            }
        }

        // Validate sale price
        if (isset($data['format_sale_price'])) {
            if (!$this->validateVariantSalePrice($variant, $data, $meta['_sale_price'] ?? null)) {
                $success = false;
            }
        }

        // Validate general price (format_price)
        if (isset($data['format_price'])) {
            if (!$this->validateVariantGeneralPrice($variant, $data, $meta['_price'] ?? null)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate variant regular price specifically
     */
    private function validateVariantRegularPrice(WC_Product_Variation $variant, array $data, string $actualPrice): bool {
        if (!isset($data['format_regular_price']) || !$this->hasContent($data['format_regular_price'])) {
            return true;
        }

        $expectedPrice = $data['format_regular_price'];
        return $this->validateField('variant_regular_price', $expectedPrice, $actualPrice);
    }

    /**
     * Validate variant sale price specifically
     */
    private function validateVariantSalePrice(WC_Product_Variation $variant, array $data, string $actualPrice): bool {
        if (!isset($data['format_sale_price']) || !$this->hasContent($data['format_sale_price'])) {
            return true;
        }

        $expectedPrice = $data['format_sale_price'];
        return $this->validateField('variant_sale_price', $expectedPrice, $actualPrice);
    }

    /**
     * Validate variant general price specifically
     */
    private function validateVariantGeneralPrice(WC_Product_Variation $variant, array $data, string $actualPrice): bool {
        if (!isset($data['format_price']) || !$this->hasContent($data['format_price'])) {
            return true;
        }

        $expectedPrice = $data['format_price'];
        return $this->validateField('variant_price', $expectedPrice, $actualPrice);
    }

    private function validateStock(WC_Product_Variation $variant, array $data, $actualStock): bool {
        if (!isset($data['format_stock'])) {
            $this->logger->debug("Stock field 'format_stock' not found in variant data, skipping validation");
            return true;
        }

        $expectedStock = $data['format_stock'];

        // Convertir a int si es necesario
        $actualStock = $actualStock !== null ? (int)$actualStock : null;

        return $this->validateField('variant_stock', $expectedStock, $actualStock);
    }

    /**
     * Validate variant description
     */
    private function validateVariantDescription(WC_Product_Variation $variant, array $data): bool {
        if (!isset($data['format_description']) || empty(trim($data['format_description']))) {
            return true;
        }

        $expected = $data['format_description'];
        $actual = $variant->get_description();

        return $this->validateField('variant_description', $expected, $actual);
    }

    /**
     * Validate variant dimensions (weight, length, width, height)
     */
    private function validateVariantDimensions(WC_Product_Variation $variant, array $data, array $meta): bool {
        $dimensionFields = [
            'format_weight' => '_weight',
            'format_length' => '_length',
            'format_width' => '_width',
            'format_height' => '_height'
        ];

        $success = true;
        foreach ($dimensionFields as $dataField => $metaKey) {
            if (!isset($data[$dataField]) || empty(trim((string)$data[$dataField]))) continue;

            $expected = (string)$data[$dataField];
            $actual = $meta[$metaKey] ?? '';

            if (!$this->validateField($dataField, $expected, $actual)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate variant image
     */
    private function validateVariantImage(WC_Product_Variation $variant, array $data): bool {
        if (!isset($data['format_image_es'])) {
            return true; // Image is optional
        }

        $imageId = $variant->get_image_id();
        if (empty($imageId)) {
            $this->addError("Variant image not found in WordPress for variant: {$variant->get_name()}");
            return false;
        }

        $imageUrl = wp_get_attachment_url($imageId);
        if (!$imageUrl) {
            $this->addError("Variant image URL not accessible for variant: {$variant->get_name()}");
            return false;
        }

        return true;
    }

    /**
     * Validate variant manage stock setting
     */
    private function validateVariantManageStock(WC_Product_Variation $variant, array $data, array $meta): bool {
        if (!isset($data['format_manage_stock']) || empty(trim($data['format_manage_stock']))) {
            return true;
        }

        $expected = $data['format_manage_stock'] === 'yes' ? 'yes' : 'no';
        $actual = $meta['_manage_stock'] ?? 'no';

        return $this->validateField('variant_manage_stock', $expected, $actual);
    }

    /**
     * Validate variant stock status
     */
    private function validateVariantStockStatus(WC_Product_Variation $variant, array $data, array $meta): bool {
        if (!isset($data['format_stock_status']) || !$this->hasContent($data['format_stock_status'])) {
            return true;
        }

        $expected = $data['format_stock_status'];
        $actual = $variant->get_stock_status();

        return $this->validateField('variant_stock_status', $expected, $actual);
    }

    /**
     * Validate variant enabled status
     */
    private function validateVariantEnabled(WC_Product_Variation $variant, array $data): bool {
        if (!isset($data['format_enabled']) || !$this->hasContent($data['format_enabled'])) {
            return true;
        }

        $expected = $data['format_enabled'];
        $actual = $variant->get_status();

        return $this->validateField('variant_enabled', $expected, $actual);
    }

    /**
     * Validate variant product type (downloadable, virtual)
     */
    private function validateVariantProductType(WC_Product_Variation $variant, array $data): bool {
        $success = true;

        // Validate downloadable
        if (isset($data['format_downloadable']) && $this->hasContent($data['format_downloadable'])) {
            $expectedDownloadable = $data['format_downloadable'] === 'yes';
            $actualDownloadable = $variant->is_downloadable();

            if ($expectedDownloadable !== $actualDownloadable) {
                $this->addError("Variant downloadable mismatch: expected " . ($expectedDownloadable ? 'yes' : 'no') . ", found " . ($actualDownloadable ? 'yes' : 'no'));
                $success = false;
            }
        }

        // Validate virtual
        if (isset($data['format_virtual']) && $this->hasContent($data['format_virtual'])) {
            $expectedVirtual = $data['format_virtual'] === 'yes';
            $actualVirtual = $variant->is_virtual();

            if ($expectedVirtual !== $actualVirtual) {
                $this->addError("Variant virtual mismatch: expected " . ($expectedVirtual ? 'yes' : 'no') . ", found " . ($actualVirtual ? 'yes' : 'no'));
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate variant shipping class
     */
    private function validateVariantShippingClass(WC_Product_Variation $variant, array $data, array $meta): bool {
        if (!isset($data['format_shipping_class']) || !$this->hasContent($data['format_shipping_class'])) {
            return true;
        }

        $expected = $data['format_shipping_class'];
        $actual = $variant->get_shipping_class();

        return $this->validateField('variant_shipping_class', $expected, $actual);
    }

    /**
     * Validate variant attributes (color, size, material)
     */
    private function validateVariantAttributes(WC_Product_Variation $variant, array $variantData): bool {
        $data = $variantData['item_data']['format_data']['data'];
        $attributeFields = ['color', 'size', 'material'];

        $success = true;
        foreach ($attributeFields as $attributeField) {
            if (!isset($data[$attributeField]) || !$this->hasContent($data[$attributeField])) continue;

            $expected = $data[$attributeField];
            $variantAttributes = $variant->get_attributes();

            // Look for the attribute in variant attributes
            $found = false;
            foreach ($variantAttributes as $attrName => $attrValue) {
                // Check both taxonomy attributes (pa_color) and custom attributes (color)
                if (strpos($attrName, $attributeField) !== false ||
                    $attrName === "pa_{$attributeField}" ||
                    $attrName === $attributeField) {

                    // For taxonomy attributes, get the term name
                    if (strpos($attrName, 'pa_') === 0) {
                        $term = get_term_by('slug', $attrValue, $attrName);
                        $actualValue = $term ? $term->name : $attrValue;
                    } else {
                        $actualValue = $attrValue;
                    }

                    if ($actualValue === $expected) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $this->addError("Variant attribute '{$attributeField}' with value '{$expected}' not found");
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Validate variant additional fields
     */
    private function validateVariantAdditionalFields(WC_Product_Variation $variant, array $data, array $meta): bool {
        // Get parent product attributes for variant-specific fields
        $parentId = $variant->get_parent_id();
        $parentMeta = WooCommerceDatabaseReader::getAllMeta($parentId);
        $productAttributes = $parentMeta['_product_attributes'] ?? '';

        if (is_string($productAttributes)) {
            $productAttributes = maybe_unserialize($productAttributes);
        }
        if (!is_array($productAttributes)) {
            $productAttributes = [];
        }

        // Media fields are stored as individual meta
        $mediaFields = [
            'tmt_image_format_field' => '_tmt_image_format_field',
            'tmt_file_format_field' => '_tmt_file_format_field'
        ];

        $success = true;
        foreach ($mediaFields as $dataField => $metaKey) {
            if (!isset($data[$dataField]) || !$this->hasContent($data[$dataField])) continue;

            $expected = $data[$dataField];
            $actual = $meta[$metaKey] ?? '';

            if (!$this->validateField($dataField, $expected, $actual)) {
                $success = false;
            }
        }

        return $success;
    }

}

// ============================================================================
// ATTRIBUTE MANAGEMENT
// ============================================================================

/**
 * Handles WooCommerce attribute creation and management
 */
class AttributeManager {
    private Logger $logger;
    private $wpdb;

    public function __construct(Logger $logger) {
        global $wpdb;
        $this->logger = $logger;
        $this->wpdb = $wpdb;
    }

    /**
     * Ensure required product attributes exist
     */
    public function ensureProductAttributes(): bool {
        $this->logger->info("🔧 Verifying product attributes in WooCommerce...");

        $created = 0;
        $existing = 0;

        foreach (TestConfig::REQUIRED_ATTRIBUTES as $key => $config) {
            if ($this->createAttributeIfNotExists($key, $config)) {
                $created++;
            } else {
                $existing++;
            }
        }

        $this->logger->info("✅ Attributes verified - Existing: {$existing}, Created: {$created}");
        return true;
    }

    /**
     * Create attribute if it doesn't exist
     */
    private function createAttributeIfNotExists(string $key, array $config): bool {
        $this->logger->debug("🔍 Checking attribute: {$key}");

        // Check if attribute already exists
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
                $key
            )
        );

        if ($existing) {
            $this->logger->debug("✅ Attribute '{$key}' already exists (ID: {$existing->attribute_id})");
            return false;
        }

        // Create the attribute
        $this->logger->info("🔧 Creating attribute '{$key}'...");

        $result = $this->wpdb->insert(
            $this->wpdb->prefix . 'woocommerce_attribute_taxonomies',
            [
                'attribute_name' => $key,
                'attribute_label' => $config['name'],
                'attribute_type' => $config['type'],
                'attribute_orderby' => 'menu_order',
                'attribute_public' => 0
            ],
            ['%s', '%s', '%s', '%s', '%d']
        );

        if ($result === false) {
            $this->logger->error("❌ Error creating attribute '{$key}': " . $this->wpdb->last_error);
            return false;
        }

        $attributeId = $this->wpdb->insert_id;
        $this->logger->info("✅ Attribute '{$key}' created successfully (ID: {$attributeId})");

        // Register taxonomy
        $this->registerTaxonomy($config['slug'], $config['name']);

        // Clear cache
        if (function_exists('delete_transient')) {
            delete_transient('wc_attribute_taxonomies');
            $this->logger->debug("🧹 Attribute cache cleared");
        }

        return true;
    }

    /**
     * Register taxonomy in WordPress
     */
    private function registerTaxonomy(string $slug, string $name): void {
        if (!taxonomy_exists($slug)) {
            $this->logger->debug("🔧 Registering taxonomy: {$slug}");

            register_taxonomy($slug, 'product', [
                'labels' => [
                    'name' => $name,
                    'singular_name' => $name,
                    'menu_name' => $name
                ],
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => false,
                'show_in_nav_menus' => false,
                'query_var' => true,
                'rewrite' => ['slug' => $slug],
                'hierarchical' => false
            ]);

            $this->logger->debug("✅ Taxonomy {$slug} registered");
        } else {
            $this->logger->debug("✅ Taxonomy {$slug} already exists");
        }
    }
}

// ============================================================================
// DATA PROCESSING
// ============================================================================

/**
 * Handles data insertion into syncdata table
 */
class DataProcessor {
    private DatabaseManager $db;
    private Logger $logger;
    private array $insertedIds = [];

    public function __construct(DatabaseManager $db, Logger $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Insert all data into syncdata table
     */
    public function insertSyncData(array $jsonData): int {
        $this->logger->info("💾 Inserting data into syncdata table...");

        $insertedCount = 0;
        $this->insertedIds = [];

        // Insert in order: counters, categories, products, variants
        $insertOrder = ['counters', 'categories', 'products', 'variants'];

        foreach ($insertOrder as $type) {
            if (isset($jsonData[$type])) {
                foreach ($jsonData[$type] as $item) {
                    $id = $this->insertSingleRecord($item);
                    if ($id > 0) {
                        $insertedCount++;
                        $this->insertedIds[] = $id;
                    }
                }
            }
        }

        $this->logger->info("💾 Records inserted: {$insertedCount}");
        return $insertedCount;
    }

    /**
     * Insert single record into syncdata
     */
    private function insertSingleRecord(array $data): int {
        try {
            return $this->db->insertSyncRecord([
                'sync_type' => $data['sync_type'],
                'item_type' => $data['item_type'],
                'sync_tries' => (int)$data['sync_tries'],
                'item_data' => json_encode($data['item_data']),
                'sync_params' => json_encode($data['sync_params'])
            ]);
        } catch (Exception $e) {
            $this->logger->error("❌ Error inserting record: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Wait for cron processing
     */
    public function waitForCronProcessing(int $minutes): void {
        $this->logger->info("⏰ Waiting {$minutes} minutes for cron processing...");

        $startTime = time();
        $endTime = $startTime + ($minutes * 60);

        while (time() < $endTime) {
            $remaining = $this->getRemainingRecords();

            if ($remaining === 0) {
                $elapsed = time() - $startTime;
                $this->logger->info("✅ Processing completed in {$elapsed} seconds");
                return;
            }

            $elapsed = time() - $startTime;
            $remainingTime = $endTime - time();
            $this->logger->debug("⏳ Pending records: {$remaining} | Elapsed: {$elapsed}s | Remaining: {$remainingTime}s");

            sleep(30); // Check every 30 seconds
        }

        $this->logger->warning("⚠️ Wait time expired");
    }

    /**
     * Get remaining records count
     */
    private function getRemainingRecords(): int {
        if (empty($this->insertedIds)) {
            return 0;
        }

        return $this->db->getRecordCount("id IN (" . implode(',', $this->insertedIds) . ")");
    }

}

// ============================================================================
// MAIN ORCHESTRATOR
// ============================================================================

/**
 * Main SalesLayer AutoTest class - Orchestrates all testing operations
 */
class SalesLayerAutoTest {
    private DatabaseManager $db;
    private DataReader $dataReader;
    private DataProcessor $dataProcessor;
    private AttributeManager $attributeManager;
    private ProductValidator $productValidator;
    private CategoryValidator $categoryValidator;
    private VariantValidator $variantValidator;
    private Logger $logger;
    private int $testStartTime;

    public function __construct() {
        $this->validateEnvironment();
        $this->initializeComponents();
        $this->testStartTime = time();

        $this->logger->info("🚀 SalesLayer AutoTest started - " . date('Y-m-d H:i:s'));
        $this->logger->info("📊 Syncdata table: " . TestConfig::getSyncdataTable());
    }

    /**
     * Validate WordPress environment
     */
    private function validateEnvironment(): void {
        if (!function_exists('wp_get_current_user')) {
            throw new Exception("This class requires WordPress. Run from WordPress context.");
        }

        if (!defined('SLYR_WC_syncdata_table')) {
            throw new Exception("SalesLayer plugin is not active or not installed");
        }
    }

    /**
     * Initialize all components
     */
    private function initializeComponents(): void {
        $this->logger = new Logger();
        $this->db = new DatabaseManager($this->logger);
        $this->dataReader = new DataReader($this->logger);
        $this->dataProcessor = new DataProcessor($this->db, $this->logger);
        $this->attributeManager = new AttributeManager($this->logger);
        $this->productValidator = new ProductValidator($this->db, $this->logger);
        $this->categoryValidator = new CategoryValidator($this->db, $this->logger);
        $this->variantValidator = new VariantValidator($this->db, $this->logger);
    }

    /**
     * Run complete automated test
     */
    public function run_complete_test(int $cronWaitMinutes = 10, bool $cleanupAfter = true): array {
        $this->logger->info("📋 Starting complete automated test");

        $result = [
            'success' => false,
            'steps' => [],
            'inserted_count' => 0,
            'processed_count' => 0,
            'validation_results' => [],
            'errors' => [],
            'total_time' => 0
        ];

        try {
            // Step 1: Read JSON data
            $this->logger->info("📁 STEP 1: Reading JSON files...");
            $jsonData = $this->dataReader->readMockupData();
            $result['steps']['read_json'] = !empty($jsonData);

            if (empty($jsonData)) {
                throw new Exception("No valid JSON files found");
            }

            // Step 2: Setup attributes
            $this->logger->info("🔧 STEP 2: Setting up WooCommerce attributes...");
            $attributesReady = $this->attributeManager->ensureProductAttributes();
            $result['steps']['setup_attributes'] = $attributesReady;

            if (!$attributesReady) {
                throw new Exception("Error setting up WooCommerce attributes");
            }

            // Step 3: Insert data
            $this->logger->info("💾 STEP 3: Inserting data into syncdata...");
            $insertedCount = $this->dataProcessor->insertSyncData($jsonData);
            $result['inserted_count'] = $insertedCount;
            $result['steps']['insert_data'] = $insertedCount > 0;

            if ($insertedCount === 0) {
                throw new Exception("Could not insert data into syncdata");
            }

            // Step 4: Wait for cron processing
            $this->logger->info("⏰ STEP 4: Waiting for cron processing ({$cronWaitMinutes} minutes)...");
            $this->dataProcessor->waitForCronProcessing($cronWaitMinutes);
            $result['steps']['cron_wait'] = true;

            // Step 5: Validate processed data
            $this->logger->info("🔍 STEP 5: Validating processed data...");
            $validationResults = $this->validateProcessedData($jsonData);
            $result['validation_results'] = $validationResults;
            $result['processed_count'] = $validationResults['processed_count'];
            $result['steps']['validate_data'] = $validationResults['success'];

            // Step 6: Cleanup (optional)
            if ($cleanupAfter && $validationResults['success']) {
                $this->logger->info("🗑️ STEP 6: Cleaning up processed data...");
                $cleanupResults = $this->cleanupProcessedData($jsonData);
                $result['cleanup_results'] = $cleanupResults;
                $result['steps']['cleanup_data'] = $cleanupResults['success'];

                if ($cleanupResults['success']) {
                    $this->logger->info("✅ Processed data cleaned successfully - {$cleanupResults['total_deleted']} elements");
                }
            } else if ($cleanupAfter && !$validationResults['success']) {
                $this->logger->warning("⚠️ Skipping cleanup due to validation errors");
                $result['steps']['cleanup_data'] = false;
            } else {
                $this->logger->info("💡 Data cleanup disabled");
                $result['steps']['cleanup_data'] = null;
            }

            // Determine overall success
            $result['success'] = $result['steps']['read_json'] &&
                               $result['steps']['setup_attributes'] &&
                               $result['steps']['insert_data'] &&
                               $result['steps']['validate_data'] &&
                               ($result['steps']['cleanup_data'] !== false);

            $result['total_time'] = time() - $this->testStartTime;

            if ($result['success']) {
                $this->logger->info("🎉 Complete test SUCCESSFUL in {$result['total_time']} seconds");
            } else {
                $this->logger->error("❌ Complete test FAILED");
            }

        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            $this->logger->error("❌ Test error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Validate processed data
     */
    public function validateProcessedData(array $jsonData): array {
        $this->logger->info("🔍 Validating processed data in WordPress/WooCommerce...");

        $result = [
            'success' => true,
            'processed_count' => 0,
            'categories_validated' => 0,
            'products_validated' => 0,
            'variants_validated' => 0,
            'errors' => []
        ];

        try {
            // Validate categories
            foreach ($jsonData['categories'] ?? [] as $categoryData) {
                if ($this->categoryValidator->validate($categoryData)) {
                    $result['categories_validated']++;
                    $result['processed_count']++;
                } else {
                    $errors = $this->categoryValidator->getErrors();
                    $result['errors'] = array_merge($result['errors'], $errors);
                    $result['success'] = false;
                }
            }

            // Validate products
            foreach ($jsonData['products'] ?? [] as $productData) {
                if ($this->productValidator->validate($productData)) {
                    $result['products_validated']++;
                    $result['processed_count']++;
                } else {
                    $errors = $this->productValidator->getErrors();
                    $result['errors'] = array_merge($result['errors'], $errors);
                    $result['success'] = false;
                }
            }

            // Validate variants
            foreach ($jsonData['variants'] ?? [] as $variantData) {
                if ($this->variantValidator->validate($variantData)) {
                    $result['variants_validated']++;
                    $result['processed_count']++;
                } else {
                    $errors = $this->variantValidator->getErrors();
                    $result['errors'] = array_merge($result['errors'], $errors);
                    $result['success'] = false;
                }
            }

            $this->logger->info("✅ Validation completed: {$result['processed_count']} elements validated");

        } catch (Exception $e) {
            $result['errors'][] = "Error during validation: " . $e->getMessage();
            $result['success'] = false;
            $this->logger->error("❌ Validation error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Clean up processed data from WordPress
     */
    public function cleanupProcessedData(array $jsonData): array {
        $this->logger->info("🗑️ Starting cleanup of processed data...");

        $result = [
            'success' => true,
            'categories_deleted' => 0,
            'products_deleted' => 0,
            'variants_deleted' => 0,
            'total_deleted' => 0,
            'errors' => []
        ];

        try {
            // Delete variants first (they depend on products)
            foreach ($jsonData['variants'] ?? [] as $variantData) {
                if ($this->deleteVariant($variantData)) {
                    $result['variants_deleted']++;
                    $result['total_deleted']++;
                }
            }

            // Delete products
            foreach ($jsonData['products'] ?? [] as $productData) {
                if ($this->deleteProduct($productData)) {
                    $result['products_deleted']++;
                    $result['total_deleted']++;
                }
            }

            // Delete categories last
            foreach ($jsonData['categories'] ?? [] as $categoryData) {
                if ($this->deleteCategory($categoryData)) {
                    $result['categories_deleted']++;
                    $result['total_deleted']++;
                }
            }

            $this->logger->info("✅ Cleanup completed: {$result['total_deleted']} elements deleted");

        } catch (Exception $e) {
            $result['errors'][] = "Error during cleanup: " . $e->getMessage();
            $result['success'] = false;
            $this->logger->error("❌ Cleanup error: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Delete a category
     */
    private function deleteCategory(array $categoryData): bool {
        $slId = $categoryData['item_data']['ID'];
        $compId = $categoryData['sync_params']['conn_params']['comp_id'];

        $termId = $this->db->findCategoryBySalesLayerId($slId, $compId);
        if (!$termId) {
            $this->logger->debug("Category not found for deletion: SL ID {$slId}");
            return false;
        }

        $result = wp_delete_term($termId, 'product_cat');
        if (is_wp_error($result)) {
            $this->logger->error("Error deleting category {$termId}: " . $result->get_error_message());
            return false;
        }

        $this->logger->debug("✅ Category deleted: {$termId}");
        return true;
    }

    /**
     * Delete a product
     */
    private function deleteProduct(array $productData): bool {
        $slId = $productData['item_data']['ID'];
        $compId = $productData['sync_params']['conn_params']['comp_id'];

        $postId = $this->db->findProductBySalesLayerId($slId, $compId);
        if (!$postId) {
            $this->logger->debug("Product not found for deletion: SL ID {$slId}");
            return false;
        }

        $result = wp_delete_post($postId, true);
        if (!$result) {
            $this->logger->error("Error deleting product {$postId}");
            return false;
        }

        $this->logger->debug("✅ Product deleted: {$postId}");
        return true;
    }

    /**
     * Delete a variant
     */
    private function deleteVariant(array $variantData): bool {
        $slId = $variantData['item_data']['format_data']['ID_products'];
        $compId = $variantData['sync_params']['conn_params']['comp_id'];
        $formatId = $variantData['item_data']['format_data']['ID'];

        $postId = $this->db->findVariantBySalesLayerId($slId, $compId, $formatId);
        if (!$postId) {
            $this->logger->debug("Variant not found for deletion: SL ID {$slId}, Format ID {$formatId}");
            return false;
        }

        $result = wp_delete_post($postId, true);
        if (!$result) {
            $this->logger->error("Error deleting variant {$postId}");
            return false;
        }

        $this->logger->debug("✅ Variant deleted: {$postId}");
        return true;
    }

    // ========================================================================
    // UTILITY METHODS (for backward compatibility)
    // ========================================================================

    /**
     * Read mockup data (backward compatibility)
     */
    public function read_mockup_data(): array {
        return $this->dataReader->readMockupData();
    }

    /**
     * Ensure product attributes (backward compatibility)
     */
    public function ensure_product_attributes(): bool {
        return $this->attributeManager->ensureProductAttributes();
    }

    /**
     * Cleanup all data from slyr_wc_api_syncdata table
     *
     * Eliminates ALL records from the synchronization table.
     * Use with caution as this will remove all pending and processed data.
     */
    public function cleanup_test_data(): bool {
        $this->logger->info("🧹 Cleaning up ALL data from slyr_wc_api_syncdata...");

        // Get total count before deletion
        $totalCount = $this->db->getRecordCount();
        if ($totalCount === 0) {
            $this->logger->info("✅ No data found to clean - table is already empty");
            return true;
        }

        $this->logger->info("📊 Found {$totalCount} total records to delete");

        try {
            // Delete ALL records from the table
            $deleted = $this->db->deleteAllRecords();

            if ($deleted !== false) {
                $this->logger->info("✅ Successfully deleted {$totalCount} records from slyr_wc_api_syncdata");
                return true;
            } else {
                $this->logger->error("❌ Failed to delete records from slyr_wc_api_syncdata");
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("❌ Error during cleanup: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all records count
     *
     * @return array Associative array with records count
     */
    public function get_all_records_count(): array {
        return [
            'total' => $this->db->getRecordCount()
        ];
    }

    /**
     * Show system report
     */
    public function show_system_report(): bool {
        $this->logger->info("📊 SALESLAYER SYSTEM REPORT");
        $this->logger->info("=================================");

        $stats = $this->db->getTableStats();

        foreach ($stats as $key => $value) {
            $this->logger->info("📊 " . ucfirst(str_replace('_', ' ', $key)) . ": " . number_format($value));
        }

        return true;
    }

    /**
     * Validate environment
     */
    public function validate_environment(): bool {
        $this->logger->info("🔍 Validating environment for tests...");

        $checks = [
            'WordPress' => function_exists('wp_get_current_user'),
            'WooCommerce' => class_exists('WooCommerce'),
            'SalesLayer Plugin' => defined('SLYR_WC_syncdata_table'),
            'Database' => isset($GLOBALS['wpdb']) && is_object($GLOBALS['wpdb'])
        ];

        $allPassed = true;
        foreach ($checks as $check => $result) {
            if ($result) {
                $this->logger->info("✅ {$check}: OK");
            } else {
                $this->logger->error("❌ {$check}: FAILED");
                $allPassed = false;
            }
        }

        return $allPassed;
    }

    /**
     * Validate logs (placeholder)
     */
    /**
     * Validate system logs
     *
     * @return array Validation results with success, errors, warnings, details
     */
    public function validate_logs(): array {
        $this->logger->info("📄 Validating system logs...");

        $result = [
            'success' => true,
            'errors' => [],
            'warnings' => [],
            'details' => []
        ];

        // Validate different log types
        $this->validateSalesLayerLogs($result);
        $this->validateWordPressLogs($result);
        $this->validateApacheErrorLogs($result);
        $this->validateApacheAccessLogs($result);

        // Determine final result
        $result['success'] = empty($result['errors']);

        $this->logger->info($result['success'] ?
            "✅ Log validation completed successfully" :
            "❌ Log validation completed with errors"
        );

        if (!empty($result['errors'])) {
            $this->logger->error("Errors:  ".print_r($result['errors'],1));
        }

        return $result;
    }

    /**
     * Validate SalesLayer specific logs
     *
     * @param array &$result Results array by reference
     */
    private function validateSalesLayerLogs(array &$result): void {
        $this->logger->debug("🔍 Validating SalesLayer logs...");

        $logPaths = [
            TestConfig::getLogsDir(),
            '../logs/',
            'tests/logs/',
            'logs/',
            '/var/log/saleslayer/',
            '/tmp/saleslayer_logs/'
        ];

        foreach ($logPaths as $path) {
            if (!is_dir($path)) continue;

            $logFiles = glob($path . '*.{log,dat}', GLOB_BRACE);
            if (empty($logFiles)) continue;

            $result['details'][] = "SalesLayer logs found in: {$path}";

            foreach ($logFiles as $logFile) {
                $this->validateLogFile($logFile, 'SalesLayer', $result);
            }
            return; // Found logs, exit early
        }

        $result['warnings'][] = "No SalesLayer logs found in expected locations";
    }

    /**
     * Validate WordPress logs
     *
     * @param array &$result Results array by reference
     */
    private function validateWordPressLogs(array &$result): void {
        $this->logger->debug("🔍 Validating WordPress logs...");

        $wpLogPaths = array_unique(array_filter([
            WP_CONTENT_DIR . '/debug.log',
            ABSPATH . 'wp-content/debug.log',
            '/var/log/wordpress/debug.log',
            '/var/log/wordpress/error.log',
            ini_get('error_log')
        ]));

        foreach ($wpLogPaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                $result['details'][] = "WordPress log found: {$logPath}";
                $this->validateLogFile($logPath, 'WordPress', $result);
                return; // Found at least one log
            }
        }

        $result['warnings'][] = "No WordPress logs found";
    }

    /**
     * Validate Apache/PHP error logs
     *
     * @param array &$result Results array by reference
     */
    private function validateApacheErrorLogs(array &$result): void {
        $this->logger->debug("🔍 Validating Apache/PHP error logs...");

        $apacheErrorPaths = array_filter([
            '/var/log/apache2/error.log',
            '/var/log/httpd/error_log',
            '/var/log/apache/error.log',
            '/usr/local/var/log/apache2/error.log',
            '/opt/lampp/logs/error_log',
            '/xampp/apache/logs/error.log',
            '/var/log/wordpress/php_errors.log', // Docker WordPress
            ini_get('error_log')
        ]);

        foreach ($apacheErrorPaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                $result['details'][] = "Apache error log found: {$logPath}";
                $this->validateApacheErrorLogContent($logPath, $result);
            }
        }

        if (empty(array_filter($apacheErrorPaths, fn($path) => file_exists($path) && is_readable($path)))) {
            $result['warnings'][] = "No accessible Apache error logs found";
        }
    }

    /**
     * Validate Apache access logs (optional)
     *
     * @param array &$result Results array by reference
     */
    private function validateApacheAccessLogs(array &$result): void {
        $this->logger->debug("🔍 Validating Apache access logs...");

        $apacheAccessPaths = [
            '/var/log/apache2/access.log',
            '/var/log/httpd/access_log',
            '/var/log/apache/access.log',
            '/usr/local/var/log/apache2/access.log',
            '/opt/lampp/logs/access_log',
            '/xampp/apache/logs/access.log'
        ];

        foreach ($apacheAccessPaths as $logPath) {
            if (file_exists($logPath) && is_readable($logPath)) {
                $result['details'][] = "Apache access log found: {$logPath}";
                $this->validateApacheAccessLogContent($logPath, $result);
                return; // Only validate first found
            }
        }

        $result['details'][] = "Apache access logs not found (optional)";
    }

    /**
     * Validate generic log file content
     *
     * @param string $logFile Log file path
     * @param string $logType Log type (SalesLayer, WordPress, etc.)
     * @param array &$result Results array by reference
     */
    private function validateLogFile(string $logFile, string $logType, array &$result): void {
        if (!is_readable($logFile)) {
            $result['errors'][] = "{$logType} log not readable: {$logFile}";
            return;
        }

        $fileSize = filesize($logFile);
        if ($fileSize === 0) {
            $result['warnings'][] = "{$logType} log is empty: {$logFile}";
            return;
        }

        $lines = $this->tailFile($logFile, 100);
        if (empty($lines)) {
            $result['warnings'][] = "{$logType} log contains no readable lines: {$logFile}";
            return;
        }

        $result['details'][] = "{$logType} log validated: {$fileSize} bytes, " . count($lines) . " recent lines";

        // Count critical errors in recent lines
        $criticalErrors = 0;
        foreach ($lines as $line) {
            if (preg_match('/\b(FATAL|CRITICAL|ERROR|EXCEPTION)\b/i', $line)) {
                $criticalErrors++;
            }
        }

        if ($criticalErrors > 0) {
            $result['warnings'][] = "{$logType} log contains {$criticalErrors} recent critical errors";
        }
    }

    /**
     * Validate Apache error log content specifically
     *
     * @param string $logFile Apache log file path
     * @param array &$result Results array by reference
     */
    private function validateApacheErrorLogContent(string $logFile, array &$result): void {
        if (!is_readable($logFile)) {
            $result['errors'][] = "Apache error log not readable: {$logFile}";
            return;
        }

        $lines = $this->tailFile($logFile, 200);
        if (empty($lines)) {
            $result['details'][] = "Apache error log is empty (good sign)";
            return;
        }

        // Error counters
        $errorCounts = [
            'php_fatal' => 0,
            'php_warnings' => 0,
            'apache_errors' => 0,
            'saleslayer_errors' => 0,
            'recent_errors' => 0
        ];

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        foreach ($lines as $line) {
            if (preg_match('/PHP Fatal error/i', $line)) {
                $errorCounts['php_fatal']++;
                if (strpos($line, $today) !== false || strpos($line, $yesterday) !== false) {
                    $errorCounts['recent_errors']++;
                }
            }

            if (preg_match('/PHP Warning/i', $line)) {
                $errorCounts['php_warnings']++;
            }

            if (preg_match('/\[error\]|\[crit\]/i', $line)) {
                $errorCounts['apache_errors']++;
            }

            if (preg_match('/saleslayer|woocommerce.*saleslayer/i', $line)) {
                $errorCounts['saleslayer_errors']++;
            }
        }

        // Report findings
        if ($errorCounts['php_fatal'] > 0) {
            $result['errors'][] = "IMPORTANT - POSSIBLE ERROR: {$errorCounts['php_fatal']} PHP fatal errors found in Apache logs";
            if ($errorCounts['recent_errors'] > 0) {
                $result['errors'][] = "IMPORTANT - POSSIBLE ERROR: {$errorCounts['recent_errors']} recent fatal errors (last 24h)";
            }
        }

        if ($errorCounts['php_warnings'] > 10) {
            $result['warnings'][] = "Many PHP warnings found: {$errorCounts['php_warnings']}";
        }

        if ($errorCounts['apache_errors'] > 0) {
            $result['warnings'][] = "Apache errors found: {$errorCounts['apache_errors']}";
        }

        if ($errorCounts['saleslayer_errors'] > 0) {
            $result['warnings'][] = "SalesLayer related errors: {$errorCounts['saleslayer_errors']}";
        }

        if ($errorCounts['php_fatal'] === 0 && $errorCounts['apache_errors'] === 0) {
            $result['details'][] = "Apache error log: no critical errors";
        }
    }

    /**
     * Validate Apache access log content
     *
     * @param string $logFile Apache access log file path
     * @param array &$result Results array by reference
     */
    private function validateApacheAccessLogContent(string $logFile, array &$result): void {
        $lines = $this->tailFile($logFile, 50);

        if (empty($lines)) {
            $result['details'][] = "Apache access log is empty";
            return;
        }

        $errorCodes = 0;
        $salesLayerRequests = 0;

        foreach ($lines as $line) {
            // Look for HTTP error codes (4xx, 5xx)
            if (preg_match('/" [45]\d\d /', $line)) {
                $errorCodes++;
            }

            // Look for SalesLayer related requests
            if (preg_match('/saleslayer|woocommerce.*sync/i', $line)) {
                $salesLayerRequests++;
            }
        }

        if ($errorCodes > 10) {
            $result['warnings'][] = "Many HTTP error codes in recent access: {$errorCodes}";
        }

        if ($salesLayerRequests > 0) {
            $result['details'][] = "SalesLayer related requests: {$salesLayerRequests}";
        }

        $result['details'][] = "Apache access log: " . count($lines) . " recent requests analyzed";
    }

    /**
     * Read last N lines from a file efficiently
     *
     * @param string $file File path
     * @param int $lines Number of lines to read
     * @return array Array of lines
     */
    private function tailFile(string $file, int $lines = 100): array {
        if (!file_exists($file) || !is_readable($file)) {
            return [];
        }

        try {
            $handle = fopen($file, 'r');
            if (!$handle) {
                return [];
            }

            $fileSize = filesize($file);

            // For small files, read everything
            if ($fileSize < 8192) {
                $content = file_get_contents($file);
                fclose($handle);
                return array_filter(explode("\n", $content));
            }

            // For large files, use optimized tail
            $buffer = 4096;
            $output = '';

            fseek($handle, -1, SEEK_END);

            if (fread($handle, 1) != "\n") {
                $lines -= 1;
            }

            while (ftell($handle) > 0 && $lines >= 0) {
                $seek = min(ftell($handle), $buffer);
                fseek($handle, -$seek, SEEK_CUR);
                $chunk = fread($handle, $seek);
                $output = $chunk . $output;
                fseek($handle, -mb_strlen($chunk, '8bit'), SEEK_CUR);
                $lines -= substr_count($chunk, "\n");
            }

            fclose($handle);
            return array_filter(explode("\n", $output));

        } catch (Exception $e) {
            $this->logger->error("Error reading file {$file}: " . $e->getMessage());
            return [];
        }
    }
}
