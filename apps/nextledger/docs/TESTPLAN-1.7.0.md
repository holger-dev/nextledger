# NextLedger 1.7.0 — Testplan

Manueller Abnahmetest vor dem Release. Reihenfolge ist so gewählt, dass spätere Tests auf den Daten der früheren aufbauen. Geschätzte Dauer: 45–60 Minuten.

## 0) Vorbereitung

- [ ] `docker compose exec -T --user www-data nextcloud php /var/www/html/occ upgrade` ausgeführt (Migration 0024 läuft)
- [ ] `docker compose exec -T --user www-data nextcloud php -r 'opcache_reset();'` ausgeführt
- [ ] Browser: Hard-Reload (⌘⇧R), App-Version im Quelltext/Netzwerk-Tab prüfen (app.js neu geladen)
- [ ] Migration prüfen: `occ migrations:status nextledger` → 0024 = migrated
- [ ] Background-Job registriert: `occ background-job:list | grep -i recurring` (oder Tabelle oc_jobs) → `RecurringExpensesJob` vorhanden

**Testdaten-Basis** (falls nicht vorhanden):
- [ ] Aktive Firma mit: Name, Adresse, USt-IdNr. `DE123456789`, Country `DE`, Währung EUR, Sprache DE
- [ ] Aktives Wirtschaftsjahr 2026
- [ ] Ein Kunde mit Adresse, Country `DE`, USt-IdNr., Kontakt-E-Mail **und aktiviertem Rechnungsempfänger-Switch**
- [ ] Ein Vorgang für diesen Kunden
- [ ] E-Mailverhalten: Admin-SMTP oder Nextcloud Mail eingerichtet (für Versandtests)

---

## 1) Bugfixes

### 1.1 — #19 „fällig bis" ausblenden
- [ ] Rechnung **ohne** Fälligkeitsdatum anlegen → PDF erzeugen → keine „fällig bis"-Zeile, kein Strich-Platzhalter
- [ ] Rechnung **mit** Fälligkeitsdatum → PDF → Zeile „fällig bis: <Datum>" erscheint wie gewohnt

### 1.2 — #22 Korrespondenz-Umbruch
- [ ] Vorgang öffnen → Korrespondenz/Notiz mit sehr langem Text ohne Leerzeichen anlegen (z. B. 300× „x")
- [ ] Tabelle bleibt im Layout, Text bricht um, kein horizontales Scrollen

### 1.3 — #21 Mail-Anhang (blocked file type)
- [ ] Firma → E-Mail-Anhang = „Nur PDF" → Rechnung direkt versenden
- [ ] Mail kommt an, Anhang heißt sauber `Rechnung-<Nr>.pdf` (kein `nextledger-…`-Präfix, keine Doppelpunkte/Doppel-Endungen)
- [ ] Gleiches mit einem Angebot wiederholen

### 1.4 — #17 ZUGFeRD-Versand (libxml)
- [ ] Firma → Rechnungsausgabe = **ZUGFeRD EN16931**, E-Mail-Anhang = **PDF und ZUGFeRD-XML**
- [ ] Rechnung direkt versenden → Mail kommt an mit **zwei** Anhängen (`…-zugferd.pdf` + `…-zugferd.xml`)
- [ ] Nextcloud-Log gegenprüfen: **kein** `simplexml_load_file`-Fehler mehr
- [ ] E-Mail-Anhang = „Nur ZUGFeRD-XML" → Versand → nur die XML kommt an

---

## 2) Quick Wins

### 2.1 — #13 Editierbare E-Mail-Vorschau
- [ ] Rechnung → Senden-Dialog: Empfänger-, Betreff- und Nachricht-Felder sind editierbar und vorbefüllt
- [ ] Empfänger löschen → roter Hinweis erscheint, Senden-Button deaktiviert
- [ ] Zweite Adresse per Komma ergänzen → Button aktiv → Versand → Mail geht an beide, mit editiertem Betreff/Text
- [ ] Danach neuen Senden-Dialog öffnen → wieder die Standard-Vorlage (Änderung war nur einmalig)

### 2.2 — #15 Grußformel/Signatur
- [ ] Einstellungen → Texte: Grußformel `Beste Grüße aus Köln`, Signatur-Name `Team Buchhaltung` → speichern
- [ ] Rechnungs-PDF und Angebots-PDF: beide zeigen die neue Grußformel + Signatur
- [ ] Beide Felder leeren → speichern → PDFs zeigen wieder „Mit freundlichen Grüßen" + Firmeninhaber

---

## 3) Features

### 3.1 — #24 MwSt pro Position
- [ ] Neue Rechnung: Position A 100 € ohne eigenen Satz, Position B 50 € mit `7` im USt-Feld
- [ ] Summenblock im Formular zeigt zwei Steuerzeilen (19 %: 19,00 € / 7 %: 3,50 €), Gesamt 172,50 €
- [ ] PDF: USt-Spalte in der Tabelle sichtbar, zwei Steuerzeilen im Summenblock, Gesamt 172,50 €
- [ ] ZUGFeRD-XML herunterladen → zwei `ApplicableTradeTax`-Gruppen (19 + 7), Summen korrekt
- [ ] Gegenprobe: Rechnung ohne eigene Positionssätze → wie bisher eine Steuerzeile, keine USt-Spalte
- [ ] Kleinunternehmer-Firma: USt-Spalte/-Feld erscheinen nicht

### 3.2 — #14 Nummernkreise
- [ ] Firma → Nummernkreis `RE-{YYYY}-{SEQ4}` → speichern
- [ ] Zwei neue Rechnungen anlegen → Nummern `RE-2026-0001`, `RE-2026-0002`
- [ ] Feld leeren → speichern → nächste Rechnung wieder `JJJJMMTT-####`
- [ ] Ungültiges Schema eingeben (z. B. ohne `{SEQ}`) → speichern → Fallback auf Standardformat (kein Fehler/Crash)
- [ ] Angebote sind unverändert (weiterhin Standardformat)

### 3.3 — #16 Belege zu Ausgaben
- [ ] Wirtschaftsjahr → Ausgabe anlegen → speichern → Ausgabe **bearbeiten** → Beleg-Upload sichtbar
- [ ] PDF hochladen → Pfad wird angezeigt; in Nextcloud **Dateien** unter `NextLedger/<Firma>/<WJ>/Belege` liegt die Datei
- [ ] Zweiten Beleg hochladen → alte Datei bleibt in Files erhalten, Pfad zeigt auf die neue (Suffix `_1` o. ä.)
- [ ] „Beleg entfernen" → Verknüpfung weg, Datei bleibt in Files
- [ ] Negativtest: .exe/.zip hochladen → saubere Fehlermeldung

### 3.4 — #23 Wiederkehrende Ausgaben
- [ ] Ausgabe „Hosting" 10 €, Datum **vor 2 Monaten**, Wiederholung „Monatlich" → speichern
- [ ] Cron manuell anstoßen: `docker compose exec -T --user www-data nextcloud php /var/www/html/cron.php` (ggf. 2×)
- [ ] Liste neu laden → 2 zusätzliche automatische Buchungen (je +1 Monat), Vorlage selbst unverändert
- [ ] Cron erneut → **keine** Duplikate
- [ ] „Wiederholen bis" in der Vergangenheit setzen → Cron → keine neuen Buchungen

### 3.5 — #18/#20 Dokument-Layout
- [ ] Firma → Dokument-Layout: Telefon + USt-IdNr. einschalten → Rechnungs-PDF zeigt beide im Kopf
- [ ] E-Mail ausschalten → PDF ohne E-Mail-Zeile
- [ ] Position „Links oben (Logo rechts)" → PDF: Firmendaten links, Logo rechts
- [ ] Schriftgröße „Groß" → PDF sichtbar größer; zurück auf „Normal"
- [ ] Angebots-PDF: gleiche Layout-Einstellungen greifen dort ebenfalls
- [ ] Zweite Firma (falls vorhanden): hat eigene, unabhängige Layout-Einstellungen

---

## 4) Regression (Kernfunktionen kurz gegenchecken)

- [ ] Angebot anlegen → PDF → per Mail senden
- [ ] Rechnung aus Angebot (Abschlag/Schluss) → Summen korrekt
- [ ] Rechnung „als bezahlt" markieren → Einnahme im Wirtschaftsjahr aktualisiert
- [ ] GÜB-PDF (beide Varianten) erzeugen
- [ ] Logo-Upload + drei Größen unverändert funktionsfähig (1.6.3-Feature)
- [ ] Firmenwechsel: Daten sauber getrennt, aktive Firma in der Navigation korrekt
- [ ] Sprache EN: Rechnungs-PDF auf Englisch, neue Labels (VAT-Spalte) übersetzt
- [ ] Kunden-/Produkt-/Vorgangs-CRUD stichprobenartig

## 5) Abschluss

- [ ] Nextcloud-Log auf neue Errors/Warnings prüfen: `docker compose exec -T --user www-data nextcloud php /var/www/html/occ log:tail 50`
- [ ] ZUGFeRD-XML einer Mischsatz-Rechnung durch Mustang/KoSIT-Validator schicken (optional, empfohlen)
- [ ] `vendor/bin/phpunit` in `apps/nextledger` grün
- [ ] CHANGELOG/README gegengelesen

**Bei Fehlschlag:** Issue-Nummer + Schritt notieren — dann fixe ich gezielt nach.
