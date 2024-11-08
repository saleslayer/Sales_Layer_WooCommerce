jQuery(document).ready(function()
{
    var plugin_name_dir = '<?php echo SLYR_WC_PLUGIN_NAME_DIR ?>';
    var ajaxurl = "<?php echo admin_url('admin-ajax.php') ?>";    
    $('.progress').hide();
    $(":input").prop("disabled", true);
    start_check_process_status();
    if (typeof add_conn_message !== 'undefined' && add_conn_message){
        $('#messages').html(add_conn_message);
        $('#messages').fadeIn('slow');
    }
});

function update_conn_field(data)
{
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

function start_check_process_status()
{
    $('#messages').html('');
    setTimeout(check_process_status, 3000);
}

function check_process_status()
{
    jQuery.ajax({
        type:'POST',
        data:{action:'sl_wc_check_process_status'},
        url: ajaxurl,
        success: function(data) {
            data = JSON.parse(data);
            processStatusData(data);
        }
    });
}

var sync_conn = function(param)
{
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

function processStatusData(data)
{
    var connector_id = data['connector_id'];
    if (data['status'] == 'not_finished'){
        processUnfinishedStatusData(data, connector_id);
    }else if (data['status'] == 'stopped'){
        processStoppedStatusData(data);
    }else{
        processRestartStatusData(data, connector_id);
    }
}

function processUnfinishedStatusData(data, connector_id)
{
    $("#progress_catalogue_"+connector_id).show();
    $("#progress_products_"+connector_id).show();
    $("#progress_product_formats_"+connector_id).show();
    $("#progress_product_links_"+connector_id).show();
    $(":input").prop("disabled", true);
    data_content = data['content'];
    checkDataContentTables(data_content, connector_id)
    setTimeout(check_process_status, 4000);
}

function checkDataContentTables(data_content, connector_id)
{
    var tables = ['catalogue', 'products', 'product_formats', 'product_links'];
    for (var index in tables) {  
        var table = tables[index];   
        if (table in data_content) {
            processDataContentTable(data_content, table, connector_id);
        }else{
            $("#sub_progress_"+table+'_'+connector_id).parent().hide();
            continue;
        }
    }
}

function processDataContentTable(data_content, table, connector_id)
{
    var progress_name = getProgressName(table);
    var sl_data_processed = data_content[table]['processed'];
    var sl_data_total = data_content[table]['total'];
    var data_now = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow');
    var data_total = $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax');
    if (sl_data_total != data_total){
        $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuemax', sl_data_total); 
        $("#sub_progress_span_"+table+'_'+connector_id).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');         			 
    }
    if (sl_data_processed != data_now){
        $("#sub_progress_"+table+'_'+connector_id).addClass('progress-bar-striped progress-bar-animated');
        $("#sub_progress_"+table+'_'+connector_id).attr('aria-valuenow', sl_data_processed);
        $("#sub_progress_"+table+'_'+connector_id).width(((sl_data_processed * 100) / sl_data_total)+'%');
        $("#sub_progress_span_"+table+'_'+connector_id).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');
    }
    if (sl_data_processed == sl_data_total){ 
        $("#sub_progress_"+table+'_'+connector_id).removeClass('progress-bar-striped progress-bar-animated');
    }
}

function getProgressName(table)
{
    const progressNames = {
        products: ' Products ',
        product_formats: ' Product variants ',
        product_links: ' Product links ',
    };

    return progressNames[table] || ' Categories ';
}

function processStoppedStatusData(data)
{
    $(".progress").hide();
    $(":input").prop("disabled", false);
    $('#messages').html(data['header']);
}

function processRestartStatusData(data, connector_id)
{
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