<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

$order_status_options = array();
$available_order_statuses = wc_get_order_statuses();
foreach ($available_order_statuses as $key => $value) {
  $key = str_replace('wc-', '', $key);
  $order_status_options[$key] = $value;
}

// make sure that the two default order statuses are present
if (!array_key_exists('on-hold', $order_status_options)) {
  $order_status_options['on-hold'] = 'On-hold';
}
if (!array_key_exists('processing', $order_status_options)) {
  $order_status_options['processing'] = 'Processing';
}

return array(
  'enabled' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'    => __( 'Enable', 'viabill' ),
    'type'     => 'checkbox',
    'label'    => __( 'Enable ViaBill Payment Gateway', 'viabill' ),
    'default'  => 'no',
    'desc_tip' => false,
  ),
  'title' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Title', 'viabill' ),
    'type'        => 'text',
    'description' => __( 'This controls the title which the user sees during the checkout.', 'viabill' ),
    'default'     => __( 'ViaBill', 'viabill' ),
    'desc_tip'    => true,
  ),
  /*
  'show_title_as_label' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'	=> __( 'Show Title as Payment Label', 'viabill' ),
    'type'	=> 'select',    
    'description' => __( 'It is recommended to you keep the default option (icon only)', 'viabill' ),
    'options'	=> ['hide_label' => 'Show only Payment Icon', 
                  'show_label_icon'=>'Show Payment label and icon',
                  'show_label_only'=>'Show Payment label only'],
    'default'  => 'hide_label',
    'class'    => 'wc-enhanced-select',
    'desc_tip'    => false,
  ),
  */
  'description-msg' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Description', 'viabill' ),
    'type'        => 'textarea',
    'description' => __( 'Payment method description that the customer will see on the checkout page.', 'viabill' ),
    'default'     => '',
    'desc_tip'    => true,
  ),
  'confirmation-msg' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Confirmation', 'viabill' ),
    'type'        => 'textarea',
    'description' => __( 'Confirmation message that will be added to the "thank you" page.', 'viabill' ),
    'default'     => __( 'Your account has been charged and your transaction is successful.', 'viabill' ),
    'desc_tip'    => true,
  ),
  'receipt-redirect-msg' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Receipt', 'viabill' ),
    'type'        => 'textarea',
    'description' => __( 'Message that will be added to the "receipt" page. Shown if automatic redirect is enabled.', 'viabill' ),
    'default'     => __( 'Please click on the button below.', 'viabill' ),
    'desc_tip'    => true,
  ),
  'advanced-options' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Advanced options', 'viabill' ),
    'type'        => 'title',
    'description' => '',
  ),
  'in-test-mode' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'ViaBill Test Mode', 'viabill' ),
    'type'        => 'checkbox',
    'label'       => __( 'Enable ViaBill Test Mode', 'viabill' ),
    'description' => __( 'Mode used for testing purposes, disable this for live web shops.', 'viabill' ),
    'default'     => 'no',
    'desc_tip'    => true,
  ),
  'order_status_after_authorized_payment' => array(
    'title'	=> __( 'After Payment is Authorized', 'viabill' ),
    'type'	=> 'select',    
    'description' => __( 'Select the order status after the payment is authorized by Viabill', 'viabill' ),
    'options'	=> $order_status_options,
    'default'  => 'on-hold',
    'class'    => 'wc-enhanced-select',
    'desc_tip'    => false,
  ),
  'order_status_after_captured_payment' => array(
    'title'	=> __( 'After Payment is Captured', 'viabill' ),
    'type'	=> 'select',    
    'description' => __( 'Select the order status after the payment is fully captured by Viabill', 'viabill' ),
    'options'	=> $order_status_options,
    'class'    => 'wc-enhanced-select',
    'default'  => 'processing',
    'desc_tip'    => false,
  ),
  'auto-capture' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Auto-capture payments', 'viabill' ),
    'type'        => 'select',
    'class'       => 'wc-enhanced-select',
    'description' => __( 'Select this option to automatically capture all approved ViaBill orders. All automatically captured orders will be updated with an order status of, "Processing". Selecting this option will also disable the option to partially capture the order amount.', 'viabill' ),
    'default'     => 'no',
    'options'     => array(
      'no'  => __( 'No', 'viabill' ),
      'yes' => __( 'Yes', 'viabill' ),
    ),
  ),
  'automatic-capture-mail' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Auto-capture email', 'viabill' ),
    'type'        => 'checkbox',
    'label'       => __( 'Only send email for captured orders.', 'viabill' ),
    'description' => __( 'If the Auto-capture is enabled this setting will skip sending email for approved order and will only send mail when the order is captured.', 'viabill' ),
    'default'     => 'no',
  ),
  'capture-order-on-status-switch' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Capture order on status change', 'viabill' ),
    'type'        => 'select',
    'class'       => 'wc-enhanced-select',
    'description' => __( 'Select this option in order to capture the whole order amount by manually switching the order status from, "On Hold" to "Processing".', 'viabill' ),
    'default'     => 'yes',
    'options'     => array(
      'no'  => __( 'No', 'viabill' ),
      'yes' => __( 'Yes', 'viabill' ),
    ),
  ),  
  'use-logger' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Debug log', 'viabill' ),
    'type'        => 'checkbox',
    'label'       => __( 'Enable logging', 'viabill' ),
    'description' => sprintf( __( 'Log gateway events, stored in %s. Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'viabill' ), '<code>' . WC_Log_Handler_File::get_log_file_path( 'viabill' ) . '</code>' ),
    'default'     => 'no',
  ),
  'auto-redirect' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Automatic redirect', 'viabill' ),
    'type'        => 'checkbox',
    'label'       => __( 'Enable automatic redirect to the ViaBill checkout form', 'viabill' ),
    'description' => __( 'With this option enabled your customers will be automatically redirected to ViaBill checkout form. If the option is disabled the customer will have one more step that they will need to confirm in order to go to ViaBill checkout form.', 'viabill' ),
    'default'     => 'yes',
  ),
  'pending-orders-hidden' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Hide pending orders', 'viabill' ),
    'type'        => 'checkbox',
    'label'       => __( 'Hide orders that have not been sent to ViaBill', 'viabill' ),
    'description' => __( 'With this option enabled orders chosen to be payed with ViaBill but not sent to ViaBill will be hidden from order list in the admin.', 'viabill' ),
    'default'     => 'no',
  ),
  'automatic-refund-status' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Automatic refund', 'viabill' ),
    'type'        => 'checkbox',
    'label'       => __( 'Refund automatically on changing order status to "Refunded"', 'viabill' ),
    'description' => __( 'With this option enabled changing order status to "Refunded" will automatically refund the order by ViaBill payment gateway.', 'viabill' ),
    'default'     => 'no',
  ),
  'checkout-hide' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Hide in Checkout', 'viabill' ),
    'type'        => 'select',
    'class'       => 'wc-enhanced-select',
    'description' => __( 'If enabled, the Viabill payment method will not be available in the checkout step.', 'viabill' ),
    'default'     => 'no',
    'options'     => array(
      'no'  => __( 'No', 'viabill' ),
      'yes' => __( 'Yes', 'viabill' ),
    ),
  ),
  'update-db' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Database Update', 'viabill' ),
    'type'        => 'title',
    'description' => Viabill_DB_Update::show_update_field(),
  ),
);
