jQuery(document).ready(function()
{
    update_pagination_disabled();
    var plugin_name_dir = '<?php echo SLYR_WC_PLUGIN_NAME_DIR ?>';
    var ajaxurl = "<?php echo admin_url('admin-ajax.php') ?>";
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

function showMessage(type = 'success', message)
{
    var html = "<div class='dialog dialog-"+type+"'>"+message+"<br></div>";
    $('#messages').html(html);
}

function clear_message_status()
{
    var timeout =  setTimeout(function(){
        $('#messages').fadeOut('slow','',$('#messages').html(''));
        $('#messages').fadeIn();
        clearTimeout(timeout);
    }, 7000);
}