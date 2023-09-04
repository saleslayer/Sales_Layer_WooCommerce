<?php
        	
    $api_versions = ['1.18', '1.17'];
    $paginations = ['500', '1000', '2000', '3000', '4000', '5000', '6000', '7000', '8000', '9000','10000','20000','30000','40000','50000','60000','70000','80000','90000','100000'];
    $debbug_level = [
        '0' => 'None',
        '1' => 'Error',
        '2' => 'Warning',
        '3' => 'Info',
        '4' => 'Develop'
    ];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Connectors</title>
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
					<h1><?php echo SLYR_WC_name; ?> plugin <small>/ General Parameters</small></h1>
				</div>
				<div class="login-form">
					<form method="post" action="" id="config_form">
					    <div id="messages">
                            <?php show_session_messages(); ?>
                        </div>
                        <table class="form-table">
							<tr>
								<td width="35%">
									<label class="login-field-icon fui-lock" for="API_version">API version</label>									
								</td>
                                <td>
                                    <select class="select" name="API_version" id="API_version" onchange="update_api_version(this);">
                                        <?php
                                        foreach ($api_versions as $version) {
                                            $selected = (($general_params['API_version'] ?? '') == $version) ? 'selected="selected"' : '';
                                            echo '<option value="' . $version . '" ' . $selected . '>' . $version . '</option>';
                                        }
                                        ?>
                                    </select>
                                </td>
							</tr>
							<tr>
								<td width="35%">
									<label class="login-field-icon fui-lock">Pagination</label>									
								</td>
                                <td>
                                    <select class="select" name="pagination" id="pagination" onchange="update_general_parameter_field(this);" disabled>
                                        <?php 
                                        foreach ($paginations as $pagination){
                                            ?>
                                            <option value="<?php echo $pagination; ?>" <?php echo (($general_params['pagination'] ?? '') == $pagination ? 'selected' : '5000'); ?> ><?php echo $pagination; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </td>
							</tr>
                            <tr>
								<td width="35%">
									<label class="login-field-icon fui-lock" for="debbug_level">Debbug level</label>									
								</td>
                                <td>
                                    <select class="select" name="debbug_level" id="debbug_level" onchange="update_general_parameter_field(this);" >
                                        <?php 
                                        foreach ($debbug_level as $dbindex => $debbug){
                                            ?>
                                            <option value="<?php echo $dbindex; ?>" <?php echo (($general_params['debbug_level'] ?? '') == $dbindex ? 'selected' : '1'); ?> ><?php echo $dbindex . " - " . $debbug; ?></option>
                                            <?php
                                        }
                                        ?>
                                    </select>
                                </td>
							</tr>
						</table>

					</form>
				</div>
			</div>
		</div>
    </div>

    <script type="text/javascript">
        var plugin_name_dir = '<?php echo SLYR_WC_PLUGIN_NAME_DIR ?>';
        var ajaxurl = '<?php echo admin_url('admin-ajax.php') ?>';

        jQuery(document).ready(function() {

            update_pagination_disabled();

        });

        function update_pagination_disabled()
        {
            var api_version = document.querySelector('#API_version');
                pagination = document.querySelector('#pagination'),
                disvalue = false;

            disvalue = (api_version.value != '1.18');
            
            pagination.disabled = disvalue;
        }

        function update_api_version(data)
        {
            update_pagination_disabled();
            update_general_parameter_field(data);
        }

        function update_general_parameter_field(data) {
            
            var field_name = data.name;
            
            if (data.type == 'checkbox'){
                var field_value = data.checked;
                if (field_value === true){
                    field_value = 1;
                }else{
                    field_value = 0;
                }
            }else{
                var field_value = data.value;
            }

            jQuery.ajax({
                type: "GET",
                url: ajaxurl,
                dataType: "json",
                data: {
                    action:'sl_wc_update_general_parameter_field',
                    field_name:field_name,
                    field_value:field_value
                },
                success: function(data_return) {
                    showMessage(data_return['message_type'], data_return['message']);
                    $('#messages').fadeIn('slow');
                    clear_message_status();
                },
                error: function(data_return){
                    showMessage(data_return['message_type'], data_return['message']);
                    clear_message_status();
                }
            });

        }

        function showMessage(type = 'success', message) {
            var html = "<div class='dialog dialog-"+type+"'>"+message+"<br></div>";
            $('#messages').html(html);

        }

        function clear_message_status(){
            var timeout =  setTimeout(function(){
                $('#messages').fadeOut('slow','',$('#messages').html(''));
                $('#messages').fadeIn();
                clearTimeout(timeout);
            }, 7000);
        }
        
    </script>

</body>
</html>