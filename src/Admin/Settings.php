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
                $css_file = FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/css/admin.css';
                $js_file  = FL_FORMULAR_LOGGING_PLUGIN_DIR . 'assets/admin/js/admin.js';

                wp_enqueue_style(
                    'fl-admin-style',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/admin/css/admin.css',
                    array(),
                    file_exists($css_file) ? filemtime($css_file) : FL_FORMULAR_LOGGING_VERSION
                );

                wp_enqueue_script(
                    'fl-admin-script',
                    FL_FORMULAR_LOGGING_PLUGIN_URL . 'assets/admin/js/admin.js',
                    array(),
                    file_exists($js_file) ? filemtime($js_file) : FL_FORMULAR_LOGGING_VERSION,
                    true
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

            // Notification settings
            register_setting(
                self::SETTINGS_GROUP,
                'fl_notify_enabled',
                array(
                'type' => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default' => false,
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_notify_email',
                array(
                'type' => 'string',
                'sanitize_callback' => array($this, 'sanitize_notify_email'),
                'default' => '',
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_notify_slack_webhook',
                array(
                'type' => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default' => '',
            )
            );

            register_setting(
                self::SETTINGS_GROUP,
                'fl_notify_cooldown',
                array(
                'type' => 'number',
                'sanitize_callback' => array($this, 'sanitize_notify_cooldown'),
                'default' => 15,
            )
            );

            add_settings_section(
                'fl_notify_section',
                'Fehler-Benachrichtigungen',
                array($this, 'render_notify_description'),
                self::PAGE_SLUG
            );

            add_settings_field(
                'fl_notify_enabled',
                'Benachrichtigungen aktivieren',
                array($this, 'render_notify_enabled_field'),
                self::PAGE_SLUG,
                'fl_notify_section'
            );

            add_settings_field(
                'fl_notify_email',
                'E-Mail-Empfänger (eine pro Zeile)',
                array($this, 'render_notify_email_field'),
                self::PAGE_SLUG,
                'fl_notify_section'
            );

            add_settings_field(
                'fl_notify_slack_webhook',
                'Slack Webhook URL',
                array($this, 'render_notify_slack_field'),
                self::PAGE_SLUG,
                'fl_notify_section'
            );

            add_settings_field(
                'fl_notify_cooldown',
                'Cooldown (Minuten)',
                array($this, 'render_notify_cooldown_field'),
                self::PAGE_SLUG,
                'fl_notify_section'
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

            $notify_enabled     = (bool) get_option('fl_notify_enabled', false);
            $notify_email       = (string) get_option('fl_notify_email', '');
            $notify_slack       = (string) get_option('fl_notify_slack_webhook', '');
            $notify_cooldown    = max(1, (int) get_option('fl_notify_cooldown', 15));
            $rl_enabled         = (bool) get_option('fl_rate_limit_enabled', false);
            $rl_threshold       = (int) get_option('fl_rate_limit_threshold', 20);
            $rl_duration        = (int) get_option('fl_rate_limit_duration', 60);
            $rl_action          = get_option('fl_rate_limit_action', 'temporary');
            $form_pages         = (string) get_option(self::OPTION_KEY, '');
            $retention_days     = get_option('fl_retention_days', 30);
            $custom_log_dir     = (string) get_option('fl_custom_log_dir', '');
            $blocked_ips        = get_option('fl_permanently_blocked_ips', array());
            ?>
            <div class="wrap sf-wrap">
                <h1>Einstellungen</h1>

                <form method="post" action="options.php" class="sf-settings-form">
                    <?php settings_fields(self::SETTINGS_GROUP); ?>

                    <?php /* ---- Card: Allgemein ---- */ ?>
                    <div class="sf-card">
                        <div class="sf-card-header">
                            <div class="sf-card-icon sf-card-icon--blue">
                                <span class="dashicons dashicons-admin-settings"></span>
                            </div>
                            <div>
                                <h2>Allgemein</h2>
                                <p>Auf welchen Seiten wird geloggt und wie lange werden die Logs aufbewahrt.</p>
                            </div>
                        </div>
                        <div class="sf-card-body">
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-form-pages">Formularseiten</label>
                                    <span class="sf-setting-hint">Eine URL oder Pfad pro Zeile</span>
                                </div>
                                <div class="sf-setting-input">
                                    <textarea id="fl-form-pages" name="<?php echo esc_attr(self::OPTION_KEY); ?>" rows="6" class="sf-textarea-code"><?php echo esc_textarea($form_pages); ?></textarea>
                                    <span class="sf-hint">z.B. <code>/kontakt</code> oder <code>https://example.com/formular</code></span>
                                </div>
                            </div>
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-retention">Speicherdauer</label>
                                    <span class="sf-setting-hint">Automatische Löschung alter Logs</span>
                                </div>
                                <div class="sf-setting-input sf-setting-inline">
                                    <input type="number" id="fl-retention" name="fl_retention_days" value="<?php echo esc_attr((string) $retention_days); ?>" min="0.001" step="any" class="sf-input-short" />
                                    <span class="sf-unit">Tage</span>
                                </div>
                            </div>
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-log-dir">Log-Verzeichnis</label>
                                    <span class="sf-setting-hint">Optional — Standard: <code>wp-content/uploads/form-logs/</code></span>
                                </div>
                                <div class="sf-setting-input">
                                    <input type="text" id="fl-log-dir" name="fl_custom_log_dir" value="<?php echo esc_attr($custom_log_dir); ?>" class="sf-input-wide" placeholder="/var/www/virtual/user/logs" />
                                    <span class="sf-hint">Absoluter Pfad auf dem Server. Auf Nginx-Servern empfohlen, da <code>.htaccess</code> dort nicht greift.</span>
                                </div>
                            </div>
                            <div class="sf-setting-row sf-setting-row--last">
                                <div class="sf-setting-label">
                                    <label for="fl-github-token">GitHub Access Token</label>
                                    <span class="sf-setting-hint">Für automatische Updates</span>
                                </div>
                                <div class="sf-setting-input">
                                    <?php if (defined('FL_GITHUB_UPDATE_TOKEN') && FL_GITHUB_UPDATE_TOKEN !== '') : ?>
                                        <input type="password" class="sf-input-wide" value="••••••••••••••••" disabled />
                                        <span class="sf-hint sf-hint--info">Token wird aus der <code>wp-config.php</code> Konstante <code>FL_GITHUB_UPDATE_TOKEN</code> geladen.</span>
                                    <?php else : ?>
                                        <input type="password" id="fl-github-token" name="fl_github_update_token" value="<?php echo esc_attr((string) get_option('fl_github_update_token', '')); ?>" class="sf-input-wide" />
                                        <span class="sf-hint">Sicherer: <code>define('FL_GITHUB_UPDATE_TOKEN', 'ghp_...');</code> in <code>wp-config.php</code> eintragen.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php /* ---- Card: Benachrichtigungen ---- */ ?>
                    <div class="sf-card">
                        <div class="sf-card-header">
                            <div class="sf-card-icon sf-card-icon--orange">
                                <span class="dashicons dashicons-bell"></span>
                            </div>
                            <div>
                                <h2>Fehler-Benachrichtigungen</h2>
                                <p>Alert per E-Mail und/oder Slack bei kritischen System- oder Mailfehlern. Validierungs- und Captcha-Fehler lösen keinen Alert aus.</p>
                            </div>
                        </div>
                        <div class="sf-card-body">
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label>Benachrichtigungen</label>
                                </div>
                                <div class="sf-setting-input">
                                    <label class="sf-toggle">
                                        <input type="checkbox" name="fl_notify_enabled" value="1" <?php checked($notify_enabled, true); ?> />
                                        <span class="sf-toggle-slider"></span>
                                        <span class="sf-toggle-label">Aktivieren</span>
                                    </label>
                                </div>
                            </div>
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-notify-email">E-Mail-Empfänger</label>
                                    <span class="sf-setting-hint">Eine Adresse pro Zeile</span>
                                </div>
                                <div class="sf-setting-input">
                                    <textarea id="fl-notify-email" name="fl_notify_email" rows="3" class="sf-textarea-code"><?php echo esc_textarea($notify_email); ?></textarea>
                                </div>
                            </div>
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-notify-slack">Slack Webhook URL</label>
                                    <span class="sf-setting-hint">Incoming Webhook des Slack-Kanals</span>
                                </div>
                                <div class="sf-setting-input">
                                    <input type="url" id="fl-notify-slack" name="fl_notify_slack_webhook" value="<?php echo esc_attr($notify_slack); ?>" class="sf-input-wide" placeholder="https://hooks.slack.com/services/..." />
                                </div>
                            </div>
                            <div class="sf-setting-row sf-setting-row--last">
                                <div class="sf-setting-label">
                                    <label for="fl-notify-cooldown">Cooldown</label>
                                    <span class="sf-setting-hint">Mindestabstand zwischen Alerts</span>
                                </div>
                                <div class="sf-setting-input sf-setting-inline">
                                    <input type="number" id="fl-notify-cooldown" name="fl_notify_cooldown" value="<?php echo esc_attr((string) $notify_cooldown); ?>" min="1" class="sf-input-short" />
                                    <span class="sf-unit">Minuten</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php /* ---- Card: Rate Limiting ---- */ ?>
                    <div class="sf-card">
                        <div class="sf-card-header">
                            <div class="sf-card-icon sf-card-icon--red">
                                <span class="dashicons dashicons-shield"></span>
                            </div>
                            <div>
                                <h2>Rate Limiting & Sicherheit</h2>
                                <p>IPs automatisch sperren, wenn sie zu viele Fehler in kurzer Zeit produzieren.</p>
                            </div>
                        </div>
                        <div class="sf-card-body">
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label>Rate Limiting</label>
                                </div>
                                <div class="sf-setting-input">
                                    <label class="sf-toggle">
                                        <input type="checkbox" name="fl_rate_limit_enabled" value="1" <?php checked($rl_enabled, true); ?> />
                                        <span class="sf-toggle-slider"></span>
                                        <span class="sf-toggle-label">IP-Blockierung aktivieren</span>
                                    </label>
                                </div>
                            </div>
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-rl-threshold">Fehler-Schwellenwert</label>
                                    <span class="sf-setting-hint">Innerhalb von 5 Minuten</span>
                                </div>
                                <div class="sf-setting-input sf-setting-inline">
                                    <input type="number" id="fl-rl-threshold" name="fl_rate_limit_threshold" value="<?php echo esc_attr((string) $rl_threshold); ?>" min="1" class="sf-input-short" />
                                    <span class="sf-unit">Fehler</span>
                                </div>
                            </div>
                            <div class="sf-setting-row">
                                <div class="sf-setting-label">
                                    <label for="fl-rl-duration">Sperrdauer</label>
                                    <span class="sf-setting-hint">Gilt nur bei temporärer Sperre</span>
                                </div>
                                <div class="sf-setting-input sf-setting-inline">
                                    <input type="number" id="fl-rl-duration" name="fl_rate_limit_duration" value="<?php echo esc_attr((string) $rl_duration); ?>" min="1" class="sf-input-short" />
                                    <span class="sf-unit">Minuten</span>
                                </div>
                            </div>
                            <div class="sf-setting-row sf-setting-row--last">
                                <div class="sf-setting-label">
                                    <label>Aktion bei Überschreitung</label>
                                </div>
                                <div class="sf-setting-input">
                                    <div class="sf-radio-group">
                                        <label class="sf-radio-option <?php echo $rl_action === 'temporary' ? 'sf-radio-option--active' : ''; ?>">
                                            <input type="radio" name="fl_rate_limit_action" value="temporary" <?php checked($rl_action, 'temporary'); ?> />
                                            <span class="sf-radio-title">Temporär</span>
                                            <span class="sf-radio-desc">Für die eingestellte Sperrdauer</span>
                                        </label>
                                        <label class="sf-radio-option <?php echo $rl_action === 'permanent' ? 'sf-radio-option--active' : ''; ?>">
                                            <input type="radio" name="fl_rate_limit_action" value="permanent" <?php checked($rl_action, 'permanent'); ?> />
                                            <span class="sf-radio-title">Permanent</span>
                                            <span class="sf-radio-desc">Manuelles Entsperren nötig</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="sf-settings-footer">
                        <button type="submit" class="sf-btn-primary">Einstellungen speichern</button>
                    </div>

                </form>

                <?php /* ---- Blockierte IPs ---- */ ?>
                <?php if (!empty($blocked_ips) && is_array($blocked_ips)) : ?>
                <div class="sf-card sf-card--blocked-ips">
                    <div class="sf-card-header">
                        <div class="sf-card-icon sf-card-icon--red">
                            <span class="dashicons dashicons-dismiss"></span>
                        </div>
                        <div>
                            <h2>Permanent blockierte IPs</h2>
                            <p><?php echo count($blocked_ips); ?> IP-Adresse<?php echo count($blocked_ips) !== 1 ? 'n' : ''; ?> blockiert</p>
                        </div>
                    </div>
                    <div class="sf-card-body sf-card-body--flush">
                        <table class="sf-blocked-table">
                            <thead>
                                <tr>
                                    <th>IP-Adresse</th>
                                    <th>Blockiert am</th>
                                    <th>Grund</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blocked_ips as $ip => $data) :
                                    $time   = isset($data['time']) ? wp_date('d.m.Y H:i', $data['time']) : '–';
                                    $reason = isset($data['reason']) ? $data['reason'] : (isset($data['page']) ? 'Seite: ' . $data['page'] : '–');
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($ip); ?></code></td>
                                    <td><?php echo esc_html($time); ?></td>
                                    <td><?php echo esc_html($reason); ?></td>
                                    <td><button type="button" class="sf-btn-unblock fl-unblock-ip" data-ip="<?php echo esc_attr($ip); ?>" data-nonce="<?php echo wp_create_nonce('fl_unblock_ip'); ?>">Entsperren</button></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
            <?php
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
            if (defined('FL_GITHUB_UPDATE_TOKEN') && FL_GITHUB_UPDATE_TOKEN !== '') {
                echo '<input type="password" class="regular-text" value="••••••••••••••••" disabled />';
                echo '<p class="description" style="color:#2271b1;"><strong>Token wird aus der <code>wp-config.php</code> geladen</strong> (Konstante <code>FL_GITHUB_UPDATE_TOKEN</code>). Das Feld hier wird ignoriert.</p>';
                return;
            }

            $value = (string)get_option('fl_github_update_token', '');

            echo '<input type="password" name="fl_github_update_token" value="' . esc_attr($value) . '" class="regular-text" />';
            echo '<p class="description">Falls dieses Plugin in einem privaten GitHub Repository liegt, füge hier deinen <a href="https://github.com/settings/tokens" target="_blank">Personal Access Token (classic)</a> mit dem <code>repo</code> Scope ein, damit automatische Updates installiert werden können.<br>';
            echo '<strong>Sicherer:</strong> Token als Konstante in der <code>wp-config.php</code> definieren: <code>define(\'FL_GITHUB_UPDATE_TOKEN\', \'ghp_...\');</code></p>';
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

        // ---------------------------------------------------------------
        // Notification render methods
        // ---------------------------------------------------------------

        public function render_notify_description()
        {
            echo '<p>Sende eine Benachrichtigung per E-Mail und/oder Slack, wenn ein kritischer System- oder Mailfehler geloggt wird. Nutzer-/Validierungsfehler und Captcha-Fehler lösen <strong>keinen</strong> Alert aus. Ein Cooldown verhindert Benachrichtigungs-Spam bei mehreren Fehlern in kurzer Zeit.</p>';
        }

        public function render_notify_enabled_field()
        {
            $value = get_option('fl_notify_enabled', false);
            echo '<input type="checkbox" name="fl_notify_enabled" value="1" ' . checked($value, true, false) . ' />';
            echo ' <label for="fl_notify_enabled">Fehler-Benachrichtigungen aktivieren</label>';
        }

        public function render_notify_email_field()
        {
            $value = (string) get_option('fl_notify_email', '');
            echo '<textarea name="fl_notify_email" rows="4" cols="50" class="large-text code">' . esc_textarea($value) . '</textarea>';
            echo '<p class="description">Eine E-Mail-Adresse pro Zeile. Alle eingetragenen Adressen erhalten die Benachrichtigung.</p>';
        }

        public function render_notify_slack_field()
        {
            $value = (string) get_option('fl_notify_slack_webhook', '');
            echo '<input type="url" name="fl_notify_slack_webhook" value="' . esc_attr($value) . '" class="regular-text" placeholder="https://hooks.slack.com/services/..." />';
            echo '<p class="description">Slack Incoming Webhook URL. Den Webhook in deinem Slack-Workspace unter <em>Apps → Incoming Webhooks</em> anlegen.</p>';
        }

        public function render_notify_cooldown_field()
        {
            $value = max(1, (int) get_option('fl_notify_cooldown', 15));
            echo '<input type="number" name="fl_notify_cooldown" value="' . esc_attr((string) $value) . '" class="small-text" style="width:80px;" min="1" />';
            echo '<p class="description">Mindestabstand zwischen zwei Benachrichtigungen in Minuten (Standard: 15). Verhindert Benachrichtigungs-Spam bei vielen Fehlern hintereinander.</p>';
        }

        public function sanitize_notify_email($value)
        {
            $value = is_scalar($value) ? (string) $value : '';
            $lines = preg_split('/\r\n|\r|\n/', $value);
            $clean = array();
            foreach ((array) $lines as $line) {
                $addr = sanitize_email(trim((string) $line));
                if ($addr !== '') {
                    $clean[] = $addr;
                }
            }
            return implode(PHP_EOL, $clean);
        }

        public function sanitize_notify_cooldown($value)
        {
            $v = (int) $value;
            return $v >= 1 ? $v : 15;
        }
    }
}