<!-- components/navigation/LoginHeader.vue -->
<script setup>
import { useExternalLinks } from '@/composables/useExternalLinks';
import AppLogo from '@/components/ui/AppLogo.vue';
import LanguageSwitcher from '@/components/ui/LanguageSwitcher.vue';

const { LINKS, counterLinks, demoLinks, docLinks, urlFinderLinks } = useExternalLinks();

const referenceMap = {
  A: 'A', B: 'B', C: 'C-G#C', D: 'C-G#D', E: 'C-G#E', F: 'C-G#F', G: 'C-G#G',
  H: 'H', I: 'I', J: 'J',
  K: 'K-P#K', L: 'K-P#L', M: 'K-P#M', N: 'K-P#N', O: 'K-P#O', P: 'K-P#P',
  Q: null, R: 'Q-S#R', S: 'Q-S#S', T: 'T-Z#T', U: 'T-Z#U', V: 'T-Z#V', W: 'T-Z#W',
  X: null, Y: null, Z: 'T-Z#Z',
};

const buildEbscoUrl = (ref) => {
  if (!ref) return undefined;

  const [prefix, anchor] = ref.split('#');
  const articlePath = `/s/article/Platform-Reference-List-${prefix}-Usage-Consolidation?language=en_US${anchor ? `#${anchor}` : ''}`;

  try {
    const masterUrl = new URL(LINKS.ebscoUsage || 'https://ebsco.com');
    return `${masterUrl.origin}${articlePath}`;
  } catch {
    return `https://ebsco.com${articlePath}`;
  }
};
</script>

<template>
  <div class="bg-surface px-4 d-flex align-center w-100" style="border-bottom: 1px solid var(--v-theme-primary);">
    <div class="ms-2 mb-1 mt-4">
      <AppLogo :height="50" />
    </div>

    <v-defaults-provider :defaults="{ VBtn: { color: 'primary', variant: 'text' } }">
      <div class="ms-5 d-flex align-center flex-grow-1 justify-end justify-md-space-between">

        <!-- 🖥️ 1. DESKTOP ONLY NAVIGATION ROW (Hidden on mobile via 'd-none d-md-flex') -->
        <div class="d-none d-md-flex align-center ga-4">
          <!-- Documentation Menu -->
          <v-menu open-on-hover>
            <template #activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" class="text-none">{{ $t('nav.documentation') }}</v-btn>
            </template>
            <v-list>
              <template v-for="(link, index) in docLinks" :key="index">
                <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external />
                <v-divider v-if="link.divider" />
              </template>
            </v-list>
          </v-menu>

          <!-- COUNTER Links Menu -->
          <v-menu open-on-hover>
            <template #activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" class="text-none">{{ $t('nav.counter_links') }}</v-btn>
            </template>
            <v-list>
              <template v-for="(link, index) in counterLinks" :key="index">
                <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external />
                <v-divider v-if="link.divider" />
              </template>
            </v-list>
          </v-menu>

          <!-- URL Finder Menu -->
          <v-menu open-on-hover>
            <template #activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" class="text-none">{{ $t('nav.url_finder') }}</v-btn>
            </template>
            <v-list width="320">
              <template v-for="(link, index) in urlFinderLinks" :key="index">

                <!-- Layout Row A: Custom Alphabet Chip Row Block -->
                <v-list-item v-if="link.type === 'chips'" class="py-2">
                  <div class="reference-list d-flex flex-wrap justify-start">
                    <v-chip
                      v-for="(ref, letter) in referenceMap"
                      :key="letter"
                      :href="ref ? buildEbscoUrl(ref) : undefined"
                      :color="ref ? 'primary' : 'grey'"
                      :variant="ref ? 'tonal' : 'outlined'"
                      size="small"
                      class="ma-1"
                      v-external
                    >
                      {{ letter }}
                    </v-chip>
                  </div>
                </v-list-item>

                <!-- Layout Row B: Standard Content Link -->
                <v-list-item v-else :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external />

                <!-- Appended Separator Row -->
                <v-divider v-if="link.divider" />

              </template>
            </v-list>
          </v-menu>

          <!-- Demo / Dev Menu -->
          <v-menu open-on-hover>
            <template #activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" class="text-none">{{ $t('nav.demo_dev') }}</v-btn>
            </template>
            <v-list>
              <template v-for="(link, index) in demoLinks" :key="index">
                <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external />
                <v-divider v-if="link.divider" />
              </template>
            </v-list>
          </v-menu>
        </div>

        <!-- 🖥️ DESKTOP ONLY LANGUAGE SWITCHER + TRIAL BUTTON -->
        <div class="d-none d-md-flex align-center ga-2">
          <LanguageSwitcher />
          <v-btn
            :href="LINKS.ccTrial || '#'"
            color="pink"
            variant="outlined"
            class="text-none text-uppercase font-weight-bold"
            v-external
          >
            <v-icon class="me-2">mdi-monitor-shimmer</v-icon>
            {{ $t('nav.request_trial') }}
          </v-btn>
        </div>
        <!-- 📱 2. MOBILE ONLY HAMBURGER (Includes complete nested expansion trees) -->
        <div class="d-md-none">
          <v-menu :close-on-content-click="false">
            <template #activator="{ props: mobileMenuProps }">
              <v-btn
                icon="mdi-menu"
                variant="text"
                color="primary"
                :aria-label="$t('nav.documentation')"
                v-bind="mobileMenuProps"
              />
            </template>
            <v-list width="290">

              <!-- 1. Documentation Nested Tree -->
              <v-list-group value="docGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item
                    v-bind="openProps"
                    :title="$t('nav.documentation')"
                    prepend-icon="mdi-file-document-outline"
                    slim
                  />
                </template>

                <template v-for="(link, index) in docLinks" :key="index">
                  <v-list-item
                    :title="link.title"
                    :href="link.href || '#'"
                    :prepend-icon="link.icon"
                    slim
                    v-external
                    class="ps-8"
                  />
                  <v-divider v-if="link.divider" class="ms-8" />
                </template>
              </v-list-group>

              <!-- 2. COUNTER Links Nested Tree -->
              <v-list-group value="counterGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item
                    v-bind="openProps"
                    :title="$t('nav.counter_links')"
                    prepend-icon="mdi-database-outline"
                    slim
                  />
                </template>

                <template v-for="(link, index) in counterLinks" :key="index">
                  <v-list-item
                    :title="link.title"
                    :href="link.href || '#'"
                    :prepend-icon="link.icon"
                    slim
                    v-external
                    class="ps-8"
                  />
                  <v-divider v-if="link.divider" class="ms-8" />
                </template>
              </v-list-group>

              <!-- 3. URL Finder Nested Tree (Includes Alpha Chips layout scaling) -->
              <v-list-group value="urlGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item
                    v-bind="openProps"
                    :title="$t('nav.url_finder')"
                    prepend-icon="mdi-link-variant"
                    slim
                  />
                </template>

                <template v-for="(link, index) in urlFinderLinks" :key="index">

                  <!-- Mobile Row A: Custom Alphabet Chips Row Block -->
                  <v-list-item v-if="link.type === 'chips'" class="ps-8 py-2">
                    <div class="reference-list d-flex flex-wrap justify-start">
                      <v-chip
                        v-for="(ref, letter) in referenceMap"
                        :key="letter"
                        :href="ref ? buildEbscoUrl(ref) : undefined"
                        :color="ref ? 'primary' : 'grey'"
                        :variant="ref ? 'tonal' : 'outlined'"
                        size="small"
                        class="ma-1"
                        v-external
                      >
                        {{ letter }}
                      </v-chip>
                    </div>
                  </v-list-item>

                  <!-- Mobile Row B: Standard Content Link -->
                  <v-list-item
                    v-else
                    :title="link.title"
                    :href="link.href || '#'"
                    :prepend-icon="link.icon"
                    slim
                    v-external
                    class="ps-8"
                  />

                  <v-divider v-if="link.divider" class="ms-8" />

                </template>
              </v-list-group>

              <!-- 4. Demo / Dev Nested Tree -->
              <v-list-group value="devGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item
                    v-bind="openProps"
                    :title="$t('nav.demo_dev')"
                    prepend-icon="mdi-flask-outline"
                    slim
                  />
                </template>

                <template v-for="(link, index) in demoLinks" :key="index">
                  <v-list-item
                    :title="link.title"
                    :href="link.href || '#'"
                    :prepend-icon="link.icon"
                    slim
                    v-external
                    class="ps-8"
                  />
                  <v-divider v-if="link.divider" class="ms-8" />
                </template>
              </v-list-group>

              <!-- 5. Reusable Language Switcher -->
              <LanguageSwitcher variant="drawer" />

              <!-- Bottom Trial request Action Hook -->
              <v-divider class="my-1" />
              <v-list-item
                :title="$t('nav.request_trial')"
                :href="LINKS.ccTrial || '#'"
                prepend-icon="mdi-monitor-shimmer"
                class="text-pink font-weight-bold"
                v-external
                slim
              />
            </v-list>
          </v-menu>
        </div>

      </div>
    </v-defaults-provider>
  </div>
</template>
