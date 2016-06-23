<?php
/*
Plugin Name: WP eCommerce - Xero
Plugin URL: https://plugify.io
Description: Integrate your WP eCommerce store with your Xero account
Version: 1.0
Author: Plugify
Author URI: https://plugify.io
Contributors: Plugify
*/

	// Ensure WordPress has been bootstrapped
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	// Store path for re-use
	$path = trailingslashit( dirname( __FILE__ ) );

	// Define basename
	define( 'WPSC_XERO_BASENAME', plugin_basename( __FILE__ ) );

	// Ensure our class dependencies class has been defined
	$inits = array(
		'Xero_Resource'  => $path . 'lib/xero/class.xero-resource.php',
		'Xero_Contact'   => $path . 'lib/xero/class.xero-contact.php',
		'Xero_Invoice'   => $path . 'lib/xero/class.xero-invoice.php',
		'Xero_Line_Item' => $path . 'lib/xero/class.xero-line-item.php',
		'Xero_Payment'   => $path . 'lib/xero/class.xero-payment.php'
	);

	foreach ( $inits as $class => $file ) {
		require_once $file;
	}

	if ( ! class_exists( 'Plugify_WPSC_Xero' ) ) {
		require_once( $path . 'class.wpsc-xero.php' );
	}

	// Boot Plugify Xero integration for WP eCommerce
	new Plugify_WPSC_Xero();
