# Signalfeuer Formular Logging

Ein modernes, datenschutzkonformes WordPress-Plugin zum detaillierten Protokollieren und Debuggen von Formular-Einsendungen und E-Mail-Versandpipelines.

Besonders geeignet für Fehleranalysen bei YOOtheme Pro Form-Elementen, YOOessentials und Drittanbieter-Mailern wie WP Mail SMTP.

## ✨ Features

- **🚀 Native YOOtheme & YOOessentials Integration:** Erkennt Javascript-Events von UIkit und protokolliert Formulareinsendungen sowie Honeypot-Validierungsfehler direkt an der Quelle.
- **🛡️ 100% DSGVO / GDPR-Konform:**
  - **AES-256 Verschlüsselung**: Sensible JSON-Payloads (Benutzereingaben) werden vor dem Speichern mit einer 256-Bit starken AES-Verschlüsselung (`Crypto`) encodiert. Nur das Dashboard entschlüsselt on-the-fly.
  - **IP-Anonymisierung**: Die letzte Stelle der IP-Adresse des Users wird maskiert (`192.168.1.*`), bevor sie im Log landet.
  - **Verzeichnis-Absicherung**: Logs werden in `.csv`-Dateien geschrieben, die strikt durch dynamische `.htaccess`- und `index.php`-Sicherungen vor öffentlichem Zugriff geschützt sind.
  - **Konfigurierbare Speicherdauer**: Logs werden nach einer einstellbaren Dauer (Standard: 30 Tage) vollautomatisch vom Server gelöscht.
- **✉️ WP Mail SMTP Support:** Fängt tiefe Fehler im Mail-Prozess ab, lange bevor WordPress sie bemerkt. Ideal für das Debugging von SMTP-, Login- oder Connection-Timeouts.
- **📊 Modernes Admin-Interface & Signalfeuer Brand:** 
  - **Dashboard Widget**: Verpasse keine Fehler! Das Widget fasst die Logs des Tages direkt auf deinem WordPress-Start-Dashboard zusammen.
  - Elegante, übersichtliche Log-Tabelle im Backend im wunderschönen **Signalfeuer** Design.
  - **Detaillierte Fehlerklassifizierung:** Badges markieren Probleme exakt (z.B. "Spamschutz (hCaptcha)", "Nutzer/Validierung (email_field)" oder "Systemfehler").
  - **JSON-Modal**: Erlaubt das bequeme Analysieren detaillierter (und on-the-fly entschlüsselter) Daten-Payloads auf Knopfdruck.
- **🔄 Automatischer Update Checker (PUC):** Das Plugin aktualisiert sich direkt über unser GitHub-Repository. Bei privaten Repositories kann ganz einfach ein Access-Token in den Einstellungen hinterlegt werden.

## 📦 Installation

1. Lade dieses Plugin auf deinen Webserver oder in dein lokales Entwicklungsverzeichnis herunter.
2. Kopiere den Ordner `formular-logging` nach `wp-content/plugins/`.
3. Gehe im WordPress-Backend unter **Plugins** auf "Aktivieren".
4. Navigiere im Backend links zum neuen Menüpunkt **Formular Logs -> Einstellungen** und konfiguriere die Formularseiten (z. B. `/kontakt`).

## ⚙️ Einstellungen

Unter `Formular Logs -> Einstellungen` können detaillierte Konfigurationen für die Formulare vorgenommen werden:
- **Formularseiten**: Liste alle URLs oder Slugs auf (eine Zeile pro URL), auf denen die Formular-Überwachungslogik greifen soll.
- **Speicherdauer (in Tagen)**: Definiert, wie viele Tage die sicher hinterlegten CSV-Log-Dateien auf dem Server verweilen, bevor ein Cronjob diese restlos und DSGVO-konform vernichtet. 
- **GitHub Access Token (für Updates)**: Wenn dieses Repository privat geschaltet ist, kann hier ein Token hinterlegt werden, um nahtlose, automatische WordPress-Updates im Hintergrund zu erlauben.

## 👨‍💻 Entwicklerhinweise

Das Plugin ist Namespace-strukturiert (`Signalfeuer\FormularLogs\*`) und nutzt einen internen SPL-Autoloader beim Einbinden der `src/`-Ordnerklassen:

- `src/Admin`: Verwaltung der Settings-API und des Log-Darstellungs-UI-Renderings.
- `src/Core`: `RequestContext`-Builder und Event- / Hook-Registrierung (`Plugin.php`).
- `src/Loggers`: Spezielle Adapter wie `AjaxLogger` und `MailLogger`.
- `src/Storage`: Datenspeicherungslogik, Dateischutz und Cleanup (`LogStorage.php`).
