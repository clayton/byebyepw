<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://claytonlz.com
 * @since             1.0.0
 * @package           Byebyepw
 *
 * @wordpress-plugin
 * Plugin Name:       Bye Bye Passwords
 * Plugin URI:        https://github.com/clayton/byebyepw
 * Description:       Passwordless login with WebAuthN and Passkeys for WordPress
 * Version:           1.2.1
 * Author:            Clayton
 * Author URI:        https://claytonlz.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       byebyepw
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BYEBYEPW_VERSION', '1.2.1' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-byebyepw-activator.php
 */
function byebyepw_activate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-byebyepw-activator.php';
	Byebyepw_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-byebyepw-deactivator.php
 */
function byebyepw_deactivate() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-byebyepw-deactivator.php';
	Byebyepw_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'byebyepw_activate' );
register_deactivation_hook( __FILE__, 'byebyepw_deactivate' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-byebyepw.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function byebyepw_run() {

	$plugin = new Byebyepw();
	$plugin->run();

}
byebyepw_run();
