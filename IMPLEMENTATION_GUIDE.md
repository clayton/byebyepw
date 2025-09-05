# Bye Bye Passwords - Implementation Guide

## ✅ Completed Components

### Core Functionality
1. **Database Schema** - Tables for passkeys and recovery codes
2. **WebAuthn Integration** - Full WebAuthn library integration  
3. **Recovery Codes System** - Generate and verify one-time codes
4. **AJAX Handlers** - All endpoints for WebAuthn operations
5. **JavaScript** - Complete WebAuthn API implementation
6. **Admin Interface** - Settings and passkey management pages

## 🔧 Required Updates to Complete the Plugin

### 1. Update Main Plugin Class (includes/class-byebyepw.php)

Add these hooks in the `define_admin_hooks()` method:
```php
$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
$this->loader->add_action( 'show_user_profile', $plugin_admin, 'add_user_profile_fields' );
$this->loader->add_action( 'edit_user_profile', $plugin_admin, 'add_user_profile_fields' );

// Initialize AJAX handlers
require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-ajax.php';
$ajax_handler = new Byebyepw_Ajax();
$ajax_handler->register_ajax_handlers();
```

### 2. Create Login Page Modifications (public/class-byebyepw-public.php)

Add these methods to the public class:

```php
/**
 * Add passkey login button to login form
 */
public function add_passkey_login_button() {
    $options = get_option( 'byebyepw_settings' );
    $password_disabled = isset( $options['password_login_disabled'] ) ? $options['password_login_disabled'] : false;
    ?>
    <div id="byebyepw-login-section" style="margin: 20px 0;">
        <p class="byebyepw-divider" style="text-align: center; margin: 20px 0;">
            <span style="background: #fff; padding: 0 10px;"><?php _e( 'Or', 'byebyepw' ); ?></span>
        </p>
        <button type="button" id="byebyepw-authenticate-passkey" class="button button-large" style="width: 100%;">
            <?php _e( 'Sign in with Passkey', 'byebyepw' ); ?>
        </button>
        <?php if ( ! $password_disabled ) : ?>
        <p style="margin-top: 10px;">
            <a href="#" id="byebyepw-use-recovery-code"><?php _e( 'Use recovery code', 'byebyepw' ); ?></a>
        </p>
        <?php endif; ?>
    </div>
    
    <div id="byebyepw-recovery-form" style="display: none;">
        <p>
            <label for="byebyepw-recovery-code"><?php _e( 'Recovery Code', 'byebyepw' ); ?></label>
            <input type="text" name="recovery_code" id="byebyepw-recovery-code" class="input" size="20" />
        </p>
        <button type="button" id="byebyepw-submit-recovery" class="button button-primary button-large" style="width: 100%;">
            <?php _e( 'Sign in with Recovery Code', 'byebyepw' ); ?>
        </button>
    </div>
    <?php
}

/**
 * Enqueue login scripts
 */
public function enqueue_login_scripts() {
    wp_enqueue_script( 
        $this->plugin_name . '-login', 
        plugin_dir_url( __FILE__ ) . 'js/byebyepw-login.js', 
        array( 'jquery' ), 
        $this->version, 
        false 
    );
    
    wp_localize_script( $this->plugin_name . '-login', 'byebyepw_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'redirect_to' => isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : admin_url()
    ));
}

/**
 * Hide password field if disabled
 */
public function maybe_hide_password_field() {
    $options = get_option( 'byebyepw_settings' );
    if ( isset( $options['password_login_disabled'] ) && $options['password_login_disabled'] ) {
        ?>
        <style>
            #loginform #user_pass, 
            #loginform label[for="user_pass"],
            #loginform .user-pass-wrap,
            #loginform .forgetmenot,
            #loginform #wp-submit,
            #nav {
                display: none !important;
            }
        </style>
        <?php
    }
}
```

And register these hooks in includes/class-byebyepw.php:
```php
$this->loader->add_action( 'login_form', $plugin_public, 'add_passkey_login_button' );
$this->loader->add_action( 'login_enqueue_scripts', $plugin_public, 'enqueue_login_scripts' );
$this->loader->add_action( 'login_head', $plugin_public, 'maybe_hide_password_field' );
```

### 3. Create CSS Files

**admin/css/byebyepw-admin.css:**
```css
.byebyepw-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.4);
}

.byebyepw-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 80%;
    max-width: 600px;
}

.byebyepw-modal-close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.byebyepw-modal-close:hover,
.byebyepw-modal-close:focus {
    color: black;
}

.byebyepw-recovery-codes ul {
    list-style: none;
    padding: 0;
}

.byebyepw-recovery-codes li {
    margin: 10px 0;
    padding: 10px;
    background: #f0f0f0;
    font-family: monospace;
    font-size: 14px;
}

@media print {
    body * {
        visibility: hidden;
    }
    .byebyepw-recovery-codes,
    .byebyepw-recovery-codes * {
        visibility: visible;
    }
}
```

**public/js/byebyepw-login.js:**
```javascript
(function($) {
    'use strict';
    
    $(document).ready(function() {
        // Copy WebAuthn helper functions from byebyepw-webauthn.js
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
                    
                    const publicKeyOptions = response.data;
                    publicKeyOptions.challenge = base64urlToBuffer(publicKeyOptions.challenge);
                    
                    if (publicKeyOptions.allowCredentials) {
                        publicKeyOptions.allowCredentials = publicKeyOptions.allowCredentials.map(cred => ({
                            ...cred,
                            id: base64urlToBuffer(cred.id)
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
            $('#loginform > p').not('#byebyepw-recovery-form').hide();
            $('#byebyepw-login-section').hide();
            $('#byebyepw-recovery-form').show();
        });
        
        // Recovery code submission
        $('#byebyepw-submit-recovery').on('click', function() {
            const username = $('#user_login').val();
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
```

### 4. Create PHPUnit Tests

**tests/test-byebyepw-recovery-codes.php:**
```php
<?php
class Test_Byebyepw_Recovery_Codes extends WP_UnitTestCase {
    
    private $recovery_codes;
    private $user_id;
    
    public function setUp() {
        parent::setUp();
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-byebyepw-recovery-codes.php';
        $this->recovery_codes = new Byebyepw_Recovery_Codes();
        $this->user_id = $this->factory->user->create();
    }
    
    public function test_generate_recovery_codes() {
        $codes = $this->recovery_codes->generate_recovery_codes( $this->user_id );
        
        $this->assertCount( 10, $codes );
        foreach ( $codes as $code ) {
            $this->assertRegExp( '/^[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/', $code );
        }
    }
    
    public function test_verify_recovery_code() {
        $codes = $this->recovery_codes->generate_recovery_codes( $this->user_id );
        $first_code = $codes[0];
        
        // Valid code should work
        $this->assertTrue( $this->recovery_codes->verify_recovery_code( $this->user_id, $first_code ) );
        
        // Same code shouldn't work twice
        $this->assertFalse( $this->recovery_codes->verify_recovery_code( $this->user_id, $first_code ) );
        
        // Invalid code shouldn't work
        $this->assertFalse( $this->recovery_codes->verify_recovery_code( $this->user_id, 'INVALID-CODE-HERE' ) );
    }
    
    public function test_remaining_codes_count() {
        $this->recovery_codes->generate_recovery_codes( $this->user_id );
        $this->assertEquals( 10, $this->recovery_codes->get_remaining_codes_count( $this->user_id ) );
        
        $codes = $this->recovery_codes->generate_recovery_codes( $this->user_id );
        $this->recovery_codes->verify_recovery_code( $this->user_id, $codes[0] );
        
        $this->assertEquals( 9, $this->recovery_codes->get_remaining_codes_count( $this->user_id ) );
    }
}
```

## 📝 Installation Instructions

1. Upload plugin files to `/wp-content/plugins/byebyepw/`
2. Activate the plugin through the WordPress admin
3. Navigate to "Bye Bye PW" in the admin menu
4. Register a passkey for your account
5. Generate recovery codes and save them securely
6. Test passkey login before disabling password authentication
7. Optionally disable password login in Settings

## ⚠️ Important Security Notes

- Always have recovery codes before disabling passwords
- Test passkey authentication thoroughly first
- Keep multiple passkeys registered for redundancy
- Recovery codes are one-time use only
- Store recovery codes offline in a secure location

## 🔑 Key Features

- WebAuthn/FIDO2 passkey authentication
- Multiple passkeys per user
- 10 one-time recovery codes
- Optional password login disable
- WordPress admin integration
- Session-based challenge storage
- No external service dependencies