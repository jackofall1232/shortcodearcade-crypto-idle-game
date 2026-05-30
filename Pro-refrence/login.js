/**
 * Crypto Miner Tycoon - Login Page JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        /**
         * Apply custom branding if enabled
         */
        function applyBranding() {
            if (typeof cmtLogin === 'undefined' || !cmtLogin.branding) {
                return;
            }
            
            const branding = cmtLogin.branding;
            
            if (!branding.enabled) {
                return;
            }
            
            // Apply custom colors
            if (branding.colors) {
                const root = document.querySelector('.cmt-login-container');
                if (root) {
                    root.style.setProperty('--cmt-neon-cyan', branding.colors.primary);
                    root.style.setProperty('--cmt-neon-magenta', branding.colors.secondary);
                    root.style.setProperty('--cmt-neon-yellow', branding.colors.accent);
                }
            }
        }
        
        /**
         * Form loading state
         */
        function setFormLoading($form, loading) {
            const $button = $form.find('.cmt-login-button');
            const $text = $button.find('.cmt-button-text');
            const $loader = $button.find('.cmt-button-loader');
            
            if (loading) {
                $button.prop('disabled', true);
                $text.hide();
                $loader.show();
            } else {
                $button.prop('disabled', false);
                $text.show();
                $loader.hide();
            }
        }
        
        /**
         * Password strength indicator (for registration)
         */
        function checkPasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }
        
        /**
         * Password confirmation validation
         */
        $('#cmt-register-form').on('submit', function(e) {
            const password = $('#cmt_reg_password').val();
            const confirm = $('#cmt_reg_password_confirm').val();
            
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
            
            setFormLoading($(this), true);
        });
        
        /**
         * Login form submission
         */
        $('#cmt-login-form').on('submit', function() {
            setFormLoading($(this), true);
        });
        
        /**
         * Reset form submission
         */
        $('#cmt-reset-form').on('submit', function() {
            setFormLoading($(this), true);
        });
        
        /**
         * Password visibility toggle
         */
        function addPasswordToggle() {
            $('input[type="password"]').each(function() {
                const $input = $(this);
                const $wrapper = $input.parent();
                
                if ($wrapper.find('.cmt-password-toggle').length) {
                    return; // Already added
                }
                
                const $toggle = $('<span class="cmt-password-toggle">👁️</span>');
                $toggle.css({
                    'position': 'absolute',
                    'right': '15px',
                    'top': '50%',
                    'transform': 'translateY(-50%)',
                    'cursor': 'pointer',
                    'user-select': 'none',
                    'opacity': '0.7',
                    'font-size': '1.2rem'
                });
                
                $wrapper.css('position', 'relative');
                $wrapper.append($toggle);
                
                $toggle.on('click', function() {
                    if ($input.attr('type') === 'password') {
                        $input.attr('type', 'text');
                        $toggle.text('🙈');
                    } else {
                        $input.attr('type', 'password');
                        $toggle.text('👁️');
                    }
                });
            });
        }
        
        /**
         * Auto-dismiss messages after 5 seconds
         */
        function autoDismissMessages() {
            setTimeout(function() {
                $('.cmt-login-success, .cmt-login-error').fadeOut();
            }, 5000);
        }
        
        /**
         * Initialize
         */
        function init() {
            applyBranding();
            addPasswordToggle();
            autoDismissMessages();
        }
        
        init();
        
    });
    
})(jQuery);
