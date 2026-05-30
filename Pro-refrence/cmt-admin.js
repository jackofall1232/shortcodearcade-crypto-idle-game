/**
 * Crypto Miner Tycoon Pro - Admin JavaScript
 * Version: 1.1.0 - White Label Edition
 * 
 * Handles admin UI, media uploader, color pickers, and AJAX interactions
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Tab switching with smooth animation
         */
        function handleTabSwitching() {
            $('.cmt-tab-wrapper .nav-tab').on('click', function(e) {
                // Let the link work normally, but add animation
                const $content = $('.cmt-tab-content');
                
                // Fade out
                $content.css('opacity', '0');
                
                // Fade back in after page load (handled by browser)
                setTimeout(function() {
                    $content.css('opacity', '1');
                }, 100);
            });
            
            // Initial fade in
            $('.cmt-tab-content').css({
                'opacity': '0',
                'transition': 'opacity 0.3s ease'
            });
            
            setTimeout(function() {
                $('.cmt-tab-content').css('opacity', '1');
            }, 100);
        }
        
        /**
         * Handle cloud saves checkbox dependency
         */
        function handleCloudSavesDependency() {
            const $cloudSaves = $('input[name="cmt_enable_cloud_saves"]');
            const $leaderboard = $('input[name="cmt_enable_leaderboard"]');
            
            if (!$cloudSaves.length || !$leaderboard.length) {
                return;
            }
            
            function updateLeaderboardState() {
                if ($cloudSaves.is(':checked')) {
                    $leaderboard.prop('disabled', false);
                    $leaderboard.closest('label').css('opacity', '1');
                } else {
                    $leaderboard.prop('disabled', true);
                    $leaderboard.prop('checked', false);
                    $leaderboard.closest('label').css('opacity', '0.5');
                }
            }
            
            // Initial state
            updateLeaderboardState();
            
            // Update on change
            $cloudSaves.on('change', updateLeaderboardState);
        }
        
        /**
         * Confirm before disabling cloud saves if data exists
         */
        function handleCloudSavesDisableWarning() {
            const $cloudSaves = $('input[name="cmt_enable_cloud_saves"]');
            const $form = $cloudSaves.closest('form');
            
            if (!$cloudSaves.length) {
                return;
            }
            
            // Store initial state
            const initialState = $cloudSaves.is(':checked');
            
            $form.on('submit', function(e) {
                const currentState = $cloudSaves.is(':checked');
                
                // If changing from enabled to disabled
                if (initialState && !currentState) {
                    const confirmed = confirm(
                        'Warning: Disabling cloud saves will prevent users from saving/loading their games.\n\n' +
                        'Existing saved games will remain in the database but will be inaccessible.\n\n' +
                        'Are you sure you want to disable cloud saves?'
                    );
                    
                    if (!confirmed) {
                        e.preventDefault();
                        $cloudSaves.prop('checked', true);
                        return false;
                    }
                }
            });
        }
        
        /**
         * Leaderboard limit validation
         */
        function handleLeaderboardLimitValidation() {
            const $limitInput = $('input[name="cmt_leaderboard_limit"]');
            
            if (!$limitInput.length) {
                return;
            }
            
            $limitInput.on('change', function() {
                let value = parseInt($(this).val());
                
                if (isNaN(value) || value < 5) {
                    value = 5;
                } else if (value > 100) {
                    value = 100;
                }
                
                $(this).val(value);
            });
        }
        
        /**
         * Add helpful tooltips
         */
        function addTooltips() {
            // Add tooltip to cloud saves checkbox
            const $cloudSavesLabel = $('input[name="cmt_enable_cloud_saves"]').closest('label');
            if ($cloudSavesLabel.length && !$cloudSavesLabel.find('.cmt-help-icon').length) {
                $cloudSavesLabel.append(' <span class="cmt-help-icon dashicons dashicons-info" title="Saves game data to WordPress database. Requires users to be logged in."></span>');
            }
            
            // Add tooltip to leaderboard checkbox
            const $leaderboardLabel = $('input[name="cmt_enable_leaderboard"]').closest('label');
            if ($leaderboardLabel.length && !$leaderboardLabel.find('.cmt-help-icon').length) {
                $leaderboardLabel.append(' <span class="cmt-help-icon dashicons dashicons-info" title="Display top players using the [crypto_miner_leaderboard] shortcode."></span>');
            }
            
            // Make dashicons visible
            $('.cmt-help-icon').css({
                'cursor': 'help',
                'color': '#787c82',
                'font-size': '16px',
                'vertical-align': 'middle'
            });
        }
        
        /**
         * Copy shortcode to clipboard
         */
        function handleShortcodeCopy() {
            $('.cmt-info-box code').on('click', function() {
                const $code = $(this);
                const text = $code.text();
                
                // Create temporary input
                const $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
                
                // Visual feedback
                const originalBg = $code.css('background-color');
                $code.css('background-color', '#46b450');
                
                setTimeout(function() {
                    $code.css('background-color', originalBg);
                }, 200);
                
                // Show tooltip
                const $tooltip = $('<span class="cmt-copied-tooltip">Copied!</span>');
                $tooltip.css({
                    'position': 'absolute',
                    'background': '#1d2327',
                    'color': '#fff',
                    'padding': '4px 8px',
                    'border-radius': '3px',
                    'font-size': '11px',
                    'margin-left': '8px',
                    'z-index': '1000'
                });
                
                $code.after($tooltip);
                
                setTimeout(function() {
                    $tooltip.fadeOut(function() {
                        $(this).remove();
                    });
                }, 1500);
            });
            
            // Add cursor pointer to codes
            $('.cmt-info-box code').css('cursor', 'pointer');
        }
        
        /**
         * Animate stats on page load
         */
        function animateStats() {
            $('.cmt-stat-value').each(function() {
                const $this = $(this);
                const finalValue = parseInt($this.text());
                
                if (isNaN(finalValue)) {
                    return;
                }
                
                $this.text('0');
                
                $({ counter: 0 }).animate({ counter: finalValue }, {
                    duration: 1000,
                    easing: 'swing',
                    step: function() {
                        $this.text(Math.ceil(this.counter));
                    },
                    complete: function() {
                        $this.text(finalValue);
                    }
                });
            });
        }
        
        /**
         * Settings form unsaved changes warning
         */
        function handleUnsavedChanges() {
            const $form = $('form');
            let formChanged = false;
            
            if (!$form.length) {
                return;
            }
            
            // Track changes
            $form.on('change', 'input, select, textarea', function() {
                formChanged = true;
            });
            
            // Warn on page leave
            $(window).on('beforeunload', function() {
                if (formChanged) {
                    return 'You have unsaved changes. Are you sure you want to leave?';
                }
            });
            
            // Don't warn on form submit
            $form.on('submit', function() {
                formChanged = false;
            });
        }
        
        /**
         * Initialize color pickers
         */
        function initColorPickers() {
            if (typeof $.fn.wpColorPicker !== 'undefined') {
                $('.cmt-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        // Color changed
                        $(this).val(ui.color.toString());
                    },
                    clear: function() {
                        // Color cleared
                    }
                });
            }
        }
        
        /**
         * Initialize media uploader for coin image
         */
        function initMediaUploader() {
            var cmtMediaUploader;
            
            // Upload button click handler
            $('#cmt-upload-coin').on('click', function(e) {
                e.preventDefault();
                
                // If uploader already exists, open it
                if (cmtMediaUploader) {
                    cmtMediaUploader.open();
                    return;
                }
                
                // Create new media uploader
                cmtMediaUploader = wp.media({
                    title: 'Choose Custom Coin Image',
                    button: {
                        text: 'Use This Image'
                    },
                    library: {
                        type: ['image']
                    },
                    multiple: false
                });
                
                // When image is selected
                cmtMediaUploader.on('select', function() {
                    var attachment = cmtMediaUploader.state().get('selection').first().toJSON();
                    
                    // Update hidden input
                    $('#cmt_pro_custom_coin').val(attachment.url);
                    
                    // Update preview
                    $('#cmt-coin-preview').html(
                        '<img src="' + attachment.url + '" alt="Custom Coin" style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">' +
                        '<button type="button" class="button cmt-remove-coin">Remove Image</button>'
                    );
                    
                    // Update button text
                    $('#cmt-upload-coin').text('Change Image');
                });
                
                // Open the uploader
                cmtMediaUploader.open();
            });
            
            // Remove button click handler (delegated for dynamically added button)
            $(document).on('click', '.cmt-remove-coin', function(e) {
                e.preventDefault();
                
                // Clear hidden input
                $('#cmt_pro_custom_coin').val('');
                
                // Reset preview to placeholder
                $('#cmt-coin-preview').html(
                    '<div class="cmt-coin-placeholder" style="padding: 40px; text-align: center; border: 2px dashed #ddd; background: #f9f9f9;">' +
                        '<span class="dashicons dashicons-format-image" style="font-size: 48px; width: 48px; height: 48px; color: #ccc;"></span>' +
                        '<p style="margin: 10px 0 0; color: #666;">No custom coin image set</p>' +
                    '</div>'
                );
                
                // Update button text
                $('#cmt-upload-coin').text('Upload Image');
            });
        }
        
        /**
         * Initialize contest management
         */
        function initContestManagement() {
            // Create contest form submission
            $('#cmt-create-contest-form').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]');
                const originalText = $submitBtn.text();
                
                // Disable button and show loading
                $submitBtn.prop('disabled', true).text('Creating...');
                
                $.ajax({
                    url: cmtAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmt_create_contest',
                        nonce: cmtAdmin.nonce,
                        period_name: $('#period_name').val(),
                        period_type: $('#period_type').val(),
                        period_start: $('#period_start').val(),
                        period_end: $('#period_end').val(),
                        prize_first: $('#prize_first').val(),
                        prize_second: $('#prize_second').val(),
                        prize_third: $('#prize_third').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message
                            showNotice('success', response.data.message);
                            
                            // Redirect to contests list
                            setTimeout(function() {
                                window.location.href = '?page=crypto-miner-tycoon&tab=contests';
                            }, 1000);
                        } else {
                            showNotice('error', response.data || 'Failed to create contest');
                            $submitBtn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        showNotice('error', 'An error occurred. Please try again.');
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // End contest
            $('.cmt-end-contest').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to end this contest? Winners will be automatically selected.')) {
                    return;
                }
                
                const $btn = $(this);
                const periodId = $btn.data('period-id');
                const originalText = $btn.text();
                
                $btn.prop('disabled', true).text('Ending...');
                
                $.ajax({
                    url: cmtAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmt_end_contest',
                        nonce: cmtAdmin.nonce,
                        period_id: periodId
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('success', response.data.message);
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showNotice('error', response.data || 'Failed to end contest');
                            $btn.prop('disabled', false).text(originalText);
                        }
                    },
                    error: function() {
                        showNotice('error', 'An error occurred. Please try again.');
                        $btn.prop('disabled', false).text(originalText);
                    }
                });
            });
            
            // Delete contest
            $('.cmt-delete-contest').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to delete this contest? This action cannot be undone.')) {
                    return;
                }
                
                const $btn = $(this);
                const periodId = $btn.data('period-id');
                
                $.ajax({
                    url: cmtAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cmt_delete_contest',
                        nonce: cmtAdmin.nonce,
                        period_id: periodId
                    },
                    success: function(response) {
                        if (response.success) {
                            showNotice('success', response.data);
                            $btn.closest('tr').fadeOut(function() {
                                $(this).remove();
                            });
                        } else {
                            showNotice('error', response.data || 'Failed to delete contest');
                        }
                    },
                    error: function() {
                        showNotice('error', 'An error occurred. Please try again.');
                    }
                });
            });
        }
        
        /**
         * Show admin notice
         */
        function showNotice(type, message) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.cmt-admin-wrap h1').after($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Make dismissible
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
        
        /**
         * Initialize all admin functionality
         */
        function init() {
            handleTabSwitching();
            handleCloudSavesDependency();
            handleCloudSavesDisableWarning();
            handleLeaderboardLimitValidation();
            addTooltips();
            handleShortcodeCopy();
            animateStats();
            handleUnsavedChanges();
            initColorPickers();
            initMediaUploader();
            initContestManagement();
        }
        
        // Initialize everything
        init();
        
    });
    
})(jQuery);
