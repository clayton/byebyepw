(function( $ ) {
	'use strict';

	$(function() {
		// Modal handling
		const $registerModal = $('#byebyepw-register-modal');
		const $recoveryModal = $('#byebyepw-recovery-modal');
		let $lastFocusedElement = null;
		let recoveryCodesChanged = false;

		const openModal = function($modal) {
			$lastFocusedElement = $(document.activeElement);
			$modal.attr('aria-hidden', 'false').fadeIn(120);
			$('body').addClass('byebyepw-modal-open');
			setTimeout(function() {
				$modal.find('input, button').filter(':visible').first().trigger('focus');
			}, 140);
		};

		const closeModal = function($modal) {
			$modal.stop(true, true).fadeOut(100, function() {
				$modal.attr('aria-hidden', 'true');
				if (recoveryCodesChanged && $modal[0] === $recoveryModal[0]) {
					location.reload();
				}
			});
			$('body').removeClass('byebyepw-modal-open');
			if ($lastFocusedElement && $lastFocusedElement.length) {
				$lastFocusedElement.trigger('focus');
			}
		};

		const showStatus = function($element, message, type) {
			$element.removeClass('is-error is-success is-loading').addClass(type ? 'is-' + type : '').text(message);
		};

		// Open register modal
		$('#byebyepw-register-passkey').on('click', function(e) {
			e.preventDefault();
			showStatus($('#byebyepw-registration-status'), '', '');
			openModal($registerModal);
		});

		// Close modals
		$('.byebyepw-modal-close').on('click', function() {
			closeModal($(this).closest('.byebyepw-modal'));
		});

		// Close modal on outside click
		$('.byebyepw-modal').on('click', function(e) {
			if (e.target === this) {
				closeModal($(this));
			}
		});

		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				const $openModal = $('.byebyepw-modal:visible').first();
				if ($openModal.length) {
					closeModal($openModal);
				}
			}
		});

		// Start passkey registration
		$('#byebyepw-start-registration').on('click', async function(e) {
			e.preventDefault();

			const passkeyName = $('#byebyepw-passkey-name').val().trim();
			if (!passkeyName) {
				showStatus($('#byebyepw-registration-status'), byebyepw_i18n.enter_passkey_name, 'error');
				$('#byebyepw-passkey-name').trigger('focus');
				return;
			}

			const $status = $('#byebyepw-registration-status');
			const $button = $(this);
			$button.prop('disabled', true).attr('aria-busy', 'true');
			showStatus($status, byebyepw_i18n.starting_registration, 'loading');

			try {
				// Get registration challenge from server
				const challengeResponse = await $.ajax({
					url: byebyepw_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'byebyepw_get_registration_challenge',
						nonce: byebyepw_ajax.nonce
					}
				});

				if (!challengeResponse.success) {
					throw new Error(challengeResponse.data || byebyepw_i18n.failed_get_challenge);
				}

				let options = challengeResponse.data;

				// If the data is wrapped in a publicKey object, unwrap it
				if (options.publicKey) {
					options = options.publicKey;
				}

				// Convert base64url to ArrayBuffer - check if it's already a string
				if (options.challenge && typeof options.challenge === 'string') {
					options.challenge = base64urlToArrayBuffer(options.challenge);
				} else {
					throw new Error(byebyepw_i18n.invalid_challenge);
				}

				if (options.user && options.user.id && typeof options.user.id === 'string') {
					options.user.id = base64urlToArrayBuffer(options.user.id);
				}

				if (options.excludeCredentials && Array.isArray(options.excludeCredentials)) {
					options.excludeCredentials = options.excludeCredentials.map(cred => ({
						...cred,
						id: typeof cred.id === 'string' ? base64urlToArrayBuffer(cred.id) : cred.id
					}));
				}

				showStatus($status, byebyepw_i18n.follow_browser_prompts, 'loading');

				// Create credential
				const credential = await navigator.credentials.create({
					publicKey: options
				});

				showStatus($status, byebyepw_i18n.processing_passkey, 'loading');

				// Send to server
				const verifyResponse = await $.ajax({
					url: byebyepw_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'byebyepw_register_passkey',
						nonce: byebyepw_ajax.nonce,
						name: passkeyName,
						credential_id: arrayBufferToBase64url(credential.rawId),
						client_data_json: arrayBufferToBase64url(credential.response.clientDataJSON),
						attestation_object: arrayBufferToBase64url(credential.response.attestationObject)
					}
				});

				if (verifyResponse.success) {
					showStatus($status, byebyepw_i18n.passkey_registered, 'success');
					setTimeout(() => {
						location.reload();
					}, 1500);
				} else {
					throw new Error(verifyResponse.data || byebyepw_i18n.verification_failed);
				}

			} catch (error) {
				console.error('Registration error:', error);
				showStatus($status, byebyepw_i18n.registration_failed + error.message, 'error');
				$button.prop('disabled', false).removeAttr('aria-busy');
			}
		});

		// Generate recovery codes
		$('#byebyepw-generate-recovery-codes').on('click', async function(e) {
			e.preventDefault();

			const $button = $(this);
			if ($button.data('has-codes') === 1 && !confirm(byebyepw_i18n.confirm_invalidate_codes)) {
				return;
			}

			$button.prop('disabled', true).attr('aria-busy', 'true');

			try {
				const response = await $.ajax({
					url: byebyepw_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'byebyepw_generate_recovery_codes',
						nonce: byebyepw_ajax.nonce
					}
				});

				if (response.success && response.data.codes) {
					recoveryCodesChanged = true;
					const $codesList = $('#byebyepw-recovery-codes-list').empty();
					response.data.codes.forEach(function(code, index) {
						$('<div>', { class: 'recovery-code' })
							.append($('<span>', { class: 'screen-reader-text', text: (index + 1) + '. ' }))
							.append(document.createTextNode(code))
							.appendTo($codesList);
					});
					showStatus($('#byebyepw-recovery-action-status'), '', '');
					openModal($recoveryModal);

					// Store codes for copy/download
					window.byebyepwRecoveryCodes = response.data.codes;
				} else {
					showStatus($('#byebyepw-recovery-generation-status'), byebyepw_i18n.failed_generate_codes, 'error');
				}
			} catch (error) {
				console.error('Error generating recovery codes:', error);
				showStatus($('#byebyepw-recovery-generation-status'), byebyepw_i18n.error_generating_codes, 'error');
			} finally {
				$button.prop('disabled', false).removeAttr('aria-busy');
			}
		});

		// Copy recovery codes
		$('#byebyepw-copy-codes').on('click', async function() {
			if (window.byebyepwRecoveryCodes) {
				const codesText = window.byebyepwRecoveryCodes.join('\n');
				try {
					if (navigator.clipboard && window.isSecureContext) {
						await navigator.clipboard.writeText(codesText);
					} else {
						const $textarea = $('<textarea>').val(codesText).appendTo('body').select();
						document.execCommand('copy');
						$textarea.remove();
					}
					const $button = $(this);
					$button.text(byebyepw_i18n.copied_codes);
					showStatus($('#byebyepw-recovery-action-status'), byebyepw_i18n.codes_copied, 'success');
					setTimeout(function() {
						$button.text(byebyepw_i18n.copy_codes);
					}, 1800);
				} catch (error) {
					showStatus($('#byebyepw-recovery-action-status'), byebyepw_i18n.error_generating_codes, 'error');
				}
			}
		});

		// Download recovery codes
		$('#byebyepw-download-codes').on('click', function() {
			if (window.byebyepwRecoveryCodes) {
				const codesText = byebyepw_i18n.download_header + '\n' +
					byebyepw_i18n.download_generated + new Date().toLocaleString() + '\n' +
					byebyepw_i18n.download_keep_safe + '\n\n' +
					window.byebyepwRecoveryCodes.join('\n');

				// Create download link
				const blob = new Blob([codesText], { type: 'text/plain' });
				const url = window.URL.createObjectURL(blob);
				const a = document.createElement('a');
				a.href = url;
				a.download = 'byebyepw-recovery-codes.txt';
				a.click();
				window.URL.revokeObjectURL(url);
			}
		});

		// Delete passkey
		$('.byebyepw-delete-passkey').on('click', async function(e) {
			e.preventDefault();

			if (!confirm(byebyepw_i18n.confirm_delete_passkey)) {
				return;
			}

			const passkeyId = $(this).data('passkey-id');

			try {
				const response = await $.ajax({
					url: byebyepw_ajax.ajax_url,
					type: 'POST',
					data: {
						action: 'byebyepw_delete_passkey',
						nonce: byebyepw_ajax.nonce,
						passkey_id: passkeyId
					}
				});

				if (response.success) {
					location.reload();
				} else {
					showStatus($('#byebyepw-passkey-status'), response.data || byebyepw_i18n.failed_delete_passkey, 'error');
				}
			} catch (error) {
				console.error('Error deleting passkey:', error);
				showStatus($('#byebyepw-passkey-status'), byebyepw_i18n.error_deleting_passkey, 'error');
			}
		});

		// Utility functions
		function base64urlToArrayBuffer(base64url) {
			// Replace URL-safe characters
			const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');

			// Pad with '=' if necessary
			const padded = base64 + '=='.substring(0, (4 - base64.length % 4) % 4);

			// Decode base64
			const binary = atob(padded);

			// Convert to ArrayBuffer
			const buffer = new ArrayBuffer(binary.length);
			const bytes = new Uint8Array(buffer);
			for (let i = 0; i < binary.length; i++) {
				bytes[i] = binary.charCodeAt(i);
			}

			return buffer;
		}

		function arrayBufferToBase64url(buffer) {
			// Convert ArrayBuffer to base64
			const bytes = new Uint8Array(buffer);
			let binary = '';
			for (let i = 0; i < bytes.byteLength; i++) {
				binary += String.fromCharCode(bytes[i]);
			}
			const base64 = btoa(binary);

			// Convert to base64url
			return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
		}
	});

})( jQuery );
