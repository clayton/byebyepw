/**
 * Login page JavaScript for passkey authentication
 */
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // WebAuthn helper functions
        const base64urlToBuffer = function(base64url) {
            const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
            const padLen = (4 - (base64.length % 4)) % 4;
            const padded = base64 + '='.repeat(padLen);
            const binary = atob(padded);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return bytes.buffer;
        };

        const bufferToBase64url = function(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            const base64 = btoa(binary);
            return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        };
        
        // Passkey authentication
        $('#byebyepw-authenticate-passkey').on('click', function() {
            const button = this;
            const $button = $(button);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Initializing...');
            
            // Get username if available
            const username = $('#user_login').val();
            
            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_get_authentication_challenge',
                    username: username
                },
                success: function(response) {
                    if (!response.success) {
                        alert('Failed to get authentication challenge');
                        $button.prop('disabled', false).text(originalText);
                        return;
                    }
                    
                    let publicKeyOptions = response.data;
                    // Handle nested publicKey structure
                    if (publicKeyOptions.publicKey) {
                        publicKeyOptions = publicKeyOptions.publicKey;
                    }
                    
                    // Convert challenge from base64url to ArrayBuffer
                    if (publicKeyOptions.challenge && typeof publicKeyOptions.challenge === 'string') {
                        publicKeyOptions.challenge = base64urlToBuffer(publicKeyOptions.challenge);
                    }
                    
                    // Convert allowCredentials IDs
                    if (publicKeyOptions.allowCredentials && publicKeyOptions.allowCredentials.length > 0) {
                        publicKeyOptions.allowCredentials = publicKeyOptions.allowCredentials.map(cred => ({
                            ...cred,
                            id: typeof cred.id === 'string' ? base64urlToBuffer(cred.id) : cred.id
                        }));
                    }
                    
                    $button.text('Follow browser prompts...');
                    
                    navigator.credentials.get({ publicKey: publicKeyOptions })
                        .then(credential => {
                            $button.text('Authenticating...');
                            
                            $.ajax({
                                url: byebyepw_ajax.ajax_url,
                                method: 'POST',
                                data: {
                                    action: 'byebyepw_authenticate_passkey',
                                    credential_id: bufferToBase64url(credential.rawId),
                                    client_data_json: bufferToBase64url(credential.response.clientDataJSON),
                                    authenticator_data: bufferToBase64url(credential.response.authenticatorData),
                                    signature: bufferToBase64url(credential.response.signature),
                                    user_handle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null,
                                    redirect_to: byebyepw_ajax.redirect_to
                                },
                                success: function(response) {
                                    if (response.success) {
                                        window.location.href = response.data.redirect;
                                    } else {
                                        alert('Authentication failed');
                                        $button.prop('disabled', false).text(originalText);
                                    }
                                },
                                error: function() {
                                    alert('Authentication failed');
                                    $button.prop('disabled', false).text(originalText);
                                }
                            });
                        })
                        .catch(error => {
                            alert('Authentication failed: ' + error.message);
                            $button.prop('disabled', false).text(originalText);
                        });
                },
                error: function() {
                    alert('Failed to get authentication challenge');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Recovery code toggle
        $('#byebyepw-use-recovery-code').on('click', function(e) {
            e.preventDefault();
            $('#byebyepw-login-section').hide();
            $('#byebyepw-recovery-form').show();
        });
        
        // Back to passkey button
        $('#byebyepw-back-to-passkey').on('click', function(e) {
            e.preventDefault();
            $('#byebyepw-recovery-form').hide();
            $('#byebyepw-login-section').show();
        });
        
        // Recovery code submission
        $('#byebyepw-submit-recovery').on('click', function() {
            // Get username from either the main form or recovery form
            let username = $('#user_login').val() || $('#byebyepw-recovery-username').val();
            const recovery_code = $('#byebyepw-recovery-code').val();
            
            if (!username || !recovery_code) {
                alert('Please enter username and recovery code');
                return;
            }
            
            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_authenticate_recovery_code',
                    username: username,
                    recovery_code: recovery_code,
                    redirect_to: byebyepw_ajax.redirect_to
                },
                success: function(response) {
                    if (response.success) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert('Invalid recovery code');
                    }
                },
                error: function() {
                    alert('Authentication failed');
                }
            });
        });
    });
})(jQuery);