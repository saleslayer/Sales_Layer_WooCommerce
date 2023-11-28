

<a href="https://support.saleslayer.com"><p align="center"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Sales Layer Wordpress plugin for WooCommerce" width="230"></p></a>

# Sales Layer WooCommerce plugin

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.1-8892BF.svg?style=flat-square)](https://php.net/) [![Minimum WooCommerce Version](https://img.shields.io/badge/WooCommerce-%3E%3D%208.0-AA92BF.svg?style=flat-square)](https://wordpress.org/plugins/woocommerce/) [![Minimum WordPress Version](https://img.shields.io/badge/Wordpress-%3E%3D%206.0-4892BF.svg?style=flat-square)](https://wordpress.org/Download/) [![GitHub release](https://img.shields.io/github/v/release/saleslayer/Sales_Layer_WooCommerce)](https://github.com/saleslayer/Sales_Layer_WooCommerce)

WordPress plugin that allows you to easily synchronize your Sales Layer catalog information with WooCommerce.
[Sales Layer - Global Leading PIM][saleslayer-home]

## Download

Download [Sales Layer WooCommerce plugin 2.5.0](https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/download/2.5.0/saleslayer_woocommerce.zip) compatible with WooCommerce 8.x and WordPress 6.

## Important Notes

Please check the [important notes for the installation][sc-important-notes] available at our [support center][sl-sc]. In some cases, a Sales Layer account might be needed to access the documentation.

## How To Start

1. Install the package in a Wordpress site instance.

	* Go to *Plugins > Add new > Upload plugin*.
	* Select and upload our plugin zip file.

2. Create a Sales Layer WooCommerce connector and map the fields

	* The plugin needs the connector ID code and the private key, you will find them in the connector details of Sales Layer.
    
3. Add the connector credencials in WooCommerce

	* Go to *Admin > Sales Layer WooCommerce > Add connector*. Add the connector id and secret key.
	* Finally, In *Admin > Sales Layer Woo > Connectors*, push Synchronize to import categories, products and product variants automatically.

## Requirements for synchronization

- Working WooCommerce plugin installed on Wordpress site (see version guidance).

- PHP cUrl extension installed and enabled; In order to call and obtain the information from Sales Layer.

- Define the fields relationship in the Sales Layer WooCommerce connector:
	- One size for image fields.
	- Most WooCommerce fields are already defined in each section, extra fields for products or variants will be<br/> *Admin > Product > Attributes* and they must have been created in WooCommerce in order to synchronize.
	- When synchronizing a product with variants, WooCommerce attributes that are synchronized will be marked as Used for variations, then, attribute values from the product and variants will be combined and assigned to the parnet product. Variations must have only one value for each attribute.
	
## Version Guidance

| Branch         | Status     | WooCommerce Compatibility     | PHP compatibility | Changelog                             |
|----------------|------------|-------------------------------|-------------------|---------------------------------------|
| [2.3.x]        | EOL        | >= 4.1.0, <= 5.4.4            | 7.3               | [Changelog 2.3.x][changelog-2.3.x]    |
| [2.4.x]        | Fixes only | >= 5.5.0, <= 7.6.1            | \>= 7.3, <= 8.1   | [Changelog 2.4.x][changelog-2.4.x]    |
| [2.5.x]        | Stable     | 8.x                           | 8.1, 8.2          | [Changelog 2.5.x][changelog-2.5.x]    |

## Branch 2.5.x Release recommended configuration

| Release        | WooCommerce version | WordPress version | PHP version    | Web Server | 
|----------------|---------------------|-------------------|----------------|------------|
| [2.5.0][2.5.0] | WooCommerce 8.0.2   | WordPress 6.2     | PHP 8.1        | Apache2.4  |


> **Warning**.
> WooCommerce frequently releases new plugin versions to fix bugs and introduce new functionality. Some of these versions may conflict with this plugin. We highly encourage you to configure the WooCommerce plugin according to the recommendations provided in the guidance table to ensure the correct functioning of this plugin. 

> **Note**. 
> Refer to [WooCommerce Server Recommendations][woo-server-recomm] for version requirement details. Refer to the [WooCommerce GitHub repository][woo-github] for additional information about the extension (recommended for developers).

[saleslayer-home]: https://www.saleslayer.com
[changelog-2.3.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.3.x/CHANGELOG.md
[changelog-2.4.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.4.x/CHANGELOG.md
[changelog-2.5.x]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/2.5.x/CHANGELOG.md
[sc-important-notes]: https://support.saleslayer.com/woocommerce/important-notes-about-connector
[sl-sc]: https://support.saleslayer.com
[woo-server-recomm]: https://woocommerce.com/document/server-requirements/
[woo-github]: https://github.com/woocommerce/woocommerce
[2.3.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.3.x
[2.4.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.4.x
[2.5.x]:https://github.com/saleslayer/Sales_Layer_WooCommerce/tree/2.5.x
[2.5.0]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.5.0
