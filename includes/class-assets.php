<?php
if (!defined('ABSPATH')) exit;

class VC_Assets {
  public static function enqueue_public(){
    wp_register_style('vc-appoint-public', plugins_url('../public/styles.css', __FILE__), [], '0.2.0');
    wp_register_script('vc-appoint-public', plugins_url('../public/build/widget.js', __FILE__), ['wp-i18n'], '0.2.0', true);
  }
  public static function enqueue_admin($hook){
    if (strpos($hook, 'vc-appoint') === false) return;
    wp_enqueue_style('vc-appoint-admin', plugins_url('../admin/admin.css', __FILE__), [], '0.2.0');
    wp_enqueue_script('vc-appoint-admin', plugins_url('../admin/build/admin.js', __FILE__), ['jquery'], '0.2.0', true);
    wp_localize_script('vc-appoint-admin', 'VC_APPOINT_ADMIN', [
      'rest' => [ 'url' => esc_url_raw( rest_url('vc-appoint/v1/') ), 'nonce' => wp_create_nonce('wp_rest') ]
    ]);
  }
}
