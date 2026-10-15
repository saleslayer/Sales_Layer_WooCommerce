# Changelog

## [2.6.2] - 2026-10-15

### Security

- All admin AJAX actions (tools, connector and general parameter updates, synchronization, connector deletion, status polling) require the `manage_options` capability and a nonce; field updates use POST and only accept known fields and valid values.
- The connector secret key is read from the database when synchronizing and is no longer printed in the page HTML.
- Synchronization result messages are escaped; connector actions use `data-connector-id` attributes instead of inline JavaScript with the connector code.
- Synchronization analytics are sent with TLS certificate verification, no redirects and bounded timeouts (30 s connection, 60 s total).

### Changed

- Plugin tables are created with the server default storage engine and the WordPress charset/collation instead of forcing `ENGINE=MyISAM` / `utf8`.
- Connector table upgrade is non-destructive: missing columns are added with `ALTER TABLE` instead of dropping, recreating and re-inserting the table.
- The stored plugin version is only updated once every plugin table exists, so a failed upgrade is retried on the next request; database errors during the upgrade are written to the PHP error log. Table checks are skipped once verified for the current version.
- Cron events are scheduled on `init` (main site only in Multisite, never during uninstall); cron callbacks no longer use `sleep()` or `wp_die()`, and auto-sync, legacy items migration and media meta processing use locks to avoid concurrent runs.
- The synchronization queue lock is acquired atomically; a stale process is no longer killed, its lock is taken over.
- The plugin no longer changes `display_errors`, `error_reporting` or `FS_METHOD` for the whole site.
- Uninstalling removes the plugin network options, transients and cron events on every site; plugin tables (and connectors) are kept on purpose.
- Admin assets use WordPress' own jQuery and Dashicons; the bundled jQuery 3.5.0, Bootstrap and the Font Awesome CDN are no longer loaded, and plugin scripts are versioned for cache busting.
- Log files are created with `0666` permissions (instead of `0777`) and write failures are reported without PHP warnings.
- Legacy items migration processes items in batches.
- With Polylang, the same language can be synchronized on several Multisite sites: one queue row is created per site and language pair.
- Polylang detection is evaluated per site, and plugin queries are not filtered by the admin language filter.

### Fixed

- Connector configuration lost when upgrading from 2.5.x on hosts where MyISAM table creation is disabled (`wp_slyr_wc_api_config` and `wp_slyr_wc_api_multiconn` were not created).
- Legacy table migration no longer drops an unprefixed table with data when an empty prefixed table already exists.
- Plugin cron events are removed on deactivation (all sites on network deactivation), avoiding `invalid_schedule` errors in the debug log.
- Fatal error (`get_blog_details()`) when validating the language configuration on single-site installations.
- Synchronization of a language variant failed when that category already existed in the same language without Sales Layer data; the existing term is now adopted.
- Connectors page stayed locked (spinner and disabled controls) when an AJAX request failed or timed out; Tools showed no message on errors and allowed double clicks while a tool was running.
- Multisite settings were shown on single-site installations (`"0"`/`"1"` flags from `wp_localize_script`); older message timers could remove newer messages; enabling the page controls re-enabled controls outside the plugin.
- Add connector modal: Enter submits the form and errors are shown inside the modal without losing the typed data; modals and FAQ are keyboard accessible.
- Logs download returned a JSON file as `sl_logs.zip` on errors; deleting logs reported success when files could not be removed; the PHP zip extension is checked.
- New version notice ignored release tags prefixed with `v` and non-200 responses; saving an unchanged general parameter showed an error.
- Infinite loop when a parent category has no Sales Layer ID.
- Deleted items processed more than once (progress over 100 %).
- Stock status values such as "Not in stock" or "Sin stock" were set as in stock; disabling stock management without a stock status no longer sets the item out of stock.
- Failed image downloads removed the current product, variation or category images; image size checks after redirects always forced a new download; media lookup is limited to plugin attachments.
- Attribute terms were linked to an unrelated post; custom attributes whose name differs from their slug were removed; product synchronization reset the "Used for variations" flag.
- Multilanguage additional fields were looked up with the full connector language list instead of the row language.
- Field values of a previous queue row (or connector) could leak into the next one; product shipping class and variation stock / shipping class field mappings were ignored.
- The synchronization reported success when items could not be stored, and items dropped after the maximum number of attempts left no trace in the logs.
- Legacy mode published adopted draft products and could match variations of another product.
- A grouped product never reverted to simple when its linked products were removed.
- Queue JSON with escaped characters was corrupted when read back.
- Info icon in the Site & Language Configuration modal displayed as garbled characters: `style_admin.css` now declares `@charset "UTF-8"`.
- Typos and wording in admin texts, notices, log messages and documentation; remaining Spanish comments and identifiers translated to English.

### Tested

- Tested on WooCommerce 11.1.2 / WordPress 7.1.2 / PHP 8.5 / Polylang 3.8.10 (single-site and Multisite)

## [2.6.1] - 2026-04-17

### Added

- Multilanguage support: plugin architecture compatible with Polylang.
- Per-language synchronization queuing: one database row per (item, language) pair for independent language processing.
- Language-aware sync executors: Product, Category, and Format classes now support language context injection via `sync_blog_id` and `sync_lang_code` properties.
- Multilingual field filtering: automatic detection and filtering of language variants in item data to reduce database payload size.
- Language mapping configuration in modal: per-site language-to-Sales Layer language mapping with real-time validation.
- "Migrate Legacy Items" tool: idempotent migration tool for items created before multilanguage support was enabled.
- Language and blog context in sync logs: all "initialized" and error messages now include blog_id and language code for easier debugging in multisite/multilang scenarios.
- Migration utility class (`Migration`): handles legacy item metadata assignment and Polylang translation group linking.
- Enhanced Site & Language Configuration modal: unified title for multisite-only, multilang-only, and combined scenarios.

### Changed

- Counter calculation in sync queue: multiplied by language count to reflect actual database rows inserted in multilang mode.
- Language mapping dropdowns now only show available Sales Layer languages per connector.
- Updated FAQ with multilanguage setup instructions and "Migrate Legacy Items" tool documentation.

### Fixed

- Multi-site target assignment: `lang_code` now correctly assigned only to targets whose blog_id has that language configured.
- Empty targets in queue rows: language-specific queue rows only include the blog_id that needs that language (no empty targets).

### Tested

- Tested on WooCommerce 10.6.1 / WordPress 6.9.4 / PHP 8.5 / Polylang 3.7.8

## [2.6.0] - 2026-02-27

### Added

- WordPress Multisite support: network-level activation, per-connector site targeting with multisite synchronization settings modal.
- Multisite orchestrator: centralized sync queue with `switch_to_blog()` distribution to selected subsites.
- Multi-connector reference counting system (`Multiconn`): prevents incorrect item deletion when multiple connectors share the same `comp_id`.
- Add Connector modal dialog in the Connectors page, replacing the legacy standalone page.
- AJAX-based connector creation with inline validation and feedback messages.
- Empty state view in Connectors page when no connectors exist (no redirect).
- Clean orphaned multiconn tool in Plugin Tools to remove records from deactivated sites.

### Changed

- Removed legacy Add Connector page and submenu entry.
- Connector creation feedback now displays inline instead of as a WordPress admin notice.
- Updated How To Start, FAQ and README documentation to reflect the new Add Connector flow.
- Unified modal styling across Add Connector and Multisite modals (consistent header, buttons, theme colors).

### Tested

- Tested on WooCommerce 10.4.3 / WordPress 6.9 / PHP 8.5

## [2.5.3] - 2026-02-03

### Added

- Added compatibility with PHP 8.5.
- Added HPOS compatibility.

### Changed

- Optimized code with PSR-2 coding standards.
- Fixed minor bugs.

### Tested

- Tested on WooCommerce 10.4.3 / WordPress 6.9 / PHP 8.5
    
## [2.5.2] - 2024-11-05

### Added

- Tools tab.
- FAQ tab.
- Version update message.

### Changed

- General parameters small description.
- Optimized view structure.
- Fixed notices related to recent versions of PHP.
- Optimized image process.
- Fixed minor bugs.

### Tested

- Tested on WooCommerce 9.3.3 / WordPress 6.6.2 / PHP 8.2 

## [2.5.1] - 2023-12-29

### Added

- Protection against SQL Injection attacks.

### Tested

- Tested on WooCommerce 8.0.2 / WordPress 6.2 / PHP 8.1

## [2.5] - 2023-09-04

### Added

- API v1.18 compatibility.
- General Parameters configuration page, to manage API version, pagination and debug level parameters.

### Changed

- SalesLayer-Conn class version updated, API default version set to 1.18.

### Tested

- Tested on WooCommerce 8.0.2 / WordPress 6.2, 6.3.1 / PHP 8.1, 8.2 

## [2.4] - 2023-05-09

### Changed

- SalesLayer-Conn class version updated to ensure PHP-8 compatibility.

### Tested

- Tested on WooCommerce 7.6.0 / WordPress 6.2 / PHP 7.3, 8.2 

## [2.3.3] - 2021-08-24

### Changed

- SalesLayer-Conn class version updated.

## [2.3.2] - 2021-07-21

### Changed

- Image process improved.
- Minor code fixes.

## [2.3.1] - 2020-05-25

### Added

- In case multiple languages come through the API, filter has been added to sync the selected language.

### Changed

- Minor code fixes.

## [2.3.0] - 2020-05-15

### Added

- Added min and max version of WooCommerce.
- Added product status field.
- Added update of product version when synchronizing products and formats.
- Added function to find unassigned product categories by name.
- Added function to delete post_meta.

### Changed

- SalesLayerConn class updated to 1.3.1
- Modified item identifiers according to the new SalesLayerConn version.
- Bootstrap updated to 4.4.1
- Improved connectors view.
- jQuery updated to 3.5.0
- Connectors now will be sorted by creation order.
- Media functions converted to class.
- Images will be compared by file sizes instead of md5.
- Image meta of product additional images will be stored and processed by media cron in parallel.
- Improved multilanguage filter on category, product and format models.
- Improved format status field.
- Products and formats now will be disabled instead of deleted.
- 'post_status' filter modified on get_posts calls.

