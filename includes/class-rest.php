<?php
if (!defined('ABSPATH')) exit;

class VC_REST {
  public static function register_routes(){
    register_rest_route('vc-appoint/v1', '/availability', [
      'methods' => 'GET','permission_callback' => '__return_true','callback' => [__CLASS__, 'availability']
    ]);
    register_rest_route('vc-appoint/v1', '/book', [
      'methods' => 'POST','permission_callback' => '__return_true','callback' => [__CLASS__, 'book']
    ]);
    register_rest_route('vc-appoint/v1', '/cancel', [
      'methods' => 'POST','permission_callback' => '__return_true','callback' => [__CLASS__, 'cancel']
    ]);
    register_rest_route('vc-appoint/v1', '/me/bookings', [
      'methods' => 'GET','permission_callback' => function(){ return is_user_logged_in(); },'callback' => [__CLASS__, 'my_bookings']
    ]);
    // admin endpoints (cap check)
    register_rest_route('vc-appoint/v1', '/admin/bookings', [
      'methods' => 'GET','permission_callback' => function(){ return current_user_can('manage_options'); },'callback' => [__CLASS__, 'admin_bookings']
    ]);
    register_rest_route('vc-appoint/v1', '/admin/schedules', [
      'methods' => 'GET','permission_callback' => function(){ return current_user_can('manage_options'); },'callback' => [__CLASS__, 'get_schedules']
    ]);
    register_rest_route('vc-appoint/v1', '/admin/schedules', [
      'methods' => 'POST','permission_callback' => function(){ return current_user_can('manage_options'); },'callback' => [__CLASS__, 'save_schedule']
    ]);
    register_rest_route('vc-appoint/v1', '/admin/settings', [
      'methods' => 'POST','permission_callback' => function(){ return current_user_can('manage_options'); },'callback' => [__CLASS__, 'save_settings']
    ]);
  }

  public static function availability($req){
    $service = absint($req->get_param('service'));
    $staff = absint($req->get_param('staff'));
    $date = $req->get_param('date') ?: current_time('Y-m-d');
    $duration = absint($req->get_param('duration') ?: 30);
    if (!$staff) return rest_ensure_response(['date'=>$date,'slots'=>[]]);
    $slots = VC_Availability::get_slots($service, $staff, $date, $duration);
    return rest_ensure_response(['date'=>$date,'slots'=>$slots]);
  }

  public static function book($req){
    $payload = $req->get_json_params();
    $result = VC_Booking::create($payload);
    if (is_wp_error($result)) return $result;
    return rest_ensure_response($result);
  }

  public static function cancel($req){
    $payload = $req->get_json_params();
    $ref = $payload['booking_id'] ?? 0;
    $email = $payload['email'] ?? '';
    $res = VC_Booking::cancel($ref, $email);
    if (is_wp_error($res)) return $res;
    return rest_ensure_response($res);
  }

  public static function my_bookings($req){
    global $wpdb; $prefix = $wpdb->prefix;
    $uid = get_current_user_id();
    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT id,status,start,end,service_id,staff_id FROM {$prefix}vc_bookings WHERE wp_user_id=%d ORDER BY start DESC LIMIT 50
    ", $uid), ARRAY_A);
    return rest_ensure_response($rows);
  }

  public static function admin_bookings($req){
    global $wpdb; $prefix = $wpdb->prefix;
    $date = $req->get_param('date') ?: current_time('Y-m-d');
    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT id,status,start,end,service_id,staff_id,first_name,last_name,email FROM {$prefix}vc_bookings b
      LEFT JOIN {$prefix}vc_customers c ON c.id=b.customer_id
      WHERE DATE(start)=%s
      ORDER BY start ASC
    ", $date), ARRAY_A);
    return rest_ensure_response($rows);
  }

  public static function get_schedules($req){
    global $wpdb; $prefix = $wpdb->prefix;
    $staff = absint($req->get_param('staff'));
    $rows = $wpdb->get_results($wpdb->prepare("
      SELECT id,weekday,start,end,breaks FROM {$prefix}vc_schedules WHERE staff_id=%d ORDER BY weekday ASC
    ", $staff), ARRAY_A);
    $exc = $wpdb->get_results($wpdb->prepare("
      SELECT id,start,end,reason FROM {$prefix}vc_schedule_exceptions WHERE staff_id=%d ORDER BY start ASC LIMIT 100
    ", $staff), ARRAY_A);
    return rest_ensure_response(['ranges'=>$rows, 'exceptions'=>$exc]);
  }

  public static function save_schedule($req){
    global $wpdb; $prefix = $wpdb->prefix;
    $payload = $req->get_json_params();
    $staff = absint($payload['staff']);
    $ranges = $payload['ranges'] ?? [];
    $wpdb->delete("{$prefix}vc_schedules", ['staff_id'=>$staff]);
    foreach ($ranges as $r){
      $weekday = absint($r['weekday']);
      $start = sanitize_text_field($r['start']);
      $end = sanitize_text_field($r['end']);
      $breaks = isset($r['breaks']) ? wp_json_encode($r['breaks']) : null;
      $wpdb->insert("{$prefix}vc_schedules", ['staff_id'=>$staff,'weekday'=>$weekday,'start'=>$start,'end'=>$end,'breaks'=>$breaks]);
    }
    return rest_ensure_response(['ok'=>true]);
  }

  public static function save_settings($req){
    $payload = $req->get_json_params();
    foreach (['salon_name','location','from_email','font','radius','bg','surface','text','accent','muted','border'] as $key){
      if (isset($payload[$key])) VC_Settings::set($key, sanitize_text_field($payload[$key]));
    }
    return rest_ensure_response(['ok'=>true]);
  }
}
