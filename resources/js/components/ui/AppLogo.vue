<!-- resources/js/components/ui/AppLogo.vue -->
<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';

const props = defineProps({
  height: { type: [String, Number], default: 64 },
  white: { type: Boolean, default: false },
});

const authStore = useAuthStore();

const logoSrc = computed(() => {
  return props.white
    ? '/assets/images/logo-ccplus-white.png'
    : '/assets/images/logo-ccplus.png';
});

const logoDestination = computed(() => {
  if (authStore.user || authStore.isAuthenticated) {
    return '/institutions/institutions';
  }
  return '/';
});
</script>

<template>
  <router-link
    :to="logoDestination"
    class="d-inline-block text-decoration-none flex-shrink-0"
    :style="{
      height: props.height + 'px',
      width: 'calc(' + props.height + 'px * 103 / 50)',
    }"
    :aria-label="$t('common.ccplus_home_aria')"
  >
    <v-img
      :src="logoSrc"
      :alt="$t('common.ccplus_logo_alt')"
      height="100%"
      width="100%"
      contain
    />
  </router-link>
</template>
