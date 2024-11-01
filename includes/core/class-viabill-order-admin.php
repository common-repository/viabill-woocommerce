<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Order_Admin' ) ) {
  /**
   * Viabill_Order_Admin class
   *
   * @since 0.1
   */
  class Viabill_Order_Admin {
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
      require_once( VIABILL_DIR_PATH . '/includes/core/class-viabill-connector.php' );
      require_once( VIABILL_DIR_PATH . '/includes/utilities/class-viabill-logger.php' );

      $this->connector = new Viabill_Connector();
      $this->settings = Viabill_Main::get_gateway_settings();
      $this->logger = new Viabill_Logger( isset( $this->settings['use-logger'] ) && 'yes' === $this->settings['use-logger'] );

      $this->captured_status = (isset($this->settings['order_status_after_captured_payment']))?$this->settings['order_status_after_captured_payment']:'processing';
      $this->approved_status = (isset($this->settings['order_status_after_authorized_payment']))?$this->settings['order_status_after_authorized_payment']:'on-hold';      

      add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'display_capture_button' ), 10, 1 );
      add_action( 'woocommerce_order_item_add_action_buttons', array( $this, 'display_cancel_order_button' ), 20, 1 );

      add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_viabill_order_status' ), 20, 1 );

      add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_cancel' ), 10, 4 );
      add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_capture' ), 10, 4 );
      add_action( 'woocommerce_order_status_refunded', array( $this, 'maybe_refund' ), 5, 2 );

      add_filter( 'wc_order_is_editable', array( $this, 'is_order_editable' ), 10, 2 );

      add_filter( 'parse_query', array( $this, 'hide_pending_orders' ) );

      add_action( 'admin_notices', array( $this, 'display_order_notice' ), 100 );
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
     * Return false if payment method is ViaBill or $is_editable otherwise.
     *
     * @param  bool     $is_editable
     * @param  WC_Order $order
     * @return bool
     */
    public function is_order_editable( $is_editable, $order ) {
      if ( $this->is_viabill_payment($order->get_payment_method()) ) {      
        return in_array( $order->get_status(), array( 'pending', 'on-hold', 'processing', 'auto-draft' ), true );
      }

      return $is_editable;
    }

    /**
     * Cancel the given order in ViaBill system and if successful in
     * WooCommerce too.
     *
     * @param  int      $order_id
     * @param  string   $from
     * @param  string   $to
     * @param  WC_Order $order
     * @return void
     */
    public function maybe_cancel( $order_id, $from, $to, $order ) {
      if ( (!$this->is_viabill_payment($order->get_payment_method())) || ('cancelled' !== $to) ) {
        return;
      }

      if ( in_array( $order->get_meta( 'viabill_status' ), array( 'pending', 'waiting' ), true ) ) {
        return;
      }
      
      $response = $this->connector->cancel( $order );

      if ( isset( $response['success'] ) && $response['success'] ) {
        $this->logger->log( 'Successfully cancelled order ' . $order->get_id() . ' via ViaBill payment gateway', 'notice' );
        $note = __( 'Order successfully canceled at ViaBill.', 'viabill' );
        $order->update_meta_data( 'viabill_status', 'cancelled' );
      } else {
        $this->logger->log( 'Failed to cancel order ' . $order->get_id() . ' via ViaBill payment gateway', 'error' );
        $note = __( 'Something went wrong while trying to cancel the order.', 'viabill' );
        if ( ! empty( $response['message'] ) ) {
          $note .= '<br><strong>' . __( 'ViaBill\'s response', 'viabill' ) . '</strong>: "' . $response['message'] . '"';
          $this->logger->log( 'Error message ' . $response['message'], 'error' );
        }
      }

      $order->add_order_note( $note );
      $order->save();
    }

    /**
     * Capture the total order amount when switching status from approved to captured.
     *
     * @param  int      $order_id
     * @param  string   $from
     * @param  string   $to
     * @param  WC_Order $order
     * @return void
     */
    public function maybe_capture( $order_id, $from, $to, $order ) {
      if ( ! $this->is_viabill_payment($order->get_payment_method()) ) {
        return;
      }

      $capture_order_on_status_switch = isset( $this->settings['capture-order-on-status-switch'] ) && 'yes' === $this->settings['capture-order-on-status-switch'];

      if ( ! $capture_order_on_status_switch ) {
        return;
      }

      if ( $this->approved_status !== $from || $this->captured_status !== $to ) {
        return;
      }

      if ( 'captured' === $order->get_meta( 'viabill_status', true ) ) {
        return;
      }

      $this->logger->log( 'Executed capture on status change for order ' . $order_id, 'notice' );
      $this->connector->capture( $order );
    }

    /**
     * Refund order automatically on order status change
     *
     * @param  int       $order_id
     * @throws Exception Refund failed exception.
     */
    public function maybe_refund( $order_id, $from = null, $to = null, $order = null ) {
      $order = $order ? $order : wc_get_order( $order_id );

      if ( !$this->is_viabill_payment($order->get_payment_method()) ) {
        return;
      }

      if ( 'yes' !== Viabill_Main::get_gateway_settings( 'automatic-refund-status' ) ) {
        return;
      }

      if ( 'refunded' === $order->get_meta( 'viabill_status' ) ) {
        return;
      }

      if ( ! $order->get_meta( 'viabill_captured_amount' ) ) {
        $order->update_meta_data( 'viabill_status', 'refunded' );
        $order->add_order_note( __( 'Order has not been captured, nothing to refund through ViaBill payment gateway.', 'woocommerce' ) );
        $order->save();
        return;
      }

      // Remove default WooCommerce order status change refund handling.
      remove_action( 'woocommerce_order_status_refunded', 'wc_order_fully_refunded' );

      /**
       * Hook "woocommerce_order_status_refunded" returns first two arguments so we check for third to not rehook the function.
       * Hooking on "woocommerce_order_status_changed" where we have access to $status_transition['from'] so we can revert status if refund fails.
       */
      if ( ! $to ) {
        add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_refund' ), 5, 4 );
        return;
      }

      if ( 'refunded' !== $to ) {
        return;
      }

      $max_refund = wc_format_decimal( floatval( $order->get_meta( 'viabill_captured_amount', true ) ) - $order->get_total_refunded() );

      if ( ! $max_refund ) {
        $this->logger->log( 'Total amount already refunded for order with ID ' . $order_id, 'warning' );
        return;
      }

      if ( $max_refund <= 0 ) {
        $this->logger->log( 'Amount to refund is less or equal to 0 for order with ID ' . $order_id, 'warning' );
        return;
      }

      $refund = $this->connector->refund( $order, $max_refund, $order->get_currency() );

      if ( $refund ) {
        wc_create_refund(
          array(
            'amount'   => $max_refund,
            'order_id' => $order_id,
          )
        );

        // Update viabill status again after refund has been created.
        $order->update_meta_data( 'viabill_status', (int) $order->get_remaining_refund_amount() ? 'refunded_partially' : 'refunded' );
        $order->save();
      } else {
        $order->update_status( $from );
        $this->logger->log( 'Failed to refund order with ID ' . $order_id, 'error' );
        throw new Exception( 'Refund failed, order status reverted back to: "' . $from . '"' );
      }
    }

    /**
     * Display ViaBill status for provided order. Should be used in admin's
     * order view.
     *
     * @param WC_Order $order
     */
    public function display_viabill_order_status( $order ) {
      if ( !$this->is_viabill_payment($order->get_payment_method()) ) {
        return;
      }

      $status = $order->get_meta( 'viabill_status', true );

      if ( ! $status ) {
        $this->logger->log( 'Missing status for order with order ID ' . $order->get_id(), 'notice' );
        return;
      }

      $statuses = array(
        'waiting'            => _x( 'Waiting', 'ViaBill status', 'viabill' ),
        'pending'            => _x( 'Pending', 'ViaBill status', 'viabill' ),
        'pending_approval'   => _x( 'Pending Approval', 'ViaBill status', 'viabill' ),
        'approved'           => _x( 'Approved', 'ViaBill status', 'viabill' ),
        'captured'           => _x( 'Captured', 'ViaBill status', 'viabill' ),
        'captured_partially' => _x( 'Captured Partially', 'ViaBill status', 'viabill' ),
        'refunded'           => _x( 'Refunded', 'ViaBill status', 'viabill' ),
        'refunded_partially' => _x( 'Refunded Partially', 'ViaBill status', 'viabill' ),
      );
      ?>

      <div class="form-field form-field-wide viabill-status">
        <h3><?php esc_html_e( 'ViaBill Status', 'viabill' ); ?> <a class="viabill-status-refresh" href="#" style="font-weight: normal;">(<?php esc_html_e( 'Refresh', 'viabill' ); ?>)</a></h3>
        <span id="viabill-status" data-status="<?php echo esc_attr( $status ); ?>"><?php echo isset( $statuses[ $status ] ) ? $statuses[ $status ] : esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ); ?></span>
        <?php wp_nonce_field( 'viabill-status-action', 'viabill_status_refresh' ); ?>
      </div>
      <?php
    }

    /**
     * Display WordPress warning notice if current order (admin view) is processed
     * in sandbox/test mode.
     *
     * @return void
     */
    public function display_order_notice() {
      if ( ! is_admin() ) {
        return;
      }

      $screen = get_current_screen();
      if ( 'post' === $screen->base && 'shop_order' === $screen->id ) {
        $order = wc_get_order( get_the_ID() );
        if ( ! is_a( $order, 'WC_Order' ) ) {
          return;
        }

        $class = 'notice notice-warning';

        if ( 'yes' === $order->get_meta( 'in_test_mode', true ) ) {
          printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( __( 'This order was processed by ViaBill payment gateway in sandbox/test mode.', 'viabill' ) ) );
        }

        if ( in_array( $order->get_status(), array( 'viabill-approved', 'viabill-captured' ), true ) ) {
          printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( __( 'This order is still using old ViaBill order status which is no longer supported, please run database update from the ViaBill plugin settings.', 'viabill' ) ) );
        }
      }

    }

    /**
     * Register AJAX endpoints.
     */
    public function register_ajax_endpoints() {
      add_action( 'wp_ajax_get_viabill_capture_form', array( $this, 'get_capture_form_via_ajax' ) );
      add_action( 'wp_ajax_capture_viabill_payment', array( $this, 'capture_via_ajax' ) );
      add_action( 'wp_ajax_cancel_viabill_payment', array( $this, 'cancel_via_ajax' ) );
      add_action( 'wp_ajax_get_viabill_status', array( $this, 'refresh_status_via_ajax' ) );
    }

    /**
     * Register capture button for single order admin view.
     *
     * @param WC_Order $order
     */
    public function display_capture_button( $order ) {
      if ( (!$this->is_viabill_payment($order->get_payment_method())) || ('refunded' === $order->get_status()) ) {
        return;
      }

      if ( isset( $this->settings['auto-capture'] ) && 'yes' === $this->settings['auto-capture'] ) {
        return;
      }

      if ( ! in_array( $order->get_meta( 'viabill_status', true ), array( 'approved', 'captured_partially' ), true ) ) {
        return;
      }

      $amount_captured = $order->get_meta( 'viabill_captured_amount', true );
      $amount_captured = empty( $amount_captured ) ? 0 : floatval( $amount_captured );

      $amount_to_capture = $order->get_total() - $amount_captured;
      if ( $amount_to_capture <= 0 ) {
        return;
      }
      ?>
      <button type="button" id="viabill-capture-payment" class="button"><?php esc_html_e( 'Capture', 'viabill' ); ?></button>
      <?php wp_nonce_field( 'viabill-show-capture-form-action', 'viabill_show_capture_form_nonce' ); ?>
      <?php
    }

    /**
     * Register cancel order button for single order admin view.
     *
     * @param  WC_Order $order
     * @return void
     */
    public function display_cancel_order_button( $order ) {
      if ( (!$this->is_viabill_payment($order->get_payment_method())) || ('approved' !== $order->get_meta( 'viabill_status', true )) ) {
        return;
      }

      ?>
      <button type="button" id="viabill-cancel-payment" class="button"><?php esc_html_e( 'Cancel order', 'viabill' ); ?></button>
      <?php
      wp_nonce_field( 'viabill-cancel-order-action', 'cancel_order_nonce' );
    }

    /**
     * Cancel order given via $_POST array.
     *
     * @return void
     */
    public function cancel_via_ajax() {
      $nonce = isset( $_POST['viabill_cancel_nonce'] ) ? sanitize_key( $_POST['viabill_cancel_nonce'] ) : false;
      if ( ! wp_verify_nonce( $nonce, 'viabill-cancel-order-action' ) ) {
        $this->logger->log( 'Failed to verify nonce while trying to cancel order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid nonce.', 'viabill' ),
          )
        );
      }

      $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false;
      if ( empty( $order_id ) ) {
        $this->logger->log( 'Missing order ID while trying to cancel order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid order ID, please try again.', 'viabill' ),
          )
        );
      }

      $order = wc_get_order( $order_id );
      if ( empty( $order ) ) {
        $this->logger->log( 'Failed to find order with ID ' . $order_id . ' while trying to cancel order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Missing order, please try again.', 'viabill' ),
          )
        );
      }

      $order->update_status( 'cancelled' );

      wp_send_json(
        array(
          'success' => true,
          'message' => __( 'Order successfully cancelled.', 'viabill' ),
        )
      );
      wp_die();
    }

    /**
     * Capture amount for an order given via $_POST array.
     *
     * @return void
     */
    public function capture_via_ajax() {
      $nonce = isset( $_POST['viabill_capture_nonce'] ) ? sanitize_key( $_POST['viabill_capture_nonce'] ) : false;
      if ( ! wp_verify_nonce( $nonce, 'viabill-capture-action' ) ) {
        $this->logger->log( 'Failed to verify nonce while trying to capture order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid nonce.', 'viabill' ),
          )
        );
      }

      $amount = isset( $_POST['amount'] ) ? $this->to_float( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
      if ( $amount <= 0 ) {
        $this->logger->log( 'Invalid amount provided while trying to capture order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Please enter valid amount.', 'viabill' ),
          )
        );
      }

      $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false;
      if ( empty( $order_id ) ) {
        $this->logger->log( 'Missing order ID while trying to capture order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid order ID, please try again.', 'viabill' ),
          )
        );
      }

      $order = wc_get_order( $order_id );

      if ( empty( $order ) ) {
        $this->logger->log( 'Failed to find order with ID ' . $order_id . ' while trying to capture order via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Missing order, please try again.', 'viabill' ),
          )
        );
      }
      $this->logger->log( 'Executed capture via ajax for order ' . $order_id, 'notice' );
      $response = $this->connector->capture( $order, $amount );

      wp_send_json( $response );
      wp_die();
    }

    /**
     * Fetch status from ViaBill and update order data
     *
     * @return void
     */
    public function refresh_status_via_ajax() {
      $nonce = isset( $_POST['viabill_status_refresh'] ) ? sanitize_key( $_POST['viabill_status_refresh'] ) : false;
      if ( ! wp_verify_nonce( $nonce, 'viabill-status-action' ) ) {
        $this->logger->log( 'Failed to verify nonce while trying to fetch status via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid nonce.', 'viabill' ),
          )
        );
      }

      $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : false;
      if ( empty( $order_id ) ) {
        $this->logger->log( 'Missing order ID while trying to fetch order status via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid order ID, please try again.', 'viabill' ),
          )
        );
      }

      $order = wc_get_order( $order_id );

      if ( empty( $order ) ) {
        $this->logger->log( 'Failed to find order with ID ' . $order_id . ' while trying to fetch order status via ajax.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Missing order, please try again.', 'viabill' ),
          )
        );
      }

      $status = $this->connector->get_status( $order );

      if ( ! $status ) {
        $this->logger->log( 'No status returned by ViaBill for order ' . $order->get_id(), 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Status request failed.', 'viabill' ),
          )
        );
      }

      $this->sync_viabill_status( strtolower( htmlentities( $status, ENT_QUOTES, 'UTF-8' ) ), $order );

      wp_send_json_success( $status );
      wp_die();
    }

    /**
     * Fetch status from ViaBill and update order data
     *
     * @return void
     */
    public function sync_viabill_status( $status, $order ) {
      $this->logger->log( 'Refresh status received order status: ' . $status . ' for order ' . $order->get_id(), 'info' );

      $old_status = $order->get_meta( 'viabill_status' );

      switch ( $status ) {
        case 'waiting':
          if ( ! in_array( $old_status, array( 'pending', 'waiting', 'cancelled', 'pending_approval' ), true ) ) {
            $order->update_meta_data( 'viabill_status', $status );
          }
          if ( 'cancelled' === $order->get_status() ) {
            $order->set_status( 'pending' );
          }
          $order->save();
          break;
        case 'approved':
          $order->update_meta_data( 'viabill_status', $status );
          $order->set_status( $this->approved_status, __( 'Order approved by ViaBill.', 'viabill' ) );
          $order->save();
          break;
        case 'captured':
          if ( ! in_array( $old_status, array( 'captured', 'captured_partially' ), true ) ) {
            $order->update_meta_data( 'viabill_status', $status );
          }
          if ( round( $order->get_meta( 'viabill_captured_amount' ), 2 ) === round( $order->get_total(), 2 ) ) {
            $order->set_status( $this->captured_status, __( 'Order captured by ViaBill.', 'viabill' ) );
          }
          $order->save();
          break;
        case 'refunded':
          if ( ! in_array( $old_status, array( 'refunded', 'refunded_partially' ), true ) ) {
            $order->update_meta_data( 'viabill_status', $status );
          }
          if ( ! (int) $order->get_remaining_refund_amount() ) {
            $order->set_status( 'refunded', __( 'Order refunded by ViaBill.', 'viabill' ) );
          }
          $order->save();
          break;
        case 'rejected':
          $order->update_meta_data( 'viabill_status', $status );
          $order->set_status( 'failed', __( 'Order payment rejected by ViaBill.', 'viabill' ) );
          $order->save();
          break;
        case 'cancelled':
          $order->update_meta_data( 'viabill_status', $status );
          $order->set_status( 'cancelled', __( 'Order cancelled by ViaBill.', 'viabill' ) );
          $order->save();
          break;

        default:
          $order->update_meta_data( 'viabill_status', $status );
          $order->save();
          break;
      }
    }

    /**
     * Convert to float based on WooCommerce currency options.
     *
     * @param  string $value
     * @return float
     */
    private function to_float( $value ) {
      $thousand_separator = wc_get_price_thousand_separator();
      $decimal_separator  = wc_get_price_decimal_separator();

      $value = str_replace( $thousand_separator, '', $value );
      $value = str_replace( $decimal_separator, '.', $value );

      return floatval( $value );
    }

    /**
     * Display capture subform for given order.
     *
     * @param  WC_Order $order
     * @return void
     */
    private function display_capture_form( $order ) {
      $amount_captured = $order->get_meta( 'viabill_captured_amount', true );
      $amount_captured = empty( $amount_captured ) ? 0 : floatval( $amount_captured );

      $amount_to_capture = $order->get_total() - $amount_captured;
      ?>
      <div class="wc-order-data-row wc-order-viabill-capture-payment wc-order-data-row-toggle" style="display: none;">
        <table class="wc-order-totals">
          <tr>
            <td class="label"><?php esc_html_e( 'Amount already captured', 'viabill' ); ?>:</td>
            <td class="total">
              -
              <?php
              echo wc_price(
                $amount_captured,
                array(
                  'currency' => $order->get_currency(),
                )
              );
              ?>
            </td>
          </tr>
          <tr>
            <td class="label"><?php esc_html_e( 'Total available to capture', 'viabill' ); ?>:</td>
            <td class="total">
              <?php
              echo wc_price(
                $amount_to_capture,
                array(
                  'currency' => $order->get_currency(),
                )
              );
              ?>
            </td>
          </tr>
          <tr>
            <td class="label"><label for="capture-amount"><?php esc_html_e( 'Capture amount', 'viabill' ); ?>:</label></td>
            <td class="total">
              <input type="text" id="capture-viabil-amount" name="capture-amount" class="wc_input_price" />
              <div class="clear"></div>
            </td>
          </tr>
        </table>
        <div class="clear"></div>
        <div class="refund-actions">
          <?php
          $capture_amount = '<span class="wc-order-capture-amount">' . wc_price(
            0,
            array(
              'currency' => $order->get_currency(),
            )
          ) . '</span>';
          ?>
          <button id="do-viabill-capture" type="button" class="button button-primary"><?php printf( __( 'Capture %1$s via %2$s', 'viabill' ), $capture_amount, 'ViaBill' ); ?></button>
          <button type="button" class="button cancel-action"><?php esc_html_e( 'Cancel', 'viabill' ); ?></button>
          <div class="clear"></div>
        </div>
      </div>
      <?php wp_nonce_field( 'viabill-capture-action', 'viabill_capture_nonce' ); ?>
      <?php
    }

    /**
     * Return capture form if valid order ID provided via $_REQUEST array.
     *
     * @return void
     */
    public function get_capture_form_via_ajax() {
      $nonce = isset( $_REQUEST['viabill_show_capture_form_nonce'] ) ? sanitize_key( $_REQUEST['viabill_show_capture_form_nonce'] ) : false;

      if ( ! wp_verify_nonce( $nonce, 'viabill-show-capture-form-action' ) ) {
        $this->logger->log( 'Failed to verify nonce while trying to return capture form.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Invalid nonce.', 'viabill' ),
          )
        );
      }

      $order_id = isset( $_REQUEST['order_id'] ) ? intval( $_REQUEST['order_id'] ) : false;
      if ( empty( $order_id ) ) {
        $this->logger->log( 'Failed to get order ID while trying to return capture form.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Missing order ID, can\'t display order capture form.', 'viabill' ),
          )
        );
      }

      $order = wc_get_order( $order_id );
      if ( ! $order ) {
        $this->logger->log( 'Failed to get order with ID ' . $order_id . ' while trying to return capture form.', 'error' );
        wp_send_json(
          array(
            'success' => false,
            'message' => __( 'Order ID is invalid, can\'t display order capture form.', 'viabill' ),
          )
        );
      }
      $this->display_capture_form( $order );
      wp_die();
    }

    /**
     * Filters the query in WooCommerce orders view to hide pending orders
     *
     * @param  WP_Query $query
     * @return void
     */
    public function hide_pending_orders( $query ) {
      global $pagenow;

      $post_type = isset( $_GET['post_type'] )? sanitize_key($_GET['post_type']) : '';
      $orders_archive = is_admin() && 'edit.php' === $pagenow && 'shop_order' === $post_type; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
      $pending_hidden = isset( $this->settings['pending-orders-hidden'] ) && 'yes' === $this->settings['pending-orders-hidden'];

      if ( $orders_archive && $pending_hidden ) {
        $query->query_vars['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
          'relation' => 'OR',
          array(
            'key'     => 'viabill_status',
            'compare' => 'NOT IN',
            'value'   => array( 'pending', 'waiting', 'cancelled' ),
          ),
          array(
            'key'     => 'viabill_status',
            'compare' => 'NOT EXISTS',
            'value'   => '42', // Requires random string to work but string has no effect on query - https://developer.wordpress.org/reference/classes/wp_query/#custom-field-post-meta-parameters.
          ),
        );
      }
    }
  }
}
