<?php
/*
   Plugin Name: Actify
   Plugin URI: http://moldova.org/gamification
   Description: Actify is a Wordpress plugin that increases the interaction of the readers of your website with the content.
   Version: 1.0
   Author: Moldova.org Team
   Author URI: http://moldova.org/actify/about-plugin
   License: GPL2
   */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ACTIFY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACTIFY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACTIFY_VERSION', '1.0' );


require_once( ACTIFY_PLUGIN_DIR . 'classes/class.actify.php' );
require_once( ACTIFY_PLUGIN_DIR . 'classes/class.actify.widget.highlights.php' );

/**
 * Load textdomain
 */
if ( ! function_exists( 'actify_load_textdomain' ) ) {

	function actify_load_textdomain() {

		load_plugin_textdomain( 'actify', false, basename( dirname( __FILE__ ) ) . '/languages' );

	}
}

add_action( 'init', 'actify_load_textdomain' );

if ( ! function_exists( 'init_actify' ) ) {
	function init_actify() {

		new ACTIFY( ACTIFY_PLUGIN_DIR, ACTIFY_PLUGIN_URL, ACTIFY_VERSION );
	}
}

/**
 * Init Actify.
 */
add_action( 'init', 'init_actify' );

if ( ! function_exists( 'register_actify_highlights_widget' ) ) {
	function register_actify_highlights_widget() {
		register_widget( 'Actify_Highlights_Widget' );
	}
}
/**
 * Register Actify Highlights Widget.
 */
add_action( 'widgets_init', 'register_actify_highlights_widget' );
