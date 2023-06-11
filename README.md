

<a href="https://support.saleslayer.com"><img src="https://saleslayer.com/assets/images/logo.svg" alt="Sales Layer WooCommerce" width="460"></a>

# Sales Layer WooCommerce plugin

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.3-8892BF.svg?style=flat-square)](https://php.net/) [![Minimum WooCommerce Version](https://img.shields.io/badge/WooCommerce-%3E%3D%207.0-AA92BF.svg?style=flat-square)](https://wordpress.org/plugins/woocommerce/) [![Minimum WordPress Version](https://img.shields.io/badge/Wordpress-%3E%3D%206.0-4892BF.svg?style=flat-square)](https://wordpress.org/Download/) [![GitHub release](https://img.shields.io/github/v/release/saleslayer/Sales_Layer_WooCommerce)](https://github.com/saleslayer/Sales_Layer_WooCommerce)

Wordpress plugin that allows you to easily synchronize your Sales Layer catalogue information with WooCommerce.
[Sales Layer - Global Leading PIM][saleslayer-home]

## Download

[Download latest plugin version][latest-release-download]
Check out the latest changes at our [Changelog][changelog-md]

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

| Version | Status | WooCommerce Compatibility    | Recommended Configuration                   |
|---------|--------|------------------------------|---------------------------------------------|
| 2.3.x   | EOL    | >= 4.1.0, <= 5.4.4           | WooCommerce 5.4.0 / PHP 7.3                 |
| 2.4.0   | Latest | >= 5.5.0, <= 7.6.1           | WooCommerce 7.6.0 / WordPress 6.2 / PHP 8.1 |

> **Warning**.
> WooCommerce releases frequently new plugin versions fixing bugs and adding new functionallity. Some of this versions could be in conflict with this plugin. We highly encourage you to set the WooCommerce plugin configuration recommended in the guidance table for running correctly this plugin. 

> **Note**. 
> See also [WooCommerce Server Recommendations][woo-server-recomm] for more info on version requirements.

[saleslayer-home]: https://www.saleslayer.com
[latest-release-download]: https://github.com/saleslayer/Sales_Layer_WooCommerce/releases/latest/download/saleslayer_woocommerce.zip
[changelog-md]: https://github.com/saleslayer/Sales_Layer_WooCommerce/blob/master/CHANGELOG.md
[sc-important-notes]: https://support.saleslayer.com/woocommerce/important-notes-about-connector
[sl-sc]: https://support.saleslayer.com
[woo-server-recomm]: https://woocommerce.com/document/server-requirements/
