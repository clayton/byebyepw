# Bye Bye Passwords

A WordPress plugin that enables passwordless authentication using WebAuthn/Passkeys, providing a more secure and user-friendly login experience.

## Features

- 🔐 **Passwordless Authentication**: Sign in to WordPress using biometrics, security keys, or platform authenticators
- 🔑 **Multiple Passkeys**: Register and manage multiple passkeys per user
- 🔄 **Recovery Codes**: Generate and use one-time recovery codes as backup authentication
- 🚫 **Password-Optional**: Option to completely disable password login for enhanced security
- 👤 **User-Friendly**: Simple interface integrated into WordPress admin and login pages
- 🛡️ **Secure**: Built on WebAuthn/FIDO2 standards with no external dependencies
- 📱 **Cross-Platform**: Works with Touch ID, Face ID, Windows Hello, and hardware security keys

## Requirements

- WordPress 5.0 or higher
- PHP 8.0 or higher
- SSL/HTTPS enabled (required for WebAuthn)
- Modern browser with WebAuthn support

## Installation

1. Download the plugin zip file or clone this repository
2. Upload to `/wp-content/plugins/bye-bye-passwords` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to "Bye Bye Passwords" in the admin menu to get started

## Getting Started

### For Administrators

1. **Initial Setup**
   - After activation, go to "Bye Bye Passwords" in the WordPress admin menu
   - Register your first passkey by clicking "Register New Passkey"
   - Follow your browser's prompts to create a passkey
   - Generate recovery codes as a backup authentication method

2. **Managing Passkeys**
   - View all registered passkeys in the main plugin page
   - Delete passkeys that are no longer needed
   - Each user can have multiple passkeys (e.g., one for phone, one for laptop)

3. **Recovery Codes**
   - Generate a set of 10 one-time use recovery codes
   - Store these codes safely - they cannot be retrieved once the dialog is closed
   - Use them to regain access if you lose your passkey device

4. **Settings**
   - Navigate to "Bye Bye Passwords > Settings"
   - Enable "Disable password login" to require passkey authentication
   - Configure site name for passkey prompts

### For Users

1. **Logging In with a Passkey**
   - On the WordPress login page, click "Sign in with Passkey"
   - Your browser will prompt you to authenticate (fingerprint, face, PIN, or security key)
   - Upon successful authentication, you'll be logged in automatically

2. **Using Recovery Codes**
   - If you can't use your passkey, click "Use recovery code" on the login page
   - Enter your username and one of your recovery codes
   - Each code can only be used once

## Security Considerations

- **HTTPS Required**: WebAuthn only works over secure connections
- **Backup Access**: Always keep recovery codes in a safe place
- **Multiple Passkeys**: Register passkeys on multiple devices for redundancy
- **No Passwords**: When password login is disabled, ensure you have working passkeys or recovery codes

## Browser Compatibility

The plugin works with modern browsers that support WebAuthn:

- Chrome/Edge 67+
- Firefox 60+
- Safari 14+
- Opera 54+

## Troubleshooting

### Common Issues

**"WebAuthn not supported" error**
- Ensure you're using HTTPS
- Update your browser to the latest version
- Check if your device supports WebAuthn

**"Invalid challenge" error during registration/authentication**
- Clear browser cookies and try again
- Wait a moment and retry from a fresh page load

**Cannot register passkey**
- Ensure JavaScript is enabled
- Check browser console for errors
- Verify HTTPS is properly configured

## Development

This plugin is built using the WordPress Plugin Boilerplate architecture and includes:

- Object-oriented plugin structure
- Separate admin and public functionality
- AJAX handlers for WebAuthn operations
- WordPress coding standards compliance

### File Structure

```
bye-bye-passwords/
├── admin/           # Admin-specific functionality
├── includes/        # Core plugin files
├── lib/            # WebAuthn library
├── public/         # Public-facing functionality
├── languages/      # Translation files
└── bye-bye-passwords.php   # Main plugin file
```

## Local Development & Testing (LAMP Devcontainer)

This repository includes a self-contained LAMP devcontainer (Apache, MariaDB, selectable PHP `8.0`/`8.2`/`8.4`/`8.6`, selectable WordPress versions, trusted local HTTPS, WP-CLI, WordPress Plugin Check, and Playwright) for testing real WebAuthn/Passkey flows locally.

### Prerequisites

- Docker with Compose v2 (e.g., Docker Desktop, Colima, OrbStack, or Docker Engine)

### Day-to-Day Commands

```bash
# Generate local dev TLS certs (if needed) and start LAMP (defaults: PHP 8.6 + WP 7.2-RC3)
.devcontainer/bin/dev up --build

# Check active PHP/WordPress versions, plugins, URLs, and container health
.devcontainer/bin/dev status

# Switch PHP version (cached per version)
.devcontainer/bin/dev switch-php 8.4   # also supports 8.6, 8.2, 8.0

# Switch WordPress core version (preserves DB and plugin mount)
.devcontainer/bin/dev switch-wp 7.2-RC3   # also supports 7.1.5, latest, rc, nightly

# Run WordPress Plugin Check (automatically excludes .devcontainer and .git)
.devcontainer/bin/dev plugin-check

# Run Playwright Chromium WebAuthn E2E tests over strictly verified HTTPS
.devcontainer/bin/dev test-e2e

# Stop containers (keeps DB and WordPress volumes)
.devcontainer/bin/dev down

# Full reset (stops containers and deletes DB and WordPress volumes)
.devcontainer/bin/dev down -v
```

### Trusting the Local Dev Root CA for Browser Passkey Testing

On first startup, `.devcontainer/bin/dev up` generates a local Root CA (`.devcontainer/certs/ca.crt`) and a SAN leaf certificate for `localhost` and `byebyepw.test`. The Playwright container trusts this CA automatically; to test real Passkeys in your host Chromium/Chrome browser at `https://localhost:8443` (or `https://byebyepw.test:8443` with an `/etc/hosts` entry), trust the generated Root CA once:

**macOS (Keychain)** — or run `.devcontainer/bin/dev trust-macos` to print this command:

```bash
sudo security add-trusted-cert -d -r trustRoot \
  -k /Library/Keychains/System.keychain \
  .devcontainer/certs/ca.crt
```

**Linux (Debian/Ubuntu system trust store + Chromium NSS DB)**:

```bash
sudo cp .devcontainer/certs/ca.crt /usr/local/share/ca-certificates/byebyepw-local-ca.crt
sudo update-ca-certificates

# Also add to Chromium's NSS DB (requires libnss3-tools):
certutil -d "sql:$HOME/.pki/nssdb" -A -t "CT,C,C" \
  -n "Bye Bye Passwords Local Dev CA" -i .devcontainer/certs/ca.crt
```

See [`.devcontainer/README.md`](.devcontainer/README.md) for full details, `.env` overrides, `/etc/hosts` setup, and WP-CLI helpers.

> **Security Note:** The default dev accounts (`admin` / `admin123!` and `subscriber` / `subscriber123!`) and database credentials are **local-only container defaults** bound to `127.0.0.1` — never use them in production. All generated TLS keys and certificates (`.devcontainer/certs/`), local `.env` overrides, and Playwright `.devcontainer/test-results/` are generated at runtime, gitignored, and never committed.

## Contributing

Contributions are welcome! Please feel free to submit issues and pull requests.

## License

This plugin is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Credits

- Built with [WebAuthn-r0](https://github.com/lbuchs/WebAuthn) PHP library
- WordPress Plugin Boilerplate for structure
- WebAuthn/FIDO2 standards by W3C and FIDO Alliance

## Support

For issues, questions, or feature requests, please create an issue on GitHub.

---

**Note**: This plugin is designed for enhanced security. Always ensure you have backup authentication methods (recovery codes) before disabling password login.
