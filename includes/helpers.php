<?php
if (!defined('ABSPATH')) exit;

function vc_appoint_now() { return current_time('mysql'); }
function vc_appoint_ts() { return current_time('timestamp'); }
function vc_appoint_prefix() { global $wpdb; return $wpdb->prefix; }

function vc_appoint_make_ics($summary, $description, $location, $start_dt, $end_dt){
  $uid = uniqid('vc-appoint-');
  $dtstart = gmdate('Ymd\THis\Z', strtotime(get_gmt_from_date($start_dt)));
  $dtend   = gmdate('Ymd\THis\Z', strtotime(get_gmt_from_date($end_dt)));
  $lines = [
    'BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Valcode//Appoint//DE',
    'BEGIN:VEVENT',
    'UID:' . $uid,
    'DTSTAMP:' . gmdate('Ymd\THis\Z'),
    'DTSTART:' . $dtstart,
    'DTEND:' . $dtend,
    'SUMMARY:' . wp_kses_normalize_entities($summary),
    'DESCRIPTION:' . wp_kses_normalize_entities($description),
    'LOCATION:' . wp_kses_normalize_entities($location),
    'END:VEVENT','END:VCALENDAR'
  ];
  return implode("\r\n", $lines);
}

function vc_appoint_send_mail_with_ics($to, $subject, $body_text, $ics_content){
  $headers = ['Content-Type: text/plain; charset=UTF-8'];
  $attachments = [];
  if ($ics_content){
    $tmp = wp_tempnam('booking.ics');
    file_put_contents($tmp, $ics_content);
    $attachments[] = $tmp;
  }
  return wp_mail($to, $subject, $body_text, $headers, $attachments);
}
