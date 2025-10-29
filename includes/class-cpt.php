<?php
if (!defined('ABSPATH')) exit;

class VC_CPT {
  public static function register(){
    register_post_type('vc_service', [
      'labels' => [ 'name' => __('Services','valcode-appoint') ],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-scissors',
      'supports' => ['title','editor','thumbnail'],
      'show_in_rest' => true
    ]);

    register_post_type('vc_staff', [
      'labels' => [ 'name' => __('Staff','valcode-appoint') ],
      'public' => false,
      'show_ui' => true,
      'menu_icon' => 'dashicons-groups',
      'supports' => ['title','editor','thumbnail'],
      'show_in_rest' => true
    ]);
  }
}
