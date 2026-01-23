/**
 * BusinessApp Admin Settings JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab switching
        $('.businessapp-tab').on('click', function(e) {
            e.preventDefault();
            
            var targetTab = $(this).data('tab');
            
            // Update URL without reload
            var url = new URL(window.location);
            url.searchParams.set('tab', targetTab);
            window.history.pushState({}, '', url);
            
            // Update active tab
            $('.businessapp-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show corresponding content
            $('.businessapp-tab-content').hide();
            $('#businessapp-tab-' + targetTab).show();
        });

        // Logo upload
        $('#businessapp-upload-logo').on('click', function(e) {
            e.preventDefault();
            
            var mediaUploader = wp.media({
                title: 'Choose Business Logo',
                button: {
                    text: 'Use this logo'
                },
                multiple: false
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#businessapp-logo-url').val(attachment.url);
                $('#businessapp-logo-preview-img').attr('src', attachment.url).show();
            });

            mediaUploader.open();
        });

        // Form submission with AJAX
        $('#businessapp-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            var $saveBtn = $('.businessapp-save-btn');
            var originalText = $saveBtn.html();
            
            $saveBtn.prop('disabled', true).html('Saving...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData + '&action=businessapp_save_settings_ajax',
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        $('.businessapp-success-message').addClass('show').text('Settings saved successfully!');
                        
                        setTimeout(function() {
                            $('.businessapp-success-message').removeClass('show');
                        }, 3000);
                    }
                },
                error: function() {
                    alert('Error saving settings. Please try again.');
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Add template functionality
        $('.businessapp-add-template-btn').on('click', function(e) {
            e.preventDefault();
            
            var templateName = prompt('Enter template name:');
            if (templateName) {
                var templatePrice = prompt('Enter default price:');
                if (templatePrice) {
                    addTemplateRow(templateName, templatePrice);
                }
            }
        });

        function addTemplateRow(name, price) {
            var html = '<div class="businessapp-template-item">' +
                '<label>' +
                '<input type="checkbox" name="templates[]" value="' + name + '">' +
                '<span>' + name + '</span>' +
                '</label>' +
                '<span class="businessapp-template-price">$' + price + '</span>' +
                '</div>';
            
            $('.businessapp-template-list').append(html);
        }

        // Handle business type change
        $('#businessapp-business-type').on('change', function() {
            var businessType = $(this).val();
            
            // Hide all business type specific sections
            $('.businessapp-business-type-section').hide();
            
            // Show relevant section
            $('#businessapp-type-' + businessType).show();
        });

        // Initialize: show current tab
        var urlParams = new URLSearchParams(window.location.search);
        var currentTab = urlParams.get('tab') || 'general';
        
        $('.businessapp-tab[data-tab="' + currentTab + '"]').addClass('active');
        $('.businessapp-tab-content').hide();
        $('#businessapp-tab-' + currentTab).show();
    });

})(jQuery);
