/**
 * Shortcode Arcade Crypto Idle Game - Admin JavaScript
 * @since 0.4.6
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Tab switching with smooth animation
         */
        function handleTabSwitching() {
            $('.sacig-tab-wrapper .nav-tab').on('click', function(e) {
                // Let the link work normally, but add animation
                const $content = $('.sacig-tab-content');
                
                // Fade out
                $content.css('opacity', '0');
                
                // Fade back in after page load (handled by browser)
                setTimeout(function() {
                    $content.css('opacity', '1');
                }, 100);
            });
            
            // Initial fade in
            $('.sacig-tab-content').css({
                'opacity': '0',
                'transition': 'opacity 0.3s ease'
            });
            
            setTimeout(function() {
                $('.sacig-tab-content').css('opacity', '1');
            }, 100);
        }
        
        /**
         * Handle cloud saves checkbox dependency
         */
        function handleCloudSavesDependency() {
            const $cloudSaves = $('input[name="sacig_enable_cloud_saves"]');
            const $leaderboard = $('input[name="sacig_enable_leaderboard"]');
            
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
            const $cloudSaves = $('input[name="sacig_enable_cloud_saves"]');
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
                    const message = sacigAdminStrings.confirmDisableCloudTitle + '\n\n' +
                                  sacigAdminStrings.confirmDisableCloudBody + '\n\n' +
                                  sacigAdminStrings.confirmDisableCloudQuestion;
                    const confirmed = confirm(message);

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
            const $limitInput = $('input[name="sacig_leaderboard_limit"]');
            
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
            const $cloudSavesLabel = $('input[name="sacig_enable_cloud_saves"]').closest('label');
            if ($cloudSavesLabel.length && !$cloudSavesLabel.find('.sacig-help-icon').length) {
                $cloudSavesLabel.append(' <span class="sacig-help-icon dashicons dashicons-info"></span>');
                $cloudSavesLabel.find('.sacig-help-icon').attr('title', sacigAdminStrings.tooltipCloudSaves);
            }

            // Add tooltip to leaderboard checkbox
            const $leaderboardLabel = $('input[name="sacig_enable_leaderboard"]').closest('label');
            if ($leaderboardLabel.length && !$leaderboardLabel.find('.sacig-help-icon').length) {
                $leaderboardLabel.append(' <span class="sacig-help-icon dashicons dashicons-info"></span>');
                $leaderboardLabel.find('.sacig-help-icon').attr('title', sacigAdminStrings.tooltipLeaderboard);
            }

            // Make dashicons visible
            $('.sacig-help-icon').css({
                'cursor': 'help',
                'color': '#787c82',
                'font-size': '16px',
                'vertical-align': 'middle'
            });
        }
        
        /**
         * Copy shortcode to clipboard
         * Uses modern Clipboard API with fallback to execCommand
         */
        function handleShortcodeCopy() {
            $('.sacig-info-box code').on('click', function() {
                const $code = $(this);
                const text = $code.text();

                // Try modern Clipboard API first
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function() {
                        showCopyFeedback($code);
                    }).catch(function() {
                        // Fallback to execCommand
                        copyViaExecCommand(text);
                        showCopyFeedback($code);
                    });
                } else {
                    // Fallback for older browsers
                    copyViaExecCommand(text);
                    showCopyFeedback($code);
                }
            });

            // Add cursor pointer to codes
            $('.sacig-info-box code').css('cursor', 'pointer');
        }

        /**
         * Fallback clipboard copy using execCommand
         */
        function copyViaExecCommand(text) {
            const $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
        }

        /**
         * Show visual feedback for copy action
         */
        function showCopyFeedback($code) {
            // Visual feedback
            const originalBg = $code.css('background-color');
            $code.css('background-color', '#46b450');

            setTimeout(function() {
                $code.css('background-color', originalBg);
            }, 200);

            // Show tooltip with localized text
            const $tooltip = $('<span class="sacig-copied-tooltip"></span>').text(sacigAdminStrings.copiedLabel);
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
        }
        
        /**
         * Animate stats on page load
         */
        function animateStats() {
            $('.sacig-stat-value').each(function() {
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
                    return sacigAdminStrings.unsavedChangesWarning;
                }
            });
            
            // Don't warn on form submit
            $form.on('submit', function() {
                formChanged = false;
            });
        }
        
        /**
         * Animate upgrade CTAs
         */
        function animateUpgradeCTAs() {
            $('.sacig-upgrade-cta, .sacig-upgrade-button').each(function() {
                const $this = $(this);
                
                // Pulse animation on hover
                $this.hover(
                    function() {
                        $(this).css('animation', 'pulse 0.5s ease-in-out');
                    },
                    function() {
                        $(this).css('animation', '');
                    }
                );
            });
        }
        
        /**
         * Initialize the branding color pickers
         */
        function handleColorPickers() {
            const $pickers = $('.sacig-color-picker');

            if (!$pickers.length || typeof $.fn.wpColorPicker !== 'function') {
                return;
            }

            $pickers.wpColorPicker();
        }

        /**
         * Branding custom coin image media uploader
         */
        function handleCoinUploader() {
            const $button = $('#sacig-upload-coin');
            const $remove = $('.sacig-remove-coin');
            const $input = $('#sacig_custom_coin');
            const $preview = $('#sacig-coin-preview');

            if (!$button.length || typeof wp === 'undefined' || !wp.media) {
                return;
            }

            let frame;

            $button.on('click', function(e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                frame = wp.media({
                    title: sacigAdminStrings.selectCoinImage,
                    button: { text: sacigAdminStrings.useThisImage },
                    multiple: false
                });

                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $input.val(attachment.url).trigger('change');
                    $preview.html('<img src="' + attachment.url + '" alt="" style="max-width:96px;height:auto;">');
                    $remove.show();
                });

                frame.open();
            });

            $remove.on('click', function(e) {
                e.preventDefault();
                $input.val('').trigger('change');
                $preview.html('<span class="sacig-coin-placeholder"><span class="dashicons dashicons-format-image"></span> ' + sacigAdminStrings.noCoinImage + '</span>');
                $remove.hide();
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
            animateUpgradeCTAs();
            handleColorPickers();
            handleCoinUploader();
        }
        
        // Initialize
        init();
        
    });
    
})(jQuery);
