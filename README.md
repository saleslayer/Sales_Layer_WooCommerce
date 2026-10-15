

<a href="https://support.saleslayer.com"><p align="center"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Sales Layer WordPress plugin for WooCommerce" width="230"></p></a>

# Sales Layer WooCommerce plugin

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg?style=flat-square)](https://php.net/) [![Minimum WooCommerce Version](https://img.shields.io/badge/WooCommerce-%3E%3D%208.2-AA92BF.svg?style=flat-square)](https://wordpress.org/plugins/woocommerce/) [![Minimum WordPress Version](https://img.shields.io/badge/WordPress-%3E%3D%206.4-4892BF.svg?style=flat-square)](https://wordpress.org/Download/) [![GitHub release](https://img.shields.io/github/v/release/saleslayer/Sales_Layer_WooCommerce)](https://github.com/saleslayer/Sales_Layer_WooCommerce)

WordPress plugin that allows you to easily synchronize your Sales Layer catalogue information with WooCommerce.
[Sales Layer - Global Leading PIM][saleslayer-home]

## Important Notes

Please check the [important notes for the installation][sc-important-notes] available at our [support center][sl-sc]. In some cases, a Sales Layer account might be needed to access the documentation.

## How To Start

1. Install the plugin on a WordPress site.

	* Go to *Plugins > Add new > Upload plugin*.
	* Select and upload our plugin zip file.

2. Create a Sales Layer WooCommerce connector and map the fields

	* The plugin needs the connector ID code and the private key. You will find them in the connector details of Sales Layer.
    
3. Add the connector credentials in WooCommerce

	* Go to *Admin > Sales Layer WooCommerce > Connectors* and click the **Add connector** button. Enter the connector ID and secret key.
	* Once the connector appears in the list, push Synchronize to import categories, products and product variants automatically.

## Requirements for synchronization

- A working WooCommerce plugin installed on a WordPress site (see version guidance).

- PHP cURL extension installed and enabled, in order to call and obtain the information from Sales Layer.

- PHP zip extension, required to download the plugin logs from *Tools > Download SL Logs*.

- Outgoing HTTPS connections with up-to-date root certificates: TLS certificates are verified when calling the Sales Layer API and when sending synchronization analytics, so a proxy that intercepts TLS must be trusted by the server.

- The web server user must be able to write in the plugin `logs/` folder. Log file names include a random suffix and the folder is protected with `.htaccess`; download them from *Tools > Download SL Logs*.

- Plugin administration requires the `manage_options` capability (administrators); in Multisite, `manage_network_options` (super admins).

- Define the fields relationship in the Sales Layer WooCommerce connector:
	- One size for image fields.
	- Most WooCommerce fields are already defined in each section, extra fields for products or variants will be treated as attributes<br/> (*Admin > Product > Attributes*) and they must be created in WooCommerce beforehand in order to synchronize.
	- When synchronizing a product with variants, WooCommerce attributes that are synchronized will be marked as Used for variations. Then, attribute values from the product and variants will be combined and assigned to the parent product. Variations must have only one value for each attribute.
	
## Multisite and multilanguage

- In WordPress Multisite the plugin must be **network activated**. Each connector can target several sites from *Connectors > Settings*.
- With Polylang, map in *Connectors > Settings* each site language to a Sales Layer language. The same language can be mapped on several sites: each site receives its own copy of the items in that language.
- Sites without Polylang receive the items in the connector default language.

## Upgrading

- Back up the database before upgrading and make sure there is no synchronization in progress.
- Recommended procedure: deactivate and delete the previous version from *Plugins*, then upload and activate the new one. Connectors are kept: the plugin tables are not removed when the plugin is deleted.
- From 2.6.2, upgrading from 2.5.x renames the legacy tables to the WordPress prefix and only adds missing columns: the connectors table is no longer recreated, and the server default storage engine is used (MyISAM is no longer required).

## Version Guidance

| Branch         | Status     | WooCommerce version     | PHP compatibility | Changelog                             |
|----------------|------------|-------------------------|-------------------|---------------------------------------|
| [2.3.x]        | EOL        | >= 4.1.0, <= 5.4.4      | 7.3               | [Changelog 2.3.x][changelog-2.3.x]    |
| [2.4.x]        | EOL        | >= 5.5.0, <= 7.6.1      | >= 7.3, <= 8.1    | [Changelog 2.4.x][changelog-2.4.x]    |
| [2.5.x]        | Fixes only | >= 8.0.2, <= 10.4.3     | >= 8.1, <= 8.5    | [Changelog 2.5.x][changelog-2.5.x]    |
| [2.6.x]        | Stable     | >= 8.2.0, <= 11.1.2     | >= 8.0, <= 8.5    | [Changelog 2.6.x][changelog-2.6.x]    |

## Branch 2.6.x Release recommended configuration

| Release        | WooCommerce version | WordPress version | PHP version    | Web Server | 
|----------------|---------------------|-------------------|----------------|------------|
| [2.6.0][2.6.0] | WooCommerce 10.4.3  | WordPress 6.9     | PHP 8.5        | Apache 2.4 |
| [2.6.1][2.6.1] | WooCommerce 10.6.1  | WordPress 6.9.4   | PHP 8.5        | Apache 2.4 |
| [2.6.2][2.6.2] | WooCommerce 11.1.2  | WordPress 7.1.2   | PHP 8.5        | Apache 2.4 |


## Branch 2.5.x Release recommended configuration

| Release        | WooCommerce version | WordPress version | PHP version    | Web Server | 
|----------------|---------------------|-------------------|----------------|------------|
| [2.5.0][2.5.0] | WooCommerce 8.0.2   | WordPress 6.2     | PHP 8.1        | Apache 2.4 |
| [2.5.1][2.5.1] | WooCommerce 8.0.2   | WordPress 6.2     | PHP 8.1        | Apache 2.4 |
| [2.5.2][2.5.2] | WooCommerce 9.3.3   | WordPress 6.6.2   | PHP 8.2        | Apache 2.4 |
| [2.5.3][2.5.3] | WooCommerce 10.4.3  | WordPress 6.9     | PHP 8.5        | Apache 2.4 |

> **Warning**.
> WooCommerce frequently releases new plugin versions to fix bugs and introduce new functionality. Some of these versions may conflict with this plugin. We highly encourage you to configure the WooCommerce plugin according to the recommendations provided in the guidance table to ensure the correct functioning of this plugin.

> **Install**.
> If the server is behind a NAT and you get redirection loops between the public IP (domain) and the local private IP of the VM when executing the cron jobs:
> - Create a new entry in /etc/hosts with the local private IP address and the domain, to properly route the internal cURL calls.

> **Note**. 
> Refer to [WooCommerce Server Recommendations][woo-server-recomm] for version requirement details. Refer to the [WooCommerce GitHub repository][woo-github] for additional information about the extension (recommended for developers).

[saleslayer-home]: https://www.saleslayer.com
[changelog-2.3.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.3.x/CHANGELOG.md
[changelog-2.4.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.4.x/CHANGELOG.md
[changelog-2.5.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.5.x/CHANGELOG.md
[changelog-2.6.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.6.x/CHANGELOG.md
[sc-important-notes]: https://support.saleslayer.com/en/support/solutions/folders/206000100929
[sl-sc]: https://support.saleslayer.com/en/support/home
[woo-server-recomm]: https://woocommerce.com/document/server-requirements/
[woo-github]: https://github.com/woocommerce/woocommerce
[2.3.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.3.x
[2.4.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.4.x
[2.5.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.5.x
[2.6.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.6.x
[2.5.0]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.5.0
[2.5.1]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.5.1
[2.5.2]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.5.2
[2.5.3]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.5.3
[2.6.0]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.6.0
[2.6.1]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.6.1
[2.6.2]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.6.2
