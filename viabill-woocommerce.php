<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: ViaBill - WooCommerce
 * Plugin URI: https://www.viabill.dk/
 * Description: ViaBill Gateway for WooCommerce.
 * Version: 1.1.52
 * Requires at least: 5.0
 * Requires PHP: 5.6
 * Author: ViaBill
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: viabill
 * Domain Path: /languages
 *
 * WC requires at least: 3.3
 * WC tested up to: 5.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! function_exists( 'viabill_is_woocommerce_active' ) ) {  

  /**
   * Return true if the WooCommerce plugin is active or false otherwise.
   *
   * @since 0.1
   * @return boolean
   */
  function viabill_is_woocommerce_active() {
    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
      return true;
    }
    return false;
  }

  function register_compatibility() {
    add_action( 'before_woocommerce_init', 'viabill_hpos_compatibility' );
    add_action( 'before_woocommerce_init', 'viabill_declare_cart_checkout_blocks_compatibility');
    add_action( 'woocommerce_blocks_loaded', 'viabill_register_order_approval_payment_method_type');
  }

  /**
  * Declare support for the HPOS mode
  */
  function viabill_hpos_compatibility() {
    if( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        __FILE__,
        true // true (compatible, default) or false (not compatible)
      );
    }
  }

  /**
   * Custom function to declare compatibility with cart_checkout_blocks feature 
   */
  function viabill_declare_cart_checkout_blocks_compatibility() {				
    // Check if the required class exists
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
      // Declare compatibility for 'cart_checkout_blocks'
      \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
  }
      
  /**
  * Custom function to register a payment method type
  */
  function viabill_register_order_approval_payment_method_type() {				
    // Check if the required class exists
    if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
      return;
    }

    // Include the custom Blocks Checkout class for the Monthly Payments
    require_once VIABILL_DIR_PATH . '/includes/utilities/class-viabill-woocommerce-block-checkout.php';		
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
        // Register an instance of WC_Viabill_Blocks
        $payment_method_registry->register( new WC_Viabill_Blocks );
      }
    );

    // Include the custom Blocks Checkout class for the Try Before you Buy
    require_once VIABILL_DIR_PATH . '/includes/utilities/class-viabill-try-woocommerce-block-checkout.php';
    add_action(
      'woocommerce_blocks_payment_method_type_registration',
      function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
        // Register an instance of WC_Viabill_Blocks
        $payment_method_registry->register( new WC_Viabill_Try_Blocks );
      }
    );
  }

  register_compatibility(); 

}

if ( ! function_exists( 'viabill_admin_notice_missing_woocommerce' ) ) {
  /**
   * Echo admin notice HTML for missing WooCommerce plugin.
   *
   * @since 0.1
   */
  function viabill_admin_notice_missing_woocommerce() {
    global $current_screen;
    if ( 'plugins' === $current_screen->parent_base ) {
      ?>
      <div class="notice notice-error">
        <p><?php echo __( 'Please install and activate <a href="http://www.woothemes.com/woocommerce/" target="_blank">WooCommerce</a> before activating ViaBill payment gateway!', 'viabill' ); ?></p>
      </div>
      <?php
    }
  }
}
if ( ! viabill_is_woocommerce_active() ) {
  add_action( 'admin_notices', 'viabill_admin_notice_missing_woocommerce' );
  return;
}

if ( ! class_exists( 'Viabill_Main' ) ) {
  /**
   * The main plugin class.
   *
   * @since 0.1
   */
  class Viabill_Main {
    /**
     * Instance of the current class, null before first usage.
     *
     * @var Viabill_Main
     */
    protected static $instance = null;

    /**
     * Instance of the Viabill_Merchant_Profile class, to check for registered user before outputing any data.
     *
     * @var Viabill_Merchant_Profile
     */
    protected static $merchant = null;

    /**
     * Class constructor, initialize constants and settings.
     *
     * @since 0.1
     */
    protected function __construct() {
      require_once(plugin_dir_path( __FILE__ ). 'includes/core/class-viabill-registration.php' );
      require_once(plugin_dir_path( __FILE__ ). 'includes/core/class-viabill-merchant-profile.php' );

      self::register_constants();

      add_action( 'plugins_loaded', array( $this, 'check_requirements' ) );

      add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );

      add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_script' ) );
      add_action( 'wp_enqueue_scripts', array( $this, 'register_client_script' ) );
      add_action( 'admin_init', array( $this, 'maybe_redirect_to_registration' ) );
      add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 5 );
      add_action( 'admin_init', array( $this, 'check_for_other_viabill_gateways' ), 1 );
      add_action( 'admin_init', array( $this, 'viabill_migrate_older_version' ), 5 );
      // add_action( 'activated_plugin', array( $this, 'check_backward_compatibility' ) );
      add_action( 'woocommerce_admin_field_payment_gateways', array( $this, 'check_for_other_viabill_gateways' ) );

      // shortcodes for price tags
      add_shortcode('viabill_pricetag_product', array( $this, 'viabill_pricetag_product_shortcode') );
      add_shortcode('viabill_pricetag_cart', array( $this, 'viabill_pricetag_cart_shortcode') );
      add_shortcode('viabill_pricetag_tbyb_checkout', array( $this, 'viabill_pricetag_tbyb_checkout_shortcode') );
      add_shortcode('viabill_pricetag_monthly_checkout', array( $this, 'viabill_pricetag_monthly_checkout_shortcode') );

      new Viabill_Registration( true );

      $merchant = new Viabill_Merchant_Profile();
      $merchant->register_ajax_endpoints();

      if ( Viabill_Main::is_merchant_registered() ) {
        $this->init_pricetags();

        if ( ! self::is_payment_gateway_disabled() ) {
          $this->init_payment_gateway();
        }
      }
      
    }

    /**
     * Load ViaBill Payment Gateway.
     */
    public function init_payment_gateway() {
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-payment-gateway.php' );
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-order-admin.php' );

      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-notices.php' );
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-support.php' );

      require_once(VIABILL_DIR_PATH. '/includes/utilities/class-viabill-icon-shortcode.php' );
      require_once(VIABILL_DIR_PATH. '/includes/utilities/class-viabill-db-update.php' );

      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-api.php' );

      $payment_operations = new Viabill_Order_Admin();
      $payment_operations->register_ajax_endpoints();

      $notices = new Viabill_Notices( true );
      $support = new Viabill_Support( true );

      $api = new Viabill_API();
      $api->register();

      $icon_shortcode = new Viabill_Icon_Shortcode();
      $icon_shortcode->register();

      $db_update = new Viabill_DB_Update();
      $db_update->init();

      add_action( 'admin_init', array( $this, 'check_wc_decimal_places' ) );
      add_action( 'admin_init', array( $this, 'check_is_test_mode' ) );      
    }

    /**
     * Load ViaBill PriceTags
     */
    public function init_pricetags() {
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-pricetag.php' );

      $pricetag = new Viabill_Pricetag();
      $pricetag->maybe_show();
    }    

    public function viabill_pricetag_product_shortcode($atts) {
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-pricetag.php' );

      // Define default attribute values
      $atts = shortcode_atts(array(
          'inplace' => false, // default is false
      ), $atts, 'pricetag');

      // Access the inplace attribute
      $inplace = $atts['inplace'];

      $pricetag = new Viabill_Pricetag();
      return $pricetag->show_on_product($inplace);
    }

    public function viabill_pricetag_cart_shortcode($atts) {
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-pricetag.php' );

      // Define default attribute values
      $atts = shortcode_atts(array(
          'inplace' => false, // default is false
      ), $atts, 'pricetag');

      // Access the inplace attribute
      $inplace = $atts['inplace'];

      $pricetag = new Viabill_Pricetag();
      return $pricetag->show_on_cart($inplace);
    }

    public function viabill_pricetag_monthly_checkout_shortcode($atts) {
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-pricetag.php' );

      // Define default attribute values
      $atts = shortcode_atts(array(
          'inplace' => false, // default is false
      ), $atts, 'pricetag');

      // Access the inplace attribute
      $inplace = $atts['inplace'];

      $pricetag = new Viabill_Pricetag();
      return $pricetag->show_on_monthly_checkout($inplace);
    }

    public function viabill_pricetag_tbyb_checkout_shortcode($atts) {
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-pricetag.php' );

      // Define default attribute values
      $atts = shortcode_atts(array(
          'inplace' => false, // default is false
      ), $atts, 'pricetag');

      // Access the inplace attribute
      $inplace = $atts['inplace'];

      $pricetag = new Viabill_Pricetag();
      return $pricetag->show_on_tbyb_checkout($inplace);
    }

    /**
     * Check if payment gateway should be disabled.
     */
    public static function is_payment_gateway_disabled() {
      // the disabled gateway option is obsolete
      // right now, the preferred way is to hide the payment method
      // during the checkout page      
      if (get_option( 'viabill_gateway_disabled' )) {        
        self::set_payment_gateway_disabled();
      }
      return false;      
    }

    /**
     * Set payment gateway as disabled.
     */
    public static function set_payment_gateway_disabled() {
      // the disabled gateway option is obsolete
      // right now, the preferred way is to hide the payment method
      // during the checkout page
      update_option( 'viabill_gateway_disabled', 0 );
      
      $option_key = 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings';
      $settings = get_option($option_key);      
      if (is_array($settings) && isset($settings['checkout-hide'])) {
          $settings['checkout-hide'] = 'yes';
          // Save the updated settings back to the database
          update_option($option_key, $settings);
      }
    }

    /**
     * Set payment gateway as enabled.
     */
    public static function set_payment_gateway_enabled() {
      // the disabled gateway option is obsolete
      // right now, the preferred way is to hide the payment method
      // during the checkout page
      update_option( 'viabill_gateway_disabled', 0 );

      $option_key = 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings';
      $settings = get_option($option_key);      
      if (is_array($settings) && isset($settings['checkout-hide'])) {
          $settings['checkout-hide'] = 'no';
          // Save the updated settings back to the database
          update_option($option_key, $settings);
      }
    }    

    /**
     * Does the site has all the needed data for the gateway to work.
     *
     * @return bool
     */
    public static function is_merchant_registered() {
      $required_data = array( 'viabill_key', 'viabill_secret', 'viabill_pricetag' );
      foreach ( $required_data as $data_key ) {
        $data = get_option( $data_key );
        if ( ! is_string( $data ) || empty( $data ) ) {
          return false;
        }
      }
      return true;
    }

    /**
     * Register plugin's constants.
     */
    public static function register_constants() {
      if ( ! defined( 'VIABILL_PLUGIN_ID' ) ) {
        define( 'VIABILL_PLUGIN_ID', 'viabill_official' );
      }
      if ( ! defined( 'VIABILL_PLUGIN_VERSION' ) ) {
        define( 'VIABILL_PLUGIN_VERSION', '1.1.52' );
      }
      if ( ! defined( 'VIABILL_DIR_PATH' ) ) {
        define( 'VIABILL_DIR_PATH', plugin_dir_path( __FILE__ ) );
      }
      if ( ! defined( 'VIABILL_DIR_URL' ) ) {
        define( 'VIABILL_DIR_URL', plugin_dir_url( __FILE__ ) );
      }
      if ( ! defined( 'VIABILL_REQUIRED_PHP_VERSION' ) ) {
        define( 'VIABILL_REQUIRED_PHP_VERSION', '5.6' );
      }
      if ( ! defined( 'VIABILL_REQUIRED_WC_VERSION' ) ) {
        define( 'VIABILL_REQUIRED_WC_VERSION', '3.3' );
      }
    }

    /**
     * Load plugin's textdomain.
     */
    public function load_textdomain() {
      load_plugin_textdomain( 'viabill', false, basename( dirname( __FILE__ ) ) . '/languages' );
    }

    /**
     * Create settings link depending on gateway activation.
     */
    public static function get_settings_link() {
      if ( ! Viabill_Main::is_merchant_registered() ) {
        $link = get_admin_url( null, 'admin.php?page=viabill-register' );
      } elseif ( self::is_payment_gateway_disabled() ) {
        $link = get_admin_url( null, 'admin.php?page=wc-settings&tab=viabill_pricetag' );
      } else {
        $link = get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=' . VIABILL_PLUGIN_ID );
      }

      return $link;
    }

    /**
     * Check versions of requirements.
     */
    public function check_requirements() {
      $requirements = array(
        'php' => array(
          'current_version' => phpversion(),
          'requred_version' => VIABILL_REQUIRED_PHP_VERSION,
          'name'            => 'PHP',
        ),
        'wc'  => array(
          'current_version' => WC_VERSION,
          'requred_version' => VIABILL_REQUIRED_WC_VERSION,
          'name'            => 'WooCommerce',
        ),
      );

      $error_notices = array();

      foreach ( $requirements as $requirement ) {
        if ( version_compare( $requirement['current_version'], $requirement['requred_version'], '<' ) ) {
          $error_notices[] = sprintf(
            __( 'The minimum required version of %1$s is %2$s. The version you are running is %3$s. Please update %1$s in order to use ViaBill.', 'viabill' ),
            $requirement['name'],
            $requirement['requred_version'],
            $requirement['current_version']
          );
        }
      }

      if ( $error_notices ) {
        add_action( 'admin_init', array( $this, 'deactivate_self' ) );

        foreach ( $error_notices as $error_notice ) {
          self::admin_notice( $error_notice );
        }
      }
    }

    /**
     * Check if test mode is on and display a notice globally in admin.
     */
    public function check_is_test_mode() {
      if ( 'yes' === self::get_gateway_settings( 'in-test-mode' ) ) {
        self::admin_notice( __( 'ViaBill is currently in sandbox/test mode, disable it for live web shops.', 'viabill' ), 'warning' );
      }
    }
    
    /**
     * If there are other ViaBill payment gateways disable our gateway.
     */
    public static function check_for_other_viabill_gateways() {          
      $can_display_warning = false;

      $page = (isset($_REQUEST['page']))?sanitize_key($_REQUEST['page']):'';
      $tab = (isset($_REQUEST['tab']))?sanitize_key($_REQUEST['tab']):'';
      if ($page == 'wc-settings') {
        switch ($tab) {
          case 'checkout':
          case 'viabill_pricetag':
             $can_display_warning = true;
             break;   
        }
      }

      if ( self::viabill_gateway_exists() ) {          
        self::set_payment_gateway_disabled();
      } else {
        $can_display_warning = false;
        if ( self::is_payment_gateway_disabled() ) {
          self::set_payment_gateway_enabled();
        }
      }

      if ($can_display_warning) {
        // __( 'You can only have one ViaBill payment gateway active at the same time. The ViaBill payment gateway from the plugin ViaBill - WooCommerce has been disabled.', 'viabill' )
        $notice = __( 'IMPORTANT! You have ViaBill payments enabled through a payment gateway. The ViaBill payment method provided by this ViaBill WooCommerce plugin requires that ViaBill as a payment method is disabled in that gateway. Fortunately, we’ve made it easy for you; simply click the button below and it is instantly disabled (everything else stays enabled, of course)', 'viabill');
        $notice .= '<br/><input class="button-secondary" style="margin-bottom:10px;" type="button" value="'.__( 'Disable now', 'viabill' ).'" id="DisableThirdPartyPaymentBtn" >';
        self::admin_notice( $notice );
      }

    }

    /**
     * Checks if ViaBill payment gateways already exists and they are enabled
     */
    public static function viabill_gateway_exists($must_be_enabled = true) {
      // Check if there already is payment method with id "viabill".
      $payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();

      $third_party_found = isset( $payment_gateways['viabill'] ) && ! $payment_gateways['viabill'] instanceof Viabill_Payment_Gateway;

      if ($third_party_found) {
        if ($must_be_enabled) {
          $option_key = 'woocommerce_viabill_settings';
          $payment_settings = get_option( $option_key, false );
          if ( !empty($payment_settings) ) {
            if (is_array($payment_settings)) {
              if (isset($payment_settings['enabled'])) {
                if ($payment_settings['enabled'] == 'yes') {
                  return true;
                };                                
              }
            }
          }
        } else {
           return true;
        }
      }
            
      return false;                  
    }

    /**
     * Migrate older plugin settings, if present
     */
    public static function viabill_migrate_older_version() {
        $new_payment_settings = array();
        $new_option_key = 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings';
        $payment_settings = get_option( $new_option_key, false );
        if ( !empty($payment_settings) ) {
          if (is_array($payment_settings)) {
             return true;
          }
        }
        
        $old_payment_settings = array();
        $old_option_key = 'woocommerce_viabill_settings';
        $payment_settings = get_option( $old_option_key, false );
        if ( !empty($payment_settings) ) {
          if (is_array($payment_settings)) {
            // Check if there already is payment method with id "viabill".
            $payment_gateways = WC_Payment_Gateways::instance()->payment_gateways();
            $third_party_found = isset( $payment_gateways['viabill'] ) && ! $payment_gateways['viabill'] instanceof Viabill_Payment_Gateway;
            if ($third_party_found) {
                // don't do anything
            } else {
                update_option($new_option_key, $payment_settings, true);
                return true;
            }            
          }
        }

        return false;
    }

    /**
     * Deactivate plugin.
     */
    public static function deactivate_self() {
      remove_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( self::get_instance(), 'add_settings_link' ) );
      remove_action( 'admin_init', array( self::get_instance(), 'maybe_redirect_to_registration' ) );
      remove_action( 'admin_init', array( self::get_instance(), 'check_wc_decimal_places' ) );
      remove_action( 'admin_init', array( self::get_instance(), 'check_is_test_mode' ) );

      deactivate_plugins( plugin_basename( __FILE__ ) );
      unset( $_GET['activate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    }

    /**
     * Add admin notice.
     *
     * @param  string $notice Notice content.
     * @param  string $type   Notice type.
     */
    public static function admin_notice( $notice, $type = 'error' ) {
      add_action(
        'admin_notices',
        function() use ( $notice, $type ) {
          printf( '<div class="notice notice-%2$s"><p>%1$s</p></div>', $notice, $type );
        }
      );
    }

    /**
     * Register plugin's admin JS script.
     */
    public function register_admin_script() {
      wp_enqueue_script( 'viabill-admin-script', VIABILL_DIR_URL . '/assets/js/viabill-admin.js', array( 'jquery' ), VIABILL_PLUGIN_VERSION, true );
      wp_localize_script(
        'viabill-admin-script',
        'viabillAdminScript',
        array(
          'url'                           => admin_url( 'admin-ajax.php' ),
          'automatic_refund'              => self::get_gateway_settings( 'automatic-refund-status' ),
          'status_capture'                => self::get_gateway_settings( 'capture-order-on-status-switch' ),
          'msg_error_default'             => __( 'Something went wrong, please refresh the page and try again.', 'viabill' ),
          'msg_error_capture'             => __( 'Something went wrong with ViaBill capture form. Please refresh and try again.', 'viabill' ),
          'msg_order_cancel'              => __( 'Are you sure you want to cancel the order?', 'viabill' ),
          'msg_order_switch_status'       => __( 'Warning: this order has ViaBill status "Pending Approval". By switching the order status before ViaBill approves it, you assume all responsibility for the transaction if the order is shipped prior to approval or if the order is denied by ViaBill. To reduce risk, please change the order status only after ViaBill status is no longer "Pending Approval".', 'viabill' ),
          'msg_order_rounded_amount'      => __( 'Warning: you are trying to capture price amount with more than 2 decimal places. ViaBill only supports 2 decimal places so the price will be rounded before sending it to ViaBill for capturing, this may lead to capturing less or more than the real amount. Confirming and proceeding with this you assume all responsibility for the transaction.', 'viabill' ),
          'msg_order_status_refund'       => __( 'Changing order status to "Refunded" will automatically process refund for available captured amount by ViaBill payment gateway. You can turn this off in the plugin settings. This process cannot be undone, are you sure you want to continue?', 'viabill' ),
          'msg_order_status_refund_empty' => __( 'Warning: You are trying to refund an order before capturing it, nothing will be refunded and the order status will be set as "Refunded", are you sure you want to continue?', 'viabill' ),
          'msg_order_status_depricated'   => __( 'Order status you selected is no longer supported by ViaBill payment gateway and will be removed in the later versions. Please use "On-hold" instead of "Approved by ViaBill" and "Processing" instead of "Captured by ViaBill".', 'viabill' ),
          'msg_order_status_capture'      => __( 'Switching order status to "Processing" will try to capture full order amount through ViaBill, do you want to continue?', 'viabill' ),
          'msg_order_status_sync'         => __( 'Refreshing this order will contact ViaBill\'s system and attempt to sync this order with ViaBill\'s records. If you have configured the ViaBill - WooCommerce plugin settings to allow for interacting with order status changes, they will be executed at this time. You can disable these settings in the ViaBill - WooCommerce plugin settings. Would you like to continue?', 'viabill' ),
          'disable_third_party_gateway_url'   => self::get_disable_thrid_party_link()
        )
      );
    }

    /**
     * Register plugin's client JS script.
     */
    public function register_client_script() {
      wp_enqueue_script( 'viabill-client-script', VIABILL_DIR_URL . '/assets/js/viabill.js', array( 'jquery' ), VIABILL_PLUGIN_VERSION, true );
    }

    /**
     * Adds the link to the settings page on the plugins WP page.
     *
     * @param array   $links
     * @return array
     */
    public function add_settings_link( $links ) {
      $settings_link = '<a href="' . self::get_settings_link() . '">' . __( 'Settings', 'viabill' ) . '</a>';
      array_unshift( $links, $settings_link );

      return $links;
    }

    /**
     * Redirect to registration page if 'viabill_activation_redirect' option is
     * set to true.
     */
    public function maybe_redirect_to_registration() {
      if ( get_option( 'viabill_activation_redirect', false ) ) {
        delete_option( 'viabill_activation_redirect' );
        wp_safe_redirect( get_admin_url( null, 'admin.php?page=viabill-register' ) );
        exit;
      }
    }

    /**
     * Check if WooCommerce decimals are set to more than 2 places.
     */
    public function check_wc_decimal_places() {
      $decimals = (int) get_option( 'woocommerce_price_num_decimals', 2 );

      if ( $decimals > 2 && (int) get_option( 'viabill_decimals', 2 ) !== $decimals ) {
        update_option( 'viabill_decimals', $decimals );
        update_user_meta( get_current_user_id(), 'dismissed_viabill_decimals_notice', false );
      } else {
        update_user_meta( get_current_user_id(), 'dismissed_viabill_decimals_notice', true );
      }

      if ( $decimals > 2 && ! get_user_meta( get_current_user_id(), 'dismissed_viabill_decimals_notice', true ) ) {
        WC_Admin_Notices::add_custom_notice( 'viabill_decimals_' . $decimals, sprintf( __( '%1$s ViaBill Notice - %2$s Your WooCommerce store is set to use %3$d decimal places. Please take in account that ViaBill payment gateway works only with 2 decimal places and will round all received amounts to 2 decimal places.', 'viabill' ), '<strong>', '</strong>', $decimals ) );
      }
    }

    /**
     * Load gateway settings from the database.
     *
     * @return array
     */
    public static function get_gateway_settings( $setting = false ) {
      $settings = get_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings', array() );
      return $setting ? isset( $settings[ $setting ] ) ? $settings[ $setting ] : false : $settings;
    }

    /**
     * Checks for other ViaBill gateway plugins before deleting saved settings.
     * If there are other plugins simply delete only fields set by this plugin, otherwise delete all of the settings.
     */
    public static function delete_gateway_settings() {
      delete_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings' );      
    }

    /**
     * Installation procedure.
     *
     * @static
     */
    public static function install() {
      if ( ! current_user_can( 'activate_plugins' ) ) {
        return false;
      }      

      self::register_constants();

      if ( ! Viabill_Main::is_merchant_registered() ) {
        add_option( 'viabill_activation_redirect', true );
      }
    }

    /**
     * Uninstallation procedure.
     *
     * @static
     */
    public static function uninstall() {
      if ( ! current_user_can( 'activate_plugins' ) ) {
        return false;
      }

      self::register_constants();
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-merchant-profile.php' );
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-notices.php' );
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-support.php' );

      $merchant = new Viabill_Merchant_Profile();
      $merchant->delete_registration_data();

      self::delete_gateway_settings();

      $notices = new Viabill_Notices();
      $notices->destroy_cron();
      $notices->delete_from_db();

      wp_cache_flush();
    }

    /**
     * Deactivation function.
     *
     * @static
     */
    public static function deactivate() {
      if ( ! current_user_can( 'activate_plugins' ) ) {
        return false;
      }

      delete_option( 'viabill_gateway_disabled' );

      self::register_constants();
      require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-notices.php' );

      $notices = new Viabill_Notices();
      $notices->destroy_cron();
    }

    /**
     * Return class instance.
     *
     * @static
     * @return Viabill_Main
     */
    public static function get_instance() {
      if ( is_null( self::$instance ) ) {
        self::$instance = new self();
      }
      return self::$instance;
    }

    /**
     * Cloning is forbidden.
     *
     * @since 0.1
     */
    public function __clone() {
      return wp_die( 'Cloning is forbidden!' );
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 0.1
     */
    public function __wakeup() {
      return wp_die( 'Unserializing instances is forbidden!' );
    }

    /**
     * Disable third party payment method with "viabill" name
     *
     * @since 1.1.11
     */
    public function disable_third_party_payment() {           
      $success = false;       

      // third party
      $option_key = 'woocommerce_viabill_settings';
      $payment_settings = get_option( $option_key, false );
      if ( get_option( $option_key, false ) ) {        
        if (is_array($payment_settings)) {
          if (isset($payment_settings['enabled'])) {
            $payment_settings['enabled'] = 'no';
            update_option( $option_key, $payment_settings, true );
            $success = true;
          }
        }
      }

      // viabill official
      if ($success) {
        if ( get_option( 'viabill_gateway_disabled', false ) ) {
          update_option( 'viabill_gateway_disabled', 0 );
        }

        $option_key = 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings';
        $payment_settings = get_option( $option_key, false );
        if ( get_option( $option_key, false ) ) {        
          if (is_array($payment_settings)) {
            if (isset($payment_settings['enabled'])) {
              $payment_settings['enabled'] = 'yes';
              update_option( $option_key, $payment_settings, true );              
            }
          }
        }
      }
      
      if ($success) {
        $user_msg = __( 'The third party payment method has been disabled successfully!!', 'viabill' );
      } else {
        $user_msg = __( 'The third party payment method could not be disabled. Perhaps it is not enabled, or the third party payment is no longer available.', 'viabill' );
      }      
      
      echo $user_msg;

      die();
    }
    
    /**
     * Disable third party payment method with "viabill" name
     *
     * @since 1.1.11
     */
    public static function get_disable_thrid_party_link() {
      $link = get_site_url(null, 'wp-json/viabill/disable-payment/thirdparty');
      return $link;
    }

    /**
     * Check to see if we need to change/update the viabill options,
     * stored in the database
     * 
     * @since 1.1.11
     */
    public function check_backward_compatibility() {
       
    }

  }
}

register_activation_hook( __FILE__, array( 'Viabill_Main', 'install' ) );
register_uninstall_hook( __FILE__, array( 'Viabill_Main', 'uninstall' ) );
register_deactivation_hook( __FILE__, array( 'Viabill_Main', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Viabill_Main', 'get_instance' ), 0 );

add_action('rest_api_init', function () {
  register_rest_route( 'viabill', 'disable-payment/thirdparty', array (
    'methods'  => 'GET',
    'callback' => array( 'Viabill_Main', 'disable_third_party_payment' ),
    'permission_callback' => function() { return true; }
  ));
});

if ( ! function_exists( 'wkwc_is_wc_order' ) ) {
  /**
   * Check is post WooCommerce order.
   *
   * @param int $post_id Post id.
   *
   * @return bool $bool True|false.
   */
  function wkwc_is_wc_order( $post_id = 0 ) {
      $bool = false;
      if ( 'shop_order' === OrderUtil::get_order_type( $post_id ) ) {
          $bool = true;
      }
      return $bool;
  }
}
