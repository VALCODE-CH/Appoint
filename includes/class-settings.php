<?php
if (!defined('ABSPATH')) exit;

class VC_Settings {
  public static function admin_menu(){
    add_menu_page(__('Valcode Appoint','valcode-appoint'), __('Valcode Appoint','valcode-appoint'), 'manage_options', 'vc-appoint', [__CLASS__,'render_admin'], 'dashicons-calendar-alt', 56);
  }

  public static function render_admin(){
    ?>
    <div class="wrap">
      <h1>Valcode Appoint</h1>
      <div id="vc-appoint-admin"></div>
    </div>
    <?php
  }

  public static function get($key, $default = ''){
    $opts = get_option('vc_appoint_settings', []);
    return $opts[$key] ?? $default;
  }

  public static function set($key, $val){
    $opts = get_option('vc_appoint_settings', []);
    $opts[$key] = $val;
    update_option('vc_appoint_settings', $opts);
  }

  public static function branding_vars(){
    $vars = [];
    $map = [
      '--vc-font' => self::get('font', ''),
      '--vc-radius' => self::get('radius', ''),
      '--vc-bg' => self::get('bg', ''),
      '--vc-surface' => self::get('surface', ''),
      '--vc-text' => self::get('text', ''),
      '--vc-accent' => self::get('accent', ''),
      '--vc-muted' => self::get('muted', ''),
      '--vc-border' => self::get('border', ''),
    ];
    foreach ($map as $k=>$v){
      if ($v!=='') $vars[] = $k.':'.$v.';';
    }
    return implode('', $vars);
  }
}
