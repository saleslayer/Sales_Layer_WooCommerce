jQuery(document).ready(function()
{
    update_pagination_disabled();
});

function update_pagination_disabled()
{
    var api_version = document.querySelector('#API_version'),
        pagination_select = document.querySelector('#pagination');
    if (!api_version || !pagination_select) {
        return;
    }
    pagination_select.disabled = (api_version.value != '1.18');
}

function update_api_version(data)
{
    update_pagination_disabled();
    update_general_parameter_field(data);
}

function update_general_parameter_field(data)
{
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
        type: "POST",
        url: ajax_object.ajaxurl,
        dataType: "json",
        data: {
            action:'sl_wc_update_general_parameter_field',
            field_name:field_name,
            field_value:field_value,
            nonce:ajax_object.nonce
        },
        success: function(data_return) {
            showMessage(data_return['message_type'], data_return['message']);
            jQuery('#messages').fadeIn('slow');
            clear_message_status();
        },
        error: function(xhr){
            var json = xhr && xhr.responseJSON;
            var message = (json && json.message) ? json.message : "Couldn't update the parameter.";
            showMessage('error', message);
            jQuery('#messages').fadeIn('slow');
            clear_message_status();
        }
    });
}

function showMessage(type = 'success', message)
{
    var allowedTypes = ['success', 'warning', 'info', 'error'];
    if (allowedTypes.indexOf(type) === -1) {
        type = 'info';
    }

    var div = document.createElement('div');
    div.appendChild(document.createTextNode(String(message || '')));

    cancel_message_clear();
    jQuery('#messages').html("<div class='dialog dialog-" + type + "'>" + div.innerHTML + "<br></div>");
}

var slyrMessageTimer = null;

/**
 * Cancel a pending clear_message_status(), so an older timer cannot wipe a newer message.
 */
function cancel_message_clear()
{
    clearTimeout(slyrMessageTimer);
    slyrMessageTimer = null;
    jQuery('#messages').stop(true, true).show();
}

/**
 * Hide and empty #messages after 7 seconds. Only one timer is kept: a new call replaces the previous one.
 */
function clear_message_status()
{
    clearTimeout(slyrMessageTimer);
    slyrMessageTimer = setTimeout(function(){
        slyrMessageTimer = null;
        jQuery('#messages').fadeOut('slow', function() {
            jQuery(this).html('').show();
        });
    }, 7000);
}
