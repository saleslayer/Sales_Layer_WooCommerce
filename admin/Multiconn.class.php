<?php

/**
 * Multi-Connector Reference Counting System.
 *
 * Tracks which connectors reference each SalesLayer item on each site
 * to prevent premature deletion when multiple connectors share the
 * same comp_id. Uses a normalized table (one row per connector-item-site)
 * with atomic INSERT IGNORE / DELETE operations to avoid race conditions.
 *
 * In multisite environments, each (connector, item, site) combination
 * is tracked independently, so deletions are evaluated per-site rather
 * than globally across all sites.
 */
class Multiconn
{

    private static $instance;
    protected $db;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Get singleton instance.
     *
     * @return self
     */
    public static function &get_instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new Multiconn();
        }
        return self::$instance;
    }

    /**
     * Create the multiconn table if it does not exist.
     *
     * Schema: normalized design — one row per connector-item-site tuple.
     * UNIQUE index prevents duplicates and enables INSERT IGNORE.
     * The blog_id column tracks which WordPress site the item belongs to,
     * enabling per-site deletion decisions in multisite environments.
     *
     * @return void
     */
    public function create_table(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . SLYR_WC_multiconn_table . "` (" .
            "`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id', " .
            "`item_type` varchar(30) NOT NULL COMMENT 'Item Type (category, product, product_format)', " .
            "`sl_id` varchar(32) NOT NULL COMMENT 'SalesLayer Item ID', " .
            "`sl_comp_id` int(20) NOT NULL COMMENT 'SalesLayer Company ID', " .
            "`conn_codename` varchar(32) NOT NULL COMMENT 'Connector codename (conn_code)', " .
            "`blog_id` bigint(20) NOT NULL DEFAULT 1 COMMENT 'WordPress site ID (blog_id)', " .
            "PRIMARY KEY (`id`), " .
            "UNIQUE KEY `idx_multiconn_unique` (`item_type`, `sl_id`, `sl_comp_id`, `conn_codename`, `blog_id`)" .
            ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Sales Layer Multi-Connector Reference Table'"
        );
    }

    /**
     * Check if the multiconn table exists and create it if missing.
     *
     * @return void
     */
    public function check_table(): void
    {
        $tableName = SLYR_WC_multiconn_table;
        $result = $this->db->get_var(
            $this->db->prepare("SHOW TABLES LIKE %s", $tableName)
        );

        if ($result !== $tableName) {
            $this->create_table();
        }
    }

    /**
     * Register a connector's interest in an item on a specific site.
     *
     * Uses INSERT IGNORE — if the row already exists (same item_type,
     * sl_id, sl_comp_id, conn_codename, blog_id), the operation is
     * silently ignored. This is a single atomic query with no
     * read-modify-write.
     *
     * @param string $itemType     Item type (category, product, product_format)
     * @param string $slId         SalesLayer item ID
     * @param int    $compId       SalesLayer company ID
     * @param string $connCodename Connector codename (conn_code)
     * @param int    $blogId       WordPress site ID (blog_id)
     * @return void
     */
    public function register_connector(string $itemType, string $slId, int $compId, string $connCodename, int $blogId): void
    {
        $sql = $this->db->prepare(
            "INSERT IGNORE INTO `" . SLYR_WC_multiconn_table . "` " .
            "(item_type, sl_id, sl_comp_id, conn_codename, blog_id) VALUES (%s, %s, %d, %s, %d)",
            $itemType,
            $slId,
            $compId,
            $connCodename,
            $blogId
        );

        $this->db->query($sql);
    }

    /**
     * Unregister a connector's interest in an item on a specific site
     * and check if deletion is safe on that site.
     *
     * Removes the connector's row for the given blog_id, then counts
     * remaining references for the same item on the same site.
     * Returns true if no other connector references this item on this
     * site (safe to delete on this site).
     * Returns false if other connectors still need this item on this
     * site (do NOT delete on this site).
     *
     * @param string $itemType     Item type (category, product, product_format)
     * @param string $slId         SalesLayer item ID
     * @param int    $compId       SalesLayer company ID
     * @param string $connCodename Connector codename (conn_code)
     * @param int    $blogId       WordPress site ID (blog_id)
     * @return bool True if item can be safely deleted on this site, false if other connectors need it
     */
    public function unregister_connector(string $itemType, string $slId, int $compId, string $connCodename, int $blogId): bool
    {
        // Step 1: Remove this connector's reference for this specific site
        $deleteSql = $this->db->prepare(
            "DELETE FROM `" . SLYR_WC_multiconn_table . "` " .
            "WHERE item_type = %s AND sl_id = %s AND sl_comp_id = %d AND conn_codename = %s AND blog_id = %d",
            $itemType,
            $slId,
            $compId,
            $connCodename,
            $blogId
        );

        $this->db->query($deleteSql);

        // Step 2: Count remaining references from other connectors on THIS site
        $countSql = $this->db->prepare(
            "SELECT COUNT(*) FROM `" . SLYR_WC_multiconn_table . "` " .
            "WHERE item_type = %s AND sl_id = %s AND sl_comp_id = %d AND blog_id = %d",
            $itemType,
            $slId,
            $compId,
            $blogId
        );

        $remainingCount = (int) $this->db->get_var($countSql);

        return $remainingCount === 0;
    }

    /**
     * Remove all references for a specific connector within a company.
     *
     * Called when a connector is completely deleted from the system.
     * Single atomic DELETE query — no iteration needed.
     *
     * @param string $connCodename Connector codename (conn_code)
     * @param int    $compId       SalesLayer company ID
     * @return void
     */
    public function purge_connector(string $connCodename, int $compId): void
    {
        $sql = $this->db->prepare(
            "DELETE FROM `" . SLYR_WC_multiconn_table . "` " .
            "WHERE conn_codename = %s AND sl_comp_id = %d",
            $connCodename,
            $compId
        );

        $this->db->query($sql);
    }

    /**
     * Remove multiconn records whose blog_id is no longer an active
     * target in the connector's multisite configuration.
     *
     * For each connector in slyr_wc_api_config:
     * 1. Reads active multisite_targets from conn_extra JSON
     * 2. Collects blog_ids marked as active
     * 3. Deletes multiconn rows for that connector where blog_id
     *    is NOT in the active list
     *
     * Connectors without multisite_targets are skipped (legacy/single-site).
     *
     * @return int Total number of orphaned rows deleted across all connectors
     */
    public function purgeOrphanedBlogIds(): int
    {
        $totalDeleted = 0;

        $connectors = $this->db->get_results(
            "SELECT conn_code, comp_id, conn_extra FROM " . SLYR_WC_connector_table,
            ARRAY_A
        );

        if (empty($connectors)) {
            return 0;
        }

        foreach ($connectors as $connector) {
            $connCode = $connector['conn_code'];
            $compId = (int) $connector['comp_id'];
            $connExtra = json_decode($connector['conn_extra'] ?? '{}', true);

            // Skip connectors without multisite targets (legacy/single-site)
            if (empty($connExtra['multisite_targets'])) {
                continue;
            }

            // Collect blog_ids — presence in multisite_targets = active
            // (the save handler only stores active targets, no 'active' field)
            $activeBlogIds = [];
            foreach ($connExtra['multisite_targets'] as $target) {
                if (isset($target['blog_id'])) {
                    $activeBlogIds[] = (int) $target['blog_id'];
                }
            }

            if (empty($activeBlogIds)) {
                // All targets are inactive — purge all multiconn rows for this connector
                $sql = $this->db->prepare(
                    "DELETE FROM `" . SLYR_WC_multiconn_table . "` " .
                    "WHERE conn_codename = %s AND sl_comp_id = %d",
                    $connCode,
                    $compId
                );
            } else {
                // Delete rows for blog_ids not in the active list
                $placeholders = implode(',', array_fill(0, count($activeBlogIds), '%d'));
                $sql = $this->db->prepare(
                    "DELETE FROM `" . SLYR_WC_multiconn_table . "` " .
                    "WHERE conn_codename = %s AND sl_comp_id = %d AND blog_id NOT IN (" . $placeholders . ")",
                    array_merge([$connCode, $compId], $activeBlogIds)
                );
            }

            $this->db->query($sql);
            $totalDeleted += (int) $this->db->rows_affected;
        }

        return $totalDeleted;
    }
}

