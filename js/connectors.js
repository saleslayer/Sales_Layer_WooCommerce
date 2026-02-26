var ajaxurl = (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '';

function setConnectorPageInputsDisabled(disabled)
{
    // Do not disable the Add Connector modal inputs, otherwise users can open it and stare at disabled fields.
    $(":input").not('#slyr-add-connector-overlay :input').prop('disabled', disabled);
}

jQuery(document).ready(function()
{
    if (typeof ajax_object !== 'undefined' && ajax_object.ajaxurl) {
        ajaxurl = ajax_object.ajaxurl;
    }

    $('.progress').hide();
    setConnectorPageInputsDisabled(true);
    start_check_process_status();

    // Add Connector modal bindings
    jQuery('#slyr-add-connector-form').on('submit', function(e) {
        e.preventDefault();
        submitAddConnector();
    });

    jQuery('#slyr-add-connector-overlay').on('click', function(e) {
        if (e.target === this) {
            closeAddConnectorModal();
        }
    });

    // Enable submit only when both fields have values
    jQuery('#slyr-add-connector-id, #slyr-add-connector-secret').on('input', function() {
        var hasConnectorId = jQuery('#slyr-add-connector-id').val().trim() !== '';
        var hasSecretKey = jQuery('#slyr-add-connector-secret').val().trim() !== '';
        jQuery('#slyr-add-connector-submit').prop('disabled', !(hasConnectorId && hasSecretKey));
    });
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
    var allowedTypes = ['success', 'warning', 'info', 'error'];
    if (allowedTypes.indexOf(type) === -1) {
        type = 'info';
    }

    var safeMessage = escapeHtml(String(message || ''));
    var html = "<div class='dialog dialog-" + type + "'>" + safeMessage + "<br></div>";
    jQuery('#messages').html(html);
}

// --- Add Connector Modal ---

function openAddConnectorModal()
{
    jQuery('#slyr-add-connector-id').val('');
    jQuery('#slyr-add-connector-secret').val('');
    jQuery('#slyr-add-connector-submit').prop('disabled', true);
    jQuery('#slyr-add-connector-overlay').css('display', 'flex');
    jQuery('#slyr-add-connector-id').trigger('focus');
}

function closeAddConnectorModal()
{
    jQuery('#slyr-add-connector-overlay').hide();
}

function submitAddConnector()
{
    if (typeof ajax_object === 'undefined' || !ajax_object.create_connector_nonce) {
        showMessage('warning', 'Missing security nonce. Please reload the page.');
        clear_message_status();
        return;
    }

    var connectorId = String(jQuery('#slyr-add-connector-id').val() || '').trim();
    var secretKey = String(jQuery('#slyr-add-connector-secret').val() || '').trim();

    if (!connectorId || !secretKey) {
        showMessage('warning', 'Connector ID and Secret key are required.');
        clear_message_status();
        return;
    }

    closeAddConnectorModal();
    showMessage('info', 'Validating connector credentials…');
    jQuery('#messages').fadeIn('slow');

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        dataType: 'json',
        data: {
            action: 'sl_wc_create_connector',
            connector_id: connectorId,
            secret_key: secretKey,
            nonce: ajax_object.create_connector_nonce
        },
        success: function(response) {
            if (response && response.success) {
                var successMessage = (response.data && response.data.message)
                    ? response.data.message
                    : 'Connector added successfully!';
                showMessage('success', successMessage);
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }

            var message = (response && response.data && response.data.message)
                ? response.data.message
                : 'Error when creating the connector.';

            showMessage('warning', message);
            clear_message_status();
        },
        error: function(xhr) {
            var rawResponse = (xhr && xhr.responseText) ? String(xhr.responseText).trim() : '';
            if (rawResponse === '-1') {
                showMessage('warning', 'Security check failed. Please reload the page and try again.');
            } else {
                showMessage('warning', 'Connection error. Please try again.');
            }
            clear_message_status();
        }
    });
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
    setConnectorPageInputsDisabled(true);
    $('#messages').html('<div class="dialog dialog-info">Downloading data from Sales Layer API&hellip;</div>');
    $('#messages').fadeIn('slow');
    jQuery.ajax({
        type:'POST',
        data:{action:'sl_wc_synchronize_connector', connector_id: conn_id, secret_key: sec_key},
        url: ajaxurl,
        success: function(data) {
            data = JSON.parse(data);
            $('#messages').html(data['message']);
            $('#messages').fadeIn('slow');
            // Start polling AFTER items are queued so progress bars appear automatically
            setTimeout(check_process_status, 3000);
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
    setConnectorPageInputsDisabled(true);
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
    setConnectorPageInputsDisabled(false);
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
    setConnectorPageInputsDisabled(false);
    if (data['content']) {
        $('#messages').html(data['content']);
    }
}

// --- Multisite Configuration Modal ---

/**
 * Open the multisite configuration modal for a given connector.
 * Fetches network sites and current config, then populates the modal.
 * @param {string} connectorId
 */
function openMultisiteModal(connectorId)
{
    if (!ajax_object.is_multisite_mode) {
        return;
    }

    $('#slyr-multisite-connector-id').val(connectorId);
    $('#slyr-multisite-sites-body').html('<tr><td colspan="3">Loading sites...</td></tr>');
    $('#slyr-multisite-overlay').css('display', 'flex');

    // Fetch sites and config in parallel
    var sitesRequest = jQuery.ajax({
        type: 'GET',
        url: ajax_object.ajaxurl,
        dataType: 'json',
        data: {
            action: 'slyr_get_network_sites',
            nonce: ajax_object.multisite_nonce
        }
    });

    var configRequest = jQuery.ajax({
        type: 'GET',
        url: ajax_object.ajaxurl,
        dataType: 'json',
        data: {
            action: 'slyr_get_multisite_config',
            connector_id: connectorId,
            nonce: ajax_object.multisite_nonce
        }
    });

    jQuery.when(sitesRequest, configRequest).done(function(sitesResponse, configResponse) {
        var sitesData = sitesResponse[0];
        var configData = configResponse[0];

        if (!sitesData.success || !configData.success) {
            $('#slyr-multisite-sites-body').html(
                '<tr><td colspan="3">Error loading data.</td></tr>'
            );
            return;
        }

        var sites = sitesData.data.sites;
        var mainSiteId = sitesData.data.main_site_id || 1;
        var config = configData.data;
        var savedTargets = config.multisite_targets || [];
        var availableLanguages = config.available_languages || [];

        // Show available languages info
        if (availableLanguages.length > 0) {
            $('#slyr-multisite-languages-info').html(
                '<p><strong>Available Languages (from SalesLayer):</strong> ' +
                availableLanguages.join(', ') + '</p>'
            );
        } else {
            $('#slyr-multisite-languages-info').html(
                '<p class="slyr-multisite-warning">No languages configured in this connector. ' +
                'Synchronize the connector first to load available languages.</p>'
            );
        }

        populateMultisiteSitesTable(sites, savedTargets, availableLanguages, mainSiteId);

    }).fail(function() {
        $('#slyr-multisite-sites-body').html(
            '<tr><td colspan="3">Connection error. Please try again.</td></tr>'
        );
    });
}

/**
 * Populate the sites table in the modal with checkboxes and language dropdowns.
 * Sites without WooCommerce are shown disabled. Main site lock is dynamic:
 * locked (checked+disabled) only when no other site is active.
 *
 * @param {Array} sites Network sites
 * @param {Array} savedTargets Previously saved targets from conn_extra
 * @param {Array} availableLanguages Languages from connector
 * @param {number} mainSiteId The main site blog_id
 */
function populateMultisiteSitesTable(sites, savedTargets, availableLanguages, mainSiteId)
{
    var tableBody = '';

    // Build a lookup of saved targets by blog_id.
    // Only active targets are stored, so presence in the array = active.
    var savedLookup = {};
    for (var i = 0; i < savedTargets.length; i++) {
        savedLookup[savedTargets[i].blog_id] = savedTargets[i];
    }

    for (var s = 0; s < sites.length; s++) {
        var site = sites[s];
        var saved = savedLookup[site.blog_id] || null;
        var isMainSite = (site.blog_id === mainSiteId);
        var hasWoo = site.has_woocommerce;

        // Determine initial active state:
        // - No WooCommerce → always inactive
        // - Present in savedTargets → active (stored = active)
        // - Not present + main site + no saved config at all → active by default
        // - Not present otherwise → inactive
        var isActive;
        if (!hasWoo) {
            isActive = false;
        } else if (saved) {
            isActive = true;
        } else if (isMainSite && savedTargets.length === 0) {
            isActive = true;
        } else {
            isActive = false;
        }
        var selectedLang = saved ? saved.lang_code : '';

        var rowClass = !hasWoo ? ' class="slyr-multisite-site-disabled"' : '';
        tableBody += '<tr data-blog-id="' + site.blog_id + '"' + rowClass + '>';

        // Site name column with badges
        tableBody += '<td><strong>' + site.blog_id + '. ' + escapeHtml(site.blogname) + '</strong>';
        if (isMainSite) {
            tableBody += '<span class="slyr-multisite-main-site-badge">Main Site</span>';
        }
        if (!hasWoo) {
            tableBody += '<span class="slyr-multisite-no-woo-badge">WooCommerce disabled</span>';
        }
        tableBody += '<br><span class="slyr-multisite-url">' + escapeHtml(site.siteurl) + '</span></td>';

        // Active checkbox: sites without WooCommerce are always disabled.
        // Main site disabled/enabled state is managed dynamically by updateMainSiteCheckboxState().
        var checkboxAttrs = '';
        if (!hasWoo) {
            checkboxAttrs = ' disabled';
        } else if (isMainSite) {
            checkboxAttrs = ' data-main-site="1"';
        }
        tableBody += '<td><input type="checkbox" class="slyr-site-active" ' +
            (isActive ? 'checked' : '') + checkboxAttrs + ' /></td>';

        // Language dropdown: disabled for sites without WooCommerce
        var selectDisabled = !hasWoo ? ' disabled' : '';
        tableBody += '<td><select class="slyr-site-lang"' + selectDisabled + '>';
        tableBody += '<option value="">-- Select --</option>';

        for (var l = 0; l < availableLanguages.length; l++) {
            var lang = availableLanguages[l];
            tableBody += '<option value="' + lang + '"' +
                (selectedLang === lang ? ' selected' : '') + '>' + lang + '</option>';
        }

        tableBody += '</select></td>';
        tableBody += '</tr>';
    }

    if (tableBody === '') {
        tableBody = '<tr><td colspan="3">No sites found in the network.</td></tr>';
    }

    $('#slyr-multisite-sites-body').html(tableBody);

    // Set initial lock state for main site checkbox
    updateMainSiteCheckboxState();

    // Bind change event to recalculate lock state on every checkbox toggle
    $('#slyr-multisite-sites-body').off('change', '.slyr-site-active').on(
        'change', '.slyr-site-active', updateMainSiteCheckboxState
    );
}

/**
 * Dynamically lock/unlock the main site checkbox based on other active sites.
 *
 * Rules:
 *   - If at least one non-main site is checked → main site is unlockable (user can uncheck it)
 *   - If no non-main site is checked → main site is forced checked + disabled (minimum 1 guarantee)
 */
function updateMainSiteCheckboxState()
{
    var $mainCheckbox = $('#slyr-multisite-sites-body .slyr-site-active[data-main-site="1"]');
    if ($mainCheckbox.length === 0) {
        return;
    }

    // Count active non-main, non-disabled-by-woo checkboxes
    var otherActiveCount = $('#slyr-multisite-sites-body .slyr-site-active')
        .not('[data-main-site="1"]')
        .not(':disabled')
        .filter(':checked')
        .length;

    if (otherActiveCount > 0) {
        // Other sites are active — main site can be toggled
        $mainCheckbox.prop('disabled', false);
    } else {
        // No other site active — force main site on
        $mainCheckbox.prop('checked', true).prop('disabled', true);
    }
}

/**
 * Close the multisite configuration modal.
 */
function closeMultisiteModal()
{
    $('#slyr-multisite-overlay').css('display', 'none');
}

/**
 * Save multisite configuration from the modal form.
 * Collects targets from the sites table and sends them via AJAX.
 * Disabled rows (no WooCommerce) are skipped.
 * Backend enforces at least one active site (falls back to main site).
 */
function saveMultisiteConfig()
{
    var connectorId = $('#slyr-multisite-connector-id').val();

    var targets = [];
    $('#slyr-multisite-sites-body tr').each(function() {
        var blogId = $(this).data('blog-id');
        if (typeof blogId === 'undefined') {
            return;
        }

        // Skip sites without WooCommerce (disabled rows)
        if ($(this).hasClass('slyr-multisite-site-disabled')) {
            return;
        }

        targets.push({
            blog_id: blogId,
            lang_code: $(this).find('.slyr-site-lang').val() || '',
            active: $(this).find('.slyr-site-active').is(':checked')
        });
    });

    jQuery.ajax({
        type: 'POST',
        url: ajax_object.ajaxurl,
        dataType: 'json',
        data: {
            action: 'slyr_save_multisite_config',
            nonce: ajax_object.multisite_nonce,
            connector_id: connectorId,
            targets: JSON.stringify(targets)
        },
        success: function(response) {
            if (response.success) {
                showMessage('success', response.data.message);
                closeMultisiteModal();
            } else {
                showMessage('warning', response.data.message || 'Error saving configuration.');
            }
            clear_message_status();
        },
        error: function() {
            showMessage('warning', 'Connection error. Please try again.');
            clear_message_status();
        }
    });
}

/**
 * Delete a connector via AJAX and reload the page on success.
 * @param {string} connectorId
 */
function deleteConnector(connectorId)
{
    if (!confirm('Are you sure you want to delete connector ' + connectorId + '?')) {
        return;
    }

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: {
            action: 'sl_wc_delete_connector',
            connector_id: connectorId
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                showMessage('warning', response.data.message || 'Error deleting connector.');
                clear_message_status();
            }
        },
        error: function() {
            showMessage('warning', 'Connection error. Please try again.');
            clear_message_status();
        }
    });
}

/**
 * Escape HTML special characters to prevent XSS in dynamic content.
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text)
{
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

// Bind the Save button click event when DOM is ready
jQuery(document).ready(function() {
    jQuery('#slyr-multisite-save').on('click', function() {
        saveMultisiteConfig();
    });
});