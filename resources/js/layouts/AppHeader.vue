<!-- layouts/AppHeader.vue -->
<script setup>

import { useAuthStore } from '@/plugins/authStore.js';
import { useExternalLinks } from '@/composables/useExternalLinks';

const authStore = useAuthStore();
const { openCop, openCounterApi, openRegistry, openRegistryApi } = useExternalLinks();
// Set homeUrl based on role
const homeUrl = (authStore.is_serveradmin || authStore.is_admin) ? "/admin" : "/reports";
function apiLogout() { authStore.logout({}); };

</script>

<template>
  <v-toolbar color="#0066A1">
    <template v-slot:prepend>
      &nbsp; &nbsp; <span class="me-10 poppins-light">CC&#8209;PLUS</span>
    </template>
    <template v-slot:append>
      <v-toolbar-title>
        <v-btn id="logout" icon="mdi-logout" @click="apiLogout"></v-btn>
        <v-tooltip activator="#logout" location="top" >Log out</v-tooltip>
        <v-menu>
          <template v-slot:activator="{ props }">
            <v-btn id="more" icon="mdi-dots-vertical" v-bind="props"></v-btn>
            <v-tooltip activator="#more" location="top">COUNTER links</v-tooltip>
          </template>
          <v-list>
            <v-list-item @click="openCop" prepend-icon="mdi-file-document">
              <v-list-item-title>Code of Practice</v-list-item-title>
            </v-list-item>
            <v-list-item @click="openCounterApi" prepend-icon="mdi-api">
              <v-list-item-title>COUNTER API</v-list-item-title>
            </v-list-item>
            <v-list-item @click="openRegistry" prepend-icon="mdi-database">
              <v-list-item-title>Registry</v-list-item-title>
            </v-list-item>
            <v-list-item @click="openRegistryApi" prepend-icon="mdi-api">
              <v-list-item-title>Registry API</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-menu>
      </v-toolbar-title>
    </template>
  </v-toolbar>
</template>

