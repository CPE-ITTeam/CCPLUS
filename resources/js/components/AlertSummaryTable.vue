<template>
  <div>
    <!-- Data table for data/harvest alerts -->
    <h2>Failed Harvest Alerts</h2>
    <v-data-table :headers="headers" :items="alerts" item-key="id" class="elevation-1"
                  :hide-default-footer="true" :server-items-length="5" dense>
      <template v-slot:item="{ item }">
        <tr>
          <td>{{ item.status }}</td>
          <td>{{ item.yearmon }}</td>
          <td>{{ item.prov_name }}</td>
          <td>{{ item.inst_name }}</td>
    		  <td>{{ item.report_name }}</td>
          <td>{{ item.updated }}</td>
		      <td><a :href="item.detail_url">Harvest details</a></td>
        </tr>
      </template>
    </v-data-table>
	<p class="more">
      <a href="/alerts">See all alerts</a>
	</p>
  </div>
</template>

<script>
  export default {
    props: {
            alerts: { type:Array, default: () => [] },
           },
    data () {
      return {
        headers: [
          { text: 'Status', value: 'status' },
          { text: 'Year-Month', value: 'yearmon' },
          { text: 'Provider', value: 'prov_name' },
          { text: 'Institution', value: 'inst_name' },
          { text: 'Report', value: 'report_name' },
          { text: 'Last Updated', value: 'updated' },
          { text: '', value: '', sortable: false },
        ],
      }
    },
    mounted() {
      console.log('AlertData Component mounted.');
    }
  }
</script>
