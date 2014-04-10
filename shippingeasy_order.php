<?php 
/* 
Plugin Name: ShippingEasy 
Description: Plugin for calculate the weight and place order.
Author: ShippingEasy
Version: 1.0 
*/


function shippingeasy_order_install() {
  include_once( 'shippingeasy_order_install.php' );
	shippingeasy_order_create_page();
}
register_activation_hook(__FILE__,'shippingeasy_order_install');

function shippingeasy_order_uninstall() {
  global $wpdb;
  // Delete post
  $wpdb->query("DELETE FROM $wpdb->posts WHERE post_title = 'shipment-callback'");
  delete_option('apikey');
  delete_option('secretkey');
  delete_option('baseurl');
  delete_option('storeapi');
}
register_deactivation_hook(__FILE__,'shippingeasy_order_uninstall');

/*----------Start API Endpoint---------*/
if(isset($_GET)) {

  //Get the Requested url.
  if($_SERVER['REQUEST_URI'] == '/shipment-callback/') {
    $values = file_get_contents('php://input');
    $output = json_decode($values, true);

    //Store the values of shipped order which we are getting from ShippingEasy.
    $id = $output['shipment']['orders']['external_order_identifier'];
    //$output['shipment']['orders']['id'];
    $shipping_id = $output['shipment']['id'];
    $tracking_number = $output['shipment']['tracking_number'];
    $carrier_key = $output['shipment']['carrier_key'];
    $carrier_service_key = $output['shipment']['carrier_service_key'];
    $external_order_identifier = $output['shipment']['orders']['external_order_identifier'];

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
      //add_comment_meta( $comment_id, 'is_customer_note', 1 );

      $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
    }
    $data = 'Success';
    return $data;
  }
}

/*-----------------End API EDndpoint--------------------*/

/*-----------------Start Creating API Setting Form------*/

//Wordpress Hook to create admin menu.
add_action('admin_menu', 'baw_create_menu');

//Function to create menu.
function baw_create_menu() {

  //create new top-level menu
  add_menu_page('ShippingEasy Plugin Settings', 'SE Settings', 'administrator', __FILE__, 'baw_settings_page',plugins_url('/images/generic.png', __FILE__));

  //call register settings function
  add_action( 'admin_init', 'register_mysettings' );
}

function register_mysettings() {

 if( isset($_GET['settings-updated']) ) { ?>
    <div id="message" class="updated">
        <p><strong><?php _e('Settings saved.') ?></strong></p>
    </div>
 <?php } 
	//register our settings
	register_setting( 'baw-settings-group', 'apikey' );
	register_setting( 'baw-settings-group', 'secretkey' );
	register_setting( 'baw-settings-group', 'baseurl' );
	register_setting( 'baw-settings-group', 'storeapi' );
}

//ShippingEast API setting page
function baw_settings_page() {
?>
<div class="wrap">
<h2>ShippingEasy Setting</h2>
<form method="post" action="options.php">
    <?php settings_fields( 'baw-settings-group' ); ?>
    <?php do_settings_sections( 'baw-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Customer API Key</th>
        <td><input type="text" name="apikey" value="<?php echo get_option('apikey'); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Customer Secret Key</th>
        <td><input type="text" name="secretkey" value="<?php echo get_option('secretkey'); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Base URL</th>
        <td><input type="text" name="baseurl" value="<?php echo get_option('baseurl'); ?>" /></td>
        </tr>

        <tr valign="top">
        <th scope="row">Store API Key</th>
        <td><input type="text" name="storeapi" value="<?php echo get_option('storeapi'); ?>" /></td>
        </tr>
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php }

/*-----------------End Creating API Setting Form------*/

/*-----------------Start Creating Order------*/

/**
 * WooCommerce Extra Feature
 * --------------------------
 *
 * Add custom fee to cart automatically
 *
 */



function woo_email_order_coupons( $order_id ) {
  //Initialize global wordpress global variable.
  global $wpdb;
  global $woocommerce;
  global $post;

  //Include shippingeasy file.
  include ('shipping_easy-php/lib/ShippingEasy.php');
  //Fetch Customer API Key and Secret Key.
  //Customer API Key.
  $apikey = get_option('apikey');
  //Customer Secret Key.
  $secretkey = get_option('secretkey');
  //Base URL.
  $baseurl = get_option('baseurl');
  //Store API.
  $storeapi = get_option('storeapi');
  ShippingEasy::setApiBase($baseurl);
  ShippingEasy::setApiKey($apikey);
  ShippingEasy::setApiSecret($secretkey);

  $order = new WC_Order($order_id);
  $temp = array();
  //echo "<pre>"; print_r($order); echo "</pre>";

  $billing_company =  $order->billing_company;
  $billing_first_name =  $order->billing_first_name;
  $billing_last_name =  $order->billing_last_name;
  $billing_address =  $order->billing_address_1;
  $billing_address2 =  $order->billing_address_2;
  $billing_city =  $order->billing_city;
  $billing_state =  $order->billing_state;
  $billing_postcode =  $order->billing_postcode;
  $billing_country =  $order->billing_country;
  $billing_email =  $order->billing_email;
  $billing_phone =  $order->billing_phone;
  $shipping_company =  $order->shipping_company;
  $shipping_first_name =  $order->shipping_first_name;
  $shipping_last_name =  $order->shipping_last_name;
  $shipping_address =  $order->shipping_address_1;
  $shipping_address2 =  $order->shipping_address_2;
  $shipping_city =  $order->shipping_city;
  $shipping_state =  $order->shipping_state;
  $shipping_postcode =  $order->shipping_postcode;
  $shipping_country =  $order->shipping_country;
  $shipping_method =  $order->shipping_method;
  $order_total =  $order->order_total;
  $order_tax =  $order->order_tax;
  $order_shipping =  $order->order_shipping;
  $order_shipping_tax =  $order->order_shipping_tax;
  $cart_discount =  $order->cart_discount;

  foreach($order->get_items() as $item){
	 // $post 			= get_post( $item['product_id'] );
	  $product_id 		= $item['product_id'];
	  $post_meta 		= get_post_meta( $item['product_id'] );
	  $regular_price	= get_post_meta( $item['product_id'] ,'_regular_price');
	  $sku 				= get_post_meta( $item['product_id'] ,'_sku');
	  $item_name 		= $item['name'];
	  $item_qty 		= $item['qty'];
	  $line_subtotal    = $item['line_subtotal'];
	  $unit_price 		= $line_subtotal/$item_qty;
	  $line_subtotal    = $item['line_subtotal'];
	  $weight_to_oz		= woocommerce_get_weight( $post_meta['_weight'][0], 'oz' );

    $temp[] = array(
		  "item_name" => "$item_name",
		  "sku" => "$sku[0]",
		  "bin_picking_number" => "7",
		  "unit_price" => "$unit_price",
		  "total_excluding_tax" => "$line_subtotal",
		  "weight_in_ounces" => "$weight_to_oz",
		  "quantity" => "$item_qty",
		);
  } 

  //Calculate the time.
  $time = time();
  $date = date('Y-m-d H:i:s',$time);

  $total_excluding_tax = $line_subtotal;
  $shipping_cost_including_tax = ($order_shipping + $order_shipping_tax);
 
  $order_comment = $wpdb->get_results("SELECT post_excerpt FROM {$wpdb->prefix}posts WHERE ID ='$order_id'");
  foreach ($order_comment as $order_comments) {
   $post_excerpt = $order_comments->post_excerpt;
  }

  //Creating order array.
  $values = array(
    "external_order_identifier" => "$order_id",
    "ordered_at" => "$date",
    "order_status" => "awaiting_shipment",
    "subtotal_including_tax" => "$order_total",
    "total_including_tax" => "$order_total",
    "total_excluding_tax" => "$total_excluding_tax",
    "discount_amount" => "$cart_discount",
    "coupon_discount" => "$cart_discount",
    "subtotal_including_tax" => "$order_total",
    "subtotal_excluding_tax" => "$total_excluding_tax",
    "subtotal_excluding_tax" => "$total_excluding_tax",
    "subtotal_tax" => "$order_tax",
    "total_tax" => "$order_tax",
    "base_shipping_cost" => "$order_shipping",
    "shipping_cost_including_tax" => "$shipping_cost_including_tax",
    "shipping_cost_excluding_tax" => "$order_shipping",
    "shipping_cost_tax" => "$order_shipping_tax",
    "base_handling_cost" => "0.00",
    "handling_cost_excluding_tax" => "0.00",
    "handling_cost_including_tax" => "0.00",
    "handling_cost_tax" => "0.00",
    "base_wrapping_cost" => "0.00",
    "wrapping_cost_excluding_tax" => "0.00",
    "wrapping_cost_including_tax" => "0.00",
    "wrapping_cost_tax" => "0.00",
    "notes" => "$post_excerpt",
    "billing_company" => "$billing_company",
    "billing_first_name" => "$billing_first_name",
    "billing_last_name" => "$billing_last_name",
    "billing_address" => "$billing_address",
    "billing_address2" => "$billing_address2",
    "billing_city" => "$billing_city",
    "billing_state" => "$billing_state",
    "billing_postal_code" => "$billing_postcode",
    "billing_country" => "$billing_country",
    "billing_phone_number" => "$billing_phone",
    "billing_email" => "$billing_email",
    "recipients" => array(
      array (
        "first_name" => "$shipping_first_name",
        "last_name" => "$shipping_last_name",
        "company" => "$shipping_company",
        "email" => "charles.crona@okeefe.org",
        "phone_number" => "637-481-6505",
        "residential" => "true",
        "address" => "$shipping_address",
        "address2" => "$shipping_address2",
        "province" => "",
        "state" => "$shipping_state",
        "city" => "$shipping_city",
        "postal_code" => "$shipping_postcode",
        "postal_code_plus_4" => "1234",
        "country" => "$shipping_country",
        "shipping_method" => "$shipping_method",
        "base_cost" => "10.00",
        "cost_excluding_tax" => "10.00",
        "cost_tax" => "0.00",
        "base_handling_cost" => "0.00",
        "handling_cost_excluding_tax" => "0.00",
        "handling_cost_including_tax" => "0.00",
        "handling_cost_tax" => "0.00",
        "shipping_zone_id" => "123",
        "shipping_zone_name" => "XYZ",
        "items_total" => "$item_qty",
        "items_shipped" => "0",
        "line_items" => shipping_order_detail( $order_id)
      )
    )
  ); 
  //Call ShippingEasy API to place order.
  try {
    $order=new ShippingEasy_Order($storeapi,$values);
    $order->create();
  } catch (Exception $e) {
    echo '<b> Error: ',  $e->getMessage(), "\n </b>";
  }
}

function shipping_order_detail( $order_id ){
  $temp = array();
  $order = new WC_Order($order_id);
  foreach($order->get_items() as $item){
	  //$post 			= get_post( $item['product_id'] );
	  $product_id 		= $item['product_id'];
	  $post_meta 		= get_post_meta( $item['product_id'] );
	  $regular_price	= get_post_meta( $item['product_id'] ,'_regular_price');
	  $sku 				= get_post_meta( $item['product_id'] ,'_sku');
	  $item_name 		= $item['name'];
	  $item_qty 		= $item['qty'];
	  $line_subtotal    = $item['line_subtotal'];
	  $unit_price 		= $line_subtotal/$item_qty;
	  $line_subtotal    = $item['line_subtotal'];
	  $weight_to_oz		= woocommerce_get_weight( $post_meta['_weight'][0], 'oz' );
	  $temp[] = array(
		  "item_name" => "$item_name",
		  "sku" => "$sku[0]",
		  "bin_picking_number" => "0",
		  "unit_price" => "$unit_price",
		  "total_excluding_tax" => "$line_subtotal",
		  "weight_in_ounces" => "$weight_to_oz",
		  "quantity" => "$item_qty",
		);
	  
  }
  
  return $temp;
}

add_action( 'woocommerce_thankyou', 'woo_email_order_coupons' );

/*-----------------End Creating Order----------*/

/*-----------------Start Call Shipped API------*/


//Cancellation API.
add_filter('redirect_post_location', 'page_to_post_on_publish_or_save');

function page_to_post_on_publish_or_save($location) {
  global $post;
  $order_id = get_post();
  $orderid = $order_id->ID;
  // Publishing draft or updating published post
  if ((isset($_POST['publish']) || isset($_POST['save'])) && preg_match("/post=([0-9]*)/", $location, $match) && $post && $post->ID == $match[1] &&
        (isset($_POST['publish']) || $post->post_status == 'publish') && $pl = get_permalink($post->ID)) {
     //Check whether order status is cancelled or not? 
     if($_POST['order_status'] == 'cancelled') {
       //Include shippingeasy file.
       include ('shipping_easy-php/lib/ShippingEasy.php');
       //Fetch API Key , Secret Key, Base URl from E-commerce databse.
       //Customer API Key.
       $apikey = get_option('apikey');
       //Customer Secret Key.
       $secretkey = get_option('secretkey');
       //Base URL.
       $baseurl = get_option('baseurl');
       //Store API.
       $storeapi = get_option('storeapi');
       ShippingEasy::setApiBase($baseurl);
       ShippingEasy::setApiKey($apikey);
       ShippingEasy::setApiSecret($secretkey);

       //Call ShippingEasy Cancellation API.
       try {
         $cancellation = new ShippingEasy_Cancellation($storeapi,"$orderid");
         $cancellation->create();
       } catch (Exception $e) {
           echo '<b> Error: ',  $e->getMessage(), "\n </b>"; die;
       }
     }
  }
  return $location;
}

function woocommerce_view_menu() {
  global $wpdb; 
  $id = $_GET['order'];
  $myrows = $wpdb->get_results( "SELECT comment_ID FROM wp_comments WHERE comment_post_ID = '$id'" );
  $count = count($myrows);
  if($count > 2) {
    $querystr = "
    SELECT comment_content FROM wp_comments WHERE comment_post_ID = '$id' ORDER BY comment_ID DESC  LIMIT 1 ";
    $pageposts = $wpdb->get_results($querystr, OBJECT);
    print_r($pageposts[0]->comment_content);
  }
}
add_action('woocommerce_view_order', 'woocommerce_view_menu');
/*-----------------End Call Shipped API------*/
