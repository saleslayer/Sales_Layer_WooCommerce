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

/**
 * Show initial loading spinner while checking sync status.
 */
function showInitialLoader()
{
    var loaderHtml = '<div class="slyr-initial-loader">';
    loaderHtml += '<div class="slyr-spinner"></div>';
    loaderHtml += '<span>Checking synchronization status...</span>';
    loaderHtml += '</div>';
    $('#messages').html(loaderHtml).show();
}

/**
 * Hide the initial loader.
 */
function hideInitialLoader()
{
    var $loader = $('#messages .slyr-initial-loader');
    if ($loader.length > 0) {
        $loader.fadeOut(200, function() {
            $(this).remove();
        });
    }
}

function start_check_process_status()
{
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
    hideInitialLoader();
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
    hideInitialLoader();
    if (data['content']) {
        $('#messages').html(data['content']);
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
    if (!ajax_object.is_multisite_mode && !ajax_object.has_multilang_plugin) {
        return;
    }

    $('#slyr-multisite-connector-id').val(connectorId);
    $('#slyr-sites-list').html('<div class="slyr-loading">Loading sites...</div>');
    $('#slyr-validation-errors').hide().empty();
    $('#slyr-multisite-save').prop('disabled', true);
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
            $('#slyr-sites-list').html('<div class="slyr-loading">Error loading data.</div>');
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
        $('#slyr-sites-list').html('<div class="slyr-loading">Connection error. Please try again.</div>');
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
    var $infoBox = $('#slyr-sl-languages-info');

    if (!slLanguages || slLanguages.length === 0) {
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
    var hasMultilang = ajax_object.has_multilang_plugin;
    var isMultisite = ajax_object.is_multisite_mode;

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

    $('#slyr-sites-list').html(html);

    // Initialize validation state
    validateAllSitesLanguages();

    // In multisite mode, bind checkbox change for main site logic
    if (isMultisite) {
        updateMainSiteCheckboxState();
        $('#slyr-sites-list').off('change', '.slyr-site-active').on(
            'change', '.slyr-site-active', function() {
                var $checkbox = $(this);
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
    var $mainCheckbox = $('#slyr-sites-list .slyr-site-active[data-main-site="1"]');
    if ($mainCheckbox.length === 0) {
        return;
    }

    // Count active non-main, non-disabled-by-woo checkboxes
    var otherActiveCount = $('#slyr-sites-list .slyr-site-active')
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
    var $card = $('.slyr-site-card[data-blog-id="' + blogId + '"]');
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
    var $select = $(select);
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
    var isMultisite = ajax_object.is_multisite_mode;
    var availableLanguages = window.slyrModalData.availableLanguages || [];

    if (availableLanguages.length === 0) {
        return;
    }

    $('.slyr-site-card').each(function() {
        var $card = $(this);

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
            return $(this).val() !== '';
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
    var hasMultilang = ajax_object.has_multilang_plugin;
    var isMultisite = ajax_object.is_multisite_mode;

    $('.slyr-site-card').each(function() {
        var $card = $(this);
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
                return $(this).val() !== '';
            });
            if ($mappedDropdowns.length === 0) {
                var siteName = $card.find('.slyr-site-name').text().trim();
                errors.push('Site "' + siteName + '" requires at least one language mapped to Sales Layer.');
            }
        }
    });

    // Show/hide errors
    var $errorContainer = $('#slyr-validation-errors');
    if (errors.length > 0) {
        var errorHtml = '<div class="slyr-error-title">⚠️ Configuration Error</div>';
        errorHtml += '<ul>';
        errors.forEach(function(err) {
            errorHtml += '<li>' + escapeHtml(err) + '</li>';
        });
        errorHtml += '</ul>';
        $errorContainer.html(errorHtml).show();
        $('#slyr-multisite-save').prop('disabled', true);
    } else {
        $errorContainer.hide().empty();
        $('#slyr-multisite-save').prop('disabled', false);
    }

    return errors.length === 0;
}

/**
 * Close the multisite configuration modal.
 */
function closeMultisiteModal()
{
    $('#slyr-multisite-overlay').css('display', 'none');
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

    var connectorId = $('#slyr-multisite-connector-id').val();
    var isMultisite = ajax_object.is_multisite_mode;
    var hasMultilang = ajax_object.has_multilang_plugin;

    var targets = [];
    var siteMappings = [];

    $('.slyr-site-card').each(function() {
        var $card = $(this);
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
                var $select = $(this);
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