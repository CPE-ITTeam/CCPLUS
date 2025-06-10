<template>
  <div>
    <h1>Reports
      <span v-if="conso.length>0"> : {{ conso }}</span>
    </h1>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Saved Reports -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>My Reports</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <saved-reports-data-table :reports="reports" :counter_reports="counter_reports" :filters="filters"
                                    :fy_month="fy_month"
          ></saved-reports-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Harvest Log -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>COUNTER Report Types</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <view-reports :counter_reports="counter_reports"></view-reports>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  export default {
    props: {
      reports: { type:Array, default: () => [] },
      counter_reports: { type:Array, default: () => [] },
      filters: { type:Object, default: () => {} },
      conso: { type:String, default: '' },
      fy_month: { type: Number, default: 1 },
    },
    data () {
        return {
            panels: [],
            queueKey: 1,
        }
    },
    methods: {
    },
    mounted() {
        // Subscribe to store updates
        this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
        console.log('Reports Component mounted.');
    }
  }
</script>
<style>
</style>
