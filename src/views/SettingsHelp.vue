<template>
  <section class="settings-help">
    <h1>Hilfe</h1>

    <div class="card">
      <h2>Einrichtung (erste Schritte)</h2>
      <ol>
        <li>Wirtschaftsjahr anlegen</li>
        <li>Firmadaten eintragen</li>
        <li>Kunden anlegen</li>
        <li>Optional: Produkte/DL anlegen</li>
        <li>Vorgänge erstellen und Angebote/Rechnungen ableiten</li>
      </ol>
      <div class="setup-links">
        <NcButton type="secondary" :to="{ name: 'fiscal-year' }">
          Wirtschaftsjahr
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'settings-company' }">
          Firmadaten
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'customers' }">
          Kunden
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'products' }">
          Produkte/DL
        </NcButton>
        <NcButton type="secondary" :to="{ name: 'cases' }">
          Vorgänge
        </NcButton>
      </div>
    </div>

    <div class="card">
      <h2>Kurzanleitung</h2>
      <ol>
        <li>Kunden anlegen und Kontakte pflegen</li>
        <li>Vorgang erstellen und dem Kunden zuordnen</li>
        <li>Leistungen als Positionen hinzufügen</li>
        <li>Angebot erstellen oder direkt Rechnung anlegen</li>
        <li>PDF exportieren und versenden</li>
        <li>Einnahmen/Ausgaben im Wirtschaftsjahr prüfen</li>
      </ol>
    </div>

    <div class="card">
      <h2>Grundbegriffe</h2>
      <ul>
        <li>Kunde: Stammdaten, Rechnungs‑E-Mail, Ansprechpartner.</li>
        <li>Vorgang: Klammer für Angebot, Rechnung, Notizen und Korrespondenz.</li>
        <li>Position: Einzelne Leistung/Produkt mit Menge und Preis.</li>
        <li>Angebot: Verbindliche Preisübersicht für den Kunden.</li>
        <li>Rechnung: Zahlungsaufforderung, erzeugt Einnahmen im Wirtschaftsjahr.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Angebote & Rechnungen</h2>
      <ul>
        <li>Du kannst aus einem Vorgang jederzeit ein Angebot oder eine Rechnung erstellen.</li>
        <li>Positionen lassen sich aus Produkten übernehmen oder manuell anlegen.</li>
        <li>Rechnungsnummern werden automatisch vergeben.</li>
        <li>PDFs findest du direkt in den Aktionen einer Rechnung/Angebots.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Abschlagszahlung & Schlussrechnung</h2>
      <p>
        Abschlagsrechnungen dienen der Teilzahlung während eines laufenden Projekts.
        Die Schlussrechnung stellt die Gesamtsumme dar und zieht bereits gezahlte Abschläge ab.
      </p>
      <ul>
        <li>Abschlagsrechnung: Teilbetrag fakturieren, z. B. 30% vor Projektstart.</li>
        <li>Schlussrechnung: Gesamtbetrag minus Summe der Abschläge.</li>
        <li>Tipp: Lege Abschläge als separate Rechnungen im selben Vorgang an.</li>
        <li>Notiere im Rechnungstext, auf welches Angebot oder welche Abschläge sich die Rechnung bezieht.</li>
      </ul>
    </div>

    <div class="card">
      <h2>E-Mailversand</h2>
      <ul>
        <li>Manuell: Mailvorlage öffnen, PDF anhängen und versenden.</li>
        <li>Direkt: Versand über den Admin‑SMTP‑Server mit Vorschau.</li>
        <li>Die Standard‑Absenderadresse kommt aus der Nextcloud‑Administration.</li>
        <li>Empfängerlogik beim Kunden: Rechnungs‑E-Mail hat Vorrang, sonst Ansprechpartner.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Wirtschaftsjahr</h2>
      <ul>
        <li>Ohne aktives Wirtschaftsjahr können keine Rechnungen erstellt werden.</li>
        <li>Es kann immer nur ein Wirtschaftsjahr aktiv sein.</li>
        <li>Einnahmen entstehen automatisch durch Rechnungen.</li>
        <li>Ausgaben kannst du manuell erfassen.</li>
        <li>Der GÜB‑Export fasst Einnahmen, Ausgaben und Ergebnis zusammen.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Texte & Vorlagen</h2>
      <ul>
        <li>Texte für PDF und E‑Mail findest du unter Einstellungen → Texte.</li>
        <li>Änderungen gelten nur für neue Dokumente.</li>
        <li>Nutze Platzhalter, falls vorhanden, um Nummern oder Namen automatisch einzusetzen.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Fehlerbehebung</h2>
      <ul>
        <li>Fehlendes PDF: Seite neu laden und erneut exportieren.</li>
        <li>Kein Versand möglich: SMTP‑Einstellungen in Nextcloud prüfen.</li>
        <li>Falsche Zahlen: Prüfe Positionen, Steuersatz und Abschläge im Vorgang.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Support</h2>
      <p>Bei Fragen oder Feedback: <strong>holger@heidkamp.dev</strong></p>
    </div>
  </section>
</template>

<script>
import { NcButton } from '@nextcloud/vue'

export default {
  name: 'SettingsHelp',
  components: {
    NcButton,
  },
}
</script>

<style scoped>
.settings-help {
  max-width: 860px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 16px;
  background: var(--color-main-background, #fff);
}

.card h2 {
  margin: 0 0 12px;
  font-size: 18px;
}

.card p {
  margin: 0 0 12px;
}

.setup-links {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 12px;
}

ol,
ul {
  margin: 0;
  padding-left: 20px;
}

li {
  margin: 6px 0;
}
</style>
