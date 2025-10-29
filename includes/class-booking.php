<?php
if (!defined('ABSPATH')) exit;

class VC_Booking {
  public static function create($payload){
    global $wpdb; $prefix = $wpdb->prefix;
    $email = sanitize_email($payload['email'] ?? '');
    $phone = sanitize_text_field($payload['phone'] ?? '');
    $first = sanitize_text_field($payload['first_name'] ?? '');
    $last  = sanitize_text_field($payload['last_name'] ?? '');
    $service_id = absint($payload['service_id'] ?? 0);
    $staff_id   = absint($payload['staff_id'] ?? 0);
    $date = sanitize_text_field($payload['date'] ?? '');
    $time = sanitize_text_field($payload['time'] ?? '');
    $duration = absint($payload['duration_minutes'] ?? 30);
    $notes = sanitize_textarea_field($payload['notes'] ?? '');

    if (!$email || !$first || !$last || !$service_id || !$staff_id || !$date || !$time){
      return new WP_Error('bad_request', __('Missing required fields','valcode-appoint'), ['status'=>400]);
    }

    $start_dt = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
    $end_dt   = date('Y-m-d H:i:s', strtotime($start_dt . " +{$duration} minutes"));
    $now = current_time('mysql');

    // availability re-check
    $slots = VC_Availability::get_slots($service_id, $staff_id, $date, $duration);
    if (!in_array(date('H:i', strtotime($start_dt)), $slots, true)){
      return new WP_Error('conflict', __('Slot not available','valcode-appoint'), ['status'=>409]);
    }

    // upsert customer by email
    $customer_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}vc_customers WHERE email=%s", $email));
    if (!$customer_id){
      $wpdb->insert("{$prefix}vc_customers", [
        'wp_user_id' => get_current_user_id() ?: null,
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => $email,
        'phone'      => $phone,
        'created_at' => $now, 'updated_at' => $now
      ]);
      $customer_id = $wpdb->insert_id;
    } else {
      $wpdb->update("{$prefix}vc_customers", [
        'first_name' => $first,'last_name'=>$last,'phone'=>$phone,'updated_at'=>$now
      ], ['id'=>$customer_id]);
    }

    // create booking header + item
    $wpdb->insert("{$prefix}vc_bookings", [
      'status' => 'confirmed', 'customer_id'=>$customer_id, 'wp_user_id'=> (get_current_user_id() ?: null),
      'email'=>$email, 'phone'=>$phone, 'notes'=>$notes,
      'subtotal'=>0,'discount'=>0,'fee'=>0,'total'=>0,
      'start'=>$start_dt,'end'=>$end_dt,'service_id'=>$service_id,'staff_id'=>$staff_id,
      'created_at'=>$now,'updated_at'=>$now
    ]);
    $booking_id = $wpdb->insert_id;

    $wpdb->insert("{$prefix}vc_booking_items", [
      'booking_id'=>$booking_id,'service_id'=>$service_id,'staff_id'=>$staff_id,'start'=>$start_dt,'end'=>$end_dt,'price'=>0
    ]);

    // send emails
    $salon = VC_Settings::get('salon_name','Ihr Salon');
    $location = VC_Settings::get('location','');
    $subject = sprintf(__('%s: Buchung bestätigt','valcode-appoint'), $salon);
    $body = sprintf("Hallo %s %s,\n\nIhre Buchung ist bestätigt.\nDatum: %s %s\nService-ID: %d\nMitarbeiter-ID: %d\n\nBis bald!\n%s",
      $first, $last, $date, date('H:i', strtotime($start_dt)), $service_id, $staff_id, $salon);
    $ics = vc_appoint_make_ics($salon . ' Termin', 'Buchung bestätigt', $location, $start_dt, $end_dt);
    vc_appoint_send_mail_with_ics($email, $subject, $body, $ics);

    return [
      'booking_id'=>$booking_id,'status'=>'confirmed','start'=>$start_dt,'end'=>$end_dt
    ];
  }

  public static function cancel($ref, $email){
    global $wpdb; $prefix = $wpdb->prefix;
    $id = absint($ref);
    $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}vc_bookings WHERE id=%d AND email=%s", $id, $email));
    if (!$exists) return new WP_Error('not_found', __('Booking not found','valcode-appoint'), ['status'=>404]);
    $wpdb->update("{$prefix}vc_bookings", ['status'=>'cancelled'], ['id'=>$id]);
    return ['ok'=>true];
  }

  public static function process_reminders(){
    global $wpdb; $prefix = $wpdb->prefix;
    $now = current_time('timestamp');
    $targets = [
      24*3600 => 'Reminder 24h',
      2*3600 => 'Reminder 2h'
    ];
    foreach ($targets as $delta=>$label){
      $winStart = date('Y-m-d H:i:s', $now + $delta - 1800);
      $winEnd   = date('Y-m-d H:i:s', $now + $delta + 1800);
      $rows = $wpdb->get_results($wpdb->prepare("
        SELECT id,email,start,end FROM {$prefix}vc_bookings
        WHERE status='confirmed' AND start BETWEEN %s AND %s
      ", $winStart, $winEnd));
      foreach ($rows as $r){
        $salon = VC_Settings::get('salon_name','Ihr Salon');
        $subject = sprintf(__('%s: %s','valcode-appoint'), $salon, $label);
        $body = sprintf("Erinnerung: Ihr Termin am %s um %s.\nBis bald!\n%s",
          date('d.m.Y', strtotime($r->start)),
          date('H:i', strtotime($r->start)),
          $salon);
        @wp_mail($r->email, $subject, $body);
      }
    }
  }
}
