jQuery(document).ready(function($)
{
    var ajaxurl = ajax_object.ajaxurl;
});
function showMessage(type = 'success', message)
{
	var messageContainer = jQuery('#message-container');
	if (type === 'success') {
		messageContainer.attr('class', 'success-message');
	} else if (type === 'error') {
		messageContainer.attr('class', 'error-message');
	}	
	messageContainer.text(message);
	messageContainer.fadeIn();
	setTimeout(function(){
		messageContainer.fadeOut('slow', function() {
			messageContainer.text('');
		});
	}, 7000);
}

document.getElementById('download_sl_logs').addEventListener('click', function()
{      
	jQuery.ajax({
		url: ajaxurl,
		type: 'POST',
		data: {
			action: 'sl_wc_execute_tool', 
			tool_to_execute: 'download_sl_logs'
		},
		xhrFields: {
			responseType: 'blob'
		},
		success: function (response, status, xhr) {
			if (response instanceof Blob) {
				var downloadUrl = URL.createObjectURL(response);
				var a = document.createElement('a');
				a.href = downloadUrl;
				a.download = 'sl_logs.zip';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				showMessage('success', "SL logs zip generated properly.");
			}else{
				showMessage('error', "Couldn't download SL logs.");
			}
		},
		error: function (response) {
			showMessage('error', "Couldn't download SL logs.");
		}
	});
});

document.getElementById('delete_sl_logs').addEventListener('click', function()
{
	jQuery.ajax({
		url: ajaxurl,
		type: "POST",
		dataType: "json",
		data: {
			action: 'sl_wc_execute_tool', 
			tool_to_execute: 'delete_sl_logs'
		},
		success: function(response) {
			showMessage(response['message_type'], response['message']);
		},
		error: function(data_return){
			showMessage(response['message_type'], response['message']);
		}
	});
});

document.getElementById('delete_sl_pending_items').addEventListener('click', function()
{
	jQuery.ajax({
		url: ajaxurl,
		type: "POST",
		dataType: "json",
		data: {
			action: 'sl_wc_execute_tool', 
			tool_to_execute: 'delete_sl_pending_items'
		},
		success: function(response) {
			showMessage(response['message_type'], response['message']);
		},
		error: function(data_return){
			showMessage(response['message_type'], response['message']);
		}
	});
});

document.getElementById('delete_sl_credentials').addEventListener('click', function()
{
	jQuery.ajax({
		url: ajaxurl,
		type: "POST",
		dataType: "json",
		data: {
			action: 'sl_wc_execute_tool',
			tool_to_execute: 'delete_sl_credentials'
		},
		success: function(response) {
			showMessage(response['message_type'], response['message']);
		},
		error: function(data_return){
			showMessage(response['message_type'], response['message']);
		}
	});
});

document.getElementById('clean_orphaned_multiconn').addEventListener('click', function()
{
	jQuery.ajax({
		url: ajaxurl,
		type: "POST",
		dataType: "json",
		data: {
			action: 'sl_wc_execute_tool',
			tool_to_execute: 'clean_orphaned_multiconn'
		},
		success: function(response) {
			showMessage(response['message_type'], response['message']);
		},
		error: function(data_return){
			showMessage('error', "Couldn't clean orphaned multiconn records.");
		}
	});
});