<?php
$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );

require_once($parse_uri[0].'wp-load.php');

require_once( $parse_uri[0].'wp-config.php');

// A bug in PHP < 5.2.2 makes $HTTP_RAW_POST_DATA not set by default,
// but we can do it ourself.
if ( !isset( $HTTP_RAW_POST_DATA ) ) {
	$HTTP_RAW_POST_DATA = file_get_contents( 'php://input' );
}

if ( isset($HTTP_RAW_POST_DATA) )
	$HTTP_RAW_POST_DATA = trim($HTTP_RAW_POST_DATA);

$values = file_get_contents('php://input');

global $wpdb;
global $woocommerce;
global $post;	
$output = json_decode($values, true);
//Store the values of shipped order which we are getting from ShippingEasy.
$id = $output['shipment']['orders'][0]['external_order_identifier'];
$shipping_id = $output['shipment']['id']; 
$tracking_number = $output['shipment']['tracking_number'];
$carrier_key = $output['shipment']['carrier_key'];
$carrier_service_key = $output['shipment']['carrier_service_key'];
$external_order_identifier = $output['shipment']['orders'][0]['external_order_identifier'];
  
$rrr = 'External Order Identifier :' .$external_order_identifier . '<br/> Shipping Tracking Number :' .$tracking_number. '<br/> Carrier Key :' .$carrier_key. '<br/> Carrier Service Key :' .$carrier_service_key;
//Store in E-commerce databse
$myrows = $wpdb->get_results( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = $id && comment_agent = 'ShippingEasy'" );
$count = count($myrows);

if($count > 0) {
  $wpdb->query("UPDATE $wpdb->comments SET comment_content = '$rrr' WHERE comment_post_ID = $id ORDER BY comment_ID DESC  LIMIT 1");
  $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
}
else {
  $time = current_time('mysql');
  $data = array(
	'comment_post_ID' => $id,
	'comment_author' => 'ShippingEasy',
	'comment_author_email' => 'order@shippingeasy.com',
	'comment_author_url' => '',
	'comment_content' => $rrr,
	'comment_parent' => 0,
	'user_id' => 1,
	'comment_author_IP' => '',
	'comment_agent' => 'ShippingEasy',
	'comment_type' => 'order_note',
	'comment_date' => $time,
	'comment_approved' => 1,
  );
  $comment_id = wp_insert_comment($data);
  $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
}
?>
