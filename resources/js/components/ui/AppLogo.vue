<!-- resources/js/components/ui/AppLogo.vue -->
<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/useAuthStore.js';

const props = defineProps({
  height: { type: [String, Number], default: 64 },
  white: { type: Boolean, default: false } // Defaults to colored version
});

const authStore = useAuthStore();

// Points directly to the actual filenames in public/assets/images/
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
      width: 'calc(' + props.height + 'px * 103 / 50)' 
    }"
    aria-label="CC-PLUS Home"
  >
    <v-img 
      :src="logoSrc" 
      alt="CC-PLUS Logo" 
      height="100%"
      width="100%"
      contain
    />
  </router-link>
</template>
