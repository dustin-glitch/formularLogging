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
- **Dateipfad für Logs (Absolut)**: Erlaubt es, den standardmäßigen Speicherort (`wp-content/uploads/form-logs/`) mit einem absoluten Server-Pfad zu überschreiben (z.B. `/var/www/virtual/user/logs/`). Sehr nützlich für Server wie Mittwald mit speziellen Nginx/Ordner-Restriktionen.
- **GitHub Access Token (für Updates)**: Wenn dieses Repository privat geschaltet ist, kann hier ein Token hinterlegt werden, um nahtlose, automatische WordPress-Updates im Hintergrund zu erlauben.

## 🛠 Setup & Integration (Schritt-für-Schritt)

Sobald das Plugin installiert ist, musst du es für deine Formularseiten scharf schalten.

1. **Seiten registrieren**: Gehe zu **Formular Logs -> Einstellungen** und trage in das Textfeld die URLs ein, auf denen sich deine Formulare befinden. 
   - Beispiele: `/kontakt`, `/jetzt-bewerben` oder `https://meine-domain.de/support`.
   - Das Plugin lädt seine (super-leichten) Erkennungsskripte *nur* auf diesen spezifischen Seiten, um die WordPress-Performance anderswo nicht zu beeinträchtigen.
2. **Formulare abschicken**: Konfiguriere dein Formular (z.B. in YOOtheme Pro) wie gewohnt. 
   - Sobald ein Nutzer das Formular absendet, schaltet sich die Javascript-Logik (oder beim Senden die WP Mail SMTP Hook-Logik) dieses Plugins dazwischen.
   - Alle Submit-Events, Feld-Validierungsfehler und Antworten vom Server werden automatisch über APIs geloggt.
3. **Logs prüfen**: Sende ein Test-Formular ab. Navigiere anschließend zu **Formular Logs -> Alle Logs**.
   - Du siehst nun den neuen Block ("Request ID") für deine Test-Anfrage.
   - Wenn auf der Frontend-Seite z.B. das FriendlyCaptcha fehlschlägt, wird der Backend-Log dir sofort ein präzises gelbes Warn-Badge ("Spamschutz") anzeigen.
   - Mit einem Klick auf **JSON ansehen** kannst du jederzeit den Inhalt deines Test-Requests (entschlüsselt) betrachten.

## 🔒 Sicherheit & .htaccess

Da die geloggten Formulardaten in CSV-Dateien gespeichert werden, greifen strenge Sicherheitsmechanismen:

- **Apache Server:** Das Plugin kümmert sich um alles selbst! Sobald das Verzeichnis `wp-content/uploads/form-logs/` erstellt wird, wird automatisch eine restriktive `.htaccess`-Datei (`Deny from all`) und eine `index.php` erzeugt. Dadurch ist direkter Dateizugriff von außen unmöglich.
- **Nginx Server:** Da Nginx `.htaccess`-Dateien naturgemäß ignoriert, **musst** du folgenden Block in deine Nginx Server-Konfiguration (`server { ... }`) oder Plesk-Zusatzrichtlinien eintragen:

```nginx
# Formular Logging Plugin: Schutz des Log-Verzeichnisses
location ^~ /wp-content/uploads/form-logs/ {
    deny all;
    return 403;
}
```

## 👨‍💻 Entwicklerhinweise

Das Plugin ist Namespace-strukturiert (`Signalfeuer\FormularLogs\*`) und nutzt einen internen SPL-Autoloader beim Einbinden der `src/`-Ordnerklassen:

- `src/Admin`: Verwaltung der Settings-API und des Log-Darstellungs-UI-Renderings.
- `src/Core`: `RequestContext`-Builder und Event- / Hook-Registrierung (`Plugin.php`).
- `src/Loggers`: Spezielle Adapter wie `AjaxLogger` und `MailLogger`.
- `src/Storage`: Datenspeicherungslogik, Dateischutz und Cleanup (`LogStorage.php`).
