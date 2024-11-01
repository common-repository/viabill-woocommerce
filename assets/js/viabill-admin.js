jQuery(document).ready(function( $ ) {

  viabillMyURLSetup();
  displayViabillCaptureAmount();
  getViabillCaptureForm( viabillSetupCapture );

  setupViabillTermsAndConditions();
  maybeHideRefundButton();

  viaBillSetupCancelOrderButton();

  viaBillDisplayStatusSwitchWarning();

  viaBillDisplayRefundWarning();

  viaBillDisplayDepricatedStatusWarning();

  viaBillDisplayCaptureOnStatusWarning();

  viaBillCaptureSettings();

  $('.viabill-status-refresh').on( 'click', refreshViabillStatus );

  function viaBillSetupCancelOrderButton() {
    if ( $('#viabill-cancel-payment').length ) {
      $('#viabill-cancel-payment').on( 'click', function() {
        var isConfirmed = confirm( viabillAdminScript.msg_order_cancel );
        if ( !isConfirmed ) {
          return;
        }

        var data = {
          action: 'cancel_viabill_payment',
          order_id: $('#post_ID').val(),
          viabill_cancel_nonce: $('input[name="cancel_order_nonce"]').val(),
          _wp_http_referer: $('input[name="_wp_http_referer"]').val()
        };

        $( '#woocommerce-order-items' ).block({
          message: null,
          overlayCSS: {
            background: '#fff',
            opacity: 0.6
          }
        });

        $.post( viabillAdminScript.url, data ).done( function( response ) {
          alert( response.message );
          if ( response.success ) {
            location.reload();
          }
        } )
        .fail( function() {
          displayViabillDefaultError();
        } )
        .always( function() {
          $( '#woocommerce-order-items' ).unblock();
        } );

      } );
    }
  }

  function getViabillCaptureForm( callback ) {
    if ( $('#viabill-capture-payment').length ) {
      var $captureBtn = $('#viabill-capture-payment');
      var $row = $captureBtn.parents('.wc-order-data-row');
      $.get(
        viabillAdminScript.url,
        {
          action: 'get_viabill_capture_form',
          order_id: $('#post_ID').val(),
          viabill_show_capture_form_nonce: $('input[name="viabill_show_capture_form_nonce"]').val()
        }
      ).done( function( data ) {
        if ( /<[a-z][\s\S]*>/i.test( data ) ) { // is HTML
          data = data.trim();
          $row.after( data );
          if ( typeof callback === 'function' ) {
            callback( $captureBtn );
          }
        } else if ( typeof data === 'object' ) {
          try {
            if ( !data.success && data.message ) {
              $captureBtn.on( 'click', function () {
                alert( data.message );
              } );
            } else {
              $captureBtn.on( 'click', displayViabillCaptureFormInitError );
            }
          } catch ( e ) {
            $captureBtn.on( 'click', displayViabillCaptureFormInitError );
          }
        }
      } );
    }
  }

  function displayViabillCaptureAmount() {
    $( document ).on( 'keyup', '#capture-viabil-amount', function () {

      var $captureAmount = $('#do-viabill-capture .wc-order-capture-amount');
      if ( $captureAmount.length ) {

        var total = $( this ).val();
        if ( accounting && accounting.unformat && woocommerce_admin && woocommerce_admin.mon_decimal_point ) {
          total = accounting.unformat( total, woocommerce_admin.mon_decimal_point );
        }

        try {
          if ( accounting && accounting.formatMoney && woocommerce_admin_meta_boxes ) {
            total = accounting.formatMoney( total, {
              symbol:    woocommerce_admin_meta_boxes.currency_format_symbol,
              decimal:   woocommerce_admin_meta_boxes.currency_format_decimal_sep,
              thousand:  woocommerce_admin_meta_boxes.currency_format_thousand_sep,
              precision: woocommerce_admin_meta_boxes.currency_format_num_decimals,
              format:    woocommerce_admin_meta_boxes.currency_format
            } );
          }
        } catch (e) {
          total = $( this ).val();
        }

        $captureAmount.html( total );
      }
    } );
  }

  function viabillMyURLSetup() {
    if ( $('.viabill-dashboard-link').length ) {
      // fetch viabill dashboard links via ajax.
      $.get(
        viabillAdminScript.url,
        { action: 'get_my_viabill_url' }
      ).done( function( url ) {
        if ( url !== undefined && url ) {
          $('.viabill-dashboard-link').each( function() {
            $(this).attr( 'href', url );
          } );
        }
      } );
    }
  }

  function viabillSetupCapture( $btn ) {
    $btn.on( 'click', function() {
      $('div.wc-order-viabill-capture-payment').slideDown();
      $('div.wc-order-data-row-toggle').not('div.wc-order-viabill-capture-payment').slideUp();
      $('div.wc-order-totals-items').slideUp();
      $('#woocommerce-order-items').find('div.refund').show();
      $('.wc-order-edit-line-item .wc-order-edit-line-item-actions').hide();
    } );

    $('#do-viabill-capture').on( 'click', captureViabillAmount );
  }

  function captureViabillAmount() {
    $('#do-viabill-capture').off( 'click' );

    var $amount = $('#capture-viabil-amount')
        amount  = $amount.val();

    if ( !$amount.length || !amount ) {
      alert( 'Please enter valid amount and try again.' );
      return;
    }

    if ( viabillDecimalsCount( amount ) > 2 ) {
      var confirmed = window.confirm( viabillAdminScript.msg_order_rounded_amount );
      if ( ! confirmed ) {
        return;
      }
    }

    var data = {
      action: 'capture_viabill_payment',
      amount: amount,
      order_id: $('#post_ID').val(),
      viabill_capture_nonce: $('input[name="viabill_capture_nonce"]').val(),
      _wp_http_referer: $('input[name="_wp_http_referer"]').val()
    };

    $( '#woocommerce-order-items' ).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });

    $.post( viabillAdminScript.url, data ).done( function( response ) {
      if ( response.success ) {
        alert( 'Successfully captured!' );

        $( 'div.wc-order-data-row-toggle' ).not( 'div.wc-order-bulk-actions' ).slideUp();
        $( 'div.wc-order-bulk-actions' ).slideDown();
        $( 'div.wc-order-totals-items' ).slideDown();
        $( '#woocommerce-order-items' ).find( 'div.refund' ).hide();
        $( '.wc-order-edit-line-item .wc-order-edit-line-item-actions' ).show();

        location.reload();
      } else {
        ! alert( response.message ) && location.reload();
      }
    } )
    .fail( function() {
      displayViabillDefaultError();
    } )
    .always( function() {
      $( '#woocommerce-order-items' ).unblock();
      $('#do-viabill-capture').on( 'click', captureViabillAmount );
    } );
  }

  function refreshViabillStatus(e) {
    e.preventDefault();

    var confirmed = window.confirm( viabillAdminScript.msg_order_status_sync );
    if ( ! confirmed ) {
      return;
    }

    $('.viabill-status-refresh').off( 'click' );

    var data = {
      action: 'get_viabill_status',
      order_id: $('#post_ID').val(),
      viabill_status_refresh: $('input[name="viabill_status_refresh"]').val(),
      _wp_http_referer: $('input[name="_wp_http_referer"]').val()
    };

    $( '.viabill-status' ).block({
      message: null,
      overlayCSS: {
        background: '#fff',
        opacity: 0.6
      }
    });

    $.post( viabillAdminScript.url, data ).done( function( response ) {
      if ( response.success ) {
        ! alert( 'Status synced successfully!' ) && location.reload();
      } else {
        alert( 'Status sync failed, please try again later or check the logs for more information.' );
      }
    } )
    .fail( function() {
      displayViabillDefaultError();
    } )
    .always( function() {
      $( '.viabill-status' ).unblock();
      $('.viabill-status-refresh').on( 'click', refreshViabillStatus );
    } );
  }

  function setupViabillTermsAndConditions() {
    var $terms = $( '#viabill-terms' );
    if ( !$terms.length ) {
      return;
    }

    // update terms URL if selected country changes
    var $termsLink  = $('#viabill-terms-link');
    var $regCountry = $('#viabill-reg-country');
    var baseURL     = $termsLink.attr( 'href' );

    if ( $regCountry.length && $termsLink.length ) {
      $termsLink.attr( 'href', baseURL + '#' + $regCountry.val() );
      $regCountry.on( 'change', function() {
        $termsLink.attr( 'href', baseURL + '#' + $regCountry.val() );
      } );
    }
  }

  function displayViabillCaptureFormInitError() {
    alert( viabillAdminScript.msg_error_capture );
  }

  function displayViabillDefaultError() {
    alert( viabillAdminScript.msg_error_default );
  }

  function maybeHideRefundButton() {
    // hide refund button if order viabill status is different than 'captured'
    var isPost = Boolean( $('#post').length );
    var status = $('#viabill-status').data('status');

    if ( isPost && status ) {
      var refundBtn = $('.refund-items');
      if ( ['captured', 'captured_partially', 'refunded_partially'].indexOf(status) === -1 && refundBtn.length ) {
        refundBtn.remove();
      }
    }
  }

  function viaBillDisplayStatusSwitchWarning() {
    // display confirm alert if trying to edit order which has not received ViaBill response
    $('.save_order').on('click', function(e) {
        let isViabil = (('viabill_official' === $('#_payment_method').val())||
                        ('viabill_try' === $('#_payment_method').val())),
          pendingApproval = 'pending_approval' === $('#viabill-status').data('status');

      if (isViabil && pendingApproval) {
        e.preventDefault();
        var confirmed = window.confirm( viabillAdminScript.msg_order_switch_status );

        if ( confirmed ) {
          $('#post').submit();
        }
      }
    });
  }

  function viaBillDisplayRefundWarning() {
    // display confirm alert if changing order status to refunded
    $('.save_order').on('click', function(e){      
      let isViabil = (('viabill_official' === $('#_payment_method').val())||
                      ('viabill_try' === $('#_payment_method').val())),
          refundStatus  = 'wc-refunded' === $('#order_status').val(),
          viabillStatus = $('#viabill-status').data('status');

      if (isViabil && refundStatus && 'yes' === viabillAdminScript.automatic_refund) {
        e.preventDefault();
        let confirmed = window.confirm(viabillAdminScript.msg_order_status_refund);

        if ( ['captured', 'captured_partially', 'refunded_partially'].indexOf(status) !== -1 ) {
          let confirmedEmpty = window.confirm(viabillAdminScript.msg_order_status_refund_empty);
          confirmed = confirmed && confirmedEmpty;
        }

        if (confirmed) {
          $('#post').submit();
        }
      }
    });
  }

  function viaBillDisplayDepricatedStatusWarning() {
    // display confirm alert if changing order status to refunded
    $('.save_order').on('click', function(e){      
      let isViabil = (('viabill_official' === $('#_payment_method').val())||
                      ('viabill_try' === $('#_payment_method').val())),
          status   = $('#order_status').val();

      if (isViabil && status.includes('wc-viabill')) {
        e.preventDefault();
        window.alert( viabillAdminScript.msg_order_status_depricated );
      }
    });
  }

  function viaBillDisplayCaptureOnStatusWarning() {
    // display confirm alert if changing order status to processing
    $('.save_order').on('click', function(e){
      let isViabil = (('viabill_official' === $('#_payment_method').val())||
                      ('viabill_try' === $('#_payment_method').val())),
          status   = $('#order_status').val();

      if (isViabil && 'yes' === viabillAdminScript.status_capture && 'wc-processing' === status) {
        e.preventDefault();
        let confirmed = window.confirm(viabillAdminScript.msg_order_status_capture);

        if (confirmed) {
          $('#post').submit();
        }
      }
    });
  }

  function viaBillCaptureSettings() {
    // If auto-capture option is enabled disable capture-order-on-status-switch option.
    $('#woocommerce_viabill_auto-capture').on('change', function(){
      var $this = $(this),
          $statusSwitchSelect = $('#woocommerce_viabill_capture-order-on-status-switch');
      if ( 'yes' === $this.val() ) {
        $statusSwitchSelect.val('no').attr('disabled', true).trigger('change');
      } else {
        $statusSwitchSelect.removeAttr('disabled');
      }
    });
  }

  function viabillDecimalsCount(number) {
    number = parseFloat(number);
    if (!Number.isFinite(number)) return 0;
    var e = 1;
    while (Math.round(number * e) / e !== number) e *= 10;
    return Math.log10(e);
  }  
  
  if ($("#DisableThirdPartyPaymentBtn").length) {
    $("#DisableThirdPartyPaymentBtn").click(function() {
      $.ajax({
        method: 'get',
        url: viabillAdminScript.disable_third_party_gateway_url,
        data: null,
        dataType: "text",
        success: function(data) {
            alert(data); 
            location.reload();
        },
        error: function(e) {
            console.log(e);
        }
      });             
    });
  }

});
