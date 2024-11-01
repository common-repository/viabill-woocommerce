<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if (! defined ('VIABILL_TRY_PAYMENT_METHOD_ID')) {
  define('VIABILL_TRY_PAYMENT_METHOD_ID', 'viabill_try');
  define('VIABILL_MONTHLY_PAYMENT_METHOD_ID', 'viabill_official');
}

if ( ! class_exists( 'Viabill_API' ) ) {
  /**
   * Viabill_API class
   *
   * @since 0.1
   */
  class Viabill_API {

    /**
     * API's interface.
     *
     * @var Viabill_Connector
     */
    private $connector;

    /**
     * Name of the ViaBill's approved status.
     *
     * @var string
     */
    private $approved_status;

    /**
     * Name of the ViaBill's captured status.
     *
     * @var string
     */
    private $captured_status;

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

    /**
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;

    /**
     * Class constructor.
     */
    public function __construct() {
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-connector.php' );
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );

      $this->connector = new Viabill_Connector();
      $this->settings  = Viabill_Main::get_gateway_settings();
      $this->logger    = new Viabill_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );

      $this->captured_status = (isset($this->settings['order_status_after_captured_payment']))?$this->settings['order_status_after_captured_payment']:'processing';
      $this->approved_status = (isset($this->settings['order_status_after_authorized_payment']))?$this->settings['order_status_after_authorized_payment']:'on-hold';
    }

    /**
     * Initialize all the needed hook methods.
     */
    public function register() {      
      add_action( 'woocommerce_api_' . VIABILL_MONTHLY_PAYMENT_METHOD_ID, array( $this, 'do_checkout_status' ) );
      add_action( 'woocommerce_api_' . VIABILL_MONTHLY_PAYMENT_METHOD_ID . '_checkout_authorize', array( $this, 'do_checkout_authorize' ) );
      add_action( 'woocommerce_api_' . VIABILL_TRY_PAYMENT_METHOD_ID, array( $this, 'do_checkout_status' ) );
      add_action( 'woocommerce_api_' . VIABILL_TRY_PAYMENT_METHOD_ID . '_checkout_authorize', array( $this, 'do_checkout_authorize' ) );
    }

    /**
     * Return full URL of the 'viabill' endpoint.
     *
     * @return string
     */
    public function get_checkout_status_url($payment_method_id = null) {
      if (empty($payment_method_id)) $payment_method_id = VIABILL_MONTHLY_PAYMENT_METHOD_ID;
      $url = WC()->api_request_url( $payment_method_id );
      if ($payment_method_id == VIABILL_TRY_PAYMENT_METHOD_ID) {
         $url = $url; // &trybeforeyoubuy=1';
      }
      return $url;
    }

    /**
     * Die with given message, encoded as JSON, and set HTTP response status.
     *
     * @param  mixed   $message
     * @param  integer $status_code Defaults to 200.
     */
    private function respond( $message, $status_code = 200 ) {
      status_header( $status_code );
      header( 'content-type: application/json; charset=utf-8' );

      $encoded_message = wp_json_encode( $message );
      if ( ! $encoded_message ) {
        $this->logger->log( 'Failed to encode API response message.', 'error' );
        $encoded_message = -1;
      }

      die( $encoded_message );
    }

    /**
     * Return assoc array of parameters from either 'php://input' (POST request
     * body), or $_REQUEST.
     *
     * @return array $params
     */
    private function resolve_params() {
      $params = json_decode( file_get_contents( 'php://input' ), true );
      if ( empty( $params ) ) {
        // NOTE: external request, signature is checked in the calling method to determine validity.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $params = $_REQUEST;
        // phpcs:enable
      }

      return $params;
    }

    /**
     * Check if local signature matches with one from ViaBill ($params).
     *
     * @param  WC_Order  $order
     * @param  array     $params
     * @param  boolean   $use_deprecated_id Defaults to false.
     * @return boolean
     */
    public function is_signature_match( $order, $params, $use_deprecated_id = false ) {
      $transaction_id = $this->connector->get_unique_transaction_id( $order, $use_deprecated_id );
      $order_number   = $this->connector->get_order_number( $order, $use_deprecated_id );
      $order_amount   = $order->get_total();

      $signature_p1 = $transaction_id . '#' . $order_number . '#' . number_format($order_amount, 2, '.', '') . '#' . $order->get_currency();
      $signature_p2 = $params['status'] . '#' . $params['time'] . '#' . get_option( 'viabill_secret', '' );

      $local_signature = md5( $signature_p1 . '#' . $signature_p2 );
      $is_match        = $local_signature === $params['signature'];

      if ( ! $is_match && ! $use_deprecated_id ) {
        $signature_mismatch_info = "Mismatch info: Local signature with deprecated disabled is [".$signature_p1. ']#[' . $signature_p2.'] remote signature is '.$params['signature'];
        $this->logger->log( $signature_mismatch_info, 'error' );
        return $this->is_signature_match( $order, $params, true );
      }

      if (! $is_match ) {
        $signature_mismatch_info = "Mismatch info: Local signature with deprecated enabled is ".$signature_p1. '#' . $signature_p2.' remote signature is '.$params['signature'];
        $this->logger->log( $signature_mismatch_info, 'error' );
      }

      return $is_match;
    }

    /**
     * Should be used as a callback URL for ViaBill API checkout request.
     */
    public function do_checkout_status() {
      // Check if any parametars are received.
      $params = $this->resolve_params();
      // Log parametars if debug loggger is turned on.
      $this->logger->log( wp_json_encode( $params ), 'info' );
      if ( empty( $params ) ) {
        $this->logger->log( 'Missing params for status API endpoint.', 'error' );
        $this->respond(
          array(
            'is_success' => false,
            'message'    => 'Missing parameters.',
          ),
          400
        );
      }

      // Check if required params received.
      foreach ( array( 'orderNumber', 'status', 'signature', 'time' ) as $required_param ) {
        if ( ! isset( $params[ $required_param ] ) || empty( $params[ $required_param ] ) ) {
          $this->logger->log( 'Missing ' . $required_param . ' param for status API endpoint.', 'error' );
          $this->respond(
            array(
              'is_success' => false,
              'message'    => 'Missing required parametars.',
            ),
            400
          );
        }
      }

      $order_id = wc_get_order_id_by_order_key( $params['orderNumber'] );
      if ( empty( $order_id ) ) {
        $order_id = intval( $params['orderNumber'] );
      }

      // Check if received order id matches any orders.
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' for status API endpoint.', 'error' );
        $this->respond(
          array(
            'is_success' => false,
            'message'    => 'Couldn\'t find corresponding order.',
          ),
          500
        );
      }

      // Check if order has been payed with ViaBill.
      $payment_method = $order->get_payment_method();
      if (( VIABILL_MONTHLY_PAYMENT_METHOD_ID !== $payment_method ) && 
        ( VIABILL_TRY_PAYMENT_METHOD_ID !== $payment_method )) {         
        $this->logger->log( 'Order ' . $order_id . ' payment method ['.$payment_method.'] is not ViaBill.', 'error' );
        $this->respond(
          array(
            'is_success' => false,
            'message'    => 'Payment method is not ViaBill',
          ),
          500
        );
      }

      // Check if signature is valid.
      if ( ! $this->is_signature_match( $order, $params ) ) {
        $this->logger->log( 'Signature mismatch for order ' . $order_id . ' for status API endpoint.', 'error' );
        $order->add_order_note( __( 'Signature mismatch! Order\'s data from ViaBill is different than the one in web shop.', 'viabill' ) );
        $this->respond(
          array(
            'is_success' => false,
            'message'    => 'Signature mismatch.',
          ),
          500
        );
      }

      // Check if order status has already been set.
      if ( 'pending' !== $order->get_status() ) {
        $this->logger->log( 'Order ' . $order_id . ' status has already been set.', 'error' );

        $this->respond(
          array(
            'is_success' => false,
            'message'    => 'Order status already set.',
          ),
          500
        );
      }

      $status       = strtolower( htmlentities( $params['status'], ENT_QUOTES, 'UTF-8' ) );
      $auto_capture = 'yes' === Viabill_Main::get_gateway_settings( 'auto-capture' );
      $on_hold_mail = 'yes' === Viabill_Main::get_gateway_settings( 'automatic-capture-mail' );

      if ( $auto_capture && $on_hold_mail ) {
        add_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false', 10, 2 );
      }

      $this->logger->log( 'All checks passed! Order ' . $order_id . ' status returned by ViaBill: ' . $status, 'notice' );

      // Saving data after each edit since auto capture also edits the same data so we prevent overwrites.
      if ( 'approved' === $status ) {
        $order->update_meta_data( 'viabill_status', $status );
        $order->set_status( $this->approved_status, __( 'Order approved by ViaBill.', 'viabill' ) );
        $order->save();

        if ( $auto_capture ) {
          $this->logger->log( 'Executed automatic capture for order ' . $order_id, 'notice' );
          $this->connector->capture( $order );
        }
      } else {
        if ( 'cancelled' === $status ) {
          $order->set_status( 'cancelled' );
        } elseif ( 'rejected' === $status ) {
          $order->set_status( 'failed' );
        }
        $order->add_order_note( __( 'ViaBill\'s new order status', 'viabill' ) . ': ' . ucfirst( $status ) );
        $order->update_meta_data( 'viabill_status', $status );
        $order->save();
      }

      $this->respond(
        array(
          'is_success' => true,
          'message'    => 'OK',
        )
      );
    }

    /**
    * Perform the checkout authorize request
    */
    public function do_checkout_authorize() {
          
      $formData = [
        "protocol" => "",
        "apikey" => "",
        "transaction" => "",
        "order_number" => "",
        "amount" => "",
        "currency" => "",
        "success_url" => "",
        "cancel_url" => "",
        "callback_url" => "",
        "test" => "",
        "customParams" => "",
        "cartParams" => "",
        "md5check" => "",
        "tbyb" => "",
      ];			

      foreach ($formData as $key => &$value) {	
        if (!isset($_POST[$key]))	continue;
        if ($key == 'customParams') {
          $customParams = str_replace('\"', '"', sanitize_text_field($_POST[$key]));
          // double json decode 
          if (!empty($customParams)) {
            $customParams = json_decode($customParams, true);
            foreach ($customParams as $cpk => &$cpv) {
              $dcpv = json_decode('{"'.$cpk.'":"'.$cpv.'"}', true);
              $cpv = $dcpv[$cpk];
            }
            $value = $customParams;
          }          
        } elseif ($key == 'cartParams') {
          $cartParams = str_replace('\"', '"', sanitize_text_field($_POST[$key]));
          if (!empty($cartParams)) {
            $value = $cartParams;
          }	else {
            unset($formData[$key]);
          }          
        } elseif ($key == 'tbyb') {
          $tbybValue = null;
          if (isset($_POST[$key])) {
            $tbybValue = intval(sanitize_text_field($_POST[$key]));
            switch ($tbybValue) {
              case 1:
              case 0:
                $value = $tbybValue;
                break;
            }
          }
          if (isset($tbybValue)) {
            $value = $tbybValue;
          } else {
            unset($formData[$key]);
          }
        } else {
          $value = sanitize_text_field($_POST[$key]);
        }
      }			
        
      $payload = json_encode($formData);
      
      $redirect_url = null;
      $error_msg = null;

      //++++++++++++++++++++++++++++++++++++++++
      $url = 'https://secure.viabill.com/api/checkout-authorize/addon/woocommerce';

      $method = 'POST';

      $headers = [
        //'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'User-Agent' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13',
      ];                   

      $args = array(
        'method' => $method,            
        'headers' => $headers,
        'body' => $payload,
        'redirection' => 0,
        'httpversion' => '1.1',
        'sslverify' => false,
        'timeout' => 60
      );

      $data = wp_remote_post($url, $args);
      $redirect_url = '';
      $error_msg = ''; 

      if (is_wp_error($data)) {        
        $error_msg = $data->get_error_message();    
        $this->logger->log( 'Failed to execute do_checkout_authorize(): '.$error_msg, 'critical' );
      }             

      if (is_array($data)) {    
          $data_str = '';
          $response_code =  (int) wp_remote_retrieve_response_code($data);
          if ($response_code == 400) {            
            $response_message = wp_remote_retrieve_response_message($data);            
            if (!empty($response_message)) {
              $error_msg = $response_message;
            }

            $response_body = wp_remote_retrieve_body($data);
            if (!empty($response_body)) {
              $body_data = json_decode($response_body, true);
              if (is_array($body_data)) {
                if (isset($body_data['errors'][0]['error'])) {
                    $error_msg = (empty($error_msg))?$body_data['errors'][0]['error']:$error_msg.' : '.$body_data['errors'][0]['error'];
                    $this->logger->log('Remote data error detail: ' . $error_detail, 'notice');
                }
              }
            }

            $data_str = print_r($data, true);
            $data_str = str_replace(array("\r", "\n"), '', $data_str);  // Remove new lines
            $this->logger->log('Remote data received (Code: ' . $response_code . ', Message: ' . $response_message . '): ' . $data_str, 'notice'); 
          } else {
            $redirect_url = wp_remote_retrieve_header( $data, 'Location' );
          }
      } else {
          $data_str = var_export($data, true);
          $data_str = str_replace(array("\r", "\n"), '', $data_str);  // Remove new lines
          $this->logger->log('Remote data received (object): ' . $data_str, 'notice');
      }

      if (isset($redirect_url)) {
        $response = json_encode(array('redirect'=>$redirect_url, 'error'=>$error_msg));
        exit($response);
      } else {
        $error_msg = 'Could not perform this checkout operation. Please try again.';
        wp_die($error_msg);
      }
    }
      
    public function headersToArray( $str )
    {
      $headers = array();
      $headersTmpArray = explode( "\r\n" , $str );
      for ( $i = 0 ; $i < count( $headersTmpArray ) ; ++$i )
      {
        // we dont care about the two \r\n lines at the end of the headers
        if ( strlen( $headersTmpArray[$i] ) > 0 )
        {
          // the headers start with HTTP status codes, which do not contain a colon so we can filter them out too
          if ( strpos( $headersTmpArray[$i] , ":" ) )
          {
            $headerName = substr( $headersTmpArray[$i] , 0 , strpos( $headersTmpArray[$i] , ":" ) );
            $headerValue = substr( $headersTmpArray[$i] , strpos( $headersTmpArray[$i] , ":" )+1 );
            $headers[$headerName] = $headerValue;
          }
        }
      }
      return $headers;
    }

    /**
     * Return full URL of the 'viabill' endpoint.
     *
     * @return string
     */
    public function get_checkout_authorize_url($payment_method_id = null) {
      if (empty($payment_method_id)) $payment_method_id = VIABILL_MONTHLY_PAYMENT_METHOD_ID;
      return WC()->api_request_url( $payment_method_id . '_checkout_authorize');
    }

  }
}
