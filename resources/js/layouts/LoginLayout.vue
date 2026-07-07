<script setup>
import { reactive } from "vue"

const epre = "https://connect.ebsco.com/s/article/Platform-Reference-List-"
const epost = "-Usage-Consolidation#"

const referenceMap = reactive({
  A: "A", B: "B", C: "C-G#C", D: "C-G#D", E: "C-G#E", F: "C-G#F", G: "C-G#G",
  H: "H", I: "I", J: "J",
  K: "K-P#K", L: "K-P#L", M: "K-P#M", N: "K-P#N", O: "K-P#O", P: "K-P#P",
  Q: null,
  R: "Q-S#R", S: "Q-S#S",
  T: "T-Z#T", U: "T-Z#U", V: "T-Z#V", W: "T-Z#W",
  X: null, Y: null,
  Z: "T-Z#Z"
})

const buildEbscoUrl = (ref) => {
  const [prefix, anchor] = ref.split("#")
  return `${epre}${prefix}${epost}${anchor || ""}`
}
</script>
<template>
  <v-app class="ccplus-app">
    <!-- HEADER WITH NAVBAR -->
    <v-app-bar class="ccplus-header" flat density="comfortable">
      <!-- Left: Logo -->
      <div class="header-left">
        <img src="/images/CC_Plus_Logo.png" alt="CC-PLUS KYVL" class="header-logo" href="https://ccplus.kyvl.org" target="_blank" />
      </div>
      <!-- Right: Navigation Menus -->
      <div class="header-nav">
        <!-- Documentation -->
        <v-menu open-on-hover>
          <template #activator="{ props }">
            <v-btn v-bind="props" variant="text">Documentation</v-btn>
          </template>
          <v-list>
            <v-list-item href="https://github.com/CPE-ITTeam/CCPLUS/blob/dev-vue3/docs/installation.markdown" 
                         target="_blank" title="Installation instructions" />
            <v-divider />
            <v-list-item href="https://kyvl.libwizard.com/f/ccplus-local" target="_blank" title="Institutional onboarding" />
            <v-list-item href="https://kyvl.org/ccplus/about" target="_blank" title="KYVL LibGuide (needs work)" />
          </v-list>
        </v-menu>
        <!-- COUNTER Links -->
        <v-menu open-on-hover>
          <template #activator="{ props }">
            <v-btn v-bind="props" variant="text">COUNTER Links</v-btn>
          </template>
          <v-list>
            <v-list-item href="https://cop5.countermetrics.org/" target="_blank" title="Code of Practice" />
            <v-divider />
            <v-list-item href="https://countermetrics.stoplight.io/docs/counter-sushi-api/au9uaf0yg84mo-counter-api" target="_blank"title="COUNTER API" />
            <v-divider />
            <v-list-item href="https://registry.countermetrics.org/" target="_blank" title="COUNTER Registry" />
            <v-list-item href="https://registry.countermetrics.org/api/v1/" target="_blank" title="Registry API Root" />
            <v-divider />
            <v-list-item href="https://www.countermetrics.org/code-of-practice/tools/" target="_blank" title="Tools and Services" />
            <v-list-item href="https://validator.countermetrics.org/" title="Validator" />
          </v-list>
        </v-menu>
        <!-- URL Finder -->
        <v-menu open-on-hover>
          <template #activator="{ props }">
            <v-btn v-bind="props" variant="text">URL Finder</v-btn>
          </template>
          <v-list>
            <v-list-item title="EBSCO Master Platform List" target="_blank"
              href="https://connect.ebsco.com/s/article/EBSCO-Usage-Consolidation-Master-Platform-List-and-Recent-Updates?language=en_US"
            />
            <!-- Dynamic reference list -->
            <v-list-item>
              <div class="reference-list">
                <v-chip v-for="(ref, letter) in referenceMap" :key="letter" :href="ref ? buildEbscoUrl(ref) : undefined"
                        :color="ref ? 'primary' : 'grey'" :variant="ref ? 'tonal' : 'outlined'" size="small" class="ma-1">
                  {{ letter }}
                </v-chip>
              </div>
            </v-list-item>
            <v-divider />
            <v-list-item href="https://www.counter51.info/" target="_blank" title="counter51.info - Celus" />
            <v-list-item href="https://knowledge.exlibrisgroup.com/Alma/Product_Documentation/010Alma_Online_Help_(English)/020Acquisitions/030Acquisitions_Infrastructure/010Managing_Vendors/SUSHI_Vendor_Lists" target="_blank" title="ExLibris SUSHI Vendor Lists" />
            <v-list-item href="https://jusp.jisc.ac.uk/counter-5-1-faq/" target="_blank" title="JISC Counter 5. FAQ" />
          </v-list>
        </v-menu>
        <!-- Demo / Dev -->
        <v-menu open-on-hover>
          <template #activator="{ props }">
            <v-btn v-bind="props" variant="text">Demo / Dev</v-btn>
          </template>
          <v-list>
            <v-list-item href="https://kyvl.libwizard.com/f/ccplus-trial" target="_blank" title="Trial request" />
            <v-divider />
            <v-list-item href="https://demo.ccplus.kyvl.org" target="_blank" title="Demo" />
            <v-list-item href="https://dev.ccplus.kyvl.org" target="_blank" title="Dev" />
          </v-list>
        </v-menu>
      </div>
    </v-app-bar>
    <main class="ccplus-main">
      <router-view />
    </main>
    <!-- FOOTER -->
    <footer class="ccplus-footer">
      <div class="footer-links">
        <a href="https://www.countermetrics.org/" target="_blank" rel="noopener">
          <img src="https://www.countermetrics.org/wp-content/themes/counter/images/counter-logo-new.svg" />
        </a>
        <a href="https://registry.countermetrics.org/" target="_blank" rel="noopener">
          <img src="https://registry.countermetrics.org/favicon.ico" />
        </a>
        <a href="https://github.com/CPE-ITTeam/CCPLUS/tree/dev-vue3" target="_blank" rel="noopener">
          <img src="https://github.githubassets.com/favicons/favicon.svg" />
        </a>
      </div>
    </footer>
  </v-app>
</template>
<style scoped>
body {
  background-color: rgba(var(--cc-alt-blue), .4);
}
.ccplus-app {
  border: 2px solid var(--cc-blue);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
/* HEADER */
.ccplus-header {
  width: 100%;
  display: flex;
  align-items: center;
  border-bottom: 2px solid var(--cc-blue);
  padding: 0 1rem;
  justify-content: space-between;
}
.header-left {
  display: flex;
  align-items: center;
}
.header-logo {
  height: 40px;
  width: auto;
}
.header-nav {
  display: flex;
  align-items: center;
  gap: 1rem;
}
/* MAIN */
.ccplus-main {
  flex: 1;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 2rem 0;
}
/* FOOTER */
.ccplus-footer {
  width: 100%;
  border-top: 2px solid var(--cc-blue);
  background-color: #f8f9fa;
  padding: 1rem 0;
  display: flex;
  justify-content: center;
}
.footer-links {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}
.footer-links img {
  height: 28px;
  width: auto;
}
</style>