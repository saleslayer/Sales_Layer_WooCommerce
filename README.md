<h1>Sales Layer WooCommerce plugin</h1>
Sales Layer plugin allows you to easily synchronize your catalogue information with WooCommerce.

<h3>How To Start</h3>

<p>1. Install the package in WooCommerce:
Go to Plugins -> Add new -> Upload plugin.
Then select and upload our plugin zip file.
</p>

<p>2. Create a Sales Layer WooCommerce connector and map the fields.
The plugin needs the connector ID code and the private key, you will find them in the connector details of Sales Layer.
</p>
    
<p>3. Go to Admin -> Sales Layer WooCommerce -> Add connector
    -Add the connection credentials.
    -In Admin -> Sales Layer Woo -> Connectors, push Synchronize to import categories, products and product formats automatically.
</p>

<p><b>Requirements for synchronization</b>
    -cUrl extension installed; In order to call and obtain the information from Sales Layer.<br>
    -Define the fields relationship in the Sales Layer WooCommerce connector: <br>
        -One size for image fields.<br>
        -Most WooCommerce fields are already defined in each section, extra fields for products or product formats will be Admin-> Product attributes and they must have been created in WooCommerce in order to synchronize.
        -When synchronizing a product that has formats, WooCommerce attributes that are synchronized will be marked as Used for variations, then, attribute values from the product and product formats will be combined and assigned to the product. Variations must have only one value for each attribute.
</p>