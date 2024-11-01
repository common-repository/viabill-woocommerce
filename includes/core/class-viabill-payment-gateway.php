<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
  return;
}

if (! defined ('VIABILL_TRY_PAYMENT_METHOD_ID')) {
  define('VIABILL_TRY_PAYMENT_METHOD_ID', 'viabill_try');
  define('VIABILL_MONTHLY_PAYMENT_METHOD_ID', 'viabill_official'); // same as: VIABILL_PLUGIN_ID
  
  // Hide "try before you buy" payment option in backend settings  
  define('TRY_BEFORE_YOU_BUY_SHOW_SETTING_OPTION', true);
}

function get_gateway_icon( $string, $arg1 = null, $arg2 = null) {
  $logo = 'viabill_logo_primary.png';

  $locale   = get_locale();
  $language = null;

  if ( strpos( $locale, '_' ) !== false ) {
    $locale_parts = explode( '_', $locale );
    $language     = strtolower($locale_parts[0]);
  } elseif ( strlen( $locale ) === 2 ) {
    $language = strtolower($locale);
  }
  
  switch ($language) {
     case 'da':
     case 'es':
     case 'en':
        $logo = 'ViaBill_Logo_'.$language.'.png';
        break;
  }

  $icon = '<img class="viabill_logo" style="height: 1em; width: auto; margin-left: 7px; float: none;" src="' . esc_url( plugins_url( '/assets/img/' . $logo, dirname( __FILE__ ) . '/../../../'  ) ) . '" alt="' . esc_attr( 'Pay with Viabill' ). '" />';
  return $icon;
}

add_filter( 'viabill_gateway_checkout_icon', 'get_gateway_icon', 10, 3 );

function get_try_gateway_icon( $string, $arg1 = null, $arg2 = null) {
  $logo = 'viabill_try_logo_primary.png';

  $locale   = get_locale();
  $language = null;

  if ( strpos( $locale, '_' ) !== false ) {
    $locale_parts = explode( '_', $locale );
    $language     = strtolower($locale_parts[0]);
  } elseif ( strlen( $locale ) === 2 ) {
    $language = strtolower($locale);
  }
  
  switch ($language) {
     case 'da':        
     case 'es':
     case 'en':
        $logo = 'ViaBill_Try_Logo_'.$language.'.png';
        break;
     default:
        $logo = 'ViaBill_Try_Logo_en.png';
        break;
  }

  $icon = '<img class="viabill_logo" style="height: 1em; width: auto; margin-left: 7px; float: none;" src="' . esc_url( plugins_url( '/assets/img/' . $logo, dirname( __FILE__ ) . '/../../../'  ) ) . '" alt="' . esc_attr( 'Pay with Viabill - Try before you Buy' ). '" />';
  return $icon;
}

add_filter( 'viabill_try_gateway_checkout_icon', 'get_try_gateway_icon', 10, 3 );

/**
 * Register payment gateway's class as a new method of payment.
 *
 * @param array $methods
 * @return array
 */
function viabill_add_gateway( $methods ) {  
  if (defined('TRY_BEFORE_YOU_BUY_SHOW_SETTING_OPTION')) {    
    $include_try_payment_method = TRY_BEFORE_YOU_BUY_SHOW_SETTING_OPTION;    
    if ($include_try_payment_method) {
      // check if the option is available in the specific country
      $country = wc_get_base_location()['country'];
      if (empty($country)) {
        $shop_location = get_option( 'woocommerce_default_country' ); 
        $shop_location = explode(':', $shop_location);
        $country  = reset($shop_location); 
      }
      if (!empty($country)) {
        $country = strtoupper($country);
        switch ($country) {
          case 'ES':
          case 'SP':
          case 'SPAIN':
            $include_try_payment_method = false;
            break;
        }
      }
    }
  }
  
  $methods[] = 'Viabill_Payment_Gateway';  
  if ($include_try_payment_method) {
    $methods[] = 'Viabill_Try_Payment_Gateway';
  }
  
  return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'viabill_add_gateway' );

// ========================================================================
// Monthly Payments class (default Viabill payment method)
// ========================================================================

if ( ! class_exists( 'Viabill_Payment_Gateway' ) ) {
  /**
   * Viabill_Payment_Gateway class
   */
  class Viabill_Payment_Gateway extends WC_Payment_Gateway {
    /**
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;

    /**
     * API's interface.
     *
     * @var Viabill_Connector
     */
    private $connector;

    /**
     * Merchant's profile, object which holds merchant's data.
     *
     * @var Viabill_Merchant_Profile
     */
    private $merchant;

    /**
     * Notices.
     *
     * @var Viabill_Notices
     */
    private $notices;

    /**
     * Support.
     *
     * @var Viabill_Support
     */
    private $support;

    /**
     * REST API.
     *
     * @var Viabill_API
     */
    private $api;

    /**
     * Availability
     * 
     * @var boolean
     */
    private $checkout_hide;

    private $woocommerce_currency_supported_wp_notice_raised = false;

    /**
     * Class constructor with basic gateway's setup.
     *
     * @param bool $init  Should the class attributes be initialized.
     */
    public function __construct() {
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-iso-code-converter.php' );

      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-merchant-profile.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-connector.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-notices.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-support.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-api.php' );

      $this->connector = new Viabill_Connector();
      $this->api       = new Viabill_API();
      
      $this->id           = VIABILL_MONTHLY_PAYMENT_METHOD_ID;
      $this->method_title = __( 'ViaBill - Easy Monthly Payments', 'viabill' );
      $this->has_fields   = true;      

      $this->init_form_fields();
      $this->init_settings();

      $this->supports = array( 'products', 'refunds' );
      $this->merchant = new Viabill_Merchant_Profile();
      $this->notices  = new Viabill_Notices();
      $this->support  = new Viabill_Support();

      $this->checkout_hide = $this->is_viabill_payment_hidden();
      if ($this->checkout_hide) {
        $this->enabled = false;
      }

      $this->logger = new Viabill_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );

      $this->title = esc_attr( isset( $this->settings['title'] ) ? $this->settings['title'] : '' );
      $this->add_actions();      
    }

    /**
     * Return true if current WooCommerce currency is supported by ViaBill, false
     * otherwise.
     *
     * @return bool
     */
    private function is_woocommerce_currency_supported() {
      $converter = new Viabill_ISO_Code_Converter();

      $this->supported_currencies = array();
      $supported_countries        = $this->connector->get_available_countries();

      foreach ( $supported_countries as $supported_country ) {
        $supported_country_code = $converter->get_currency_by_country( $supported_country['code'] );
        if ( ! empty( $supported_country_code ) ) {
          array_push( $this->supported_currencies, $supported_country_code );
        }
      }

      $current_currency = get_woocommerce_currency();
      return in_array( $current_currency, $this->supported_currencies, true );
    }

    /**
     * Disable payment gateway if current currency is unsupported.
     *
     * @return void
     */
    public function force_disable_if_currency_unsupported() {
      if ( 'yes' !== $this->settings['enabled'] ) {
        return;
      }

      if ( ! $this->is_woocommerce_currency_supported() ) {
        $this->logger->log( 'Payment gateway disabled because of the unsupported WooCommerce currency.', 'warning' );
        $this->settings['enabled'] = 'no';

        update_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings', $this->settings, true );
        add_action( 'admin_notices', array( $this, 'show_currency_unsupported_wp_notice' ) );
        $this->woocommerce_currency_supported_wp_notice_raised = true;
      }
    }

    /**
     * Display WordPress admin notice for unsupported currency.
     *
     * @return void
     */
    public function show_currency_unsupported_wp_notice() {
      if ( defined( 'RAISED_VIABILL_UNSUPPORTED_CURRENCY' ) ) {
        return;
      }

      $class   = 'notice notice-error';
      $message = __( 'Current WooCommerce currency is unsupported and ViaBill is disabled.', 'viabill' );

      $supported_message = '';
      if ( property_exists( $this, 'supported_currencies' ) ) {
        $supported_message  = ' ' . __( 'Please change to one of the next currencies', 'viabill' ) . ':';
        $supported_message .= '<ol>';

        foreach ( $this->supported_currencies as $supported_currency ) {
          $supported_message .= '<li>' . $supported_currency . '</li>';
        }
        $supported_message .= '</ol>';
      }

      if ( ! defined( 'RAISED_VIABILL_UNSUPPORTED_CURRENCY' ) ) {
        define( 'RAISED_VIABILL_UNSUPPORTED_CURRENCY', true );
      }

      printf( '<div class="%1$s"><p><b>%2$s</b>%3$s</p></div>', esc_attr( $class ), esc_html( $message ), $supported_message );
    }

    /**
     * Return true if payment gateway needs further setup. It basically checks if
     * WooCommerce current currency is supported and if not, it returns false.
     *
     * @override
     * @return bool
     */
    public function needs_setup() {
      return ! $this->is_woocommerce_currency_supported();
    }

    /**
     * Register different actions.
     */
    private function add_actions() {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'do_receipt_page' ) );
      add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'do_order_confirmation' ) );
      add_filter( 'pre_update_option_' . $this->get_option_key(), array( $this, 'filter_settings_values' ) );
      add_filter( 'woocommerce_settings_api_form_fields_' . $this->id, array( $this, 'filter_settings_fields' ) );

      // check if currency is changed!
      add_action(
        'woocommerce_settings_saved',
        function() {
          global $current_tab;
          global $current_section;

          if ( 'general' === $current_tab ) {
            $this->force_disable_if_currency_unsupported();
          } elseif ( 'checkout' === $current_tab && VIABILL_PLUGIN_ID === $current_section ) {
            $this->force_disable_if_currency_unsupported();
          }
        },
        10
      );
    }

    /**
     * Process refund via ViaBill API.
     *
     * @override
     * @param  int    $order_id
     * @param  float  $amount   Defaults to null.
     * @param  string $reason   Defaults to empty string.
     * @return bool             True or false based on success, or a WP_Error object.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while processing refund.', 'warning' );
        return false;
      }

      return $this->connector->refund( $order, $amount, $order->get_currency() );
    }

    /**
     * Trigger 'viabill_gateway_checkout_icon' hook.
     *
     * @override
     */
    public function get_icon() {
      if ( isset( $this->settings['show_title_as_label'] ) && ! empty( $this->settings['show_title_as_label'] ) ) {
        $display_option = $this->settings['show_title_as_label'];
        if ($display_option == 'show_label_only') {
          return '';
        }
      }

      $icon = (string) apply_filters( 'viabill_gateway_checkout_icon', 0 );

      if ( ! empty( $icon ) ) {
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
      }

      return '';
    }


    /**
    * Override the default payment method title and visually hide it
    *
    * @override
    */
    public function get_title() {
      if (is_admin()) {
        return parent::get_title();
      } else {
        if ( isset( $this->settings['show_title_as_label'] ) && ! empty( $this->settings['show_title_as_label'] ) ) {
          $display_option = $this->settings['show_title_as_label'];
          if (($display_option == 'show_label_icon') || ($display_option == 'show_label_only')) {
            return parent::get_title();
          }
        }

        return '';
      }      
    }

    /**
     * Define gateway's fields visible at WooCommerce's Settings page and
     * Checkout tab.
     *
     * @override
     */
    public function init_form_fields() {
      $this->form_fields = include( VIABILL_DIR_PATH . '/includes/utilities/viabill-settings-fields.php' );
    }

    /**
     * Echoes gateway's options (Checkout tab under WooCommerce's settings).
     *
     * @override
     */
    public function admin_options() {
      $notif_count = $this->notices->get_unseen_count();

      if (isset($_GET['viabill_success_message'])) {
          // Sanitize the message
          $message = sanitize_text_field(urldecode($_GET['viabill_success_message']));

          // Display the message
          echo '<div class="notice notice-success is-dismissible">
                  <p>' . esc_html($message) . '</p>
                </div>';
      }

      ?>

      <h2><?php esc_html_e( 'ViaBill Payment Gateway', 'viabill' ); ?></h2>
      <?php if ( Viabill_Main::is_merchant_registered() ) : ?>
        <table class="form-table">
          <caption style="text-align:left;">
            <a href="" class="button-secondary viabill-dashboard-link" target="_blank"><?php esc_html_e( 'My ViaBill', 'viabill' ); ?></a>
            <a class="button-secondary" href="<?php echo Viabill_Notices::get_admin_url(); ?>">
              <?php esc_html_e( 'Notifications', 'viabill' ); ?>
              <?php echo $notif_count ? '(' . $notif_count . ')' : null; ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=viabill_pricetag' ); ?>" class="button-secondary viabill-pricetags-link">
              <?php esc_html_e( 'ViaBill PriceTags', 'viabill' ); ?>
            </a>
            <a href="<?php echo Viabill_Support::get_admin_url(); ?>" class="button-secondary viabill-support-link">
              <?php esc_html_e( 'Support', 'viabill' ); ?>
            </a>
          </caption>

          <?php $this->generate_settings_html(); ?>
        </table>
      <?php else : ?>
        <?php require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-registration.php' ); ?>
        <b>
          <?php esc_html_e( 'You\'re not signed in.', 'viabill' ); ?>
          <?php esc_html_e( 'Please', 'viabill' ); ?>
          <a href="<?php echo Viabill_Registration::get_admin_url(); ?>"><?php esc_html_e( 'login or register', 'viabill' ); ?></a>.
        </b>
        <style media="screen">
          p.submit { display: none; }
        </style>
      <?php endif; ?>
      <?php
    }

    /**
     * Display description of the gateway on the checkout page.
     *
     * @override
     */
    public function payment_fields() {
      if ( isset( $this->settings['description-msg'] ) && ! empty( $this->settings['description-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['description-msg'] ) . '</p>';
      }

      if ( 'yes' === $this->settings['in-test-mode'] ) {
        $test_mode_notice = '<p><b>' . __( 'ViaBill is currently in sandbox/test mode, disable it for live web shops.', 'viabill' ) . '</b></p>';
        $test_mode_notice = apply_filters( 'viabill_payment_description_test_mode_notice', $test_mode_notice );

        if ( ! empty( $test_mode_notice ) ) {
          echo $test_mode_notice;
        }
      }

      // do_action( 'viabill_pricetag_after_payment_description' );
      do_action( 'viabill_pricetag_after_monthly_payment_description' );
    }

    /**
     * Echo confirmation message on the 'thank you' page.
     */
    public function do_order_confirmation( $order_id ) {
      $order = wc_get_order( $order_id );

      if ( 'pending' === $order->get_meta( 'viabill_status' ) ) {
        $order->update_meta_data( 'viabill_status', 'pending_approval' );
        $order->save();
      }

      if ( isset( $this->settings['confirmation-msg'] ) && ! empty( $this->settings['confirmation-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['confirmation-msg'] ) . '</p>';
      }
    }

    /**
     * Echo redirect message on the 'receipt' page.
     */
    private function show_receipt_message() {
      if ( isset( $this->settings['receipt-redirect-msg'] ) && ! empty( $this->settings['receipt-redirect-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['receipt-redirect-msg'] ) . '</p>';
      }
    }

    /**
     * Trigger actions for 'receipt' page.
     *
     * @param int $order_id
     */
    public function do_receipt_page( $order_id ) {
      static $executed = false;

      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while trying to show receipt page.', 'warning' );
        return false;
      }

      if ( ! $order->get_meta( 'viabill_status' ) ) {
        $order->add_meta_data( 'viabill_status', 'pending', true );
        $order->save();
      }

      $transaction = $this->connector->get_unique_transaction_id( $order );      
      $order_amount = number_format($order->get_total(), 2, '.', '');

      $success_url = $order->get_checkout_order_received_url();
      $cancel_url = $order->get_cancel_order_url_raw();
      $callback_url = $this->api->get_checkout_status_url($this->id);

      // sanity check
      if (strpos($success_url, 'http') !== 0) {
          // Prepend the base URL using WordPress function to get site URL
          $success_url = site_url($success_url);
      }
      if (strpos($cancel_url, 'http') !== 0) {
          // Prepend the base URL using WordPress function to get site URL
          $cancel_url = site_url($cancel_url);
      }
      if (strpos($callback_url, 'http') !== 0) {
          // Prepend the base URL using WordPress function to get site URL
          $callback_url = site_url($callback_url);
      }

      $md5check    = md5( $this->merchant->get_key() . '#' . $order_amount . '#' . $order->get_currency() . '#' . $transaction . '#' . $order->get_id() . '#' . $success_url . '#' . $cancel_url . '#' . $this->merchant->get_secret() );

      $is_test_mode = 'yes' === $this->settings['in-test-mode'];
      if ( $is_test_mode && 'yes' !== $order->get_meta( 'in_test_mode' ) ) {
        $order->add_order_note( __( 'Order was done in <b>test mode</b>.', 'viabill' ) );
        $order->add_meta_data( 'in_test_mode', 'yes', true );
        $order->save();
      }

      $customer_info = $this->get_customer_info($order);
      $customer_info_json = (empty($customer_info))?'':json_encode($customer_info);

      $cart_info = $this->get_cart_info($order);
      $cart_info_json = (empty($cart_info))?'':json_encode($cart_info);

      $tbyb = $this->get_tbyb_flag($this->id);
      
      $debug_info = array(
        'apikey' => $this->merchant->get_key(),
        'transaction' => $transaction,
        'order_number' => $order->get_id(),
        'amount' => $order_amount,
        'currency' => $order->get_currency(),
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,        
        'callback_url' => $callback_url,
        'test' => $is_test_mode ? 'true' : 'false',
        'customParams' => $customer_info_json,
        'cartParams' => $cart_info_json,
        'md5check' => $md5check,
        'tbyb' => $tbyb
      );
      $debug_info_str = print_r($debug_info, true);
      $this->logger->log( $debug_info_str, 'notice');

      if (!$executed) {      

        $form_url = $this->api->get_checkout_authorize_url($this->id);        

        ?>      
        <form id="viabill-payment-form" action="<?php echo esc_url($this->connector->get_checkout_url()); ?>" method="post">
          <input type="hidden" name="protocol" value="3.0">
          <input type="hidden" name="apikey" value="<?php echo esc_attr($this->merchant->get_key()); ?>">
          <input type="hidden" name="transaction" value="<?php echo esc_attr($transaction); ?>">
          <input type="hidden" name="order_number" value="<?php echo esc_attr($order->get_id()); ?>">
          <input type="hidden" name="amount" value="<?php echo esc_attr($order_amount); ?>">
          <input type="hidden" name="currency" value="<?php echo esc_attr($order->get_currency()); ?>">
          <input type="hidden" name="success_url" value="<?php echo esc_url($success_url); ?>">
          <input type="hidden" name="cancel_url" value="<?php echo esc_url($cancel_url); ?>">
          <input type="hidden" name="callback_url" value="<?php echo esc_url($callback_url); ?>">
          <input type="hidden" name="test" value="<?php echo $is_test_mode ? 'true' : 'false'; ?>">
          <input type="hidden" name="customParams" value="<?php echo htmlspecialchars($customer_info_json, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="cartParams" value="<?php echo htmlspecialchars($cart_info_json, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="md5check" value="<?php echo esc_attr($md5check); ?>">
          <input type="hidden" name="tbyb" value="<?php echo $tbyb ? '1' : '0'; ?>">
        </form>                      

        <input type="button" id="viabill-payment-form-submit" value="Submit" onclick="postViabillPaymentForm()" />
        <?php

        $inline_script = "
          window.postViabillPaymentForm = function() {          
            var formData = jQuery('#viabill-payment-form').serialize();
            jQuery.ajax({
              type: 'POST',
              url: '{$form_url}',
              data: formData,
              dataType: 'json',

              success: function(data, textStatus){
                if (data.redirect) {
                  window.location.href = data.redirect;
                } else {
                  console.log('No data redirect after posting ViaBill Payment Form');
                  console.log(data);
                }
              },
              error: function(errMsg) {
                console.log('Unable to post ViaBill Payment Form');
                console.log(errMsg);
              }
            }); 
          }      
          
          jQuery('#viabill-payment-form-submit').on('click', postViabillPaymentForm);
        ";
        
        // wp_add_inline_script( 'jquery', '<script>'.$inline_script.'</script>' );
        wc_enqueue_js($inline_script);

        if ($this->settings['auto-redirect'] === 'yes') {
          $this->enqueue_redirect_js();
        } else {
          $this->show_receipt_message();
        }

        $executed = true;
      }

      // 'yes' === $this->settings['auto-redirect'] ? $this->enqueue_redirect_js() : $this->show_receipt_message();      
    }

    /**
     * Enqueue JavaScript for redirecting to a ViaBill form.
     */
    private function enqueue_redirect_js() {
      // It's safe to use $ with WooCommerce.
      // If there's no redirect after 10 seconds, unblock the UI.      
      wc_enqueue_js( "if ($.fn.block) { $('.woocommerce').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6}}); setTimeout(function(){ $('.woocommerce').unblock(); }, 10000); }" );      
      //wc_enqueue_js( "$('#viabill-payment-form').submit();" );
      wc_enqueue_js( "postViabillPaymentForm();" );
    }

    /**
     * Redirect to given URL if possible.
     *
     * @param string $url
     */
    public function call_redirect( $url ) {
      if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
      }
      $this->logger->log( 'Headers already sent before redirecting to ' . $url . ' URL.', 'warning' );
    }

    /**
     * Process the payment and return the result.
     *
     * @override
     * @param string $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while trying to process payment.', 'critical' );
        return;
      }

      // Remove cart.
      WC()->cart->empty_cart();

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true ),
      );
    }

    /**
     *  Declare compatibility with HPOS   
     */
    public function custom_order_tables() {
      return true;
    }

    /**
     * Filter settings values before they are update in the database.
     *
     * @param  string $value  Option value.
     * @return string         Option value.
     */
    public function filter_settings_values( $value ) {
      // If auto-capture option is enabled than disable capture-order-on-status-switch.
      if ( isset( $value['auto-capture'] ) && 'yes' === $value['auto-capture'] ) {
        $value['capture-order-on-status-switch'] = 'no';
      }

      return $value;
    }

    /**
     * Filter settings fields.
     *
     * @param  array $fields Fields array.
     * @return array
     */
    public function filter_settings_fields( $fields ) {
      // If auto-capture option is enabled than disable capture-order-on-status-switch field.
      if ( isset( $this->settings['auto-capture'] ) && 'yes' === $this->settings['auto-capture'] ) {
        if ( isset( $fields['capture-order-on-status-switch'] ) ) {
          $fields['capture-order-on-status-switch']['disabled'] = true;
        }
      }

      return $fields;
    }

    /**
     * 
     */
    public function is_viabill_payment_hidden() {
      if ( isset( $this->settings['checkout-hide'] ) && 'yes' === $this->settings['checkout-hide'] ) {
         return true;
      }
      return false;
    }
   
    /**
     * Get information about the customer for the active order
     * 
     * @param WC_Order $order
     * 
     * @return array
     */ 
    public function get_customer_info($order) {      
      
      $info = [
        'email'=>'',
        'phoneNumber'=>'',
        'firstName'=>'',
        'lastName'=>'',
        'fullName'=>'',
        'address'=>'',
        'city'=>'',
        'postalCode'=>'',
        'country'=>''
      ];

      // sanity check
      if (empty($order)) return $info;           
      
      $info['email']  = $order->get_billing_email();
      $info['phoneNumber']  = $this->sanitizePhone($order->get_billing_phone(),
                                                 $order->get_billing_country());
      $info['firstName'] = $order->get_billing_first_name();
      $info['lastName']  = $order->get_billing_last_name();      
      $info['fullName'] = trim($info['firstName'].' '.$info['lastName']);
      $address = $order->get_billing_address_1();
      $address .= ' '.$order->get_billing_address_2();
      $info['address'] = trim($address);
      $info['city']       = $order->get_billing_city();      
      $info['postalCode']   = $order->get_billing_postcode();
      $info['country']    = $order->get_billing_country();

      return $info;
    }

    /**
     * Get information about the cart contents for the active order
     * 
     * @param WC_Order $order
     * 
     * @return array
     */ 
    public function get_cart_info($order) {                           
      // sanity check
      if (empty($order)) return null;

      try {

        $order_data = $order->get_data(); // The Order data
        $order_amount = number_format($order->get_total(), 2, '.', '');

        $shipping_same_as_billing = 'yes';
        $billing_fields = [
          'address' => trim($order_data['billing']['address_1'].' '.$order_data['billing']['address_2']),
          'city' => trim($order_data['billing']['city']),
          'state' => trim($order_data['billing']['state']),
          'postcode' => trim($order_data['billing']['postcode']),
          'country' => $order_data['billing']['country']        
        ];
        $shipping_fields = [
          'address' => trim($order_data['shipping']['address_1'].' '.$order_data['shipping']['address_2']),
          'city' => trim($order_data['shipping']['city']),
          'state' => trim($order_data['shipping']['state']),
          'postcode' => trim($order_data['shipping']['postcode']),
          'country' => trim($order_data['shipping']['country'])          
        ];
        foreach ($billing_fields as $billing_field => $billing_value ) {
          $shipping_value = $shipping_fields[$billing_field];
          if (!empty($shipping_value) && !empty($billing_value)) {
            if ($shipping_value != $billing_value) {
              $shipping_same_as_billing = 'no';
            }
          }
        }

        $info = [
          'date_created' => $order_data['date_created']->date('Y-m-d H:i:s'),
          'subtotal'=> $order->get_subtotal(),        
          'tax' => $order->get_total_tax(),
          'shipping'=>$order->get_shipping_total(),      
          'discount'=>$order->get_total_discount(),
          'total'=>$order_amount,
          'currency'=>$order->get_currency(),
          'quantity'=> $order->get_item_count(),
          'billing_email' => trim($order_data['billing']['email']),
          'billing_phone' => trim($order_data['billing']['phone']), 
          'shipping_city' => trim($order_data['shipping']['city']),
          'shipping_postcode' => trim($order_data['shipping']['postcode']),
          'shipping_country' => trim($order_data['shipping']['country']),
          'shipping_same_as_billing' => $shipping_same_as_billing
        ];

        if ($order->get_coupon_codes()) {
          $info['coupon'] = 1;
        }

        $info['products'] = [];
        // Get and Loop Over Order Items
        foreach ( $order->get_items() as $item_id => $item ) {        
          $product_id = $item->get_product_id();
          $product = wc_get_product( $product_id );
          $product_quantity = $item->get_quantity();     

          $product_entry = [
            'id' => $product_id,
            'name' => $item->get_name(),
            'quantity' => $product_quantity, 
            'subtotal' => $item->get_subtotal(),
            'tax' => $item->get_subtotal_tax()                   
          ];        

          if (!empty($tax)) {
            $product_entry['tax_class'] = $item->get_tax_class();          
          }

          $product_initial_price = (float) $product->get_regular_price();
          $product_sales_price = (float) $product->get_sale_price();
          $product_discount_price = ($product_initial_price - $product_sales_price);
          if ($product_discount_price > 0.01) {
            $product_entry['discount'] = number_format($product_discount_price * $product_quantity, 2);
          }          
          
          if ( function_exists( 'wc_get_product_category_list' ) ) {
            // Use the new function if it exists
            $categories = wc_get_product_category_list( $product_id );
          } else {
            // Fallback to the deprecated function if the new one doesn't exist
            $categories = $product->get_categories();
          }		  		 
          
          if (!empty($categories)) {
            if (is_array($categories)) {
              $product_entry['categories'] = implode(';', $categories);
            } else {
              $product_entry['categories'] = $categories;
            }
            $product_entry['categories'] = strip_tags($product_entry['categories']);
          }         
          
          $product_entry['product_url'] = get_permalink( $product_entry['id'] );

          $image_ref = get_post_thumbnail_id( $product_id );
          if (!empty( $image_ref )) {
            $image = wp_get_attachment_image_src( $image_ref, 'single-post-thumbnail' );
            if (!empty($image)) {
              $product_entry['image_url'] = $image[0];
            }
          }

          $meta = $item->get_meta_data();
          if (!empty($meta)) {
            if (is_array($meta)) {
              foreach ($meta as $entry) {
                $meta_data = $entry->get_data();
                if (!isset($product_entry['meta'])) $product_entry['meta'] = '';
                $product_entry['meta'] .= $meta_data['key'].':'.$meta_data['value'];
              }
            }
          }

          if ($product->has_weight()) {
            $product_entry['weight'] = $product->get_weight();
          }
          if ($product->get_downloadable()) {
            $product_entry['virtual'] = 1;
          }
          if ($product->get_featured()) {
            $product_entry['featured'] = 1;
          }

          /*
          $description = $product->get_description();
          $short_description = $product->get_short_description();
          if (!empty($short_description)) {
            $product_entry['description'] = $this->truncateDescription($short_description);
          } else if (!empty($description)) {
            $product_entry['description'] = $this->truncateDescription($description);
          }
          */

          $info['products'][] = $product_entry;
        }
      
      } catch ( Exception $e ) {
         return null;
      }

      return $info;
    }

    public function get_tbyb_flag($payment_method_id = null) {      
      if (empty($payment_method_id)) $payment_method_id = VIABILL_MONTHLY_PAYMENT_METHOD_ID;
      if ($payment_method_id == VIABILL_TRY_PAYMENT_METHOD_ID) {
         return 1;
      }
      return 0;
    }

    public function sanitizePhone($phone, $country_code = null) {
      if (empty($phone)) {
          return $phone;
      }
      if (empty($country_code)) {
          return $phone;
      }
      $clean_phone = str_replace(array('+','(',')','-',' '),'',$phone);
      if (strlen($clean_phone)<3) {
          return $phone;
      }
      $country_code = strtoupper($country_code);
      switch ($country_code) {
          case 'US':
          case 'USA': // +1
              $prefix = substr($clean_phone, 0, 1);
              if ($prefix == '1') {
                  $phone_number = substr($clean_phone, 1);
                  if (strlen($phone_number)==10) {
                      $phone = $phone_number;
                  }
              }                
              break;
          case 'DK': 
          case 'DNK': // +45
              $prefix = substr($clean_phone, 0, 2);
              if ($prefix == '45') {
                  $phone_number = substr($clean_phone, 2);
                  if (strlen($phone_number)==8) {
                      $phone = $phone_number;
                  }
              }
              break;
          case 'ES': 
          case 'ESP': // +34
              $prefix = substr($clean_phone, 0, 2);
              if ($prefix == '34') {
                  $phone_number = substr($clean_phone, 2);
                  if (strlen($phone_number)==9) {
                      $phone = $phone_number;
                  }
              }
              break;        
      }

      return $phone;
    }

    public function truncateDescription($text, $maxchar=200, $end='...') {
      if (empty($text)) return '';
      $text = strip_tags(trim($text)); 
      if (strlen($text) > $maxchar || $text == '') {
          $words = preg_split('/\s/', $text);      
          $output = '';
          $i      = 0;
          while (1) {
              $length = strlen($output)+strlen($words[$i]);
              if ($length > $maxchar) {
                  break;
              } 
              else {
                  $output .= " " . $words[$i];
                  ++$i;
              }
          }
          $output .= $end;
      } 
      else {
          $output = $text;
      }
      return $output;
    }

  }
}

// ========================================================================
// Try before you buy class
// ========================================================================

if ( ! class_exists( 'Viabill_Try_Payment_Gateway' ) ) {
  /**
   * Viabill_Try_Payment_Gateway class
   */
  class Viabill_Try_Payment_Gateway extends WC_Payment_Gateway {
    /**
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;

    /**
     * API's interface.
     *
     * @var Viabill_Connector
     */
    private $connector;

    /**
     * Merchant's profile, object which holds merchant's data.
     *
     * @var Viabill_Merchant_Profile
     */
    private $merchant;

    /**
     * Notices.
     *
     * @var Viabill_Notices
     */
    private $notices;

    /**
     * Support.
     *
     * @var Viabill_Support
     */
    private $support;

    /**
     * REST API.
     *
     * @var Viabill_API
     */
    private $api;        

    /**
     * Viabill common settings
     */
    private $common_settings = [];

    /**
     * Class constructor with basic gateway's setup.
     *
     * @param bool $init  Should the class attributes be initialized.
     */
    public function __construct() {
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-iso-code-converter.php' );

      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-merchant-profile.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-connector.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-notices.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-support.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-api.php' );

      $this->connector = new Viabill_Connector();
      $this->api       = new Viabill_API();

      $this->id           = VIABILL_TRY_PAYMENT_METHOD_ID;
      $this->method_title = __( 'ViaBill - Try Before You Buy', 'viabill' );
      $this->has_fields   = true;      
	
      $this->init_form_fields();
	  
	    // load the settings for this payment method
      // and then add more settings that are common
      // with the main Viabill payment method
      $this->init_settings();	    
      $settings = Viabill_Main::get_gateway_settings();
      foreach ($settings as $s_key => $s_value) {
        if (!isset($this->settings[$s_key])) {
          $this->common_settings[$s_key] = $s_value;
          $this->settings[$s_key] = $s_value;
        }		  
      }

      $this->supports = array( 'products', 'refunds' );
      $this->merchant = new Viabill_Merchant_Profile();
      $this->notices  = new Viabill_Notices();
      $this->support  = new Viabill_Support();
      
      $this->logger = new Viabill_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );

      $this->title = esc_attr( isset( $this->settings['title'] ) ? $this->settings['title'] : '' );
      $this->add_actions();
    }       
    

    /**
     * Return true if payment gateway needs further setup. It basically checks if
     * WooCommerce current currency is supported and if not, it returns false.
     *
     * @override
     * @return bool
     */
    public function needs_setup() {
		   return false;
    }

    /**
     * Register different actions.
     */
    private function add_actions() {
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'do_receipt_page' ) );
      add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'do_order_confirmation' ) );
      add_filter( 'pre_update_option_' . $this->get_option_key(), array( $this, 'filter_settings_values' ) );
      add_filter( 'woocommerce_settings_api_form_fields_' . $this->id, array( $this, 'filter_settings_fields' ) );      
    }

    /**
     * Process refund via ViaBill API.
     *
     * @override
     * @param  int    $order_id
     * @param  float  $amount   Defaults to null.
     * @param  string $reason   Defaults to empty string.
     * @return bool             True or false based on success, or a WP_Error object.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while processing refund.', 'warning' );
        return false;
      }

      return $this->connector->refund( $order, $amount, $order->get_currency() );
    }

    /**
     * Trigger 'viabill_try_gateway_checkout_icon' hook.
     *
     * @override
     */
    public function get_icon() {
      if ( isset( $this->settings['show_title_as_label'] ) && ! empty( $this->settings['show_title_as_label'] ) ) {
        $display_option = $this->settings['show_title_as_label'];
        if ($display_option == 'show_label_only') {
          return '';
        }
      }

      $icon = (string) apply_filters( 'viabill_try_gateway_checkout_icon', 0 );      

      if ( ! empty( $icon ) ) {
        return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
      }

      return '';
    }


    /**
    * Override the default payment method title and visually hide it
    *
    * @override
    */
    public function get_title() {
      if (is_admin()) {
        return parent::get_title();
      } else {

        if ( isset( $this->settings['show_title_as_label'] ) && ! empty( $this->settings['show_title_as_label'] ) ) {
          $display_option = $this->settings['show_title_as_label'];
          if (($display_option == 'show_label_icon') || ($display_option == 'show_label_only')) {
            return parent::get_title();
          }
        }

        return '';
      }      
    }

    /**
     * Define gateway's fields visible at WooCommerce's Settings page and
     * Checkout tab.
     *
     * @override
     */
    public function init_form_fields() {
      $this->form_fields = include( VIABILL_DIR_PATH . '/includes/utilities/viabill-try-settings-fields.php' );
    }

    /**
     * Echoes gateway's options (Checkout tab under WooCommerce's settings).
     *
     * @override
     */
    public function admin_options() {
      $notif_count = $this->notices->get_unseen_count();
      ?>

      <h2><?php esc_html_e( 'ViaBill Try Payment Gateway', 'viabill' ); ?></h2>
      <?php if ( Viabill_Main::is_merchant_registered() ) : ?>
        <table class="form-table">
          <caption style="text-align:left;">
            <a href="" class="button-secondary viabill-dashboard-link" target="_blank"><?php esc_html_e( 'My ViaBill', 'viabill' ); ?></a>
            <a class="button-secondary" href="<?php echo Viabill_Notices::get_admin_url(); ?>">
              <?php esc_html_e( 'Notifications', 'viabill' ); ?>
              <?php echo $notif_count ? '(' . $notif_count . ')' : null; ?>
            </a>
            <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=viabill_pricetag' ); ?>" class="button-secondary viabill-pricetags-link">
              <?php esc_html_e( 'ViaBill PriceTags', 'viabill' ); ?>
            </a>
            <a href="<?php echo Viabill_Support::get_admin_url(); ?>" class="button-secondary viabill-support-link">
              <?php esc_html_e( 'Support', 'viabill' ); ?>
            </a>
          </caption>

          <?php $this->generate_settings_html(); ?>
        </table>
      <?php else : ?>
        <?php require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-registration.php' ); ?>
        <b>
          <?php esc_html_e( 'You\'re not signed in.', 'viabill' ); ?>
          <?php esc_html_e( 'Please', 'viabill' ); ?>
          <a href="<?php echo Viabill_Registration::get_admin_url(); ?>"><?php esc_html_e( 'login or register', 'viabill' ); ?></a>.
        </b>
        <style media="screen">
          p.submit { display: none; }
        </style>
      <?php endif; ?>
      <?php
    }

    /**
     * Display description of the gateway on the checkout page.
     *
     * @override
     */
    public function payment_fields() {
      if ( isset( $this->settings['description-msg'] ) && ! empty( $this->settings['description-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['description-msg'] ) . '</p>';
      }	  	  
	  
      if ( 'yes' === $this->settings['in-test-mode'] ) {
        $test_mode_notice = '<p><b>' . __( 'ViaBill is currently in sandbox/test mode, disable it for live web shops.', 'viabill' ) . '</b></p>';
        $test_mode_notice = apply_filters( 'viabill_payment_description_test_mode_notice', $test_mode_notice );

        if ( ! empty( $test_mode_notice ) ) {
          echo $test_mode_notice;
        }
      }	  

      // do_action( 'viabill_pricetag_after_payment_description' );
      do_action( 'viabill_pricetag_after_tbyb_payment_description' );
    }

    /**
     * Echo confirmation message on the 'thank you' page.
     */
    public function do_order_confirmation( $order_id ) {
      $order = wc_get_order( $order_id );

      if ( 'pending' === $order->get_meta( 'viabill_status' ) ) {
        $order->update_meta_data( 'viabill_status', 'pending_approval' );
        $order->save();
      }

      if ( isset( $this->settings['confirmation-msg'] ) && ! empty( $this->settings['confirmation-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['confirmation-msg'] ) . '</p>';
      }
    }

    /**
     * Echo redirect message on the 'receipt' page.
     */
    private function show_receipt_message() {
      if ( isset( $this->settings['receipt-redirect-msg'] ) && ! empty( $this->settings['receipt-redirect-msg'] ) ) {
        echo '<p>' . wptexturize( $this->settings['receipt-redirect-msg'] ) . '</p>';
      }
    }

    /**
     * Trigger actions for 'receipt' page.
     *
     * @param int $order_id
     */
    public function do_receipt_page( $order_id ) {
      static $executed = false;

      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while trying to show receipt page.', 'warning' );
        return false;
      }

      if ( ! $order->get_meta( 'viabill_status' ) ) {
        $order->add_meta_data( 'viabill_status', 'pending', true );
        $order->save();
      }

      $transaction = $this->connector->get_unique_transaction_id( $order );
      $order_amount = number_format($order->get_total(), 2, '.', ''); 
      
      $success_url = $order->get_checkout_order_received_url();
      $cancel_url = $order->get_cancel_order_url_raw();
      $callback_url = $this->api->get_checkout_status_url($this->id);

      // sanity check
      if (strpos($success_url, 'http') !== 0) {
          // Prepend the base URL using WordPress function to get site URL
          $success_url = site_url($success_url);
      }
      if (strpos($cancel_url, 'http') !== 0) {
          // Prepend the base URL using WordPress function to get site URL
          $cancel_url = site_url($cancel_url);
      }
      if (strpos($callback_url, 'http') !== 0) {
          // Prepend the base URL using WordPress function to get site URL
          $callback_url = site_url($callback_url);
      }
      
      $md5check    = md5( $this->merchant->get_key() . '#' . $order_amount . '#' . $order->get_currency() . '#' . $transaction . '#' . $order->get_id() . '#' . $success_url . '#' . $cancel_url . '#' . $this->merchant->get_secret() );

      $is_test_mode = 'yes' === $this->settings['in-test-mode'];
      if ( $is_test_mode && 'yes' !== $order->get_meta( 'in_test_mode' ) ) {
        $order->add_order_note( __( 'Order was done in <b>test mode</b>.', 'viabill' ) );
        $order->add_meta_data( 'in_test_mode', 'yes', true );
        $order->save();
      }

      $customer_info = $this->get_customer_info($order);
      $customer_info_json = (empty($customer_info))?'':json_encode($customer_info);

      $cart_info = $this->get_cart_info($order);
      $cart_info_json = (empty($cart_info))?'':json_encode($cart_info);

      $tbyb = $this->get_tbyb_flag($this->id);
      
      $debug_info = array(
        'apikey' => $this->merchant->get_key(),
        'transaction' => $transaction,
        'order_number' => $order->get_id(),
        'amount' => $order_amount,
        'currency' => $order->get_currency(),
        'success_url' => $success_url,
        'cancel_url' => $cancel_url,
        'callback_url' => $callback_url,
        'test' => $is_test_mode ? 'true' : 'false',
        'customParams' => $customer_info_json,
        'cartParams' => $cart_info_json,
        'md5check' => $md5check,
        'tbyb' => $tbyb
      );
      $debug_info_str = print_r($debug_info, true);
      $this->logger->log( $debug_info_str, 'notice');

      if (!$executed) {      
      
        $form_url = $this->api->get_checkout_authorize_url($this->id);        

        ?>
        <form id="viabill-try-payment-form" action="<?php echo esc_url($this->connector->get_checkout_url()); ?>" method="post">
          <input type="hidden" name="protocol" value="3.0">
          <input type="hidden" name="apikey" value="<?php echo esc_attr($this->merchant->get_key()); ?>">
          <input type="hidden" name="transaction" value="<?php echo esc_attr($transaction); ?>">
          <input type="hidden" name="order_number" value="<?php echo esc_attr($order->get_id()); ?>">
          <input type="hidden" name="amount" value="<?php echo esc_attr($order_amount); ?>">
          <input type="hidden" name="currency" value="<?php echo esc_attr($order->get_currency()); ?>">
          <input type="hidden" name="success_url" value="<?php echo esc_url($success_url); ?>">
          <input type="hidden" name="cancel_url" value="<?php echo esc_url($cancel_url); ?>">
          <input type="hidden" name="callback_url" value="<?php echo esc_url($callback_url); ?>">
          <input type="hidden" name="test" value="<?php echo $is_test_mode ? 'true' : 'false'; ?>">
          <input type="hidden" name="customParams" value="<?php echo htmlspecialchars($customer_info_json, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="cartParams" value="<?php echo htmlspecialchars($cart_info_json, ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="md5check" value="<?php echo esc_attr($md5check); ?>">
          <input type="hidden" name="tbyb" value="<?php echo $tbyb ? '1' : '0'; ?>">
        </form> 

        <input type="button" id="viabill-try-payment-form-submit" value="Submit" onclick="postViabillTryPaymentForm()" />
        
        <?php

        $inline_script = "      
        function postViabillTryPaymentForm() {
          var formData = jQuery('#viabill-try-payment-form').serialize();
          jQuery.ajax({
            type: 'POST',          
            url: '{$form_url}',
            data: formData,		
            dataType: 'json',		
            
            success: function(data, textStatus){			
              if (data.redirect) {                            
                window.location.href = data.redirect;
              } else {
                console.log('No data redirect after posting ViaBill Try Payment Form');
                console.log(data);
              }
            },
            error: function(errMsg) {
              console.log('Unable to post ViaBill Try Payment Form');
              console.log(errMsg);			
            }
          }); 
        }

        jQuery('#viabill-try-payment-form-submit').on('click', postViabillTryPaymentForm);
        ";

        wc_enqueue_js($inline_script);

        if ($this->settings['auto-redirect'] === 'yes') {
          $this->enqueue_redirect_js();
        } else {
          $this->show_receipt_message();
        }  

        $executed = true;      
      }
      
    }

    /**
     * Enqueue JavaScript for redirecting to a ViaBill form.
     */
    private function enqueue_redirect_js() {
      // It's safe to use $ with WooCommerce.
      // If there's no redirect after 10 seconds, unblock the UI.      
      wc_enqueue_js( "if ($.fn.block) { $('.woocommerce').block({ message: null, overlayCSS: { background: '#fff', opacity: 0.6}}); setTimeout(function(){ $('.woocommerce').unblock(); }, 10000); }" );      
      //wc_enqueue_js( "$('#viabill-payment-form').submit();" );
      wc_enqueue_js( "postViabillTryPaymentForm();" );
    }

    /**
     * Redirect to given URL if possible.
     *
     * @param string $url
     */
    public function call_redirect( $url ) {
      if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
      }
      $this->logger->log( 'Headers already sent before redirecting to ' . $url . ' URL.', 'warning' );
    }

    /**
     * Process the payment and return the result.
     *
     * @override
     * @param string $order_id
     * @return array
     */
    public function process_payment( $order_id ) {
      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to find order ' . $order_id . ' while trying to process payment.', 'critical' );
        return;
      }

      // Remove cart.
      WC()->cart->empty_cart();

      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true ),
      );
    }

    /**
     *  Declare compatibility with HPOS   
     */
    public function custom_order_tables() {
      return true;
    }

    /**
     * Filter settings values before they are update in the database.
     *
     * @param  string $value  Option value.
     * @return string         Option value.
     */
    public function filter_settings_values( $value ) {
      // If auto-capture option is enabled than disable capture-order-on-status-switch.
      if (!empty($this->common_settings)) {
        foreach ($this->common_settings as $ckey => $cval) {
          unset($value[$ckey]);
        }
      }      

      return $value;
    }

    /**
     * Filter settings fields.
     *
     * @param  array $fields Fields array.
     * @return array
     */
    public function filter_settings_fields( $fields ) {      
      return $fields;
    }    
   
    /**
     * Get information about the customer for the active order
     * 
     * @param WC_Order $order
     * 
     * @return array
     */ 
    public function get_customer_info($order) {      
      
      $info = [
        'email'=>'',
        'phoneNumber'=>'',
        'firstName'=>'',
        'lastName'=>'',
        'fullName'=>'',
        'address'=>'',
        'city'=>'',
        'postalCode'=>'',
        'country'=>''
      ];

      // sanity check
      if (empty($order)) return $info;           
      
      $info['email']  = $order->get_billing_email();      
      $info['phoneNumber']  = $this->sanitizePhone($order->get_billing_phone(),
                                                 $order->get_billing_country());   
      $info['firstName'] = $order->get_billing_first_name();
      $info['lastName']  = $order->get_billing_last_name();      
      $info['fullName'] = trim($info['firstName'].' '.$info['lastName']);
      $address = $order->get_billing_address_1();
      $address .= ' '.$order->get_billing_address_2();
      $info['address'] = trim($address);
      $info['city']       = $order->get_billing_city();      
      $info['postalCode']   = $order->get_billing_postcode();
      $info['country']    = $order->get_billing_country();

      return $info;
    }

    /**
     * Get information about the cart contents for the active order
     * 
     * @param WC_Order $order
     * 
     * @return array
     */ 
    public function get_cart_info($order) {                           
      // sanity check
      if (empty($order)) return null;

      try {

        $order_data = $order->get_data(); // The Order data
        $order_amount = number_format($order->get_total(), 2, '.', '');

        $shipping_same_as_billing = 'yes';
        $billing_fields = [
          'address' => trim($order_data['billing']['address_1'].' '.$order_data['billing']['address_2']),
          'city' => trim($order_data['billing']['city']),
          'state' => trim($order_data['billing']['state']),
          'postcode' => trim($order_data['billing']['postcode']),
          'country' => $order_data['billing']['country']        
        ];
        $shipping_fields = [
          'address' => trim($order_data['shipping']['address_1'].' '.$order_data['shipping']['address_2']),
          'city' => trim($order_data['shipping']['city']),
          'state' => trim($order_data['shipping']['state']),
          'postcode' => trim($order_data['shipping']['postcode']),
          'country' => trim($order_data['shipping']['country'])          
        ];
        foreach ($billing_fields as $billing_field => $billing_value ) {
          $shipping_value = $shipping_fields[$billing_field];
          if (!empty($shipping_value) && !empty($billing_value)) {
            if ($shipping_value != $billing_value) {
              $shipping_same_as_billing = 'no';
            }
          }
        }

        $info = [
          'date_created' => $order_data['date_created']->date('Y-m-d H:i:s'),
          'subtotal'=> $order->get_subtotal(),        
          'tax' => $order->get_total_tax(),
          'shipping'=>$order->get_shipping_total(),      
          'discount'=>$order->get_total_discount(),
          'total'=>$order_amount,
          'currency'=>$order->get_currency(),
          'quantity'=> $order->get_item_count(),
          'billing_email' => trim($order_data['billing']['email']),
          'billing_phone' => trim($order_data['billing']['phone']), 
          'shipping_city' => trim($order_data['shipping']['city']),
          'shipping_postcode' => trim($order_data['shipping']['postcode']),
          'shipping_country' => trim($order_data['shipping']['country']),
          'shipping_same_as_billing' => $shipping_same_as_billing
        ];

        if ($order->get_coupon_codes()) {
          $info['coupon'] = 1;
        }

        $info['products'] = [];
        // Get and Loop Over Order Items
        foreach ( $order->get_items() as $item_id => $item ) {        
          $product_id = $item->get_product_id();
          $product = wc_get_product( $product_id );
          $product_quantity = $item->get_quantity();     

          $product_entry = [
            'id' => $product_id,
            'name' => $item->get_name(),
            'quantity' => $product_quantity, 
            'subtotal' => $item->get_subtotal(),
            'tax' => $item->get_subtotal_tax()                   
          ];        

          if (!empty($tax)) {
            $product_entry['tax_class'] = $item->get_tax_class();          
          }

          $product_initial_price = (float) $product->get_regular_price();
          $product_sales_price = (float) $product->get_sale_price();
          $product_discount_price = ($product_initial_price - $product_sales_price);
          if ($product_discount_price > 0.01) {
            $product_entry['discount'] = number_format($product_discount_price * $product_quantity, 2);
          }

          $categories = $product->get_categories(); 
          if (!empty($categories)) {
            if (is_array($categories)) {
              $product_entry['categories'] = implode(';', $categories);
            } else {
              $product_entry['categories'] = $categories;
            }
            $product_entry['categories'] = strip_tags($product_entry['categories']);
          }         
          
          $product_entry['product_url'] = get_permalink( $product_entry['id'] );

          $image_ref = get_post_thumbnail_id( $product_id );
          if (!empty( $image_ref )) {
            $image = wp_get_attachment_image_src( $image_ref, 'single-post-thumbnail' );
            if (!empty($image)) {
              $product_entry['image_url'] = $image[0];
            }
          }

          $meta = $item->get_meta_data();
          if (!empty($meta)) {
            if (is_array($meta)) {
              foreach ($meta as $entry) {
                $meta_data = $entry->get_data();
                if (!isset($product_entry['meta'])) $product_entry['meta'] = '';
                $product_entry['meta'] .= $meta_data['key'].':'.$meta_data['value'];
              }
            }
          }

          if ($product->has_weight()) {
            $product_entry['weight'] = $product->get_weight();
          }
          if ($product->get_downloadable()) {
            $product_entry['virtual'] = 1;
          }
          if ($product->get_featured()) {
            $product_entry['featured'] = 1;
          }

          /*
          $description = $product->get_description();
          $short_description = $product->get_short_description();
          if (!empty($short_description)) {
            $product_entry['description'] = $this->truncateDescription($short_description);
          } else if (!empty($description)) {
            $product_entry['description'] = $this->truncateDescription($description);
          }
          */

          $info['products'][] = $product_entry;
        }
      
      } catch ( Exception $e ) {
         return null;
      }

      return $info;
    }

    public function get_tbyb_flag($payment_method_id = null) {      
      if (empty($payment_method_id)) $payment_method_id = VIABILL_MONTHLY_PAYMENT_METHOD_ID;
      if ($payment_method_id == VIABILL_TRY_PAYMENT_METHOD_ID) {
         return 1;
      }
      return 0;
    }

    public function sanitizePhone($phone, $country_code = null) {
      if (empty($phone)) {
          return $phone;
      }
      if (empty($country_code)) {
          return $phone;
      }
      $clean_phone = str_replace(array('+','(',')','-',' '),'',$phone);
      if (strlen($clean_phone)<3) {
          return $phone;
      }
      $country_code = strtoupper($country_code);
      switch ($country_code) {
          case 'US':
          case 'USA': // +1
              $prefix = substr($clean_phone, 0, 1);
              if ($prefix == '1') {
                  $phone_number = substr($clean_phone, 1);
                  if (strlen($phone_number)==10) {
                      $phone = $phone_number;
                  }
              }                
              break;
          case 'DK': 
          case 'DNK': // +45
              $prefix = substr($clean_phone, 0, 2);
              if ($prefix == '45') {
                  $phone_number = substr($clean_phone, 2);
                  if (strlen($phone_number)==8) {
                      $phone = $phone_number;
                  }
              }
              break;
          case 'ES': 
          case 'ESP': // +34
              $prefix = substr($clean_phone, 0, 2);
              if ($prefix == '34') {
                  $phone_number = substr($clean_phone, 2);
                  if (strlen($phone_number)==9) {
                      $phone = $phone_number;
                  }
              }
              break;        
      }

      return $phone;
    }    

  }
}