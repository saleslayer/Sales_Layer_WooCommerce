var slyrToolsMessageTimer = null;

function showMessage(type = 'success', message)
{
	var messageContainer = jQuery('#message-container');
	if (type === 'success') {
		messageContainer.attr('class', 'success-message');
	} else {
		messageContainer.attr('class', 'error-message');
	}
	// Only one timer: an older one must not hide a newer message.
	clearTimeout(slyrToolsMessageTimer);
	messageContainer.stop(true, true).text(message).fadeIn();
	slyrToolsMessageTimer = setTimeout(function(){
		messageContainer.fadeOut('slow', function() {
			messageContainer.text('');
		});
	}, 7000);
}

/**
 * Message for a failed tool request (HTTP error, permission or nonce check, invalid JSON).
 * @param {object} xhr
 * @param {string} fallback
 * @returns {string}
 */
function toolErrorMessage(xhr, fallback)
{
	var json = xhr && xhr.responseJSON;
	if (json && json.message) {
		return json.message;
	}
	if (json && json.data && json.data.message) {
		return json.data.message;
	}
	if (xhr && xhr.status === 403) {
		return 'Security check failed or permission denied. Please reload the page and try again.';
	}
	return fallback;
}

/**
 * Disable every tool button while one tool is running and show a spinning icon on the clicked one,
 * so a double click cannot launch the same (or another) tool twice.
 * @param {HTMLElement} button
 * @param {boolean} busy
 */
function setToolsBusy(button, busy)
{
	var $buttons = jQuery('.action-group .button');
	var $icon = jQuery(button).find('.dashicons');

	if (busy) {
		$buttons.prop('disabled', true);
		jQuery(button).attr('aria-busy', 'true');
		$icon.attr('data-icon', $icon.attr('class')).attr('class', 'dashicons dashicons-update slyr-spin');
	} else {
		$buttons.prop('disabled', false);
		jQuery(button).removeAttr('aria-busy');
		if ($icon.attr('data-icon')) {
			$icon.attr('class', $icon.attr('data-icon')).removeAttr('data-icon');
		}
	}
}

/**
 * Run a tool through the sl_wc_execute_tool AJAX action.
 * @param {HTMLElement} button
 * @param {string} tool
 * @param {string} errorMessage Fallback message when the request fails
 * @param {function} [onSuccess] Custom success handler; by default the server message is shown
 */
function runTool(button, tool, errorMessage, onSuccess)
{
	setToolsBusy(button, true);
	jQuery.ajax({
		url: ajax_object.ajaxurl,
		type: 'POST',
		dataType: 'json',
		data: {
			action: 'sl_wc_execute_tool',
			tool_to_execute: tool,
			nonce: ajax_object.nonce
		},
		success: function(response) {
			if (onSuccess) {
				onSuccess(response);
			} else {
				showMessage(response['message_type'], response['message']);
			}
		},
		error: function(xhr) {
			showMessage('error', toolErrorMessage(xhr, errorMessage));
		},
		complete: function() {
			setToolsBusy(button, false);
		}
	});
}

document.getElementById('download_sl_logs').addEventListener('click', function()
{
	var button = this;
	setToolsBusy(button, true);
	jQuery.ajax({
		url: ajax_object.ajaxurl,
		type: 'POST',
		data: {
			action: 'sl_wc_execute_tool',
			tool_to_execute: 'download_sl_logs',
			nonce: ajax_object.nonce
		},
		xhrFields: {
			responseType: 'blob'
		},
		// Always hand back the raw Blob. Without this, a JSON error response (Content-Type: application/json)
		// makes jQuery try a blob -> json conversion, fail with "parsererror" and skip the server message.
		dataType: 'binary',
		success: function (response, status, xhr) {
			var contentType = xhr.getResponseHeader('Content-Type') || '';
			if (response instanceof Blob && contentType.indexOf('application/zip') === -1) {
				// The server answered with a JSON error (no logs, zip extension missing...), not with the zip file.
				response.text().then(function (text) {
					var message = "Couldn't download SL logs.";
					try {
						var json = JSON.parse(text);
						if (json && json.data && json.data.message) {
							message = json.data.message;
						}
					} catch (e) {}
					showMessage('error', message);
				});
				return;
			}
			if (response instanceof Blob) {
				var downloadUrl = URL.createObjectURL(response);
				var a = document.createElement('a');
				a.href = downloadUrl;
				a.download = 'sl_logs.zip';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				URL.revokeObjectURL(downloadUrl);
				showMessage('success', "SL logs zip generated successfully.");
			}else{
				showMessage('error', "Couldn't download SL logs.");
			}
		},
		error: function (xhr) {
			showMessage('error', toolErrorMessage(xhr, "Couldn't download SL logs."));
		},
		complete: function() {
			setToolsBusy(button, false);
		}
	});
});

document.getElementById('delete_sl_logs').addEventListener('click', function()
{
	runTool(this, 'delete_sl_logs', "Couldn't delete SL logs.");
});

document.getElementById('delete_sl_pending_items').addEventListener('click', function()
{
	runTool(this, 'delete_sl_pending_items', "Couldn't delete SL pending items.");
});

document.getElementById('delete_sl_credentials').addEventListener('click', function()
{
	runTool(this, 'delete_sl_credentials', "Couldn't delete items' SL credentials.");
});

document.getElementById('clean_orphaned_multiconn').addEventListener('click', function()
{
	runTool(this, 'clean_orphaned_multiconn', "Couldn't clean orphaned multiconn records.");
});

document.getElementById('migrate_legacy_items').addEventListener('click', function()
{
	runTool(this, 'migrate_legacy_items', "Couldn't execute migration.", function(response) {
		if (response['message_type'] === 'success') {
			var resultDiv = jQuery('#migration-result');
			resultDiv.html(response['message']);
			resultDiv.fadeIn();
			showMessage('success', 'Migration process completed!');
		} else {
			showMessage('error', response['message']);
		}
	});
});
