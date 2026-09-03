<?php
/**
 * Plugin Name: FormPipe
 * Description: A small contact-form plugin. CPT storage, one scanner, one admin page, one block, REST.
 * Author: FormPipe
 * License: GPL-2.0-or-later
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.0
 *
 * STATUS: Experimental / pre-release. This plugin is a modernized rewrite of
 * Contact Form 7 and has NOT yet undergone rigorous testing or a security
 * audit. Do not deploy on production sites without independent review.
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
