<template>
  <section class="case-detail">
    <div class="header">
      <div>
        <h1>{{ t('title') }}</h1>
        <p class="subline">{{ t('subline') }}</p>
      </div>
      <NcButton type="secondary" @click="goBack">{{ t('back') }}</NcButton>
    </div>

    <NcLoadingIcon v-if="loading" />

    <Cases v-else :standalone="true" :focus-case-id="caseId" />
  </section>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import Cases from './Cases.vue'

export default {
  name: 'CaseDetail',
  components: {
    NcButton,
    NcLoadingIcon,
    Cases,
  },
  data() {
    return {
      loading: false,
    }
  },
  computed: {
    caseId() {
      return this.$route.params.id
    },
  },
  methods: {
    t(key) {
      return this.$tKey(`caseDetail.${key}`, key)
    },
    goBack() {
      this.$router.push({ name: 'cases' })
    },
  },
}
</script>

<style scoped>
.case-detail {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 16px;
}

.subline {
  margin: 4px 0 0;
  color: var(--color-text-lighter, #6b7280);
}
</style>
