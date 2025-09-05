/**
 * WebAuthn JavaScript for passkey operations
 */
(function($) {
    'use strict';

    const ByeByePW = {
        /**
         * Convert base64url to ArrayBuffer
         */
        base64urlToBuffer: function(base64url) {
            if (!base64url) {
                console.error('base64urlToBuffer: input is undefined or empty');
                return new ArrayBuffer(0);
            }
            const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
            const padLen = (4 - (base64.length % 4)) % 4;
            const padded = base64 + '='.repeat(padLen);
            const binary = atob(padded);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return bytes.buffer;
        },

        /**
         * Convert ArrayBuffer to base64url
         */
        bufferToBase64url: function(buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.length; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            const base64 = btoa(binary);
            return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        },

        /**
         * Register a new passkey
         */
        registerPasskey: function() {
            const button = this;
            const $button = $(button);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Initializing...');

            // Get registration challenge from server
            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_get_registration_challenge',
                    nonce: byebyepw_ajax.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        alert('Failed to get registration challenge: ' + response.data);
                        $button.prop('disabled', false).text(originalText);
                        return;
                    }

                    const publicKeyOptions = response.data;
                    
                    // Convert challenge and user.id from base64url if they exist
                    if (publicKeyOptions.challenge) {
                        publicKeyOptions.challenge = ByeByePW.base64urlToBuffer(publicKeyOptions.challenge);
                    }
                    if (publicKeyOptions.user && publicKeyOptions.user.id) {
                        publicKeyOptions.user.id = ByeByePW.base64urlToBuffer(publicKeyOptions.user.id);
                    }
                    
                    // Convert excludeCredentials
                    if (publicKeyOptions.excludeCredentials && Array.isArray(publicKeyOptions.excludeCredentials)) {
                        publicKeyOptions.excludeCredentials = publicKeyOptions.excludeCredentials.map(cred => ({
                            ...cred,
                            id: cred.id ? ByeByePW.base64urlToBuffer(cred.id) : undefined
                        }));
                    }

                    $button.text('Follow browser prompts...');

                    // Create credential
                    navigator.credentials.create({ publicKey: publicKeyOptions })
                        .then(credential => {
                            $button.text('Registering...');

                            // Send credential to server
                            $.ajax({
                                url: byebyepw_ajax.ajax_url,
                                method: 'POST',
                                data: {
                                    action: 'byebyepw_register_passkey',
                                    nonce: byebyepw_ajax.nonce,
                                    credential_id: ByeByePW.bufferToBase64url(credential.rawId),
                                    client_data_json: ByeByePW.bufferToBase64url(credential.response.clientDataJSON),
                                    attestation_object: ByeByePW.bufferToBase64url(credential.response.attestationObject)
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('Passkey registered successfully!');
                                        location.reload();
                                    } else {
                                        alert('Registration failed: ' + response.data);
                                    }
                                    $button.prop('disabled', false).text(originalText);
                                },
                                error: function() {
                                    alert('Failed to register passkey');
                                    $button.prop('disabled', false).text(originalText);
                                }
                            });
                        })
                        .catch(error => {
                            console.error('WebAuthn error:', error);
                            alert('Failed to create passkey: ' + error.message);
                            $button.prop('disabled', false).text(originalText);
                        });
                },
                error: function() {
                    alert('Failed to get registration challenge');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Authenticate with passkey
         */
        authenticatePasskey: function() {
            const button = this;
            const $button = $(button);
            const originalText = $button.text();
            
            $button.prop('disabled', true).text('Initializing...');

            // Get authentication challenge from server
            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_get_authentication_challenge',
                    nonce: byebyepw_ajax.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        alert('Failed to get authentication challenge: ' + response.data);
                        $button.prop('disabled', false).text(originalText);
                        return;
                    }

                    const publicKeyOptions = response.data;
                    
                    // Convert challenge from base64url
                    publicKeyOptions.challenge = ByeByePW.base64urlToBuffer(publicKeyOptions.challenge);
                    
                    // Convert allowCredentials
                    if (publicKeyOptions.allowCredentials) {
                        publicKeyOptions.allowCredentials = publicKeyOptions.allowCredentials.map(cred => ({
                            ...cred,
                            id: ByeByePW.base64urlToBuffer(cred.id)
                        }));
                    }

                    $button.text('Follow browser prompts...');

                    // Get credential
                    navigator.credentials.get({ publicKey: publicKeyOptions })
                        .then(credential => {
                            $button.text('Authenticating...');

                            // Send credential to server
                            $.ajax({
                                url: byebyepw_ajax.ajax_url,
                                method: 'POST',
                                data: {
                                    action: 'byebyepw_authenticate_passkey',
                                    nonce: byebyepw_ajax.nonce,
                                    credential_id: ByeByePW.bufferToBase64url(credential.rawId),
                                    client_data_json: ByeByePW.bufferToBase64url(credential.response.clientDataJSON),
                                    authenticator_data: ByeByePW.bufferToBase64url(credential.response.authenticatorData),
                                    signature: ByeByePW.bufferToBase64url(credential.response.signature),
                                    user_handle: credential.response.userHandle ? ByeByePW.bufferToBase64url(credential.response.userHandle) : null
                                },
                                success: function(response) {
                                    if (response.success) {
                                        // Redirect to admin dashboard or specified URL
                                        window.location.href = response.data.redirect || '/wp-admin/';
                                    } else {
                                        alert('Authentication failed: ' + response.data);
                                        $button.prop('disabled', false).text(originalText);
                                    }
                                },
                                error: function() {
                                    alert('Failed to authenticate');
                                    $button.prop('disabled', false).text(originalText);
                                }
                            });
                        })
                        .catch(error => {
                            console.error('WebAuthn error:', error);
                            alert('Authentication failed: ' + error.message);
                            $button.prop('disabled', false).text(originalText);
                        });
                },
                error: function() {
                    alert('Failed to get authentication challenge');
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },

        /**
         * Delete a passkey
         */
        deletePasskey: function(credentialId) {
            if (!confirm('Are you sure you want to delete this passkey?')) {
                return;
            }

            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_delete_passkey',
                    nonce: byebyepw_ajax.nonce,
                    credential_id: credentialId
                },
                success: function(response) {
                    if (response.success) {
                        alert('Passkey deleted successfully');
                        location.reload();
                    } else {
                        alert('Failed to delete passkey: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to delete passkey');
                }
            });
        },

        /**
         * Generate recovery codes
         */
        generateRecoveryCodes: function() {
            if (!confirm('This will replace any existing recovery codes. Continue?')) {
                return;
            }

            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_generate_recovery_codes',
                    nonce: byebyepw_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Display recovery codes
                        $('#byebyepw-recovery-codes-display').html(response.data.html);
                        $('#byebyepw-recovery-codes-modal').show();
                    } else {
                        alert('Failed to generate recovery codes: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to generate recovery codes');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        // Check for WebAuthn support
        if (!window.PublicKeyCredential) {
            $('.byebyepw-webauthn-not-supported').show();
            $('.byebyepw-webauthn-supported').hide();
            return;
        }

        // Bind events
        $(document).on('click', '#byebyepw-register-passkey', ByeByePW.registerPasskey);
        $(document).on('click', '#byebyepw-authenticate-passkey', ByeByePW.authenticatePasskey);
        $(document).on('click', '.byebyepw-delete-passkey', function() {
            ByeByePW.deletePasskey($(this).data('credential-id'));
        });
        $(document).on('click', '#byebyepw-generate-recovery-codes', ByeByePW.generateRecoveryCodes);
        
        // Close modal
        $(document).on('click', '.byebyepw-modal-close', function() {
            $(this).closest('.byebyepw-modal').hide();
        });
    });

})(jQuery);