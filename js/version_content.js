jQuery(document).ready(function($)
{
    if (versionMessageContainerExists() && versionContentExists()){
        jQuery('#version-message-container')
            .attr('class', versionContent.div_class)
            .html(versionContent.div_content)
            .fadeIn();
        jQuery('#notice-dismiss').on('click', function() {
            jQuery('#version-message-container').fadeOut();
        });
    }
});

function versionMessageContainerExists()
{
    return jQuery('#version-message-container').length > 0;
}

function versionContentExists()
{
    return typeof versionContent !== 'undefined' && Object.keys(versionContent).length > 0;
}