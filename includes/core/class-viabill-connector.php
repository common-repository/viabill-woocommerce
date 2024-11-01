<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Connector' ) ) {
  /**
   * Viabill_Connector class
   *
   * @since 0.1
   */
  class Viabill_Connector {

    /**
     * Contains base URLs for the API.
     * Test URL: https://secure-test.viabill.com
     *
     * @var array
     */
    private $api_url = 'https://secure.viabill.com';

    /**
     * Base API path for this plugin.
     *
     * @var string
     */
    private $api_path = '/api/addon/woocommerce/';

    /**
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

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
     * Class constructor.
     */
    public function __construct() {
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );

      $this->settings     = Viabill_Main::get_gateway_settings();
      $this->logger = new Viabill_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );

      $this->captured_status = (isset($this->settings['order_status_after_captured_payment']))?$this->settings['order_status_after_captured_payment']:'processing';
      $this->approved_status = (isset($this->settings['order_status_after_authorized_payment']))?$this->settings['order_status_after_authorized_payment']:'on-hold';
    }

    /**
     * Return root API url.
     *
     * @return string
     */
    public function get_root_url() {
      return $this->api_url . $this->api_path;
    }

    public function get_checkout_url() {
      return $this->api_url . '/api/checkout-authorize/addon/woocommerce';
    }

    /**
     * Returns true, if it's a Viabill payment method (pay in 30 days or monthly payments)
     * 
     * @param string $payment_method_name
     * @return bool
     */
    public function is_viabill_payment($payment_method_name) {
      if (($payment_method_name == 'viabill_official')||
           ($payment_method_name == 'viabill_try')) {
              return true;
      }

      return false;    
    }

    /**
     * Format amount.
     *
     * @param  float $amount
     * @return string        Formated amount to string with 2 decimal places.
     */
    public function format_amount( $amount ) {
      return wc_format_decimal( $amount, 2 );
    }

    /**
     * Return 'My ViaBill' URL from the database or, if none, from the API.
     * If failed return empty string.
     *
     * @return string
     */
    public function get_my_viabill_url() {
      $url = get_transient( 'my_viabill_token_url' );

      if ( ! empty( $url ) && is_string( $url ) ) {
        try {
          $decrypted_url = openssl_decrypt( $url, 'AES-128-ECB', 'viabill!"#?123"' );
          if ( ! $decrypted_url ) {
            $this->logger->log( 'Failed to decrypt ViaBill\'s token URL.', 'error' );
            return '';
          }
        } catch ( Exception $e ) {
          $this->logger->log( 'Failed to decrypt ViaBill\'s token URL. Exception message: ' . $e->getMessage(), 'error' );
          return '';
        }
      }

      $params   = $this->get_key_signature_get_params();
      $response = wp_remote_get( $this->get_root_url() . 'myviabill?' . $params );
      $body     = $this->get_response_body( $response, 'myviabill?' . $params );

      if ( is_array( $body ) && isset( $body['url'] ) ) {
        try {
          $encrypted_url = openssl_encrypt( $body['url'], 'AES-128-ECB', 'viabill!"#?123"' );
          if ( $encrypted_url ) {
            set_transient( 'my_viabill_token_url', $encrypted_url, 60 * 60 * 24 );
          } else {
            $this->logger->log( 'Failed to encrypt received ViaBill\'s token URL.', 'error' );
          }
        } catch ( Exception $e ) {
          $this->logger->log( 'Failed to encrypt received ViaBill\'s token URL. Exception message: ' . $e->getMessage(), 'error' );
        }
        return $body['url'];
      }

      return '';
    }

    /**
     * Return notifications from the ViaBill.
     *
     * @return array
     */
    public function get_notifications() {
      $params   = $this->get_key_signature_get_params().$this->get_platform_info_get_params();
      $response = wp_remote_get( $this->get_root_url() . 'notifications?' . $params );
      return $this->get_response_body( $response, 'notifications?' . $params, array() );
    }

    /**
     * Get key and signature formatted as a http GET parameters.
     *
     * @return string
     */
    private function get_key_signature_get_params() {
      $key       = get_option( 'viabill_key', '' );
      $signature = $this->get_signature( $key );
      return 'key=' . $key . '&signature=' . $signature;
    }

    /**
     * Get platform info used with the notifications call.
     *
     * @return string
     */
    private function get_platform_info_get_params() {      
      $valid = true;
      $plugin_version = '';      
      if (defined('VIABILL_PLUGIN_VERSION')) {
        $plugin_version = VIABILL_PLUGIN_VERSION;
      } else {
        $valid = false;
      }
      $woocommerce_version = '';
      if (defined('WC_VERSION')) {
        $woocommerce_version = WC_VERSION;
      } else {
        $valid = false;
      }
      
      if ($valid) {
        return '&platform=woocommerce&platform_ver=' . $woocommerce_version . '&module_ver=' . $plugin_version;
      }
      return '';      
    }

    /**
     * Return available countries or empty array in case of failure.
     *
     * @return array
     */
    public function get_available_countries() {
      $response = wp_remote_get( $this->get_root_url() . 'countries/supported' );
      return $this->get_response_body( $response, 'countries/supported', array() );
    }

    /**
     * Register merchant, it should return an array with following structure:
     * {
     *  'key'            => string,
     *  'secret'         => string,
     *  'pricetagScript' => string
     * }
     * or false in case of failure.
     *
     * @param  string $email
     * @param  string $country
     * @param  array  $additional_data Defaults to empty array.
     * @return array|false
     */
    public function register( $email, $name, $country, $tax_id, $additional_data = array() ) {

      if (!empty($tax_id)) {
        $request_body = array(
          'email'          => $email,
          'name'           => $name, 
          'country'        => $country,
          'taxId'          => $tax_id,
          'url'            => get_site_url(),        
          'additionalInfo' => $additional_data,
          'affiliate'      => 'WOOCOMMERCE',
        );
      } else {
        $request_body = array(
          'email'          => $email,
          'name'           => $name, 
          'country'        => $country,
          'url'            => get_site_url(),        
          'additionalInfo' => $additional_data,
          'affiliate'      => 'WOOCOMMERCE',
        );
      }
            
      $args = $this->get_default_args( $request_body );
      if ( ! $args ) {
        $this->logger->log( 'Failed to parse arguments for /register API request.', 'critical' );
        return false;
      }

      $response = wp_remote_post( $this->get_root_url() . 'register', $args );
      return $this->get_response_body( $response, '/register' );
    }    

    /**
     * Capture given amount for a given order. Return array should have structure:
     * {
     *  'success' => bool,
     *  'message' => string
     * }
     * Message could be empty string if success is equal to true.
     * If failed to capture, method calls itself with $use_deprecated_id set to true
     * to try to capture order with old version of transaction ID.
     *
     * @param  WC_Order $order             WC_Order object.
     * @param  float    $amount_to_capture Add an amount to do a partial capture, default will capture the whole order total.
     * @param  boolean  $use_deprecated_id Defaults to false.
     * @return array                       [ 'success' => bool, 'message' => string ]
     */
    public function capture( $order, $amount_to_capture = 0, $use_deprecated_id = false ) {

      if (!$this->is_viabill_payment($order->get_payment_method())) {
        $this->logger->log( 'Request to capture order ' . $order->get_id() . ' failed, wrong payment method', 'notice' );
        return array(
          'success' => false,
          'message' => __( 'Payment method is not ViaBill.', 'viabill' ),
        );
      }

      $amount_captured  = $order->get_meta( 'viabill_captured_amount', true );
      $amount_captured  = empty( $amount_captured ) ? 0 : floatval( $amount_captured );
      $available_amount = floatval( $order->get_total() ) - $amount_captured;

      if ( $amount_to_capture > 0 ) {
        if ( round( $amount_to_capture, 2 ) > round( $available_amount, 2 ) ) {
          $this->logger->log( 'Tried to capture order with ID ' . $order->get_id() . ' where available amount is greater than the remaining amount available to capture.', 'notice' );
          return array(
            'success' => false,
            'message' => __( 'Tried to capture order where available amount is greater than the remaining amount available to capture.', 'viabill' ),
          );
        } else {
          $amount = $amount_to_capture;
        }
      } else {
        $amount = $available_amount;
      }

      if ( $amount <= 0 ) {
        $this->logger->log( 'Tried to capture order ' . $order->get_id() . ' where available amount is less or equal to 0.', 'notice' );
        return array(
          'success' => false,
          'message' => __( 'Tried to capture order where available amount is less or equal to 0.', 'viabill' ),
        );
      }

      $body = array(
        'id'       => $this->get_unique_transaction_id( $order, $use_deprecated_id ),
        'apikey'   => get_option( 'viabill_key', '' ),
        'amount'   => $this->format_amount( -$amount ),
        'currency' => $order->get_currency(),
      );

      $body['signature'] = md5( $body['id'] . '#' . $body['apikey'] . '#' . $body['amount'] . '#' . $body['currency'] . '#' . get_option( 'viabill_secret', '' ) );

      $args = $this->get_default_args( $body );
      if ( ! $args ) {
        $this->logger->log( 'Failed to parse arguments for api/transaction/capture API request.', 'critical' );
        return array(
          'success' => false,
          'message' => __( 'Failed to prepare arguments for API request', 'viabill' ),
        );
      }

      $response = wp_remote_post( $this->api_url . '/api/transaction/capture', $args );
      $this->logger->log( 'Request to capture (' . $amount . ' ' . $order->get_currency() . ') via ViaBill payment gateway sent for order ' . $order->get_id(), 'notice' );

      $status_code = $this->get_response_status_code( $response );
      $is_success  = $status_code && ( $status_code >= 200 && $status_code < 300 );

      // Call itself again but with deprecated id.
      if ( ! $is_success && ! $use_deprecated_id ) {
        return $this->capture( $order, $amount_to_capture, true );
      }

      $response_body = $this->get_response_body( $response, '/api/transaction/capture', '' );

      if ( $is_success ) {
        $this->logger->log( 'Successfully captured (' . $amount . ' ' . $order->get_currency() . ') via ViaBill payment gateway for order ' . $order->get_id(), 'notice' );
        $note = sprintf( __( 'Captured %s via ViaBill payment gateway', 'viabill' ), wc_price( $amount ) );

        $amount_captured += $amount;
        $order->add_meta_data( 'viabill_captured_amount', $amount_captured, true );

        if ( round( $amount_captured, 2 ) === round( $order->get_total(), 2 ) ) {
          // Using set_status instead of update_status to delay order status change so we can avoid capturing on status change.
          if ( $this->captured_status === $order->get_status() ) {
            $order->add_order_note( $note );
          } else {
            $order->set_status( $this->captured_status, $note );
          }
          $order->update_meta_data( 'viabill_status', 'captured' );
        } else {
          $order->add_order_note( $note );
          $order->update_meta_data( 'viabill_status', 'captured_partially' );
        }
      } else {
        $order->add_order_note( sprintf( __( 'Something went wrong while trying to capture %s', 'viabill' ), wc_price( $amount ) ) );

        if ( isset( $response_body['errors'] ) && is_array( $response_body['errors'] ) ) {
          foreach ( $response_body['errors'] as $error_data ) {
            $this->logger->log( $error_data['error'], 'error' );
          }
        }
        $this->logger->log( 'Failed to capture (' . $amount . ' ' . $order->get_currency() . ') via ViaBill payment gateway for order ' . $order->get_id(), 'error' );
        $response_body = __( 'Failed to process capture.', 'viabill' );
      }

      $order->save();

      return array(
        'success' => $is_success,
        'message' => $response_body,
      );
    }

    /**
     * Trigger refund for given order and amount.
     * If failed to refund, method calls itself with $use_deprecated_id set to true
     * to try to refund order with old version of transaction ID.
     *
     * @param  WC_Order $order
     * @param  float    $amount
     * @param  string   $currency
     * @param  boolean  $use_deprecated_id Defaults to false.
     * @return array                    [ 'success' => bool, 'message' => string ]
     */
    public function refund( $order, $amount, $currency, $use_deprecated_id = false ) {
      if (!$this->is_viabill_payment($order->get_payment_method())) {
        return;
      }

      $body = array(
        'id'       => $this->get_unique_transaction_id( $order, $use_deprecated_id ),
        'apikey'   => get_option( 'viabill_key', '' ),
        'amount'   => $this->format_amount( $amount ),
        'currency' => $currency,
      );

      $body['signature'] = md5( $body['id'] . '#' . $body['apikey'] . '#' . $body['amount'] . '#' . $body['currency'] . '#' . get_option( 'viabill_secret', '' ) );

      $args     = $this->get_default_args( $body );
      $response = wp_remote_post( $this->api_url . '/api/transaction/refund', $args );
      $this->logger->log( 'Request to refund (' . $amount . ' ' . $order->get_currency() . ') via ViaBill payment gateway sent for order ' . $order->get_id(), 'notice' );

      $status_code = $this->get_response_status_code( $response );
      $is_success  = $status_code && ( $status_code >= 200 && $status_code < 300 );
      $wc_price    = wc_price( $amount, array( 'currency' => $order->get_currency() ) );

      if ( $is_success ) {
        $note = sprintf( __( 'Successfully refunded %s via ViaBill payment gateway', 'viabill' ), $wc_price );
        $this->logger->log( 'Successfully refunded (' . $amount . ' ' . $order->get_currency() . ') via ViaBill payment gateway for order ' . $order->get_id(), 'notice' );
        $order->add_order_note( $note );
        $order->update_meta_data( 'viabill_status', (int) $order->get_remaining_refund_amount() ? 'refunded_partially' : 'refunded' );
        $order->save();

        return true;
      }

      if ( ! $use_deprecated_id ) {
        return $this->refund( $order, $amount, $currency, true );
      } else {
        $note = sprintf( __( 'Failed to refund %s via ViaBill payment gateway', 'viabill' ), $wc_price );
        $this->logger->log( 'Failed to refund (' . $amount . ' ' . $order->get_currency() . ') via ViaBill payment gateway for order ' . $order->get_id(), 'error' );
        $order->add_order_note( $note );
        $response_body = $this->get_response_body( $response, '/api/transaction/refund', '' );

        return false;
      }
    }

    /**
     * Cancel given order. Return array should have structure:
     * {
     *  'success' => bool,
     *  'message' => string
     * }
     * Message could be empty string if success is equal to true.
     * If fails to cancel, method calls itself with $use_deprecated_id set to true
     * to cancel order with old verion of transaction ID.
     *
     * @param  WC_Order $order
     * @param  boolean  $use_deprecated_id Defaults to false.
     * @return array
     */
    public function cancel( $order, $use_deprecated_id = false ) {
      if (!$this->is_viabill_payment($order->get_payment_method())) {
        $this->logger->log( 'Request to cancel order ' . $order->get_id() . ' failed, wrong payment method', 'notice' );
        return array(
          'success' => false,
          'message' => __( 'Payment method is not ViaBill.', 'viabill' ),
        );
      }

      $body = array(
        'id'     => $this->get_unique_transaction_id( $order, $use_deprecated_id ),
        'apikey' => get_option( 'viabill_key', '' ),
      );

      $secret = get_option( 'viabill_secret', '' );

      $body['signature'] = md5( $body['id'] . '#' . $body['apikey'] . '#' . $secret );

      $args = $this->get_default_args( $body );

      if ( ! $args ) {
        $this->logger->log( 'Failed to parse arguments for api/transaction/cancel API request.', 'critical' );
        return array(
          'success' => false,
          'message' => __( 'Failed to prepare arguments for API request', 'viabill' ),
        );
      }

      $response = wp_remote_post( $this->api_url . '/api/transaction/cancel', $args );
      $this->logger->log( 'Request to cancel order ' . $order->get_id() . ' via ViaBill payment gateway sent.', 'notice' );

      $status_code = $this->get_response_status_code( $response );
      $is_success  = $status_code && ( $status_code >= 200 && $status_code < 300 );

      if ( ! $is_success && ! $use_deprecated_id ) {
        return $this->cancel( $order, true );
      }

      $response_body = $this->get_response_body( $response, '/api/transaction/cancel', '' );

      return array(
        'success' => $is_success,
        'message' => $response_body,
      );
    }

    /**
     * Return ViaBill status for given order.
     * If fails, call itself with $use_deprecated_id set to true to try to get
     * the status with old transaction ID.
     *
     * @param  WC_Order $order
     * @param  boolean $use_deprecated_id Defaults to false.
     * @return array|false
     */
    public function get_status( $order, $use_deprecated_id = false ) {
      $secret         = get_option( 'viabill_secret', '' );
      $api_key        = get_option( 'viabill_key', '' );
      $transaction_id = $this->get_unique_transaction_id( $order, $use_deprecated_id );
      $signature      = md5( $transaction_id . '#' . $api_key . '#' . $secret );

      $params = '?id=' . $transaction_id . '&apikey=' . $api_key . '&signature=' . $signature;

      $response = wp_remote_get( $this->api_url . '/api/transaction/status' . $params );

      if ( is_wp_error( $response ) ) {
        $this->logger->log( 'Failed to fetch status for order ' . $order->get_id() . '. Error message: ' . $response->get_error_message(), 'error' );
        return null;
      }

      $status_code = $response['response']['code'];
      $is_success  = $status_code && ( $status_code >= 200 && $status_code < 300 );
      if ( ! $is_success && ! $use_deprecated_id ) {
        return $this->get_status( $order, true );
      }

      $status = $this->get_response_body( $response, '/api/transaction/status' );

      return $status['state'];
    }

    /**
     * Return an array for default args or false if failed to JSON encode.
     *
     * @param  array  $body
     * @return array|false
     */
    private function get_default_args( $body ) {
      $encoded_body = wp_json_encode( $body );
      if ( ! $encoded_body ) {
        return false;
      }

      return array(
        'body'    => $encoded_body,
        'headers' => array(
          'Content-Type' => 'application/json',
        ),
      );
    }

    /**
     * Return plugin's unique transaction ID. If $use_deprecated_id set to true,
     * return old transaction ID used in version prior to 1.0.5.
     *
     * @param  WC_Order $order
     * @param  boolean  $use_deprecated_id Defaults to false.
     * @return string
     */
    public function get_unique_transaction_id( $order, $use_deprecated_id = false ) {
      if ( $use_deprecated_id ) {
        $transaction_id = $order->get_order_key();
      } else {
        $transaction_id = 'OrdNum_'. trim( str_replace( '#', '', $order->get_order_number() ) );
      }
    
      return $transaction_id;
    }

    public function get_order_number($order, $use_deprecated_id = false ) {
      if ( $use_deprecated_id ) { 
        $order_id = $order->get_order_key();
      } else {
        $order_id = $order->get_id();
      }
      
      return $order_id;      
    }

    /**
     * Log in merchant and return an array with following structure:
     * {
     *  'key'            => string,
     *  'secret'         => string,
     *  'pricetagScript' => string
     * }
     * or false in case of failure.
     *
     * @param  string $email
     * @param  string $password
     * @return array|false
     */
    public function login( $email, $password ) {
      $body = array(
        'email'    => $email,
        'password' => $password,
      );
      $args = array(
        'body'    => wp_json_encode( $body ),
        'headers' => array(
          'Content-Type' => 'application/json',
        ),
      );

      $response = wp_remote_post( $this->get_root_url() . 'login', $args );
      return $this->get_response_body( $response, '/login' );
    }

    /**
     * Return error messages from the response body array or false if none.
     *
     * @param  array $data
     * @return string|bool
     */
    public function get_error_messages( $data ) {
      if ( isset( $data['errors'] ) && is_array( $data['errors'] ) ) {
        if ( ! empty( $data['errors'] ) && isset( $data['errors'][0]['error'] ) ) {
          return $data['errors'][0]['error'];
        } else {
          return __( 'Something went wrong, please try again.', 'viabill' );
        }
      }

      return false;
    }

    /**
     * Return signature created from the provided key and secret or false in the
     * case of failure. If not provided, method will try to get them from
     * the database.
     *
     * @param  string $key
     * @param  string $secret
     * @return string|bool
     */
    private function get_signature( $key = '', $secret = '' ) {
      $key    = empty( $key ) ? get_option( 'viabill_key' ) : $key;
      $secret = empty( $secret ) ? get_option( 'viabill_secret' ) : $secret;

      if ( $key && is_string( $key ) && $secret && is_string( $secret ) ) {
        return md5( $key . '#' . $secret );
      } else {
        $this->logger->log( 'Failed to generate signature.', 'critical' );
        return false;
      }
    }

    /**
     * Return JSON decoded response body or $default in case of failure.
     *
     * @param  array  $response
     * @param  string $endpoint         Defaults to empty string.
     * @param  mixed  $default_response Fallback return, defaults to false.
     * @return string|array|bool
     */
    private function get_response_body( $response, $endpoint = '', $default_response = false ) {
      if ( is_wp_error( $response ) ) {
        if ( $response->has_errors() ) {
          $message = $response->get_error_message();
          $this->logger->log( 'Failed to get response for ' . $endpoint . ' with an error message: \'' . $message . '\'', 'critical' );
        } else {
          $this->logger->log( 'Failed to get response for ' . $endpoint, 'critical' );
        }
        return $default_response;
      }

      if ( is_array( $response ) && ! empty( $response['body'] ) ) {
        $decoded_body = json_decode( $response['body'], true );
        if ( is_null( $decoded_body ) ) {
          $this->logger->log( 'Failed to JSON decode response body.', 'warning' );
          return $default_response;
        }
        $this->logger->log( 'Request to '.$endpoint.' returned a valid response body', 'notice');
        return $decoded_body;
      }

      return $default_response;
    }

    /**
     * Return response status or false in case of failure.
     *
     * @param  array $response
     * @return int|bool
     */
    private function get_response_status_code( $response ) {
      if ( is_wp_error( $response ) || ! is_array( $response ) ) {
        return false;
      }

      if ( ! isset( $response['response'] ) || ! is_array( $response['response'] ) ) {
        $this->logger->log( 'Failed to get status code, missing \'response\'.', 'warning' );
        return false;
      }

      if ( ! isset( $response['response']['code'] ) ) {
        $this->logger->log( 'Failed to get status code, missing \'code\'.', 'warning' );
        return false;
      }

      return (int) $response['response']['code'];
    }

  }
}
