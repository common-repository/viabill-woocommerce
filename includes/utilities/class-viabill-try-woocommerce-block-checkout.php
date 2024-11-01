<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! class_exists( 'WC_Viabill_Try_Blocks' ) ) {

    require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-payment-gateway.php' );
    require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-order-admin.php' );

    require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-notices.php' );
    require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-support.php' );

    require_once(VIABILL_DIR_PATH. '/includes/utilities/class-viabill-icon-shortcode.php' );
    require_once(VIABILL_DIR_PATH. '/includes/utilities/class-viabill-db-update.php' );

    require_once(VIABILL_DIR_PATH. '/includes/core/class-viabill-api.php' );

    final class WC_Viabill_Try_Blocks extends AbstractPaymentMethodType {
        private $gateway;
        protected $name = 'viabill_try';// payment gateway name
        
        public function initialize() {
            $this->settings = get_option( 'woocommerce_viabill_try_settings', [] );
            $this->gateway = new Viabill_Try_Payment_Gateway();
        }
        
        public function is_active() {		
            return $this->gateway->is_available();
        }
        
        public function get_payment_method_script_handles() {
            wp_register_script(
                'wc-viabill-try-blocks-integration',
                plugin_dir_url(__FILE__) . '../../assets/block/checkout_try.js',
                [
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ],
                null,
                true
            );
            
            wp_enqueue_script('wc-viabill-try-blocks-integration');
                    
            if( function_exists( 'wp_set_script_translations' ) ) {            
                wp_set_script_translations( 'wc-viabill-try-blocks-integration', 'viabill', VIABILL_DIR_PATH. 'languages/' );
            }		
            
            return [ 'wc-viabill-try-blocks-integration' ];
        }
        
        public function get_payment_method_data() {		
            return [
                'title' => $this->gateway->title,
                'description' => $this->gateway->description,
            ];
        }
    }

}