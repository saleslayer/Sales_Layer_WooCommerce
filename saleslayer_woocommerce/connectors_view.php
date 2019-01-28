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
		<div id="messages">
			<?php show_session_messages(); ?>
		</div>
		<table class="wp-list-table widefat fixed striped posts">
		<thead>
			<tr>
				<th>Connector ID</th>
		        <th>Last Update</th>
		        <th>Progress</th>
		        <th>Auto-sync every</th>
		        <th>Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php
        	
        	$auto_sync_options = array('0'=>'','1'=>'1H','3'=>'3H','6'=>'6H','8'=>'8H','12'=>'12H','15'=>'15H','24'=>'24H','48'=>'48H','72'=>'72H');

        	foreach ($connectors as $connector) {
		?>
			<tr>
				<td class="title column-title column-primary"><strong><?php echo $connector['conn_code']; ?></strong></td>
				<td class="date column-date"><strong><?php echo $connector['last_update']; ?></strong></td>
				<td>
					<div class="progress" id="progress_catalogue_<?php echo $connector['conn_code']; ?>">
						<div class="progress-bar progress-bar-success" id="sub_progress_catalogue_<?php echo $connector['conn_code']; ?>" role="progressbar" aria-valuenow="0" aria-valuemin="0" 
							aria-valuemax="100" style="width:0%">
					    	Catalogue
						</div>
					</div>
					<div class="progress" id="progress_products_<?php echo $connector['conn_code']; ?>">
						<div class="progress-bar progress-bar-success" id="sub_progress_products_<?php echo $connector['conn_code']; ?>" role="progressbar" aria-valuenow="0" aria-valuemin="0" 
							aria-valuemax="100" style="width:0%">
					    	Products
					  	</div>
					</div>
					<div class="progress" id="progress_product_formats_<?php echo $connector['conn_code']; ?>">
						<div class="progress-bar progress-bar-success" id="sub_progress_product_formats_<?php echo $connector['conn_code']; ?>" role="progressbar" aria-valuenow="0" aria-valuemin="0" 
							aria-valuemax="100" style="width:0%">
					    	Product formats
					  	</div>
					</div>
					<div class="progress" id="progress_product_links_<?php echo $connector['conn_code']; ?>">
						<div class="progress-bar progress-bar-success" id="sub_progress_product_links_<?php echo $connector['conn_code']; ?>" role="progressbar" aria-valuenow="0" aria-valuemin="0" 
							aria-valuemax="100" style="width:0%">
					    	Product links
					  	</div>
					</div>
				</td>
                <td>
                    <select class="select" name="auto_sync" id="auto_sync_<?php echo $connector['conn_code']; ?>" onchange="update_conn_field(this);" >
                        <?php
                        foreach($auto_sync_options as $asoKey => $auto_sync_option){
                            ?>
                            <option value="<?php echo $asoKey; ?>" <?php echo ($connector['auto_sync'] == $asoKey ? 'selected' : ''); ?> ><?php echo $auto_sync_option; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </td>
                <td class="tags column-tags">
					<button class="button button-primary button-sync" connectorid=<?php echo $connector['conn_code']; ?> secretkey=<?php echo $connector['conn_secret']; ?> onclick="sync_conn(this)" id="sync_<?php echo $connector['conn_code']; ?>">Synchronize!</button>
					<form id="delconn_form" name="delconn_form" method="post" action="">
						<input name="delete_conn" type="hidden" value="<?php echo $connector['conn_code']; ?>" />
						<input type="submit" class="button button-forget" value="Forget Me!">
					</form>
				</td>
			</tr>
		<?php
			}
		?>
		</tbody>
		</table>
	</div>
<script type="text/javascript">
	var plugin_name_dir = '<?php echo SLYR_WC_PLUGIN_NAME_DIR ?>';
	var ajaxurl = '<?php echo admin_url('admin-ajax.php') ?>';

	jQuery(document).ready(function(){
		
		$('.progress').hide();
	    $(":input").prop("disabled", true);
		start_check_process_status();

	});

    function update_conn_field(data){

        var connector_id = data.id.replace(data.name+'_', "");
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
            data: {action:'sl_wc_update_conn_field',connector_id:connector_id,field_name:field_name,field_value:field_value},
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

	function start_check_process_status(){

	    $('#messages').html('');
		setTimeout(check_process_status, 3000);
	}

	var sync_conn = function(param){

		var registro = new Date;
		var conn_id = param.getAttribute('connectorid');
		var sec_key = param.getAttribute('secretkey');

	    $(":input").prop("disabled", true);
		start_check_process_status();
		
		jQuery.ajax({
			type:'POST',
			data:{action:'sl_wc_synchronize_connector', connector_id: conn_id, secret_key: sec_key},
			url: ajaxurl,
			success: function(data) {

				data = JSON.parse(data);
				
				$('#messages').html(data['message']);
                $('#messages').fadeIn('slow');
				
			}

		});
		
	}

	function check_process_status(){

		jQuery.ajax({
			type:'POST',
			data:{action:'sl_wc_check_process_status'},
			url: ajaxurl,
			success: function(data) {
	          	data = JSON.parse(data);
	          	var connector_id = data['connector_id'];

	          	if (data['status'] == 'not_finished'){
	          		
	          		$("#progress_catalogue_"+connector_id).show();
	          		$("#progress_products_"+connector_id).show();
	          		$("#progress_product_formats_"+connector_id).show();
	          		$("#progress_product_links_"+connector_id).show();
					
	          		$(":input").prop("disabled", true);
	          		
	          		data_content = data['content'];
	          		
	          		var tables = ['catalogue', 'products', 'product_formats', 'product_links'];
					
					for (var index in tables) { 
					    
					    var table = tables[index];
					    
					    if (table in data_content) {

						    var progress_name = '';
						    
						    switch(table) {
						        case 'products':
						            progress_name = ' Products ';
						            break;
						        case 'product_formats':
						            progress_name = ' Product formats ';
						            break;
						        case 'product_links':
						            progress_name = ' Product links ';
						            break;
						        default:
						            progress_name = ' Categories ';
						            break;
						    }
			          		
			          		var sl_data_processed = data_content[table]['processed'];
			          		var sl_data_total = data_content[table]['total'];

			          		var data_now = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow');
			          		var data_total = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax');
			          		
			          		if (sl_data_total != data_total){
							
								$("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax', sl_data_total);          			
			          		
			          		}

			          		if (sl_data_processed != data_now){
			          		
			          			$("#sub_progress_"+table+'_'+connector_id).addClass('progress-bar-striped active');
			          			$("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow', sl_data_processed);
			          			$("#sub_progress_"+table+'_'+connector_id).width(((sl_data_processed * 100) / sl_data_total)+'%');
			          			$("#sub_progress_"+table+'_'+connector_id).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');
			          		
			          		}
			          		
			          		if (sl_data_processed == sl_data_total){
			          			
			          			$("#sub_progress_"+table+'_'+connector_id).removeClass('progress-bar-striped active');
			          		
			          		}

					    }else{
							
							$("#sub_progress_"+table+'_'+connector_id).parent().hide();
					    	continue;

					    }
					
					}

					setTimeout(check_process_status, 1000);
				
	          	}else if (data['status'] == 'stopped'){

	          		$(".progress").hide();
	          		$(":input").prop("disabled", false);
	          		$('#messages').html(data['header']);

				}else{

	          		$("#sub_progress_catalogue_"+connector_id).width(0+'%');
	          		$("#sub_progress_products_"+connector_id).width(0+'%');
	          		$("#sub_progress_product_formats_"+connector_id).width(0+'%');
	          		$("#sub_progress_product_links_"+connector_id).width(0+'%');
	          		$("#sub_progress_catalogue_"+connector_id).attr('aria-valuenow', 0);
	          		$("#sub_progress_products_"+connector_id).attr('aria-valuenow', 0);
	          		$("#sub_progress_product_formats_"+connector_id).attr('aria-valuenow', 0);
	          		$("#sub_progress_product_links_"+connector_id).attr('aria-valuenow', 0);

	          		$(".progress").hide();
	          		$(":input").prop("disabled", false);
	          		$('#messages').html(data['content']);

	          	}

			}

	    });

	}

</script>

</body>
</html> 