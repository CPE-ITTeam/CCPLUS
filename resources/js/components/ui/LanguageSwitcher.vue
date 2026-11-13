<!-- components/ui/LanguageSwitcher.vue -->
<script setup>
import { setAppLocale } from '@/plugins/i18n.js';

defineProps({
  variant: {
    type: String,
    default: 'menu', // 'menu' for desktop header bar | 'drawer' for mobile v-list-group
  },
});

const languages = [
  { code: 'en', label: 'English' },
  { code: 'fi', label: 'Suomi' },
  { code: 'es', label: 'Español' },
  { code: 'fr', label: 'Français' },
  { code: 'de', label: 'Deutsch' },
  { code: 'hu', label: 'Magyar' },
];
</script>

<template>
  <!-- Mobile Drawer Nested List Group -->
  <v-list-group v-if="variant === 'drawer'" value="langGroup">
    <template #activator="{ props: openProps }">
      <v-list-item
        v-bind="openProps"
        :title="$t('nav.language')"
        prepend-icon="mdi-translate"
        slim
      />
    </template>

    <v-list-item
      v-for="lang in languages"
      :key="lang.code"
      :title="lang.label"
      :active="$i18n.locale === lang.code"
      slim
      class="ps-8"
      @click="setAppLocale(lang.code)"
    />
  </v-list-group>

  <!-- Desktop Header Menu Button -->
  <v-menu v-else>
    <template #activator="{ props: langProps }">
      <v-btn
        v-bind="langProps"
        prepend-icon="mdi-translate"
        class="text-uppercase"
        :aria-label="$t('nav.language')"
      >
        {{ $i18n.locale }}
      </v-btn>
    </template>

    <v-list density="compact">
      <v-list-item
        v-for="lang in languages"
        :key="lang.code"
        :title="lang.label"
        :active="$i18n.locale === lang.code"
        @click="setAppLocale(lang.code)"
      />
    </v-list>
  </v-menu>
</template>
