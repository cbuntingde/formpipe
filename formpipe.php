<?php
/**
 * Plugin Name: FormPipe
 * Plugin URI:  https://github.com/cbuntingde/formpipe
 * Description: A small contact-form plugin. CPT storage, one scanner, one admin page, one block, REST.
 * Version:     1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:      FormPipe
 * Author URI:  https://github.com/cbuntingde/formpipe
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: formpipe
 * Domain Path: /languages
 *
 * @package FormPipe
 */
defined( 'ABSPATH' ) || exit;

define( 'FORMPIPE_VERSION', '1.0.0' );
define( 'FORMPIPE_FILE', __FILE__ );
define( 'FORMPIPE_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'FORMPIPE_MODULES_DIR', FORMPIPE_DIR . '/modules' );

require FORMPIPE_DIR . '/includes/helpers.php';
require FORMPIPE_DIR . '/includes/Plugin.php';
add_action( 'plugins_loaded', static function () {
	\FormPipe\Plugin::boot();
} );
