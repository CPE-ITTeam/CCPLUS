<script setup>

  import { ref, onMounted, onBeforeMount, watch, computed } from 'vue';
  import { useRouter,useRoute } from 'vue-router';
  import { useAuthStore } from '@/plugins/authStore.js';

  import AppHeader from './AppHeader.vue'
  // Data
  const activePage = ref(0);
  const activeTab = ref(0);
  var pages = ref([]);
  // Get vue router configuration
  const this_route = useRoute();
  const router = useRouter();
  const all_routes = [ ...router.options.routes ];
  // API route index-value is hardwired to 900 below
  const apiRoute = computed(() => {
    let theRoute = all_routes.find( route => route.name == 'API');
    return (theRoute) ? {...theRoute} : { path:'/', meta: { title:'API', level:0 } };
  });
  // Profile route index-value is hardwired to 999 below
  const profileRoute = computed(() => {
    let theRoute = all_routes.find( route => route.name == 'Profile');
    return (theRoute) ? {...theRoute} : { path:'/', meta: { title:'Profile', level:0 } };
  });
  // Pinia DataStore
  const authStore = useAuthStore();
  // These are intentionally NOT reactive...
  const is_admin = authStore.is_admin;
  const is_conso_admin = authStore.is_conso_admin;
  const is_group_admin = authStore.is_group_admin;
  const is_serveradmin = authStore.is_serveradmin;
  const emit = defineEmits(['updateConso']);

  // Detect when the path updates via forward/back browser history actions
  watch( () => this_route.path, (newPath, oldPath) => {
    // On changed path, update activePage and activeTab to match the new path
    if (newPath !== oldPath) {
      router.replace(newPath);
      let newParts = newPath.split("/");
      let newPage = pages.value.findIndex( page => page.path == "/"+newParts[1] );
      if ( newPage >= 0 ) {
        activePage.value = newPage;
        if ( newParts.length > 2) { // newPath points to a tab
          let newTab = pages.value[newPage].children.findIndex( child => child.path == newParts[2] );
          if ( newTab >= 0 ) {
            activeTab.value = newTab;
          }
        }
      }
    }
  });
  // Update tab changes from menu(s)
  function onTabChange(newTab) {
    let page = pages.value[activePage.value];
    if (page) {
      let path = page.path;
      path += (page.children.length == 0) ? "" : "/"+page.children[activeTab.value].path;
      // push path to router if new
      if (path != this_route.path) router.push(path);
    }
  }
  // Update page changes from menu(s)
  function onPageChange(newPage) {
    if (newPage == 900) {
      router.push(apiRoute.value.path);
      return;
    } else if (newPage == 999) {
      router.push(profileRoute.value.path);
      return;
    }
    let page = pages.value[newPage];
    if ( typeof(page) != 'undefined') {
      let path = page.path;
      path += (page.children.length == 0) ? "" : "/"+page.children[0].path
      if (path != this_route.path) {
        // push path to router if new
        router.push(path);
        activeTab.value = 0; //force to first tab on page change
      }
    }
  };

  // Before Mount - Build the pages array based on user role and route configuration
  // Routes marked level-0 or not-shown are excluded.
  onBeforeMount(() => {
    // Restrict routes by role
    // **Expects** : Level-1 routes may have mixed roles for their (level-2) child routes.
    //             : Level-2 routes will NOT have mixed roles for their (level-3) child routes
    all_routes.forEach( (topRoute) => {
      if (topRoute.meta.level == 1) { // level-1 routes have no "show" property, restrict by-role
        var theRoute = {...topRoute};
        if ( is_serveradmin ) {
          pages.value.push({...theRoute});
        } else if ( is_serveradmin || (is_admin && topRoute.meta.role == "Admin") || topRoute.meta.role == "Viewer") {
          theRoute.children = [];
          topRoute.children.forEach( (childRoute) => {
            if ( (childRoute.meta.role == "Admin" && is_admin) ||
                  (childRoute.meta.role == "ConsoAdmin" && is_conso_admin) ||
                  (childRoute.meta.role == "GroupAdmin" && is_group_admin) ||
                  childRoute.meta.role == "Viewer") {
              // Hide specific grandchild route(s)
              if (childRoute.name === 'Institutions') { // Hide groups if not "Admin" for at least one group
                const _gcx = childRoute.children.findIndex(gc => gc.name === 'InstitutionGroupsTable');
                childRoute.children[_gcx].show = is_group_admin;
              }
              theRoute.children.push(childRoute);
            }
          });
          if (topRoute.children.length==0 || theRoute.children.length>0) {
            pages.value.push({...theRoute});
          }
        }
      }
    });
  });
  onMounted(() => {
    // Initialize activePage and activeTab based on current_route path
    let parts = this_route.path.split("/");
    let curPage = pages.value.findIndex( page => page.path == "/"+parts[1] );
    if ( curPage >= 0 ) {
      activePage.value = curPage;
      if ( parts.length > 2) { // path points to a tab
        let curTab = pages.value[curPage].children.findIndex( child => child.path == parts[2] );
        if ( curTab >= 0 ) {
          activeTab.value = curTab;
        }
      }
    }
  });
</script>

<template>
  <v-app>
    <v-card rounded="lg" class="my-3 mx-10 d-flex flex-column">
      <AppHeader />
      <v-tabs v-model="activePage" color="#0066A1" bg-color="blue-grey-lighten-5" align-tabs="center"
              @update:model-value="onPageChange">
        <v-spacer></v-spacer> 
        <v-tab v-for="(page, index) in pages" :key="index" :value="index">
          {{ page.meta.title }}
        </v-tab>
        <!-- Add API icon as a tab using spacer -->
        <v-spacer></v-spacer>
        <v-tab key="900" value="900">
          <v-icon size="large" :title="apiRoute.meta.title">mdi-api</v-icon>
        </v-tab>
        <v-tab key="999" value="999">
          <v-icon :title="(authStore.user!=null) ? authStore.user.name+' '+profileRoute.meta.title
                                                 : profileRoute.meta.title">mdi-account</v-icon>
        </v-tab>
      </v-tabs>
      <!-- Component assignment for api and profile panels -->
      <v-main v-if="activePage==900">
        <v-tabs-window v-model="activePage" @update:model-value="onPageChange">
          <v-tabs-window-item key=900 value=900 transition="false" reverse-transition="false">
              <router-view :key="apiRoute.meta.key" />
          </v-tabs-window-item>
        </v-tabs-window>
      </v-main>
      <v-main v-else-if="activePage==999">
        <v-tabs-window v-model="activePage" @update:model-value="onPageChange">
          <v-tabs-window-item key=999 value=999 transition="false" reverse-transition="false">
              <router-view :key="profileRoute.meta.key" />
          </v-tabs-window-item>
        </v-tabs-window>
      </v-main>
      <v-main v-else>
        <v-card-title class="page-title">
          <span v-if="pages[activePage].children.length==0">{{ pages[activePage].meta.title }}</span>
          <span v-else>{{ pages[activePage].meta.title }} — {{pages[activePage].children[activeTab].meta.title }}</span>
        </v-card-title>
        <v-card-text v-if="pages[activePage].children.length==0" class="justify-center px-4">
          <router-view :key="profileRoute.meta.key" @updateConso="$emit('updateConso', $event)"/>
        </v-card-text>
        <v-card-text v-else class="justify-center px-4">
          <v-tabs-window v-model="activePage" @update:model-value="onPageChange">
            <v-tabs-window-item v-for="(page, pageIndex) in pages" :key="pageIndex" :value="pageIndex"
                              transition="false" reverse-transition="false">
              <v-tabs v-model="activeTab" bg-color="white" density="compact" @update:model-value="onTabChange">
                <template v-for="(child, cidx) in page.children" :key="cidx">
                  <v-tab v-if="child.show" :value="cidx" color="green">
                    {{ child.meta.title }}
                  </v-tab>
                </template>
              </v-tabs>
              <v-tabs-window v-model="activeTab" @update:model-value="onTabChange">
                <template v-for="(child, tabIndex) in page.children" :key="tabIndex">
                  <v-tabs-window-item v-if="child.show" :value="tabIndex" transition="false" reverse-transition="false">
                    <div v-if="child.children">
                      <v-expansion-panels multiple class="mt-6 rounded-lg" v-model="child.panels">
                        <template v-for="(grandchild, gcidx) in child.children" :key="gcidx">
                          <v-expansion-panel v-if="grandchild.show" class="rounded-lg border">
                            <v-expansion-panel-title>
                              {{ grandchild.meta.title }}
                            </v-expansion-panel-title>
                            <v-expansion-panel-text class="rounded-lg">
                              <router-view :key="grandchild.meta.key" @updateConso="$emit('updateConso', $event)"/>
                            </v-expansion-panel-text>
                          </v-expansion-panel>
                          </template>
                      </v-expansion-panels>
                    </div>
                    <div v-else>
                      <p>&nbsp;</p>
                      <router-view :key="child.meta.key" @updateConso="$emit('updateConso', $event)"/>
                    </div>
                  </v-tabs-window-item>
                </template>
              </v-tabs-window>
            </v-tabs-window-item>
          </v-tabs-window>
        </v-card-text>
      </v-main>

      <v-spacer></v-spacer>

    </v-card>
  </v-app>
</template>
<style scoped>
 .activeItem {
   background-color: #9fc5f8;
 }
</style>
