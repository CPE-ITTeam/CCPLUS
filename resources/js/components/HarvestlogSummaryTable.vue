<template>
  <div>
    <div v-if="harvests.length > 0">
      <v-data-table :headers="headers" :items="mutable_harvests" item-key="id" class="elevation-1"
                    :hide-default-footer="true" :server-items-length="10" dense :options="dt_options">
        <template v-slot:item.updated_at="{ item }">
          {{ item.updated_at.substr(0,10) }}
        </template>
        <template v-slot:item.details="{ item }">
          <a :href="'/harvests/'+item.id+'/edit'">details</a>
        </template>
      </v-data-table>
    </div>
    <div v-else>
      <p>No harvest records found for this institution</p>
    </div>
    <p class="more">
      <a :href="seemore_url">See all harvests</a>
    </p>
  </div>
</template>

<script>
  export default {
    props: {
            harvests: { type:Array, default: () => [] },
            inst_id: { type:Number, default: 0 },
            prov_id: { type:Number, default: 0 },
            inst_context: { type: Number, default: 1 }
          },
    data () {
      return {
        // Actual headers array is built from these in mounted()
        header_fields: [
          { label: 'Updated', name: 'updated_at' },
          { label: 'Institution', name: 'sushi_setting.institution.name' },
          { label: 'Platform', name: 'sushi_setting.provider.name' },
          { label: 'Report', name: 'report.name' },
          { label: 'Usage Date', name: 'yearmon' },
          { label: 'Attempts', name: 'attempts' },
          { label: 'Status', name: 'status' },
          { label: '', name: 'details' },
        ],
        headers: [],
        dt_options: {itemsPerPage:10, sortBy:['updated_at','sushi_setting.provider.name'], sortDesc:[false],
                     multiSort:true, mustSort:false},
        mutable_harvests: this.harvests,
        seemore_url: "/harvests",
      }
    },
    mounted() {
      if (this.inst_id>0 || this.prov_id>0) {
          this.seemore_url+='?';
          if (this.inst_id>0) this.seemore_url += 'institutions='+this.inst_id;
          if (this.prov_id>0) {
              if (this.inst_id>0) this.seemore_url+='&';
              this.seemore_url += 'providers='+this.prov_id;
          }
      }
      // Setup datatable headers
      this.header_fields.forEach((fld) => {
          if (fld.label == 'Institution') {
              if (this.int_context == 1) this.headers.push({ text: fld.label, value: fld.name });
          } else if (fld.name == 'details') {
            this.headers.push({ text: '', value: fld.name, sortable:false });
          } else {
            this.headers.push({ text: fld.label, value: fld.name });
          }
      });
      console.log('HarvestLogSummary Component mounted.');
    }
  }
</script>
<style>
</style>
