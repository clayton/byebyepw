# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Bye Bye Passwords (byebyepw) is a WordPress plugin that implements passwordless authentication using WebAuthn and Passkeys. The plugin follows WordPress coding standards and uses the WordPress Plugin Boilerplate architecture.

## Architecture

The plugin follows the WordPress Plugin Boilerplate pattern with clear separation of concerns:

- **`byebyepw.php`**: Main plugin bootstrap file that initializes the plugin
- **`includes/`**: Core plugin functionality
  - `class-byebyepw.php`: Main plugin class that coordinates all components
  - `class-byebyepw-loader.php`: Manages all hooks and filters
  - `class-byebyepw-activator.php`: Handles plugin activation
  - `class-byebyepw-deactivator.php`: Handles plugin deactivation
  - `class-byebyepw-i18n.php`: Internationalization support
- **`admin/`**: WordPress admin area functionality
  - `class-byebyepw-admin.php`: Admin-specific hooks and functionality
  - `css/`, `js/`, `partials/`: Admin assets and templates
- **`public/`**: Public-facing functionality
  - `class-byebyepw-public.php`: Public-facing hooks and functionality
  - `css/`, `js/`, `partials/`: Public assets and templates
- **`languages/`**: Translation files

## Development Commands

### WordPress Development

Since this is a WordPress plugin, development typically requires:

1. **Local WordPress Setup**: Place this plugin in `wp-content/plugins/` directory of a WordPress installation
2. **Activation**: Activate through WordPress admin panel at Plugins page
3. **Testing**: Use WordPress's built-in plugin testing capabilities

### PHP Linting

To check PHP syntax:
```bash
php -l byebyepw.php
php -l includes/*.php
php -l admin/*.php
php -l public/*.php
```

### WordPress Coding Standards

If WordPress Coding Standards (WPCS) is installed:
```bash
phpcs --standard=WordPress byebyepw.php includes/ admin/ public/
```

## Key Implementation Areas

When implementing WebAuthn/Passkey functionality, focus on:

1. **Authentication Flow**: Modify WordPress login hooks in `public/class-byebyepw-public.php`
2. **User Registration**: Add passkey registration in admin user profile
3. **Database Schema**: Use `class-byebyepw-activator.php` for any database table creation
4. **AJAX Endpoints**: Register AJAX actions through the loader class
5. **JavaScript Integration**: Place WebAuthn API calls in appropriate `js/` directories

## WordPress Hooks Pattern

All hooks are registered through the loader pattern:
- Actions/filters are defined in respective class methods
- Registration happens via `$this->loader->add_action()` or `add_filter()`
- The loader executes all hooks when `run()` is called

## Plugin Text Domain

The plugin uses `byebyepw` as its text domain for internationalization. All translatable strings should use:
```php
__('Text', 'byebyepw')
_e('Text', 'byebyepw')
```
- remember to always make a zip file named byebyepw.zip so it works for wordpress upload naming conventions