<?php 
/* 
Plugin Name: ShippingEasy 
Description: Plugin to integrate ShippingEasy API.Using this plugin orders will be created in ShippingEasy. When order is shipped and updated in ShippingEasy same will get updated in woocommerce.
Author: ShippingEasy
Version: 1.2
*/

//Wordpress Hook to create admin menu.
add_action('admin_menu', 'shippingeasy_create_menu');

//Function to create menu.
function shippingeasy_create_menu() {

  //create menu for ShippingEasy API settings
  add_menu_page('ShippingEasy Plugin Settings', 'ShippingEasy', 'administrator', __FILE__, 'shippingeasy_settings_page',plugins_url('/images/generic.png', __FILE__));

  //call register settings function
  add_action( 'admin_init', 'shippingeasy_register_mysettings' );
}

function shippingeasy_register_mysettings() {

 if( isset($_GET['settings-updated']) ) { ?>
    <div id="message" class="updated">
        <p><strong><?php _e('Settings saved.') ?></strong></p>
    </div>
 <?php } 
	//register our settings
	register_setting( 'shippingeasy-settings-group', 'apikey' );
	register_setting( 'shippingeasy-settings-group', 'secretkey' );
	register_setting( 'shippingeasy-settings-group', 'baseurl' );
	register_setting( 'shippingeasy-settings-group', 'storeapi' );
}

//ShippingEast API setting page
function shippingeasy_settings_page() {
?>
<div class="wrap">
<h2>ShippingEasy Settings</h2>
<form method="post" action="options.php">
    <?php settings_fields( 'shippingeasy-settings-group' ); ?>
    <?php do_settings_sections( 'shippingeasy-settings-group' ); ?>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Customer API Key</th>
        <td><input style="width:520px" type="text" name="apikey" value="<?php echo get_option('apikey'); ?>" /></td>
        </tr>
         
        <tr valign="top">
        <th scope="row">Customer Secret Key</th>
        <td><input style="width:520px" type="text" name="secretkey" value="<?php echo get_option('secretkey'); ?>" /></td>
        </tr>
        
        <tr valign="top">
        <th scope="row">Base URL</th>
        <?php if(get_option('baseurl') == '') { ?>
           <td><input style="width:520px" type="text" name="baseurl" value="https://app.shippingeasy.com" /></td>
        <?php }
        else { ?>
          <td><input style="width:520px" type="text" name="baseurl" value="<?php echo get_option('baseurl'); ?>" /></td>
        <?php } ?>
        </tr>

        <tr valign="top">
        <th scope="row">Store API Key</th>
        <td><input style="width:520px" type="text" name="storeapi" value="<?php echo get_option('storeapi'); ?>" /></td>
        </tr>
    </table>
    
    <?php submit_button(); ?>

</form>
</div>
<?php }

//Shipment callback functionality.
class Pugs_API_Endpoint{	

	/** Hook WordPress
	*	@return void
	*/
	public function __construct(){
		add_filter('query_vars', array($this, 'add_query_vars'), 0);
		add_action('parse_request', array($this, 'sniff_requests'), 0);
		add_action('init', array($this, 'add_endpoint'), 0);
	}	

	/** Add public query vars
	*	@param array $vars List of current public query vars
	*	@return array $vars 
	*/
	public function add_query_vars($vars){
		$vars[] = 'shipment';
		$vars[] = 'callback';
		return $vars;
	}

	/** Add API Endpoint
	*	This is where the magic happens - brush up on your regex skillz
	*	@return void
	*/
	public function add_endpoint(){
		add_rewrite_rule('^shipment/callback','index.php?shipment=1&callback=1','top');
	}

	/**	Sniff Requests
	*	This is where we hijack all API requests
	* 	If $_GET['__api'] is set, we kill WP and serve up pug bomb awesomeness
	*	@return die if API request
	*/
	public function sniff_requests(){
		global $wp;
		if(isset($wp->query_vars['shipment'])){
			$this->handle_request();
			exit;
		}
	}

	/** Handle Requests
	*	This is where we send off for an intense pug bomb package
	*	@return void 
	*/
	protected function handle_request(){
		global $wp;
    global $wpdb;
		$pugs = $wp->query_vars['pugs'];
    $values = file_get_contents('php://input');
    $wpdb->insert( 
      "shipping_requests", 
        array(
          'value' => $values,
          'server' => 1,
          'request' => 1,
          'http_response_header' => 1,
          'http_raw_post_data' => 1
        )
    );

    $output = json_decode($values, true);

    //Store the values of shipped order which we are getting from ShippingEasy.
    $id = $output['shipment']['orders'][0]['external_order_identifier'];
    $shipping_id = $output['shipment']['id'];
    $tracking_number = $output['shipment']['tracking_number'];
    $carrier_key = $output['shipment']['carrier_key'];
    $carrier_service_key = $output['shipment']['carrier_service_key'];
    $shipment_cost_cents = $output['shipment']['shipment_cost'];
    $shipment_cost = ($shipment_cost_cents / 100);
    $line_subtotal = 0;
    $total_tax = 0;
    $cart_discount = 0;
    $order_discount = 0;

    $comment_update = 'Shipping Tracking Number: ' .$tracking_number. '<br/> Carrier Key: ' .$carrier_key. '<br/> Carrier Service Key: ' .$carrier_service_key;

    //Store in E-commerce databse
    $myrows = $wpdb->get_results( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = $id && comment_agent = 'ShippingEasy'" );
    $count = count($myrows);

    $order = new WC_Order($id);
    $cart_discount =  $order->cart_discount;
    $order_discount =  $order->order_discount;

    foreach($order->get_items() as $item){
  	  $regular_price 	= get_post_meta( $item['product_id'] ,'_regular_price');
      $line_subtotal += $regular_price[0];
    }

    foreach($order->get_tax_totals( ) as $taxes){
      $total_tax = $taxes->amount;
    }

    $updated_price = ($line_subtotal + $shipment_cost + $total_tax) - ($cart_discount + $order_discount);


    if($count > 0) {
      $wpdb->query("UPDATE $wpdb->comments SET comment_content = '$comment_update' WHERE comment_post_ID = $id ORDER BY comment_ID DESC  LIMIT 1");
      $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
      $wpdb->query("UPDATE $wpdb->postmeta SET meta_value = $shipment_cost WHERE meta_key = '_order_shipping' && post_id = $id ");
      $wpdb->query("UPDATE $wpdb->postmeta SET meta_value = $updated_price WHERE meta_key = '_order_total' && post_id = $id ");
    }
    else {
      $time = current_time('mysql');
      $data = array(
        'comment_post_ID' => $id,
        'comment_author' => 'ShippingEasy',
        'comment_author_email' => 'order@shippingeasy.com',
        'comment_author_url' => '',
        'comment_content' => $comment_update,
        'comment_parent' => 0,
        'user_id' => 1,
        'comment_author_IP' => '',
        'comment_agent' => 'ShippingEasy',
        'comment_type' => 'order_note',
        'comment_date' => $time,
        'comment_approved' => 1,
      );

      $comment_id = wp_insert_comment($data);
      add_comment_meta( $comment_id, 'is_customer_note', 1 );

      $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
      $wpdb->query("UPDATE $wpdb->postmeta SET meta_value = $shipment_cost WHERE meta_key = '_order_shipping' && post_id = $id ");
      $wpdb->query("UPDATE $wpdb->postmeta SET meta_value = $updated_price WHERE meta_key = '_order_total' && post_id = $id ");
    }

	  		  	$this->send_response('Order has been updated successfully ' .$comment_update, json_decode($pugs));
	}

	/** Response Handler
	*	This sends a JSON response to the browser
	*/
	protected function send_response($msg, $pugs = ''){
		$response['message'] = $msg;
		header('content-type: application/json; charset=utf-8');
	    echo json_encode($response)."\n";
	    exit;
	}
}
new Pugs_API_Endpoint();

/**
 * WooCommerce place order in ShippingEasy
 *
 */

function shipping_place_order( $order_id ) {
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
  $downloads_subtotal = 0;
  $download_total = 0;
  $order = new WC_Order($order_id);
  $temp = array();
  $total_products = count($order->get_items());
  foreach($order->get_items() as $item){
    //echo "<pre>"; print_r($item); echo "</pre>";
    $product_id 	   = $item['product_id'];
    $post_meta 		   = get_post_meta( $item['product_id'] );
    //echo "<pre>"; print_r($post_meta); echo "</pre>";
    $check_virtual[]  =  $post_meta['_virtual'][0];
    $check_download[] =  $post_meta['_downloadable'][0];
    if($post_meta['_virtual'][0] == 'yes' || $post_meta['_downloadable'][0] == 'yes') {
      $download_total = $item['line_subtotal'];
      $downloads_subtotal    += $download_total;
    }
  }

  if (in_array("yes", $check_virtual)) {
    $check_virtuals += 1;    
  }
  if (in_array("yes", $check_download)) {
    $check_downloads += 1;
  }
  $total_download_product = $check_virtuals + $check_downloads;

  if($total_products > $total_download_product ) {
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
  $order_cart_total = $order->order_total;
  $order_totals = $order_cart_total - $downloads_subtotal ;
  $order_total =  $order_totals;
  $order_tax =  $order->order_tax;
  $order_shipping =  $order->order_shipping;
  $order_shipping_tax =  $order->order_shipping_tax;
  $cart_discount =  $order->cart_discount;

  foreach($order->get_items() as $item){
	  $post 			= get_post( $item['product_id'] );
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
                  "product_options" => "$option_values",
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
        "email" => "$billing_email",
        "phone_number" => "$billing_phone",
        "residential" => "true",
        "address" => "$shipping_address",
        "address2" => "$shipping_address2",
        "province" => "",
        "state" => "$shipping_state",
        "city" => "$shipping_city",
        "postal_code" => "$shipping_postcode",
        "postal_code_plus_4" => "",
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

  //echo "<pre>"; print_r($values); echo "</pre>"; die;
  //Call ShippingEasy API to place order.
  try {
    $order=new ShippingEasy_Order($storeapi,$values);
    $order->create();
  } catch (Exception $e) {
    echo '<b> Error: ',  $e->getMessage(), "\n </b>";
  }
  }
}

function shipping_order_detail( $order_id ){
  $temp = array();
  $order = new WC_Order($order_id);
  foreach($order->get_items() as $item){
    $post 			= get_post( $item['product_id'] );
    $product_id 		= $item['product_id'];
    $post_meta 		= get_post_meta( $item['product_id'] );
    $regular_price	= get_post_meta( $item['product_id'] ,'_regular_price');
    $sku 				= get_post_meta( $item['product_id'] ,'_sku');
    $item_name 		= $item['name'];
    $item_qty 		= $item['qty'];
    $line_subtotal    = $item['line_subtotal'];
    $unit_price 		= $line_subtotal/$item_qty;
    $line_subtotal    = $item['line_subtotal'];
    $check_virtual    =  $post_meta['_virtual'][0];
    $check_download   =  $post_meta['_downloadable'][0];

    if($post_meta['_weight'][0] == '') {
      $weight_to_oz = 0.00;
    }
    else {
      $weight_to_oz = woocommerce_get_weight( $post_meta['_weight'][0], 'oz' );
    }
    if($check_virtual == 'no' && $check_download == 'no') {
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
    if(isset($post_meta['_default_attributes'])) {
      $product_attr = unserialize($post_meta['_product_attributes'][0]);
      $product_attr_list = array_keys($product_attr);
      foreach($product_attr_list as $product_attr_name) {
        $product_attr_name_key[] = $product_attr[$product_attr_name]['name'];
      }
      $attr_count = count($product_attr_name_key);
      for($i = 0; $i < $attr_count; $i++) {
        $product_key = $product_attr_name_key[$i];
        $option_value[$product_key] = $item[$product_key];
      }

      foreach($option_value as $key => $value){
        $option_value[$key] = $value;
      }
      foreach($temp as $temp_arraykey => $temp_arrayvalue) {
        $temp_arraykey = $temp_arraykey;
      }
      $product_options[product_options] = $option_value;
      $temp[$temp_arraykey] = $temp[$temp_arraykey] + $product_options;
    }
  }
  $temp_count = count($temp);
  for($i=0 ; $i<$temp_count ; $i++) {
    if($temp[$i]['weight_in_ounces'] == 0) {
      unset($temp[$i]['weight_in_ounces']);
    }

  }
  return $temp;
}

add_action( 'woocommerce_thankyou', 'shipping_place_order' );

//ShippingEasy Cancellation API.
//Woocommerce Hook
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


//Wordpress plugin uninstall hook.
function shippingeasy_order_uninstall() {
  global $wpdb;
  // Delete ShippingEasy API configuration fields.
  delete_option('apikey');
  delete_option('secretkey');
  delete_option('baseurl');
  delete_option('storeapi');
}
register_deactivation_hook(__FILE__,'shippingeasy_order_uninstall');


