<?php
if (!defined('ABSPATH')) exit;

class VC_Activator {
  public static function activate(){
    global $wpdb;
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    $sql1 = "CREATE TABLE {$prefix}vc_bookings (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      customer_id BIGINT UNSIGNED NULL,
      wp_user_id BIGINT UNSIGNED NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(40) NULL,
      notes TEXT NULL,
      subtotal INT UNSIGNED NOT NULL DEFAULT 0,
      discount INT UNSIGNED NOT NULL DEFAULT 0,
      fee INT UNSIGNED NOT NULL DEFAULT 0,
      total INT UNSIGNED NOT NULL DEFAULT 0,
      start DATETIME NULL,
      end DATETIME NULL,
      service_id BIGINT UNSIGNED NULL,
      staff_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL
    ) $charset_collate;";

    $sql2 = "CREATE TABLE {$prefix}vc_booking_items (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      booking_id BIGINT UNSIGNED NOT NULL,
      service_id BIGINT UNSIGNED NOT NULL,
      staff_id BIGINT UNSIGNED NULL,
      start DATETIME NOT NULL,
      end DATETIME NOT NULL,
      price INT UNSIGNED NOT NULL DEFAULT 0,
      INDEX (booking_id)
    ) $charset_collate;";

    $sql3 = "CREATE TABLE {$prefix}vc_schedules (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      staff_id BIGINT UNSIGNED NOT NULL,
      weekday TINYINT UNSIGNED NOT NULL,
      start TIME NOT NULL,
      end TIME NOT NULL,
      breaks TEXT NULL
    ) $charset_collate;";

    $sql4 = "CREATE TABLE {$prefix}vc_schedule_exceptions (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      staff_id BIGINT UNSIGNED NOT NULL,
      start DATETIME NOT NULL,
      end DATETIME NOT NULL,
      reason VARCHAR(190) NULL
    ) $charset_collate;";

    $sql5 = "CREATE TABLE {$prefix}vc_customers (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      wp_user_id BIGINT UNSIGNED NULL,
      first_name VARCHAR(120) NOT NULL,
      last_name VARCHAR(120) NOT NULL,
      email VARCHAR(190) NOT NULL,
      phone VARCHAR(40) NULL,
      created_at DATETIME NOT NULL,
      updated_at DATETIME NOT NULL
    ) $charset_collate;";

    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);

    if (!wp_next_scheduled('vc_appoint_cron_hourly')) {
      wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'vc_appoint_cron_hourly');
    }
  }
}
