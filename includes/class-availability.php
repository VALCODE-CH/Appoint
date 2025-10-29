<?php
if (!defined('ABSPATH')) exit;

class VC_Availability {
  // Return slots for date respecting schedules, exceptions, and existing bookings
  public static function get_slots($service_id, $staff_id, $date, $duration = 30, $step = 15){
    global $wpdb; $prefix = $wpdb->prefix;
    $weekday = (int) date('w', strtotime($date)); // 0..6

    // Working ranges
    $ranges = $wpdb->get_results($wpdb->prepare("
      SELECT start, end, breaks FROM {$prefix}vc_schedules WHERE staff_id=%d AND weekday=%d
    ", $staff_id, $weekday), ARRAY_A);
    if (!$ranges) return [];

    // Apply exceptions (time ranges to block)
    $exc = $wpdb->get_results($wpdb->prepare("
      SELECT start, end FROM {$prefix}vc_schedule_exceptions WHERE staff_id=%d AND DATE(start)=%s
    ", $staff_id, $date), ARRAY_A);

    // Busy from existing bookings
    $busy = $wpdb->get_results($wpdb->prepare("
      SELECT start, end FROM {$prefix}vc_booking_items WHERE staff_id=%d AND DATE(start)=%s
    ", $staff_id, $date), ARRAY_A);

    $slots = [];
    $day_start_ts = strtotime($date . ' 00:00:00');
    $now_ts = current_time('timestamp');

    foreach ($ranges as $r){
      $start_ts = strtotime($date . ' ' . $r['start']);
      $end_ts   = strtotime($date . ' ' . $r['end']);

      for ($t=$start_ts; $t + $duration*60 <= $end_ts; $t += $step*60){
        $slot_start = $t; $slot_end = $t + $duration*60;
        if ($slot_start <= $now_ts) continue;

        // breaks
        $has_break_conflict = false;
        if (!empty($r['breaks'])){
          $br = @json_decode($r['breaks'], true);
          if (is_array($br)){
            foreach ($br as $b){
              $b_start = strtotime($date . ' ' . $b['start']);
              $b_end   = strtotime($date . ' ' . $b['end']);
              if (self::overlap($slot_start, $slot_end, $b_start, $b_end)) { $has_break_conflict = true; break; }
            }
          }
        }
        if ($has_break_conflict) continue;

        // exceptions
        $has_exc = false;
        foreach ($exc as $e){
          if (self::overlap($slot_start, $slot_end, strtotime($e['start']), strtotime($e['end']))) { $has_exc = true; break; }
        }
        if ($has_exc) continue;

        // busy bookings
        $has_busy = false;
        foreach ($busy as $b){
          if (self::overlap($slot_start, $slot_end, strtotime($b['start']), strtotime($b['end']))) { $has_busy = true; break; }
        }
        if ($has_busy) continue;

        $slots[] = date('H:i', $slot_start);
      }
    }
    return array_values(array_unique($slots));
  }

  private static function overlap($s1,$e1,$s2,$e2){
    return ($s1 < $e2) && ($s2 < $e1);
  }
}
