<template>
  <section class="settings">
    <h1>E-Mailverhalten</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <h2>Versandart</h2>
      <NcCheckboxRadioSwitch
        type="switch"
        :checked="form.mode === 'direct'"
        @update:checked="setDirectMode"
      >
        Direkt senden über den Admin-SMTP-Server (mit Vorschau)
      </NcCheckboxRadioSwitch>
      <p class="hint" v-if="form.mode !== 'direct'">
        Aktuell: Mailvorlage öffnen (PDF herunterladen und manuell verschicken).
      </p>
      <p class="hint">
        Der Direktversand nutzt die SMTP-Einstellungen aus der Nextcloud-Administration.
      </p>

      <div class="section">
        <h2>Absender &amp; Antwortadresse</h2>
        <NcTextField
          label="Absender (From)"
          :value.sync="form.fromEmail"
        />
        <NcTextField
          label="Antwortadresse (Reply-To)"
          :value.sync="form.replyToEmail"
        />
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
import { NcButton, NcCheckboxRadioSwitch, NcLoadingIcon } from '@nextcloud/vue'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import { getEmailBehavior, saveEmailBehavior } from '../api/settings'

export default {
  name: 'SettingsEmailBehavior',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcLoadingIcon,
    NcTextField,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        mode: 'manual',
        fromEmail: '',
        replyToEmail: '',
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
        const data = await getEmailBehavior()
        this.form = {
          mode: data.mode || 'manual',
          fromEmail: data.fromEmail || '',
          replyToEmail: data.replyToEmail || '',
        }
      } catch (e) {
        this.error = 'Daten konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    setDirectMode(isDirect) {
      this.form.mode = isDirect ? 'direct' : 'manual'
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const payload = {
          mode: this.form.mode,
          fromEmail: this.form.fromEmail.trim(),
          replyToEmail: this.form.replyToEmail.trim(),
        }
        const data = await saveEmailBehavior(payload)
        this.form = {
          mode: data.mode || this.form.mode,
          fromEmail: data.fromEmail || '',
          replyToEmail: data.replyToEmail || '',
        }
        this.defaults = {
          fromEmail: data.defaultFromEmail || this.defaults.fromEmail,
          replyToEmail: data.defaultReplyToEmail || this.defaults.replyToEmail,
        }
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
  max-width: 640px;
}

.form > * {
  margin-bottom: 16px;
}

.form > h2,
.form > p {
  margin-top: 0;
}

.form > p.hint {
  margin-bottom: 8px;
}

.form > p.hint + .section {
  margin-top: 0;
}

.section {
  margin-top: 0;
  padding-left: 0;
  align-self: stretch;
  width: 100%;
}

.section h2 {
  margin-left: 0;
  margin-top: 0;
}

.actions {
  display: flex;
  align-items: center;
  gap: 12px;
}

.hint {
  color: var(--color-text-lighter, #6b7280);
  font-size: 12px;
}

.success {
  color: var(--color-success, #2d9a4f);
}

.error {
  color: var(--color-error, #b91c1c);
}
</style>
