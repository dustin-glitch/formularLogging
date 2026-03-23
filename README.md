# Formular Logging

WordPress-Plugin zum Debuggen von Formular-Submissions und E-Mail-Versand. Entwickelt von Signalfeuer für den internen Agentur-Einsatz.

---

## Was es macht

- Loggt jeden Formular-Submit End-to-End: Frontend-Event → Server → Mail-Versand
- Gruppiert alle Log-Einträge einer Anfrage unter einer **Request-ID**
- Erkennt und klassifiziert Fehler automatisch: Validierung, Captcha, Mail-Fehler, JS-Fehler
- Benachrichtigt per **E-Mail und/oder Slack** bei kritischen System- und Mailfehlern
- Verschlüsselt Formulardaten (AES-256-CBC) vor dem Speichern

Unterstützt: YOOtheme Pro, YOOessentials, WP Mail SMTP und alle Standard-WordPress-Formulare.

---

## Installation

1. Ordner `formular-logging` nach `wp-content/plugins/` kopieren
2. Plugin im WordPress-Backend aktivieren
3. Unter **Formular Logs → Einstellungen** die Formularseiten eintragen

---

## Einstellungen

### Allgemein
| Einstellung | Beschreibung |
|---|---|
| Formularseiten | Seiten auf denen die Logger-Skripte geladen werden (eine URL/Pfad pro Zeile) |
| Speicherdauer | Tage bis CSV-Logs automatisch gelöscht werden (Standard: 30) |
| Dateipfad für Logs | Optionaler absoluter Pfad statt `wp-content/uploads/form-logs/` |
| GitHub Access Token | Für automatische Updates — besser als Konstante in `wp-config.php` setzen (siehe unten) |

### Fehler-Benachrichtigungen
| Einstellung | Beschreibung |
|---|---|
| Benachrichtigungen aktivieren | Sendet Alert bei kritischen System-/Mailfehlern |
| E-Mail-Empfänger | Eine Adresse pro Zeile |
| Slack Webhook URL | Incoming Webhook URL des Slack-Kanals |
| Cooldown | Mindestabstand zwischen zwei Benachrichtigungen in Minuten (Standard: 15) |

Validierungs- und Captcha-Fehler lösen **keinen** Alert aus — nur echte System-/Mailfehler.

### Rate Limiting
| Einstellung | Beschreibung |
|---|---|
| Rate Limiting aktivieren | Blockiert IPs bei zu vielen Fehlern |
| Fehler-Schwellenwert | Anzahl Fehler in 5 Min. vor Blockierung (Standard: 20) |
| Sperrdauer | Minuten der temporären Sperre (Standard: 60) |
| Aktion | Temporär oder permanent (permanente Blocks manuell entsperrbar) |

---

## GitHub-Token sicher hinterlegen

Statt das Token in der Admin-UI einzutragen, Konstante in `wp-config.php` definieren:

```php
define('FL_GITHUB_UPDATE_TOKEN', 'ghp_...');
```

Das Token wird dann nicht in der Datenbank gespeichert.

---

## Nginx: Log-Verzeichnis absichern

Auf Apache wird `.htaccess` automatisch angelegt. Auf **Nginx** muss das manuell in die Server-Konfiguration:

```nginx
location ^~ /wp-content/uploads/form-logs/ {
    deny all;
    return 403;
}
```

Alternativ unter **Einstellungen** einen Pfad außerhalb des Webroots konfigurieren.

---

## Dateistruktur

```
formular-logging/
├── formular-logging.php       # Bootstrap, Update-Checker
├── autoloader.php
├── uninstall.php
├── src/
│   ├── Core/
│   │   ├── Plugin.php         # Hooks, Rate Limiting
│   │   ├── Notifier.php       # E-Mail & Slack Benachrichtigungen
│   │   ├── Crypto.php         # AES-256-CBC
│   │   └── RequestContext.php # IP, User-Agent, Request-ID
│   ├── Loggers/
│   │   ├── AjaxLogger.php     # Frontend-AJAX-Endpoint
│   │   └── MailLogger.php     # wp_mail / phpmailer Hooks
│   ├── Storage/
│   │   └── LogStorage.php     # CSV-Dateien, Cleanup
│   └── Admin/
│       ├── AdminUI.php        # Log-Tabelle, Dashboard-Widget
│       └── Settings.php       # Einstellungsseite
└── assets/
    ├── js/
    │   ├── logger.js          # Frontend-Formular-Tracking
    │   ├── uploadLogger.js    # Datei-Upload-Tracking
    │   └── chart.umd.min.js   # Chart.js (lokal gebundelt)
    └── admin/
        ├── js/admin.js
        └── css/admin.css
```
