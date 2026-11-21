<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import LoginLayout from '@/layouts/LoginLayout.vue';
import StatusAlerts from '@/components/ui/StatusAlerts.vue';

const consoKey = ref('');
const route = useRoute();
const auth = useAuthStore();
const ccPlusStore = useCCPlusStore();

const currentLayout = computed(() => route.meta.layout || LoginLayout);
const layoutKey = computed(() => {
  return typeof route.meta.layout == 'undefined'
    ? 'LoginLayout'
    : route.meta.layout.__name + consoKey.value;
});

watch(
  () => route.fullPath,
  () => {
    ccPlusStore.clearAlerts();
  }
);

function changeConso(conso) {
  consoKey.value = conso.ccp_key;
}
</script>

<template>
  <!-- min-height="100vh" forces the root app shell to span edge-to-edge vertically -->
  <v-app min-height="100vh">
    <component :is="currentLayout" :key="layoutKey" @updateConso="changeConso($event)">
      <StatusAlerts global />
      <router-view :key="$route.fullPath" v-slot="{ Component }">
        <keep-alive>
          <component :is="Component" v-if="Component" />
        </keep-alive>
      </router-view>
    </component>
  </v-app>
</template>


<style>
input:-webkit-autofill,
input:-webkit-autofill:hover,
input:-webkit-autofill:focus,
input:-webkit-autofill:active,
textarea:-webkit-autofill,
select:-webkit-autofill {
  -webkit-box-shadow: 0 0 0 1000px rgb(var(--v-theme-surface)) inset !important;
  -webkit-text-fill-color: rgb(var(--v-theme-on-surface)) !important;
  caret-color: rgb(var(--v-theme-on-surface)) !important;
  transition: background-color 5000s ease-in-out 0s;
}
</style>
