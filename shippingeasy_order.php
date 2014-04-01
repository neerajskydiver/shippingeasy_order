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
 // error_reporting(E_ALL);
 // ini_set("display_errors", 1);
  //Get the Requested url.
  if($_SERVER['REQUEST_URI'] == '/shipment-callback/') {

    //$values = '{"shipment": {"id":12521,"tracking_number":"ABC123456789","carrier_key":"USPS","carrier_service_key":"First","orders": [{"id":96,"external_order_identifier":"100000120"}]}}';
    $values = file_get_contents('php://input');
    //$body = @file_get_contents('php://input');
    //$tt = http_get_request_body($_POST);
    //$rr = http_get_request_body('php://input');
    //$_SESSION['values'] = $values;
    //$_SESSION['post']   = $_POST;
    //$_SESSION['body']   = $body;
    //$_SESSION['tt']   = $tt;
    //$_SESSION['rr']   = $rr;

    echo "<pre>"; print_r($values); echo "</pre>";
    $output = json_decode($values, true);
    //$_SESSION['output']   = $output;
    echo "<pre>"; print_r($output); echo "</pre>";
    //Store the values of shipped order which we are getting from ShippingEasy.
    $id = $output['shipment']['orders']['id'];
    $shipping_id = $output['shipment']['id'];
    $tracking_number = $output['shipment']['tracking_number'];
    $carrier_key = $output['shipment']['carrier_key'];
    $carrier_service_key = $output['shipment']['carrier_service_key'];
    $external_order_identifier = $output['shipment']['orders']['external_order_identifier'];

    $rrr = 'Shipping ID :' .$shipping_id . '<br/> Shipping Tracking Number :' .$tracking_number. '<br/> Carrier Key :' .$carrier_key. '<br/> Carrier Service Key :' .$carrier_service_key. '<br/> External Order Identifier :' .$external_order_identifier ;
    //Store in E-commerce databse
    $myrows = $wpdb->get_results( "SELECT comment_ID FROM $wpdb->comments WHERE comment_post_ID = $id" );
    echo $count = count($myrows);
 
    if($count > 2) {
      $wpdb->query("UPDATE $wpdb->comments SET comment_content = '$rrr' WHERE comment_post_ID = $id ORDER BY comment_ID DESC  LIMIT 1");
      $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
    }
    else {
      $time = current_time('mysql');
      $data = array(
        'comment_post_ID' => $id,
        'comment_author' => 'admin',
        'comment_author_email' => 'woocommerce@eventcamp.us',
        'comment_author_url' => '',
        'comment_content' => $rrr,
        'comment_parent' => 0,
        'user_id' => 1,
        'comment_author_IP' => '',
        'comment_agent' => 'WooCommerce',
        'comment_type' => 'order_note',
        'comment_date' => $time,
        'comment_approved' => 1,
      );

      $comment_id = wp_insert_comment($data);
      add_comment_meta( $comment_id, 'is_customer_note', 1 );

      $wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
      //echo "INSERT INTO $wpdb->comments comment_content = '$rrr' WHERE comment_post_ID = $id";
      //$wpdb->insert( $table_name, array( 'comment_content' => $rrr )  );
      //$wpdb->insert( $wpdb->comments, array( 'album' => $_POST['album'], 'artist' => $_POST['artist'] ) );
      //$wpdb->query("INSERT INTO $wpdb->comments (comment_content) VALUES ('$rrr') WHERE comment_post_ID = $id");
      //$wpdb->query("UPDATE $wpdb->term_relationships SET term_taxonomy_id = 10 WHERE object_id = $id ");
    }
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

//Wordpress hook for redirecting after update aur submit the post.
//add_filter('redirect_post_location', 'redirect_to_post_on_publish_or_save');

//function redirect_to_post_on_publish_or_save($location) {
  //global $post;
  //print_r($_POST); die;
  // Publishing draft or updating published post
  //if ((isset($_POST['publish']) || isset($_POST['save'])) && preg_match("/post=([0-9]*)/", $location, $match) && $post && $post->ID == $match[1] &&
    //    (isset($_POST['publish']) || $post->post_status == 'publish') && $pl = get_permalink($post->ID)) {

    //$location = $pl; die('ccccccc');
  //}
  //return $location;
//}

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

  error_reporting(E_ALL);
  ini_set("display_errors", 1);

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
  
  //Fetch all the values of related order ID.
  $res = $wpdb->get_results("SELECT order_item_name,order_item_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id='$order_id'");
  foreach ($res as $rs) {
    $post_title1[] = $rs->order_item_name;
    $order_item_id[] = $rs->order_item_name;
 //print_r($order_item_id); die;
    $order_items_id[] = $rs->order_item_id; 
  }


  foreach($order_items_id as $tt) {
    $result_qty = $wpdb->get_results("SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE order_item_id ='$tt' && meta_key='_qty'");
    foreach ($result_qty as $results_qty) {
      $qty[] = $results_qty->meta_value;
    }
   
  }

  foreach($order_item_id as $rr) {
    //echo "SELECT ID FROM wp_posts WHERE post_title ='$rr'";
    $result = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_title ='$rr'");
    foreach ($result as $results) {
      $meta_id[] = $results->ID;
    }
  

  }


  foreach($meta_id as $ss) {
    $weight = $wpdb->get_results("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id='$ss' && meta_key ='_weight'");
    foreach ($weight as $weight_kg) {
      $wight_value[] = $weight_kg->meta_value;
    }

    $sku = $wpdb->get_results("SELECT meta_value FROM {$wpdb->prefix}postmeta WHERE post_id='$ss' && meta_key ='_sku'");
    foreach ($sku as $sku_value) {
      $sku_values[] = $sku_value->meta_value;
    }
  }

  $total_qty = count($qty);

  $orders = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}postmeta WHERE post_id='$order_id'");
  foreach ($orders as $order_detail) {
    if($order_detail->meta_key == '_billing_company') {
      $billing_company =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_first_name') {
      $billing_first_name =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_last_name') {
      $billing_last_name =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_address_1') {
      $billing_address =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_address_2') {
      $billing_address2 =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_city') {
      $billing_city =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_state') {
      $billing_state =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_postcode') {
      $billing_postcode =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_country') {
      $billing_country =  $order_detail->meta_value;
    }

    if($order_detail->meta_key == '_billing_email') {
      $billing_email =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_billing_phone') {
      $billing_phone =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_company') {
      $shipping_company =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_first_name') {
      $shipping_first_name =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_last_name') {
      $shipping_last_name =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_address_1') {
      $shipping_address =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_address_2') {
      $shipping_address2 =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_city') {
      $shipping_city =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_state') {
      $shipping_state =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_postcode') {
      $shipping_postcode =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_country') {
      $shipping_country =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_shipping_method') {
      $shipping_method =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_cart_discount') {
      $cart_discount =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_order_total') {
      $order_total =  $order_detail->meta_value;
    }
    if($order_detail->meta_key == '_order_shipping') {
      $order_shipping =  $order_detail->meta_value;
    }
  }

  $orders1 = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE ID='$order_id'");
  foreach ($orders1 as $order_detail1) {
    $post_date = $order_detail1->post_modified;
    $post_title = $order_detail1->post_title;
  }

  $term = $wpdb->get_results("SELECT term_taxonomy_id FROM {$wpdb->prefix}term_relationships WHERE object_id='$order_id'");
  foreach ($term as $term_id) {
    $term_id = $term_id->term_taxonomy_id;
  }

  $termname = $wpdb->get_results("SELECT slug FROM {$wpdb->prefix}terms WHERE term_id ='$term_id'");
  foreach ($termname as $term_status) {
    $status = $term_status->slug;
  }

  //Calculate the time.
  $time = time();
  $date = date('Y-m-d H:i:s',$time);

  $total_excluding_tax = ($order_total - $order_shipping);
  //Creating order array.
  $values = array(
    "external_order_identifier" => "$order_id",
    "ordered_at" => "$date",
    "order_status" => "awaiting_shipment",
    "subtotal_including_tax" => "$order_total",
    "total_including_tax" => "$order_total",
    "total_excluding_tax" => "$total_excluding_tax",
    "discount_amount" => "$cart_discount",
    "coupon_discount" => "1.00",
    "subtotal_including_tax" => "$order_total",
    "subtotal_excluding_tax" => "$total_excluding_tax",
    "subtotal_excluding_tax" => "$total_excluding_tax",
    "subtotal_tax" => "$order_shipping",
    "total_tax" => "$order_shipping",
    "base_shipping_cost" => "$order_shipping",
    "shipping_cost_including_tax" => "$order_shipping",
    "shipping_cost_excluding_tax" => "$order_shipping",
    "shipping_cost_tax" => "$order_shipping",
    "base_handling_cost" => "0.00",
    "handling_cost_excluding_tax" => "0.00",
    "handling_cost_including_tax" => "0.00",
    "handling_cost_tax" => "0.00",
    "base_wrapping_cost" => "0.00",
    "wrapping_cost_excluding_tax" => "0.00",
    "wrapping_cost_including_tax" => "0.00",
    "wrapping_cost_tax" => "0.00",
    "notes" => "Please send promptly.",
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
        "items_total" => "1",
        "items_shipped" => "0",
        "line_items" => shipping_order_detail( $total_qty,$post_title1,$sku_values,$wight_value)
      )
    )
  );
  //Call ShippingEasy API to place order.
  $order=new ShippingEasy_Order($storeapi,$values);
  $tt = $order->create();
  print_r($tt);
}

function shipping_order_detail( $total_qty,$post_title1,$sku_values,$wight_value ){
  $temp = array();
  for($i=0 ; $i<$total_qty ; $i++) {
    $title = $post_title1[$i];
    $sku = $sku_values[$i];
    $wight_values = $wight_value[$i];
    $temp[] = array(
		  "item_name" => "$title",
		  "sku" => "$sku",
		  "bin_picking_number" => "7",
		  "unit_price" => "1.30",
		  "total_excluding_tax" => "1.30",
		  "weight_in_ounces" => "$wight_values",
		  "quantity" => "$total_qty",
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
 // error_reporting(E_ALL);
 // ini_set("display_errors", 1);
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
       $cancellation = new ShippingEasy_Cancellation($storeapi,"$orderid");
       $cancellation->create();
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

