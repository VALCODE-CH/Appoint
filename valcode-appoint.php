<?php
/**
 * Plugin Name:       Valcode Appoint
 * Description:       Booking plugin with calendar widget, advance booking settings, and beautiful UI
 * Version:           0.6.0
 * Author:            Valcode
 * License:           GPLv2 or later
 * Text Domain:       valcode-appoint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class Valcode_Appoint {
    private static $instance = null;
    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    public $version = '0.6.0';
    public $tables = [];

    private function __construct() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $this->tables = [
            'services'     => "{$prefix}valcode_services",
            'staff'        => "{$prefix}valcode_staff",
            'appointments' => "{$prefix}valcode_appointments",
            'availability' => "{$prefix}valcode_availability",
            'blockers'     => "{$prefix}valcode_blockers",
            'customers'    => "{$prefix}valcode_customers",
        ];

        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        add_action( 'admin_menu', [ $this, 'admin_menu' ] );

        add_action( 'admin_post_valcode_save_service', [ $this, 'handle_save_service' ] );
        add_action( 'admin_post_valcode_delete_service', [ $this, 'handle_delete_service' ] );

        add_action( 'admin_post_valcode_save_staff', [ $this, 'handle_save_staff' ] );
        add_action( 'admin_post_valcode_delete_staff', [ $this, 'handle_delete_staff' ] );

        add_action( 'admin_post_valcode_save_appointment', [ $this, 'handle_save_appointment' ] );
        add_action( 'admin_post_valcode_delete_appointment', [ $this, 'handle_delete_appointment' ] );

        add_action( 'admin_post_valcode_save_customer', [ $this, 'handle_save_customer' ] );
        add_action( 'admin_post_valcode_delete_customer', [ $this, 'handle_delete_customer' ] );
        add_action( 'admin_post_valcode_import_customers', [ $this, 'handle_import_customers' ] );

        add_action( 'admin_post_valcode_save_design', [ $this, 'handle_save_design' ] );
        add_action( 'admin_post_valcode_save_settings', [ $this, 'handle_save_settings' ] );

        add_action( 'admin_post_valcode_save_availability', [ $this, 'handle_save_availability' ] );
        add_action( 'admin_post_valcode_delete_availability', [ $this, 'handle_delete_availability' ] );
        add_action( 'admin_post_valcode_save_blocker', [ $this, 'handle_save_blocker' ] );
        add_action( 'admin_post_valcode_delete_blocker', [ $this, 'handle_delete_blocker' ] );

        add_shortcode( 'valcode_appoint', [ $this, 'shortcode_form' ] );
        add_shortcode( 'valcode_password_reset', [ $this, 'shortcode_password_reset' ] );

        // AJAX
        add_action( 'wp_ajax_valcode_get_workers', [ $this, 'ajax_get_workers' ] );
        add_action( 'wp_ajax_nopriv_valcode_get_workers', [ $this, 'ajax_get_workers' ] );

        add_action( 'wp_ajax_valcode_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_valcode_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_valcode_create_appointment', [ $this, 'ajax_create_appointment' ] );
        add_action( 'wp_ajax_nopriv_valcode_create_appointment', [ $this, 'ajax_create_appointment' ] );
        add_action( 'wp_ajax_valcode_get_events', [ $this, 'ajax_get_events' ] );

        add_action( 'wp_ajax_valcode_customer_login', [ $this, 'ajax_customer_login' ] );
        add_action( 'wp_ajax_nopriv_valcode_customer_login', [ $this, 'ajax_customer_login' ] );
        add_action( 'wp_ajax_valcode_customer_register', [ $this, 'ajax_customer_register' ] );
        add_action( 'wp_ajax_nopriv_valcode_customer_register', [ $this, 'ajax_customer_register' ] );
        add_action( 'wp_ajax_valcode_customer_logout', [ $this, 'ajax_customer_logout' ] );
        add_action( 'wp_ajax_nopriv_valcode_customer_logout', [ $this, 'ajax_customer_logout' ] );
        add_action( 'wp_ajax_valcode_customer_check', [ $this, 'ajax_customer_check' ] );
        add_action( 'wp_ajax_nopriv_valcode_customer_check', [ $this, 'ajax_customer_check' ] );
        add_action( 'wp_ajax_valcode_customer_reset_request', [ $this, 'ajax_customer_reset_request' ] );
        add_action( 'wp_ajax_nopriv_valcode_customer_reset_request', [ $this, 'ajax_customer_reset_request' ] );
        add_action( 'wp_ajax_valcode_customer_reset_password', [ $this, 'ajax_customer_reset_password' ] );
        add_action( 'wp_ajax_nopriv_valcode_customer_reset_password', [ $this, 'ajax_customer_reset_password' ] );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public' ] );

        // SMTP Configuration für E-Mail-Versand
        add_action( 'phpmailer_init', [ $this, 'configure_smtp' ] );
    }

    /**
     * Konfiguriert SMTP für den E-Mail-Versand
     * SMTP-Einstellungen werden aus den Plugin-Einstellungen geladen
     */
    public function configure_smtp($phpmailer) {
        // SMTP-Einstellungen aus Plugin-Optionen laden
        $settings = get_option('valcode_appoint_settings', []);

        $smtp_host = $settings['smtp_host'] ?? '';
        $smtp_port = $settings['smtp_port'] ?? 587;
        $smtp_secure = $settings['smtp_secure'] ?? '';
        $smtp_auth = !empty($settings['smtp_auth']);
        $smtp_user = $settings['smtp_user'] ?? '';
        $smtp_pass = $settings['smtp_pass'] ?? '';
        $smtp_from_name = $settings['smtp_from_name'] ?? wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        // Nur konfigurieren wenn SMTP-Host gesetzt ist
        if (!empty($smtp_host)) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_host;
            $phpmailer->Port = $smtp_port;

            if (!empty($smtp_secure)) {
                $phpmailer->SMTPSecure = $smtp_secure;
            }

            if ($smtp_auth && !empty($smtp_user)) {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $smtp_user;
                $phpmailer->Password = $smtp_pass;
            }

            // Verwende SMTP-User als From-Adresse
            if (!empty($smtp_user)) {
                $phpmailer->From = $smtp_user;
                $phpmailer->FromName = $smtp_from_name;
            }

            // Debug bei WP_DEBUG
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $phpmailer->SMTPDebug = 2;
                $phpmailer->Debugoutput = 'error_log';
            }
        }
    }

    public function activate() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql_services = "CREATE TABLE {$this->tables['services']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            duration_minutes INT UNSIGNED NOT NULL DEFAULT 30,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY active (active)
        ) $charset_collate;";

        $sql_staff = "CREATE TABLE {$this->tables['staff']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            display_name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NULL,
            phone VARCHAR(64) NULL,
            services JSON NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY active (active)
        ) $charset_collate;";

        $sql_appts = "CREATE TABLE {$this->tables['appointments']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_name VARCHAR(191) NOT NULL,
            customer_email VARCHAR(191) NULL,
            service_id BIGINT UNSIGNED NOT NULL,
            staff_id BIGINT UNSIGNED NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NULL,
            notes TEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY service_id (service_id),
            KEY staff_id (staff_id),
            KEY status (status),
            KEY starts_at (starts_at)
        ) $charset_collate;";

        $sql_av = "CREATE TABLE {$this->tables['availability']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            staff_id BIGINT UNSIGNED NOT NULL,
            weekday TINYINT UNSIGNED NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY weekday (weekday)
        ) $charset_collate;";

        $sql_block = "CREATE TABLE {$this->tables['blockers']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            staff_id BIGINT UNSIGNED NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            note VARCHAR(191) NULL,
            PRIMARY KEY (id),
            KEY staff_id (staff_id),
            KEY starts_at (starts_at)
        ) $charset_collate;";

        $sql_customers = "CREATE TABLE {$this->tables['customers']} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            first_name VARCHAR(191) NOT NULL,
            last_name VARCHAR(191) NOT NULL,
            email VARCHAR(191) NOT NULL,
            phone VARCHAR(64) NULL,
            password_hash VARCHAR(255) NULL,
            notes TEXT NULL,
            is_guest BOOLEAN DEFAULT 0,
            reset_token VARCHAR(64) NULL,
            reset_expires DATETIME NULL,
            last_login DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            KEY user_id (user_id),
            KEY reset_token (reset_token)
        ) $charset_collate;";

        dbDelta( $sql_services );
        dbDelta( $sql_staff );
        dbDelta( $sql_appts );
        dbDelta( $sql_av );
        dbDelta( $sql_block );
        dbDelta( $sql_customers );

        // Default design options
        if ( false === get_option('valcode_appoint_design') ) {
            add_option('valcode_appoint_design', [
                'primary_color' => '#0f172a',
                'accent_color'  => '#6366f1',
                'accent_gradient_start' => '#667eea',
                'accent_gradient_end' => '#764ba2',
                'radius'        => '14px',
                'font_family'   => 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
            ]);
        }

        // Settings options
        if ( false === get_option('valcode_appoint_settings') ) {
            add_option('valcode_appoint_settings', [
                'min_advance_days' => 0,
            ]);
        }
    }

    public function admin_menu() {
        add_menu_page(
            __('Valcode Appoint','valcode-appoint'),
            __('Valcode Appoint','valcode-appoint'),
            'manage_options',
            'valcode-appoint',
            [ $this, 'render_dashboard' ],
            'dashicons-calendar-alt',
            26
        );

        add_submenu_page('valcode-appoint', __('Services','valcode-appoint'), __('Services','valcode-appoint'), 'manage_options', 'valcode-appoint-services', [ $this, 'render_services' ]);
        add_submenu_page('valcode-appoint', __('Mitarbeiter','valcode-appoint'), __('Mitarbeiter','valcode-appoint'), 'manage_options', 'valcode-appoint-staff', [ $this, 'render_staff' ]);
        add_submenu_page('valcode-appoint', __('Kunden','valcode-appoint'), __('Kunden','valcode-appoint'), 'manage_options', 'valcode-appoint-customers', [ $this, 'render_customers' ]);
        add_submenu_page('valcode-appoint', __('Termine','valcode-appoint'), __('Termine','valcode-appoint'), 'manage_options', 'valcode-appoint-appointments', [ $this, 'render_appointments' ]);
        add_submenu_page('valcode-appoint', __('Kalender','valcode-appoint'), __('Kalender','valcode-appoint'), 'manage_options', 'valcode-appoint-calendar', [ $this, 'render_calendar' ]);
        add_submenu_page('valcode-appoint', __('Verfügbarkeit','valcode-appoint'), __('Verfügbarkeit','valcode-appoint'), 'manage_options', 'valcode-appoint-availability', [ $this, 'render_availability' ]);
        add_submenu_page('valcode-appoint', __('Design','valcode-appoint'), __('Design','valcode-appoint'), 'manage_options', 'valcode-appoint-design', [ $this, 'render_design' ]);
        add_submenu_page('valcode-appoint', __('Einstellungen','valcode-appoint'), __('Einstellungen','valcode-appoint'), 'manage_options', 'valcode-appoint-settings', [ $this, 'render_settings' ]);
    }

    public function enqueue_admin($hook) {
        if ( strpos($hook, 'valcode-appoint') === false ) return;
        wp_enqueue_style( 'valcode-appoint-admin', plugins_url( 'assets/css/admin.css', __FILE__ ), [], $this->version );
        wp_enqueue_script( 'valcode-appoint-admin', plugins_url( 'assets/js/admin.js', __FILE__ ), ['jquery'], $this->version, true );
        wp_localize_script( 'valcode-appoint-admin', 'ValcodeAppointAdmin', [
            'ajax'  => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('valcode_appoint_nonce')
        ]);

        // Only on calendar page: FullCalendar CDN
        if ( isset($_GET['page']) && $_GET['page'] === 'valcode-appoint-calendar' ) {
            wp_enqueue_style( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css', [], '6.1.15' );
            wp_enqueue_script( 'fullcalendar', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', [], '6.1.15', true );
        }
    }

    public function enqueue_public() {
        $design = get_option('valcode_appoint_design', []);
        $primary = isset($design['primary_color']) ? $design['primary_color'] : '#0f172a';
        $accent  = isset($design['accent_color'])  ? $design['accent_color']  : '#6366f1';
        $gradient_start = isset($design['accent_gradient_start']) ? $design['accent_gradient_start'] : '#667eea';
        $gradient_end = isset($design['accent_gradient_end']) ? $design['accent_gradient_end'] : '#764ba2';
        $radius  = isset($design['radius'])        ? $design['radius']        : '14px';
        $font    = isset($design['font_family'])   ? $design['font_family']   : 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif';

        wp_register_style( 'valcode-appoint-public', plugins_url( 'assets/css/public.css', __FILE__ ), [], $this->version );
        wp_add_inline_style( 'valcode-appoint-public', ":root{--va-primary: {$primary}; --va-accent: {$accent}; --va-gradient-start: {$gradient_start}; --va-gradient-end: {$gradient_end}; --va-radius: {$radius}; --va-font: {$font};}" );
        wp_enqueue_style( 'valcode-appoint-public' );

        $settings = get_option('valcode_appoint_settings', []);
        $min_advance = isset($settings['min_advance_days']) ? (int)$settings['min_advance_days'] : 0;

        wp_register_script( 'valcode-appoint', plugins_url( 'assets/js/appoint.js', __FILE__ ), ['jquery'], $this->version, true );
        wp_localize_script( 'valcode-appoint', 'ValcodeAppoint', [
            'colors' => [
                'accent' => $accent,
                'gradientStart' => $gradient_start,
                'gradientEnd' => $gradient_end
            ],
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('valcode_appoint_nonce'),
            'minAdvanceDays' => $min_advance
        ]);
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>Valcode Appoint</h1><p>Verwalte Services, Mitarbeiter, Termine, Kalender, Verfügbarkeit & Design.</p><p>Frontend: <code>[valcode_appoint]</code></p></div>';
    }

    public function render_services() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        $table = $this->tables['services'];

        $services = $wpdb->get_results( "SELECT * FROM $table ORDER BY active DESC, name ASC" );
        $edit = null;
        if ( isset($_GET['edit']) ) {
            $edit_id = absint($_GET['edit']);
            $edit = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id) );
        } ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Services</h1>
            <hr class="wp-header-end"/>
            <div class="va-grid">
                <div class="va-card">
                    <h2><?php echo $edit ? 'Service bearbeiten' : 'Neuen Service anlegen'; ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                        <?php wp_nonce_field( 'valcode_save_service', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_save_service"/>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit->id; ?>"/><?php endif; ?>
                        <div class="va-field"><label for="name">Name</label><input name="name" id="name" type="text" required value="<?php echo esc_attr($edit->name ?? ''); ?>"/></div>
                        <div class="va-field two">
                            <div><label for="duration_minutes">Dauer (Min)</label><input name="duration_minutes" id="duration_minutes" type="number" min="5" step="5" required value="<?php echo esc_attr($edit->duration_minutes ?? 30); ?>"/></div>
                            <div><label for="price">Preis (CHF)</label><input name="price" id="price" type="number" min="0" step="0.05" required value="<?php echo esc_attr($edit->price ?? '0.00'); ?>"/></div>
                        </div>
                        <div class="va-field"><label class="va-check"><input type="checkbox" name="active" value="1" <?php checked( $edit ? (int)$edit->active : 1, 1 ); ?>/> Aktiv</label></div>
                        <div class="va-actions"><button class="button button-primary" type="submit"><?php echo $edit ? 'Speichern' : 'Anlegen'; ?></button></div>
                    </form>
                </div>
                <div class="va-card">
                    <h2>Bestehende Services</h2>
                    <table class="widefat fixed striped">
                        <thead><tr><th>ID</th><th>Name</th><th>Dauer</th><th>Preis</th><th>Status</th><th>Aktionen</th></tr></thead>
                        <tbody>
                        <?php if ( $services ) : foreach ( $services as $s ) : ?>
                            <tr>
                                <td><?php echo (int)$s->id; ?></td>
                                <td><?php echo esc_html($s->name); ?></td>
                                <td><?php echo (int)$s->duration_minutes; ?> min</td>
                                <td><?php echo esc_html( number_format( (float)$s->price, 2, '.', '' ) ); ?> CHF</td>
                                <td><?php echo $s->active ? 'aktiv' : 'inaktiv'; ?></td>
                                <td class="va-actions-inline">
                                    <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=valcode-appoint-services&edit='.(int)$s->id) ); ?>">Bearbeiten</a>
                                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Service wirklich löschen?');" style="display:inline">
                                        <?php wp_nonce_field( 'valcode_delete_service', '_va' ); ?>
                                        <input type="hidden" name="action" value="valcode_delete_service"/>
                                        <input type="hidden" name="id" value="<?php echo (int)$s->id; ?>"/>
                                        <button class="button button-small button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6">Keine Services vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><?php
    }

    public function handle_save_service() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_service', '_va' );
        global $wpdb; $table = $this->tables['services'];
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $data = [
            'name' => sanitize_text_field( $_POST['name'] ?? '' ),
            'duration_minutes' => max(5, (int)($_POST['duration_minutes'] ?? 30)),
            'price' => floatval( $_POST['price'] ?? 0 ),
            'active' => isset($_POST['active']) ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];
        if ( $id ) $wpdb->update( $table, $data, [ 'id' => $id ] );
        else { $data['created_at'] = current_time('mysql'); $wpdb->insert( $table, $data ); }
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-services') ); exit;
    }
    
    public function handle_delete_service() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_delete_service', '_va' );
        global $wpdb; $wpdb->delete( $this->tables['services'], [ 'id' => absint($_POST['id'] ?? 0) ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-services') ); exit;
    }

    public function render_staff() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        $table = $this->tables['staff'];
        $services_table = $this->tables['services'];
        $services = $wpdb->get_results( "SELECT id, name FROM $services_table WHERE active=1 ORDER BY name" );
        $staff = $wpdb->get_results( "SELECT * FROM $table ORDER BY active DESC, display_name ASC" );
        $edit = null;
        if ( isset($_GET['edit']) ) {
            $edit_id = absint($_GET['edit']);
            $edit = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id) );
        }
        $selected_services = $edit && $edit->services ? json_decode($edit->services, true) : [];
        if ( ! is_array($selected_services) ) $selected_services = [];
        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Mitarbeiter</h1>
            <hr class="wp-header-end"/>
            <div class="va-grid">
                <div class="va-card">
                    <h2><?php echo $edit ? 'Mitarbeiter bearbeiten' : 'Neuen Mitarbeiter anlegen'; ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                        <?php wp_nonce_field( 'valcode_save_staff', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_save_staff"/>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit->id; ?>"/><?php endif; ?>
                        <div class="va-field"><label for="display_name">Name</label><input name="display_name" id="display_name" type="text" required value="<?php echo esc_attr($edit->display_name ?? ''); ?>"/></div>
                        <div class="va-field two">
                            <div><label for="email">E-Mail</label><input name="email" id="email" type="email" value="<?php echo esc_attr($edit->email ?? ''); ?>"/></div>
                            <div><label for="phone">Telefon</label><input name="phone" id="phone" type="text" value="<?php echo esc_attr($edit->phone ?? ''); ?>"/></div>
                        </div>
                        <div class="va-field">
                            <label for="services">Bietet Services an</label>
                            <select name="services[]" id="services" multiple size="6" style="min-width:260px;">
                                <?php foreach ($services as $srv): ?>
                                    <option value="<?php echo (int)$srv->id; ?>" <?php selected( in_array( (int)$srv->id, $selected_services, true ) ); ?>>
                                        <?php echo esc_html($srv->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Mehrfachauswahl mit Strg/Cmd.</p>
                        </div>
                        <div class="va-field"><label class="va-check"><input type="checkbox" name="active" value="1" <?php checked( $edit ? (int)$edit->active : 1, 1 ); ?>/> Aktiv</label></div>
                        <div class="va-actions"><button class="button button-primary" type="submit"><?php echo $edit ? 'Speichern' : 'Anlegen'; ?></button></div>
                    </form>
                </div>
                <div class="va-card">
                    <h2>Bestehende Mitarbeiter</h2>
                    <table class="widefat fixed striped">
                        <thead><tr><th>ID</th><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Status</th><th>Aktionen</th></tr></thead>
                        <tbody>
                        <?php if ( $staff ) : foreach ( $staff as $st ) : ?>
                            <tr>
                                <td><?php echo (int)$st->id; ?></td>
                                <td><?php echo esc_html($st->display_name); ?></td>
                                <td><?php echo esc_html($st->email); ?></td>
                                <td><?php echo esc_html($st->phone); ?></td>
                                <td><?php echo $st->active ? 'aktiv' : 'inaktiv'; ?></td>
                                <td class="va-actions-inline">
                                    <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=valcode-appoint-staff&edit='.(int)$st->id) ); ?>">Bearbeiten</a>
                                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Mitarbeiter wirklich löschen?');" style="display:inline">
                                        <?php wp_nonce_field( 'valcode_delete_staff', '_va' ); ?>
                                        <input type="hidden" name="action" value="valcode_delete_staff"/>
                                        <input type="hidden" name="id" value="<?php echo (int)$st->id; ?>"/>
                                        <button class="button button-small button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6">Keine Mitarbeiter vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><?php
    }
    
    public function handle_save_staff() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_staff', '_va' );
        global $wpdb; $table = $this->tables['staff'];
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $services = array_map('absint', $_POST['services'] ?? []);
        $data = [
            'display_name' => sanitize_text_field( $_POST['display_name'] ?? '' ),
            'email' => sanitize_email( $_POST['email'] ?? '' ),
            'phone' => sanitize_text_field( $_POST['phone'] ?? '' ),
            'services' => wp_json_encode( array_values( array_filter($services) ) ),
            'active' => isset($_POST['active']) ? 1 : 0,
            'updated_at' => current_time('mysql')
        ];
        if ( $id ) $wpdb->update( $table, $data, [ 'id' => $id ] );
        else { $data['created_at'] = current_time('mysql'); $wpdb->insert( $table, $data ); }
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-staff') ); exit;
    }
    
    public function handle_delete_staff() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_delete_staff', '_va' );
        global $wpdb; $wpdb->delete( $this->tables['staff'], [ 'id' => absint($_POST['id'] ?? 0) ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-staff') ); exit;
    }

    public function render_customers() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        $table = $this->tables['customers'];

        $customers = $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC" );
        $edit = null;
        if ( isset($_GET['edit']) ) {
            $edit_id = absint($_GET['edit']);
            $edit = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $table WHERE id=%d", $edit_id) );
        } ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Kunden</h1>
            <hr class="wp-header-end"/>

            <?php if(isset($_GET['imported'])): ?>
                <div class="notice notice-success is-dismissible"><p>✅ <?php echo absint($_GET['imported']); ?> Kunden erfolgreich importiert!</p></div>
            <?php endif; ?>

            <div class="va-grid">
                <div class="va-card">
                    <h2><?php echo $edit ? 'Kunde bearbeiten' : 'Neuen Kunden anlegen'; ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                        <?php wp_nonce_field( 'valcode_save_customer', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_save_customer"/>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit->id; ?>"/><?php endif; ?>

                        <div class="va-field two">
                            <div><label for="first_name">Vorname *</label><input name="first_name" id="first_name" type="text" required value="<?php echo esc_attr($edit->first_name ?? ''); ?>"/></div>
                            <div><label for="last_name">Nachname *</label><input name="last_name" id="last_name" type="text" required value="<?php echo esc_attr($edit->last_name ?? ''); ?>"/></div>
                        </div>
                        <div class="va-field two">
                            <div><label for="email">E-Mail *</label><input name="email" id="email" type="email" required value="<?php echo esc_attr($edit->email ?? ''); ?>"/></div>
                            <div><label for="phone">Telefon</label><input name="phone" id="phone" type="text" value="<?php echo esc_attr($edit->phone ?? ''); ?>"/></div>
                        </div>
                        <div class="va-field">
                            <label for="notes">Notizen</label>
                            <textarea name="notes" id="notes" rows="3"><?php echo esc_textarea($edit->notes ?? ''); ?></textarea>
                        </div>
                        <div class="va-actions"><button class="button button-primary" type="submit"><?php echo $edit ? 'Speichern' : 'Anlegen'; ?></button></div>
                    </form>

                    <hr style="margin: 30px 0;"/>

                    <h2>Kunden importieren (CSV)</h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" class="va-form">
                        <?php wp_nonce_field( 'valcode_import_customers', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_import_customers"/>
                        <div class="va-field">
                            <label for="csv_file">CSV-Datei auswählen</label>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv,.txt" required/>
                            <p class="description">Format: Vorname, Nachname, E-Mail, Telefon, Notizen (Kopfzeile optional)</p>
                        </div>
                        <div class="va-actions"><button class="button" type="submit">Importieren</button></div>
                    </form>
                </div>

                <div class="va-card">
                    <h2>Kundenliste (<?php echo count($customers); ?>)</h2>
                    <table class="widefat fixed striped">
                        <thead><tr><th>ID</th><th>Name</th><th>E-Mail</th><th>Telefon</th><th>Typ</th><th>Aktionen</th></tr></thead>
                        <tbody>
                        <?php if ( $customers ) : foreach ( $customers as $c ) : ?>
                            <tr>
                                <td><?php echo (int)$c->id; ?></td>
                                <td><?php echo esc_html($c->first_name . ' ' . $c->last_name); ?></td>
                                <td><?php echo esc_html($c->email); ?></td>
                                <td><?php echo esc_html($c->phone); ?></td>
                                <td>
                                    <?php if (!empty($c->is_guest)): ?>
                                        <span style="display:inline-block;background:#fef3c7;color:#92400e;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">Gast</span>
                                    <?php else: ?>
                                        <span style="display:inline-block;background:#dbeafe;color:#1e40af;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">Registriert</span>
                                    <?php endif; ?>
                                </td>
                                <td class="va-actions-inline">
                                    <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=valcode-appoint-customers&edit='.(int)$c->id) ); ?>">Bearbeiten</a>
                                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Kunde wirklich löschen?');" style="display:inline">
                                        <?php wp_nonce_field( 'valcode_delete_customer', '_va' ); ?>
                                        <input type="hidden" name="action" value="valcode_delete_customer"/>
                                        <input type="hidden" name="id" value="<?php echo (int)$c->id; ?>"/>
                                        <button class="button button-small button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6">Noch keine Kunden vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div><?php
    }

    public function handle_save_customer() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_customer', '_va' );
        global $wpdb; $table = $this->tables['customers'];
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $data = [
            'first_name' => sanitize_text_field( $_POST['first_name'] ?? '' ),
            'last_name' => sanitize_text_field( $_POST['last_name'] ?? '' ),
            'email' => sanitize_email( $_POST['email'] ?? '' ),
            'phone' => sanitize_text_field( $_POST['phone'] ?? '' ),
            'notes' => sanitize_textarea_field( $_POST['notes'] ?? '' ),
            'updated_at' => current_time('mysql')
        ];
        if ( $id ) {
            $wpdb->update( $table, $data, [ 'id' => $id ] );
        } else {
            $data['created_at'] = current_time('mysql');
            $data['is_guest'] = 0; // Manually created customers are not guests
            $wpdb->insert( $table, $data );
        }
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-customers') ); exit;
    }

    public function handle_delete_customer() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_delete_customer', '_va' );
        global $wpdb; $wpdb->delete( $this->tables['customers'], [ 'id' => absint($_POST['id'] ?? 0) ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-customers') ); exit;
    }

    public function handle_import_customers() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_import_customers', '_va' );

        if ( empty($_FILES['csv_file']['tmp_name']) ) {
            wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-customers&error=nofile') ); exit;
        }

        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if ( ! $handle ) {
            wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-customers&error=read') ); exit;
        }

        global $wpdb;
        $table = $this->tables['customers'];
        $imported = 0;
        $first_row = true;

        while ( ($data = fgetcsv($handle)) !== FALSE ) {
            // Skip header row if it looks like a header
            if ( $first_row && count($data) >= 3 ) {
                $first_cell = strtolower(trim($data[0]));
                if ( in_array($first_cell, ['vorname', 'firstname', 'first_name', 'name']) ) {
                    $first_row = false;
                    continue;
                }
                $first_row = false;
            }

            if ( count($data) < 3 ) continue; // Need at least first name, last name, email

            $first_name = sanitize_text_field( trim($data[0]) );
            $last_name = sanitize_text_field( trim($data[1]) );
            $email = sanitize_email( trim($data[2]) );
            $phone = isset($data[3]) ? sanitize_text_field( trim($data[3]) ) : '';
            $notes = isset($data[4]) ? sanitize_textarea_field( trim($data[4]) ) : '';

            if ( ! $first_name || ! $last_name || ! $email ) continue;

            // Check if email already exists
            $exists = $wpdb->get_var( $wpdb->prepare("SELECT id FROM $table WHERE email=%s", $email) );
            if ( $exists ) continue; // Skip duplicates

            $wpdb->insert( $table, [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'notes' => $notes,
                'is_guest' => 0,
                'created_at' => current_time('mysql')
            ]);

            if ( $wpdb->insert_id ) $imported++;
        }

        fclose($handle);
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-customers&imported='.$imported) ); exit;
    }

    public function render_appointments() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;

        $services = $wpdb->get_results( "SELECT id, name FROM {$this->tables['services']} WHERE active=1 ORDER BY name" );
        $staff    = $wpdb->get_results( "SELECT id, display_name FROM {$this->tables['staff']} WHERE active=1 ORDER BY display_name" );
        $customers = $wpdb->get_results( "SELECT id, first_name, last_name, email FROM {$this->tables['customers']} ORDER BY first_name, last_name" );

        $edit = null;
        if ( isset($_GET['edit']) ) {
            $id = absint($_GET['edit']);
            $edit = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->tables['appointments']} WHERE id=%d", $id) );
        }

        $rows = $wpdb->get_results( "
            SELECT a.*, s.name AS service_name, st.display_name AS staff_name
            FROM {$this->tables['appointments']} a
            LEFT JOIN {$this->tables['services']} s ON s.id=a.service_id
            LEFT JOIN {$this->tables['staff']} st ON st.id=a.staff_id
            ORDER BY a.starts_at DESC
            LIMIT 500
        " );

        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Termine</h1>
            <hr class="wp-header-end"/>
            <div class="va-grid">
                <div class="va-card">
                    <h2><?php echo $edit ? 'Termin bearbeiten' : 'Neuen Termin anlegen'; ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                        <?php wp_nonce_field( 'valcode_save_appointment', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_save_appointment"/>
                        <?php if ($edit): ?><input type="hidden" name="id" value="<?php echo (int)$edit->id; ?>"/><?php endif; ?>

                        <div class="va-field">
                            <label for="customer_select">Kunde auswählen (optional)</label>
                            <select name="customer_select" id="customer_select" style="margin-bottom: 10px;">
                                <option value="">-- Manuell eingeben oder Kunde wählen --</option>
                                <?php foreach ($customers as $cust): ?>
                                    <option value="<?php echo (int)$cust->id; ?>"
                                            data-name="<?php echo esc_attr($cust->first_name . ' ' . $cust->last_name); ?>"
                                            data-email="<?php echo esc_attr($cust->email); ?>">
                                        <?php echo esc_html($cust->first_name . ' ' . $cust->last_name . ' (' . $cust->email . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="va-field"><label for="customer_name">Kundenname *</label><input name="customer_name" id="customer_name" type="text" required value="<?php echo esc_attr($edit->customer_name ?? ''); ?>"/></div>
                        <div class="va-field two">
                            <div><label for="customer_email">E-Mail</label><input name="customer_email" id="customer_email" type="email" value="<?php echo esc_attr($edit->customer_email ?? ''); ?>"/></div>
                            <div><label for="starts_at">Start *</label><input name="starts_at" id="starts_at" type="datetime-local" required value="<?php echo $edit ? esc_attr( date('Y-m-d\TH:i', strtotime($edit->starts_at)) ) : ''; ?>"/></div>
                        </div>
                        <div class="va-field two">
                            <div><label for="service_id">Service</label>
                                <select name="service_id" id="service_id" required>
                                    <option value="">Bitte wählen…</option>
                                    <?php foreach ($services as $s): ?>
                                        <option value="<?php echo (int)$s->id; ?>" <?php selected( $edit && (int)$edit->service_id === (int)$s->id ); ?>><?php echo esc_html($s->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label for="staff_id">Mitarbeiter</label>
                                <select name="staff_id" id="staff_id" <?php echo $edit ? '' : 'disabled'; ?>>
                                    <?php if ( $edit ) : ?>
                                        <option value="0">-</option>
                                        <?php foreach ($staff as $st): ?>
                                            <option value="<?php echo (int)$st->id; ?>" <?php selected( (int)$edit->staff_id === (int)$st->id ); ?>><?php echo esc_html($st->display_name); ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">Bitte zuerst Service wählen…</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>
                        <div class="va-field"><label for="notes">Notizen</label><textarea name="notes" id="notes" rows="3"><?php echo esc_textarea($edit->notes ?? ''); ?></textarea></div>
                        <div class="va-field">
                            <label for="status">Status</label>
                            <select name="status" id="status">
                                <?php $st = $edit->status ?? 'pending'; ?>
                                <option value="pending" <?php selected($st==='pending'); ?>>ausstehend</option>
                                <option value="confirmed" <?php selected($st==='confirmed'); ?>>bestätigt</option>
                                <option value="cancelled" <?php selected($st==='cancelled'); ?>>abgesagt</option>
                                <option value="done" <?php selected($st==='done'); ?>>abgeschlossen</option>
                            </select>
                        </div>
                        <div class="va-actions"><button class="button button-primary" type="submit"><?php echo $edit ? 'Speichern' : 'Termin speichern'; ?></button></div>
                    </form>
                </div>

                <div class="va-card">
                    <h2>Letzte Termine</h2>
                    <table class="widefat fixed striped">
                        <thead><tr><th>Start</th><th>Kunde</th><th>Service</th><th>Mitarbeiter</th><th>Status</th><th>Aktionen</th></tr></thead>
                        <tbody>
                        <?php if ( $rows ) : foreach ( $rows as $r ) : ?>
                            <tr>
                                <td><?php echo esc_html( date_i18n('d.m.Y H:i', strtotime($r->starts_at)) ); ?></td>
                                <td><?php echo esc_html($r->customer_name); ?></td>
                                <td><?php echo esc_html($r->service_name ?: ('#'.$r->service_id)); ?></td>
                                <td><?php echo esc_html($r->staff_name ?: '-'); ?></td>
                                <td><?php echo esc_html($r->status); ?></td>
                                <td class="va-actions-inline">
                                    <a class="button button-small" href="<?php echo esc_url( admin_url('admin.php?page=valcode-appoint-appointments&edit='.(int)$r->id) ); ?>">Bearbeiten</a>
                                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Termin wirklich löschen?');" style="display:inline">
                                        <?php wp_nonce_field( 'valcode_delete_appointment', '_va' ); ?>
                                        <input type="hidden" name="action" value="valcode_delete_appointment"/>
                                        <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"/>
                                        <button class="button button-small button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6">Noch keine Termine vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_save_appointment() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_appointment', '_va' );
        global $wpdb;
        $id            = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $customer_name = sanitize_text_field( $_POST['customer_name'] ?? '' );
        $customer_email= sanitize_email( $_POST['customer_email'] ?? '' );
        $service_id    = absint( $_POST['service_id'] ?? 0 );
        $staff_id      = absint( $_POST['staff_id'] ?? 0 );
        $starts_at_raw = sanitize_text_field( $_POST['starts_at'] ?? '' );
        $notes         = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $status        = sanitize_text_field( $_POST['status'] ?? 'pending' );

        if ( ! $customer_name || ! $service_id || ! $starts_at_raw ) {
            wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-appointments&err=missing') ); exit;
        }
        $ts = strtotime( $starts_at_raw );
        if ( $ts === false ) {
            wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-appointments&err=time') ); exit;
        }
        $starts_at = date('Y-m-d H:i:s', $ts);

        $data = [
            'customer_name' => $customer_name,
            'customer_email'=> $customer_email,
            'service_id'    => $service_id,
            'staff_id'      => $staff_id ?: null,
            'starts_at'     => $starts_at,
            'notes'         => $notes,
            'status'        => $status,
            'updated_at'    => current_time('mysql'),
        ];

        if ( $id ) {
            $wpdb->update( $this->tables['appointments'], $data, [ 'id' => $id ] );
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert( $this->tables['appointments'], $data );
        }

        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-appointments&saved=1') ); exit;
    }
    
    public function handle_delete_appointment() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_delete_appointment', '_va' );
        global $wpdb; $wpdb->delete( $this->tables['appointments'], [ 'id' => absint($_POST['id'] ?? 0) ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-appointments&deleted=1') ); exit;
    }

    public function render_calendar() {
        if ( ! current_user_can('manage_options') ) return; ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Kalender</h1>
            <hr class="wp-header-end"/>
            <div class="va-card">
                <div id="va-calendar"></div>
            </div>
            <p class="description">Klicke auf einen Event, um ihn in der Terminverwaltung zu bearbeiten.</p>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (!window.FullCalendar) return;
            var el = document.getElementById('va-calendar');
            var calendar = new FullCalendar.Calendar(el, {
                initialView: 'dayGridMonth',
                height: 'auto',
                locale: 'de',
                nowIndicator: true,
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                },
                events: function(fetchInfo, success, failure){
                    var url = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>?action=valcode_get_events&_wpnonce=<?php echo wp_create_nonce('valcode_appoint_nonce'); ?>&start=' + encodeURIComponent(fetchInfo.startStr) + '&end=' + encodeURIComponent(fetchInfo.endStr);
                    fetch(url, { credentials: 'same-origin' }).then(r=>r.json()).then(function(res){
                        if(res && res.success && res.data && res.data.events){ success(res.data.events); } else { success([]); }
                    }).catch(failure);
                },
                eventClick: function(info){
                    var id = info.event.id;
                    if(id){
                        window.location = '<?php echo esc_js( admin_url('admin.php?page=valcode-appoint-appointments&edit=') ); ?>' + id;
                    }
                }
            });
            calendar.render();
        });
        </script>
        <?php
    }

    public function ajax_get_events() {
        if ( ! current_user_can('manage_options') ) wp_send_json_error(['msg'=>'forbidden'], 403);
        check_ajax_referer('valcode_appoint_nonce');

        // Set Swiss timezone for calendar events
        date_default_timezone_set('Europe/Zurich');

        global $wpdb;
        $start = isset($_GET['start']) ? sanitize_text_field($_GET['start']) : '';
        $end   = isset($_GET['end']) ? sanitize_text_field($_GET['end']) : '';
        $where = '';
        if ($start && $end) {
            $where = $wpdb->prepare("WHERE starts_at BETWEEN %s AND %s", $start, $end);
        }
        $rows = $wpdb->get_results( "SELECT id, customer_name, starts_at, ends_at, status FROM {$this->tables['appointments']} $where ORDER BY starts_at" );
        $events = [];
        foreach ($rows as $r) {
            $title = $r->customer_name;
            $color = '#6366f1';
            if ($r->status === 'cancelled') $color = '#ef4444';
            elseif ($r->status === 'done') $color = '#16a34a';
            elseif ($r->status === 'pending') $color = '#f59e0b';

            // Format dates in Swiss timezone
            $start_dt = new DateTime($r->starts_at, new DateTimeZone('Europe/Zurich'));
            $end_dt = $r->ends_at ? new DateTime($r->ends_at, new DateTimeZone('Europe/Zurich')) : null;

            $events[] = [
                'id' => (string)$r->id,
                'title' => $title,
                'start' => $start_dt->format('c'),
                'end'   => $end_dt ? $end_dt->format('c') : null,
                'backgroundColor' => $color,
                'borderColor' => $color
            ];
        }
        wp_send_json_success([ 'events' => $events ]);
    }

    public function render_availability() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        $staff = $wpdb->get_results( "SELECT id, display_name FROM {$this->tables['staff']} WHERE active=1 ORDER BY display_name" );

        // Filter für Mitarbeiter
        $filter_staff_id = isset($_GET['filter_staff']) ? absint($_GET['filter_staff']) : 0;

        // Abfrage mit Filter
        $where_rules = $filter_staff_id ? $wpdb->prepare("WHERE av.staff_id = %d", $filter_staff_id) : "";
        $rules = $wpdb->get_results( "
            SELECT av.*, st.display_name FROM {$this->tables['availability']} av
            LEFT JOIN {$this->tables['staff']} st ON st.id=av.staff_id
            $where_rules
            ORDER BY st.display_name, av.weekday, av.start_time
        " );

        $where_blockers = $filter_staff_id ? $wpdb->prepare("WHERE b.staff_id = %d", $filter_staff_id) : "";
        $blockers = $wpdb->get_results( "
            SELECT b.*, st.display_name FROM {$this->tables['blockers']} b
            LEFT JOIN {$this->tables['staff']} st ON st.id=b.staff_id
            $where_blockers
            ORDER BY b.starts_at DESC
        " );
        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Verfügbarkeit</h1>
            <hr class="wp-header-end"/>

            <!-- Filter -->
            <div class="va-card" style="margin-bottom: 20px;">
                <form method="get" action="" style="display: flex; align-items: end; gap: 15px;">
                    <input type="hidden" name="page" value="valcode-appoint-availability"/>
                    <div class="va-field" style="margin: 0;">
                        <label for="filter_staff">Nach Mitarbeiter filtern</label>
                        <select id="filter_staff" name="filter_staff" onchange="this.form.submit()">
                            <option value="">Alle Mitarbeiter</option>
                            <?php foreach($staff as $st): ?>
                                <option value="<?php echo (int)$st->id; ?>" <?php selected($filter_staff_id, (int)$st->id); ?>>
                                    <?php echo esc_html($st->display_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if($filter_staff_id): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=valcode-appoint-availability')); ?>" class="button">Filter zurücksetzen</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="va-grid">
                <div class="va-card">
                    <h2>Öffnungszeiten-Regel</h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                        <?php wp_nonce_field( 'valcode_save_availability', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_save_availability"/>
                        <div class="va-field two">
                            <div><label for="av_staff">Mitarbeiter</label>
                                <select id="av_staff" name="staff_id" required>
                                    <option value="">Bitte wählen…</option>
                                    <?php foreach($staff as $st): ?>
                                        <option value="<?php echo (int)$st->id; ?>" <?php selected($filter_staff_id, (int)$st->id); ?>>
                                            <?php echo esc_html($st->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label for="weekday">Wochentag</label>
                                <select id="weekday" name="weekday" required>
                                    <option value="1">Montag</option><option value="2">Dienstag</option><option value="3">Mittwoch</option>
                                    <option value="4">Donnerstag</option><option value="5">Freitag</option><option value="6">Samstag</option><option value="0">Sonntag</option>
                                </select>
                            </div>
                        </div>
                        <div class="va-field two">
                            <div><label for="start_time">Start</label><input type="time" id="start_time" name="start_time" required/></div>
                            <div><label for="end_time">Ende</label><input type="time" id="end_time" name="end_time" required/></div>
                        </div>
                        <div class="va-field"><label class="va-check"><input type="checkbox" name="active" value="1" checked/> Aktiv</label></div>
                        <div class="va-actions"><button class="button button-primary">Regel speichern</button></div>
                    </form>
                    <h3>Bestehende Regeln</h3>
                    <table class="widefat fixed striped">
                        <thead><tr><th>Mitarbeiter</th><th>Tag</th><th>Start</th><th>Ende</th><th>Aktiv</th><th>Aktion</th></tr></thead>
                        <tbody>
                        <?php if ($rules): foreach ($rules as $r):
                            $weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
                            $weekday_name = isset($weekdays[(int)$r->weekday]) ? $weekdays[(int)$r->weekday] : $r->weekday;
                        ?>
                            <tr>
                                <td><?php echo esc_html($r->display_name); ?></td>
                                <td><?php echo esc_html($weekday_name); ?></td>
                                <td><?php echo esc_html($r->start_time); ?></td>
                                <td><?php echo esc_html($r->end_time); ?></td>
                                <td><?php echo $r->active ? 'ja' : 'nein'; ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Regel löschen?');" style="display:inline">
                                        <?php wp_nonce_field( 'valcode_delete_availability', '_va' ); ?>
                                        <input type="hidden" name="action" value="valcode_delete_availability"/>
                                        <input type="hidden" name="id" value="<?php echo (int)$r->id; ?>"/>
                                        <button class="button button-small button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="6">Keine Regeln vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="va-card">
                    <h2>Blocker</h2>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                        <?php wp_nonce_field( 'valcode_save_blocker', '_va' ); ?>
                        <input type="hidden" name="action" value="valcode_save_blocker"/>
                        <div class="va-field two">
                            <div><label for="bl_staff">Mitarbeiter</label>
                                <select id="bl_staff" name="staff_id" required>
                                    <option value="">Bitte wählen…</option>
                                    <?php foreach($staff as $st): ?>
                                        <option value="<?php echo (int)$st->id; ?>"><?php echo esc_html($st->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label for="bl_note">Notiz</label><input type="text" id="bl_note" name="note"/></div>
                        </div>
                        <div class="va-field two">
                            <div><label for="bl_start">Start</label><input type="datetime-local" id="bl_start" name="starts_at" required/></div>
                            <div><label for="bl_end">Ende</label><input type="datetime-local" id="bl_end" name="ends_at" required/></div>
                        </div>
                        <div class="va-actions"><button class="button button-primary">Blocker speichern</button></div>
                    </form>
                    <h3>Blocker-Liste</h3>
                    <table class="widefat fixed striped">
                        <thead><tr><th>Mitarbeiter</th><th>Start</th><th>Ende</th><th>Notiz</th><th>Aktion</th></tr></thead>
                        <tbody>
                        <?php if ($blockers): foreach ($blockers as $b): ?>
                            <tr>
                                <td><?php echo esc_html($b->display_name); ?></td>
                                <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($b->starts_at))); ?></td>
                                <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($b->ends_at))); ?></td>
                                <td><?php echo esc_html($b->note); ?></td>
                                <td>
                                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('Blocker löschen?');" style="display:inline">
                                        <?php wp_nonce_field( 'valcode_delete_blocker', '_va' ); ?>
                                        <input type="hidden" name="action" value="valcode_delete_blocker"/>
                                        <input type="hidden" name="id" value="<?php echo (int)$b->id; ?>"/>
                                        <button class="button button-small button-link-delete">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="5">Keine Blocker vorhanden.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    public function handle_save_availability() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_availability', '_va' );
        global $wpdb;
        $data = [
            'staff_id' => absint($_POST['staff_id'] ?? 0),
            'weekday' => absint($_POST['weekday'] ?? 1),
            'start_time' => sanitize_text_field($_POST['start_time'] ?? '09:00'),
            'end_time' => sanitize_text_field($_POST['end_time'] ?? '17:00'),
            'active' => isset($_POST['active']) ? 1 : 0,
        ];
        if ( ! $data['staff_id'] ) { wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-availability&err=staff') ); exit; }
        $wpdb->insert( $this->tables['availability'], $data );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-availability&saved=1') ); exit;
    }
    
    public function handle_delete_availability() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_delete_availability', '_va' );
        global $wpdb; $wpdb->delete( $this->tables['availability'], [ 'id' => absint($_POST['id'] ?? 0) ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-availability&deleted=1') ); exit;
    }
    
    public function handle_save_blocker() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_blocker', '_va' );
        global $wpdb;
        $staff_id = absint($_POST['staff_id'] ?? 0);
        $starts = sanitize_text_field($_POST['starts_at'] ?? '');
        $ends   = sanitize_text_field($_POST['ends_at'] ?? '');
        $note   = sanitize_text_field($_POST['note'] ?? '');
        if ( ! $staff_id || ! $starts || ! $ends ) { wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-availability&err=missing') ); exit; }
        $wpdb->insert( $this->tables['blockers'], [
            'staff_id' => $staff_id,
            'starts_at'=> date('Y-m-d H:i:s', strtotime($starts)),
            'ends_at'  => date('Y-m-d H:i:s', strtotime($ends)),
            'note'     => $note
        ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-availability&saved=1') ); exit;
    }
    
    public function handle_delete_blocker() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_delete_blocker', '_va' );
        global $wpdb; $wpdb->delete( $this->tables['blockers'], [ 'id' => absint($_POST['id'] ?? 0) ] );
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-availability&deleted=1') ); exit;
    }

    public function render_design() {
        if ( ! current_user_can('manage_options') ) return;
        $design = get_option('valcode_appoint_design', []);
        $primary = esc_attr($design['primary_color'] ?? '#0f172a');
        $accent  = esc_attr($design['accent_color'] ?? '#6366f1');
        $gradient_start = esc_attr($design['accent_gradient_start'] ?? '#667eea');
        $gradient_end = esc_attr($design['accent_gradient_end'] ?? '#764ba2');
        $radius  = esc_attr($design['radius'] ?? '14px');
        $font    = esc_attr($design['font_family'] ?? 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif');
        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Design</h1>
            <hr class="wp-header-end"/>
            <?php if(isset($_GET['saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>✅ Design gespeichert!</p></div>
            <?php endif; ?>
            <div class="va-card">
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                    <?php wp_nonce_field( 'valcode_save_design', '_va' ); ?>
                    <input type="hidden" name="action" value="valcode_save_design"/>

                    <h3>Grundfarben</h3>
                    <div class="va-field two">
                        <div><label for="primary_color">Primärfarbe (Text & Rahmen)</label><input type="color" id="primary_color" name="primary_color" value="<?php echo $primary; ?>"/></div>
                        <div><label for="accent_color">Akzentfarbe (Buttons & Highlights)</label><input type="color" id="accent_color" name="accent_color" value="<?php echo $accent; ?>"/></div>
                    </div>

                    <h3>Login-Button Gradient</h3>
                    <div class="va-field two">
                        <div><label for="accent_gradient_start">Gradient Start</label><input type="color" id="accent_gradient_start" name="accent_gradient_start" value="<?php echo $gradient_start; ?>"/></div>
                        <div><label for="accent_gradient_end">Gradient Ende</label><input type="color" id="accent_gradient_end" name="accent_gradient_end" value="<?php echo $gradient_end; ?>"/></div>
                    </div>

                    <h3>Typografie & Styling</h3>
                    <div class="va-field two">
                        <div><label for="radius">Eckenradius</label><input type="text" id="radius" name="radius" value="<?php echo $radius; ?>" placeholder="z.B. 14px"/></div>
                        <div><label for="font_family">Schriftfamilie</label><input type="text" id="font_family" name="font_family" value="<?php echo $font; ?>"/></div>
                    </div>

                    <div class="va-actions"><button class="button button-primary">Design speichern</button></div>
                </form>
                <p class="description">Diese Einstellungen beeinflussen das Frontend-Formular <code>[valcode_appoint]</code>.</p>
            </div>
        </div>
        <?php
    }

    public function handle_save_design() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_design', '_va' );
        $opt = get_option('valcode_appoint_design', []);
        $opt['primary_color'] = sanitize_hex_color( $_POST['primary_color'] ?? '#0f172a' );
        $opt['accent_color']  = sanitize_hex_color( $_POST['accent_color'] ?? '#6366f1' );
        $opt['accent_gradient_start'] = sanitize_hex_color( $_POST['accent_gradient_start'] ?? '#667eea' );
        $opt['accent_gradient_end']   = sanitize_hex_color( $_POST['accent_gradient_end'] ?? '#764ba2' );
        $opt['radius']        = sanitize_text_field( $_POST['radius'] ?? '14px' );
        $opt['font_family']   = sanitize_text_field( $_POST['font_family'] ?? 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif' );
        update_option('valcode_appoint_design', $opt);
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-design&saved=1') ); exit;
    }

    public function render_settings() {
        if ( ! current_user_can('manage_options') ) return;
        $settings = get_option('valcode_appoint_settings', []);
        $min_advance = isset($settings['min_advance_days']) ? (int)$settings['min_advance_days'] : 0;
        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Einstellungen</h1>
            <hr class="wp-header-end"/>
            <div class="va-card" style="max-width: 600px;">
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                    <?php wp_nonce_field( 'valcode_save_settings', '_va' ); ?>
                    <input type="hidden" name="action" value="valcode_save_settings"/>
                    
                    <div class="va-field">
                        <label for="min_advance_days">Mindest-Vorlaufzeit für Buchungen (in Tagen)</label>
                        <input type="number" id="min_advance_days" name="min_advance_days" min="0" max="365" value="<?php echo esc_attr($min_advance); ?>" />
                        <p class="description">Wie viele Tage im Voraus muss ein Termin mindestens gebucht werden?<br>
                        <strong>0</strong> = Buchung am gleichen Tag möglich<br>
                        <strong>1</strong> = Mindestens 1 Tag im Voraus<br>
                        <strong>2</strong> = Mindestens 2 Tage im Voraus, etc.</p>
                    </div>

                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;" />
                    <h2 style="margin-bottom: 20px;">SMTP E-Mail-Einstellungen</h2>
                    <p class="description" style="margin-bottom: 20px;">Konfigurieren Sie Ihren SMTP-Server für den E-Mail-Versand. Die SMTP-Benutzername wird automatisch als Absender-Adresse verwendet.</p>

                    <div class="va-field">
                        <label for="smtp_host">SMTP Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo esc_attr($settings['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com" />
                        <p class="description">SMTP Server-Adresse (z.B. smtp.gmail.com, smtp.office365.com)</p>
                    </div>

                    <div class="va-field">
                        <label for="smtp_port">SMTP Port</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo esc_attr($settings['smtp_port'] ?? '587'); ?>" placeholder="587" />
                        <p class="description">SMTP Port (587 für TLS, 465 für SSL, 25 für unverschlüsselt)</p>
                    </div>

                    <div class="va-field">
                        <label for="smtp_secure">Verschlüsselung</label>
                        <select id="smtp_secure" name="smtp_secure">
                            <option value="">Keine</option>
                            <option value="tls" <?php selected($settings['smtp_secure'] ?? 'tls', 'tls'); ?>>TLS</option>
                            <option value="ssl" <?php selected($settings['smtp_secure'] ?? '', 'ssl'); ?>>SSL</option>
                        </select>
                        <p class="description">Verschlüsselungsmethode (TLS empfohlen)</p>
                    </div>

                    <div class="va-field">
                        <label for="smtp_auth">SMTP Authentifizierung</label>
                        <label style="display: inline-flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="smtp_auth" name="smtp_auth" value="1" <?php checked(!empty($settings['smtp_auth'])); ?> />
                            <span>SMTP Authentifizierung aktivieren</span>
                        </label>
                        <p class="description">Aktivieren Sie dies, wenn Ihr SMTP-Server Authentifizierung benötigt (meistens der Fall)</p>
                    </div>

                    <div class="va-field">
                        <label for="smtp_user">SMTP Benutzername / E-Mail-Adresse</label>
                        <input type="text" id="smtp_user" name="smtp_user" value="<?php echo esc_attr($settings['smtp_user'] ?? ''); ?>" placeholder="ihre-email@gmail.com" />
                        <p class="description">Ihr E-Mail-Benutzername (meist Ihre E-Mail-Adresse). Dies wird auch als Absender-Adresse verwendet.</p>
                    </div>

                    <div class="va-field">
                        <label for="smtp_pass">SMTP Passwort</label>
                        <input type="password" id="smtp_pass" name="smtp_pass" value="<?php echo esc_attr($settings['smtp_pass'] ?? ''); ?>" placeholder="••••••••" />
                        <p class="description">Ihr E-Mail-Passwort (bei Gmail: App-Passwort verwenden)</p>
                    </div>

                    <div class="va-field">
                        <label for="smtp_from_name">Absender Name</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo esc_attr($settings['smtp_from_name'] ?? get_bloginfo('name')); ?>" placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>" />
                        <p class="description">Der Name, der in E-Mails als Absender angezeigt wird (z.B. Ihr Firmenname)</p>
                    </div>

                    <div class="va-actions">
                        <button class="button button-primary">Einstellungen speichern</button>
                    </div>
                    
                    <?php if(isset($_GET['saved'])): ?>
                        <p class="va-msg ok">✅ Einstellungen gespeichert!</p>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php
    }

    public function handle_save_settings() {
        if ( ! current_user_can('manage_options') ) wp_die('Forbidden');
        check_admin_referer( 'valcode_save_settings', '_va' );

        $settings = get_option('valcode_appoint_settings', []);
        $settings['min_advance_days'] = max(0, min(365, (int)($_POST['min_advance_days'] ?? 0)));

        // SMTP Settings
        $settings['smtp_host'] = sanitize_text_field($_POST['smtp_host'] ?? '');
        $settings['smtp_port'] = absint($_POST['smtp_port'] ?? 587);
        $settings['smtp_secure'] = sanitize_text_field($_POST['smtp_secure'] ?? '');
        $settings['smtp_auth'] = !empty($_POST['smtp_auth']) ? 1 : 0;
        $settings['smtp_user'] = sanitize_text_field($_POST['smtp_user'] ?? '');
        $settings['smtp_pass'] = $_POST['smtp_pass'] ?? ''; // Don't sanitize password
        $settings['smtp_from_name'] = sanitize_text_field($_POST['smtp_from_name'] ?? '');

        update_option('valcode_appoint_settings', $settings);
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-settings&saved=1') );
        exit;
    }

    private function get_service($id){
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->tables['services']} WHERE id=%d AND active=1", $id) );
    }
    
    private function staff_is_available_rule($staff_id, $start_dt, $end_dt){
        global $wpdb;
        $weekday = (int) wp_date('w', strtotime($start_dt));
        $time_start = wp_date('H:i:s', strtotime($start_dt));
        $time_end   = wp_date('H:i:s', strtotime($end_dt));
        $rows = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->tables['availability']} WHERE staff_id=%d AND active=1 AND weekday=%d", $staff_id, $weekday) );
        foreach($rows as $r){
            if($r->start_time <= $time_start && $r->end_time >= $time_end){
                return true;
            }
        }
        return false;
    }
    
    private function conflicts_with_blockers($staff_id, $start_dt, $end_dt){
        global $wpdb;
        $sql = "SELECT id FROM {$this->tables['blockers']} 
                WHERE staff_id=%d AND ((starts_at < %s AND ends_at > %s) OR (starts_at >= %s AND starts_at < %s))";
        $row = $wpdb->get_var( $wpdb->prepare($sql, $staff_id, $end_dt, $start_dt, $start_dt, $end_dt) );
        return !empty($row);
    }
    
    private function conflicts_with_appointments($staff_id, $start_dt, $end_dt){
        global $wpdb;
        $sql = "SELECT id FROM {$this->tables['appointments']}
                WHERE status NOT IN ('canceled','cancelled') 
                AND staff_id = %d 
                AND ((starts_at < %s AND ends_at > %s) OR (starts_at >= %s AND starts_at < %s))";
        $row = $wpdb->get_var( $wpdb->prepare($sql, $staff_id, $end_dt, $start_dt, $start_dt, $end_dt) );
        return !empty($row);
    }
    
    private function is_slot_free($staff_id, $service_id, $start_dt){
        $service = $this->get_service($service_id);
        if(!$service) return false;
        $dur = (int)$service->duration_minutes;
        $end_dt = date('Y-m-d H:i:s', strtotime($start_dt.' +'.$dur.' minutes'));
        if(!$this->staff_is_available_rule($staff_id, $start_dt, $end_dt)) return false;
        if($this->conflicts_with_blockers($staff_id, $start_dt, $end_dt)) return false;
        if($this->conflicts_with_appointments($staff_id, $start_dt, $end_dt)) return false;
        return true;
    }
    
    private function generate_time_slots($staff_id, $service_id, $date_str, $slot_len=30){
        $service = $this->get_service($service_id);
        if(!$service) return [];
        $dur = max( (int)$service->duration_minutes, $slot_len );
        $ts_date = strtotime($date_str.' 00:00:00');
        $weekday = (int) wp_date('w', $ts_date);
        global $wpdb;
        $rules = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->tables['availability']} WHERE staff_id=%d AND active=1 AND weekday=%d ORDER BY start_time", $staff_id, $weekday) );
        $slots = [];
        foreach($rules as $r){
            $start_ts = strtotime($date_str.' '.$r->start_time);
            $end_ts   = strtotime($date_str.' '.$r->end_time);
            for($t=$start_ts; $t+$dur*60 <= $end_ts; $t += $slot_len*60){
                $start_dt = date('Y-m-d H:i:s', $t);
                if($this->is_slot_free($staff_id, $service_id, $start_dt)){
                    $slots[] = [ 'start'=>$start_dt, 'label'=> wp_date('H:i', $t) ];
                }
            }
        }
        return $slots;
    }
    
    public function ajax_get_slots(){
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        $service_id = absint( $_GET['service_id'] ?? 0 );
        $staff_id   = absint( $_GET['staff_id'] ?? 0 );
        $date       = sanitize_text_field( $_GET['date'] ?? '' );
        
        if(!$service_id || !$staff_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
            wp_send_json_error(['message'=>'Ungültige Parameter.']); 
            return;
        }
        
        $slots = $this->generate_time_slots($staff_id, $service_id, $date, 30);
        
        // Get booked appointments for this staff member on this day
        global $wpdb;
        $booked = $wpdb->get_col( $wpdb->prepare(
            "SELECT starts_at FROM {$this->tables['appointments']} 
            WHERE staff_id = %d 
            AND DATE(starts_at) = %s 
            AND status NOT IN ('cancelled', 'canceled')",
            $staff_id, $date
        ));
        
        wp_send_json_success([
            'slots' => $slots,
            'booked' => $booked
        ]);
    }

    public function ajax_get_workers() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        global $wpdb;
        $service_id = isset($_GET['service_id']) ? absint($_GET['service_id']) : 0;
        $rows = $wpdb->get_results( "SELECT id, display_name, services, active FROM {$this->tables['staff']} WHERE active=1 ORDER BY display_name" );
        $out = [];
        foreach ($rows as $r) {
            $list = $r->services ? json_decode($r->services, true) : [];
            if ( ! is_array($list) ) $list = [];
            if ( $service_id && ! in_array( $service_id, $list, true ) ) continue;
            $out[] = [ 'id' => (int)$r->id, 'name' => $r->display_name ];
        }
        wp_send_json_success( [ 'workers' => $out ] );
    }

    public function ajax_create_appointment() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        global $wpdb;
        $customer_name  = sanitize_text_field( $_POST['customer_name'] ?? '' );
        $customer_email = sanitize_email( $_POST['customer_email'] ?? '' );
        $service_id     = absint( $_POST['service_id'] ?? 0 );
        $staff_id       = absint( $_POST['staff_id'] ?? 0 );
        $starts_at_raw  = sanitize_text_field( $_POST['starts_at'] ?? '' );
        $notes          = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $user_id        = isset($_POST['user_id']) ? absint($_POST['user_id']) : null;

        // If user_id is provided, get customer data from database
        if ( $user_id ) {
            $customer = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$this->tables['customers']} WHERE id=%d",
                $user_id
            ));

            if ( $customer ) {
                $customer_name = $customer->first_name . ' ' . $customer->last_name;
                $customer_email = $customer->email;
                if ( $customer->notes && !$notes ) {
                    $notes = $customer->notes;
                }
            } else {
                wp_send_json_error(['message'=>'Kunde nicht gefunden.']); return;
            }
        }

        if ( ! $customer_name || ! $service_id || ! $starts_at_raw ) {
            wp_send_json_error(['message'=>'Erforderliche Felder fehlen.']); return;
        }
        $ts = strtotime($starts_at_raw);
        if ($ts === false) { wp_send_json_error(['message'=>'Ungültiges Datum.']); return; }
        $starts_at = date('Y-m-d H:i:s', $ts);

        $service = $this->get_service($service_id);
        if(!$service){ wp_send_json_error(['message'=>'Service nicht gefunden.']); return; }
        $duration = (int)$service->duration_minutes;
        $ends_at = date('Y-m-d H:i:s', strtotime($starts_at.' +'.$duration.' minutes'));

        if(!$staff_id){ wp_send_json_error(['message'=>'Bitte Mitarbeiter wählen.']); return; }

        if( ! $this->is_slot_free($staff_id, $service_id, $starts_at) ){
            wp_send_json_error(['message'=>'Dieser Slot ist nicht mehr verfügbar. Bitte anderen Zeitpunkt wählen.']); return;
        }

        // Create or get customer record for guest bookings
        $customer_id = null;
        if (!$user_id && $customer_email) {
            // Check if guest customer with this email already exists
            $existing_customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->tables['customers']} WHERE email = %s AND is_guest = 1",
                $customer_email
            ));

            if ($existing_customer) {
                // Update existing guest customer
                $customer_id = $existing_customer->id;
                $name_parts = explode(' ', $customer_name, 2);
                $wpdb->update(
                    $this->tables['customers'],
                    [
                        'first_name' => $name_parts[0],
                        'last_name' => isset($name_parts[1]) ? $name_parts[1] : '',
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $customer_id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                // Create new guest customer
                $name_parts = explode(' ', $customer_name, 2);
                $wpdb->insert($this->tables['customers'], [
                    'first_name' => $name_parts[0],
                    'last_name' => isset($name_parts[1]) ? $name_parts[1] : '',
                    'email' => $customer_email,
                    'is_guest' => 1,
                    'created_at' => current_time('mysql')
                ], ['%s', '%s', '%s', '%d', '%s']);
                $customer_id = $wpdb->insert_id;
            }
        }

        $ok = $wpdb->insert( $this->tables['appointments'], [
            'customer_name' => $customer_name,
            'customer_email'=> $customer_email,
            'service_id'    => $service_id,
            'staff_id'      => $staff_id,
            'starts_at'     => $starts_at,
            'ends_at'       => $ends_at,
            'notes'         => $notes,
            'status'        => 'confirmed',
            'created_at'    => current_time('mysql')
        ], [ '%s','%s','%d','%d','%s','%s','%s','%s','%s' ] );
        if(!$ok){ wp_send_json_error(['message'=>'Konnte nicht speichern.']); return; }

        $appt_id = $wpdb->insert_id;

        // Build ICS (using Swiss timezone)
        date_default_timezone_set('Europe/Zurich');
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $uid = $appt_id.'@'.parse_url( home_url(), PHP_URL_HOST );
        $summary = $blogname.' – Termin: '.$service->name;
        $desc = "Service: {$service->name}\nName: {$customer_name}\nEmail: {$customer_email}\nHinweise: {$notes}";
        $dtstart = gmdate('Ymd\THis\Z', strtotime(get_date_from_gmt( get_gmt_from_date( $starts_at ), 'Y-m-d H:i:s' )));
        $dtend   = gmdate('Ymd\THis\Z', strtotime(get_date_from_gmt( get_gmt_from_date( $ends_at ), 'Y-m-d H:i:s' )));
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Valcode Appoint//DE\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:$uid\r\nSUMMARY:".esc_html($summary)."\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\nDESCRIPTION:".esc_html($desc)."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        // Email both customer and admin
        $admin_email = get_option('admin_email');
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        // Get email settings from plugin options (SMTP settings)
        $settings = get_option('valcode_appoint_settings', []);
        $from_email = !empty($settings['smtp_user']) ? $settings['smtp_user'] : 'noreply@' . parse_url(home_url(), PHP_URL_HOST);
        $from_name = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : $blogname;

        // Get staff details
        $staff = $wpdb->get_row($wpdb->prepare(
            "SELECT display_name, email FROM {$this->tables['staff']} WHERE id=%d",
            $staff_id
        ));
        $staff_name = $staff ? $staff->display_name : 'Unser Team';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];
        $attachments = [];
        $tmp = wp_upload_dir();
        $ics_path = trailingslashit($tmp['basedir'])."appoint-$appt_id.ics";
        file_put_contents($ics_path, $ics);
        $attachments[] = $ics_path;

        $gcal_link = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode($summary)
            .'&dates='.gmdate('Ymd\THis\Z', strtotime($starts_at)).'%2F'.gmdate('Ymd\THis\Z', strtotime($ends_at))
            .'&details='.rawurlencode("{$desc}").'&sf=true&output=xml';

        // Get WordPress logo and theme colors
        $custom_logo_id = get_theme_mod('custom_logo');
        $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : '';
        $site_url = home_url();

        // Get plugin design settings
        $design = get_option('valcode_appoint_design', []);
        $primary_color = isset($design['primary_color']) ? $design['primary_color'] : '#0f172a';
        $accent_color = isset($design['accent_color']) ? $design['accent_color'] : '#6366f1';
        $gradient_start = isset($design['accent_gradient_start']) ? $design['accent_gradient_start'] : '#667eea';
        $gradient_end = isset($design['accent_gradient_end']) ? $design['accent_gradient_end'] : '#764ba2';

        // Enhanced email body for customer
        $customer_body_html = '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
            <div style="background: linear-gradient(135deg, ' . $gradient_start . ' 0%, ' . $gradient_end . ' 100%); padding: 40px 30px; text-align: center; color: white; border-radius: 0;">
                ' . ($logo_url ? '<div style="margin-bottom: 20px;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($blogname) . '" style="max-height: 60px; width: auto;"/></div>' : '') . '
                <h1 style="margin: 0; font-size: 32px; font-weight: 600; text-shadow: 0 2px 4px rgba(0,0,0,0.1);">Buchung bestätigt!</h1>
            </div>
            <div style="background: #f6f7f7; padding: 40px 30px;">
                <p style="font-size: 18px; color: #1e1e1e; margin-top: 0;">Hallo <strong>' . esc_html($customer_name) . '</strong>,</p>
                <p style="font-size: 16px; color: #3c434a; line-height: 1.6;">vielen Dank für Ihre Buchung! Wir freuen uns auf Ihren Besuch.</p>

                <div style="background: white; border-radius: 8px; padding: 30px; margin: 30px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid ' . $accent_color . ';">
                    <h2 style="margin: 0 0 20px 0; color: #1e1e1e; font-size: 22px; font-weight: 600;">Ihre Termindetails</h2>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Service:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html($service->name) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Datum:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html(wp_date('d.m.Y', strtotime($starts_at), new DateTimeZone('Europe/Zurich'))) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Uhrzeit:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html(wp_date('H:i', strtotime($starts_at), new DateTimeZone('Europe/Zurich'))) . ' - ' . esc_html(wp_date('H:i', strtotime($ends_at), new DateTimeZone('Europe/Zurich'))) . ' Uhr</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Dauer:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . (int)$service->duration_minutes . ' Minuten</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Preis:</td>
                            <td style="padding: 12px 0; color: ' . $accent_color . '; font-weight: 700; text-align: right; font-size: 16px;">CHF ' . number_format((float)$service->price, 2) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Mitarbeiter:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html($staff_name) . '</td>
                        </tr>
                    </table>
                </div>

                ' . ($notes ? '<div style="background: #fef8e7; border-left: 4px solid #f0b849; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0; color: #32373c; font-size: 15px;"><strong style="color: #8a6d3b;">Ihre Notizen:</strong><br/><span style="margin-top: 8px; display: inline-block;">' . nl2br(esc_html($notes)) . '</span></p>
                </div>' : '') . '

                <div style="text-align: center; margin: 35px 0;">
                    <a href="' . esc_url($gcal_link) . '" style="display: inline-block; background: linear-gradient(135deg, ' . $gradient_start . ' 0%, ' . $gradient_end . ' 100%); color: white; text-decoration: none; padding: 16px 40px; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); transition: all 0.3s;">
                        Zu Google Kalender hinzufügen
                    </a>
                </div>

                <div style="background: #d5e5f2; padding: 20px; border-radius: 6px; margin-top: 30px; text-align: center;">
                    <p style="font-size: 14px; color: #1e5b8a; margin: 0; line-height: 1.6;">
                        Die angehängte .ics-Datei können Sie in Ihren Kalender importieren (Outlook, Apple Calendar, etc.)
                    </p>
                </div>
            </div>
            <div style="background: #1e1e1e; padding: 30px; text-align: center; color: #a7aaad;">
                ' . ($logo_url ? '<div style="margin-bottom: 15px;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($blogname) . '" style="max-height: 40px; width: auto; opacity: 0.6;"/></div>' : '') . '
                <p style="margin: 0 0 8px 0; font-size: 16px; color: #c3c4c7; font-weight: 500;">' . esc_html($blogname) . '</p>
                <p style="margin: 0; font-size: 13px;">Diese E-Mail wurde automatisch generiert.</p>
                <p style="margin: 10px 0 0 0; font-size: 13px;"><a href="' . esc_url($site_url) . '" style="color: ' . $accent_color . '; text-decoration: none;">' . esc_html(parse_url($site_url, PHP_URL_HOST)) . '</a></p>
            </div>
        </div>';

        // Admin notification email
        $admin_body_html = '
        <div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
            <div style="background: linear-gradient(135deg, ' . $primary_color . ' 0%, ' . $accent_color . ' 100%); padding: 40px 30px; text-align: center; color: white;">
                ' . ($logo_url ? '<div style="margin-bottom: 20px;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($blogname) . '" style="max-height: 60px; width: auto;"/></div>' : '') . '
                <h1 style="margin: 0; font-size: 28px; font-weight: 600;">Neue Buchung erhalten</h1>
            </div>
            <div style="background: #f6f7f7; padding: 40px 30px;">
                <div style="background: white; border-radius: 8px; padding: 30px; margin: 30px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid ' . $accent_color . ';">
                    <h2 style="margin: 0 0 20px 0; color: #1e1e1e; font-size: 22px; font-weight: 600;">Buchungsdetails</h2>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Kunde:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html($customer_name) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">E-Mail:</td>
                            <td style="padding: 12px 0; color: ' . $accent_color . '; font-weight: 600; text-align: right; font-size: 15px;"><a href="mailto:' . esc_attr($customer_email) . '" style="color: ' . $accent_color . '; text-decoration: none;">' . esc_html($customer_email) . '</a></td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Service:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html($service->name) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Datum:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html(wp_date('d.m.Y', strtotime($starts_at), new DateTimeZone('Europe/Zurich'))) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Uhrzeit:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html(wp_date('H:i', strtotime($starts_at), new DateTimeZone('Europe/Zurich'))) . ' - ' . esc_html(wp_date('H:i', strtotime($ends_at), new DateTimeZone('Europe/Zurich'))) . ' Uhr</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Dauer:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . (int)$service->duration_minutes . ' Minuten</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Mitarbeiter:</td>
                            <td style="padding: 12px 0; color: #1e1e1e; font-weight: 600; text-align: right; font-size: 15px;">' . esc_html($staff_name) . '</td>
                        </tr>
                        <tr style="border-top: 1px solid #f0f0f1;">
                            <td style="padding: 12px 0; color: #646970; font-size: 15px;">Preis:</td>
                            <td style="padding: 12px 0; color: #00a32a; font-weight: 700; text-align: right; font-size: 16px;">CHF ' . number_format((float)$service->price, 2) . '</td>
                        </tr>
                    </table>
                </div>

                ' . ($notes ? '<div style="background: #fef8e7; border-left: 4px solid #f0b849; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <p style="margin: 0; color: #32373c; font-size: 15px;"><strong style="color: #8a6d3b;">Kundennotizen:</strong><br/><span style="margin-top: 8px; display: inline-block;">' . nl2br(esc_html($notes)) . '</span></p>
                </div>' : '') . '

                <div style="text-align: center; margin: 35px 0;">
                    <a href="' . esc_url(admin_url('admin.php?page=valcode-appoint-appointments')) . '" style="display: inline-block; background: linear-gradient(135deg, ' . $gradient_start . ' 0%, ' . $gradient_end . ' 100%); color: white; text-decoration: none; padding: 16px 40px; border-radius: 6px; font-weight: 600; font-size: 16px; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);">
                        Termin im Dashboard anzeigen
                    </a>
                </div>
            </div>
            <div style="background: #1e1e1e; padding: 30px; text-align: center; color: #a7aaad;">
                ' . ($logo_url ? '<div style="margin-bottom: 15px;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($blogname) . '" style="max-height: 40px; width: auto; opacity: 0.6;"/></div>' : '') . '
                <p style="margin: 0 0 8px 0; font-size: 16px; color: #c3c4c7; font-weight: 500;">' . esc_html($blogname) . '</p>
                <p style="margin: 0; font-size: 13px;">Admin-Benachrichtigung – Automatisch generiert</p>
            </div>
        </div>';

        $mail_sent = false;
        $admin_mail_sent = false;

        // Send to customer first
        if($customer_email){
            $mail_sent = wp_mail($customer_email, 'Buchungsbestätigung – ' . $blogname, $customer_body_html, $headers, $attachments);

            // Log email result for debugging
            if(!$mail_sent) {
                error_log('Valcode Appoint: Failed to send customer email to ' . $customer_email);
            }
        }

        // Send to admin
        $admin_mail_sent = wp_mail($admin_email, 'Neue Buchung – ' . $blogname, $admin_body_html, $headers, $attachments);

        if(!$admin_mail_sent) {
            error_log('Valcode Appoint: Failed to send admin email to ' . $admin_email);
        }

        // Clean up ICS file after both emails are sent
        if(file_exists($ics_path)) {
            // Small delay to ensure emails are processed
            sleep(1);
            @unlink($ics_path);
        }

        wp_send_json_success([
            'message'=>'Termin bestätigt. Bestätigung per E-Mail gesendet.',
            'appointment_id' => $appt_id,
            'gcal' => $gcal_link
        ]);
    }

    // Custom customer login (separate from WordPress)
    public function ajax_customer_login() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        global $wpdb;

        $email = sanitize_email( $_POST['email'] ?? '' );
        $password = $_POST['password'] ?? '';

        if ( ! $email || ! $password ) {
            wp_send_json_error(['message'=>'E-Mail und Passwort erforderlich.']); return;
        }

        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['customers']} WHERE email=%s",
            $email
        ));

        if ( ! $customer || ! $customer->password_hash ) {
            wp_send_json_error(['message'=>'Ungültige Anmeldedaten.']); return;
        }

        if ( ! password_verify($password, $customer->password_hash) ) {
            wp_send_json_error(['message'=>'Ungültige Anmeldedaten.']); return;
        }

        // Update last login
        $wpdb->update( $this->tables['customers'],
            ['last_login' => current_time('mysql')],
            ['id' => $customer->id]
        );

        // Set session
        if ( ! session_id() ) session_start();
        $_SESSION['valcode_customer_id'] = $customer->id;
        $_SESSION['valcode_customer_email'] = $customer->email;
        $_SESSION['valcode_customer_name'] = $customer->first_name . ' ' . $customer->last_name;

        wp_send_json_success([
            'message'=>'Erfolgreich angemeldet!',
            'customer_id' => $customer->id,
            'customer_name' => $customer->first_name . ' ' . $customer->last_name,
            'customer_email' => $customer->email
        ]);
    }

    public function ajax_customer_register() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        global $wpdb;

        $first_name = sanitize_text_field( $_POST['first_name'] ?? '' );
        $last_name = sanitize_text_field( $_POST['last_name'] ?? '' );
        $email = sanitize_email( $_POST['email'] ?? '' );
        $phone = sanitize_text_field( $_POST['phone'] ?? '' );
        $password = $_POST['password'] ?? '';
        $notes = sanitize_textarea_field( $_POST['notes'] ?? '' );

        if ( ! $first_name || ! $last_name || ! $email || ! $password ) {
            wp_send_json_error(['message'=>'Alle Pflichtfelder ausfüllen.']); return;
        }

        if ( strlen($password) < 6 ) {
            wp_send_json_error(['message'=>'Passwort muss mindestens 6 Zeichen lang sein.']); return;
        }

        // Check if email already exists
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['customers']} WHERE email=%s",
            $email
        ));

        $customer_id = null;

        if ( $existing ) {
            // If it's a guest account, convert it to a registered account
            if ( $existing->is_guest == 1 ) {
                // Update guest account to registered account
                $result = $wpdb->update(
                    $this->tables['customers'],
                    [
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'phone' => $phone,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'notes' => $notes,
                        'is_guest' => 0,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $existing->id],
                    ['%s', '%s', '%s', '%s', '%s', '%d', '%s'],
                    ['%d']
                );

                if ( $result === false ) {
                    wp_send_json_error(['message'=>'Fehler beim Aktualisieren des Kontos.']); return;
                }

                $customer_id = $existing->id;
            } else {
                // Already a registered account
                wp_send_json_error(['message'=>'Diese E-Mail ist bereits registriert. Bitte melden Sie sich an.']); return;
            }
        } else {
            // Create new customer
            $result = $wpdb->insert( $this->tables['customers'], [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'notes' => $notes,
                'is_guest' => 0,
                'created_at' => current_time('mysql')
            ]);

            if ( ! $result ) {
                wp_send_json_error(['message'=>'Registrierung fehlgeschlagen.']); return;
            }

            $customer_id = $wpdb->insert_id;
        }

        // Auto login
        if ( ! session_id() ) session_start();
        $_SESSION['valcode_customer_id'] = $customer_id;
        $_SESSION['valcode_customer_email'] = $email;
        $_SESSION['valcode_customer_name'] = $first_name . ' ' . $last_name;

        // Different message if guest account was converted
        $message = 'Erfolgreich registriert und angemeldet!';
        if ( $existing && $existing->is_guest == 1 ) {
            $message = 'Ihr Gastkonto wurde erfolgreich in ein registriertes Konto umgewandelt!';
        }

        wp_send_json_success([
            'message' => $message,
            'customer_id' => $customer_id,
            'customer_name' => $first_name . ' ' . $last_name,
            'customer_email' => $email,
            'was_converted' => ($existing && $existing->is_guest == 1)
        ]);
    }

    public function ajax_customer_logout() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');

        if ( ! session_id() ) session_start();
        unset($_SESSION['valcode_customer_id']);
        unset($_SESSION['valcode_customer_email']);
        unset($_SESSION['valcode_customer_name']);

        wp_send_json_success(['message'=>'Erfolgreich abgemeldet.']);
    }

    public function ajax_customer_check() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');

        if ( ! session_id() ) session_start();

        if ( isset($_SESSION['valcode_customer_id']) ) {
            wp_send_json_success([
                'logged_in' => true,
                'customer_id' => $_SESSION['valcode_customer_id'],
                'customer_name' => $_SESSION['valcode_customer_name'] ?? '',
                'customer_email' => $_SESSION['valcode_customer_email'] ?? ''
            ]);
        } else {
            wp_send_json_success(['logged_in' => false]);
        }
    }

    public function shortcode_form( $atts ) {
        global $wpdb;
        $services = $wpdb->get_results( "SELECT id, name, duration_minutes, price FROM {$this->tables['services']} WHERE active=1 ORDER BY name" );

        wp_enqueue_style( 'valcode-appoint-public' );
        wp_enqueue_script( 'valcode-appoint' );

        ob_start(); ?>
        
    <div class="va-booking-form">
        <form id="va-booking" novalidate>
            
            <!-- Step 1: Service & Mitarbeiter -->
            <div class="va-step" data-step="1">
                <h3>Service & Mitarbeiter wählen</h3>
                
                <div class="va-field">
                    <label for="va_service">Service auswählen</label>
                    <select id="va_service" name="service_id" required>
                        <option value="">Bitte wählen…</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?php echo (int)$s->id; ?>">
                                <?php echo esc_html($s->name); ?> 
                                (<?php echo (int)$s->duration_minutes; ?> Min, 
                                CHF <?php echo number_format((float)$s->price, 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="va-field">
                    <label for="va_worker">Mitarbeiter auswählen</label>
                    <select id="va_worker" name="staff_id" disabled required>
                        <option value="">Bitte zuerst Service wählen…</option>
                    </select>
                </div>

                <div class="va-actions">
                    <button type="button" id="va_next_1" class="va-btn" disabled>Weiter</button>
                </div>
            </div>

            <!-- Step 2: Datum wählen - Calendar Widget -->
            <div class="va-step" data-step="2" hidden>
                <h3>Datum wählen</h3>
                
                <div id="va_calendar"></div>

                <div class="va-actions">
                    <button type="button" id="va_prev_2" class="va-btn va-prev">Zurück</button>
                    <button type="button" id="va_next_2" class="va-btn" disabled>Weiter</button>
                </div>
            </div>

            <!-- Step 3: Zeit wählen -->
            <div class="va-step" data-step="3" hidden>
                <h3>Zeit wählen</h3>
                
                <div id="va_slots" class="va-slots-grid">
                    <p class="va-loading">Bitte wählen Sie zuerst ein Datum</p>
                </div>

                <input type="hidden" id="va_starts_at" name="starts_at">

                <div class="va-actions">
                    <button type="button" id="va_prev_3" class="va-btn va-prev">Zurück</button>
                    <button type="button" id="va_next_3" class="va-btn" disabled>Weiter</button>
                </div>
            </div>

            <!-- Step 4: Login oder Gast -->
            <div class="va-step" data-step="4" hidden>
                <h3>Wie möchten Sie fortfahren?</h3>

                <!-- Logged in customer info (will be shown via JS if logged in) -->
                <div class="va-customer-logged-in" id="va_customer_logged_in" hidden>
                    <div class="va-customer-card">
                        <div class="va-customer-info">
                            <div class="va-customer-avatar">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="va-customer-details">
                                <p class="va-customer-greeting">Willkommen zurück!</p>
                                <p class="va-customer-name" id="va_logged_customer_name"></p>
                                <p class="va-customer-email" id="va_logged_customer_email"></p>
                            </div>
                        </div>
                        <button type="button" class="va-btn va-btn-logout" id="va_logout_btn">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6 14H3C2.73478 14 2.48043 13.8946 2.29289 13.7071C2.10536 13.5196 2 13.2652 2 13V3C2 2.73478 2.10536 2.48043 2.29289 2.29289C2.48043 2.10536 2.73478 2 3 2H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M11 11L14 8L11 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M14 8H6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            Abmelden
                        </button>
                    </div>
                    <input type="hidden" id="va_customer_id" value=""/>

                    <!-- Notes field for logged in users -->
                    <div class="va-auth-form" style="margin-top: 20px;">
                        <div class="va-field full">
                            <label for="va_notes_logged_in">Notizen / Wünsche (optional)</label>
                            <textarea id="va_notes_logged_in" name="notes_logged_in" rows="4" placeholder="Besondere Wünsche oder Hinweise..."></textarea>
                        </div>
                    </div>
                </div>

                <!-- Auth options (shown when not logged in) -->
                <div id="va_auth_container"><?php // Will be shown/hidden by JS ?>
                    <div class="va-auth-options">
                        <div class="va-radio-card">
                            <input type="radio" name="booking_mode" value="guest" id="va_mode_guest" checked/>
                            <label class="va-radio-label" for="va_mode_guest">
                                <div class="va-radio-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 17.9391 19.5786 16.9217 18.8284 16.1716C18.0783 15.4214 17.0609 15 16 15H8C6.93913 15 5.92172 15.4214 5.17157 16.1716C4.42143 16.9217 4 17.9391 4 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M12 11C14.2091 11 16 9.20914 16 7C16 4.79086 14.2091 3 12 3C9.79086 3 8 4.79086 8 7C8 9.20914 9.79086 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <strong>Als Gast</strong>
                                <small>Schnell & ohne Registrierung</small>
                            </label>
                        </div>
                        <div class="va-radio-card">
                            <input type="radio" name="booking_mode" value="login" id="va_mode_login"/>
                            <label class="va-radio-label" for="va_mode_login">
                                <div class="va-radio-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M15 3H19C19.5304 3 20.0391 3.21071 20.4142 3.58579C20.7893 3.96086 21 4.46957 21 5V19C21 19.5304 20.7893 20.0391 20.4142 20.4142C20.0391 20.7893 19.5304 21 19 21H15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M10 17L15 12L10 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <strong>Anmelden</strong>
                                <small>Mit bestehendem Konto</small>
                            </label>
                        </div>
                        <div class="va-radio-card">
                            <input type="radio" name="booking_mode" value="register" id="va_mode_register"/>
                            <label class="va-radio-label" for="va_mode_register">
                                <div class="va-radio-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M16 21V19C16 17.9391 15.5786 16.9217 14.8284 16.1716C14.0783 15.4214 13.0609 15 12 15H5C3.93913 15 2.92172 15.4214 2.17157 16.1716C1.42143 16.9217 1 17.9391 1 19V21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M8.5 11C10.7091 11 12.5 9.20914 12.5 7C12.5 4.79086 10.7091 3 8.5 3C6.29086 3 4.5 4.79086 4.5 7C4.5 9.20914 6.29086 11 8.5 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M20 8V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        <path d="M23 11H17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <strong>Registrieren</strong>
                                <small>Neues Konto erstellen</small>
                            </label>
                        </div>
                    </div>

                    <!-- Guest Form -->
                    <div id="va_guest_form" class="va-auth-form" style="margin-top: 20px;">
                        <div class="va-grid">
                            <div class="va-field">
                                <label for="va_guest_name">Vor- und Nachname *</label>
                                <input id="va_guest_name" name="guest_name" type="text" placeholder="Max Muster">
                            </div>
                            <div class="va-field">
                                <label for="va_guest_email">E-Mail-Adresse *</label>
                                <input id="va_guest_email" name="guest_email" type="email" placeholder="max@beispiel.ch">
                            </div>
                            <div class="va-field full">
                                <label for="va_notes">Notizen / Wünsche (optional)</label>
                                <textarea id="va_notes" name="notes" rows="4" placeholder="Besondere Wünsche oder Hinweise..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Login Form -->
                    <div id="va_login_form" class="va-auth-form" hidden style="margin-top: 20px;">
                        <div class="va-grid">
                            <div class="va-field">
                                <label for="va_login_email">E-Mail oder Benutzername *</label>
                                <input id="va_login_email" name="login_email" type="text" placeholder="Ihre E-Mail">
                            </div>
                            <div class="va-field">
                                <label for="va_login_password">Passwort *</label>
                                <input id="va_login_password" name="login_password" type="password" placeholder="Ihr Passwort">
                            </div>
                        </div>
                        <p class="description"><a href="#" id="va_forgot_password_link">Passwort vergessen?</a></p>
                    </div>

                    <!-- Forgot Password Form -->
                    <div id="va_forgot_form" class="va-auth-form" hidden style="margin-top: 20px;">
                        <div class="va-field">
                            <label for="va_reset_email">E-Mail-Adresse *</label>
                            <input id="va_reset_email" type="email" placeholder="Ihre E-Mail-Adresse">
                        </div>
                        <button type="button" id="va_reset_request_btn" class="va-btn">Reset-Link senden</button>
                        <p class="description"><a href="#" id="va_back_to_login">Zurück zum Login</a></p>
                        <div id="va_reset_msg" style="margin-top:12px;"></div>
                    </div>

                    <!-- Register Form -->
                    <div id="va_register_form" class="va-auth-form" hidden style="margin-top: 20px;">
                        <div class="va-grid">
                            <div class="va-field">
                                <label for="va_reg_firstname">Vorname *</label>
                                <input id="va_reg_firstname" name="reg_firstname" type="text" placeholder="Max">
                            </div>
                            <div class="va-field">
                                <label for="va_reg_lastname">Nachname *</label>
                                <input id="va_reg_lastname" name="reg_lastname" type="text" placeholder="Muster">
                            </div>
                            <div class="va-field">
                                <label for="va_reg_email">E-Mail-Adresse *</label>
                                <input id="va_reg_email" name="reg_email" type="email" placeholder="max@beispiel.ch">
                            </div>
                            <div class="va-field">
                                <label for="va_reg_phone">Telefon</label>
                                <input id="va_reg_phone" name="reg_phone" type="tel" placeholder="+41 79 123 45 67">
                            </div>
                            <div class="va-field full">
                                <label for="va_reg_password">Passwort *</label>
                                <input id="va_reg_password" name="reg_password" type="password" placeholder="Mindestens 6 Zeichen">
                            </div>
                            <div class="va-field full">
                                <label for="va_reg_notes">Notizen (optional)</label>
                                <textarea id="va_reg_notes" name="reg_notes" rows="3" placeholder="Besondere Hinweise..."></textarea>
                            </div>
                        </div>
                    </div>
                </div><!-- end va_auth_container -->

                <div class="va-actions" style="margin-top: 20px;">
                    <button type="button" id="va_prev_4" class="va-btn va-prev">Zurück</button>
                    <button type="submit" class="va-btn" id="va_submit_booking">Termin verbindlich buchen</button>
                </div>

                <p class="va-msg" id="va_msg" hidden></p>
            </div>

            <!-- Step 5: Success -->
            <div class="va-step" data-step="5" hidden>
                <div class="va-success-content">
                    <div class="va-success-animation">
                        <div class="va-success-icon-circle">
                            <svg class="va-checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
                                <circle class="va-checkmark-circle" cx="26" cy="26" r="25" fill="none"/>
                                <path class="va-checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                            </svg>
                        </div>
                    </div>
                    <h3 style="font-size: 28px; color: #1e1e1e; margin: 20px 0 10px 0;">Buchung erfolgreich!</h3>
                    <p style="font-size: 16px; color: #646970; margin-bottom: 30px;">Ihre Terminbuchung wurde bestätigt</p>

                    <div class="va-success-details" id="va_success_msg" style="background: #f6f7f7; border-radius: 12px; padding: 30px; margin: 20px 0; border-left: 4px solid var(--va-accent); text-align: left;">
                        <p style="font-size: 15px; color: #3c434a; line-height: 1.8; margin: 0;">
                            <strong style="color: #1e1e1e; font-size: 16px;">Was passiert jetzt?</strong><br><br>
                            Sie erhalten in Kürze eine Bestätigungs-E-Mail mit:<br>
                            • Allen Termindetails<br>
                            • Einem Kalendereintrag (.ics Datei)<br>
                            • Link zum Google Kalender<br><br>
                            Wir freuen uns auf Ihren Besuch!
                        </p>
                    </div>

                    <button type="button" class="va-btn va-btn-new" onclick="window.location.reload()" style="background: linear-gradient(135deg, var(--va-gradient-start) 0%, var(--va-gradient-end) 100%); border: none; color: white; font-size: 16px; padding: 16px 40px; border-radius: 8px; cursor: pointer; font-weight: 600; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3); transition: all 0.3s; margin-top: 20px;">
                        Neuen Termin buchen
                    </button>
                </div>
            </div>

        </form>
    </div>

        <?php
        return ob_get_clean();
    }

    // Password Reset Request
    public function ajax_customer_reset_request() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        global $wpdb;

        $email = sanitize_email( $_POST['email'] ?? '' );

        if ( ! $email ) {
            wp_send_json_error(['message'=>'Bitte E-Mail-Adresse eingeben.']); return;
        }

        // Check if customer exists and is not a guest
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['customers']} WHERE email=%s AND is_guest=0",
            $email
        ));

        if ( ! $customer ) {
            // Don't reveal if email exists or not for security
            wp_send_json_success(['message'=>'Wenn die E-Mail existiert, wurde ein Reset-Link gesendet.']); return;
        }

        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Save token
        $wpdb->update(
            $this->tables['customers'],
            [
                'reset_token' => $token,
                'reset_expires' => $expires
            ],
            ['id' => $customer->id],
            ['%s', '%s'],
            ['%d']
        );

        // Send email
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        // Get email settings from plugin options (SMTP settings)
        $settings = get_option('valcode_appoint_settings', []);
        $from_email = !empty($settings['smtp_user']) ? $settings['smtp_user'] : 'noreply@' . parse_url(home_url(), PHP_URL_HOST);
        $from_name = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : $blogname;

        $reset_link = home_url('?valcode_reset=' . $token);

        $subject = 'Passwort zurücksetzen – ' . $blogname;
        $message = '<p>Hallo ' . esc_html($customer->first_name) . ',</p>';
        $message .= '<p>Sie haben eine Anfrage zum Zurücksetzen Ihres Passworts gestellt.</p>';
        $message .= '<p><a href="' . esc_url($reset_link) . '">Klicken Sie hier, um Ihr Passwort zurückzusetzen</a></p>';
        $message .= '<p>Dieser Link ist 1 Stunde gültig.</p>';
        $message .= '<p>Falls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail.</p>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>'
        ];

        wp_mail($email, $subject, $message, $headers);

        wp_send_json_success(['message'=>'Reset-Link wurde per E-Mail gesendet.']);
    }

    // Password Reset
    public function ajax_customer_reset_password() {
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        global $wpdb;

        $token = sanitize_text_field( $_POST['token'] ?? '' );
        $password = $_POST['password'] ?? '';

        if ( ! $token || ! $password ) {
            wp_send_json_error(['message'=>'Token und Passwort erforderlich.']); return;
        }

        if ( strlen($password) < 6 ) {
            wp_send_json_error(['message'=>'Passwort muss mindestens 6 Zeichen lang sein.']); return;
        }

        // Find customer by token
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['customers']} WHERE reset_token=%s AND reset_expires > NOW()",
            $token
        ));

        if ( ! $customer ) {
            wp_send_json_error(['message'=>'Ungültiger oder abgelaufener Reset-Link.']); return;
        }

        // Update password and clear token
        $wpdb->update(
            $this->tables['customers'],
            [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'reset_token' => null,
                'reset_expires' => null,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $customer->id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        wp_send_json_success(['message'=>'Passwort wurde erfolgreich zurückgesetzt. Sie können sich jetzt anmelden.']);
    }

    // Shortcode for password reset page
    public function shortcode_password_reset( $atts ) {
        // Get token from URL
        $token = isset($_GET['valcode_reset']) ? sanitize_text_field($_GET['valcode_reset']) : '';

        if (!$token) {
            return '<div class="va-booking-form va-card"><p class="va-error">Ungültiger Reset-Link.</p></div>';
        }

        // Verify token exists and is not expired
        global $wpdb;
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tables['customers']} WHERE reset_token=%s AND reset_expires > NOW()",
            $token
        ));

        if (!$customer) {
            return '<div class="va-booking-form va-card"><p class="va-error">Dieser Reset-Link ist ungültig oder abgelaufen.</p></div>';
        }

        ob_start();
        ?>
        <div class="va-booking-form va-card">
            <h2>Neues Passwort setzen</h2>
            <p>Geben Sie Ihr neues Passwort ein für: <strong><?php echo esc_html($customer->email); ?></strong></p>

            <div class="va-field">
                <label for="va_new_password">Neues Passwort *</label>
                <input type="password" id="va_new_password" placeholder="Mindestens 6 Zeichen" />
            </div>

            <div class="va-field">
                <label for="va_new_password_confirm">Passwort bestätigen *</label>
                <input type="password" id="va_new_password_confirm" placeholder="Passwort wiederholen" />
            </div>

            <button type="button" id="va_reset_password_btn" class="va-btn">Passwort zurücksetzen</button>

            <div id="va_reset_result" style="margin-top: 16px;"></div>
        </div>

        <script>
        (function(){
            var btn = document.getElementById('va_reset_password_btn');
            var result = document.getElementById('va_reset_result');
            var newPass = document.getElementById('va_new_password');
            var confirmPass = document.getElementById('va_new_password_confirm');

            if(btn){
                btn.addEventListener('click', function(){
                    if(!newPass.value || !confirmPass.value){
                        result.textContent = 'Bitte beide Felder ausfüllen.';
                        result.className = 'va-msg err';
                        return;
                    }

                    if(newPass.value !== confirmPass.value){
                        result.textContent = 'Passwörter stimmen nicht überein.';
                        result.className = 'va-msg err';
                        return;
                    }

                    if(newPass.value.length < 6){
                        result.textContent = 'Passwort muss mindestens 6 Zeichen lang sein.';
                        result.className = 'va-msg err';
                        return;
                    }

                    btn.disabled = true;
                    btn.textContent = 'Wird gespeichert...';

                    var fd = new FormData();
                    fd.append('action', 'valcode_customer_reset_password');
                    fd.append('nonce', '<?php echo wp_create_nonce('valcode_appoint_nonce'); ?>');
                    fd.append('token', '<?php echo esc_js($token); ?>');
                    fd.append('password', newPass.value);

                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: fd
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if(res && res.success){
                            result.textContent = res.data.message || 'Passwort wurde zurückgesetzt!';
                            result.className = 'va-msg ok';
                            newPass.value = '';
                            confirmPass.value = '';
                            setTimeout(function(){
                                window.location.href = '<?php echo home_url(); ?>';
                            }, 2000);
                        } else {
                            result.textContent = res && res.data && res.data.message ? res.data.message : 'Fehler beim Zurücksetzen.';
                            result.className = 'va-msg err';
                            btn.disabled = false;
                            btn.textContent = 'Passwort zurücksetzen';
                        }
                    })
                    .catch(function(){
                        result.textContent = 'Fehler beim Zurücksetzen.';
                        result.className = 'va-msg err';
                        btn.disabled = false;
                        btn.textContent = 'Passwort zurücksetzen';
                    });
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }
}

Valcode_Appoint::instance();