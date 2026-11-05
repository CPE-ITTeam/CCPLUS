<script setup>
  import { computed, ref} from 'vue'
  import { useRoute } from 'vue-router';
  import { useAuthStore } from '@/stores/useAuthStore.js';
  import LoginLayout from '@/layouts/LoginLayout.vue'
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
  <!-- min-height="100vh" forces the root app shell to span edge-to-edge vertically -->
  <v-app min-height="100vh">
    <component :is="currentLayout" :key="layoutKey" @updateConso="changeConso($event)">
      
      <router-view :key="$route.fullPath" v-slot="{ Component }">
        <keep-alive>
          <component :is="Component" />
        </keep-alive>
      </router-view>

    </component>
  </v-app>
</template>

