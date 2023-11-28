

<a href="https://support.saleslayer.com"><p align="center"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Sales Layer Wordpress plugin for WooCommerce" width="230"></p></a>

# Sales Layer WooCommerce plugin

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0,%20%3C=%207.3-8892BF.svg?style=flat-square)](https://php.net/) [![Minimum WooCommerce Version](https://img.shields.io/badge/WooCommerce-%3E%3D%204.1,%20%3C=%207.4-AA92BF.svg?style=flat-square)](https://wordpress.org/plugins/woocommerce/) [![Minimum WordPress Version](https://img.shields.io/badge/Wordpress-%3E%3D%205.4,%20%3C=%206.0-4892BF.svg?style=flat-square)](https://wordpress.org/Download/) [![GitHub release](https://img.shields.io/badge/release-v2.3.3-blue)](https://github.com/saleslayer/Sales_Layer_WooCommerce)

WordPress plugin that allows you to easily synchronize your Sales Layer catalog information with WooCommerce.
[Sales Layer - Global Leading PIM][saleslayer-home]

## Download

Download [Sales Layer WooCommerce plugin 2.3.3](https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/download/2.3.3/saleslayer_woocommerce.zip).

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

## Branch 2.4.x Release recommended configuration

| Release        | WooCommerce version | WordPress version | PHP version    | Web Server | 
|----------------|---------------------|-------------------|----------------|------------|
| [2.3.0][2.3.0] | WooCommerce 4.1.0   | WordPress 5.4     | PHP 7.0        | Apache2.4  |
| [2.3.1][2.3.1] | WooCommerce 4.1.0   | WordPress 5.4     | PHP 7.1        | Apache2.4  |
| [2.3.2][2.3.2] | WooCommerce 5.4.0   | WordPress 5.7     | PHP 7.3        | Apache2.4  |
| [2.3.3][2.3.3] | WooCommerce 7.4.0   | WordPress 6.0     | PHP 7.3        | Apache2.4  |


> **Warning**.
> WooCommerce frequently releases new plugin versions to fix bugs and introduce new functionality. Some of these versions may conflict with this plugin. We highly encourage you to configure the WooCommerce plugin according to the recommendations provided in the guidance table to ensure the correct functioning of this plugin. 

> **Note**. 
> Refer to [WooCommerce Server Recommendations][woo-server-recomm] for version requirement details. Refer to the [WooCommerce GitHub repository][woo-github] for additional information about the extension (recommended for developers).

[saleslayer-home]: https://www.saleslayer.com
[sc-important-notes]: https://support.saleslayer.com/woocommerce/important-notes-about-connector
[sl-sc]: https://support.saleslayer.com
[woo-server-recomm]: https://woocommerce.com/document/server-requirements/
[woo-github]: https://github.com/woocommerce/woocommerce
[2.3.0]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.3.0
[2.3.1]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.3.1
[2.3.2]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.3.2
[2.3.3]:https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/tag/2.3.3
