/**
 * Production Goals - Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initTabs();
        initModals();
        initClipboard();
    });

    /**
     * Initialize tab functionality
     */
    function initTabs() {
        $('.pg-admin-tab-button').on('click', function() {
            const tabId = $(this).data('tab');
            
            // Update active tab button
            $('.pg-admin-tab-button').removeClass('active');
            $(this).addClass('active');
            
            // Show selected tab content
            $('.pg-admin-tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        });
    }

    /**
     * Initialize modal functionality
     */
    function initModals() {
        // Close modal when clicking the close button
        $('.pg-modal-close').on('click', function() {
            $(this).closest('.pg-modal').hide();
        });
        
        // Close modal when clicking outside content
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('pg-modal')) {
                $('.pg-modal').hide();
            }
        });
    }

    /**
     * Initialize clipboard copy functionality
     */
    function initClipboard() {
        $('.copy-to-clipboard').on('click', function() {
            const text = $(this).data('clipboard');
            const tempTextarea = document.createElement('textarea');
            tempTextarea.value = text;
            document.body.appendChild(tempTextarea);
            tempTextarea.select();
            document.execCommand('copy');
            document.body.removeChild(tempTextarea);
            
            const originalText = $(this).text();
            $(this).text('Copied!');
            
            setTimeout(() => {
                $(this).text(originalText);
            }, 2000);
        });
    }

    /**
     * Display an alert message
     */
    function showAlert(message, type = 'success') {
        const alertBox = $('<div class="notice ' + (type === 'success' ? 'notice-success' : 'notice-error') + ' is-dismissible"><p>' + message + '</p></div>');
        $('.pg-admin-wrap').prepend(alertBox);
        
        // Auto-dismiss after delay
        setTimeout(function() {
            alertBox.fadeOut('slow', function() {
                $(this).remove();
            });
        }, 5000);
    }

})(jQuery);

/**
 * Initialize the admin after the page is fully loaded
 */
jQuery(window).on('load', function() {
    // Localize for AJAX operations
    const pg_admin_data = {
        nonce: typeof productionGoalsAdmin !== 'undefined' ? productionGoalsAdmin.nonce : '',
        ajaxUrl: typeof productionGoalsAdmin !== 'undefined' ? productionGoalsAdmin.ajaxUrl : ajaxurl
    };
});
