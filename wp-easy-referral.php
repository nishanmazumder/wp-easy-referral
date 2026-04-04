<?php
/**
 * WP Easy Referral
 *
 * @package           wp-easy-referral
 * @author            Nishan
 * @copyright         2026 Nishan
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP Easy Referral
 * Plugin URI:        https://mmw.diviaccessories.com/
 * Description:       This database add-on for WPForms ensures all your entries are securely stored and easily accessible
 * Version:           1.1.2
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Nishan
 * Author URI:        https://github.com/nishanmazumder
 * Text Domain:       wp-easy-referral
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPREF_VERSION', '1.1.2' );
define( 'WPREF_FILE', __FILE__ );
define( 'WPREF_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPREF_URL', plugin_dir_url( __FILE__ ) );
define( 'WPREF_BASENAME', plugin_basename( __FILE__ ) );

require_once WPREF_PATH . 'includes/class-wpref-plugin.php';

function wpref_run_plugin() {
	return WPref_Plugin::get_instance();
}

wpref_run_plugin();
