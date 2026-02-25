<template>
  <section class="settings">
    <h1>{{ t('title') }}</h1>

    <NcLoadingIcon v-if="loading" />

    <div v-else class="form">
      <h2>{{ t('companyDeliveryTitle') }}</h2>
      <p class="hint">
        {{ t('companyDeliveryHint') }}
      </p>

      <div class="company-list">
        <div
          v-for="company in form.companyMappings"
          :key="company.companyId"
          class="company-row"
        >
          <div class="company-name">{{ company.companyName }}</div>
          <div class="company-mode">
            <NcSelect
              :value="company.mode"
              :options="modeOptions"
              :reduce="(option) => option.value"
              :append-to-body="false"
              :clearable="false"
              :input-label="t('deliveryMode')"
              :label-outside="true"
              @input="onCompanyModeChange(company, $event)"
            />
          </div>
          <div v-if="company.mode === 'nextcloud_mail'" class="company-account">
            <NcSelect
              :value="company.serviceKey"
              :options="mailServiceOptions"
              :reduce="(option) => option.value"
              :append-to-body="false"
              :clearable="true"
              :input-label="t('smtpAccount')"
              :label-outside="true"
              :placeholder="t('smtpSelectPlaceholder')"
              @input="onCompanyServiceChange(company, $event)"
            />
          </div>
        </div>
      </div>

      <p v-if="!mailServiceOptions.length" class="hint">
        {{ t('noMailAccountsHint') }}
      </p>
      <p class="hint">
        {{ t('adminSmtpHint') }}
      </p>

      <div class="section">
        <h2>{{ t('senderTitle') }}</h2>
        <h3 class="subsection-title">{{ t('senderLogicTitle') }}</h3>
        <p class="hint">{{ senderLogicText }}</p>
        <p class="hint">{{ t('senderHint') }}</p>
        <p class="hint" v-if="autoFromEmail"><strong>{{ t('autoFromLabel') }}:</strong> {{ autoFromEmail }}</p>
        <p class="hint" v-if="autoReplyToEmail"><strong>{{ t('autoReplyToLabel') }}:</strong> {{ autoReplyToEmail }}</p>
        <NcCheckboxRadioSwitch
          type="switch"
          :checked="form.useCustomSender"
          @update:checked="setCustomSender"
        >
          {{ t('senderOverrideSwitch') }}
        </NcCheckboxRadioSwitch>
        <div v-if="form.useCustomSender" class="sender-overrides">
          <NcTextField
            :label="t('fromLabel')"
            :value.sync="form.fromEmail"
          />
          <NcTextField
            :label="t('replyToLabel')"
            :value.sync="form.replyToEmail"
          />
        </div>
      </div>

      <div class="actions">
        <NcButton type="primary" :disabled="saving" @click="save">
          {{ t('saveButton') }}
        </NcButton>
        <span v-if="saving" class="hint">{{ t('saving') }}</span>
        <span v-if="saved" class="success">{{ t('saved') }}</span>
        <span v-if="error" class="error">{{ error }}</span>
      </div>
    </div>
  </section>
</template>

<script>
import { NcButton, NcCheckboxRadioSwitch, NcLoadingIcon } from '@nextcloud/vue'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.mjs'
import NcTextField from '@nextcloud/vue/dist/Components/NcTextField.mjs'
import { getEmailBehavior, saveEmailBehavior } from '../api/settings'

export default {
  name: 'SettingsEmailBehavior',
  components: {
    NcButton,
    NcCheckboxRadioSwitch,
    NcLoadingIcon,
    NcSelect,
    NcTextField,
  },
  data() {
    return {
      loading: true,
      saving: false,
      saved: false,
      error: '',
      form: {
        useCustomSender: false,
        fromEmail: '',
        replyToEmail: '',
        autoFromEmail: '',
        autoReplyToEmail: '',
        companyMappings: [],
      },
      activeCompanyId: null,
      mailProviders: [],
    }
  },
  computed: {
    modeOptions() {
      return [
        { value: 'manual', label: this.t('modeManual') },
        { value: 'admin_smtp', label: this.t('modeAdminSmtp') },
        { value: 'nextcloud_mail', label: this.t('modeNextcloudMail') },
      ]
    },
    mailServiceOptions() {
      const options = []
      this.mailProviders.forEach((provider) => {
        const providerLabel = provider.label || provider.providerId
        const services = Array.isArray(provider.services) ? provider.services : []
        services.forEach((service) => {
          const accountLabel = service.address
            ? `${service.label} (${service.address})`
            : service.label
          options.push({
            value: `${provider.providerId}:${service.serviceId}`,
            label: `${providerLabel} · ${accountLabel}`,
          })
        })
      })
      return options
    },
    autoFromEmail() {
      return (this.form.autoFromEmail || '').trim()
    },
    autoReplyToEmail() {
      return (this.form.autoReplyToEmail || '').trim()
    },
    senderLogicText() {
      const activeMode =
        this.form.companyMappings.find((entry) => Number(entry.companyId) === Number(this.activeCompanyId))
          ?.mode || 'manual'
      if (activeMode === 'nextcloud_mail') {
        return this.t('senderLogicMail')
      }
      if (activeMode === 'admin_smtp') {
        return this.t('senderLogicAdmin')
      }
      return this.t('senderLogicManual')
    },
  },
  async mounted() {
    await this.load()
  },
  methods: {
    t(key) {
      return this.$tKey(`settingsEmailBehavior.${key}`, key)
    },
    applyResponse(data) {
      const mappings = Array.isArray(data.companyMappings) ? data.companyMappings : []
      this.form = {
        useCustomSender: !!((data.fromEmail || '').trim() || (data.replyToEmail || '').trim()),
        fromEmail: data.fromEmail || '',
        replyToEmail: data.replyToEmail || '',
        autoFromEmail: data.autoFromEmail || '',
        autoReplyToEmail: data.autoReplyToEmail || '',
        companyMappings: mappings.map((mapping) => {
          const mode = this.normalizeMode(mapping.mode || data.mode || 'manual')
          const providerId = mapping.providerId || ''
          const serviceId = mapping.serviceId || ''
          return {
            companyId: Number(mapping.companyId) || 0,
            companyName: mapping.companyName || '',
            mode,
            serviceKey:
              mode === 'nextcloud_mail' && providerId && serviceId
                ? `${providerId}:${serviceId}`
                : '',
          }
        }),
      }
      this.activeCompanyId = Number(data.activeCompanyId || 0) || null
      this.mailProviders = Array.isArray(data.mailProviders) ? data.mailProviders : []
    },
    normalizeMode(mode) {
      if (mode === 'direct') {
        return 'admin_smtp'
      }
      if (mode === 'manual' || mode === 'admin_smtp' || mode === 'nextcloud_mail') {
        return mode
      }
      return 'manual'
    },
    async load() {
      this.loading = true
      this.error = ''
      try {
        const data = await getEmailBehavior()
        this.applyResponse(data || {})
      } catch (e) {
        this.error = this.t('loadError')
      } finally {
        this.loading = false
      }
    },
    onCompanyModeChange(company, mode) {
      company.mode = this.normalizeMode(mode)
      if (company.mode !== 'nextcloud_mail') {
        company.serviceKey = ''
      }
    },
    onCompanyServiceChange(company, serviceKey) {
      company.serviceKey = serviceKey || ''
    },
    setCustomSender(enabled) {
      this.form.useCustomSender = !!enabled
      if (!this.form.useCustomSender) {
        this.form.fromEmail = ''
        this.form.replyToEmail = ''
      }
    },
    async save() {
      this.saving = true
      this.saved = false
      this.error = ''
      try {
        const activeMapping = this.form.companyMappings.find(
          (mapping) => Number(mapping.companyId) === Number(this.activeCompanyId)
        )
        const normalizeCompanyMapping = (mapping) => {
          const mode = this.normalizeMode(mapping.mode)
          let providerId = ''
          let serviceId = ''
          if (mode === 'nextcloud_mail' && mapping.serviceKey) {
            const [provider, service] = String(mapping.serviceKey).split(':')
            providerId = provider || ''
            serviceId = service || ''
          }
          return {
            companyId: Number(mapping.companyId),
            mode,
            providerId,
            serviceId,
          }
        }
        const payload = {
          mode: activeMapping ? this.normalizeMode(activeMapping.mode) : 'manual',
          fromEmail: this.form.useCustomSender ? this.form.fromEmail.trim() : '',
          replyToEmail: this.form.useCustomSender ? this.form.replyToEmail.trim() : '',
          companyMappings: this.form.companyMappings.map(normalizeCompanyMapping),
        }
        const data = await saveEmailBehavior(payload)
        this.applyResponse(data || {})
        this.saved = true
        window.setTimeout(() => {
          this.saved = false
        }, 2000)
      } catch (e) {
        this.error = this.t('saveError')
      } finally {
        this.saving = false
      }
    },
  },
}
</script>

<style scoped>
.settings {
  max-width: 960px;
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

.subsection-title {
  margin: 8px 0 4px;
  font-size: 14px;
  font-weight: 600;
}

.sender-overrides {
  margin-top: 8px;
}

.company-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.company-row {
  display: grid;
  grid-template-columns: minmax(160px, 1fr) minmax(260px, 1.2fr) minmax(320px, 1.8fr);
  gap: 12px;
  align-items: start;
}

.company-name {
  font-weight: 600;
  padding-top: 6px;
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

@media (max-width: 900px) {
  .company-row {
    grid-template-columns: 1fr;
  }

  .company-name {
    padding-top: 0;
  }
}
</style>
