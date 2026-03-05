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

            register_setting(
                self::SETTINGS_GROUP,
                'fl_custom_log_dir',
                array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_github_update_token',
                array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default' => '',
            )
            );

            // Rate Limiting Settings
            register_setting(
                self::SETTINGS_GROUP,
                'fl_rate_limit_enabled',
                array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_rate_limit_threshold',
                array(
                'type' => 'number',
                'sanitize_callback' => 'absint',
                'default' => 20,
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_rate_limit_duration',
                array(
                'type' => 'number',
                'sanitize_callback' => 'absint',
                'default' => 60,
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_rate_limit_action',
                array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_rate_limit_action'),
                'default' => 'temporary',
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

            add_settings_field(
                'fl_custom_log_dir',
                'Dateipfad für Logs (Absolut)',
                array($this, 'render_custom_log_dir_field'),
                self::PAGE_SLUG,
                'fl_pages_section'
            );

            add_settings_field(
                'fl_github_update_token',
                'GitHub Access Token (für Updates)',
                array($this, 'render_github_token_field'),
                self::PAGE_SLUG,
                'fl_pages_section'
            );

            add_settings_section(
                'fl_rate_limit_section',
                'Rate Limiting & Sicherheit',
                array($this, 'render_rate_limit_description'),
                self::PAGE_SLUG
            );

            add_settings_field(
                'fl_rate_limit_enabled',
                'Rate Limiting aktivieren',
                array($this, 'render_rate_limit_enabled_field'),
                self::PAGE_SLUG,
                'fl_rate_limit_section'
            );

            add_settings_field(
                'fl_rate_limit_threshold',
                'Fehler-Schwellenwert',
                array($this, 'render_rate_limit_threshold_field'),
                self::PAGE_SLUG,
                'fl_rate_limit_section'
            );

            add_settings_field(
                'fl_rate_limit_duration',
                'Sperrdauer (Minuten)',
                array($this, 'render_rate_limit_duration_field'),
                self::PAGE_SLUG,
                'fl_rate_limit_section'
            );

            add_settings_field(
                'fl_rate_limit_action',
                'Aktion bei Überschreitung',
                array($this, 'render_rate_limit_action_field'),
                self::PAGE_SLUG,
                'fl_rate_limit_section'
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

            $blocked_ips = get_option('fl_permanently_blocked_ips', array());
            if (!empty($blocked_ips) && is_array($blocked_ips)) {
                echo '<div style="background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px; margin-top:20px;">';
                echo '<h2>Permanent blockierte IPs</h2>';
                echo '<table class="wp-list-table widefat striped">';
                echo '<thead><tr><th>IP-Adresse</th><th>Blockiert am</th><th>Grund</th><th>Aktion</th></tr></thead>';
                echo '<tbody>';
                foreach ($blocked_ips as $ip => $data) {
                    $time = isset($data['time']) ? wp_date('d.m.Y H:i:s', $data['time']) : '-';
                    $reason = isset($data['reason']) ? $data['reason'] : (isset($data['page']) ? 'Seite: ' . $data['page'] : '-');
                    echo '<tr>';
                    echo '<td>' . esc_html($ip) . '</td>';
                    echo '<td>' . esc_html($time) . '</td>';
                    echo '<td>' . esc_html($reason) . '</td>';
                    echo '<td><a href="#" class="button fl-unblock-ip" data-ip="' . esc_attr($ip) . '" data-nonce="' . wp_create_nonce('fl_unblock_ip') . '">Entsperren</a></td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';

                echo '<script>
                    jQuery(document).ready(function ($) {
                        $(".fl-unblock-ip").on("click", function (e) {
                            e.preventDefault();
                            var $btn = $(this);
                            if (!confirm("Soll diese IP-Adresse wirklich entsperrt werden?")) {
                                return;
                            }
                            $.post(ajaxurl, {
                                action: "fl_unblock_ip",
                                ip: $btn.data("ip"),
                                nonce: $btn.data("nonce")
                            })
                                .done(function (response) {
                                    if (response.success) {
                                        $btn.closest("tr").fadeOut(300, function () {
                                            $(this).remove();
                                        });
                                    } else {
                                        alert("Fehler: " + (response.data || "Unbekannt"));
                                    }
                                })
                                .fail(function () {
                                    alert("Ein Serverfehler ist aufgetreten.");
                                });
                        });
                    });
                </script>';
                echo '</div>';
            }

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

        public function render_github_token_field()
        {
            $value = (string)get_option('fl_github_update_token', '');

            echo '<input type="password" name="fl_github_update_token" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '<p class="description">Falls dieses Plugin in einem privaten GitHub Repository liegt, füge hier deinen <a href="https://github.com/settings/tokens" target="_blank">Personal Access Token (classic)</a> mit dem <code>repo</code> Scope ein, damit automatische Updates installiert werden können.</p>';
        }

        public function render_custom_log_dir_field()
        {
            $value = (string)get_option('fl_custom_log_dir', '');

            echo '<input type="text" name="fl_custom_log_dir" value="' . esc_attr($value) . '" class="regular-text" style="width: 100%; max-width: 600px;" placeholder="/var/www/virtual/user/logs" />';
            echo '<p class="description">Optional: Überschreibe den automatischen Dateipfad (<code>wp-content/uploads/form-logs/</code>) mit einem festen absoluten Pfad auf deinem Server. Besonders nützlich bei restriktiven Hostern wie Mittwald oder komplexen Nginx Setups.</p>';
        }

        public function render_rate_limit_description()
        {
            echo '<p>Blockiere IP-Adressen temporär, wenn von diesen zu viele fehlerhafte Anfragen gesendet werden (z.B. Spam-Bots, die Captchas triggern).</p>';
        }

        public function render_rate_limit_enabled_field()
        {
            $value = get_option('fl_rate_limit_enabled', false);
            echo '<input type="checkbox" name="fl_rate_limit_enabled" value="1" ' . checked($value, true, false) . ' />';
            echo ' <label for="fl_rate_limit_enabled">Spam-Schutz / IP-Blockierung aktivieren</label>';
        }

        public function render_rate_limit_threshold_field()
        {
            $value = get_option('fl_rate_limit_threshold', 20);
            echo '<input type="number" name="fl_rate_limit_threshold" value="' . esc_attr((string)$value) . '" class="small-text" style="width: 80px;" min="1" />';
            echo '<p class="description">Wie viele Fehler dürfen von einer IP-Adresse in einem 5-Minuten-Fenster auftreten, bevor die IP blockiert wird?</p>';
        }

        public function render_rate_limit_duration_field()
        {
            $value = get_option('fl_rate_limit_duration', 60);
            echo '<input type="number" name="fl_rate_limit_duration" value="' . esc_attr((string)$value) . '" class="small-text" style="width: 80px;" min="1" />';
            echo '<p class="description">Für wie viele Minuten soll die IP blockiert werden?</p>';
        }

        public function render_rate_limit_action_field()
        {
            $value = get_option('fl_rate_limit_action', 'temporary');
            echo '<fieldset>';
            echo '<label><input type="radio" name="fl_rate_limit_action" value="temporary" ' . checked($value, 'temporary', false) . ' /> Temporäre Sperre</label><br>';
            echo '<label><input type="radio" name="fl_rate_limit_action" value="permanent" ' . checked($value, 'permanent', false) . ' /> Permanente Sperre</label>';
            echo '</fieldset>';
            echo '<p class="description">Soll die IP bei Erreichen des Schwellenwerts nur für die eingestellte Dauer (temporär) oder für immer (permanent) blockiert werden? Permanente Blocks erscheinen untem in der Tabelle.</p>';
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

            $url = admin_url('admin.php?page=' . self::PAGE_SLUG);
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

        public function sanitize_rate_limit_action($value)
        {
            $allowed = array('temporary', 'permanent');
            $value = sanitize_text_field((string)$value);
            return in_array($value, $allowed, true) ? $value : 'temporary';
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