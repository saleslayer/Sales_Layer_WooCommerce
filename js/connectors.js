var ajaxurl = (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '';

/**
 * wp_localize_script() sends scalars as strings ("0"/"1"), and "0" is truthy in JS.
 * @param {*} value
 * @returns {boolean}
 */
function slyrFlag(value)
{
    return value === true || value === 1 || value === '1';
}

/**
 * Build an ID selector that is safe for any connector code (dots, colons, quotes...).
 * @param {string} id
 * @returns {string}
 */
function slyrIdSelector(id)
{
    return '#' + jQuery.escapeSelector(String(id));
}

/**
 * Message to show when an AJAX request fails (HTTP error, permission/nonce check, invalid JSON).
 * @param {object} xhr
 * @param {string} fallback
 * @returns {string}
 */
function slyrAjaxErrorMessage(xhr, fallback)
{
    var json = xhr && xhr.responseJSON;
    if (json && json.message) {
        return json.message;
    }
    if (json && json.data && json.data.message) {
        return json.data.message;
    }
    return fallback;
}

/**
 * Parse a JSON response that may already be an object; returns null when it is not valid JSON
 * (e.g. a PHP warning printed before the JSON).
 * @param {*} data
 * @returns {object|null}
 */
function slyrParseJson(data)
{
    if (data && typeof data === 'object') {
        return data;
    }
    try {
        return JSON.parse(data);
    } catch (e) {
        return null;
    }
}

function setConnectorPageInputsDisabled(disabled)
{
    // Only the plugin's own controls, never the modals (they manage their own state) nor other wp-admin controls.
    // When enabling again, only re-enable what this function disabled, so controls that start disabled stay so.
    var $inputs = jQuery('#slyr_catalogue_admin :input').not('.slyr-multisite-overlay :input');
    if (disabled) {
        $inputs.filter(':enabled').attr('data-slyr-ui-disabled', '1').prop('disabled', true);
    } else {
        $inputs.filter('[data-slyr-ui-disabled]').removeAttr('data-slyr-ui-disabled').prop('disabled', false);
    }
}

jQuery(document).ready(function()
{
    if (typeof ajax_object !== 'undefined' && ajax_object.ajaxurl) {
        ajaxurl = ajax_object.ajaxurl;
    }

    jQuery('.progress').hide();
    setConnectorPageInputsDisabled(true);
    showInitialLoader();
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

    // Modals: Esc closes the open one and Tab stays inside it
    jQuery(document).on('keydown', handleModalKeydown);

    // Row actions read the connector from data-connector-id (no connector code inside inline JS)
    jQuery(document).on('click', '.button-sync', function() {
        sync_conn(this);
    });
    jQuery(document).on('click', '.button-multisite', function() {
        openMultisiteModal(String(jQuery(this).attr('data-connector-id')));
    });
    jQuery(document).on('click', '.button-forget', function() {
        deleteConnector(String(jQuery(this).attr('data-connector-id')));
    });

    // Enable submit only when both fields have values
    jQuery('#slyr-add-connector-id, #slyr-add-connector-secret').on('input', function() {
        if (jQuery('#slyr-add-connector-submit').data('busy')) {
            return;
        }
        var hasConnectorId = jQuery('#slyr-add-connector-id').val().trim() !== '';
        var hasSecretKey = jQuery('#slyr-add-connector-secret').val().trim() !== '';
        jQuery('#slyr-add-connector-submit').prop('disabled', !(hasConnectorId && hasSecretKey));
    });
});

function update_conn_field(data)
{
    var connector_id = data.getAttribute('data-connector-id') || data.id.replace(data.name+'_', "");
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
        url: ajaxurl,
        dataType: "json",
        data: {action:'sl_wc_update_conn_field',connector_id:connector_id,field_name:field_name,field_value:field_value,nonce:ajax_object.nonce},
        success: function(data_return) {
            showMessage(data_return['message_type'], data_return['message']);
            jQuery('#messages').fadeIn('slow');
            clear_message_status();
        },
        error: function(xhr){
            showMessage('error', slyrAjaxErrorMessage(xhr, "Couldn't update the field."));
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

    var safeMessage = escapeHtml(String(message || ''));
    var html = "<div class='dialog dialog-" + type + "'>" + safeMessage + "<br></div>";
    cancel_message_clear();
    jQuery('#messages').html(html);
}

// --- Add Connector Modal ---

var slyrModalReturnFocus = null;

/**
 * Remember the element that opened a modal so focus can go back to it on close.
 */
function rememberModalOpener()
{
    slyrModalReturnFocus = document.activeElement;
}

function restoreModalOpenerFocus()
{
    if (slyrModalReturnFocus && document.body.contains(slyrModalReturnFocus)) {
        slyrModalReturnFocus.focus();
    }
    slyrModalReturnFocus = null;
}

/**
 * Esc closes the open modal; Tab / Shift+Tab cycle through its focusable elements.
 * @param {KeyboardEvent} e
 */
function handleModalKeydown(e)
{
    var $overlay = jQuery('.slyr-multisite-overlay:visible').last();
    if (!$overlay.length) {
        return;
    }

    if (e.key === 'Escape') {
        e.preventDefault();
        if ($overlay.attr('id') === 'slyr-add-connector-overlay') {
            closeAddConnectorModal();
        } else {
            closeMultisiteModal();
        }
        return;
    }

    if (e.key !== 'Tab') {
        return;
    }

    var $focusable = $overlay.find('a[href], button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])').filter(':visible');
    if (!$focusable.length) {
        return;
    }

    var first = $focusable.get(0);
    var last = $focusable.get($focusable.length - 1);
    var inside = jQuery.contains($overlay.get(0), document.activeElement);

    if (e.shiftKey && (document.activeElement === first || !inside)) {
        e.preventDefault();
        last.focus();
    } else if (!e.shiftKey && (document.activeElement === last || !inside)) {
        e.preventDefault();
        first.focus();
    }
}

function showAddConnectorError(message)
{
    jQuery('#slyr-add-connector-message').text(message).show();
}

function openAddConnectorModal()
{
    rememberModalOpener();
    jQuery('#slyr-add-connector-id').val('');
    jQuery('#slyr-add-connector-secret').val('');
    jQuery('#slyr-add-connector-message').hide().empty();
    jQuery('#slyr-add-connector-submit').data('busy', false).prop('disabled', true).text('Add connector');
    jQuery('#slyr-add-connector-overlay').css('display', 'flex');
    jQuery('#slyr-add-connector-id').trigger('focus');
}

function closeAddConnectorModal()
{
    jQuery('#slyr-add-connector-overlay').hide();
    restoreModalOpenerFocus();
}

function submitAddConnector()
{
    if (typeof ajax_object === 'undefined' || !ajax_object.create_connector_nonce) {
        showAddConnectorError('Missing security nonce. Please reload the page.');
        return;
    }

    var connectorId = String(jQuery('#slyr-add-connector-id').val() || '').trim();
    var secretKey = String(jQuery('#slyr-add-connector-secret').val() || '').trim();

    if (!connectorId || !secretKey) {
        showAddConnectorError('Connector ID and Secret key are required.');
        return;
    }

    // Keep the modal open until the server answers, so the data does not have to be typed again on error.
    var $submit = jQuery('#slyr-add-connector-submit');
    jQuery('#slyr-add-connector-message').hide().empty();
    $submit.data('busy', true).prop('disabled', true).text('Validating…');

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
                closeAddConnectorModal();
                showMessage('success', successMessage);
                jQuery('#messages').fadeIn('slow');
                setTimeout(function() { location.reload(); }, 1500);
                return;
            }

            var message = (response && response.data && response.data.message)
                ? response.data.message
                : 'Error creating the connector.';

            showAddConnectorError(message);
            $submit.data('busy', false).prop('disabled', false).text('Add connector');
        },
        error: function(xhr) {
            var rawResponse = (xhr && xhr.responseText) ? String(xhr.responseText).trim() : '';
            if (rawResponse === '-1') {
                showAddConnectorError('Security check failed. Please reload the page and try again.');
            } else {
                showAddConnectorError('Connection error. Please try again.');
            }
            $submit.data('busy', false).prop('disabled', false).text('Add connector');
        }
    });
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

/**
 * Show initial loading spinner while checking sync status.
 */
function showInitialLoader()
{
    var loaderHtml = '<div class="slyr-initial-loader">';
    loaderHtml += '<div class="slyr-spinner"></div>';
    loaderHtml += '<span>Checking synchronization status...</span>';
    loaderHtml += '</div>';
    cancel_message_clear();
    jQuery('#messages').html(loaderHtml).show();
}

/**
 * Hide the initial loader.
 */
function hideInitialLoader()
{
    var $loader = jQuery('#messages .slyr-initial-loader');
    if ($loader.length > 0) {
        $loader.fadeOut(200, function() {
            jQuery(this).remove();
        });
    }
}

function start_check_process_status()
{
    setTimeout(check_process_status, 3000);
}

/**
 * Leave the page usable after a failed request: enable the controls, drop the loader and show the error.
 * @param {string} message
 */
function restoreConnectorPageAfterError(message)
{
    jQuery('.progress').hide();
    setConnectorPageInputsDisabled(false);
    hideInitialLoader();
    showMessage('error', message);
    jQuery('#messages').fadeIn('slow');
}

function check_process_status()
{
    jQuery.ajax({
        type:'POST',
        data:{action:'sl_wc_check_process_status', nonce: ajax_object.nonce},
        url: ajaxurl,
        success: function(data) {
            data = slyrParseJson(data);
            if (!data) {
                restoreConnectorPageAfterError("Couldn't read the synchronization status. Reload the page to check it again.");
                return;
            }
            processStatusData(data);
        },
        error: function(xhr) {
            restoreConnectorPageAfterError(slyrAjaxErrorMessage(xhr, "Couldn't check the synchronization status. Reload the page to check it again."));
        }
    });
}

var sync_conn = function(param)
{
    var conn_id = param.getAttribute('data-connector-id');
    setConnectorPageInputsDisabled(true);
    cancel_message_clear();
    jQuery('#messages').html('<div class="dialog dialog-info">Downloading data from Sales Layer API&hellip;</div>');
    jQuery('#messages').fadeIn('slow');
    jQuery.ajax({
        type:'POST',
        data:{action:'sl_wc_synchronize_connector', connector_id: conn_id, nonce: ajax_object.nonce},
        url: ajaxurl,
        success: function(data) {
            data = slyrParseJson(data);
            if (!data) {
                restoreConnectorPageAfterError("Unexpected response from the server. Check the Sales Layer logs.");
                return;
            }
            jQuery('#messages').html(data['message']);
            jQuery('#messages').fadeIn('slow');
            // Start polling AFTER items are queued so progress bars appear automatically
            setTimeout(check_process_status, 3000);
        },
        error: function(xhr) {
            // The synchronization may still be running on the server (e.g. web server timeout): poll the status.
            restoreConnectorPageAfterError(slyrAjaxErrorMessage(xhr, "The synchronization request failed or timed out. Checking its status…"));
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
    jQuery(slyrIdSelector("progress_catalogue_"+connector_id)).show();
    jQuery(slyrIdSelector("progress_products_"+connector_id)).show();
    jQuery(slyrIdSelector("progress_product_formats_"+connector_id)).show();
    jQuery(slyrIdSelector("progress_product_links_"+connector_id)).show();
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
            jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).parent().hide();
            continue;
        }
    }
}

function processDataContentTable(data_content, table, connector_id)
{
    var progress_name = getProgressName(table);
    var sl_data_processed = data_content[table]['processed'];
    var sl_data_total = data_content[table]['total'];
    var data_now = jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).attr('aria-valuenow');
    var data_total = jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).attr('aria-valuemax');
    if (sl_data_total != data_total){
        jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).attr('aria-valuemax', sl_data_total); 
        jQuery(slyrIdSelector("sub_progress_span_"+table+'_'+connector_id)).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');         			 
    }
    if (sl_data_processed != data_now){
        jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).addClass('progress-bar-striped progress-bar-animated');
        jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).attr('aria-valuenow', sl_data_processed);
        jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).width(((sl_data_processed * 100) / sl_data_total)+'%');
        jQuery(slyrIdSelector("sub_progress_span_"+table+'_'+connector_id)).text(sl_data_processed+'/'+sl_data_total+progress_name+'processed.');
    }
    if (sl_data_processed == sl_data_total){ 
        jQuery(slyrIdSelector("sub_progress_"+table+'_'+connector_id)).removeClass('progress-bar-striped progress-bar-animated');
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
    jQuery(".progress").hide();
    setConnectorPageInputsDisabled(false);
    hideInitialLoader();
    jQuery('#messages').html(data['header']);
}

function processRestartStatusData(data, connector_id)
{
    jQuery(slyrIdSelector("sub_progress_catalogue_"+connector_id)).width(0+'%');
    jQuery(slyrIdSelector("sub_progress_products_"+connector_id)).width(0+'%');
    jQuery(slyrIdSelector("sub_progress_product_formats_"+connector_id)).width(0+'%');
    jQuery(slyrIdSelector("sub_progress_product_links_"+connector_id)).width(0+'%');
    jQuery(slyrIdSelector("sub_progress_catalogue_"+connector_id)).attr('aria-valuenow', 0);
    jQuery(slyrIdSelector("sub_progress_products_"+connector_id)).attr('aria-valuenow', 0);
    jQuery(slyrIdSelector("sub_progress_product_formats_"+connector_id)).attr('aria-valuenow', 0);
    jQuery(slyrIdSelector("sub_progress_product_links_"+connector_id)).attr('aria-valuenow', 0);
    jQuery(".progress").hide();
    setConnectorPageInputsDisabled(false);
    hideInitialLoader();
    if (data['content']) {
        jQuery('#messages').html(data['content']);
    }
}

// --- Multisite/Multilang Configuration Modal ---

// Global storage for modal data
window.slyrModalData = {
    sites: [],
    config: {},
    pluginLanguages: {},
    siteMappings: {},
    defaultLanguages: {}
};

/**
 * Open the multisite/multilang configuration modal for a given connector.
 * Fetches network sites and current config, then renders cards with language accordions.
 * @param {string} connectorId
 */
function openMultisiteModal(connectorId)
{
    // Must have either multisite or multilang plugin
    if (!slyrFlag(ajax_object.is_multisite_mode) && !slyrFlag(ajax_object.has_multilang_plugin)) {
        return;
    }

    jQuery('#slyr-multisite-connector-id').val(connectorId);
    jQuery('#slyr-sites-list').html('<div class="slyr-loading">Loading sites...</div>');
    jQuery('#slyr-validation-errors').hide().empty();
    jQuery('#slyr-multisite-save').prop('disabled', true);
    rememberModalOpener();
    jQuery('#slyr-multisite-overlay').css('display', 'flex');
    jQuery('#slyr-multisite-overlay .slyr-multisite-close').trigger('focus');

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
            jQuery('#slyr-sites-list').html('<div class="slyr-loading">Error loading data.</div>');
            return;
        }

        var sites = sitesData.data.sites;
        var mainSiteId = sitesData.data.main_site_id || 1;
        var config = configData.data;

        // Store in global for later use
        window.slyrModalData.sites = sites;
        window.slyrModalData.config = config;
        window.slyrModalData.pluginLanguages = config.plugin_languages_per_site || {};
        window.slyrModalData.siteMappings = config.site_mappings || [];
        window.slyrModalData.defaultLanguages = config.default_languages || {};
        window.slyrModalData.availableLanguages = config.available_languages || [];

        // Render SL languages info box
        renderSlLanguagesInfo(config.available_languages || []);

        renderSiteCards(sites, config, mainSiteId);

    }).fail(function() {
        jQuery('#slyr-sites-list').html('<div class="slyr-loading">Connection error. Please try again.</div>');
    });
}

/**
 * Render the Sales Layer languages info box at the top of the modal.
 * Shows which languages are available in the SL connector.
 *
 * @param {Array} slLanguages Array of SL language codes (e.g., ['es', 'en'])
 */
function renderSlLanguagesInfo(slLanguages)
{
    var $infoBox = jQuery('#slyr-sl-languages-info');

    // Check if multilang plugin is active. WordPress may stringify boolean values from DB/AJAX,
    // so we check for both boolean false and string "0" or "false".
    var isMultilangActive = slyrFlag(ajax_object.has_multilang_plugin);

    if (!isMultilangActive || !slLanguages || slLanguages.length === 0) {
        $infoBox.hide();
        return;
    }

    var html = '<div class="slyr-info-title">Map your site languages to these Sales Layer connector languages:</div>';
    html += '<div class="slyr-sl-lang-list">';
    for (var i = 0; i < slLanguages.length; i++) {
        html += '<span class="slyr-sl-lang-badge">' + escapeHtml(slLanguages[i]) + '</span>';
    }
    html += '</div>';

    $infoBox.html(html).show();
}

/**
 * Render site cards with language mapping dropdowns.
 * Each site is a card. If Polylang is active for that site, it shows a collapsible
 * language section with dropdowns to map to SL languages.
 *
 * @param {Array} sites Network sites
 * @param {Object} config Configuration data from connector
 * @param {number} mainSiteId The main site blog_id
 */
function renderSiteCards(sites, config, mainSiteId)
{
    var html = '';
    var savedTargets = config.multisite_targets || [];
    var siteMappings = config.site_mappings || [];
    var pluginLanguagesPerSite = config.plugin_languages_per_site || {};
    var defaultLanguages = config.default_languages || {};
    var availableLanguages = config.available_languages || []; // SL languages
    var hasMultilang = slyrFlag(ajax_object.has_multilang_plugin);
    var isMultisite = slyrFlag(ajax_object.is_multisite_mode);

    // Build lookup for saved targets and mappings
    var savedTargetsLookup = {};
    savedTargets.forEach(function(t) {
        savedTargetsLookup[t.blog_id] = t;
    });

    var savedMappingsLookup = {};
    siteMappings.forEach(function(m) {
        savedMappingsLookup[m.blog_id] = m.languages || {};
    });

    for (var s = 0; s < sites.length; s++) {
        var site = sites[s];
        var blogId = site.blog_id;
        var isMainSite = (blogId === mainSiteId);
        var hasWoo = site.has_woocommerce;
        var siteLanguages = pluginLanguagesPerSite[blogId] || [];
        var defaultLang = defaultLanguages[blogId] || '';
        var savedMapping = savedMappingsLookup[blogId] || {};

        // Determine if site is active
        var savedTarget = savedTargetsLookup[blogId];
        var isActive = false;
        if (!hasWoo) {
            isActive = false;
        } else if (savedTarget) {
            isActive = savedTarget.active !== false;
        } else if (isMainSite && savedTargets.length === 0) {
            isActive = true;
        }

        // Determine if this card has an accordion
        var hasAccordion = hasMultilang && siteLanguages.length > 0 && hasWoo;

        // Card classes
        var cardClasses = 'slyr-site-card';
        if (!hasWoo) cardClasses += ' disabled';
        if (!hasAccordion) cardClasses += ' no-accordion';
        if (!isActive && hasWoo) cardClasses += ' slyr-inactive';

        // Click handler on header: only if has accordion and site is active
        var headerClick = hasAccordion ? 'onclick="toggleSiteAccordion(' + blogId + ')"' : '';

        html += '<div class="' + cardClasses + '" data-blog-id="' + blogId + '">';

        // Card Header — fully clickable for accordion
        html += '<div class="slyr-site-card-header" ' + headerClick + '>';
        html += '<div class="slyr-site-info">';
        html += '<span class="slyr-site-name">' + blogId + '. ' + escapeHtml(site.blogname);
        if (isMainSite) {
            html += '<span class="slyr-badge slyr-badge-main">Main Site</span>';
        }
        if (!hasWoo) {
            html += '<span class="slyr-badge slyr-badge-no-woo">No WooCommerce</span>';
        }
        html += '</span>';
        html += '<span class="slyr-site-url">' + escapeHtml(site.siteurl) + '</span>';
        html += '</div>'; // .slyr-site-info

        html += '<div class="slyr-site-controls">';

        // Activate toggle switch (only in multisite mode).
        // stopPropagation is on the <label> only so clicking the text label
        // still propagates to the header and opens the accordion.
        if (isMultisite) {
            var checkDisabled = !hasWoo ? ' disabled' : '';
            var checkChecked = isActive ? ' checked' : '';
            var mainAttr = isMainSite ? ' data-main-site="1"' : '';
            var toggleLabel = isActive ? 'Active' : 'Activate';
            html += '<div class="slyr-site-active-toggle">';
            html += '<label class="slyr-toggle-switch" onclick="event.stopPropagation()">';
            html += '<input type="checkbox" class="slyr-site-active"' + checkChecked + checkDisabled + mainAttr + ' />';
            html += '<span class="slyr-toggle-track"></span>';
            html += '</label>';
            html += '<span>' + toggleLabel + '</span>';
            html += '</div>';
        }

        html += '</div>'; // .slyr-site-controls

        // Chevron — only if has accordion
        if (hasAccordion) {
            html += '<div class="slyr-site-chevron">';
            html += '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4,6 8,10 12,6"/></svg>';
            html += '</div>';
        }

        html += '</div>'; // .slyr-site-card-header

        // Accordion Body (languages with SL mapping dropdowns)
        if (hasMultilang && siteLanguages.length > 0 && hasWoo) {
            html += '<div class="slyr-site-accordion-body" data-blog-id="' + blogId + '">';
            html += '<div class="slyr-language-list">';

            for (var l = 0; l < siteLanguages.length; l++) {
                var lang = siteLanguages[l];
                var langCode = lang.code || lang.slug || lang;
                var langName = lang.name || langCode;
                var isDefault = (langCode === defaultLang);
                // Get saved SL language mapping for this Polylang language
                var savedSlLang = savedMapping[langCode] || '';

                var itemClass = 'slyr-language-item';
                if (savedSlLang !== '') itemClass += ' mapped';

                html += '<div class="' + itemClass + '" data-lang-code="' + escapeHtml(langCode) + '">';
                html += '<span class="slyr-language-name">' + escapeHtml(langName);
                if (isDefault) {
                    html += '<span class="slyr-lang-primary-tooltip">';
                    html += '<i class="slyr-lang-primary-icon">?</i>';
                    html += '<span class="slyr-lang-primary-tipbox">Primary language of this site</span>';
                    html += '</span>';
                }
                html += '</span>';
                html += '<span class="slyr-language-code">' + escapeHtml(langCode) + '</span>';

                // Dropdown for SL language mapping
                html += '<select class="slyr-language-mapping-select" data-lang-code="' + escapeHtml(langCode) + '"';
                html += ' data-blog-id="' + blogId + '"';
                if (isDefault) html += ' data-is-default="1"';
                html += ' onchange="handleLanguageMappingChange(this)">';
                html += '<option value="">-- No language --</option>';
                for (var sl = 0; sl < availableLanguages.length; sl++) {
                    var slLang = availableLanguages[sl];
                    var selected = (slLang === savedSlLang) ? ' selected' : '';
                    html += '<option value="' + escapeHtml(slLang) + '"' + selected + '>' + escapeHtml(slLang.toUpperCase()) + '</option>';
                }
                html += '</select>';

                html += '</div>'; // .slyr-language-item
            }

            html += '</div>'; // .slyr-language-list
            html += '</div>'; // .slyr-site-accordion-body
        }

        html += '</div>'; // .slyr-site-card
    }

    if (html === '') {
        html = '<div class="slyr-loading">No sites found.</div>';
    }

    jQuery('#slyr-sites-list').html(html);

    // Initialize validation state
    validateAllSitesLanguages();

    // In multisite mode, bind checkbox change for main site logic
    if (isMultisite) {
        updateMainSiteCheckboxState();
        jQuery('#slyr-sites-list').off('change', '.slyr-site-active').on(
            'change', '.slyr-site-active', function() {
                var $checkbox = jQuery(this);
                var $card = $checkbox.closest('.slyr-site-card');
                var isActive = $checkbox.is(':checked');

                // Toggle inactive visual state and label.
                // $checkbox is inside .slyr-toggle-switch, so we must climb up to the
                // label and target the text span (last direct child), not .slyr-toggle-track.
                var $textLabel = $checkbox.closest('.slyr-site-active-toggle').children('span').last();
                if (isActive) {
                    $card.removeClass('slyr-inactive');
                    $textLabel.text('Active');
                } else {
                    $card.addClass('slyr-inactive').removeClass('expanded');
                    $textLabel.text('Activate');
                }

                updateMainSiteCheckboxState();
                validateAllSitesLanguages();
            }
        );
    }
}

/**
 * Dynamically lock/unlock the main site checkbox based on other active sites.
 * Updated to work with the new card-based structure.
 *
 * Rules:
 *   - If at least one non-main site is checked → main site is unlockable (user can uncheck it)
 *   - If no non-main site is checked → main site is forced checked + disabled (minimum 1 guarantee)
 */
function updateMainSiteCheckboxState()
{
    var $mainCheckbox = jQuery('#slyr-sites-list .slyr-site-active[data-main-site="1"]');
    if ($mainCheckbox.length === 0) {
        return;
    }

    // Count active non-main, non-disabled-by-woo checkboxes
    var otherActiveCount = jQuery('#slyr-sites-list .slyr-site-active')
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
 * Toggle the accordion for a site's language section.
 * Only opens if the site is active.
 * @param {number} blogId
 */
function toggleSiteAccordion(blogId)
{
    var $card = jQuery('.slyr-site-card[data-blog-id="' + blogId + '"]');
    if ($card.hasClass('slyr-inactive')) {
        return;
    }
    $card.toggleClass('expanded');
}

/**
 * Handle language mapping dropdown change event.
 * Updates visual state and triggers validation for ALL sites.
 * If all dropdowns in ANY active site are set to "No language", auto-selects the first SL language
 * for the default Polylang language of that site.
 *
 * @param {HTMLElement} select The dropdown element
 */
function handleLanguageMappingChange(select)
{
    var $select = jQuery(select);
    var $item = $select.closest('.slyr-language-item');

    // Update visual state based on whether a SL language is mapped
    if ($select.val() !== '') {
        $item.addClass('mapped');
    } else {
        $item.removeClass('mapped');
    }

    // Validate all sites — do NOT auto-enforce here so the user can see the error
    // when all languages are deselected. enforceLanguageMappingOnAllSites() runs
    // only on initial render to set sensible defaults.
    validateAllSitesLanguages();
}

/**
 * Ensures all active sites have at least one language mapped.
 * For any site with all dropdowns set to "No language", auto-selects the first SL language
 * for the default Polylang language.
 */
function enforceLanguageMappingOnAllSites()
{
    var isMultisite = slyrFlag(ajax_object.is_multisite_mode);
    var availableLanguages = window.slyrModalData.availableLanguages || [];

    if (availableLanguages.length === 0) {
        return;
    }

    jQuery('.slyr-site-card').each(function() {
        var $card = jQuery(this);

        // Skip disabled cards (no WooCommerce)
        if ($card.hasClass('disabled')) {
            return;
        }

        // Check if site is active
        var isActive = true;
        if (isMultisite) {
            isActive = $card.find('.slyr-site-active').is(':checked');
        }

        if (!isActive) {
            return;
        }

        // Check if site has language dropdowns
        var $allDropdowns = $card.find('.slyr-language-mapping-select');
        if ($allDropdowns.length === 0) {
            return;
        }

        // Check if ALL dropdowns are "No language"
        var $mappedDropdowns = $allDropdowns.filter(function() {
            return jQuery(this).val() !== '';
        });

        if ($mappedDropdowns.length === 0) {
            // All dropdowns are "No language" - auto-select first SL language for default
            var $defaultDropdown = $card.find('.slyr-language-mapping-select[data-is-default="1"]');

            if ($defaultDropdown.length > 0) {
                var firstSlLang = availableLanguages[0];
                $defaultDropdown.val(firstSlLang);
                $defaultDropdown.closest('.slyr-language-item').addClass('mapped');
            }
        }
    });
}

/**
 * Validate all active sites have at least one language mapped to SL.
 * Shows error messages and enables/disables the Save button.
 */
function validateAllSitesLanguages()
{
    var errors = [];
    var hasMultilang = slyrFlag(ajax_object.has_multilang_plugin);
    var isMultisite = slyrFlag(ajax_object.is_multisite_mode);

    jQuery('.slyr-site-card').each(function() {
        var $card = jQuery(this);
        var blogId = $card.data('blog-id');

        // Skip disabled cards (no WooCommerce)
        if ($card.hasClass('disabled')) {
            return;
        }

        // Check if site is active
        var isActive = true;
        if (isMultisite) {
            isActive = $card.find('.slyr-site-active').is(':checked');
        }

        if (!isActive) {
            return;
        }

        // If multilang is active and site has language dropdowns, check at least one has a mapping
        var $accordionBody = $card.find('.slyr-site-accordion-body');
        if (hasMultilang && $accordionBody.length > 0) {
            var $mappedDropdowns = $card.find('.slyr-language-mapping-select').filter(function() {
                return jQuery(this).val() !== '';
            });
            if ($mappedDropdowns.length === 0) {
                var siteName = $card.find('.slyr-site-name').text().trim();
                errors.push('Site "' + siteName + '" requires at least one language mapped to Sales Layer.');
            }
        }
    });

    // Show/hide errors
    var $errorContainer = jQuery('#slyr-validation-errors');
    if (errors.length > 0) {
        var errorHtml = '<div class="slyr-error-title">⚠️ Configuration Error</div>';
        errorHtml += '<ul>';
        errors.forEach(function(err) {
            errorHtml += '<li>' + escapeHtml(err) + '</li>';
        });
        errorHtml += '</ul>';
        $errorContainer.html(errorHtml).show();
        jQuery('#slyr-multisite-save').prop('disabled', true);
    } else {
        $errorContainer.hide().empty();
        jQuery('#slyr-multisite-save').prop('disabled', false);
    }

    return errors.length === 0;
}

/**
 * Close the multisite configuration modal.
 */
function closeMultisiteModal()
{
    jQuery('#slyr-multisite-overlay').css('display', 'none');
    restoreModalOpenerFocus();
}

/**
 * Save multisite/multilang configuration from the modal form.
 * Collects targets and language mappings from the card-based UI.
 * Disabled cards (no WooCommerce) are skipped.
 * Backend enforces at least one active site (falls back to main site).
 */
function saveMultisiteConfig()
{
    // Validate before saving
    if (!validateAllSitesLanguages()) {
        return;
    }

    var connectorId = jQuery('#slyr-multisite-connector-id').val();
    var isMultisite = slyrFlag(ajax_object.is_multisite_mode);
    var hasMultilang = slyrFlag(ajax_object.has_multilang_plugin);

    var targets = [];
    var siteMappings = [];

    jQuery('.slyr-site-card').each(function() {
        var $card = jQuery(this);
        var blogId = $card.data('blog-id');

        if (typeof blogId === 'undefined') {
            return;
        }

        // Skip disabled cards (no WooCommerce)
        if ($card.hasClass('disabled')) {
            return;
        }

        // Determine active state
        var isActive = true;
        if (isMultisite) {
            isActive = $card.find('.slyr-site-active').is(':checked');
        }

        targets.push({
            blog_id: blogId,
            active: isActive
        });

        // Collect language mappings from dropdowns if multilang is active
        if (hasMultilang && isActive) {
            var languages = {};
            $card.find('.slyr-language-mapping-select').each(function() {
                var $select = jQuery(this);
                var polylangCode = $select.data('lang-code');
                var slLangCode = $select.val();
                // Only include if a SL language is selected (not "No language")
                if (slLangCode !== '') {
                    languages[polylangCode] = slLangCode;
                }
            });

            if (Object.keys(languages).length > 0) {
                siteMappings.push({
                    blog_id: blogId,
                    languages: languages
                });
            }
        }
    });

    jQuery.ajax({
        type: 'POST',
        url: ajax_object.ajaxurl,
        dataType: 'json',
        data: {
            action: 'slyr_save_multisite_config',
            nonce: ajax_object.multisite_nonce,
            connector_id: connectorId,
            targets: JSON.stringify(targets),
            site_mappings: JSON.stringify(siteMappings)
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
            connector_id: connectorId,
            nonce: ajax_object.nonce
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                showMessage('warning', (response.data && response.data.message) || 'Error deleting connector.');
                clear_message_status();
            }
        },
        error: function(xhr) {
            showMessage('warning', slyrAjaxErrorMessage(xhr, 'Connection error. Please try again.'));
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
