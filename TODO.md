# nextledger – TODO / Projektstruktur

GUIDELINES: https://nextcloud-vue-components.netlify.app/

## 0) Zielbild (kurz)
- Buchhaltungssoftware (Nextcloud‑UI/Vue)
- Navigation nach Nextcloud‑Guidelines (Nextcloud Vue Components)
- Startseite „Tägliches“ mit aktuellen Infos (offene Rechnungen, aktuelle Vorgänge)

## 1) Navigation & Seitenstruktur
**Navigation (links, Nextcloud‑Guidelines):**
- **Tägliches**
  - Vorgänge
  - Kunden
  - Rechnungen
  - Angebote
  - Produkte/DL
  - Wirtschaftsjahr
- **Einstellungen**
  - Firma
  - Texte
  - Steuer
  - Weiteres

**Startseite:**
- Ansicht „Tägliches“ (Dashboard) = Einstieg
- Inhalte: offene Rechnungen + aktuelle Vorgänge (später weitere KPIs)

## 2) Stammdaten / Einstellungen
### 2.1 Firma
- Firmendaten (für Rechnung/Angebot rechts oben)

### 2.2 Texte
- Standard‑Begrüßungstext **Rechnung**
- Standard‑Begrüßungstext **Angebot**
- Abschluss-/Rechnungstext (Footer‑Text)

### 2.3 Steuer
- Standard‑USt‑Satz (z. B. 19%)
- **oder** Kleinunternehmerregelung (Hinweistext)
- Auswirkung: Steuerblock unter der Gesamtsumme

### 2.4 Weiteres
- Zahlungsziel
- Kontodaten/Bankdaten (Footer)

## 3) Kernbereiche
### 3.1 Wirtschaftsjahr
- Anlegen: Name, Datum von/bis
- Einnahmen (aus Rechnungen) mit Status: **offen** / **bezahlt**
- Ausgaben: Name, Beschreibung, Betrag
- Gewinn‑Überschuss‑Berechnung (GÜB) automatisch aus Einnahmen/Ausgaben

### 3.2 Kunden
- Felder: Firma, Ansprechpartner, Straße, Hausnummer, PLZ, Stadt, E‑Mailadresse

### 3.3 Vorgänge
- Neuer Vorgang für Kunden
- Vorgangsdaten: Name, Beschreibung, Nextcloud Deck Link, Nextcloud Kollektiv Link
- Detailseite Vorgang:
  - Neue Rechnung
  - Neues Angebot
  - Neues Element

**Element (im Vorgang):**
- Name, Notiz, Anhang

### 3.4 Produkte/DL
- Produkte und Dienstleistungen frei anlegbar
- Felder: Name, Beschreibung, Stückpreis

### 3.5 Rechnungen
- Neue Rechnung im Vorgang erzeugen
- Daten werden automatisch aus Kunde + Firma übernommen
- Standard‑Begrüßungstext (aus „Texte“)
- Optionaler Zusatztext
- Positionen hinzufügen:
  - Produkt (aus Produkte/DL)
  - Dienstleistung (aus Produkte/DL)
  - Freie Position (Name, Beschreibung, Stückpreis)
  - **Menge pro Position**
- Rechenblock rechts: Zwischensumme, Gesamt, Steuerblock
- Steuerblock abhängig von „Steuer“ (USt‑Satz oder Kleinunternehmer‑Hinweis)
- Aktionen:
  - PDF exportieren
  - direkt an Kunden senden
- Rechnung wird als Einnahme im Wirtschaftsjahr erfasst
- Status: offen/bezahlt

**Nummerierung Rechnung:**
- Format: `JJJJMMTT-LAUFEND`

### 3.6 Angebote
- Analog zu Rechnung
- Standard‑Begrüßungstext aus „Texte“ (Angebot)
- Nummerierung Angebot ebenfalls `JJJJMMTT-LAUFEND`

## 4) Dokument‑Layout (Rechnung/Angebot)
- Rechts oben: Firmendaten (rechtsbündig)
- Links darunter: Kundendaten
- Überschrift fett: „Rechnung“/„Angebot“
- Begrüßungstext + optionaler Zusatztext
- Positionstabelle
- Steuerinfos
- Abschluss-/Rechnungstext
- Footer: Kontodaten + Zahlungsziel

---

# Umsetzung: Schritt‑für‑Schritt Aufgaben (für KI‑Abarbeitung)

## Phase A – Setup & Grundgerüst
1. **Projekt‑Skeleton** (Vue + Nextcloud Vue Components) inkl. Layout + App‑Shell
2. **Left‑Nav** nach Struktur (Tägliches + Einstellungen)
3. **Routing & leere Views** für alle Menü‑Punkte
4. **Basis‑Layout** für Detail‑Seiten (Listen + Detail‑Panel)

## Phase B – Datenmodell (Core Entities)
5. Datenmodelle definieren:
   - Firma, Texte, Steuer, Weiteres
   - Kunde
   - Vorgang
   - Element (Vorgang)
   - Produkt/DL
   - Rechnung
   - Angebot
   - Wirtschaftsjahr
   - Einnahme/Ausgabe
6. Nummern‑Generator (Format `JJJJMMTT-LAUFEND`)

## Phase C – Einstellungen
7. **Firma‑Maske** (CRUD)
8. **Texte‑Maske** (Begrüßung Rechnung/Angebot, Abschluss‑Text)
9. **Steuer‑Maske** (USt‑Satz vs Kleinunternehmer)
10. **Weiteres‑Maske** (Zahlungsziel, Kontodaten)

## Phase D – Stammdaten
11. **Kunden‑Verwaltung** (Liste + Formular)
12. **Produkte/DL** (Liste + Formular)

## Phase E – Vorgänge & Elemente
13. **Vorgänge‑Liste** (nach Kunden filterbar)
14. **Vorgang‑Detail** (Name, Beschreibung, Deck‑Link, Kollektiv‑Link)
15. **Elemente** an Vorgang (Name, Notiz, Anhang)

## Phase F – Rechnungen & Angebote
16. **Rechnung erstellen** (auto‑Daten aus Kunde/Firma)
17. **Positionen** (Produkt/DL/frei, Menge, Preis, Summen)
18. **Steuerblock** abhängig von Einstellungen
19. **Rechnung PDF‑Export** (Layout nach Vorgabe)
20. **Rechnung senden** + Einnahme anlegen (Status offen)
21. **Angebot erstellen** (wie Rechnung, anderer Text)

## Phase G – Wirtschaftsjahr
22. **Wirtschaftsjahr anlegen** (Name + Zeitraum)
23. **Einnahmen** (aus Rechnungen, Statuspflege)
24. **Ausgaben** (manuell anlegen)
25. **GÜB** automatisch berechnen

## Phase H – Dashboard („Tägliches“)
26. **Dashboard‑Kacheln**: offene Rechnungen, aktuelle Vorgänge
27. (Optional) weitere KPIs (Einnahmen Monat, Ausgaben Monat)

## Phase I – Feinschliff
28. Validierungen + Pflichtfelder
29. UX‑Details (Nextcloud‑Guidelines, Empty‑States)
30. Seed‑Daten (Demo)
