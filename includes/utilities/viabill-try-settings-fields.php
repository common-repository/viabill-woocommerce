<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

return array(
  'enabled' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'    => __( 'Enable', 'viabill' ),
    'type'     => 'checkbox',
    'label'    => __( 'Enable ViaBill Try before you Buy Payment Gateway', 'viabill' ),
    'default'  => 'no',
    'desc_tip' => false,
  ),
  'title' => array( // phpcs:ignore WordPress.Arrays.MultipleStatementAlignment.DoubleArrowNotAligned
    'title'       => __( 'Title', 'viabill' ),
    'type'        => 'text',
    'description' => __( 'This controls the title which the user sees during the checkout.', 'viabill' ),
    'default'     => __( 'Viabill', 'viabill' ),
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
    'description' => __( 'This controls the description the customer will see on the checkout page.', 'viabill' ),
    'default'     => __( 'Pay only for what you keep - in 30 days', 'viabill' ),
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
);
