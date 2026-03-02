<?php
namespace Signalfeuer\FormularLogs\Admin;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Signalfeuer\FormularLogs\Admin\Settings')) {
    class Settings
    {
        const OPTION_KEY = 'fl_form_pages';
        const SETTINGS_GROUP = 'fl_settings_group';
        const PAGE_SLUG = 'formular-logging-settings';

        public function __construct()
        {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        }

        public function enqueue_admin_assets($hook_suffix)
        {
            if ($hook_suffix === 'formular-logs_page_' . self::PAGE_SLUG) {
                wp_enqueue_style(
                    'fl-admin-style',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/admin/css/admin.css',
                    array(),
                    file_exists(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css') ? filemtime(FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css') : FL_FORMULAR_LOGGING_VERSION
                );
            }
        }

        public function register_admin_page()
        {
            add_submenu_page(
                'formular-logs',
                'Einstellungen',
                'Einstellungen',
                'manage_options',
                self::PAGE_SLUG,
                array($this, 'render_settings_page')
            );
        }

        public function register_settings()
        {
            register_setting(
                self::SETTINGS_GROUP,
                self::OPTION_KEY,
                array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_form_pages'),
                'default' => '',
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_retention_days',
                array(
                'type' => 'number',
                'sanitize_callback' => array($this, 'sanitize_retention_days'),
                'default' => 30,
            )
            );

            add_settings_section(
                'fl_pages_section',
                'Frontend Logging Seiten',
                array($this, 'render_section_description'),
                self::PAGE_SLUG
            );

            add_settings_field(
                'fl_form_pages',
                'Formularseiten (eine pro Zeile)',
                array($this, 'render_form_pages_field'),
                self::PAGE_SLUG,
                'fl_pages_section'
            );

            add_settings_field(
                'fl_retention_days',
                'Speicherdauer (in Tagen)',
                array($this, 'render_retention_field'),
                self::PAGE_SLUG,
                'fl_pages_section'
            );
        }

        public function render_settings_page()
        {
            if (!current_user_can('manage_options')) {
                return;
            }

            echo '<div class="wrap sf-wrap">';
            echo '<h1>Formular Logging Einstellungen</h1>';
            echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; margin-top:20px;">';
            echo '<form method="post" action="options.php">';
            settings_fields(self::SETTINGS_GROUP);
            do_settings_sections(self::PAGE_SLUG);
            submit_button('Einstellungen speichern', '', '', false, array('class' => 'sf-btn-primary'));
            echo '</form>';
            echo '</div>';
            echo '</div>';
        }

        public function render_section_description()
        {
            echo '<p>Definiere die Seiten, auf denen die Logger-Skripte geladen werden (je Zeile ein relativer Pfad wie <code>/kontakt</code> oder eine volle URL).</p>';
        }

        public function render_form_pages_field()
        {
            $value = (string)get_option(self::OPTION_KEY, '');

            echo '<textarea name="' . esc_attr(self::OPTION_KEY) . '" rows="10" cols="70" class="large-text code" style="border:1px solid #ccd0d4; border-radius:4px; padding:10px; font-family:monospace;">' . esc_textarea($value) . '</textarea>';
            echo '<p class="description">Beispiele: <code>/kontakt</code>, <code>/angebot-anfragen</code>, <code>https://example.com/landing/formular</code></p>';
        }

        public function render_retention_field()
        {
            $value = get_option('fl_retention_days', 30);
            if ($value <= 0) {
                $value = 7;
            }

            echo '<input type="number" name="fl_retention_days" value="' . esc_attr((string)$value) . '" class="small-text" style="width: 80px;" min="0.001" step="any" />';
            echo '<p class="description">Wie viele Tage sollen die Log-CSV-Dateien auf dem Server gespeichert bleiben bevor sie gelöscht werden? (Zum Testen sind auch Kommazahlen wie 0.001 erlaubt).</p>';
        }

        public function render_empty_pages_notice()
        {
            if (!is_admin() || !current_user_can('manage_options')) {
                return;
            }

            if ($this->has_form_pages_configured()) {
                return;
            }

            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if ($screen && strpos((string)$screen->id, 'update') !== false) {
                return;
            }

            $url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
            echo '<div class="notice notice-warning"><p>';
            echo 'Formular Logging ist aktiv, aber es sind keine Formularseiten konfiguriert. ';
            echo '<a href="' . esc_url($url) . '">Jetzt Seiten eintragen</a>.';
            echo '</p></div>';
        }

        public function has_form_pages_configured()
        {
            return !empty($this->get_normalized_form_paths());
        }

        public function should_enqueue_for_current_request()
        {
            $paths = $this->get_normalized_form_paths();
            if (empty($paths)) {
                return false;
            }

            $request_uri = isset($_SERVER['REQUEST_URI']) ? (string)wp_unslash($_SERVER['REQUEST_URI']) : '';
            $current_path = $this->normalize_entry($request_uri);
            if ($current_path === '') {
                return false;
            }

            return in_array($current_path, $paths, true);
        }

        public function sanitize_form_pages($value)
        {
            $value = is_scalar($value) ? (string)$value : '';
            $lines = preg_split('/\r\n|\r|\n/', $value);
            $clean = array();

            foreach ((array)$lines as $line) {
                $normalized = $this->normalize_entry((string)$line);
                if ($normalized === '') {
                    continue;
                }
                $clean[$normalized] = $normalized;
            }

            return implode(PHP_EOL, array_values($clean));
        }

        public function sanitize_retention_days($value)
        {
            $value = str_replace(',', '.', (string)$value);
            return floatval($value);
        }

        public function get_normalized_form_paths()
        {
            $raw = (string)get_option(self::OPTION_KEY, '');
            if ($raw === '') {
                return array();
            }

            $lines = preg_split('/\r\n|\r|\n/', $raw);
            $paths = array();

            foreach ((array)$lines as $line) {
                $normalized = $this->normalize_entry((string)$line);
                if ($normalized === '') {
                    continue;
                }
                $paths[$normalized] = $normalized;
            }

            return array_values($paths);
        }

        private function normalize_entry($entry)
        {
            $entry = trim((string)$entry);
            if ($entry === '') {
                return '';
            }

            if (preg_match('#^https?://#i', $entry)) {
                $path = wp_parse_url($entry, PHP_URL_PATH);
                if (!is_string($path)) {
                    return '';
                }
            }
            else {
                $path = wp_parse_url($entry, PHP_URL_PATH);
                if (!is_string($path) || $path === '') {
                    $path = $entry;
                }
            }

            $path = trim((string)$path);
            if ($path === '') {
                return '';
            }

            if (strpos($path, '/') !== 0) {
                $path = '/' . $path;
            }

            $path = preg_replace('#/+#', '/', $path);
            $path = untrailingslashit((string)$path);

            return $path === '' ? '/' : $path;
        }
    }
}