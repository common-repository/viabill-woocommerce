<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Logger' ) ) {
  /**
   * Viabill_Logger class
   *
   * @since 0.1
   */
  class Viabill_Logger {
    /**
     * Whether or not logging is enabled.
     *
     * @var bool
     */
    public $is_log_enabled = false;

    /**
     * List of valid logger levels, from most to least urgent.
     *
     * @var array
     */
    public $log_levels = array(
      'emergency',
      'alert',
      'critical',
      'error',
      'warning',
      'notice',
      'info',
      'debug',
    );

    /**
     * Init logger.
     *
     * @param bool $is_log_enabled Defaults to false.
     */
    public function __construct( $is_log_enabled = false ) {
      $this->toggle_logger( $is_log_enabled );
    }

    /**
     * Enable logger.
     *
     * @param  bool $is_log_enabled
     * @return void
     */
    public function toggle_logger( $is_log_enabled ) {
      $this->is_log_enabled = $is_log_enabled;
    }

    /**
     * Logs given message for given level and return true if successful, false
     * otherwise.
     *
     * @param  string  $message
     * @param  string  $level    Check $log_levels for valid level values, defaults to 'info'.
     * @return bool
     */
    public function log( $message, $level = 'info' ) {
      if ( ! $this->is_log_enabled ) {
        return false;
      }

      if ( empty( $this->logger ) ) {
        if ( function_exists( 'wc_get_logger' ) ) {
          $this->logger = wc_get_logger();
        } else {
          return false;
        }
      }

      // Check if provided level is valid and fall back to 'notice' level if not.
      if ( ! in_array( $level, $this->log_levels, true ) ) {
        $this->log( 'Invalid log level provided: ' . $level, 'debug' );
        $level = 'notice';
      }

      // Check if api key is in the message and redact it.
      if ( strpos( $message, 'apiKey' ) !== -1 ) {
        $message = preg_replace( '/(?<=apiKey=).{0,123}/', '*****', $message );
      }

      $this->logger->log(
        $level,
        $message,
        array(
          'source' => VIABILL_PLUGIN_ID,
        )
      );

      return true;
    }

    /**
     * Returns the filepath of the file that contains the log data     
     *
     * @return string
     */
    public function getFilepath() {
       $filepath = wc_get_log_file_path( VIABILL_PLUGIN_ID );
       return $filepath;
    }

  }
}
