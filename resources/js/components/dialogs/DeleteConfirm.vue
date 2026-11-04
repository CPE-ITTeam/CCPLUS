<!-- components/dialogs/DeleteConfirm.vue -->
<script setup>
  const props = defineProps({
    dataset: { type:String, required: true },
    item: { type:Object, required: true },
  });
  const emit = defineEmits(['close', 'confirm']);
</script>
<template>
  <v-card>
    <v-card-title class="pa-6 d-flex justify-space-between align-center">
      <span>Confirm Delete</span>
      <v-tooltip text="Cancel" location="bottom">
        <template #activator="{ props }">
          <v-btn icon variant="outlined" class="close-btn" v-bind="props" @click="$emit('close')">
            <v-icon size="18">mdi-close</v-icon>
          </v-btn>
        </template>
      </v-tooltip>
    </v-card-title>
    <v-card-text>
      <p>&nbsp;</p>
      <p v-if="props.dataset=='connections'">
        <strong>You are about to delete <font color="red">ALL CONNECTIONS</font> to {{  item.platform }}</strong>
      </p>
      <p v-else-if="props.dataset=='consortia'">
        <strong>
          Deleting this consortium will completely remove the database and all associated data from the system,
          including all connections, harvests, and reports for all members of this consortium.<br/><br />
          <font color="red">WARNING: This action cannot be undone.</font>
        </strong>
      </p>
      <p v-else>
        <strong>You are about to delete {{ item.name }} from {{ props.dataset }}</strong>
      </p>
      <p>&nbsp;</p>
      <h3> Are you Sure?</h3>
      <p>&nbsp;</p>
      <v-row>
        <v-col cols="12" class="text-left">
          <v-btn color="primary" @click="$emit('confirm')">Yes, Delete it</v-btn>
          <v-btn variant="text" class="ml-2" @click="$emit('close')">Cancel</v-btn>
        </v-col>
      </v-row>
    </v-card-text>
  </v-card>
</template>
