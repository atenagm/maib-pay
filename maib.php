<?php
/*
Plugin Name: MAIB-PAY
Plugin URI: http://nopage.com
Description: plugin will add order to database and redirect to pay page on maib. Use shortcode [pay_maib] as action page.
Version: 1.0
Author: vmiron
Author URI: 
*/
?>
<?php

// create shortcode [pay_maib]
add_shortcode('pay_maib','maib_echo');
function maib_echo() {
    global $post;
    function getUserIP() {
        $ipaddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_X_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if(isset($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']))
            $ipaddress = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
        else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if(isset($_SERVER['HTTP_FORWARDED']))
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if(isset($_SERVER['REMOTE_ADDR']))
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';
        return $ipaddress;
    }
    $ip = getUserIP();
    // check if user post value amount
        if ($_POST['amount']) {
            // check if return exchange rate exist !
            if ( !function_exists( 'get_bnm_data' ) ) {
                return "error";
                wp_die(); // if not die
            }
            // check values 
        if (is_numeric($_POST['amount']) && is_numeric($_POST['valute']) && is_numeric($_POST['project-id'])) {
            $amount = $_POST['amount'] * 100;
            $pay = 498;
            $USD = get_bnm_data(USD);
            $EUR = get_bnm_data(EUR);
            // MDL
            if ($_POST['valute'] == 1) {
                $prvl = $amount / 100;
            }
            // USD == 2 
            if ($_POST['valute'] == 2) {
                $amount *= $USD;
                $prvl = $amount / 100;
            }
            // EUR == 3
            if ($_POST['valute'] == 3) {
                $amount *= $EUR;
                $prvl = $amount / 100;
            }
        } 
        else {
            // if is not numeric valus
            echo "error";
            exit;
      }
    global $woocommerce;
  

    $product_id = $_POST['project-id']; // project id
  
    $new_product_price = $prvl; // Value that user want to donate
    $quantity = 1; // quantity of product  , don't change! 
    $name = explode(" ", $_POST['name']); // explode first name and last name
      $address = array(
        'first_name' => $name[0],
        'last_name'  => $name[1],
        'email'      => $_POST['email'],
        'phone'      => $_POST['tell'],
    );
          
    // Get an instance of the WC_Product object
    $product = wc_get_product( $product_id );
      
    // Change the product price
    $product->set_price( $new_product_price );
      
    // Create the order
    $order = wc_create_order();
      
    // Add the product to the order
    $order->set_address( $address, 'billing' );
    $order->add_product( $product, $quantity);
    $order->calculate_totals(); // updating totals
    $order->set_payment_method('MAIB');

    $options = array(
        CURLOPT_VERBOSE => '1',
        CURLOPT_CERTINFO => true,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CAINFO => 'cacert.pem',
        CURLOPT_SSLCERT => 'pcert.pem',
        CURLOPT_SSLKEY => 'key.pem',
        CURLOPT_SSLCERTPASSWD => 'Za86DuC$',
        CURLOPT_HEADER => 0,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'command=v&amount='. $amount . '&currency='. $pay . '&client_ip_addr='.$ip,
    );
    $bankKey = new Curl('https://ecomm.maib.md:4499/ecomm2/MerchantHandler', $options);
    
    $response = $bankKey->getResponse();

        if (strpos($response, "TRANSACTION_ID") === 0) {
            $key = substr($response, 16);
            $keyMeta = $key;
            $keyMeta = strtolower($keyMeta);
            update_post_meta($order->ID, 'transaction_id', $keyMeta);
            $key = urlencode($key);
            
            $order->save(); // Save the order data
            header('Location:https://ecomm.maib.md:7443/ecomm2/ClientHandler?trans_id=' . $key);
        }
    }
}

add_action('template_redirect', 'maib_echo'); // execute function maib_echo

function get_order_by_trans_id($trans_id) {
    global $wpdb;
    $query = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key=%s AND meta_value=%s",
        strtolower('transaction_id'),
        $trans_id
    );

    $order_id = $wpdb->get_var($query);
    if(!$order_id) {
        return false;
    }

    return wc_get_order($order_id);
}

function verify() {
    $id     = strtolower($_POST['trans_id']);
    $key    = urlencode($_POST['trans_id']); 
    $order_id = 0;
    $order_id = get_order_by_trans_id($id);
    $options = array(
        CURLOPT_VERBOSE => '1',
        CURLOPT_CERTINFO => true,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_CAINFO => 'cacert.pem',
        CURLOPT_SSLCERT => 'pcert.pem',
        CURLOPT_SSLKEY => 'key.pem',
        CURLOPT_SSLCERTPASSWD => 'Za86DuC$',
        CURLOPT_HEADER => 0,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'command=c&trans_id='. $key  . '&client_ip_addr='.$ip,
    );
    $bankKey = new Curl('https://ecomm.maib.md:4499/ecomm2/MerchantHandler', $options);
    $response = $bankKey->getResponse();

  
        if (preg_match("/RESULT: OK/i", $response)) {
            echo "Tranzaction succes!";
            $order = new WC_Order($order_id->ID); 
            $note = 'At ' . date('Y-m-d H:i:s') .' order was updated order key = '. $_POST['trans_id'] ;
            $order->update_status('completed', $note);
            update_post_meta($order_id->ID, 'transaction_details', $response);
        } else {
            echo "Error";
        }
    }
 
}

class Curl
{
    /** @var resource cURL handle */
    private $ch;

    /** @var mixed The response */
    private $response = false;

    /**
     * @param string $url
     * @param array  $options
     */
    public function __construct($url, array $options = array())
    {
        $this->ch = curl_init($url);

        foreach ($options as $key => $val) {
            curl_setopt($this->ch, $key, $val);
        }

        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Get the response
     * @return string
     * @throws \RuntimeException On cURL error
     */
    public function getResponse()
    {
         if ($this->response) {
             return $this->response;
         }

        $response = curl_exec($this->ch);
        $error    = curl_error($this->ch);
        $errno    = curl_errno($this->ch);

        if (is_resource($this->ch)) {
            curl_close($this->ch);
        }

        if (0 !== $errno) {
            throw new \RuntimeException($error, $errno);
        }

        return $this->response = $response;
    }

    /**
     * Let echo out the response
     * @return string
     */
    public function __toString()
    {
        return $this->getResponse();
    }
}

add_shortcode('check_pay','verify');
?>