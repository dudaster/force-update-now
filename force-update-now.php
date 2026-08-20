<?php
/**
 * Plugin Name: Force Update Now
 * Description: Queries the WordPress.org API directly (plugin_information / theme_information) and installs the published version immediately, bypassing the "Protect the Shire" 24h/6h cooldown applied to update notifications.
 * Version: 0.1.0
 * Requires PHP: 7.4
 * Author: dudaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUN_PLUGIN_FILE', __FILE__ );
define( 'FUN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once FUN_PLUGIN_DIR . 'includes/class-fun-checker.php';
require_once FUN_PLUGIN_DIR . 'includes/class-fun-installer.php';
require_once FUN_PLUGIN_DIR . 'includes/class-fun-safety.php';
require_once FUN_PLUGIN_DIR . 'includes/class-fun-admin.php';
require_once FUN_PLUGIN_DIR . 'includes/class-fun-self-updater.php';

FUN_Admin::init();
FUN_Self_Updater::init();
