<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Notices' ) ) {
  /**
   * Viabill_Notices class
   *
   * @since 0.1
   */
  class Viabill_Notices {

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
     * Cron hook name.
     *
     * @static
     * @var string
     */
    const CRON_HOOK_NAME = 'viabill_notice_cron';

    /**
     * Settings page slug.
     *
     * @static
     * @var string
     */
    const SLUG = 'viabill-notices';

    /**
     * Database option key name.
     *
     * @static
     * @var string
     */
    const DB_KEY = 'viabill_notifications';

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
     * Initialize action hooks for notices page and CRON if merchant is already
     * registered.
     *
     * @return void
     */
    public function init() {
      if ( Viabill_Main::is_merchant_registered() ) {
        add_action( 'admin_menu', array( $this, 'register_settings_page' ), 200 );
        add_action( 'current_screen', array( $this, 'maybe_show_wp_notice' ) );

        // WP Cron hooks.
        add_action( self::CRON_HOOK_NAME, array( $this, 'update' ) );
        if ( ! wp_next_scheduled( self::CRON_HOOK_NAME ) ) {
          $is_scheduled = wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK_NAME );
          if ( ! $is_scheduled ) {
            $this->logger->log( 'Failed to schedule CRON event for ViaBill notices.', 'critical' );
          }
        }
      }
    }

    /**
     * Add given message to database. Return true if updated or false otherwise.
     *
     * @param  string  $message
     * @param  bool    $is_seen Defaults to false.
     * @return bool
     */
    public function add_custom( $message, $is_seen = false ) {
      $notifications = $this->get_from_db();
      if ( $this->is_message_saved( $message, $notifications ) ) {
        return false;
      }
      $this->push_to_array( $message, $notifications, $is_seen );
      return $this->save_to_db( $notifications );
    }

    /**
     * Push given message to notifications array.
     *
     * @param  string  $message
     * @param  array   $notifications
     * @param  bool    $is_seen       Defaults to false.
     * @return void
     */
    private function push_to_array( $message, &$notifications, $is_seen = false ) {
      array_push(
        $notifications,
        array(
          'message' => $message,
          'date'    => time(),
          'seen'    => $is_seen,
        )
      );
    }

    /**
     * Update notifications from the ViaBill's API.
     */
    public function update() {
      $raw = $this->connector->get_notifications();
      if ( isset( $raw['messages'] ) && is_array( $raw['messages'] ) ) {
        $new_messages  = false;
        $notifications = $this->get_from_db();
        foreach ( $raw['messages'] as $message ) {
          if ( ! $this->is_message_saved( $message, $notifications ) ) {
            $new_messages = true;
            $this->push_to_array( $message, $notifications );
          }
        }

        if ( $new_messages ) {
          $this->save_to_db( $notifications );
        }
      }
    }

    /**
     * Return notices settings page URL.
     *
     * @static
     * @return string
     */
    public static function get_admin_url() {
      return get_admin_url( null, 'admin.php?page=' . self::SLUG );
    }

    /**
     * Is the notification message already saved in the database.
     *
     * @param  string  $message
     * @param  array   $notifications
     * @return bool
     */
    private function is_message_saved( $message, $notifications ) {
      $is_message_saved = false;
      foreach ( $notifications as $notification ) {
        if ( $notification['message'] === $message ) {
          $is_message_saved = true;
          break;
        }
      }
      return $is_message_saved;
    }

    /**
     * Mark all the provided notifications as seen.
     * Notifications should be an array of array with the following structure:
     *    ['message' => string, 'date' => UNIX timestamp string, 'seen' => bool]
     *
     * @param array $notifications
     */
    private function mark_as_seen( $notifications ) {
      $notifications_count = count( $notifications );
      for ( $i = 0; $i < $notifications_count; $i++ ) {
        $notifications[ $i ]['seen'] = true;
      }
      $this->save_to_db( $notifications );
    }

    /**
     * Serialize and save provided array of notifications to the database and
     * return true if updated or false if failed to update.
     * Notifications should be an array of arrays with the following structure:
     *    ['message' => string, 'date' => UNIX timestamp string, 'seen' => bool]
     *
     * @param  array  $notifications
     * @return bool
     */
    private function save_to_db( $notifications ) {
      $serialized = maybe_serialize( $notifications );
      if ( ! update_option( self::DB_KEY, $serialized ) ) {
        $this->logger->log( 'Failed to save ViaBill notices to the database.', 'error' );
        return false;
      }

      return true;
    }

    /**
     * Retrieve notifications from the database.
     * Notifications should be an array of arrays with the following structure:
     *    ['message' => string, 'date' => UNIX timestamp string, 'seen' => bool]
     *
     * @return array
     */
    private function get_from_db() {
      $messages = get_option( self::DB_KEY, array() );
      $messages = maybe_unserialize( $messages );
      return $messages;
    }

    /**
     * Delete notifications from the database. Returns true if is successfully
     * deleted or false on failure, or if option does not exist.
     *
     * @return bool
     */
    public function delete_from_db() {
      return delete_option( self::DB_KEY );
    }

    /**
     * Get number of unread notifications.
     *
     * @return int
     */
    public function get_unseen_count() {
      $count         = 0;
      $notifications = $this->get_from_db();
      foreach ( $notifications as $notification ) {
        if ( isset( $notification['seen'] ) && ! $notification['seen'] ) {
          $count++;
        }
      }
      return $count;
    }

    /**
     * Unschedule next cron event.
     */
    public function destroy_cron() {
      $timestamp = wp_next_scheduled( self::CRON_HOOK_NAME );
      wp_unschedule_event( $timestamp, self::CRON_HOOK_NAME );
    }

    /**
     * Display notices or registration form if user not registered.
     */
    public function show() {
      ?>
      <div class="wrap">
        <h2><?php esc_html_e( 'ViaBill Notices', 'viabill' ); ?></h2>
        <br>
        <a class="button-secondary" href="<?php echo esc_attr( Viabill_Main::get_settings_link() ); ?>"><?php esc_html_e( 'ViaBill settings', 'viabill' ); ?></a>
        <br><br><br>
        <table class="wp-list-table widefat fixed striped" cellspacing="0">
            <thead>
              <tr>
                <th class="manage-column column-cb check-column" scope="col"></th>
                <th class="manage-column column-columnname" scope="col">
                  <span><?php esc_html_e( 'Notice', 'viabill' ); ?></span>
                </th>
                <th class="manage-column column-columnname date" scope="col">
                  <span><?php esc_html_e( 'Date', 'viabill' ); ?></span>
                </th>
              </tr>
            </thead>

            <tfoot>
              <tr>
                <th class="manage-column column-cb check-column" scope="col"></th>
                <th class="manage-column column-columnname" scope="col">
                  <?php esc_html_e( 'Notice', 'viabill' ); ?>
                </th>
                <th class="manage-column column-columnname date" scope="col">
                  <?php esc_html_e( 'Date', 'viabill' ); ?>
                </th>
              </tr>
            </tfoot>
            <?php
            $this->update();

            $notifications = $this->get_from_db();
            // Sort from low to high.
            uasort(
              $notifications,
              function( $a, $b ) {
                if ( $a == $b ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                  return 0;
                }
                return $a < $b ? 1 : -1;
              }
            );
            ?>
            <tbody>
              <?php if ( ! empty( $notifications ) ) : ?>
                <?php $date_format = get_option( 'date_format' ); ?>
                <?php foreach ( $notifications as $notification ) : ?>
                  <?php $seen = $notification['seen']; ?>
                  <tr class="alternate">
                    <th class="check-column" scope="row"></th>
                    <td class="column-columnname">
                      <?php if ( ! $seen ) : ?>
                        <b>
                      <?php endif; ?>
                        <?php echo $notification['message']; ?>
                        <?php // Intentionally echoed directly because messages are always in English. ?>
                        <?php echo 'More information on'; ?>
                        <a href="" class="viabill-dashboard-link" target="_blank"><?php echo 'My ViaBill' ?></a>.
                      <?php if ( ! $seen ) : ?>
                        </b>
                      <?php endif; ?>
                    </td>
                    <td class="column-columnname">
                      <?php if ( ! $seen ) : ?>
                        <b>
                      <?php endif; ?>
                        <?php echo( date( $date_format, $notification['date'] ) ); ?>
                      <?php if ( ! $seen ) : ?>
                        </b>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php $this->mark_as_seen( $notifications ); ?>
              <?php else : ?>
                <tr class="alternate">
                  <th class="check-column" scope="row"></th>
                  <td colspan="2">
                    <?php esc_html_e( 'No recorded notifications', 'viabill' ); ?>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
      </div>
      <?php
    }

    /**
     * Show wp notice if there are unreaded notices from the ViaBill. Should be
     * called at/after 'current_screen' action hook trigger, but before
     * 'admin_notices' action hook trigger.
     */
    public function maybe_show_wp_notice() {
      if ( function_exists( 'get_current_screen' ) ) {
        $screen                 = get_current_screen();
        $show_on_current_screen = is_a( $screen, 'WP_Screen' ) && $screen->id !== 'woocommerce_page_' . self::SLUG;
      } else {
        $show_on_current_screen = true;
      }

      $show          = false;
      $notifications = $this->get_from_db();
      if ( ! empty( $notifications ) ) {
        foreach ( $notifications as $notification ) {
          if ( isset( $notification['seen'] ) && ! $notification['seen'] ) {
            $show = true;
            break;
          }
        }
      }

      if ( $show && $show_on_current_screen ) {
        add_action( 'admin_notices', array( $this, 'show_wp_notice' ) );
      }
    }

    /**
     * Echo register notice HTML.
     */
    public function show_wp_notice() {
      ?>
      <div class="notice notice-info">
        <p>
          <?php esc_html_e( 'You have new', 'viabill' ); ?>
          <a href="<?php echo Viabill_Notices::get_admin_url(); ?>"><?php esc_html_e( 'ViaBill notice', 'viabill' ); ?></a>.
        </p>
      </div>
      <?php
    }

    /**
     * Register submenu page for the notices.
     *
     * @return void
     */
    public function register_settings_page() {
      add_submenu_page(
        'woocommerce',
        __( 'My ViaBill Notices', 'viabill' ),
        __( 'ViaBill Notices', 'viabill' ),
        'manage_woocommerce',
        self::SLUG,
        array( $this, 'show' )
      );
    }
  }
}
