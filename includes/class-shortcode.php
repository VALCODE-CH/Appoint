<?php
if (!defined('ABSPATH')) exit;

class VC_Shortcode {
  public static function register(){
    add_shortcode('vc_appoint', [__CLASS__, 'render']);
  }
  public static function render($atts){
    $atts = shortcode_atts(['service' => '', 'staff' => '', 'duration' => '30'], $atts);
    wp_enqueue_style('vc-appoint-public');
    wp_enqueue_script('vc-appoint-public');
    $data = [
      'service' => $atts['service'],
      'staff'   => $atts['staff'],
      'duration'=> $atts['duration'],
      'rest'    => [ 'url' => esc_url_raw( rest_url('vc-appoint/v1/') ), 'nonce' => wp_create_nonce('wp_rest') ]
    ];
    wp_localize_script('vc-appoint-public', 'VC_APPOINT', $data);
    return '<div id="vc-appoint-widget"></div>';
  }
}
