<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title data-i18n="howto.title"></title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link href="//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css" rel="stylesheet">
	</head>
	<body>
		<div id="slyr_catalogue_admin">
		<header>
			<div id="logo">
				<a href=""><img src="<?php echo plugin_dir_url( __FILE__ ).'images/'.SLYR_WC_name_logo; ?>"></a>
			</div>
			<h3>How To Start</h3>
		</header>
		<main>
			<section class="howtoinfo">
				<p><strong><?php echo SLYR_WC_name; ?></strong> plugin allows you to add in your Wordpress website all your catalogue super easily. To do so, the catalog automatically imports and syncs all the product information.</p>
				<p>First of all the plugin needs the <strong>connector ID code</strong> and the <strong>private key</strong>. You will find them in the connector details of  <strong><?php echo SLYR_WC_company_name; ?></strong>.</p>
			</section>
			<section class="steps">
				<ol>
					<li>Go to <a href="<?php echo admin_url() ?>admin.php?page=slyr_wc_add_connector"><?php echo SLYR_WC_name; ?> -> Add connector</a></li>
					<li>Add the connection credentials.</li>
					<li>In <a href="<?php echo admin_url() ?>admin.php?page=slyr_wc_connectors">connectors</a>, push Synchronize to import categories, products and product formats automatically.</li>
				</ol>
			</section>
			<section class="howtoinfo">
					<p><strong>Requirements for synchronization</strong></p>
			</section>
			<section class="steps">
				<ol>
					<li><strong>cUrl</strong> extension installed; In order to call and obtain the information from <strong><?php echo SLYR_WC_company_name; ?></strong>.</li>
					<li>Define the fields relationship in the <strong><?php echo SLYR_WC_company_name; ?></strong> WooCommerce connector: </li>
					<ol>
						<li>One size for image fields.</li>
						<li>Most WooCommerce fields are already defined in each section, extra fields for products or product formats will be <strong><a href="<?php echo admin_url() ?>edit.php?post_type=product&page=product_attributes"> attributes</a></strong> and they must have been created in WooCommerce in order to synchronize.</li>
						<li>When synchronizing a product that has formats, WooCommerce attributes that are synchronized will be marked as <strong>Used for variations</strong>, then, attribute values from the product and product formats will be combined and assigned to the product. Variations must have only one value for each attribute.</li>
					</ol>
				</ol>
			</section>
			<section class="howtoinfo">
					<p><strong>How To Synchronize By Cron</strong></p>
			</section>
			<section class="steps">
				<ol>
					<li>To activate auto-synchronization, make you to have active cron job on your server.</li>
					<li>You can add or create cron jobs by adding by command on your console:</li>
					<ol>
						<li><strong>* * * * *  wget -q -O - https://yourdomain.com/wp-cron.php</strong></li>
						<p>or</p>
						<li><strong>* * * * *  php -q -f /path/to/your/woocommerce/root/directory/wp-cron.php</strong></li>
						<p><i>*( Remember to replace yourdomain.com with your actual domain or /path/to/your/woocommerce/root/directory with the actual path of your WooCommerce installation )</i></p>
					</ol>
				</ol>
			</section>
		</main>
		</div>
	</body>
</html>