# Changelog

## [2.5.3] - 2026-02-03

### Added

- Added compatibility with PHP 8.5.
- Added HPOS compatibility.

### Changed

- Optimized code with PSR-2 coding standards.
- Fixed minor bugs.

### Tested

- Tested on WooCommerce 10.4.3 / Wordpress 6.9 / PHP 8.5
    
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

- Tested on WooCommerce 9.3.3 / Wordpress 6.6.2 / PHP 8.2 

## [2.5.1] - 2023-12-29

### Added

- Protection agaisnt SQL Injection attacks.

### Tested

- Tested on WooCommerce 8.0.2 / Wordpress 6.2 / PHP 8.1

## [2.5] - 2023-09-04

### Added

- API v1.18 compatibility.
- General Parameters configuration page, to manage API version, pagination and debug level parameters.

### Changed

- SalesLayer-Conn class version updated, API default version set to 1.18.

### Tested

- Tested on WooCommerce 8.0.2 / Wordpress 6.2, 6.3.1 / PHP 8.1, 8.2 

## [2.4] - 2023-05-09

### Changed

- SalesLayer-Conn class version updated to ensure PHP-8 compatibility.

### Tested

- Tested on WooCommerce 7.6.0 / Wordpress 6.2 / PHP 7.3, 8.2 

## [2.3.3] - 2021-08-24

### Changed

- SalesLayer-Conn class version updated.

## [2.3.2] - 2021-07-21

### Changed

- Image process improved.
- Minor code fixes.

## [2.3.1] - 2020-05-25

### Added

- In case multiples languages come through the API, filter has been added to sync the selected language.

### Changed

- Minor code fixes.

## [2.3.0] - 2020-05-15

### Added

- Added min and max version of WC.
- Added product status field.
- Added update of product version when synchronizing products and formats.
- Added function to find unnasigned product categories by name.
- Added function to delete post_meta.

### Changed

- SalesLayerConn class updated to 1.3.1
- Modified items identificators accordly as the new SalesLayerConn version.
- Bootstrap updated to 4.4.1
- Improved connectors view.
- Jquery updated to 3.5.0
- Connectors now will be sorted by creation order.
- Media functions converted to class.
- Images will be compared by file sizes instead of md5.
- Image meta of product additional images will be stored and processed by media cron on parallel.
- Improved multilan filter on category, product and format models.
- Improved format status field.
- Products and formats now will be disabled instead of deleted.
- 'post_status' filter modified on get_posts calls.

