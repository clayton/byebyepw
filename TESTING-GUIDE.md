# Bye Bye Passwords - Testing Guide

## Plugin Activation
1. Navigate to WordPress Admin → Plugins
2. Find "Bye Bye Passwords" and click "Activate"
3. Verify no activation errors occur

## Passkey Registration (Admin Dashboard)

### Initial Setup
1. Navigate to Settings → Bye Bye Passwords in WordPress admin
2. You should see:
   - Your registered passkeys (initially empty)
   - "Register New Passkey" button
   - Recovery codes section

### Register a Passkey
1. Click "Register New Passkey"
2. Enter a name for your passkey (e.g., "MacBook Touch ID")
3. Click "Register"
4. Follow browser prompts to create passkey
5. Verify the passkey appears in your list

### Recovery Codes
1. Click "Generate New Recovery Codes"
2. Click "Yes, Generate" to confirm
3. **IMPORTANT**: Copy or download the codes immediately
4. Store them in a secure location
5. Each code can only be used once

## Login Testing

### Test Passkey Login
1. Log out of WordPress
2. On the login page, you should see "Sign in with passkey" button
3. Click "Sign in with passkey"
4. Follow browser prompts to authenticate
5. You should be logged in successfully

### Test Recovery Code Login
1. Log out of WordPress
2. Click "Sign in with passkey"
3. Click "Use recovery code instead"
4. Enter your username
5. Enter one of your recovery codes
6. Click "Sign in with recovery code"
7. You should be logged in successfully
8. Note: That recovery code is now used and cannot be used again

## Multiple Passkey Management

### Register Additional Passkeys
1. Go to Settings → Bye Bye Passwords
2. Register additional passkeys with different names
3. Verify all passkeys appear in your list
4. Test login with different passkeys

### Delete a Passkey
1. Click "Delete" next to a passkey
2. Confirm deletion
3. Verify the passkey is removed from the list
4. Test that deleted passkey no longer works for login

## Browser Compatibility Testing

Test the plugin in multiple browsers:
- Chrome/Edge (version 67+)
- Safari (version 14+)
- Firefox (version 60+)

## Security Testing

### Test Invalid Credentials
1. Try to authenticate with a passkey from a different site
2. Verify authentication fails with appropriate error

### Test Used Recovery Codes
1. Try to reuse a recovery code that was already used
2. Verify authentication fails

### Test Session Management
1. Register a passkey
2. Clear browser cookies/session
3. Try to complete registration without starting over
4. Verify appropriate error handling

## Database Verification

Check that the following tables were created:
- `wp_byebyepw_passkeys` - stores passkey credentials
- `wp_byebyepw_recovery_codes` - stores recovery codes

## Troubleshooting Common Issues

### "Invalid challenge" error
- Clear browser cache and cookies
- Try using an incognito/private browser window
- Ensure PHP sessions are enabled on your server

### "Class not found" errors
- Verify all files in `/lib/WebAuthn-r0/` are present
- Check file permissions (should be readable)

### Passkey not working
- Ensure you're on HTTPS (passkeys require secure context)
- Check that your domain matches the registered passkey domain
- Verify JavaScript is enabled in your browser

## Testing Checklist

- [ ] Plugin activates without errors
- [ ] Database tables are created
- [ ] Can register a passkey from admin
- [ ] Passkey appears in list after registration
- [ ] Can generate recovery codes
- [ ] Recovery codes modal shows codes properly
- [ ] Can copy/download recovery codes
- [ ] Login page shows passkey button
- [ ] Can login with passkey
- [ ] Can login with recovery code
- [ ] Recovery code is single-use
- [ ] Can register multiple passkeys
- [ ] Can delete passkeys
- [ ] Appropriate error messages for failures
- [ ] Works in multiple browsers
- [ ] Works on mobile devices with biometric support

## Version Information
- Plugin Version: 1.0.0
- Requires WordPress: 3.0.1+
- Requires PHP: 5.6+
- Requires HTTPS for production use
