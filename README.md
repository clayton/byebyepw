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
- PHP 7.2 or higher
- SSL/HTTPS enabled (required for WebAuthn)
- Modern browser with WebAuthn support

## Installation

1. Download the plugin zip file or clone this repository
2. Upload to `/wp-content/plugins/byebyepw` directory
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

### Debug Tools

The plugin includes built-in debug tools accessible from "Bye Bye Passwords > Debug Tools":

- View database status and tables
- Check session and challenge status
- View all registered passkeys
- Clear passkeys and challenges if needed
- Review debug logs

### Common Issues

**"WebAuthn not supported" error**
- Ensure you're using HTTPS
- Update your browser to the latest version
- Check if your device supports WebAuthn

**"Invalid challenge" error during registration/authentication**
- Clear browser cookies and try again
- Use the debug tools to clear stored challenges

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
byebyepw/
├── admin/           # Admin-specific functionality
├── includes/        # Core plugin files
├── lib/            # WebAuthn library
├── public/         # Public-facing functionality
├── languages/      # Translation files
└── byebyepw.php   # Main plugin file
```

## Contributing

Contributions are welcome! Please feel free to submit issues and pull requests.

## License

This plugin is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

- Built with [WebAuthn-r0](https://github.com/lbuchs/WebAuthn) PHP library
- WordPress Plugin Boilerplate for structure
- WebAuthn/FIDO2 standards by W3C and FIDO Alliance

## Support

For issues, questions, or feature requests, please create an issue on GitHub.

---

**Note**: This plugin is designed for enhanced security. Always ensure you have backup authentication methods (recovery codes) before disabling password login.