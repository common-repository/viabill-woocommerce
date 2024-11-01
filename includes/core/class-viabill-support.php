<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Support' ) ) {
  /**
   * Viabill_Support class
   *
   * @since 0.1
   */
  class Viabill_Support {

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
     * Logger.
     *
     * @var Viabill_Logger
     */
    private $logger;    

    /**
     * Settings page slug.
     *
     * @static
     * @var string
     */
    const SLUG = 'viabill-support';

    /**
     * Contact email address
     */
    const VIABILL_TECH_SUPPORT_EMAIL = 'tech@viabill.com';

    /**
     * Number of lines to read from the log files (debug and error).
     */
    const LOG_FILE_LINES_TO_READ = 150;

    /**
     * Class constructor, initialize class attributes and hooks if $initialize
     * is set to true.
     *
     * @param boolean $initialize Defaults to false.
     */
    public function __construct( $initialize = false ) {
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-connector.php' );
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-merchant-profile.php' );
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );

      $this->merchant  = new Viabill_Merchant_Profile();
      $this->connector = new Viabill_Connector();

      $settings     = Viabill_Main::get_gateway_settings();
      $this->logger = new Viabill_Logger( isset( $settings['use-logger'] ) && 'yes' === $settings['use-logger'] );
      
      if ( $initialize ) {
        $this->init();
      }
    }

    /**
     * Initialize action hooks for support page
     *
     * @return void
     */
    public function init() {            
      if ( Viabill_Main::is_merchant_registered() ) {        
        add_action( 'admin_menu', array( $this, 'register_settings_page' ), 200 );        
        add_action( 'admin_post_reset_viabill_account', array( $this, 'reset_viabill_account' ), 200 );        
      }
    }

    /**
     * Return support settings page URL.
     *
     * @static
     * @return string
     */
    public static function get_admin_url() {
      return get_admin_url( null, 'admin.php?page=' . self::SLUG );
    }

    /**
     * Display support or registration form if user not registered.
     */
    public function show() {

      ?>
      <style>
        .form-control { width: 100%; }
        fieldset { margin-top: 10px; }
        legend { font-weight: bold; color: #A0A0A0; margin-bottom: 5px; }
        label { font-weight: bold; margin-top: 10px; margin-bottom: 10px; }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-info {
            color: #31708f;
            background-color: #d9edf7;
            border-color: #bce8f1;
        }
        .alert-success {
            color: #3c763d;
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }
        .alert-warning {
            color: #8a6d3b;
            background-color: #fcf8e3;
            border-color: #faebcc;
        }
        .alert-danger {
            color: #a94442;
            background-color: #f2dede;
            border-color: #ebccd1;
        }
      </style>  
      <?php

      if (isset($_REQUEST['ticket_info'])) {
         $result = $this->getContactFormOutput();
         echo $result;
         return;
      }        

      $params = $this->getContactForm();
      if (isset($params['error'])) {
          echo '<div class="alert alert-danger" role="alert">'.$params['error'].'</div>';
          return;
      } else {
         foreach ($params as $key => $value) {
           $$key = $value;
         }
      }  
      
      $action_url = $this->getActionURL();

      $account_reset_url = admin_url('admin-post.php?action=reset_viabill_account');      

      ?>    

    <div class="wrap">
    <h2><?php echo __( 'ViaBill Support', 'viabill' ); ?></h2>
    <br>
    <a class="button-secondary" href="<?php echo esc_attr( Viabill_Main::get_settings_link() ); ?>"><?php echo __( 'ViaBill settings', 'viabill' ); ?></a>
    <br><br><br>  
    
    <h3><?php echo __( 'Support Request Form');?></h3>
    <div class="alert alert-info" role="alert">
        <?php 
        echo __( 'Please fill out the form below and click on the', 'viabill');
        echo ' <em>';
        echo __( 'Send Support Request', 'viabill');
        echo '</em> ';
        echo __( 'button to send your request', 'viabill'); 
        ?>        
    </div>
    <form id="tech_support_form" action="<?php echo esc_url($action_url); ?>" method="post">
    <fieldset>
        <legend class="w-auto text-primary"><?php echo __( 'Issue Description', 'viabill'); ?></legend>
        <div class="form-group">
            <label><?php echo __( 'Your Name', 'viabill');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[name]"
                 value="" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Your Email', 'viabill');?></label>
            <input class="form-control" type="text" required="true" name="ticket_info[email]"
                 value="" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Message', 'viabill');?></label>
            <textarea class="form-control" name="ticket_info[issue]" 
            placeholder="<?php echo __( 'Type your issue description here ...', 'viabill'); ?>" rows="10" required="true"></textarea>
        </div>
    </fieldset>
    <fieldset>
        <legend class="w-auto text-primary"><?php echo __( 'Eshop Info', 'viabill');?></legend>
        <div class="form-group">
            <label><?php echo __( 'Store Name', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
                 value="<?php echo esc_attr($storeName); ?>" name="shop_info[name]" />
        </div>                
        <div class="form-group">
            <label><?php echo __( 'Store URL', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_url($storeURL); ?>" name="shop_info[url]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Store Email', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo sanitize_email($storeEmail); ?>" name="shop_info[email]" />             
        </div>
        <div class="form-group">
            <label><?php echo __( 'Api Key', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo  esc_attr($apiKey); ?>" name="shop_info[apikey]" />
             <p>Not you? <a href="<?php echo $account_reset_url; ?>">Reset ViaBill Account</a></p>
        </div>
        <div class="form-group">
            <label><?php echo __( 'Eshop Country', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_attr($storeCountry); ?>" name="shop_info[country]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Eshop Language', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_attr($langCode); ?>" name="shop_info[language]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Eshop Currency', 'viabill');?></label>
            <input class="form-control" type="text" required="true"
             value="<?php echo esc_attr($currencyCode); ?>" name="shop_info[currency]" />
        </div>                
        <div class="form-group">
            <label><?php echo __( 'Module Version', 'viabill');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($module_version); ?>" name="shop_info[addon_version]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'WooCommerce Version', 'viabill');?></label>
            <input type="hidden" value="woocommerce" name="shop_info[platform]" />
            <input class="form-control" type="text"
             value="<?php echo esc_attr($platform_version); ?>" name="shop_info[platform_version]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'PHP Version', 'viabill');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($php_version); ?>" name="shop_info[php_version]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Memory Limit', 'viabill');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($memory_limit); ?>" name="shop_info[memory_limit]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'O/S', 'viabill');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($os); ?>" name="shop_info[os]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Debug File', 'viabill');?></label>
            <input class="form-control" type="text"
             value="<?php echo esc_attr($debug_file); ?>" name="shop_info[debug_file]" />
        </div>
        <div class="form-group">
            <label><?php echo __( 'Debug Data', 'viabill');?></label>
            <textarea class="form-control"
             name="shop_info[debug_data]"><?php echo esc_html($debug_log_entries); ?></textarea>
        </div>        
    </fieldset>            
    <div class="form-group form-check">
        <input type="checkbox" value="accepted" required="true"
         class="form-check-input" name="terms_of_use" id="terms_of_use"/>
          <label class="form-check-label"><?php echo __( 'I have read and accept the', 'viabill');?>
           <a href="<?php echo esc_url($terms_of_use_url); ?>"><?php echo __( 'Terms and Conditions', 'viabill');?></a></label>
    </div>           
    <button type="button" onclick="validateAndSubmit()" class="button-primary">
    <?php echo __( 'Send Support Request', 'viabill');?></button>
    </form>
    </div>

    <script>
    function validateAndSubmit() {
        var form_id = "tech_support_form";
        var error_msg = "";
        var valid = true;
        
        jQuery("#" + form_id).find("select, textarea, input").each(function() {
            if (jQuery(this).prop("required")) {
                if (!jQuery(this).val()) {
                    valid = false;
                    var label = jQuery(this).closest(".form-group").find("label").text();
                    error_msg += "* " + label + " <?php echo __('is required', 'viabill');?>\n";
                }
            }
        });
        
        if (jQuery("#terms_of_use").prop("checked") == false) {
            valid = false;
            error_msg += "* <?php echo __('You need to accept The Terms and Conditions.', 'viabill');?>\n";
        }
        
        if (valid) {
            jQuery("#" + form_id).submit();	
        } else {
            error_msg = "<?php echo __('Please correct the following errors and try again:', 'viabill'); ?>\n" + error_msg;
            alert(error_msg);
        }		
    }
    </script>
      
      <?php
    }
    
    protected function getContactForm()
    {
        $params = array();

        try {
            // Get Module Version            
            $module_version = VIABILL_PLUGIN_VERSION;
                                    
            // Get PHP info
            $php_version = phpversion();
            $memory_limit = ini_get('memory_limit');

            // Get WooCommerce Version
            $platform_version = '';
            if (defined('WC_VERSION')) {
              $platform_version = WC_VERSION;
            } else {
              $valid = false;
            }      
            
            // Log data
            $debug_file_path = $this->logger->getFilepath();
            
            // Get Store Info
            $langCode = get_bloginfo('language');
            $currencyCode = get_woocommerce_currency();
            $storeName = get_bloginfo('name');
            $storeURL = get_home_url();

            // Get ViaBill Config
            $storeCountry = WC()->countries->get_base_country();
            
            $storeEmail = get_bloginfo('admin_email');
        
            $file_lines = self::LOG_FILE_LINES_TO_READ;
    
            $debug_log_entries = 'N/A';
            if (file_exists($debug_file_path)) {
                $debug_log_entries = $this->fileTail($debug_file_path, $file_lines);
            }
            
            $action_url = $this->getActionURL();
    
            $terms_of_service_lang = strtolower(trim($langCode));
            switch ($terms_of_service_lang) {
                case 'us':
                    $terms_of_use_url = 'https://viabill.com/us/legal/cooperation-agreement/';
                    break;
                case 'es':
                    $terms_of_use_url = 'https://viabill.com/es/legal/contrato-cooperacion/';
                    break;
                case 'dk':
                    $terms_of_use_url = 'https://viabill.com/dk/legal/cooperation-agreement/';
                    break;
                default:
                    $terms_of_use_url = 'https://viabill.com/dk/legal/cooperation-agreement/';
                    break;
            }    

            $apiKey = get_option( 'viabill_key' );

            $params = [
                'module_version'=>$module_version,
                'platform_version'=>$platform_version,
                'php_version'=>$php_version,
                'memory_limit'=>$memory_limit,
                'os'=>PHP_OS,
                'debug_file'=>$debug_file_path,
                'debug_log_entries'=>$debug_log_entries,
                'action_url'=>$action_url,                
                'terms_of_use_url'=>$terms_of_use_url,
                'langCode'=>$langCode,
                'currencyCode'=>$currencyCode,
                'storeName'=>$storeName,
                'storeURL'=>$storeURL,
                'storeEmail'=>$storeEmail,
                'storeCountry'=>$storeCountry,
                'apiKey'=>$apiKey
            ];
        } catch (\Exception $e) {            
            $params['error'] = $e->getMessage();

            return $params;
        }
        
        return $params;
    }
        
    protected function getContactFormOutput()
    {        
        $platform = sanitize_text_field($_REQUEST['shop_info']['platform']);                
        $merchant_email = sanitize_email(trim($_REQUEST['ticket_info']['email']));
        $shop_url =  sanitize_url($_REQUEST['shop_info']['url']);
        $contact_name = sanitize_text_field($_REQUEST['ticket_info']['name']);
        $message = sanitize_textarea_field($_REQUEST['ticket_info']['issue']);                                                       
        
        $shop_info_html = '<ul>';
        foreach ($_REQUEST['shop_info'] as $key => $value) {
            $label = strtoupper(str_replace('_', ' ', sanitize_key($key)));            
            if ($key == 'debug_data') {
                $shop_info_html .= '<li><strong>'.$label.'</strong><br/>
                <div style="background-color: #FFFFCC;">'.
                    esc_html($value).'</div></li>';
            } elseif ($key == 'error_data') {
                $shop_info_html .= '<li><strong>'.$label.'</strong><br/>
                <div style="background-color: #FFCCCC;">'.
                    esc_html($value).'</div>
                </li>';
            } else {
                $shop_info_html .= '<li><strong>'.esc_html($label).
                    '</strong>: '.esc_attr($value).'</li>';
            }
        }
        $shop_info_html .= '</ul>';
                        
        $email_subject = "New ".ucfirst($platform)." Support Request from ".$shop_url;
        $email_body = "Dear support,\n<br/>You have received a new support request with ".
                       "the following details:\n";
        $email_body .= "<h3>Ticket</h3>";
        $email_body .= "<table>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Name:</strong></td><td>".
            $ticket_info['name']."</td></tr>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Email:</strong></td><td>".
            $ticket_info['email']."</td></tr>";
        $email_body .= "<tr><td style='background: #eee;'><strong>Issue:</strong></td><td>".
            $ticket_info['issue']."</td></tr>";
        $email_body .= "</table>";
        $email_body .= "<h3>Shop Info</h3>";
        $email_body .= $shop_info_html;
                
        $sender_email = $this->getSenderEmail($merchant_email);
        $to = self::VIABILL_TECH_SUPPORT_EMAIL;        
        $support_email = self::VIABILL_TECH_SUPPORT_EMAIL;

        $success = $this->sendMail($to, $sender_email, $email_subject, $email_body);
        if (!$success) {
            // use another method
            $success = $this->sendMail($to, $sender_email, $email_subject, $email_body, true);
        }
        
        if ($success) {
            $success_msg = '';
            $success_msg = __('Your request has been received successfully!', 'viabill').
                __('We will get back to you soon at ', 'viabill')."<strong>{$sender_email}</strong>. ".
                __('You may also contact us at ', 'viabill')."<strong>{$support_email}</strong>.";
            $body = "<div class='alert alert-success'><div class='alert-text'>
                <strong>".__('Success!')."</strong><br/>".
                $success_msg.
                "</div></div>";
        } else {
            $fail_msg = __('Could not email your request form to the technical support team. ', 'viabill').
                __('Please try again or contact us at ', 'viabill')."<strong>{$support_email}</strong>.";
            $body = "<div class='alert alert-danger'><div class='alert-text'>
                <strong>".__('Error', 'viabill')."</strong><br/>".
                $fail_msg.
                "</div></div>";
        }
        
        $html = $body;
   
        return $html;
    }   
    
    protected function sendMail($to, $from, $email_subject, $email_body)
    {
        $success = false;
        
        $headers = "From: " . $from . "\r\n";
        $headers .= "Reply-To: ". $to . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $phpMailer = 'mail';
        $success = $phpMailer($to, $email_subject, $email_body, $headers);
            
        return $success;
    }
    
    protected function getActionURL()
    {
        return $this->get_admin_url();        
    }
    
    protected function getSenderEmail($merchant_email)
    {
        $senderEmail = '';
        
        $site_host = wc_get_cart_url();
                
        // check if merchant email shares the same domain with the site host
        if (!empty($merchant_email)) {
            list($account, $domain) = explode('@', $merchant_email, 2);
            if (strpos($site_host, $domain)!== false) {
                $senderEmail = $merchant_email;
            }
        }
        
        if (empty($senderEmail)) {
            $senderEmail = get_option( 'admin_email' );            
        }
        
        # sanity check
        if (empty($senderEmail)) {
            $domain_name = $site_host;

            if (strpos($site_host, '/')!==false) {
                $parts = explode('/', $site_host);
                foreach ($parts as $part) {
                    if (strpos($part, '.')!==false) {
                        $domain_name = $part;
                        break;
                    }
                }
            }

            $parts = explode('.', $domain_name);
            $parts_n = count($parts);
            $sep = '';
            $senderEmail = 'noreply@';
            for ($i=($parts_n-2); $i<$parts_n; $i++) {
                $senderEmail .= $sep . $parts[$i];
                $sep = '.';
            }
        }
                    
        return $senderEmail;
    }
    
    protected function fileTail($filepath, $num_of_lines = 100)
    {
        $tail = '';
        
        $file = new \SplFileObject($filepath, 'r');
        $file->seek(PHP_INT_MAX);
        $last_line = $file->key();
        
        if ($last_line < $num_of_lines) {
            $num_of_lines = $last_line;
        }
        
        if ($num_of_lines>0) {
            $lines = new \LimitIterator($file, $last_line - $num_of_lines, $last_line);
            $arr = iterator_to_array($lines);
            $arr = array_reverse($arr);
            $tail = implode("", $arr);
        }
        
        return $tail;
    }
    
    /**
     * Register submenu page for the support.
     *
     * @return void
     */
    public function register_settings_page() {          
      add_submenu_page(
        'woocommerce',
        __( 'ViaBill Support', 'viabill' ),
        __( 'ViaBill Support', 'viabill' ),
        'manage_woocommerce',
        self::SLUG,
        array( $this, 'show' )
      );
    }

    /**
     * Clear all ViaBill account details, so the merchant can register/login again
     * 
     *  @return void
     */
    public function reset_viabill_account() {  
        $merchant = new Viabill_Merchant_Profile();
        $merchant->delete_registration_data();

        // Add a success message to the URL
        $redirect_url = add_query_arg(
            array(
                'viabill_success_message' => urlencode('ViaBill account details have been reset successfully.')
            ),
            Viabill_Main::get_settings_link()
        );

        wp_safe_redirect($redirect_url);
    }

  }
}
