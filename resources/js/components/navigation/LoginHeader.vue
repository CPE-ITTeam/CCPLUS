<!-- components/navigation/LoginHeader.vue -->
<template>
  <div class="bg-surface px-4 d-flex align-center w-100" style="border-bottom: 1px solid var(--v-theme-primary);">
    <div class="ms-2 mb-1 mt-4">
      <!-- Standard colored logo with no background used by default -->
      <AppLogo :height="50" />
    </div>

    <!-- Forces all inner buttons to render as clean, visible primary blue text links natively -->
    <v-defaults-provider :defaults="{ VBtn: { color: 'primary', variant: 'text' } }">
      <div class="ms-5 d-flex align-center flex-grow-1 justify-end justify-md-space-between">

        <!-- 🖥️ 1. DESKTOP ONLY NAVIGATION ROW (Hidden on mobile via 'd-none d-md-flex') -->
        <div class="d-none d-md-flex align-center ga-4">
          <!-- Documentation Menu -->
          <v-menu open-on-hover>
            <template #activator="{ props: menuProps }">
              <v-btn v-bind="menuProps" class="text-none">Documentation</v-btn>
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
              <v-btn v-bind="menuProps" class="text-none">COUNTER Links</v-btn>
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
              <v-btn v-bind="menuProps" class="text-none">URL Finder</v-btn>
            </template>
            <v-list width="320">
              <template v-for="(link, index) in urlFinderLinks" :key="index">

                <!-- Layout Row A: Custom Alphabet Chip Row Block -->
                <v-list-item v-if="link.type === 'chips'" class="py-2">
                  <div class="reference-list d-flex flex-wrap justify-start">
                    <v-chip v-for="(ref, letter) in referenceMap" :key="letter"
                      :href="ref ? buildEbscoUrl(ref) : undefined" :color="ref ? 'primary' : 'grey'"
                      :variant="ref ? 'tonal' : 'outlined'" size="small" class="ma-1" v-external>
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
              <v-btn v-bind="menuProps" class="text-none">Demo / Dev</v-btn>
            </template>
            <v-list>
              <template v-for="(link, index) in demoLinks" :key="index">
                <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external />
                <v-divider v-if="link.divider" />
              </template>
            </v-list>
          </v-menu>

        </div>

        <!-- 🖥️ DESKTOP ONLY TRIAL BUTTON (Hidden on mobile via 'd-none d-md-block') -->
        <div class="d-none d-md-block">
          <v-btn :href="LINKS.ccTrial || '#'" color="pink" variant="outlined"
            class="text-none text-uppercase font-weight-bold" v-external>
            <v-icon class="me-2">mdi-monitor-shimmer</v-icon>
            Request Trial
          </v-btn>
        </div>

        <!-- 📱 2. MOBILE ONLY HAMBURGER (Includes complete nested expansion trees) -->
        <div class="d-md-none">
          <v-menu :close-on-content-click="false">
            <template #activator="{ props: mobileMenuProps }">
              <v-btn icon="mdi-menu" variant="text" color="primary" v-bind="mobileMenuProps"></v-btn>
            </template>
            <v-list width="290">

              <!-- 1. Documentation Nested Tree -->
              <v-list-group value="docGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item v-bind="openProps" title="Documentation" prepend-icon="mdi-file-document-outline" slim />
                </template>

                <template v-for="(link, index) in docLinks" :key="index">
                  <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external
                    class="ps-8" />
                  <v-divider v-if="link.divider" class="ms-8" />
                </template>
              </v-list-group>


              <!-- 2. COUNTER Links Nested Tree -->
              <v-list-group value="counterGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item v-bind="openProps" title="COUNTER Links" prepend-icon="mdi-database-outline" slim />
                </template>

                <template v-for="(link, index) in counterLinks" :key="index">
                  <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external
                    class="ps-8" />
                  <v-divider v-if="link.divider" class="ms-8" />
                </template>
              </v-list-group>


              <!-- 3. URL Finder Nested Tree (Includes Alpha Chips layout scaling) -->
              <v-list-group value="urlGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item v-bind="openProps" title="URL Finder" prepend-icon="mdi-link-variant" slim />
                </template>

                <template v-for="(link, index) in urlFinderLinks" :key="index">

                  <!-- Mobile Row A: Custom Alphabet Chips Row Block -->
                  <v-list-item v-if="link.type === 'chips'" class="ps-8 py-2">
                    <div class="reference-list d-flex flex-wrap justify-start">
                      <v-chip v-for="(ref, letter) in referenceMap" :key="letter"
                        :href="ref ? buildEbscoUrl(ref) : undefined" :color="ref ? 'primary' : 'grey'"
                        :variant="ref ? 'tonal' : 'outlined'" size="small" class="ma-1" v-external>
                        {{ letter }}
                      </v-chip>
                    </div>
                  </v-list-item>

                  <!-- Mobile Row B: Standard Content Link -->
                  <v-list-item v-else :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim
                    v-external class="ps-8" />

                  <v-divider v-if="link.divider" class="ms-8" />

                </template>
              </v-list-group>

              <!-- 4. Demo / Dev Nested Tree -->
              <v-list-group value="devGroup">
                <template #activator="{ props: openProps }">
                  <v-list-item v-bind="openProps" title="Demo / Dev" prepend-icon="mdi-flask-outline" slim />
                </template>

                <template v-for="(link, index) in demoLinks" :key="index">
                  <v-list-item :title="link.title" :href="link.href || '#'" :prepend-icon="link.icon" slim v-external
                    class="ps-8" />
                  <v-divider v-if="link.divider" class="ms-8" />
                </template>
              </v-list-group>

              <!-- Bottom Trial request Action Hook -->
              <v-divider class="my-1" />
              <v-list-item title="Request Trial" :href="LINKS.ccTrial || '#'" prepend-icon="mdi-monitor-shimmer"
                class="text-pink font-weight-bold" v-external slim />
            </v-list>
          </v-menu>
        </div>

      </div>
    </v-defaults-provider>
  </div>
</template>

<script setup>
import { useExternalLinks } from '@/composables/useExternalLinks';
import AppLogo from "@/components/ui/AppLogo.vue";

const { LINKS, counterLinks, demoLinks, docLinks, urlFinderLinks } = useExternalLinks();

const referenceMap = {
  A: "A", B: "B", C: "C-G#C", D: "C-G#D", E: "C-G#E", F: "C-G#F", G: "C-G#G",
  H: "H", I: "I", J: "J",
  K: "K-P#K", L: "K-P#L", M: "K-P#M", N: "K-P#N", O: "K-P#O", P: "K-P#P",
  Q: null, R: "Q-S#R", S: "Q-S#S", T: "T-Z#T", U: "T-Z#U", V: "T-Z#V", W: "T-Z#W",
  X: null, Y: null, Z: "T-Z#Z"
};

const buildEbscoUrl = (ref) => {
  if (!ref) return undefined

  const [prefix, anchor] = ref.split("#")

  try {
    // 1. Instantiating a native URL object securely guarantees the domain path structure holds
    const masterUrl = new URL(LINKS.ebscoUsage || 'https://ebsco.com')

    // 2. Safely capture the domain origin (e.g. "https://connect.ebsco.com")
    const origin = masterUrl.origin

    // 3. Assemble the explicit targeted article path explicitly
    const baseUrl = `${origin}/s/article/Platform-Reference-List-${prefix}-Usage-Consolidation`
    const localeQuery = "?language=en_US"
    const elementHash = anchor ? `#${anchor}` : ""

    return baseUrl + localeQuery + elementHash
  } catch (e) {
    // Solid hardcoded fallback string array logic in case the composable URL structure is corrupt
    return `https://ebsco.com{prefix}-Usage-Consolidation?language=en_US${anchor ? '#' + anchor : ''}`
  }
}
</script>
