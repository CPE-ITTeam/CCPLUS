<template>
  <div>
    <div v-if="harvests.length > 0">
      <v-data-table :headers="headers" :items="mutable_harvests" item-key="id" class="elevation-1"
                    :hide-default-footer="true" :server-items-length="10" dense disable-sort>
        <template v-slot:item="{ item }">
          <tr>
            <td>{{ item.updated_at.substr(0,10) }}</td>
            <td>{{ item.sushi_setting.institution.name }}</td>
            <td>{{ item.sushi_setting.provider.name }}</td>
            <td>{{ item.report.name }}</td>
            <td>{{ item.yearmon }}</td>
            <td>{{ item.attempts }}</td>
            <td>{{ item.status }}</td>
            <td v-if="item.attempts>0">
              <a :href="'/harvests/'+item.id+'/edit'">details</a>
            </td>
          </tr>
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
           },
    data () {
      return {
        headers: [
          { text: 'Updated', value: 'updated_at' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Platform', value: 'prov_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Usage Date', value: 'yearmon' },
          { text: 'Attempts', value: 'attempts' },
          { text: 'Status', value: 'status' },
          { text: '', value: '', sortable: false },
        ],
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
      console.log('HarvestLogSummary Component mounted.');
    }
  }
</script>
<style>
</style>
