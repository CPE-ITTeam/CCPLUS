<template>
  <div>
    <h1>Usage Report Harvesting
      <span v-if="conso.length>0"> : {{ conso }}</span>
    </h1>
    <v-expansion-panels multiple focusable v-model="panels">
      <!-- Manual Harvest -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Manual Harvesting</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <p>&nbsp;</p>
          <p>Harvests may be manually added to the CC-Plus harvesting queue once settings are defined to connect
             platform services with one more institutions.
          </p>
          <p>The harvesting queue is automatically scanned on a preset interval established by the CC-Plus administrator.<br />
             The CC-Plus system processes all harvest requests on a first-in first-out basis.
             <h5>Note:</h5>
             <ul>
              <li>Requesting a manual harvest for a previously harvested platform, institition, and month,
                  will <strong>re-initialize the harvest as a new entry</strong> with zero attempts.</li>
              <li>On successful retrieval, manually harvested data will replace (overwrite) all previously
                  harvested report data for a given institution->platform->month.</li>
             </ul>
          </p>
          <manual-harvest :institutions="harvest_insts" :inst_groups="groups" :providers="harvest_provs" :all_reports="reports"
                          :presets="presets" @new-harvests="updateHarvests" @updated-harvests="updateHarvests"
          ></manual-harvest>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Job Queue -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Harvest Queue</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <harvestqueue-data-table :institutions="institutions" :groups="groups" :providers="providers" :reports="reports"
                                   :filters="job_filters" :key="queueKey"
          ></harvestqueue-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
      <!-- Harvest Log -->
      <v-expansion-panel>
        <v-expansion-panel-header>
          <h2>Harvest Log</h2>
        </v-expansion-panel-header>
        <v-expansion-panel-content>
          <harvestlog-data-table :harvests="harvests" :institutions="institutions" :groups="groups" :providers="providers"
                                 :reports="reports" :bounds="mutable_bounds" :filters="filters" :codes="codes"
                                 @restarted-harvest="updateHarvests"
          ></harvestlog-data-table>
        </v-expansion-panel-content>
      </v-expansion-panel>
    </v-expansion-panels>
  </div>
</template>
<script>
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            institutions: { type:Array, default: () => [] },
            groups: { type:Array, default: () => [] },
            providers: { type:Array, default: () => [] },
            reports: { type:Array, default: () => [] },
            bounds: { type:Array, default: () => [] },
            filters: { type:Object, default: () => {} },
            codes: { type:Array, default: () => [] },
            presets: { type:Object, default: () => {} },
            conso: { type:String, default: '' },
           },
    data () {
        return {
            failure: '',
            success: '',
            panels: [],
            harvest_provs: [],
            harvest_insts: [],
            mutable_bounds: [...this.bounds],
            job_filters: {'providers':[], 'institutions':[], 'groups':[], 'reports':[], 'yymms':[], 'statuses':[], 'codes':[]},
            queueKey: 1,
        }
    },
    methods: {
      updateHarvests (harvests) {
        // update only the HarvestQueue component; when new harvests are added, HarvestLogs
        // should still display the originally passed array since it won't need to show
        // anything changed/added by the ManualHarvest or HarvestQueue components.
        this.queueKey += 1;
      }
    },
    mounted() {
        this.harvest_provs = this.providers.filter(p => p.sushi_enabled);
        this.harvest_insts = [ ...this.institutions];

        // If inst/prov filters or presets passed in, force open a panel
        if (this.presets.inst_id != null || this.presets.prov_id != null) { // manual harvest presets
            this.panels = [0];
        } else if (this.filters.institutions.length > 0 || this.filters.providers.length > 0) { // harvestlogs filters
            this.panels = [2];
        }

        // Subscribe to store updates
        this.$store.subscribe((mutation, state) => { localStorage.setItem('store', JSON.stringify(state)); });
        console.log('Harvesting Component mounted.');
    }
  }
</script>
<style>
</style>
