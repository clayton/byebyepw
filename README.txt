=== Bye Bye Passwords ===
Contributors: claytonlz
Donate link: https://claytonlz.com/
Tags: passwordless, webauthn, passkeys, authentication, security
Requires at least: 5.0
Tested up to: 6.8
Stable tag: 1.2.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enable passwordless authentication for WordPress using WebAuthn/Passkeys. More secure, more convenient.

== Description ==

**Bye Bye Passwords** brings modern passwordless authentication to WordPress using WebAuthn/Passkeys technology. Say goodbye to weak passwords and hello to secure, convenient login with biometrics, security keys, or platform authenticators.

= Key Features =

* **Passwordless Login** - Sign in using Touch ID, Face ID, Windows Hello, or security keys
* **Multiple Passkeys** - Register multiple devices for convenient access anywhere
* **Recovery Codes** - Generate one-time backup codes for emergency access
* **Enhanced Security** - Eliminate password-based attacks completely
* **User-Friendly** - Simple setup with no technical knowledge required
* **Privacy-Focused** - No external services or tracking
* **WordPress Integration** - Seamlessly integrated into WordPress admin and login

= How It Works =

1. Register a passkey from your WordPress admin profile
2. Use your device's built-in authentication (fingerprint, face, PIN)
3. Sign in instantly without typing passwords

= Requirements =

* SSL/HTTPS enabled website (required for WebAuthn)
* Modern browser with WebAuthn support
* PHP 7.2 or higher
* WordPress 5.0 or higher

== Installation ==

1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to "Bye Bye Passwords" in the admin menu
4. Register your first passkey
5. Generate recovery codes as backup

== Frequently Asked Questions ==

= What browsers support WebAuthn/Passkeys? =

Chrome/Edge 67+, Firefox 60+, Safari 14+, and Opera 54+ all support WebAuthn.

= What happens if I lose my device? =

Use your recovery codes to regain access, then register a new passkey. We recommend registering multiple devices.

= Is this more secure than passwords? =

Yes! Passkeys are phishing-resistant, can't be stolen in data breaches, and use cryptographic authentication.

= Do I need special hardware? =

No, most modern devices have built-in authenticators (Touch ID, Face ID, Windows Hello). You can also use USB security keys.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.1.2 =
* Security: Fix username enumeration vulnerability by standardizing authentication error messages
* Security: Implement constant-time comparison for recovery code verification to prevent timing attacks  
* Security: Add comprehensive CSRF protection for all public authentication endpoints
* Enhancement: Strengthen session security with secure CSRF token management
* Enhancement: Improve error message consistency across all authentication flows

= 1.2.0 =
* Compliance: Complete WordPress.org plugin directory compliance overhaul
* Security: Enhanced nonce verification for all AJAX endpoints to meet WordPress.org standards
* Security: Fixed output escaping throughout WebAuthn library with WordPress-specific modifications
* Security: Improved input sanitization and validation across all user-facing forms
* Security: Removed discouraged PHP functions (unlink, curl) in favor of WordPress equivalents
* Enhancement: Updated text domain to match WordPress.org requirements (bye-bye-passwords)
* Enhancement: Cleaned up plugin structure removing development files from distribution
* Documentation: Added comprehensive phpcs ignore comments for legitimate security exceptions
* Library: Forked and customized WebAuthn library for WordPress.org compliance requirements

= 1.1.1 =
* Fix: Resolve authentication failure with platform authenticators (Touch ID, Face ID, Windows Hello)
* Fix: Improve sign count validation to be more lenient with authenticators that don't increment counters
* Security: Maintain protection against cloned authenticators while allowing normal platform authenticator operation
* Improved: Enhanced logging for sign count validation debugging

= 1.1.0 =
* Security: Critical security updates - Fix session hijacking and race conditions
* Security: Strengthen challenge management to prevent authentication bypass  
* Security: Re-enable sign count validation to detect cloned authenticators
* Security: Add rate limiting to authentication endpoints (10 challenges/5min, 5 auth attempts/5min, 3 recovery codes/10min)
* Enhancement: Implement secure session handling with proper timeout and regeneration
* Enhancement: Replace predictable transient keys with secure UUIDs
* Enhancement: Add comprehensive challenge validation and immediate invalidation
* Update: Domain references changed from labountylabs.com to claytonlz.com

= 1.0.0 =
* Initial release
* Core WebAuthn/Passkeys authentication functionality
* Multiple passkey registration per user
* Recovery codes system with one-time use codes
* Admin interface for managing passkeys and recovery codes
* Login page integration with passkey authentication
* Option to disable password login for enhanced security
* Debug tools for troubleshooting
* WordPress coding standards compliance
* GPL v2 licensing for WordPress.org compatibility

== Upgrade Notice ==

= 1.2.0 =
WordPress.org compliance update: Enhanced security, improved nonce verification, and plugin directory requirements. Recommended for all users.

= 1.1.2 =
Security update: Fixes username enumeration, timing attacks, and adds CSRF protection. Recommended upgrade.

= 1.1.1 =
Recommended update: Fixes authentication issues with platform authenticators while maintaining security.

= 1.1.0 =
CRITICAL SECURITY UPDATE: Fixes multiple high-risk vulnerabilities. Immediate upgrade recommended.

= 1.0.0 =
Initial release of Bye Bye Passwords - Enable passwordless authentication for WordPress using WebAuthn/Passkeys technology.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`