<h1><a href="https://saleslayer.com/" title="Title" rel="nofollow">Sales Layer</a> WooCommerce plugin</h1>
Sales Layer plugin allows you to easily synchronize your catalogue information with WooCommerce.

<h2>How To Start</h2>

<p><h3>1. Install the package in WooCommerce:</h3><br>
Go to Plugins -> Add new -> Upload plugin.<br>
Then select and upload our plugin zip file.
</p>

<p><h3>2. Create a Sales Layer WooCommerce connector and map the fields.</h3><br>
The plugin needs the connector ID code and the private key, you will find them in the connector details of Sales Layer.
</p>
    
<p><h3>3. Go to Admin -> Sales Layer WooCommerce -> Add connector</h3><br>
    -Add the connection credentials.<br>
    -In Admin -> Sales Layer Woo -> Connectors, push Synchronize to import categories, products and product formats automatically.
</p>

<p><h2>Requirements for synchronization</h2><br>
    -cUrl extension installed; In order to call and obtain the information from Sales Layer.<br>
    -Define the fields relationship in the Sales Layer WooCommerce connector:<br>
        -One size for image fields.<br>
        -Most WooCommerce fields are already defined in each section, extra fields for products or product formats will be Admin-> Product attributes and they must have been created in WooCommerce in order to synchronize.<br>
        -When synchronizing a product that has formats, WooCommerce attributes that are synchronized will be marked as Used for variations, then, attribute values from the product and product formats will be combined and assigned to the product. Variations must have only one value for each attribute.
</p>

