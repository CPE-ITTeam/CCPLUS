<!-- components/navigation/LoginFooter.vue -->
<template>
  <div class="bg-grey-lighten-4 py-6 d-flex justify-center w-100" style="border-top: 1px solid var(--v-theme-primary);">
    <div class="d-flex align-center ga-6">
      <a :href="LINKS.counterMetrics" target="_blank" rel="noopener" aria-label="Official COUNTER Code of Practice website">
        <img src="/assets/images/logo-counter.svg" style="height: 28px; width: auto;" alt="COUNTER Metrics Logo" />
      </a>
      <a :href="LINKS.registry" target="_blank" rel="noopener" aria-label="Official COUNTER Registry portal">
        <img src="/assets/images/logo-counter-registry.png" style="height: 28px; width: auto;" alt="COUNTER Registry Logo" />
      </a>
      <a :href="LINKS.ccLatest" target="_blank" rel="noopener" aria-label="CCPLUS GitHub open source repository">
        <img src="/assets/images/logo-github.svg" style="height: 28px; width: auto;" alt="GitHub Logo" />
      </a>
    </div>
  </div>
</template>

<script setup>
import { useExternalLinks } from '@/composables/useExternalLinks';
const { LINKS } = useExternalLinks();
</script>
