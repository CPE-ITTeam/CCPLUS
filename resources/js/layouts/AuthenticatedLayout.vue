<script setup>

  import { ref, onMounted, onBeforeMount, watch } from 'vue';
  import { useRouter,useRoute } from 'vue-router';
  import { useAuthStore } from '@/plugins/authStore.js';

  import AppHeader from './AppHeader.vue'
  // Data
  const activePage = ref(0);
  const activeTab = ref(0);
  const panel = ref([0, 1]);
  var homeUrl = ref("/");
  var userDialog = ref(false);
  var pages = ref([]);
  // Get vue router configuration
  const this_route = useRoute();
  const router = useRouter();
  const all_routes = [ ...router.options.routes ];
  // Pinia DataStore
  const authStore = useAuthStore();
  // These are intentionally NOT reactive...
  const is_admin = authStore.is_admin;
  const is_conso_admin = authStore.is_conso_admin;
  const is_serveradmin = authStore.is_serveradmin;
  // External links
  function userDialogDone ({ result, msg, user, new_inst }) {
    userDialog.value = false;
  }

  // Increment component key(s) for all components EXCEPT the one
  // responsible for changing the consoKey (it's already updated)
  function handleChangeConso(changed) {
    pages.value.forEach( page => {
      if (typeof(page.children) != 'undefined') {
        page.children.forEach( child => {
          if (typeof(child.component) != 'undefined') {
            if (child.name!=changed) { child.meta.key++; }
          }
          if (typeof(child.children) != 'undefined') {
            child.children.forEach( grandchild => {
              if (typeof(grandchild.component) != 'undefined') {
                if (grandchild.name!=changed) { grandchild.meta.key++; }
              }
            });
          }
        });
      }
    });
  }

  // Force to tab:0 on page switches
  watch(() => activePage.value, () => { activeTab.value = 0; });

  // Before Mount
  onBeforeMount(() => {
    // Restrict routes by role
    // **Expects** : Level-1 routes may have mixed roles for their (level-2) child routes.
    //             : Level-2 routes will NOT have mixed roles for their (level-3) child routes
    if ( is_serveradmin ) {
      pages.value = all_routes.filter( r => r.meta.level==1 );
    } else {
      all_routes.forEach( (topRoute) => {
        if (topRoute.meta.level == 1) {
          var theRoute = {...topRoute};
          if ( (is_admin && topRoute.meta.role == "Admin") || topRoute.meta.role == "Viewer") {
            theRoute.children = [];
            topRoute.children.forEach( (childRoute) => {
              if ( (childRoute.meta.role == "Admin" && is_admin) ||
                   (childRoute.meta.role == "ConsoAdmin" && is_conso_admin) ||
                   childRoute.meta.role == "Viewer") {
                theRoute.children.push(childRoute);
              }
            });
            if (topRoute.children.length==0 || theRoute.children.length>0) {
              pages.value.push({...theRoute});
            }
          }
        }
      });
    }
  });

  // On Mount
  onMounted(() => {
    // Set homeUrl based on role
    homeUrl.value = (is_serveradmin || is_admin) ? "/admin" : "/reports";
    console.log('Authenticated Layout Mounted');
  });
</script>

<template>
  <v-app>
    <v-card rounded="lg" class="my-3 mx-10 d-flex flex-column">
      <AppHeader />
      <v-tabs v-model="activePage" color="#0066A1" bg-color="blue-grey-lighten-5" align-tabs="center">
        <v-tab v-for="(page, index) in pages" :key="index" :value="index">
          {{ page.meta.title }}
        </v-tab>
      </v-tabs>
      <v-main>
        <v-card-title class="page-title">
          {{ pages[activePage].meta.title }} — {{pages[activePage].children[activeTab].meta.title }}
        </v-card-title>
        <v-card-text class="justify-center px-4">
          <v-tabs-window v-model="activePage">
            <v-tabs-window-item v-for="(page, pageIndex) in pages" :key="pageIndex" :value="pageIndex"
                                transition="false" reverse-transition="false">
              <v-tabs v-model="activeTab" bg-color="white" density="compact">
                <v-tab v-for="(child, cidx) in page.children" :key="cidx" :value="cidx" color="green">
                  {{ child.meta.title }}
                </v-tab>
              </v-tabs>
              <v-tabs-window v-model="activeTab">
                <v-tabs-window-item v-for="(child, tabIndex) in page.children" :key="tabIndex"
                                    :value="tabIndex" transition="false" reverse-transition="false">
                  <div v-if="child.children">
                    <v-expansion-panels multiple class="mt-6 rounded-lg" v-model="panel">
                      <v-expansion-panel v-for="(grandchild, gcidx) in child.children"
                                        :key="gcidx" class="rounded-lg border">
                        <v-expansion-panel-title>
                          {{ grandchild.meta.title }}
                        </v-expansion-panel-title>
                        <v-expansion-panel-text class="rounded-lg">
                          <component :is="grandchild.component" :key="grandchild.meta.key"
                                     @updateConso="handleChangeConso(child.name)" />
                        </v-expansion-panel-text>
                      </v-expansion-panel>
                    </v-expansion-panels>
                  </div>
                  <div v-else>
                    <component :is="child.component" :key="child.meta.key"
                               @updateConso="handleChangeConso(child.name)"/>
                  </div>
              </v-tabs-window-item>
              </v-tabs-window>
            </v-tabs-window-item>
          </v-tabs-window>
        </v-card-text>
      </v-main>

      <v-spacer></v-spacer>

      <v-dialog v-model="userDialog" content-class="ccplus-dialog">
        <user-dialog dtype="profile" :user="Props.auth.user" @user-complete="userDialogDone"></user-dialog>
      </v-dialog>
    </v-card>
  </v-app>
</template>
<style scoped>
 .activeItem {
   background-color: #9fc5f8;
 }
</style>
