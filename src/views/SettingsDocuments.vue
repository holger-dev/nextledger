<template>
  <section class="settings">
    <h1>Dokumente</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <NcCheckboxRadioSwitch
        :checked.sync="form.autoStorePdfs"
        type="switch"
      >
        Rechnungs- und Angebots-PDFs automatisch in Dateien speichern
      </NcCheckboxRadioSwitch>

      <NcCheckboxRadioSwitch
        :checked.sync="form.keepPdfVersions"
        type="switch"
        :disabled="!form.autoStorePdfs"
      >
        Versionierung aktivieren (z.B. RE20260221-0001_v1, _v2, _v3)
      </NcCheckboxRadioSwitch>

      <p class="hint" v-if="form.autoStorePdfs">
        Ablagepfad: Dateien / NextLedger / Firma / Wirtschaftsjahr
      </p>

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
import { getDocuments, saveDocuments } from '../api/settings'

export default {
  name: 'SettingsDocuments',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcLoadingIcon,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        autoStorePdfs: false,
        keepPdfVersions: false,
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
        const data = await getDocuments()
        this.form = {
          autoStorePdfs: Boolean(data.autoStorePdfs),
          keepPdfVersions: Boolean(data.keepPdfVersions),
        }
      } catch (e) {
        this.error = 'Einstellungen konnten nicht geladen werden.'
      } finally {
        this.loading = false
      }
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const payload = {
          autoStorePdfs: Boolean(this.form.autoStorePdfs),
          keepPdfVersions: Boolean(this.form.autoStorePdfs && this.form.keepPdfVersions),
        }
        const data = await saveDocuments(payload)
        this.form.autoStorePdfs = Boolean(data.autoStorePdfs)
        this.form.keepPdfVersions = Boolean(data.keepPdfVersions)
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
  max-width: 760px;
}

.form > * {
  margin-bottom: 16px;
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
