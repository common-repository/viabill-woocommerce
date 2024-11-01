<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

return apply_filters(
  'viabill_pricetag_settings_fields',
  array(
    'pricetag-general'                        => array(
      'id'   => 'pricetag-general',
      'name' => __( 'PriceTag general', 'viabill' ),
      'type' => 'title',
      'desc' => __( 'Enable ViaBill\'s PriceTags to obtain the best possible conversion, and inform your customers about ViaBill.', 'viabill' ),
    ),
    'pricetag-enabled'                        => array(
      'id'      => 'pricetag-enabled',
      'name'    => __( 'Enable PriceTags', 'viabill' ),
      'type'    => 'checkbox',
      'desc'    => __( 'Enable display of PriceTags', 'viabill' ),
      'default' => Viabill_Main::get_gateway_settings( 'enabled' ),
    ),
    array(
      'id'   => 'pricetag-general',
      'type' => 'sectionend',
    ),

    'pricetag-locations'                      => array(
      'id'   => 'pricetag-locations',
      'name' => __( 'PriceTag locations', 'viabill' ),
      'type' => 'title',
      'desc' => __( 'Enable display of PriceTags for each location separately (PriceTags need to be enabled for location settings to take effect). Following sections contain advanced settings for each location.', 'viabill' ),
    ),
    'pricetag-on-product'                     => array(
      'id'      => 'pricetag-on-product',
      'name'    => __( 'Product page', 'viabill' ),
      'type'    => 'checkbox',
      'desc'    => __( 'Show on product page', 'viabill' ),
      'default' => Viabill_Main::get_gateway_settings( 'enabled' ),
    ),
    'pricetag-on-cart'                        => array(
      'id'      => 'pricetag-on-cart',
      'name'    => __( 'Cart page', 'viabill' ),
      'type'    => 'checkbox',
      'desc'    => __( 'Show on cart summary', 'viabill' ),
      'default' => 'yes',
    ),
    'pricetag-on-checkout'                    => array(
      'id'      => 'pricetag-on-checkout',
      'name'    => __( 'Checkout page', 'viabill' ),
      'type'    => 'checkbox',
      'desc'    => __( 'Show on checkout', 'viabill' ),
      'default' => 'yes',
    ),
    array(
      'id'   => 'pricetag-locations',
      'type' => 'sectionend',
    ),

    'pricetag-product'                        => array(
      'id'   => 'pricetag-product',
      'name' => __( 'PriceTag product page', 'viabill' ),
      'type' => 'title',
    ),
    'pricetag-position-product'               => array(
      'id'      => 'pricetag-position-product',
      'name'    => __( 'Product PriceTag position', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector of the element before which a PriceTag will be inserted on the product page. If left empty the PriceTag will be inserted after the price.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-product-hook'                  => array(
      'id'      => 'pricetag-product-hook',
      'name'    => __( 'Product PriceTag Hook', 'viabill' ),
      'type'    => 'select',
      'desc'    => __( 'Here you can change the default hook that will trigger the display of the PriceTag on the product page.', 'viabill' ),
      'default' => 'woocommerce_single_product_summary',
      'options' => array(
        'woocommerce_single_product_summary' => 'woocommerce_single_product_summary',        
        'woocommerce_before_add_to_cart_form' => 'woocommerce_before_add_to_cart_form'
      )
    ),
    'pricetag-product-dynamic-price'          => array(
      'id'      => 'pricetag-product-dynamic-price',
      'name'    => __( 'Product dynamic price selector', 'viabill' ),
      'type'    => 'text',
      'desc'    => sprintf( __( 'A Query selector for the element that contains the total price of the product on the single product page. In some cases it may prove practical to use the following selector: %1$s. With this selector the element will be found using this logic: %2$s.', 'viabill' ), '<code>' . esc_html( '<closest>|<actual element>' ) . '</code>', '<code>' . esc_html( 'pricetag.closest(<closest>).find(<actual_element>)' ) . '</code>' ),
      'default' => '',
    ),
    'pricetag-product-dynamic-price-trigger'  => array(
      'id'      => 'pricetag-product-dynamic-price-trigger',
      'name'    => __( 'Product dynamic price trigger', 'viabill' ),
      'type'    => 'text',
      'desc'    => sprintf( __( 'If the price is variable then it is possible to trigger an %1$s. %2$s selector is also supported using this attribute.', 'viabill' ), '<code>vb-update-price event</code>', '<code>' . esc_html( '<closest>|<actual elements>' ) . '</code>' ),
      'default' => '',
    ),
    'pricetag-style-product'                  => array(
      'id'      => 'pricetag-style-product',
      'name'    => __( 'Product PriceTag CSS style', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'Here you can add your own custom CSS style to the PriceTag wrapper. Please enter CSS properties following this example: "margin-left: 20px; padding: 10px;".', 'viabill' ),
      'default' => '',
    ),    
    array(
      'id'   => 'pricetag-product',
      'type' => 'sectionend',
    ),

    'pricetag-cart'                           => array(
      'id'   => 'pricetag-cart',
      'name' => __( 'PriceTag cart page', 'viabill' ),
      'type' => 'title',
    ),
    'pricetag-position-cart'                  => array(
      'id'      => 'pricetag-position-cart',
      'name'    => __( 'Cart PriceTag position', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector of the element before which a PriceTag will be inserted on the cart page. If left empty the PriceTag will be inserted afer cart totals.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-cart-dynamic-price'             => array(
      'id'      => 'pricetag-cart-dynamic-price',
      'name'    => __( 'Cart dynamic price selector', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector for the element that contains the total price on the cart.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-cart-dynamic-price-trigger'     => array(
      'id'      => 'pricetag-cart-dynamic-price-trigger',
      'name'    => __( 'Cart dynamic price trigger', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector for the trigger element on the cart page.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-style-cart'                     => array(
      'id'      => 'pricetag-style-cart',
      'name'    => __( 'Cart PriceTag CSS style', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'Here you can add your own custom CSS style to the PriceTag wrapper. Please enter CSS properties following this example: "margin-left: 20px; padding: 10px;".', 'viabill' ),
      'default' => '',
    ),
    array(
      'id'   => 'pricetag-cart',
      'type' => 'sectionend',
    ),

    'pricetag-checkout'                       => array(
      'id'   => 'pricetag-checkout',
      'name' => __( 'PriceTag checkout page', 'viabill' ),
      'type' => 'title',
    ),
    'pricetag-position-checkout'              => array(
      'id'      => 'pricetag-position-checkout',
      'name'    => __( 'Checkout PriceTag position', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector of the element before which a PriceTag will be inserted on the checkout page. If left empty the PriceTag will be inserted, depending if the ViaBill payment gateway is enabled, in the payment method description or under the list of payment methods.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-checkout-dynamic-price'         => array(
      'id'      => 'pricetag-checkout-dynamic-price',
      'name'    => __( 'Checkout dynamic price selector', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector for the element that contains the total price on the checkout page.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-checkout-dynamic-price-trigger' => array(
      'id'      => 'pricetag-checkout-dynamic-price-trigger',
      'name'    => __( 'Checkout dynamic price trigger', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'A Query selector for the trigger element on the checkout page.', 'viabill' ),
      'default' => '',
    ),
    'pricetag-style-checkout'                 => array(
      'id'      => 'pricetag-style-checkout',
      'name'    => __( 'Checkout PriceTag CSS style', 'viabill' ),
      'type'    => 'text',
      'desc'    => __( 'Here you can add your own custom CSS style to the PriceTag wrapper. Please enter CSS properties following this example: "margin-left: 20px; padding: 10px;".', 'viabill' ),
      'default' => '',
    ),
    array(
      'id'   => 'pricetag-checkout',
      'type' => 'sectionend',
    ),

  )
);