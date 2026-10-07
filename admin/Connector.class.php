<?php

/**
 * Connector repository and schema manager.
 *
 * Responsible for creating/upgrading Sales Layer connector tables and
 * providing CRUD operations on connector records.
 */
class Connector
{

    private static $connector;
    public $conn_data = array();
    protected $db;

    public function __construct()
    {

        global $wpdb;
        $this->db = $wpdb;
        
    }

    /**
     * Get singleton instance.
     * @return Connector Singleton instance
     */
    public static function &get_instance()
    {

        if (is_null(self::$connector)) {
        
            self::$connector = new Connector();
        
        }
        
        return self::$connector;

    }

    /**
     * Connector table columns (name => definition).
     * Shared by create_table() and upgrade_connector_table() so both stay in sync.
     */
    private const CONNECTOR_TABLE_COLUMNS = [
        'cnf_id'           => 'int(11) NOT NULL AUTO_INCREMENT',
        'conn_code'        => 'varchar(32) NOT NULL',
        'conn_secret'      => 'varchar(32) NOT NULL',
        'default_cat_id'   => 'int(11) NOT NULL',
        'comp_id'          => 'int(20) NOT NULL',
        'last_update'      => 'datetime DEFAULT NULL',
        'default_language' => 'varchar(6) NOT NULL',
        'languages'        => 'mediumtext NOT NULL',
        'conn_extra'       => 'mediumtext',
        'auto_sync'        => "int(3) DEFAULT '0'",
        'last_sync'        => 'datetime DEFAULT NULL',
    ];

    /**
     * Create Sales Layer table.
     *
     * No storage engine is forced: the server default is used (InnoDB on
     * most hosts). Some managed hosts disable MyISAM, which made the
     * CREATE fail silently.
     *
     * @return bool True on success
     */
    public function create_table()
    {
        $columns = array();

        foreach (self::CONNECTOR_TABLE_COLUMNS as $name => $definition) {
            $columns[] = "`{$name}` {$definition}";
        }

        return false !== $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . SLYR_WC_connector_table . "` (" .
            implode(', ', $columns) . ", " .
            "PRIMARY KEY (`cnf_id`)" .
            ") " . $this->db->get_charset_collate()
        );
    }

    /**
     * Add any connector table column missing from an existing table.
     *
     * Replaces the old DROP + CREATE + re-insert upgrade: existing rows
     * are never removed.
     *
     * @return bool True when the table has all expected columns
     */
    private function upgrade_connector_table(): bool
    {
        $existing = $this->db->get_col("SHOW COLUMNS FROM `" . SLYR_WC_connector_table . "`");

        if (empty($existing)) {
            return false;
        }

        $result = true;

        foreach (self::CONNECTOR_TABLE_COLUMNS as $name => $definition) {
            if (in_array($name, $existing, true)) {
                continue;
            }

            if (false === $this->db->query(
                "ALTER TABLE `" . SLYR_WC_connector_table . "` ADD COLUMN `{$name}` {$definition}"
            )) {
                $this->log_db_error("Adding column {$name} to " . SLYR_WC_connector_table);
                $result = false;
            }
        }

        return $result;
    }

    /**
     * Create Sales Layer sync data table.
     * @return void
     */
    public function create_syncdata_table()
    {
        
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . SLYR_WC_syncdata_table . "` (" .
            "`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id', " .
            "`sync_type` varchar(10) NOT NULL COMMENT 'Sync Type', " .
            "`item_type` varchar(30) NOT NULL COMMENT 'Item Type', " .
            "`sync_tries` int(11) NOT NULL DEFAULT '0' COMMENT 'Sync Tries', " .
            "`item_data` longtext COMMENT 'Item Data', " .
            "`sync_params` longtext COMMENT 'Sync Parameters', " .
            "PRIMARY KEY (`id`)" .
            ") " . $this->db->get_charset_collate() . " COMMENT='Sales Layer Sync Data Table'"
        );
           
    }

    /**
     * Create Sales Layer sync data flag table.
     * @return void
     */
    public function create_syncdata_flag_table()
    {       

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `" . SLYR_WC_syncdata_flag_table . "` (" .
            "`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Id', " .
            "`syncdata_pid` bigint(20) NOT NULL DEFAULT '0' COMMENT 'Sync Data Pid', " .
            "`syncdata_last_date` datetime NOT NULL COMMENT 'Sync Data Last Update', " .
            "PRIMARY KEY (`id`)" .
            ") " . $this->db->get_charset_collate() . " COMMENT='Sales Layer Sync Data Flag Table'"
        );
    
    }

    /**
     * Check if Sales Layer table exists.
     * @return void
     */
    public function check_table()
    {
        
        if (!($this->db->get_var("SHOW TABLES LIKE '" . SLYR_WC_connector_table . "'"))) {

            $this->create_table();
        
        }

    }

    /**
     * Check if Sales Layer sync data table exists. 
     * @return void
     */
    public function check_syncdata_table()
    {

        if (!($this->db->get_var("SHOW TABLES LIKE '" . SLYR_WC_syncdata_table . "'"))) {

            $this->create_syncdata_table();

        }

    }

    /**
     * Check if Sales Layer sync data flag table exists. 
     * @return void
     */
    public function check_syncdata_flag_table()
    {

        if (!($this->db->get_var("SHOW TABLES LIKE '" . SLYR_WC_syncdata_flag_table . "'"))) {

            $this->create_syncdata_flag_table();

        }

    }

    /**
     * Migrate old unprefixed tables to new prefixed names.
     *
     * Prior to v2.5.4, plugin tables were created without the WordPress
     * database prefix (e.g. 'slyr_wc_api_config' instead of
     * 'wp_slyr_wc_api_config'). This method renames them in-place so
     * that existing data is preserved without any copy/re-insert.
     *
     * Safe to call multiple times: it only renames when the old table
     * exists AND the new prefixed table does NOT exist yet.
     *
     * @return bool True when every rename/cleanup succeeded
     */
    private function migrate_tables_to_prefixed(): bool
    {
        $migrations = [
            [
                'old' => SLYR_WC_connector_table_base,
                'new' => SLYR_WC_connector_table,
            ],
            [
                'old' => SLYR_WC_syncdata_table_base,
                'new' => SLYR_WC_syncdata_table,
            ],
            [
                'old' => SLYR_WC_syncdata_flag_table_base,
                'new' => SLYR_WC_syncdata_flag_table,
            ],
            [
                'old' => SLYR_WC_multiconn_table_base,
                'new' => SLYR_WC_multiconn_table,
            ],
        ];

        $result = true;

        foreach ($migrations as $migration) {
            $oldExists = $this->table_exists($migration['old']);
            $newExists = $this->table_exists($migration['new']);

            if ($oldExists && $newExists) {
                // Both exist (edge case: partial migration or manual intervention).
                // Keep the legacy data if the prefixed table is empty; otherwise
                // drop the old unprefixed table to avoid confusion.
                $oldRows = (int) $this->db->get_var("SELECT COUNT(*) FROM `{$migration['old']}`");
                $newRows = (int) $this->db->get_var("SELECT COUNT(*) FROM `{$migration['new']}`");

                if ($oldRows > 0 && $newRows === 0) {
                    if (false === $this->db->query("DROP TABLE `{$migration['new']}`")) {
                        $this->log_db_error("Dropping empty table {$migration['new']}");
                        $result = false;
                        continue;
                    }
                    $newExists = false;
                } else {
                    if (false === $this->db->query("DROP TABLE IF EXISTS `{$migration['old']}`")) {
                        $this->log_db_error("Dropping legacy table {$migration['old']}");
                        $result = false;
                    }
                    continue;
                }
            }

            if ($oldExists && !$newExists) {
                // RENAME TABLE is atomic in MySQL/MariaDB — no data loss risk
                if (false === $this->db->query(
                    "RENAME TABLE `{$migration['old']}` TO `{$migration['new']}`"
                )) {
                    $this->log_db_error("Renaming {$migration['old']} to {$migration['new']}");
                    $result = false;
                }
            }
        }

        return $result;
    }

    /**
     * Check whether a table exists.
     * @param string $table_name Table name
     * @return bool
     */
    private function table_exists(string $table_name): bool
    {
        return $this->db->get_var(
            $this->db->prepare("SHOW TABLES LIKE %s", $this->db->esc_like($table_name))
        ) === $table_name;
    }

    /**
     * Log the last database error to the PHP/WordPress error log.
     *
     * sl_debug() cannot be used here: check_version() runs before the
     * plugin debug level is loaded.
     *
     * @param string $context What was being done
     * @return void
     */
    private function log_db_error(string $context): void
    {
        error_log('[Sales Layer WooCommerce] ' . $context . ' failed: ' . $this->db->last_error);
    }

    /**
     * Check Sales Layer plugin version.
     *
     * On upgrade, legacy tables are renamed and missing columns are added;
     * no table holding data is dropped. The stored version is only updated
     * once every table is in place, so a failed upgrade is retried on the
     * next request instead of being marked as done.
     *
     * @return void
     */
    public function check_version()
    {

        $ver = get_site_option('SLYR_WC_version');
        $needs_upgrade = ($ver === false || version_compare((string) $ver, (string) SLYR_WC_version, '<'));
        $upgrade_ok = true;

        if ($needs_upgrade) {
            // v2.5.4: Rename old unprefixed tables to prefixed names
            // before any CREATE logic runs on the new names
            $upgrade_ok = $this->migrate_tables_to_prefixed();
        }

        $this->check_table();

        if ($needs_upgrade) {
            $upgrade_ok = $this->upgrade_connector_table() && $upgrade_ok;
        }

        $this->check_syncdata_table();
        $this->check_syncdata_flag_table();

        $multiconn = Multiconn::get_instance();
        $multiconn->check_table();

        if ($needs_upgrade) {

            foreach (array(
                SLYR_WC_connector_table,
                SLYR_WC_syncdata_table,
                SLYR_WC_syncdata_flag_table,
                SLYR_WC_multiconn_table,
            ) as $table_name) {
                if (!$this->table_exists($table_name)) {
                    $this->log_db_error("Creating table {$table_name}");
                    $upgrade_ok = false;
                }
            }

            if ($upgrade_ok) {
                update_site_option('SLYR_WC_version', SLYR_WC_version);
            } else {
                error_log(
                    '[Sales Layer WooCommerce] Upgrade from ' . ($ver === false ? 'none' : $ver) .
                    ' to ' . SLYR_WC_version . ' incomplete; it will be retried on the next request.'
                );
            }

        }

    }

    /**
     * Get connector row(s).
     * @param string|null $connector_id Connector id to get data; when null returns all
     * @return object|array Single row (object) or a list of rows (array)
     */
    public function get_connector(?string $connector_id = null)
    {

        if (!is_null($connector_id) && $this->check_connector($connector_id)) {
        
            $stmt = $this->db->prepare(
                "SELECT * FROM " . SLYR_WC_connector_table . " WHERE conn_code IN (%s) ",
                $connector_id
            );

            return $this->db->get_row($stmt);
            
        }

        return $this->db->get_results('SELECT * FROM ' . SLYR_WC_connector_table . ' ORDER BY cnf_id');

    }

    /**
     * Check if a connector exists.
     * @param string $connector_id              connector id
     * @return bool Result of check
     */
    public function check_connector($connector_id)
    {

        $stmt = $this->db->prepare(
            "SELECT * FROM " . SLYR_WC_connector_table . " WHERE conn_code IN (%s)",
            $connector_id
        );     
        
        return (!empty($this->db->get_results($stmt)));

    }

    /**
     * Add a connector to the Sales Layer table.
     *
     * The connector is always initialized with the main site as the
     * default active target. This enables a unified sync flow where
     * both single-site and multisite iterate through targets.
     *
     * @param string $connector_id Connector id
     * @param string $secret_key   Connector secret key
     * @return int|false Number of rows affected or false on failure
     */
    public function add_connector($connector_id, $secret_key)
    {
        $connExtra = json_encode([
            'multisite_targets' => [
                [
                    'blog_id'   => (int) get_main_site_id(),
                    'lang_code' => '',
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare(
            "INSERT INTO `" . SLYR_WC_connector_table . "` (" .
            "conn_code, conn_secret, default_cat_id, comp_id, last_update, " .
            "default_language, languages, conn_extra" .
            ") VALUES (%s, %s, '0', '0', null, '', '', %s)",
            [ $connector_id, $secret_key, $connExtra ]
        );

        return $this->db->query($stmt);
    }

    /**
     * Delete a connector from the Sales Layer table.
     * @param string $connector_id Connector id
     * @return int|false Number of rows affected or false on failure
     */
    public function delete_connector($connector_id)
    {

        if ($this->check_connector($connector_id)) {
            
            $stmt = $this->db->prepare(
                "DELETE FROM `" . SLYR_WC_connector_table . "` WHERE conn_code IN (%s)",
                $connector_id
            );

            return $this->db->query($stmt);
        
        }

        return false;
        
    }

    /**
     * Update connector information.
     * @param string $connector_id Connector id
     * @param array  $data         Data to update (column => value)
     * @return void
     */
    public function update_connector($connector_id, array $data = array())
    {

        $connector = $this->get_connector($connector_id);

        if (!empty($data)) {

            $this->conn_data = $data;

            $this->db->update(SLYR_WC_connector_table, $data, array('cnf_id' => $connector->cnf_id));

        }

    }

    /**
     * Get field information from a connector.
     * @param string $connector_id Connector id
     * @param string $field_name   Field name to fetch
     * @return string|false Field value or false when not present
     */
    public function get_info($connector_id, $field_name)
    {

        $connector = $this->get_connector($connector_id);

        $conn_data = json_decode(json_encode($connector), true);

        if (isset($conn_data[$field_name])) {
            
            return $conn_data[$field_name];

        }

        return false;

    }

    /**
     * Update a connector field value.
     * @param  string   $connector_id               Sales Layer connector id
     * @param  string   $field_name                 connector field name field
     * @param  string   $field_value                connector field value
     * @return string Status: 'success', 'error_update', or 'error_forbidden'
     */
    public function update_conn_field($connector_id, $field_name, $field_value)
    {

        if ($field_name == 'root_category') { $field_name = 'default_cat_id'; }
           
        $forbidden_fields = array('cnf_id', 'conn_code', 'conn_secret');

        if (!in_array($field_name, $forbidden_fields)) {

            if ($field_value !== $this->get_info($connector_id, $field_name)) {

                try {

                    $this->update_connector($connector_id, array($field_name => $field_value));
                    return 'success';
                
                } catch (\Exception $e) {

                    sl_debug(
                        'Error updating connector: ' . $connector_id
                        . ' field: ' . $field_name
                        . ' to: ' . $field_value
                        . ' - ' . $e->getMessage(),
                        'error'
                    );
                    return 'error_update';

                }

                } else {

                return 'success';

            }

        }

        return 'error_forbidden';

    }

}
