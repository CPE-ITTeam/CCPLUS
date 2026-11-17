<!-- components/navigation/AppHeader.vue -->
<template>
  <v-toolbar class="pa-2">
    <template #prepend>
      <div class="d-flex align-center">
        <AppLogo :height="50" white />
      </div>
    </template>

    <v-spacer />

    <div class="d-flex align-center ga-1">
      <!-- Language Switcher -->
      <LanguageSwitcher />

      <!-- Global User Account Dropdown -->
      <v-menu close-on-content-click>
        <template #activator="{ props: menuProps }">
          <v-btn
            icon="mdi-account-circle-outline"
            v-bind="menuProps"
            :aria-label="$t('nav.user_menu_aria')"
          />
        </template>

        <v-list width="220">
          <!-- Tenant User Links -->
          <v-list-item
            v-if="!authStore.is_serveradmin"
            to="/account/profile"
            prepend-icon="mdi-account-edit-outline"
            :title="$t('nav.my_profile')"
          />

          <v-list-item
            v-if="!authStore.is_serveradmin"
            to="/account/institution"
            :prepend-icon="authStore.is_conso_admin ? 'mdi-lan' : 'mdi-domain'"
            :title="authStore.is_conso_admin ? $t('routes.consortium_settings') : $t('routes.home_institution')"
          />

          <v-list-item
            v-if="!authStore.is_serveradmin"
            to="/account/api"
            prepend-icon="mdi-api"
            :title="$t('nav.api_settings')"
          />

          <!-- ServerAdmin Links -->
          <v-list-item
            v-if="authStore.is_serveradmin"
            to="/server/settings"
            prepend-icon="mdi-cog-outline"
            :title="$t('routes.server_settings')"
          />

          <v-list-item
            v-if="authStore.is_serveradmin"
            to="/server/mailsettings"
            prepend-icon="mdi-email-outline"
            :title="$t('routes.mail_settings')"
          />

          <v-list-item
            v-if="authStore.is_serveradmin"
            to="/server/api"
            prepend-icon="mdi-api"
            :title="$t('nav.api_settings')"
          />

          <v-divider class="my-1" />

          <v-list-item
            link
            prepend-icon="mdi-logout"
            :title="$t('nav.logout')"
            color="error"
            @click="apiLogout"
          />
        </v-list>


      </v-menu>

      <!-- System External COUNTER Links Menu -->
      <v-menu>
        <template #activator="{ props: menuProps }">
          <v-btn
            icon="mdi-dots-vertical"
            v-bind="menuProps"
            :aria-label="$t('nav.external_links_aria')"
          />
        </template>

        <v-list>
          <v-list-item
            v-for="(link, index) in moreLinks"
            :key="index"
            :title="link.title"
            :href="link.href || '#'"
            :prepend-icon="link.icon"
            slim
            v-external
          />
        </v-list>
      </v-menu>
    </div>
  </v-toolbar>
</template>

<script setup>
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useExternalLinks } from '@/composables/useExternalLinks';
import AppLogo from '@/components/ui/AppLogo.vue';
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue';

const authStore = useAuthStore();
const { moreLinks } = useExternalLinks();

function apiLogout() {
  authStore.logoutStateClear();
}
</script>
