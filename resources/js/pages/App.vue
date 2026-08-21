<script setup>
  import { computed, ref} from 'vue'
  import { useRoute } from 'vue-router';
  import { useAuthStore } from '@/plugins/authStore.js';
  import LoginLayout from '../layouts/LoginLayout.vue'
  var consoKey = ref('');
  const route = useRoute();
  const auth = useAuthStore();
  const currentLayout = computed(() => route.meta.layout || LoginLayout);
  const layoutKey = computed(() => {
    return (typeof(route.meta.layout) == 'undefined') ? 'LoginLayout'
                                                      : route.meta.layout.__name+consoKey.value;
  });
  function changeConso(conso) {
    consoKey.value = conso.ccp_key;
  }
</script>

<template>
  <!-- Force layout to sync with changes to route and consortium key -->
  <component :is="currentLayout" :key="layoutKey" @updateConso="changeConso($event)">
    <keep-alive>
      <router-view :key="$route.fullPath" />
    </keep-alive>
  </component>
</template>
