<!-- layouts/AuthenticatedLayout.vue -->

<script setup>
import { ref, onMounted, onBeforeMount, watch, computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/stores/useAuthStore.js';
import { useCCPlusStore } from '@/stores/useCCPlusStore.js';
import AppHeader from '@/components/navigation/AppHeader.vue';

// Local UI & Navigation States
const activePage = ref(0);
const activeTab = ref(0);
const drawer = ref(false);
const pages = ref([]);

// Router Hooks
const this_route = useRoute();
const router = useRouter();
const all_routes = [...router.options.routes];

// Extract specialized static routes safely
const profileRoute = computed(() => all_routes.find(r => r.path === '/')?.children?.find(r => r.path === '/profile') || { meta: { title: 'Profile' } });
const apiRoute = computed(() => all_routes.find(r => r.path === '/')?.children?.find(r => r.path === '/apiSettings') || { meta: { title: 'API Settings' } });

// Pinia DataStores
const authStore = useAuthStore();
const ccPlusStore = useCCPlusStore();
const { consortia } = storeToRefs(ccPlusStore);

// Role Guards
const is_admin = authStore.is_admin;
const is_conso_admin = authStore.is_conso_admin;
const is_group_admin = authStore.is_group_admin;
const is_serveradmin = authStore.is_serveradmin;

// Emits interchange config
const emit = defineEmits(['updateConso']);

// Global Switcher Local Mapping Value Reference
const globalConsoKey = ref(null);

// Detect when the path updates via forward/back browser history actions
watch(() => this_route.path, (newPath, oldPath) => {
  if (newPath !== oldPath) {
    let newParts = newPath.split("/");
    let newPage = pages.value.findIndex(page => page.path == "/" + newParts[1]);
    if (newPage >= 0) {
      activePage.value = newPage;
      if (newParts.length > 2) {
        let newTab = pages.value[newPage].children.findIndex(child => child.path == newParts[2]);
        if (newTab >= 0) {
          activeTab.value = newTab;
        }
      }
    } else if (newPath.includes('apiSettings')) {
      activePage.value = 900;
    } else if (newPath.includes('profile')) {
      activePage.value = 999;
    }
  }
});

// Update tab changes from menu(s)
function onTabChange(newTab) {
  let page = pages.value[activePage.value];
  if (page && page.children && page.children[activeTab.value]) {
    let path = page.path;
    path += (page.children.length == 0) ? "" : "/" + page.children[activeTab.value].path;
    if (path != this_route.path) router.push(path);
  }
}

// Update page changes from menu(s)
function onPageChange(newPage) {
  if (newPage === 900) {
    activePage.value = 900;
    router.push('/apiSettings');
  } else if (newPage === 999) {
    activePage.value = 999;
    router.push('/profile');
  } else {
    let page = pages.value[newPage];
    if (typeof (page) != 'undefined') {
      let path = page.path;
      path += (page.children.length == 0) ? "" : "/" + page.children[0].path;
      if (path != this_route.path) {
        router.push(path);
        activeTab.value = 0;
      }
    }
  }
}


async function handleGlobalConsoChange(event) {
  if (event && event.id && event.ccp_key) {
    try {
      await authStore.setConso(event.id, event.ccp_key);
      window.location.reload(); // Hot-reload context bounds to clear tenant models securely
    } catch (err) {
      console.error("Failed to re-route cluster connections:", err);
    }
  }
}


function forwardConsoSignal(event) {
  emit('updateConso', event);
}

// Sync selection models when asynchronous data hydrates from backend
watch(consortia, (newList) => {
  if (newList.length > 0 && authStore.ccp_key) {
    const currentActive = newList.find(c => c.ccp_key === authStore.ccp_key);
    if (currentActive) globalConsoKey.value = currentActive;
  }
}, { immediate: true });

// Before Mount role restricting routine
onBeforeMount(() => {
  all_routes.forEach((topRoute) => {
    if (topRoute.meta && topRoute.meta.level == 1) {
      var theRoute = { ...topRoute };
      if (is_serveradmin) {
        pages.value.push({ ...theRoute });
      } else if ((is_admin && topRoute.meta.role == "Admin") || topRoute.meta.role == "Viewer") {
        theRoute.children = [];
        if (topRoute.children) {
          topRoute.children.forEach((childRoute) => {
            if ((childRoute.meta.role == "Admin" && is_admin) ||
              (childRoute.meta.role == "ConsoAdmin" && is_conso_admin) ||
              (childRoute.meta.role == "GroupAdmin" && is_group_admin) ||
              childRoute.meta.role == "Viewer") {
              if (childRoute.name === 'Institutions' && childRoute.children) {
                const _gcx = childRoute.children.findIndex(gc => gc.name === 'InstitutionGroupsTable');
                if (_gcx >= 0) childRoute.children[_gcx].show = is_group_admin;
              }
              theRoute.children.push(childRoute);
            }
          });
        }
        if (!topRoute.children || topRoute.children.length == 0 || theRoute.children.length > 0) {
          pages.value.push({ ...theRoute });
        }
      }
    }
  });
});


onMounted(async () => {
  // Sync router tracking highlights with path states
  let parts = this_route.path.split("/");
  let curPage = pages.value.findIndex(page => page.path == "/" + parts[1]);
  if (curPage >= 0) {
    activePage.value = curPage;
    if (parts.length > 2 && pages.value[curPage].children) {
      let curTab = pages.value[curPage].children.findIndex(child => child.path == parts[2]);
      if (curTab >= 0) activeTab.value = curTab;
    }
  } else if (this_route.path.includes('apiSettings')) {
    activePage.value = 900;
  } else if (this_route.path.includes('profile')) {
    activePage.value = 999;
  }

  // Hydrate consortia datasets seamlessly for server admin dropdown allocations
  if (is_serveradmin) {
    try {
      await ccPlusStore.getConsortia();
      if (consortia.value.length > 0 && authStore.ccp_key) {
        const currentActive = consortia.value.find(c => c.ccp_key === authStore.ccp_key);
        if (currentActive) globalConsoKey.value = currentActive;
      }
    } catch (err) {
      console.error("Dashboard connection sync failed:", err);
    }
  }
});
</script>

<template>
  <!-- 💡 MOBILE SIDE NAVIGATION DRAWER -->
  <v-navigation-drawer v-model="drawer" temporary class="bg-surface d-md-none">
    <v-list density="compact" nav>
      <v-list-item v-for="(page, index) in pages" :key="index" :title="page?.meta?.title" :active="activePage === index"
        @click="onPageChange(index); drawer = false;" />
    </v-list>
  </v-navigation-drawer>

  <v-main style="min-height: 100vh;" class="w-100">
    <div class="d-flex flex-column w-100 pa-0">

      <v-card class="my-3 mx-auto d-flex flex-column w-100" max-width="1300" style="border: none; overflow: hidden;">
        <!-- App Navbar Title/Control Bar Header Slot (Now handles API and Account Actions) -->
        <AppHeader @toggle-drawer="drawer = !drawer" />

        <!-- ─── 🖥️ DESKTOP ONLY NAVIGATION TABS (Hidden on mobile via d-none d-md-flex) ─── -->
        <v-tabs v-model="activePage" align-tabs="center" class="d-none d-md-flex w-100"
          @update:model-value="onPageChange">
          <v-tab v-for="(page, index) in pages" :key="index" :value="index">
            {{ page?.meta?.title }}
          </v-tab>
        </v-tabs>

        <!-- VALIDATES ARRAY LOOKUPS SAFELY BEFORE RENDERING CORES -->
        <div v-if="pages && pages[activePage]">

          <!-- 💡 UNIFIED CONTROL HEADER TRACK -->
          <div
            class="d-flex flex-column flex-md-row align-stretch align-md-center justify-md-space-between ga-0 ga-md-3 px-6 pt-4 w-100">

            <!-- ─── 🏢 MOBILE & DESKTOP ENVIRONMENT SELECTOR ─── -->
            <div v-if="is_serveradmin && consortia.length > 0"
              class="w-100 flex-md-initial order-first order-md-last mb-2 mb-md-0"
              style="max-width: 100%; min-width: 280px; width: auto;">
              <v-autocomplete v-model="globalConsoKey" :label="$t('nav.active_environment')" :items="consortia"
                item-title="name" item-value="ccp_key" return-object bg-color="white" hide-details
                @update:modelValue="handleGlobalConsoChange" />

              <v-divider class="d-md-none mt-3" />
            </div>



            <!-- ─── 📱 MOBILE ONLY: RESPONSIVE DROPDOWN NAVIGATION MENU ─── -->
            <div class="d-md-none w-100 mt-2 mb-0">
              <v-menu close-on-content-click>
                <template #activator="{ props: menuProps }">
                  <v-btn v-bind="menuProps" variant="outlined" color="primary"
                    class="w-100 justify-space-between text-none" append-icon="mdi-chevron-down">
                    <span class="font-weight-bold">
                      {{ pages[activePage]?.meta?.title || $t('nav.navigate_dashboard') }} </span>
                  </v-btn>
                </template>

                <v-list width="100%">
                  <v-list-item v-for="(page, index) in pages" :key="index" :title="page?.meta?.title"
                    :active="activePage === index" :prepend-icon="page.path.includes('inst') ? 'mdi-bank-outline' :
                      page.path.includes('harv') ? 'mdi-cloud-download-outline' :
                        page.path.includes('rep') ? 'mdi-file-chart-outline' :
                          page.path.includes('user') ? 'mdi-account-group-outline' :
                            page.path.includes('alert') ? 'mdi-bell-outline' :
                              page.path.includes('conso') ? 'mdi-domain' : 'mdi-text-box-outline'
                      " @click="onPageChange(index)" />
                </v-list>
              </v-menu>
            </div>

            <!-- ─── 📊 FLUSH PAGE TITLE HEADER TEXT ─── -->
            <v-card-title class="page-title font-weight-bold pa-0 ma-0 mt-2 mt-md-0 order-last order-md-first">
              <span v-if="!pages[activePage].children || pages[activePage].children.length == 0">
                {{ pages[activePage]?.meta?.title }}
              </span>
              <span v-else-if="pages[activePage].children[activeTab]">
                {{ pages[activePage].children[activeTab]?.meta?.title }}
              </span>
            </v-card-title>

          </div>
          <!-- Master children data tables layout views -->
          <v-card-text v-if="!pages[activePage].children || pages[activePage].children.length == 0">
            <router-view :key="$route.fullPath" @updateConso="forwardConsoSignal" />
          </v-card-text>

          <v-card-text v-else class="pt-0">
            <v-tabs-window v-model="activePage" @update:model-value="onPageChange">
              <v-tabs-window-item v-for="(page, pageIndex) in pages" :key="pageIndex" :value="pageIndex">

                <v-tabs v-model="activeTab" bg-color="white" density="compact" @update:model-value="onTabChange">
                  <template v-for="(child, cidx) in page?.children" :key="cidx">
                    <v-tab v-if="child?.show" :value="cidx" color="green">
                      {{ child?.meta?.title }}
                    </v-tab>
                  </template>
                </v-tabs>

                <v-tabs-window v-model="activeTab" @update:model-value="onTabChange">
                  <template v-for="(child, tabIndex) in page?.children" :key="tabIndex">
                    <v-tabs-window-item v-if="child?.show" :value="tabIndex">

                      <div v-if="child?.children">
                        <v-expansion-panels multiple class="mt-6 rounded-lg" v-model="child.panels">
                          <template v-for="(grandchild, gcidx) in child.children" :key="gcidx">
                            <v-expansion-panel v-if="grandchild?.show" class="rounded-lg border">
                              <v-expansion-panel-title>
                                {{ grandchild?.meta?.title }}
                              </v-expansion-panel-title>
                              <v-expansion-panel-text class="rounded-lg">
                                <router-view :key="grandchild?.meta?.key" @updateConso="forwardConsoSignal" />
                              </v-expansion-panel-text>
                            </v-expansion-panel>
                          </template>
                        </v-expansion-panels>
                      </div>

                      <div v-else>
                        <router-view class="mt-4" :key="$route.fullPath" @updateConso="forwardConsoSignal" />
                      </div>

                    </v-tabs-window-item>
                  </template>
                </v-tabs-window>

              </v-tabs-window-item>
            </v-tabs-window>
          </v-card-text>
        </div> <!-- Closes the initial <div v-if="pages && pages[activePage]"> block -->

      </v-card>

    </div>
  </v-main>
</template>
