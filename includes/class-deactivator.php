<?php
if (!defined('ABSPATH')) exit;

class VC_Deactivator {
  public static function deactivate(){
    $ts = wp_next_scheduled('vc_appoint_cron_hourly');
    if ($ts) wp_unschedule_event($ts, 'vc_appoint_cron_hourly');
  }
}
