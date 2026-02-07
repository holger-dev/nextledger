<template>
  <section class="settings">
    <h1>Texte</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <div class="section">
        <div class="section__header">
          <h2>PDF-Texte</h2>
          <p class="hint">Diese Texte erscheinen im Angebot bzw. in der Rechnung als PDF.</p>
        </div>

        <div class="text-block">
          <p class="text-block__hint">Erscheint in der Rechnung oberhalb der Positionen.</p>
          <NcTextArea label="Begrüßungstext Rechnung" :value.sync="form.invoiceGreeting" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">Erscheint im Angebot oberhalb der Positionen.</p>
          <NcTextArea label="Begrüßungstext Angebot" :value.sync="form.offerGreeting" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">Erscheint nach der Gesamtsumme und vor „Mit freundlichen Grüßen“.</p>
          <NcTextArea label="Abschlusstext Rechnung" :value.sync="form.invoiceClosingText" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">Erscheint nach der Gesamtsumme und vor „Mit freundlichen Grüßen“.</p>
          <NcTextArea label="Abschlusstext Angebot" :value.sync="form.offerClosingText" />
        </div>

        <div class="text-block">
          <p class="text-block__hint">Erscheint im Fußbereich jeder PDF-Seite.</p>
          <NcTextArea label="Footer-Text (PDF)" :value.sync="form.footerText" />
        </div>
      </div>

      <div class="section">
        <div class="section__header">
          <h2>E-Mail Texte</h2>
          <p class="hint">Diese Texte werden als E-Mail-Vorlage verwendet.</p>
        </div>
        <div class="text-block">
          <p class="text-block__hint" v-pre>
            Platzhalter: {{offerNumber}}, {{customerName}}, {{customerContact}},
            {{customerSalutation}}, {{caseName}}, {{total}}, {{issueDate}}
          </p>
          <NcTextArea
            label="E-Mail Betreff Angebot"
            :value.sync="form.offerEmailSubject"
            :placeholder="'z. B. Angebot {{offerNumber}}'"
          />
        </div>

        <div class="text-block">
          <p class="text-block__hint" v-pre>
            Platzhalter: {{offerNumber}}, {{customerName}}, {{customerContact}},
            {{customerSalutation}}, {{caseName}}, {{total}}, {{issueDate}}
          </p>
          <NcTextArea
            label="E-Mail Text Angebot"
            :value.sync="form.offerEmailBody"
            :placeholder="'z. B. {{customerSalutation}},\\n\\nanbei das Angebot {{offerNumber}}.'"
          />
        </div>

        <div class="text-block">
          <p class="text-block__hint" v-pre>
            Platzhalter: {{invoiceNumber}}, {{customerName}}, {{customerContact}},
            {{customerSalutation}}, {{caseName}}, {{total}}, {{issueDate}}
          </p>
          <NcTextArea
            label="E-Mail Betreff Rechnung"
            :value.sync="form.invoiceEmailSubject"
            :placeholder="'z. B. Rechnung {{invoiceNumber}}'"
          />
        </div>

        <div class="text-block">
          <p class="text-block__hint" v-pre>
            Platzhalter: {{invoiceNumber}}, {{customerName}}, {{customerContact}},
            {{customerSalutation}}, {{caseName}}, {{total}}, {{issueDate}}
          </p>
          <NcTextArea
            label="E-Mail Text Rechnung"
            :value.sync="form.invoiceEmailBody"
            :placeholder="'z. B. {{customerSalutation}},\\n\\nanbei die Rechnung {{invoiceNumber}}.'"
          />
        </div>
      </div>

      <div class="actions">
        <NcButton type="primary" :disabled="saving" @click="save">
          Speichern
        </NcButton>
        <span v-if="saving" class="hint">Speichere…</span>
        <span v-if="saved" class="success">Gespeichert</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import NcTextArea from '@nextcloud/vue/dist/Components/NcTextArea.mjs'
import { getTexts, saveTexts } from '../api/settings'

export default {
  name: 'SettingsTexts',
  components: {
    NcButton,
    NcLoadingIcon,
    NcTextArea,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        invoiceGreeting: '',
        offerGreeting: '',
        footerText: '',
        offerClosingText: '',
        invoiceClosingText: '',
        offerEmailSubject: '',
        offerEmailBody: '',
        invoiceEmailSubject: '',
        invoiceEmailBody: '',
      },
    }
  },
  async mounted() {
    await this.load()
  },
  methods: {
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getTexts()
        const safeString = (value) => (value === null || value === undefined ? '' : String(value))
        this.form = {
          ...this.form,
          invoiceGreeting: safeString(data.invoiceGreeting),
          offerGreeting: safeString(data.offerGreeting),
          footerText: safeString(data.footerText),
          offerClosingText: safeString(data.offerClosingText),
          invoiceClosingText: safeString(data.invoiceClosingText),
          offerEmailSubject: safeString(data.offerEmailSubject),
          offerEmailBody: safeString(data.offerEmailBody),
          invoiceEmailSubject: safeString(data.invoiceEmailSubject),
          invoiceEmailBody: safeString(data.invoiceEmailBody),
        }
      } catch (e) {
        this.error = 'Daten konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const data = await saveTexts(this.form)
        this.form = { ...this.form, ...data }
        this.saved = true
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
      } catch (e) {
        this.error = 'Speichern fehlgeschlagen.'
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.settings {
  max-width: 860px;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section {
  border: 1px solid var(--color-border, #e5e7eb);
  border-radius: 12px;
  padding: 16px;
  background: var(--color-main-background, #fff);
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.section__header h2 {
  margin: 0 0 4px;
  font-size: 18px;
}

.section__header .hint {
  margin: 0;
}

.text-block {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.text-block__hint {
  margin: 0;
  font-size: 14px;
  line-height: 1.5;
  display: block;
  opacity: 0.9;
  color: var(--color-text-lighter, #6b7280);
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hint {
  color: var(--color-text-lighter, #6b7280);
}

.success {
  color: var(--color-success, #2d9a4f);
}

.error {
  color: var(--color-error, #b91c1c);
}
</style>
