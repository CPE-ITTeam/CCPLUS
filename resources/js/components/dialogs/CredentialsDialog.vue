<!-- components/dialogs/CredentialsDialog.vue -->
<script setup>
  import { ref, reactive, computed, onMounted } from 'vue';
  import { useAuthStore } from '@/plugins/authStore.js';

  const props = defineProps({
    initialValues: {
      validator: (value) => { // validator allows initialValues to be string or object
        const isString = typeof value === 'string';
        const isObject = typeof value === 'object' && value !== null;
        if (!isString && !isObject) {
          console.warn('Invalid prop: "initialValues" must be a string or an object.');
        }
        return isString || isObject;
      }
    },
    schema: { type: Object, required: true },
  });
  const { ccPost } = useAuthStore();
  const formRef = ref();
  const opType = ref(props.schema.type);
  var formValues = reactive({...props.initialValues});
  var inst_options = ref([]);
  var plat_options = ref([]);
  var success = ref('');
  var failure = ref('');
  var showTest = ref(false);
  var testData = ref('');
  var testStatus = ref('');
  // Put fields in an by-name object based on the input schema prop
  const formFields = computed(() => {
    var fields = {};
    props.schema.fields.forEach ( (fld) => {
      if ( (opType.value=='Add' && props.schema.requiredKeys.includes(fld.name)) ||
           (opType.value == 'Edit' && !fld.static) ) {
        fields[fld.name] = {...fld};
      }
    })
    return fields;
  });
  const requiredRule = (v) => !!v || 'This field is required';
  const emit = defineEmits(['submit', 'cancel']);

  async function testSettings (type) {
    failure.value = '';
    success.value = '';
    testData.value = '';
    testStatus.value = "... Working ...";
    showTest.value = true;

    // Setup test arguments
    var testArgs = {'type' : type, 'prov_id' : formValues['prov_id']};
    props.schema.options['all_connectors'].forEach ( (cnx) => {
      if (requiredCnx.value[cnx.name]) {
        testArgs[cnx.name] = formValues[cnx.name];
      }
    });
    // Run test, display outcome
    const response = await ccPost('/api/credentials/test', testArgs);
    testStatus.value = response.msg;
    if (response.result) {
      testData.value = response.rows;
    } else {
      testData = (response.rows.length>0) ? response.rows : "Test Failed";
    }
  }

  const curPlatform = computed(() => {
    return (formValues['prov_id']>0) ? props.schema.options['platforms'].find( p => p.id == formValues['prov_id'])
                                     : null;
  });

  const requiredCnx = computed(() => {
    var requiredMap = {};
    props.schema.options['all_connectors'].forEach(cnx => requiredMap[cnx.name]=false);
    return (curPlatform.value == null) ? {'customer_id':false, 'requestor_id':false, 'api_key':false, 'extra_args':false}
                                       : curPlatform.value['connectors'];
  });

  function submitForm() {
     if (formRef.value?.validate()) {
      emit('submit', formValues);
    }
  }
  onMounted(() => {
    if (opType.value == 'Edit') {
      inst_options.value = props.schema.options['institutions'].filter( inst => inst.id==formValues['inst_id']);
      plat_options.value = props.schema.options['platforms'].filter( plat => plat.id==formValues['prov_id']);
    } else {
      inst_options.value = [...props.schema.options['institutions']];
      plat_options.value = [...props.schema.options['platforms']];
    }
});
</script>
<template>
  <div>
    <v-form ref="formRef" @submit.prevent="submitForm">
      <v-container>
        <v-row class="ma-0 py-1 justify-center" no-gutters>
          <v-col v-if="inst_options.length==1" class="d-flex px-2" cols="5">
            <strong>{{ inst_options[0]['name'] }}</strong>
          </v-col>
          <v-col v-else class="d-flex ma-0 pa-0" cols="5">
            <v-autocomplete v-model="formValues['inst_id']" :items="inst_options" :label="formFields['institutions'].label"
                            density="compact" variant="outlined" :rules="[requiredRule]" item-title="name" item-value="id"
            ></v-autocomplete>
          </v-col>
          <v-col cols="2" class="d-flex justify-center"> &lt;&lt; -- &gt;&gt; </v-col>
          <v-col v-if="plat_options.length==1" class="d-flex px-2" cols="5">
            <strong>{{ plat_options[0]['name'] }}</strong>
          </v-col>
          <v-col v-else class="d-flex px-2" cols="5">
            <v-autocomplete :items="plat_options" v-model="formValues['prov_id']" :label="formFields['platforms'].label"
                            density="compact" variant="outlined" :rules="[requiredRule]" item-title="name" item-value="id"
            ></v-autocomplete>
          </v-col>
        </v-row>
        <template v-for="cnx in props.schema.options['all_connectors']">
          <v-row class="my-1 pa-0" no-gutters>
            <v-col v-if="(requiredCnx[cnx.name] && typeof(formValues[cnx.name])!='undefined')" class="d-flex px-2" cols="10">
              <v-text-field v-model="formValues[cnx.name]" :label='cnx.label' :id='cnx.name' variant="outlined"
                            :rules="[requiredRule]" clearable></v-text-field>
            </v-col>
          </v-row>
        </template>
        <div v-if="success || failure" class="status-message">
          <span v-if="success" class="good" role="alert" v-text="success"></span>
          <span v-if="failure" class="fail" role="alert" v-text="failure"></span>
        </div>
        <v-row class="d-flex mt-2" no-gutters>
          <v-col class="d-flex px-2 justify-center">
            <v-btn color="primary" @click="submitForm">Save</v-btn>
          </v-col>
          <v-col class="d-flex px-2 justify-center">
            <v-btn variant="text" class="ml-2" @click="emit('cancel')">Cancel</v-btn>
          </v-col>
        </v-row>
        <div v-if="showTest">
          <div>{{ testStatus }}</div>
          <div v-for="row in testData">{{ row }}</div>
        </div>
      </v-container>
    </v-form>
    <v-row v-if="formValues['inst_id']!=null && formValues['prov_id']!=null" class="d-flex mt-1 mx-2">
      <v-col class="d-flex px-2 justify-center">
        <v-btn color="secondary" @click="testSettings('status')">Service Status</v-btn>
      </v-col>
      <v-col class="d-flex px-2 justify-center">
        <v-btn color="secondary" @click="testSettings('test')">Test Credentials</v-btn>
      </v-col>
    </v-row>
  </div>
</template>
<style scoped>
</style>
