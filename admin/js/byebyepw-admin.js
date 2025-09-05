(function( $ ) {
	'use strict';

	$(function() {
		// Modal handling
		const $registerModal = $('#byebyepw-register-modal');
		const $recoveryModal = $('#byebyepw-recovery-modal');
		
		// Open register modal
		$('#byebyepw-register-passkey').on('click', function(e) {
			e.preventDefault();
			$registerModal.show();
		});
		
		// Close modals
		$('.byebyepw-modal-close').on('click', function() {
			$(this).closest('.byebyepw-modal').hide();
		});
		
		// Close modal on outside click
		$('.byebyepw-modal').on('click', function(e) {
			if (e.target === this) {
				$(this).hide();
			}
		});
		
		// Start passkey registration
		$('#byebyepw-start-registration').on('click', async function(e) {
			e.preventDefault();
			
			const passkeyName = $('#byebyepw-passkey-name').val().trim();
			if (!passkeyName) {
				alert('Please enter a name for your passkey');
				return;
			}
			
			const $status = $('#byebyepw-registration-status');
			$status.html('<p>Starting registration...</p>');
			
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
					throw new Error(challengeResponse.data || 'Failed to get challenge');
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
					throw new Error('Invalid challenge received from server');
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
				
				$status.html('<p>Please follow your browser prompts to create a passkey...</p>');
				
				// Create credential
				const credential = await navigator.credentials.create({
					publicKey: options
				});
				
				$status.html('<p>Processing passkey...</p>');
				
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
					$status.html('<p class="success">Passkey registered successfully!</p>');
					setTimeout(() => {
						location.reload();
					}, 1500);
				} else {
					throw new Error(verifyResponse.data || 'Verification failed');
				}
				
			} catch (error) {
				console.error('Registration error:', error);
				$status.html('<p class="error">Registration failed: ' + error.message + '</p>');
			}
		});
		
		// Generate recovery codes
		$('#byebyepw-generate-recovery-codes').on('click', async function(e) {
			e.preventDefault();
			
			if (!confirm('This will invalidate all existing recovery codes. Continue?')) {
				return;
			}
			
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
					// Display codes in modal
					const codesHtml = response.data.codes.map(code => 
						'<div class="recovery-code">' + code + '</div>'
					).join('');
					
					$('#byebyepw-recovery-codes-list').html(codesHtml);
					$recoveryModal.show();
					
					// Store codes for copy/download
					window.byebyepwRecoveryCodes = response.data.codes;
				} else {
					alert('Failed to generate recovery codes');
				}
			} catch (error) {
				console.error('Error generating recovery codes:', error);
				alert('Error generating recovery codes');
			}
		});
		
		// Copy recovery codes
		$('#byebyepw-copy-codes').on('click', function() {
			if (window.byebyepwRecoveryCodes) {
				const codesText = window.byebyepwRecoveryCodes.join('\n');
				
				// Create textarea, copy, and remove
				const $textarea = $('<textarea>').val(codesText).appendTo('body').select();
				document.execCommand('copy');
				$textarea.remove();
				
				alert('Recovery codes copied to clipboard!');
			}
		});
		
		// Download recovery codes
		$('#byebyepw-download-codes').on('click', function() {
			if (window.byebyepwRecoveryCodes) {
				const codesText = 'Bye Bye Passwords - Recovery Codes\n' +
					'Generated: ' + new Date().toLocaleString() + '\n' +
					'Keep these codes safe!\n\n' +
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
			
			if (!confirm('Are you sure you want to delete this passkey?')) {
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
					alert('Failed to delete passkey');
				}
			} catch (error) {
				console.error('Error deleting passkey:', error);
				alert('Error deleting passkey');
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