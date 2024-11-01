<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Registration' ) ) {
  /**
   * Viabill_Registration class
   */
  class Viabill_Registration {
    /**
     * API's interface.
     *
     * @var Viabill_Connector
     */
    private $connector;

    /**
     * Merchant's profile, object which holds merchant's data and it's methods.
     *
     * @var Viabill_Merchant_Profile
     */
    private $merchant;

    /**
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;

    /**
     * Was the registration already tried.
     *
     * @var bool
     */
    public static $tried_to_register = false;

    /**
     * ViaBill's URL for terms and conditions.
     *
     * @var string
     */
    private $terms_url = 'https://viabill.com/trade-terms/';

    /**
     * Registration page slug.
     *
     * @static
     * @var string
     */
    const SLUG = 'viabill-register';

    /**
     * Class constructor, initialize class attributes and defines hooked methods.
     *
     * @param boolean $register_settings_page Defaults to false.
     */
    public function __construct( $register_settings_page = false ) {
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-notices.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-connector.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-merchant-profile.php' );
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );

      $this->connector = new Viabill_Connector();
      $this->merchant  = new Viabill_Merchant_Profile();
      // Enabled by default because at this point gateway is always disabled.
      $this->logger = new Viabill_Logger( true );

      add_action( 'admin_init', array( $this, 'maybe_process' ) );

      if ( $register_settings_page && ! Viabill_Main::is_merchant_registered() ) {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ), 200 );
      }
    }

    /**
     * Return registration page admin URL.
     *
     * @return string
     */
    public static function get_admin_url() {
      return get_admin_url( null, 'admin.php?page=' . self::SLUG );
    }

    /**
     * Trigger process method and, if successful, redirect to notices page.
     */
    public function maybe_process() {
      if ( self::$tried_to_register ) {
        return false;
      }
      self::$tried_to_register = true;

      $response = $this->process();
      if ( is_array( $response ) ) {
        if ( isset( $response['success'] ) && $response['success'] ) {
          wp_safe_redirect( Viabill_Main::get_settings_link() );
          exit;
        } else {
          $this->reg_response = $response;
          add_action( 'admin_notices', array( $this, 'show_register_wp_notice' ) );
        }
      }
    }

    /**
     * Process registration or login (fetch data from $_POST) and return
     * an array with the following structure:
     *    ['success' => bool, 'message' => string]
     * or false if registration already tried.
     *
     * @return array|bool
     */
    private function process() {
      $response = array(
        'success' => false,
        'message' => __( 'Something went wrong, please try again.', 'viabill' ),
      );

      if ( isset( $_POST[ self::SLUG ] ) && isset( $_POST[ self::SLUG . '-nonce' ] ) ) {
        $nonce = sanitize_key( $_POST[ self::SLUG . '-nonce' ] );
        if ( wp_verify_nonce( $nonce, self::SLUG . '-action' ) === 1 ) {
          $country = isset( $_POST['viabill-reg-country'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['viabill-reg-country'] ) ) ) : '';

          $email = isset( $_POST['viabill-reg-email'] ) ? sanitize_email( wp_unslash( $_POST['viabill-reg-email'] ) ) : '';

          $name = isset( $_POST['viabill-reg-contact-name'] ) ? sanitize_text_field( wp_unslash( $_POST['viabill-reg-contact-name'] ) ) : '';

          $tax_id = isset( $_POST['viabill-reg-taxid'] ) ? $this->sanitize_tax_id( wp_unslash( $_POST['viabill-reg-taxid'] ), $country ) : '';
          
          if (empty($tax_id)) {
            $response = array(
              'success' => false,
              'message' => __( 'Tax ID cannot be empty, or contain an invalid value. Please try again.', 'viabill' ),
            );    
            return $response;
          }

          $additional_data = array(
            isset( $_POST['viabill-reg-shop-url'] ) ? esc_url_raw( wp_unslash( $_POST['viabill-reg-shop-url'] ) ) : '',
            isset( $_POST['viabill-reg-phone'] ) ? sanitize_text_field( wp_unslash( $_POST['viabill-reg-phone'] ) ) : '',
          );

          $body = $this->connector->register( $email, $name, $country, $tax_id, $additional_data );
          $this->process_response_body( $response, $body );
          $this->logger->log( $response['message'], $response['success'] ? 'info' : 'critical' );
          
          return $response;
        } else {
          $this->logger->log( 'Failed to verify nonce \'' . $nonce . '\' while trying to register the merchant.', 'alert' );
          return $response;
        }
      } elseif ( isset( $_POST['viabill-login'] ) && isset( $_POST['viabill-login-nonce'] ) ) {
        $nonce = sanitize_key( $_POST['viabill-login-nonce'] );
        if ( wp_verify_nonce( $nonce, 'viabill-login-action' ) === 1 ) {

          $password = isset( $_POST['viabill-login-password'] ) ? sanitize_text_field( wp_unslash( $_POST['viabill-login-password'] ) ) : '';
          $email    = isset( $_POST['viabill-login-email'] ) ? sanitize_email( wp_unslash( $_POST['viabill-login-email'] ) ) : '';

          $body = $this->connector->login( $email, $password );
          $this->process_response_body( $response, $body, true );
          $this->logger->log( $response['message'], $response['success'] ? 'info' : 'critical' );

          return $response;
        } else {
          $this->logger->log( 'Failed to verify nonce while trying to log in the merchant.', 'alert' );
          return $response;
        }
      }
      return false;
    }

    /**
     * Process registration response body from the ViaBill's API and define
     * $response's message and success bool flag.
     *
     * @param  array &$response
     * @param  array $body
     * @param  bool  $is_body   Defaults to false.
     * @return void
     */
    private function process_response_body( &$response, $body, $is_login = false ) {
      if ( ! $body ) {
        if ( $is_login ) {
          $response['message'] = __( 'Failed to login, please try again.', 'viabill' );
        } else {
          $response['message'] = __( 'Failed to register, please try again.', 'viabill' );
        }
        return;
      }

      $error_messages = $this->connector->get_error_messages( $body );
      if ( is_string( $error_messages ) ) {
        $response['message'] = $error_messages;
      } else {
        $is_saved = $this->merchant->save_registration_data( $body );
        if ( $is_saved ) {
          $response['success'] = true;
          if ( $is_login ) {
            $response['message'] = __( 'User is successfully logged-in.', 'viabill' );
          } else {
            $response['message'] = __( 'User is successfully registered.', 'viabill' );
          }
        }
      }
    }

    /**
     * Echo merchant's registration/login form.
     */
    public function show() {
      $countries = $this->connector->get_available_countries();
      array_unshift(
        $countries,
        array(
          'code' => '',
          'name' => 'Choose Country',
        )
      );
      ?>
      <form method="post">
        <h2><?php esc_html_e( 'New to ViaBill?', 'viabill' ); ?></h2>
        <p><?php esc_html_e( 'Create your ViaBill account by typing in your e-mail and selecting your country.', 'viabill' ); ?></p>
        <table class="form-table">
          <tbody>
            <?php
            $this->do_field(
              __( 'E-mail', 'viabill' ),
              array(
                'id'       => 'viabill-reg-email',
                'name'     => 'viabill-reg-email',
                'type'     => 'email',
                'required' => true,
              )
            );
            ?>

            <?php
            $current_user_id = get_current_user_id();
            $user_data       = get_userdata( $current_user_id );
            $user_phone      = get_user_meta( $current_user_id, 'billing_phone', true );
            ?>

            <?php
            $this->do_field(
              __( 'Contact name', 'viabill' ),
              array(
                'id'    => 'viabill-reg-contact-name',
                'name'  => 'viabill-reg-contact-name',
                'type'  => 'text',
                'value' => $user_data->display_name ? $user_data->display_name : '',
                'class' => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'Country', 'viabill' ),
              array(
                'id'       => 'viabill-reg-country',
                'name'     => 'viabill-reg-country',
                'type'     => 'select',
                'class'    => 'select',
                'style'    => 'min-width: 215px;',
                'required' => true,
              ),
              $countries
            );
            ?>

            <?php
            $this->do_field(
              __( 'Shop URL (live)', 'viabill' ),
              array(
                'id'       => 'viabill-reg-shop-url',
                'name'     => 'viabill-reg-shop-url',
                'type'     => 'text',
                'value'    => get_site_url(),
                'class'    => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>
            
            <?php
            $this->do_field(
              __( 'Phone number', 'viabill' ),
              array(
                'id'    => 'viabill-reg-phone',
                'name'  => 'viabill-reg-phone',
                'type'  => 'phone',
                'value' => $user_phone,
                'class' => 'input-text regular-input',
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'Tax ID', 'viabill' ),
              array(
                'id'    => 'viabill-reg-taxid',
                'name'  => 'viabill-reg-taxid',
                'type'  => 'text',
                'value' => '',
                'class' => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>

            <?php
            $terms_label  = __( 'I\'ve read and accept the', 'viabill' ) . ' ';
            $terms_label .= '<a id="viabill-terms-link" href="' . $this->terms_url . '" target="_blank">' . __( 'terms & conditions', 'viabill' ) . '</a>';

            $this->do_field(
              $terms_label,
              array(
                'id'       => 'viabill-terms',
                'name'     => 'viabill-terms',
                'type'     => 'checkbox',
                'required' => true,
              )
            );
            ?>

            <?php $this->do_submit( self::SLUG, __( 'Register', 'viabill' ), self::SLUG . '-action', self::SLUG . '-nonce' ); ?>
          </tbody>
        </table>
      </form>
      <form method="post">
        <table class="form-table">
          <tbody>
            <tr valign="top">
              <th scope="row" class="titledesc"></th>
              <td class="forminp">
                <h3>- <?php esc_html_e( 'OR', 'viabill' ); ?> -</h3>
              </td>
            </tr>
          </tbody>
        </table>
        <h2><?php esc_html_e( 'Already have a ViaBill account?', 'viabill' ); ?></h2>
        <p><?php esc_html_e( 'Log in with your e-mail and password.', 'viabill' ); ?></p>
        <table class="form-table">
          <tbody>
            <?php
            $this->do_field(
              __( 'E-mail', 'viabill' ),
              array(
                'id'       => 'viabill-login-email',
                'name'     => 'viabill-login-email',
                'type'     => 'email',
                'class'    => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>

            <?php
            $this->do_field(
              __( 'Password', 'viabill' ),
              array(
                'id'       => 'viabill-login-password',
                'name'     => 'viabill-login-password',
                'type'     => 'password',
                'class'    => 'input-text regular-input',
                'required' => true,
              )
            );
            ?>

            <tr valign="top">
              <th scope="row" class="titledesc"></th>
              <td class="forminp">
                <?php
                $lang_iso2 = 'en';
                $lang = get_locale();
                if (strlen($lang)>2) {
                  $upos = strpos($lang, '_');
                  if ($upos>0) {
                    $lang_code = strtolower(substr($lang, 0, $upos));
                    switch ($lang_code) {
                      case 'da':
                      case 'es':
                      case 'en':
                      $lang_iso2 = $lang_code;
                      break;
                    }
                  }
                }
                $forgot_pass_url = 'https://my.viabill.com/merchant/'.$lang_iso2.'/#/auth/forgot';
                ?>                
                <a href="<?php echo esc_url($forgot_pass_url); ?>" target="_blank"><?php esc_html_e( 'Forgot password?', 'viabill' ); ?></a>
              </td>
            </tr>
            <?php $this->do_submit( 'viabill-login', __( 'Login', 'viabill' ), 'viabill-login-action', 'viabill-login-nonce' ); ?>
          </tbody>
        </table>
      </form>
      <?php
    }

    /**
     * Echo registration submit button HTML.
     *
     * @param string $id
     * @param string $label
     * @param string $nonce_action
     * @param string $nonce_name
     */
    private function do_submit( $id, $label, $nonce_action, $nonce_name ) {
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc"></th>
        <td class="forminp">
          <input id="<?php echo $id; ?>" name="<?php echo $id; ?>" class="button-primary woocommerce-save-button" type="submit" value="<?php echo $label; ?>">
          <?php wp_nonce_field( $nonce_action, $nonce_name ); ?>
        </td>
      </tr>
      <?php
    }

    /**
     * Echo registration field's HTML.
     *
     * @param  string $label    Label for input or standalone
     * @param  array  $args     Different attributes:
     *                          array (
     *                            'id'       => 'test_id',
     *                            'name'     => 'test_name',
     *                            'value'    => '',
     *                            'required' => false
     *                          )
     * @param  array  $options  Defaults to empty array.
     */
    private function do_field( $label = '', $args = array(), $options = array() ) {
      array_walk(
        $args,
        function( $attr_val, $attr_name ) use ( &$attrs ) {
          $attrs .= $attr_name . '=\'' . $attr_val . '\' ';
        }
      );
      ?>
      <tr valign="top">
        <th scope="row" class="titledesc">
          <?php if ( ! empty( $label ) && 'checkbox' !== $args['type'] ) : ?>
            <label for="<?php echo $args['id']; ?>"><?php echo $label; ?></label>
          <?php endif; ?>
        </th>
        <td class="forminp">
          <?php if ( 'select' === $args['type'] && $options ) : ?>
            <select <?php echo $attrs ?>>
              <?php foreach ( $options as $option ) : ?>
                <option value="<?php echo $option['code']; ?>"><?php echo $option['name']; ?></option>
              <?php endforeach; ?>
            </select>
          <?php else : ?>
            <input <?php echo $attrs ?>>
            <?php if ( 'checkbox' === $args['type'] && ! empty( $label ) ) : ?>
              <label for="<?php echo $args['id']; ?>"><?php echo $label; ?></label>
            <?php endif; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php
    }

    /**
     * Echo register notice HTML.
     */
    public function show_register_wp_notice() {
      $type    = isset( $this->reg_response['success'] ) && $this->reg_response['success'] ? 'success' : 'error';
      $message = isset( $this->reg_response['message'] ) ? $this->reg_response['message'] : false;
      if ( ! $message ) {
        if ( 'success' === $type ) {
          $message = __( 'Registration didn\'t went as expected but, for now, everything seems alright.', 'viabill' );
        } else {
          $message = __( 'Something went wrong, please try again.', 'viabill' );
        }
      }
      ?>
      <div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
          <p><?php echo esc_html( $message ); ?></p>
      </div>
      <?php
    }

    /**
     * Register WooCommerce settings subpage.
     */
    public function register_settings_page() {
      add_submenu_page(
        'woocommerce',
        __( 'ViaBill Login/Register', 'viabill' ),
        __( 'ViaBill Login/Register', 'viabill' ),
        'manage_woocommerce',
        self::SLUG,
        array( $this, 'show' )
      );
    }

    /**
     * Sanitize and format the Tax ID (if given)
     */
    public function sanitize_tax_id($tax_id, $country) {
       $tax_id = str_replace(array(' ','-'), '', trim($tax_id));
       if ($country == 'ES') {        
        $regex_with_prefix = '/^ES[0-9A-Z]*/';
        if (preg_match($regex_with_prefix, $tax_id)) {
          return $tax_id;
        }
        $regex_without_prefix = '/^[0-9A-Z]+/';
        if (preg_match($regex_without_prefix, $tax_id)) {
          return 'ES'.$tax_id;
        }
       } else if ($country == 'DK') {
         $regex_with_prefix = '/^DK[0-9]{8}$/';
         if (preg_match($regex_with_prefix, $tax_id)) {
          return $tax_id;
         }
         $regex_without_prefix = '/^[0-9]{8}$/';
         if (preg_match($regex_without_prefix, $tax_id)) {
          return 'DK'.$tax_id;
         }
       }
       return '';
    }
  }
}
