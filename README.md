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

## Setup Guide

### 1. Plugin installieren

Ordner `formular-logging` nach `wp-content/plugins/` kopieren und im WordPress-Backend aktivieren.

### 2. Formularseiten eintragen

**Formular Logs → Einstellungen → Formularseiten**

Eine URL oder ein Pfad pro Zeile. Nur auf diesen Seiten werden die Logger-Skripte geladen.

```
/kontakt
/angebot
https://example.com/bewerbung
```

Tipp: Pfade ohne Domain funktionieren auch und sind portabler.

### 3. Nginx: Log-Verzeichnis absichern

Auf Apache wird `.htaccess` automatisch angelegt. Auf **Nginx** muss das manuell in die Server-Konfiguration:

```nginx
location ^~ /wp-content/uploads/form-logs/ {
    deny all;
    return 403;
}
```

Alternativ unter Einstellungen einen **absoluten Pfad außerhalb des Webroots** konfigurieren — das ist die sicherere Variante:

```
/var/www/logs/form-logs
```

### 4. GitHub-Token hinterlegen (automatische Updates)

In `wp-config.php` definieren statt in der Admin-UI:

```php
define('FL_GITHUB_UPDATE_TOKEN', 'ghp_...');
```

Das Token wird dann nicht in der Datenbank gespeichert. Benötigt werden mindestens `repo` (Read-only) Berechtigungen.

### 5. YOOtheme Pro + YOOessentials (ZOOlanders)

Kein zusätzlicher Konfigurationsschritt nötig. Das Plugin hängt sich automatisch in den YOOtheme Event-Stack ein (`form.submission` Middleware) und loggt:

- **Server-seitig:** Formulardaten, Aktionsstatus (Erfolg/Fehler) und Meta-Daten über den YOOtheme Event-Hook
- **Frontend:** Submit-Start, Erfolg, Validierungs- und Submission-Fehler über UIkit-Events (`yooessentials-form:submit`, `yooessentials-form:submitted` etc.)

Beide Einträge werden über die **Request-ID** zusammengeführt, die das Frontend als verstecktes Feld (`fl_request_id`) in das Formular injiziert.

### 6. WP Mail SMTP

Wird automatisch erkannt. Die Hooks `wp_mail_smtp_mailcatcher_send_failed` werden zusätzlich zu den Standard-WordPress-Mail-Hooks registriert. Kein Konfigurationsschritt nötig.

### 7. Fehler-Benachrichtigungen (optional)

**Formular Logs → Einstellungen → Fehler-Benachrichtigungen**

- E-Mail-Empfänger: eine Adresse pro Zeile
- Slack: Incoming Webhook URL des Ziel-Kanals
- Cooldown sinnvoll auf ≥15 Min. lassen um Benachrichtigungs-Spam zu vermeiden

Validierungs- und Captcha-Fehler lösen **keinen** Alert aus.

### 8. Rate Limiting (optional)

**Formular Logs → Einstellungen → Rate Limiting**

Empfehlung für Produktivsysteme:
- Schwellenwert: 20 Fehler in 5 Min.
- Aktion: Temporär (60 Min.)
- Permanent nur bei bekannten Angriffs-IPs

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
