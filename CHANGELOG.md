# Changelog

## [2.4.0] - 2023-05-09

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



