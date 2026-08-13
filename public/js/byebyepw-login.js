/**
 * Login page JavaScript for passkey authentication
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        const $message = $('#byebyepw-login-message');
        const $passkeyButton = $('#byebyepw-authenticate-passkey');
        const $recoveryButton = $('#byebyepw-submit-recovery');
        const passkeyButtonText = $passkeyButton.text().trim();
        const recoveryButtonText = $recoveryButton.text().trim();

        const showMessage = function(message, type) {
            $message
                .removeClass('error warning success')
                .addClass(type || 'error')
                .prop('hidden', !message)
                .text(message || '');
        };

        const setButtonBusy = function($button, busy, label) {
            $button.prop('disabled', busy);
            if (busy) {
                $button.attr('aria-busy', 'true').find('span:last-child').text(label);
            } else {
                $button.removeAttr('aria-busy');
            }
        };

        // Check WebAuthn support
        const isWebAuthnSupported = window.PublicKeyCredential !== undefined;

        if (!isWebAuthnSupported) {
            $passkeyButton.hide();
            showMessage(byebyepw_i18n.browser_not_supported + ' ' + byebyepw_i18n.browser_not_supported_msg, 'warning');
        }

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
        $passkeyButton.on('click', function() {
            const button = this;
            const $button = $(button);
            showMessage('', '');
            setButtonBusy($button, true, byebyepw_i18n.initializing);

            // Get username if available
            const username = $('#user_login').val();

            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_get_authentication_challenge',
                    username: username,
                    nonce: byebyepw_ajax.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        showMessage(byebyepw_i18n.failed_get_auth_challenge, 'error');
                        setButtonBusy($button, false, '');
                        $button.find('span:last-child').text(passkeyButtonText);
                        return;
                    }

                    // Store security tokens for authentication request
                    const csrfToken = response.data.csrf_token;
                    const nonce = response.data.nonce;

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

                    $button.find('span:last-child').text(byebyepw_i18n.follow_browser_prompts);

                    navigator.credentials.get({ publicKey: publicKeyOptions })
                        .then(credential => {
                            $button.find('span:last-child').text(byebyepw_i18n.authenticating);

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
                                    redirect_to: byebyepw_ajax.redirect_to,
                                    csrf_token: csrfToken,
                                    nonce: nonce
                                },
                                success: function(response) {
                                    if (response.success) {
                                        window.location.href = response.data.redirect;
                                    } else {
                                        showMessage(byebyepw_i18n.auth_failed, 'error');
                                        setButtonBusy($button, false, '');
                                        $button.find('span:last-child').text(passkeyButtonText);
                                    }
                                },
                                error: function() {
                                    showMessage(byebyepw_i18n.auth_failed, 'error');
                                    setButtonBusy($button, false, '');
                                    $button.find('span:last-child').text(passkeyButtonText);
                                }
                            });
                        })
                        .catch(() => {
                            showMessage(byebyepw_i18n.auth_failed_detail, 'warning');
                            setButtonBusy($button, false, '');
                            $button.find('span:last-child').text(passkeyButtonText);
                        });
                },
                error: function() {
                    showMessage(byebyepw_i18n.failed_get_auth_challenge, 'error');
                    setButtonBusy($button, false, '');
                    $button.find('span:last-child').text(passkeyButtonText);
                }
            });
        });

        // Recovery code toggle
        $('#byebyepw-use-recovery-code').on('click', function(e) {
            e.preventDefault();
            showMessage('', '');
            $('body').addClass('byebyepw-recovery-active');
            $('#byebyepw-login-section').hide();
            $('#byebyepw-recovery-form').show();
            const $firstField = $('#user_login:visible, #byebyepw-recovery-username:visible, #byebyepw-recovery-code:visible').first();
            $firstField.trigger('focus');
        });

        // Back to passkey button
        $('#byebyepw-back-to-passkey').on('click', function(e) {
            e.preventDefault();
            showMessage('', '');
            $('body').removeClass('byebyepw-recovery-active');
            $('#byebyepw-recovery-form').hide();
            $('#byebyepw-login-section').show();
            $passkeyButton.trigger('focus');
        });

        // Recovery code submission
        $recoveryButton.on('click', function() {
            const $button = $(this);
            // Get username from either the main form or recovery form
            let username = $('#user_login').val() || $('#byebyepw-recovery-username').val();
            const recovery_code = $('#byebyepw-recovery-code').val();

            if (!username) {
                showMessage(byebyepw_i18n.enter_username, 'error');
                $('#user_login:visible, #byebyepw-recovery-username:visible').first().trigger('focus');
                return;
            }

            if (!recovery_code) {
                showMessage(byebyepw_i18n.enter_recovery_code, 'error');
                $('#byebyepw-recovery-code').trigger('focus');
                return;
            }

            showMessage('', '');
            $button.prop('disabled', true).attr('aria-busy', 'true').text(byebyepw_i18n.recovery_authenticating);

            // First get a CSRF token by requesting a challenge (but ignore the challenge)
            $.ajax({
                url: byebyepw_ajax.ajax_url,
                method: 'POST',
                data: {
                    action: 'byebyepw_get_authentication_challenge',
                    username: username,
                    nonce: byebyepw_ajax.nonce
                },
                success: function(challengeResponse) {
                    if (!challengeResponse.success) {
                        showMessage(byebyepw_i18n.recovery_failed, 'error');
                        $button.prop('disabled', false).removeAttr('aria-busy').text(recoveryButtonText);
                        return;
                    }

                    // Use security tokens from challenge response
                    const csrfToken = challengeResponse.data.csrf_token;
                    const nonce = challengeResponse.data.nonce;

                    // Now attempt recovery code authentication
                    $.ajax({
                        url: byebyepw_ajax.ajax_url,
                        method: 'POST',
                        data: {
                            action: 'byebyepw_authenticate_recovery_code',
                            username: username,
                            recovery_code: recovery_code,
                            redirect_to: byebyepw_ajax.redirect_to,
                            csrf_token: csrfToken,
                            nonce: nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                window.location.href = response.data.redirect;
                            } else {
                                showMessage(byebyepw_i18n.recovery_failed, 'error');
                                $button.prop('disabled', false).removeAttr('aria-busy').text(recoveryButtonText);
                            }
                        },
                        error: function() {
                            showMessage(byebyepw_i18n.recovery_failed, 'error');
                            $button.prop('disabled', false).removeAttr('aria-busy').text(recoveryButtonText);
                        }
                    });
                },
                error: function() {
                    showMessage(byebyepw_i18n.recovery_failed, 'error');
                    $button.prop('disabled', false).removeAttr('aria-busy').text(recoveryButtonText);
                }
            });
        });

        $('#byebyepw-recovery-code').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#byebyepw-submit-recovery').trigger('click');
            }
        });
    });
})(jQuery);
