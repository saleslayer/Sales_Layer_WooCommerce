<h1><a href="https://saleslayer.com/" title="Title">Sales Layer</a> WooCommerce plugin</h1>
Sales Layer plugin allows you to easily synchronize your catalogue information with WooCommerce.

<h2>How To Start</h2>

<p>
    <h3>1. Install the package in WooCommerce</h3>
    <ul>
        <li>Go to Plugins -> Add new -> Upload plugin</li>
        <li>Select and upload our plugin zip file.</li>
    </ul>
</p>

<p>
    <h3>2. Create a Sales Layer WooCommerce connector and map the fields</h3>
    <ul>
        <li>The plugin needs the connector ID code and the private key, you will find them in the connector details of Sales Layer.</li>
    </ul>
</p>
    
<p>
    <h3>3. Add the connector credencials in WooCommerce</h3>
    <ul>
        <li>Go to Admin -> Sales Layer WooCommerce -> Add connector and add the connector id and secret key.</li>
        <li>Finally, In Admin -> Sales Layer Woo -> Connectors, push Synchronize to import categories, products and product variants automatically.</li>
    </ul>
</p>

<p>
    <h2>Requirements for synchronization</h2>
    <ul>
        <li>cUrl extension installed; In order to call and obtain the information from Sales Layer.</li>
        <li>Define the fields relationship in the Sales Layer WooCommerce connector:
            <ul>
                <li>One size for image fields.</li>
                <li>Most WooCommerce fields are already defined in each section, extra fields for products or variants will be Admin-> Product attributes and they must have been created in WooCommerce in order to synchronize.</li>
                <li>When synchronizing a product with variants, WooCommerce attributes that are synchronized will be marked as Used for variations, then, attribute values from the product and variants will be combined and assigned to the parnet product. Variations must have only one value for each attribute.</li>
            </ul>
        </li>
    </ul>
</p>

