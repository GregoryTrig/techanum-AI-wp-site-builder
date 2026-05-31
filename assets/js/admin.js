jQuery(document).ready(function($) {
    // Add UI elements to the settings page for creating pages from prompts
    if (window.location.pathname.includes('options-general.php') && window.location.search.includes('page=prompt-to-page')) {
        // Create a section for prompt-based page creation
        var $settingsWrap = $('.wrap');
        
        // Add the prompt input section
        var promptSection = 
            '<div id="ptp-prompt-section" class="postbox">' +
            '   <h2 class="hndle">Create Page from Prompt</h2>' +
            '   <div class="inside">' +
            '       <p>Enter a prompt to generate content for a new page:</p>' +
            '       <textarea id="ptp_prompt" rows="5" cols="50" placeholder="Describe the content you want for your new page..."></textarea><br><br>' +
            '       <button id="ptp_create_page_btn" class="button button-primary">Create Page</button>' +
            '       <div id="ptp_response_message"></div>' +
            '   </div>' +
            '</div>';
        
        $settingsWrap.append(promptSection);
        
        // Handle page creation
        $('#ptp_create_page_btn').on('click', function(e) {
            e.preventDefault();
            
            var prompt = $('#ptp_prompt').val().trim();
            var $button = $(this);
            var $message = $('#ptp_response_message');
            
            // Validate input
            if (prompt === '') {
                $message.html('<div class="notice notice-error"><p>Prompt cannot be empty</p></div>');
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Creating...');
            $message.html('');
            
            // Send AJAX request
            $.ajax({
                url: ptp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_page_from_prompt',
                    nonce: ptp_ajax.nonce,
                    prompt: prompt
                },
                success: function(response) {
                    if (response.success) {
                        $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        // Show link to edit the created page
                        var editUrl = 'post.php?post=' + response.data.page_id + '&action=edit';
                        $message.append('<p><a href="' + editUrl + '" class="button button-secondary" target="_blank">Edit Created Page</a></p>');
                    } else {
                        $message.html('<div class="notice notice-error"><p>Error: ' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $message.html('<div class="notice notice-error"><p>An unexpected error occurred</p></div>');
                },
                complete: function() {
                    // Re-enable button
                    $button.prop('disabled', false).text('Create Page');
                }
            });
        });
    }
});