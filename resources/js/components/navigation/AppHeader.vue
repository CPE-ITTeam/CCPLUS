<!-- components/navigation/AppHeader.vue -->
<template>
  <v-toolbar class="pa-2">
    <!-- resources/js/components/navigation/AppHeader.vue -->
    <template #prepend>
      <div class="d-flex align-center">
        <AppLogo :height="50" white />
      </div>
    </template>


    <v-spacer></v-spacer>

    <div class="d-flex align-center ga-1">
      <!-- ─── 👤 UTILITIES GROUP TIER: GLOBAL USER ACCOUNT DROPDOWN ─── -->
      <v-menu close-on-content-click>
        <template #activator="{ props: menuProps }">
          <!-- 💡 WCAG ADVANCEMENT: Aria-label tells screen readers what the button opens,
                allowing us to safely strip the aggressive popup hover tooltips app-wide! -->
          <v-btn icon="mdi-account-circle-outline" v-bind="menuProps"
            aria-label="User profile settings and system context options" />
        </template>

        <v-list width="200">
          <!-- 👤 Route-link into your personal profile forms views -->
          <v-list-item to="/profile" prepend-icon="mdi-account-edit-outline" title="My Profile" />

          <!-- 🔌 Quick-jump connection toggle for system settings if user is ServerAdmin -->
          <v-list-item v-if="authStore.is_serveradmin" to="/server/api" prepend-icon="mdi-api" title="API Settings" />

          <v-divider class="my-1" />

          <!-- 🚪 System session termination escape hook (Moved into menu layer!) -->
          <v-list-item link prepend-icon="mdi-logout" title="Log Out" color="error" @click="apiLogout" />
        </v-list>
      </v-menu>

      <!-- ─── 🔌 SYSTEM EXTERNAL TIER: COUNTER LINKS OVERLAY (FAR RIGHT) ─── -->
      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn icon="mdi-dots-vertical" v-bind="menuProps"
            aria-label="Open external COUNTER reference resources and registries links" />
        </template>

        <v-list>
          <!-- Loops dynamically through your custom link list array -->
          <v-list-item v-for="(link, index) in moreLinks" :key="index" :title="link.title" :href="link.href || '#'"
            :prepend-icon="link.icon" slim v-external />
        </v-list>
      </v-menu>

    </div>
  </v-toolbar>
</template>

<script setup>
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useExternalLinks } from '@/composables/useExternalLinks';
import AppLogo from "@/components/ui/AppLogo.vue";

const authStore = useAuthStore();

// Destructure the raw links directly from the composable state
const { moreLinks } = useExternalLinks();

// Array containing the structured list properties
/*
const moreLinks = [
  { title: 'Code of Practice', href: LINKS.cop, icon: 'mdi-file-document-outline' },
  { title: 'COUNTER API', href: LINKS.counterApi, icon: 'mdi-api' },
  { title: 'Registry', href: LINKS.registry, icon: 'mdi-database-outline' },
  { title: 'Registry API', href: LINKS.registryApi, icon: 'mdi-code-json' },
];
*/

function apiLogout() {
  authStore.logoutStateClear();
}
</script>
