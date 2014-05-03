<?php
/**
 * ShippingEasy Uninstall
 *
 * Uninstalling WooCommerce deletes user roles, options, tables, and pages.
 *
 * @author 		WooThemes
 * @category 	Core
 * @package 	WooCommerce/Uninstaller
 * @version     1.2
 */
  error_reporting(E_ALL);
  ini_set("display_errors", 1);
if( !defined('WP_UNINSTALL_PLUGIN') ) exit();
	global $wpdb;
// Delete options
$wpdb->query("DELETE FROM $wpdb->posts WHERE post_title = 'shipment-callback'");
