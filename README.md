# Signalfeuer Formular Logging

Ein modernes, datenschutzkonformes WordPress-Plugin zum detaillierten Protokollieren und Debuggen von Formular-Einsendungen und E-Mail-Versandpipelines.

Besonders geeignet für Fehleranalysen bei YOOtheme Pro Form-Elementen, YOOessentials und Drittanbieter-Mailern wie WP Mail SMTP.

---

## ✨ Features

### 📋 End-to-End Formular-Logging
- Automatische Protokollierung aller Formular-Einsendungen, Validierungsfehler und Server-Antworten
- Jede Anfrage erhält eine eindeutige **Request-ID** für lückenlose Nachverfolgung über alle Pipeline-Schritte
- Unterstützung für HTML5-Validierung, Captcha-Fehler (reCAPTCHA, hCaptcha, Turnstile, FriendlyCaptcha) und Honeypot-Erkennung
- Gesonderte Upload-Überwachung für Datei-Felder (`uploadLogger.js`)

### 🚀 Native YOOtheme & YOOessentials Integration
- Erkennt UIkit-Events und protokolliert `yooessentials-form:submit`, `submitted`, `submission-error` und `validation-error` direkt an der Quelle
- Beherrschung serverseitiger YOOtheme `form.submission`-Events inkl. Fehlererkennung
- Optionale Hook-Anbindung an YOOessentials-Formulardaten (`essentials_form_*`)

### ✉️ WP Mail & SMTP Support
- Fängt die gesamte Mail-Pipeline ab: `wp_mail` → `phpmailer_init` → `wp_mail_succeeded` / `wp_mail_failed`
- Tiefgehende SMTP-Diagnose: Mailer-Typ, Host, Port, Auth und Verschlüsselung werden mitgeloggt
- Spezial-Hook für **WP Mail SMTP** Plugin-Fehler (`wp_mail_smtp_mailcatcher_send_failed`)

### 🛡️ Rate Limiting & IP-Blockierung
- **Temporäre Sperre**: Blockiert IPs automatisch nach zu vielen Fehlern innerhalb eines 5-Minuten-Fensters
- **Permanente Sperre**: Optional dauerhafte Blockierung bei Schwellenwert-Überschreitung
- Konfigurierbare Parameter: Fehler-Schwellenwert, Sperrdauer, Sperr-Aktion (temporär/permanent)
- Verwaltungstabelle im Backend zum Einsehen und Entsperren blockierter IPs
- **Admin-Schutz**: Eingeloggte Administratoren, das Admin-Dashboard und `wp-login.php` werden nie blockiert

### 🔐 100% DSGVO / GDPR-Konform
- **AES-256-CBC Verschlüsselung**: Sensible JSON-Payloads (Benutzereingaben) werden vor dem Speichern verschlüsselt. Nur das Admin-Dashboard entschlüsselt on-the-fly
- **IP-Anonymisierung**: Die letzte Stelle der IP wird maskiert (`192.168.1.***`) — für IPv6 analog
- **Verzeichnis-Absicherung**: `.htaccess` (`Deny from all`) und `index.php` werden automatisch erzeugt
- **Konfigurierbare Speicherdauer**: Automatische Löschung nach einstellbarer Dauer (Standard: 30 Tage)
- **Manuelle Bereinigung**: Sofort-Löschung alter Logs per Button im Admin

### 📊 Dashboard & Admin-Interface
- **Dashboard-Widget** mit Tagesübersicht (Anfragen, Erfolge, Fehler) und interaktivem **Chart.js-Liniendiagramm** der letzten 7 Tage
- Übersichtliche Log-Tabelle mit Gruppierung nach Request-ID
- **Fehlerklassifizierung** mit farbigen Badges:
  - 🟢 *Erfolgreich / Info*
  - 🟡 *Nutzer/Validierung (feldname)* — zeigt betroffene Felder
  - 🟡 *Spamschutz (hCaptcha / reCAPTCHA / …)* — erkennt Captcha-Typ automatisch
  - 🔴 *System-/Mailerfehler*
  - 🔴 *JS Fehler*
- **JSON-Modal** mit strukturierter Zusammenfassung: Validierungsfehler werden als lesbare Liste dargestellt, bevor der rohe JSON angezeigt wird
- Filter nach Datum, Request-ID, Status und Event-Typ
- CSV-Download der Tages-Logs

### 🔄 Automatische Updates
- Integrierter **Plugin Update Checker (PUC)** für nahtlose Updates direkt über GitHub
- Unterstützung für private Repos via GitHub Access Token

---

## 📦 Installation

1. Lade den Plugin-Ordner `formular-logging` herunter
2. Kopiere ihn nach `wp-content/plugins/`
3. Aktiviere das Plugin unter **Plugins** im WordPress-Backend
4. Navigiere zu **Formular Logs → Einstellungen** und konfiguriere deine Formularseiten

---

## ⚙️ Einstellungen

Unter **Formular Logs → Einstellungen**:

### Frontend Logging Seiten
| Einstellung | Beschreibung |
|-------------|-------------|
| **Formularseiten** | URLs/Slugs der Seiten mit Formularen (eine pro Zeile). Das Plugin lädt die Logger-Skripte *nur* auf diesen Seiten. Beispiele: `/kontakt`, `/jetzt-bewerben` |
| **Speicherdauer** | Wie lange CSV-Logs gespeichert werden, bevor sie automatisch gelöscht werden (Standard: 30 Tage, Kommazahlen für Tests möglich) |
| **Dateipfad für Logs** | Optional: Absoluter Server-Pfad statt `wp-content/uploads/form-logs/`. Nützlich bei restriktiven Hostern |
| **GitHub Access Token** | Personal Access Token für automatische Updates bei privaten Repos |

### Rate Limiting & Sicherheit
| Einstellung | Beschreibung |
|-------------|-------------|
| **Rate Limiting aktivieren** | Schaltet die IP-Blockierung bei zu vielen Fehlern ein |
| **Fehler-Schwellenwert** | Anzahl Fehler in 5 Min. bevor eine IP blockiert wird (Standard: 20) |
| **Sperrdauer** | Minuten einer temporären Sperre (Standard: 60) |
| **Aktion bei Überschreitung** | Temporär (für die eingestellte Dauer) oder permanent (manuelles Entsperren nötig) |

---

## 🛠 Setup & Integration

### 1. Seiten registrieren
Trage unter **Einstellungen** die URLs deiner Formularseiten ein:
```
/kontakt
/jetzt-bewerben
https://meine-domain.de/support
```
Die Logger-Skripte werden ausschließlich auf diesen Seiten geladen.

### 2. Formulare nutzen
Konfiguriere dein Formular (z.B. in YOOtheme Pro) wie gewohnt. Das Plugin schaltet sich automatisch dazwischen:
- **Frontend**: JavaScript erkennt Submit-Events, Validierungsfehler und Server-Antworten
- **Backend**: PHP-Hooks fangen Mail-Versand, SMTP-Konfiguration und Fehler ab

### 3. Logs prüfen
Navigiere zu **Formular Logs → Alle Logs**:
- Jede Anfrage erscheint als gruppierter Block mit Request-ID
- Badges zeigen auf einen Blick: Erfolg, Validierungsfehler oder Systemfehler
- Klicke auf **JSON ansehen** um Payload-Details (verschlüsselt gespeichert, on-the-fly entschlüsselt) zu sehen

---

## 🔒 Sicherheit

### Apache Server
Das Plugin erstellt automatisch eine restriktive `.htaccess` und `index.php` im Log-Verzeichnis. **Kein weiterer Handlungsbedarf.**

### Nginx Server
Da Nginx `.htaccess` ignoriert, füge diesen Block in deine Server-Konfiguration ein:
```nginx
# Formular Logging: Log-Verzeichnis absichern
location ^~ /wp-content/uploads/form-logs/ {
    deny all;
    return 403;
}
```

### IP-Erkennung
Die IP-Adresse wird primär über `REMOTE_ADDR` ermittelt. Falls ein Reverse-Proxy vorgeschaltet ist, werden sekundär `X-Forwarded-For` und `Client-IP` Header ausgewertet.

---

## 👨‍💻 Architektur

```
formular-logging/
├── formular-logging.php          # Plugin-Bootstrap, Hooks, Update-Checker
├── autoloader.php                # SPL-Autoloader für Signalfeuer\FormularLogs\*
├── uninstall.php                 # Deinstallation (Logs werden bewusst behalten)
├── src/
│   ├── Admin/
│   │   ├── AdminUI.php           # Log-Tabelle, Dashboard-Widget, CSV-Download
│   │   └── Settings.php          # Einstellungsseite, Rate-Limit-Konfiguration
│   ├── Core/
│   │   ├── Plugin.php            # Singleton, Hook-Registrierung, Rate-Limiting
│   │   ├── Crypto.php            # AES-256-CBC Ver-/Entschlüsselung
│   │   └── RequestContext.php    # IP, User-Agent, Request-ID, Payload-Handling
│   ├── Loggers/
│   │   ├── AjaxLogger.php        # Frontend-AJAX-Endpoint
│   │   └── MailLogger.php        # wp_mail / phpmailer / WP Mail SMTP Hooks
│   └── Storage/
│       └── LogStorage.php        # CSV-Dateien, Cleanup, Verzeichnisschutz
└── assets/
    ├── js/
    │   ├── logger.js             # Frontend-Formular-Erkennung & Event-Logging
    │   └── uploadLogger.js       # Datei-Upload-Überwachung
    └── admin/
        ├── js/admin.js           # JSON-Modal, Dashboard-Chart
        └── css/admin.css         # Signalfeuer Admin-Branding
```

---

## 📄 Lizenz

GPLv2 or later
