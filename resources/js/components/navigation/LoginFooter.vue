<!-- components/navigation/LoginFooter.vue -->
<template>
  <div class="bg-grey-lighten-4 py-6 d-flex justify-center w-100" style="border-top: 1px solid var(--v-theme-primary);">
    <div class="d-flex align-center ga-6">
      <a :href="LINKS.counterMetrics" target="_blank" rel="noopener" :aria-label="$t('footer.counter_aria')">
        <img src="/assets/images/logo-counter.svg" style="height: 28px; width: auto;" :alt="$t('footer.counter_alt')" />
      </a>
      <a :href="LINKS.registry" target="_blank" rel="noopener" :aria-label="$t('footer.registry_aria')">
        <img src="/assets/images/logo-counter-registry.png" style="height: 28px; width: auto;" :alt="$t('footer.registry_alt')" />
      </a>
      <a :href="LINKS.ccLatest" target="_blank" rel="noopener" :aria-label="$t('footer.github_aria')">
        <img src="/assets/images/logo-github.svg" style="height: 28px; width: auto;" :alt="$t('footer.github_alt')" />
      </a>
    </div>
  </div>
</template>

<script setup>
import { useExternalLinks } from '@/composables/useExternalLinks';

const { LINKS } = useExternalLinks();
</script>
