<?php
/**
 * Plugin Name: Valcode Appoint
 * Description: Coiffeur-Booking-Plugin mit Kalender, E-Mail-ICS, Schedules & Branding (V1 ohne Payment).
 * Version: 0.2.0
 * Author: Valcode
 * Text Domain: valcode-appoint
 */
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-activator.php';
require_once __DIR__ . '/includes/class-deactivator.php';
require_once __DIR__ . '/includes/class-cpt.php';
require_once __DIR__ . '/includes/class-assets.php';
require_once __DIR__ . '/includes/class-rest.php';
require_once __DIR__ . '/includes/class-shortcode.php';
require_once __DIR__ . '/includes/class-settings.php';
require_once __DIR__ . '/includes/class-availability.php';
require_once __DIR__ . '/includes/class-booking.php';

register_activation_hook(__FILE__, ['VC_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['VC_Deactivator', 'deactivate']);

add_action('init', ['VC_CPT', 'register']);
add_action('init', ['VC_Shortcode', 'register']);
add_action('rest_api_init', ['VC_REST', 'register_routes']);
add_action('wp_enqueue_scripts', ['VC_Assets', 'enqueue_public']);
add_action('admin_enqueue_scripts', ['VC_Assets', 'enqueue_admin']);
add_action('admin_menu', ['VC_Settings', 'admin_menu']);

// Branding variables as inline style
add_action('wp_head', function(){
  $vars = VC_Settings::branding_vars();
  if (!$vars) return;
  echo '<style id="vc-appoint-branding">:root{' . esc_html($vars) . '}</style>';
});

// Cron for reminders (hourly)
add_action('vc_appoint_cron_hourly', ['VC_Booking', 'process_reminders']);
