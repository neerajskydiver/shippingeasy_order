<?php

/**
 * ShippingEasy Install
 *
 * Plugin install script which adds default pages.
 *
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


/**
 * Create a page
 *
 * @access public
 * @param mixed $slug Slug for the new page
 * @param mixed $option Option name to store the page's ID
 * @param string $page_title (default: '') Title for the new page
 * @param string $page_content (default: '') Content for the new page
 * @param int $post_parent (default: 0) Parent for the new page
 * @return void
 */
function shippingeasy_order_create_page() {
	global $wpdb;
global $user_ID;
$page['post_type']    = 'page';
$page['post_content'] = 'shipment';
$page['post_parent']  = 0;
$page['post_author']  = $user_ID;
$page['post_status']  = 'publish';
$page['post_title']   = 'shipment-callback';
$page = apply_filters('shippingeasy_order_add_new_page', $page, 'teams');
$pageid = wp_insert_post ($page);
if ($pageid == 0) { /* Add Page Failed */ }
change_permalinks();
}
function change_permalinks() {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure('/%postname%/');
    $wp_rewrite->flush_rules();
}
add_action('init', 'change_permalinks', 20);
?>
