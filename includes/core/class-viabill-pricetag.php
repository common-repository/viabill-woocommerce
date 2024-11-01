<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Pricetag' ) ) {
  /**
   * Viabill_Pricetag class
   */
  class Viabill_Pricetag {
    /**
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;

    /**
     * Merchant's profile, object which holds merchant's data and it's methods.
     *
     * @var Viabill_Merchant_Profile
     */
    private $merchant;

    /**
     * Array of languages where the key is supported language and the value is
     * actual language code which is used.
     *
     * @var array
     */
    public static $supported_languages = array(
      'da' => 'da',
      'en' => 'en',
      'es' => 'es',
      'eu' => 'es',
      'ca' => 'es'
    );

    /**
     * Array of currencies where the key is supported supported and the value is
     * actual currency code which is used.
     *
     * @var array
     */
    public static $supported_currencies = array(
      'usd' => 'USD',
      'eur' => 'EUR',
      'dkk' => 'DKK'      
    );

    /**
     * Array of countries where the key is supported supported and the value is
     * actual country code which is used.
     *
     * @var array
     */
    public static $supported_countries  = array(
      'es' => 'ES',
      'spain' => 'ES',
      'dk' => 'DK',
      'denmark' => 'DK',
      'us' => 'US',
      'usa' => 'US',
    );

    /**
     * Contains all the payment gateway settings values.
     *
     * @var array
     */
    private $settings;

    public function __construct() {
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-merchant-profile.php' );

      $this->merchant = new Viabill_Merchant_Profile();
      $this->settings = Viabill_Main::get_gateway_settings();

      $this->add_pre_tab_settings_load();

      add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_pricetag_setttings_tab' ), 50 );
      add_action( 'woocommerce_settings_tabs_viabill_pricetag', array( $this, 'output_settings_tab' ) );
      add_action( 'woocommerce_update_options_viabill_pricetag', array( $this, 'update_tab_settings' ) );
    }

    /**
     * Show priceTag on different pages if enabled on the wc settings panel.
     *
     * @return void
     */
    public function maybe_show() {
      $payment_gateway_enabled = isset( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
      $pricetags_enabled       = isset( $this->settings['pricetag-enabled'] ) && 'yes' !== $this->settings['pricetag-enabled'];
            
      // If PriceTag settings are not explicitly set fallback to payment gateway enabled setting.
      if ( ( ! isset( $this->settings['pricetag-enabled'] ) && ! $payment_gateway_enabled ) || $pricetags_enabled ) {
        return;
      }

      $valid_combination = self::getValidCountryLanguageCurrencyCombination();
      if (! $valid_combination ) {
         return;
      }

      if ( ( ! isset( $this->settings['pricetag-on-product'] ) && $payment_gateway_enabled ) || 'yes' === $this->settings['pricetag-on-product'] ) {        
        $action_hook_name = isset( $this->settings['pricetag-product-hook'] )? $this->settings['pricetag-product-hook'] : 'woocommerce_single_product_summary';
        // or 'woocommerce_before_add_to_cart_form'

        add_action( 'viabill_pricetag_on_single_product', array( 'Viabill_Pricetag', 'show_on_product' ) );
        add_action( $action_hook_name, array( 'Viabill_Pricetag', 'show_on_product' ) );        
      }
      if ( ( ! isset( $this->settings['pricetag-on-cart'] ) && $payment_gateway_enabled ) || 'yes' === $this->settings['pricetag-on-cart'] ) {
        add_action( 'viabill_pricetag_on_cart', array( 'Viabill_Pricetag', 'show_on_cart' ) );
        add_action( 'woocommerce_proceed_to_checkout', array( 'Viabill_Pricetag', 'show_on_cart' ) );
      }
      if ( ( ! isset( $this->settings['pricetag-on-checkout'] ) && $payment_gateway_enabled ) || 'yes' === $this->settings['pricetag-on-checkout'] ) {
        // add_action( 'viabill_pricetag_on_checkout', array( 'Viabill_Pricetag', 'show_on_checkout' ) );
        // Check if payment gateway enabled since PriceTag is displayed in the payment method description.
        if ( ! Viabill_Main::is_payment_gateway_disabled() ) {
          // add_action( 'viabill_pricetag_after_payment_description', array( 'Viabill_Pricetag', 'show_on_checkout' ) );
          add_action( 'viabill_pricetag_after_monthly_payment_description', array( 'Viabill_Pricetag', 'show_on_monthly_checkout' ) );
          add_action( 'viabill_pricetag_after_tbyb_payment_description', array( 'Viabill_Pricetag', 'show_on_tbyb_checkout' ) );
        } else {
          add_action( 'woocommerce_review_order_before_submit', array( 'Viabill_Pricetag', 'show_on_checkout' ) );
        }
      }

      $this->append_script();
    }

    /**
     * Checks if the currently selected currency, language and country code are valid for the 
     * pricetags to appear.
     */
    public static function getValidCountryLanguageCurrencyCombination() {
      $valid = false;

      $country = self::get_supported_country();
      $currency = self::get_supported_currency();
      $language = self::get_supported_language();      
      
      if ((!empty($country))&&(!empty($language))&&(!empty($currency))) {
        switch ($country) {
          case 'US':
            if (($language != 'en')&& ($language != 'es')) {
              $language = 'en';
            }            
            if ($currency != 'USD') {
               $valid = false;
            } else {              
              $valid = true;
            }            
            break;

          case 'ES':
            if (($language != 'en')&& ($language != 'es')) {
              $language = 'es';
            }
            if ($currency != 'EUR') {
               $valid = false;
            } else {              
              $valid = true;
            }
            break;
              
          case 'DK':
            if (($language != 'da')&& ($language != 'en')) {
              $language = 'da';
            }
            if ($currency != 'DKK') {
               $valid = false;
            } else {              
              $valid = true;
            }
            break;    

          default:
            // unsupported country, do nothing
            break;  
        }                 
      }         
      
      if ($valid) {
        $combination['language'] = $language;
        $combination['currency'] = $currency;
        $combination['country'] = $country;
        return $combination;
      }
      
      return false;
    }

    /**
     * Echo script after the </body> tag.
     * Uses 'wp_print_footer_scripts' action hook.
     *
     * @return void
     */
    private function append_script() {
      add_action(
        'wp_print_footer_scripts',
        function() {
          $script = $this->merchant->get_pricetag();
          if ( is_string( $script ) && ! empty( $script ) ) {
            echo $script;
          }          
        },
        100
      );
    }
  
    /**
     * Echo data-dynamic-price HTML attribute if the value is available in the
     * plugin's settings under the key "pricetag-{$target}-dynamic-price".
     *
     * @param  string $target   Should be "product", "cart", or "checkout".
     * @param  array  $settings
     * @return void
     */
    public static function display_dynamic_price( $target, $settings ) {
      $name = 'pricetag-' . esc_attr($target) . '-dynamic-price';
      if ( isset( $settings[ $name ] ) && ! empty( $settings[ $name ] ) ) {
        echo 'data-dynamic-price="' . esc_attr($settings[ $name ]) . '"';
      }
    }

    /**
     * Echo data-dynamic-price-triggers HTML attribute if the value is available
     * in the plugin's settings under the key
     * "pricetag-{$target}-dynamic-price-triggers".
     *
     * @param  string $target   Should be "product", "cart", or "checkout".
     * @param  array  $settings
     * @return void
     */
    public static function display_dynamic_price_trigger( $target, $settings ) {
      $name = 'pricetag-' . esc_attr($target) . '-dynamic-price-trigger';
      if ( isset( $settings[ $name ] ) && ! empty( $settings[ $name ] ) ) {
        echo 'data-dynamic-price-triggers="' . esc_attr($settings[ $name ]) . '"';
      }
    }

    /**
     * Display pricetag on the single product page.
     *
     * @static
     * @return void
     */
    public static function show_on_product($inplace = false) {
      if ( ! is_product() ) {
        return;
      }

      global $product;
      $settings = get_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings', array() );
      $settings['inplace'] = $inplace;

      self::show( 'product', 'product', wc_get_price_including_tax( $product ), $settings );
    }

    /**
     * Dislay pricetag on the cart page.
     *
     * @static
     * @return void
     */
    public static function show_on_cart($inplace = false) {
      $settings = get_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings', array() );
      $settings['inplace'] = $inplace;

      // Check if WC() and WC()->cart are available
  	  if ( function_exists( 'WC' ) && WC() && isset( WC()->cart ) ) {
        $totals = WC()->cart->get_totals();
        $total = isset( $totals['total'] ) ? $totals['total'] : 0;
      } else {
        // Handle the case when WC() or WC()->cart is not available
        $total = 0; // Default value or handle differently
      }
           
      if ($inplace) { 		   	    
        return self::show( 'basket', 'cart', $total, $settings );
      } else {
        self::show( 'basket', 'cart', $total, $settings );
      }      
    }

    /**
     * Display pricetag on the checkout page.
     *
     * @return void
     */
    public static function show_on_checkout($payment_method = 'monthly', $inplace = false) {
      $settings = get_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings', array() );
      $settings['inplace'] = $inplace;

      $totals = WC()->cart->get_totals();
      $total  = isset( $totals['total'] ) ? $totals['total'] : 0;
      self::show( 'payment', 'checkout', $total, $settings, $payment_method);
    }

    /**
     * Display pricetag on the checkout page for the monthly payments.
     *
     * @return void
     */
    public static function show_on_monthly_checkout($inplace = false) {
      $payment_method = 'monthly';
      self::show_on_checkout($payment_method, $inplace);      
    }

    /**
     * Display pricetag on the checkout page for the "Try Before you Buy" method.
     *
     * @return void
     */
    public static function show_on_tbyb_checkout($inplace = false) {
      $payment_method = 'tbyb';
      self::show_on_checkout($payment_method, $inplace);      
    }

    /**
     * Display priceTag with the give parameters.
     *
     * @param  string           $view
     * @param  string           $target
     * @param  string|int|float $price
     * @param  array            $settings
     * @return void
     */
    public static function show( $view, $target, $price, $settings, $payment_method = null ) {
      $dynamic_price         = 'pricetag-' . $target . '-dynamic-price';
      $dynamic_price_trigger = $dynamic_price . '-trigger';
      $position_field        = 'pricetag-position-' . $target;
      $position              = Viabill_Main::get_gateway_settings( 'pricetag-position-' . $target );
      $position_inplace      = (isset($settings['inplace']))?$settings['inplace']:false;
      $style                 = Viabill_Main::get_gateway_settings( 'pricetag-style-' . $target );      
      $combination           = self::getValidCountryLanguageCurrencyCombination();

      // if no valid combination found, do not display the pricetag
      if (!$combination) {
        $currency = self::get_supported_currency();
        $language = self::get_supported_language();          
        $country = self::get_supported_country();
        echo '<span style="display:none">No pricetag is shown as there is an invalid combination of language ['.$language.'] /currency ['.$currency.'] /country ['.$country.']</span>';
        return;
      } 
      
      $language = $combination['language'];
      $currency = $combination['currency'];
      $country = $combination['country'];

      // Do you want to differentiate between "monthly" and "try before you buy"?
      $product_types_str = '';
      switch ($target) {        
        case 'product':
          break;
        case 'cart':
          break;
        case 'checkout':
          if ($payment_method == 'tbyb') {
            $product_types = ['tbyb'];
          } else {
            $product_types = ['light','liberty','plus'];
          }
          $product_types_str = 'data-checkout-product-types=\''.json_encode($product_types).'\'';

          break;
      }        

      $attrs = array_filter(
        array(
          'view'                   => $view,
          'price'                  => $price,
          'currency'               => $currency,
          'country-code'           => $country,
          'language'               => $language,
          'dynamic-price'          => isset( $settings[ $dynamic_price ] ) && ! empty( $settings[ $dynamic_price ] ) ? $settings[ $dynamic_price ] : '',
          'dynamic-price-triggers' => isset( $settings[ $dynamic_price_trigger ] ) && ! empty( $settings[ $dynamic_price_trigger ] ) ? $settings[ $dynamic_price_trigger ] : '',          
        )
      );
      
      $style_html = (!empty($style)) ? 'style="'.esc_attr($style).'"' : '';  
      
      $html = '<div class="viabill-pricetag-wrap" '.$style_html.'><div '.$product_types_str.' ';
      foreach ($attrs as $attr_name => $attr_value) {
        $html .= 'data-' . esc_attr($attr_name) . '="' . esc_attr($attr_value) . '" ';
      } 

      // If there is a jQuery selector saved for position render the selector and add class via javascript to trigger script.
      if ($position_inplace) {
        $html .= 'class="viabill-pricetag" ';
      } else if ( $position ) {
        $html .= 'data-append-target="' . esc_attr($position) . '" ';            
      } else {
        $html .= 'class="viabill-pricetag" ';
      }   

      $html .= '></div></div>';
            
      if ($position_inplace) {		  
        return $html;
      } else {
        echo $html;
      }        
    }

    /**
     * Extract and return current language code from locale or false if not
     * supported.
     *
     * @return string|boolean
     */
    public static function get_supported_language() {
      $locale   = get_locale();
      $language = null;

      if ( strpos( $locale, '_' ) !== false ) {
        $locale_parts = explode( '_', $locale );
        $language     = $locale_parts[0];
      } elseif ( strlen( $locale ) === 2 ) {
        $language = $locale;
      }

      if ( array_key_exists( $language, self::$supported_languages ) ) {
        return self::$supported_languages[ $language ];
      } else {
        return false;
      }
    }

    /**
     * Extract and return current currency code or false if not supported
     */
    public static function get_supported_currency() {
      $currency = get_woocommerce_currency();
      if (empty($currency)) true; // this should never happen

      $currency = strtolower($currency);
      
      if ( array_key_exists( $currency, self::$supported_currencies ) ) {
        return self::$supported_currencies[ $currency ];
      } else {
        return false;
      }
    }

    /**
     * Extract and return current currency code or false if not supported
     */
    public static function get_supported_country() {
      $country = wc_get_base_location()['country'];
      if (empty($country)) true; // this should never happen

      $country = strtolower($country);
      
      if ( array_key_exists( $country, self::$supported_countries ) ) {
        return self::$supported_countries[ $country ];
      } else {
        return false;
      }
    }

    /**
     * Add a option name based filter for loading settings to PriceTag settings tab.
     *
     * @return void
     */
    public function add_pre_tab_settings_load() {
      $gateway_settings = $this->get_pricetag_settings();
      array_walk(
        $gateway_settings,
        function( $data, $id ) {
          add_filter( 'pre_option_' . $id, array( $this, 'pre_tab_settings_load' ), 10, 2 );
        }
      );
    }

    /**
     * For specified PriceTag settings fields load value from viabill gateway setttings.
     *
     * @param mixed  $value Value which to return.
     * @param string $field Name of the field.
     *
     * @return mixed/bool
     */
    public function pre_tab_settings_load( $value, $field ) {
      return Viabill_Main::get_gateway_settings( $field );
    }

    /**
     * Add custom settings tab for ViaBill PriceTag to WooCommerce settings tabs
     *
     * @param array $settings_tabs WooCommerce settings tabs.
     *
     * @return array
     */
    public function add_pricetag_setttings_tab( $settings_tabs ) {
      $settings_tabs['viabill_pricetag'] = __( 'ViaBill PriceTags', 'viabill' );
      return $settings_tabs;
    }

    /**
     * Fetch the PriceTag fields and display them in the ViaBill PriceTag settings tab.
     *
     * @return void
     */
    public function output_settings_tab() {
      ?>
      <h2><?php esc_html_e( 'ViaBill PriceTags', 'viabill' ); ?></h2>
     
      <a class="button-secondary" href="<?php echo esc_attr( Viabill_Main::get_settings_link() ); ?>"><?php esc_html_e( 'ViaBill settings', 'viabill' ); ?></a>
      
      <?php
      woocommerce_admin_fields( $this->get_pricetag_settings() );
    }

    /**
     * Format PriceTag fields values and save them to gateway settings option.
     *
     * @return void
     */
    public function update_tab_settings() {
      $pricetag_settings = $this->get_pricetag_settings( true );

      array_walk(
        $pricetag_settings,
        function( &$field, $id, $new_data ) {
          $raw_value = isset( $new_data[ $id ] ) ? wp_unslash( $new_data[ $id ] ) : null;

          if ( 'checkbox' === $field['type'] ) {
            $field = '1' === $raw_value || 'yes' === $raw_value ? 'yes' : 'no';
          } elseif ( strpos( $id, 'style' ) ) {
            $field = wp_strip_all_tags( $raw_value );
          } else {
            $field = $raw_value;
          }
        },
        $_POST // WPCS: input var okay, CSRF ok.
      );

      update_option( 'woocommerce_' . VIABILL_PLUGIN_ID . '_settings', array_merge( Viabill_Main::get_gateway_settings(), $pricetag_settings ) );
    }

    /**
     * Fetch array of fields for ViaBill PriceTag settings tab.
     *
     * @param string $save_format Remove titles and section separators for database entry.
     *
     * @return array
     */
    public function get_pricetag_settings( $save_format = false ) {
      $settings = include( VIABILL_DIR_PATH . '/includes/utilities/viabill-settings-fields-pricetag.php' );

      if ( $save_format ) {
        $settings = array_filter(
          $settings,
          function( $field ) {
            return ! in_array( $field['type'], [ 'title', 'sectionend' ], true );
          }
        );
      }

      return $settings;
    }
  }
}
