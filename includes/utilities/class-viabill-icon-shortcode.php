<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'Viabill_Icon_Shortcode' ) ) {
  /**
   * Viabill_Icon_Shortcode class
   *
   * @since 0.1.
   */
  class Viabill_Icon_Shortcode {
    /**
     * Register the shortcode for the further usage.
     *
     * @return void
     */
    public function register() {
      add_shortcode( 'viabill_icon', array( $this, 'do_shortcode' ) );
    }

    /**
     * Display link with the image (ViaBill logo). $attributes should contain
     * next parameters:
     *  'type'    => string ('blue' or 'white'),
     *  'width'   => int|float
     *  'height'  => int|float
     *
     * @param  array $attributes
     * @return void
     */
    public function do_shortcode( $attributes ) {
      $a = shortcode_atts(
        array(
          'type' => 'blue',
        ),
        $attributes
      );

      try {
        if ( ! isset( $attributes['width'] ) && ! isset( $attributes['height'] ) ) {
          $a['width']  = 398 / 4;
          $a['height'] = 90 / 4;
        } elseif ( isset( $attributes['width'] ) ) {
          $a['width']  = floatval( $attributes['width'] );
          $a['height'] = 90 * ( $a['width'] / 398 );
        } else {
          $a['height'] = floatval( $attributes['height'] );
          $a['width']  = 398 * ( $a['height'] / 90 );
        }
      } catch ( Exception $e ) {
        $a['width']  = 398 / 4;
        $a['height'] = 90 / 4;
      }

      $a['width']  = intval( $a['width'] );
      $a['height'] = intval( $a['height'] );

      $img_url = VIABILL_DIR_URL;
      switch ( $a['type'] ) {
        case 'blue':
          $img_url .= 'assets/img/viabill_logo_blue.png';
          break;
        default:
          $img_url .= 'assets/img/viabill_logo_white.png';
          break;
      }
      ?>
      <a href="https://www.viabill.dk/" target="_blank" title="<?php esc_html_e( 'ViaBill - try, before you buy', 'viabill' ); ?>">
        <img
          src="<?php echo $img_url; ?>"
          alt="<?php esc_attr_e( 'ViaBill logo', 'viabill' ); ?>"
          width="<?php echo $a['width']; ?>"
          height="<?php echo $a['height']; ?>"
        >
      </a>
      <?php
    }
  }
}
