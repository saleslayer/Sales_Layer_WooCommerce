<!DOCTYPE html>
<html lang="es">
	
<head>
    <meta charset="utf-8">
    <title>Add Connector</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style type="text/css">
        body{
            color: black;
        }
    </style>
</head>

<body>
	<div id="slyr_catalogue_admin">
		<div class="container login">
			<div class="login-screen">
				<div class="login-icon">
					<h1><?php echo SLYR_WC_name; ?> plugin <small>/ Add connector</small></h1>
				</div>
				<div class="login-form">
					<form method="post" action="" id="config_form">
					<?php show_session_messages(); ?>
						<table class="form-table">
							<tr>
								<td>
									<label class="login-field-icon fui-lock" for="connector_id"></label>
									<input type="text" class="regular-text" placeholder="Connector ID code" id="connector_id" name="connector_id" autocomplete="off" required="true">
								</td>
							</tr>
							<tr>
								<td>
									<label class="login-field-icon fui-lock" for="private-key"></label>
									<input type="text" class="regular-text" placeholder="Connector Secret key" id="secret_key" name="secret_key" autocomplete="off" required="true">
								</td>
							</tr>
						</table>

						<input type="submit" class="button button-primary button_block" value="Add and Synchronize Connector!">

					</form>
				</div>
			</div>
		</div>
	</div>
<script type="text/javascript">
	var plugin_name_dir = '<?php echo SLYR_WC_PLUGIN_NAME_DIR ?>';
</script>

</body>
</html> 