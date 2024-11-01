<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_DB_Update' ) ) {
  /**
   * Viabill_Background_Processing class
   *
   * @since 1.1.0
   */
  class Viabill_DB_Update {
    /**
     * API's interface.
     *
     * @var Viabill_Connector
     */
    private $connector;

    /**
     * Database update statuses.
     *
     * @var $statuses
     */
    private $statuses = array(
      'wc-viabill-approved' => 'wc-on-hold',
      'wc-viabill-captured' => 'wc-processing',
      'wc-pending'          => 'wc-pending',
    );

    /**
     * Class constructor.
     */
    public function init() {
      $this->connector = new Viabill_Connector();

      add_action( 'admin_init', array( $this, 'install_actions' ) );
      add_action( 'update_order_status_batch', array( $this, 'update_order_status_batch' ), 10, 2 );

      $this->init_update_state();
    }

    /**
     * Add initial update value.
     */
    private function init_update_state() {
      if ( is_null( get_option( 'viabill_db_update', null ) ) ) {
        $this->set_update_state( $this->statuses );
      }
    }

    /**
     * Sets update option value.
     *
     * @param mixed $state state update process
     */
    private function set_update_state( $state ) {
      update_option( 'viabill_db_update', is_scalar( $state ) ? wp_json_encode( $state ) : $state );
    }

    /**
     * Get update progress.
     */
    public static function get_update_state() {
      return get_option( 'viabill_db_update', false );
    }

    /**
     * Check specific update state.
     */
    public static function is_update_state( $state ) {
      $update_progress = self::get_update_state();

      if ( ! $update_progress ) {
        return false;
      }

      switch ( $state ) {
        case 'done':
          $return = ( 'done' === $update_progress['wc-viabill-approved'] && 'done' === $update_progress['wc-viabill-captured'] && 'done' === $update_progress['wc-pending'] );
          break;

        case 'in-progress':
          $return = ( is_null( $update_progress['wc-viabill-approved'] ) && is_null( $update_progress['wc-viabill-captured'] ) && is_null( $update_progress['wc-pending'] ) );
          break;

        default:
          $return = false;
          break;
      }

      return $return;
    }

    /**
     * Display admin notice to update database.
     */
    public static function show_update_field() {
      return sprintf(
        __( 'If you updated the ViaBill plugin from before version 1.1.0 to a later version, we strongly advise you to start a database update to ensure that there are no order conflicts. Performing a database update will only update ViaBill related data in the database, no other data will be accessed or altered. Before starting the update we recommend you create a backup of your database! %1$s Start the update %2$s', 'viabill' ),
        '<a href="' . esc_url( wp_nonce_url( add_query_arg( 'db_update_viabill', 'true', Viabill_Main::get_settings_link() ), 'vb_db_update', 'vb_db_update_nonce' ) ) . '">',
        '</a>'
      );
    }

    /**
     * Install actions when a update button is clicked within the admin area.
     *
     * This function is hooked into admin_init to affect admin only.
     */
    public function install_actions() {
      if ( ! empty( $_GET['db_update_viabill'] ) ) {
        check_admin_referer( 'vb_db_update', 'vb_db_update_nonce' );

        if ( ! as_next_scheduled_action( 'update_order_status_batch' ) ) {
          $this->set_update_state( array_map( '__return_null', $this->statuses ) );

          update_user_meta( get_current_user_id(), 'dismissed_viabil_updating_notice', false );
          WC_Admin_Notices::add_custom_notice( 'viabil_updating', sprintf( __( '%1$s ViaBill Notice - %2$s Database is currently being updated in the background.', 'viabill' ), '<strong>', '</strong>' ) );

          array_map( array( 'Viabill_DB_Update', 'update_order_status_batch' ), array_flip( $this->statuses ) );
        }
      }
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
     * Handle fetching batches and creating scheduled batch handling
     *
     * @param string $status Status which will be updated.
     */
    public function update_order_status_batch( $status, $batch = 0 ) {
      $old_status_orders = $this->get_old_status_orders( $status, 'wc-pending' === $status ? $batch : 0 );

      if ( $old_status_orders ) {
        as_schedule_single_action(
          time() + 1,
          'update_order_status_batch',
          array(
            'status' => $status,
            'batch'  => $batch + 10,
          ),
          'viabill_db_update'
        );
        $this->update_order_status( $old_status_orders, $status );
      } else {
        $progress = self::get_update_state();

        if ( 'done' !== $progress[ $status ] ) {
          $progress[ $status ] = 'done';
          $this->set_update_state( $progress );
        }

        if ( self::is_update_state( 'done' ) ) {
          WC_Admin_Notices::remove_notice( 'viabil_updating' );
          update_user_meta( get_current_user_id(), 'dismissed_viabil_updating_notice', true );
          update_user_meta( get_current_user_id(), 'dismissed_viabil_updated_notice', false );
          WC_Admin_Notices::add_custom_notice( 'viabil_updated', sprintf( __( '%1$s ViaBill Notice - %2$s Database update has been finished.', 'viabill' ), '<strong>', '</strong>' ) );
        }
      }
    }

    /**
     * Update statuses for batch received
     *
     * @param array  $rows   Batch of rows for update.
     * @param string $status Status to update.
     */
    private function update_order_status( $rows, $status ) {
      if ( ! $rows || ! is_array( $rows ) ) {
        return;
      }

      foreach ( $rows as $row ) {
        $order = wc_get_order( $row['ID'] );

        if ( 'wc-pending' === $status ) {
          if ( $this->is_viabill_payment($order->get_payment_method()) ) {
            $old_status = $order->get_meta( 'viabill_status' );

            if ( ! $old_status ) {
              $new_status = $this->connector->get_status( $order );

              $order->update_meta_data( 'viabill_status', isset( $new_status['errors'] ) ? substr( $status, 3 ) : strtolower( $new_status['state'] ) );
            } elseif ( count( $rows ) < 10 ) {
              as_unschedule_action(
                'update_order_status_batch',
                array(
                  'status' => $status,
                ),
                'viabill_db_update'
              );

              $progress = self::get_update_state();

              if ( 'done' !== $progress[ $status ] ) {
                $progress[ $status ] = 'done';
                $this->set_update_state( $progress );
              }
            }
          }
        } else {
          $order->update_meta_data( 'viabill_status', substr( $status, 11 ) );

          $this->update_order_new_status( absint( $row['ID'] ), $this->statuses[ $status ] );
        }

        $order->save();
      }
    }

    /**
     * Fetch batch of 10 orders from database for status update
     *
     * @param string $status Orders with this status will be fetched
     * @param string $offset Offset in the database
     */
    private function get_old_status_orders( $status, $offset = 0 ) {
      global $wpdb;

      /* phpcs:disable
       * Disable phpcs rules - WordPress.DB.DirectDatabaseQuery.DirectQuery - Direct query needed to fetch data from database
       *                     - WordPress.DB.DirectDatabaseQuery.NoCaching - No chaching because we need the newest data
       */
      return $wpdb->get_results(
        $wpdb->prepare(
          "
          SELECT ID FROM {$wpdb->posts}
          WHERE post_status = %s
          ORDER BY ID DESC
          LIMIT 10
          OFFSET %d
          ",
          $status,
          $offset
        ),
        ARRAY_A
      );
      // phpcs:enable
    }

    /**
     * Update single database row in table wp_posts
     *
     * @param int    $order_id ID of an entry to update
     * @param string $status   New status which will be inserted
     */
    private function update_order_new_status( $order_id, $status ) {
      global $wpdb;
      /* phpcs:disable
       * Disable phpcs rules - WordPress.DB.DirectDatabaseQuery.DirectQuery - Direct query needed to edit database entries to avoid triggering code exections
       *                     - WordPress.DB.DirectDatabaseQuery.NoCaching - No chaching since we are updating not fetching
       */
      $wpdb->update(
        $wpdb->posts,
        array(
          'post_status' => $status,
        ),
        array(
          'ID' => $order_id,
        )
      );
      // phpcs:enable
    }
  }
}
