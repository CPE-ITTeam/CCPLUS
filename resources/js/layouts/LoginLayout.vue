<!-- layouts/LoginLayout.vue -->
 
<template>
  <!-- 1. Outer canvas wraps everything in your universal light slate gray background -->
  <div class="bg-background w-100 pa-0"
    style="min-height: 100vh; display: flex; flex-direction: column; justify-content: center;">

    <!-- 2. Inner card container pulls directly from your global 'VCard' framework defaults again! -->

    <!-- This is default; use commented lines to test changes -->

    <v-card 
      class="my-3 mx-auto d-flex flex-column w-100" 
      max-width="1300" 
      style="min-height: calc(100vh - 24px); border: none; overflow: hidden;"
    >
    <!--
      flat variant="outlined"
      style="min-height: calc(100vh - 24px); border: 1px solid rgb(var(--v-theme-secondary)) !important; overflow: hidden;"
    -->
      <!-- 💡 INSULATED HEADER ELEMENT (Stays crisp white) -->
      <LoginHeader />

      <!-- 💡 2. CORE VIEW CONTENT BODY -->
      <!-- By placing 'bg-info' right here, your medium consortium blue stays trapped inside the center content view! -->
      <div class="flex-grow-1 bg-info" style="display: grid; place-items: center; padding: 2rem;">
        <div class="w-100">
          <router-view />
        </div>
      </div>

      <!-- 💡 INSULATED TIGHT FOOTER ELEMENT (Stays clean gray) -->
      <LoginFooter />

    </v-card>
  </div>
</template>

<script setup>
import LoginHeader from '@/components/navigation/LoginHeader.vue';
import LoginFooter from '@/components/navigation/LoginFooter.vue';
</script>
