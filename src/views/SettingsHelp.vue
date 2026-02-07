<template>
  <section class="settings-help">
    <h1>Hilfe</h1>

    <div class="card">
      <h2>Kurzanleitung</h2>
      <ol>
        <li>Kunde anlegen</li>
        <li>Vorgang erstellen und Kunden zuordnen</li>
        <li>Angebot erstellen und Positionen hinzufügen</li>
        <li>Rechnung aus dem Vorgang anlegen</li>
        <li>PDF exportieren und versenden</li>
        <li>Einnahmen/Ausgaben im Wirtschaftsjahr prüfen</li>
      </ol>
    </div>

    <div class="card">
      <h2>Wichtige Hinweise</h2>
      <ul>
        <li>Ohne aktives Wirtschaftsjahr können keine Rechnungen erstellt werden.</li>
        <li>Es kann immer nur ein Wirtschaftsjahr aktiv sein.</li>
        <li>Texte für PDF und E-Mail findest du unter Einstellungen → Texte.</li>
      </ul>
    </div>

    <div class="card">
      <h2>Support</h2>
      <p>Bei Fragen oder Feedback: <strong>holger@heidkamp.dev</strong></p>
    </div>
  </section>
</template>

<script>
export default {
  name: 'SettingsHelp',
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

ol,
ul {
  margin: 0;
  padding-left: 20px;
}

li {
  margin: 6px 0;
}
</style>
