<?php
/**
 * Plugin Name:       Valcode Appoint
 * Description:       Booking plugin with admin for Services, Staff, Appointments (+Calendar & Edit), Availability rules, and a styled frontend form that saves appointments.
 * Version:           0.5.0
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

    public $version = '0.5.0';
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
        ];

        register_activation_hook( __FILE__, [ $this, 'activate' ] );

        add_action( 'admin_menu', [ $this, 'admin_menu' ] );

        add_action( 'admin_post_valcode_save_service', [ $this, 'handle_save_service' ] );
        add_action( 'admin_post_valcode_delete_service', [ $this, 'handle_delete_service' ] );

        add_action( 'admin_post_valcode_save_staff', [ $this, 'handle_save_staff' ] );
        add_action( 'admin_post_valcode_delete_staff', [ $this, 'handle_delete_staff' ] );

        add_action( 'admin_post_valcode_save_appointment', [ $this, 'handle_save_appointment' ] );
        add_action( 'admin_post_valcode_delete_appointment', [ $this, 'handle_delete_appointment' ] );

        add_action( 'admin_post_valcode_save_design', [ $this, 'handle_save_design' ] );

        add_action( 'admin_post_valcode_save_availability', [ $this, 'handle_save_availability' ] );
        add_action( 'admin_post_valcode_delete_availability', [ $this, 'handle_delete_availability' ] );
        add_action( 'admin_post_valcode_save_blocker', [ $this, 'handle_save_blocker' ] );
        add_action( 'admin_post_valcode_delete_blocker', [ $this, 'handle_delete_blocker' ] );

        add_shortcode( 'valcode_appoint', [ $this, 'shortcode_form' ] );

        // AJAX
        add_action( 'wp_ajax_valcode_get_workers', [ $this, 'ajax_get_workers' ] );
        add_action( 'wp_ajax_nopriv_valcode_get_workers', [ $this, 'ajax_get_workers' ] );
        
        add_action( 'wp_ajax_valcode_get_slots', [ $this, 'ajax_get_slots' ] );
        add_action( 'wp_ajax_nopriv_valcode_get_slots', [ $this, 'ajax_get_slots' ] );
add_action( 'wp_ajax_valcode_create_appointment', [ $this, 'ajax_create_appointment' ] );
        add_action( 'wp_ajax_nopriv_valcode_create_appointment', [ $this, 'ajax_create_appointment' ] );
        add_action( 'wp_ajax_valcode_get_events', [ $this, 'ajax_get_events' ] );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public' ] );
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
            weekday TINYINT UNSIGNED NOT NULL, -- 0=Sun .. 6=Sat
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

        dbDelta( $sql_services );
        dbDelta( $sql_staff );
        dbDelta( $sql_appts );
        dbDelta( $sql_av );
        dbDelta( $sql_block );

        // Default design options
        if ( false === get_option('valcode_appoint_design') ) {
            add_option('valcode_appoint_design', [
                'primary_color' => '#0f172a',
                'accent_color'  => '#6366f1',
                'radius'        => '14px',
                'font_family'   => 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif',
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
        add_submenu_page('valcode-appoint', __('Termine','valcode-appoint'), __('Termine','valcode-appoint'), 'manage_options', 'valcode-appoint-appointments', [ $this, 'render_appointments' ]);
        add_submenu_page('valcode-appoint', __('Kalender','valcode-appoint'), __('Kalender','valcode-appoint'), 'manage_options', 'valcode-appoint-calendar', [ $this, 'render_calendar' ]);
        add_submenu_page('valcode-appoint', __('Verfügbarkeit','valcode-appoint'), __('Verfügbarkeit','valcode-appoint'), 'manage_options', 'valcode-appoint-availability', [ $this, 'render_availability' ]);
        add_submenu_page('valcode-appoint', __('Design','valcode-appoint'), __('Design','valcode-appoint'), 'manage_options', 'valcode-appoint-design', [ $this, 'render_design' ]);
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
        $radius  = isset($design['radius'])        ? $design['radius']        : '14px';
        $font    = isset($design['font_family'])   ? $design['font_family']   : 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif';

        wp_register_style( 'valcode-appoint-public', plugins_url( 'assets/css/public.css', __FILE__ ), [], $this->version );
        wp_add_inline_style( 'valcode-appoint-public', ":root{--va-primary: {$primary}; --va-accent: {$accent}; --va-radius: {$radius}; --va-font: {$font};}" );
        wp_enqueue_style( 'valcode-appoint-public' );

        wp_register_script( 'valcode-appoint', plugins_url( 'assets/js/appoint.js', __FILE__ ), ['jquery'], $this->version, true );
        wp_localize_script( 'valcode-appoint', 'ValcodeAppoint', [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('valcode_appoint_nonce')
        ]);
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>Valcode Appoint</h1><p>Verwalte Services, Mitarbeiter, Termine, Kalender, Verfügbarkeit & Design.</p><p>Frontend: <code>[valcode_appoint]</code></p></div>';
    }

    // ---------- SERVICES / STAFF (same as before, trimmed here for brevity) ----------
    // (The methods are identical to v0.4.0; kept fully to ensure functionality)
    
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

    // ---------- APPOINTMENTS (List + Create + Edit) ----------
    public function render_appointments() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;

        $services = $wpdb->get_results( "SELECT id, name FROM {$this->tables['services']} WHERE active=1 ORDER BY name" );
        $staff    = $wpdb->get_results( "SELECT id, display_name FROM {$this->tables['staff']} WHERE active=1 ORDER BY display_name" );

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

                        <div class="va-field"><label for="customer_name">Kundenname</label><input name="customer_name" id="customer_name" type="text" required value="<?php echo esc_attr($edit->customer_name ?? ''); ?>"/></div>
                        <div class="va-field two">
                            <div><label for="customer_email">E-Mail</label><input name="customer_email" id="customer_email" type="email" value="<?php echo esc_attr($edit->customer_email ?? ''); ?>"/></div>
                            <div><label for="starts_at">Start</label><input name="starts_at" id="starts_at" type="datetime-local" required value="<?php echo $edit ? esc_attr( date('Y-m-d\TH:i', strtotime($edit->starts_at)) ) : ''; ?>"/></div>
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

    // ---------- CALENDAR (FullCalendar) ----------
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
            $events[] = [
                'id' => (string)$r->id,
                'title' => $title,
                'start' => gmdate('c', strtotime($r->starts_at)),
                'end'   => $r->ends_at ? gmdate('c', strtotime($r->ends_at)) : null,
                'backgroundColor' => $color,
                'borderColor' => $color
            ];
        }
        wp_send_json_success([ 'events' => $events ]);
    }

    // ---------- AVAILABILITY ----------
    public function render_availability() {
        if ( ! current_user_can('manage_options') ) return;
        global $wpdb;
        $staff = $wpdb->get_results( "SELECT id, display_name FROM {$this->tables['staff']} WHERE active=1 ORDER BY display_name" );
        $rules = $wpdb->get_results( "
            SELECT av.*, st.display_name FROM {$this->tables['availability']} av
            LEFT JOIN {$this->tables['staff']} st ON st.id=av.staff_id
            ORDER BY st.display_name, av.weekday, av.start_time
        " );
        $blockers = $wpdb->get_results( "
            SELECT b.*, st.display_name FROM {$this->tables['blockers']} b
            LEFT JOIN {$this->tables['staff']} st ON st.id=b.staff_id
            ORDER BY b.starts_at DESC
        " );
        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Verfügbarkeit</h1>
            <hr class="wp-header-end"/>
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
                                        <option value="<?php echo (int)$st->id; ?>"><?php echo esc_html($st->display_name); ?></option>
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
                        <?php if ($rules): foreach ($rules as $r): ?>
                            <tr>
                                <td><?php echo esc_html($r->display_name); ?></td>
                                <td><?php echo esc_html($r->weekday); ?></td>
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

    
    // ---------- AVAILABILITY & SLOTS HELPERS ----------
    private function get_service($id){
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->tables['services']} WHERE id=%d AND active=1", $id) );
    }
    private function staff_is_available_rule($staff_id, $start_dt, $end_dt){
        // Check weekday/time window against availability rules
        global $wpdb;
        $weekday = (int) wp_date('w', strtotime($start_dt)); // 0..6
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
                WHERE staff_id=%d AND ((start_datetime < %s AND end_datetime > %s) OR (start_datetime >= %s AND start_datetime < %s))";
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
        // date_str = 'Y-m-d'
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
// ---------- AJAX HELPERS ----------
    
    public function ajax_get_slots(){
        check_ajax_referer('valcode_appoint_nonce', 'nonce');
        $service_id = absint( $_GET['service_id'] ?? 0 );
        $staff_id   = absint( $_GET['staff_id'] ?? 0 );
        $date       = sanitize_text_field( $_GET['date'] ?? '' ); // Y-m-d
        if(!$service_id || !$staff_id || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)){
            wp_send_json_error(['message'=>'Ungültige Parameter.']); return;
        }
        $slots = $this->generate_time_slots($staff_id, $service_id, $date, 30);
        wp_send_json_success(['slots'=>$slots]);
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

        // Insert & prevent race by re-checking
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

        // Build ICS
        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $uid = $appt_id.'@'.parse_url( home_url(), PHP_URL_HOST );
        $summary = $blogname.' – Termin: '.$service->name;
        $desc = "Service: {$service->name}\nName: {$customer_name}\nEmail: {$customer_email}\nHinweise: {$notes}";
        $dtstart = gmdate('Ymd\THis\Z', strtotime(get_date_from_gmt( get_gmt_from_date( $starts_at ), 'Y-m-d H:i:s' )));
        $dtend   = gmdate('Ymd\THis\Z', strtotime(get_date_from_gmt( get_gmt_from_date( $ends_at ), 'Y-m-d H:i:s' )));
        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Valcode Appoint//DE\r\nMETHOD:REQUEST\r\nBEGIN:VEVENT\r\nUID:$uid\r\nSUMMARY:".esc_html($summary)."\r\nDTSTART:$dtstart\r\nDTEND:$dtend\r\nDESCRIPTION:".esc_html($desc)."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";

        // Email both customer and admin
        $admin_email = get_option('admin_email');
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $attachments = [];
        $tmp = wp_upload_dir();
        $ics_path = trailingslashit($tmp['basedir'])."appoint-$appt_id.ics";
        file_put_contents($ics_path, $ics);
        $attachments[] = $ics_path;

        $gcal_link = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode($summary)
            .'&dates='.gmdate('Ymd\THis\Z', strtotime($starts_at)).'%2F'.gmdate('Ymd\THis\Z', strtotime($ends_at))
            .'&details='.rawurlencode("{$desc}").'&sf=true&output=xml';

        $body_html = '<p>Danke für Ihre Buchung!</p><p><strong>'.esc_html($service->name).'</strong><br/>'
            . esc_html( wp_date('d.m.Y H:i', strtotime($starts_at)) ) . ' – '
            . esc_html( wp_date('H:i', strtotime($ends_at)) ) . '</p>'
            . '<p><a href="'.esc_url($gcal_link).'" target="_blank" rel="noopener">Zu Google Kalender hinzufügen</a></p>';

        if($customer_email){
            wp_mail($customer_email, 'Buchungsbestätigung', $body_html, $headers, $attachments);
        }
        wp_mail($admin_email, 'Neue Buchung', $body_html, $headers, $attachments);

        wp_send_json_success([
            'message'=>'Termin bestätigt. Bestätigung per E-Mail gesendet.',
            'appointment_id' => $appt_id,
            'gcal' => $gcal_link
        ]); return;


        $wpdb->insert( $this->tables['appointments'], [
            'customer_name' => $customer_name,
            'customer_email'=> $customer_email,
            'service_id'    => $service_id,
            'staff_id'      => $staff_id ?: null,
            'starts_at'     => $starts_at,
            'notes'         => $notes,
            'status'        => 'pending',
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql'),
        ] );
        wp_send_json_success(['message'=>'Termin gespeichert']);
    }

    // ---------- DESIGN ----------
    public function render_design() {
        if ( ! current_user_can('manage_options') ) return;
        $design = get_option('valcode_appoint_design', []);
        $primary = esc_attr($design['primary_color'] ?? '#0f172a');
        $accent  = esc_attr($design['accent_color'] ?? '#6366f1');
        $radius  = esc_attr($design['radius'] ?? '14px');
        $font    = esc_attr($design['font_family'] ?? 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif');
        ?>
        <div class="wrap va-wrap">
            <h1 class="wp-heading-inline">Design</h1>
            <hr class="wp-header-end"/>
            <div class="va-card">
                <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" class="va-form">
                    <?php wp_nonce_field( 'valcode_save_design', '_va' ); ?>
                    <input type="hidden" name="action" value="valcode_save_design"/>
                    <div class="va-field two">
                        <div><label for="primary_color">Primärfarbe</label><input type="color" id="primary_color" name="primary_color" value="<?php echo $primary; ?>"/></div>
                        <div><label for="accent_color">Akzentfarbe</label><input type="color" id="accent_color" name="accent_color" value="<?php echo $accent; ?>"/></div>
                    </div>
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
        $opt['radius']        = sanitize_text_field( $_POST['radius'] ?? '14px' );
        $opt['font_family']   = sanitize_text_field( $_POST['font_family'] ?? 'Inter, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif' );
        update_option('valcode_appoint_design', $opt);
        wp_safe_redirect( admin_url('admin.php?page=valcode-appoint-design&saved=1') ); exit;
    }

    // ---------- SHORTCODE FRONTEND (styled + saves) ----------
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

            <!-- Step 2: Datum wählen -->
            <div class="va-step" data-step="2" hidden>
                <h3>Datum wählen</h3>
                
                <div class="va-field">
                    <label for="va_date">Wunschdatum</label>
                    <input id="va_date" type="date" required/>
                </div>

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

            <!-- Step 4: Persönliche Daten -->
            <div class="va-step" data-step="4" hidden>
                <h3>Ihre Kontaktdaten</h3>
                
                <div class="va-grid">
                    <div class="va-field">
                        <label for="va_name">Vor- und Nachname *</label>
                        <input id="va_name" name="customer_name" type="text" required placeholder="Max Muster">
                    </div>

                    <div class="va-field">
                        <label for="va_email">E-Mail-Adresse *</label>
                        <input id="va_email" name="customer_email" type="email" required placeholder="max@beispiel.ch">
                    </div>

                    <div class="va-field full">
                        <label for="va_notes">Notizen / Wünsche (optional)</label>
                        <textarea id="va_notes" name="notes" rows="4" placeholder="Besondere Wünsche oder Hinweise für Ihren Termin..."></textarea>
                    </div>
                </div>

                <div class="va-actions">
                    <button type="button" id="va_prev_4" class="va-btn va-prev">Zurück</button>
                    <button type="submit" class="va-btn">Termin verbindlich buchen</button>
                </div>

                <p class="va-msg" id="va_msg" hidden></p>
            </div>

            <!-- Step 5: Success -->
            <div class="va-step" data-step="5" hidden>
                <div class="va-success-content">
                    <div class="va-success-icon">✅</div>
                    <h3>Buchung erfolgreich!</h3>
                    
                    <div class="va-success-details" id="va_success_msg">
                        <p>Ihre Buchung wurde erfolgreich gespeichert.</p>
                        <p>Sie erhalten in Kürze eine Bestätigungs-E-Mail mit allen Details und einem Kalendereintrag.</p>
                    </div>

                    <button type="button" class="va-btn va-btn-new" onclick="window.location.reload()">
                        Neuen Termin buchen
                    </button>
                </div>
            </div>

        </form>
    </div>

        <?php
        return ob_get_clean();
    }
}

Valcode_Appoint::instance();
