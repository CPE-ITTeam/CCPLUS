<template>
  <div
    v-if="hasVisibleActions"
    class="d-flex align-center ga-1"
    :class="dataset === 'badCredentials' ? 'justify-center' : 'justify-end'"
  >
    <v-btn
      v-if="isCredentialDataset"
      icon="mdi-connection"
      color="info"
      :loading="isTestingRow"
      :title="$t('credentials.test_live', 'Test Live Credentials')"
      :aria-label="$t('credentials.test_live', 'Test Live Credentials')"
      @click="$emit('testCredential', item)"
    />

    <v-tooltip
      v-if="dataset === 'platforms' && (item.refresh_result === 'orphan' || item.refresh_result == null)"
      :text="item.refresh_text"
    >
      <template #activator="{ props: tooltipProps }">
        <v-btn
          :icon="item.refresh_icon"
          :color="item.refresh_color"
          v-bind="tooltipProps"
          :aria-label="$t('tables.platform_refresh_state_aria')"
        />
      </template>
    </v-tooltip>

    <v-tooltip v-else-if="item.refresh_result != null" :text="item.refresh_text">
      <template #activator="{ props: tooltipProps }">
        <v-btn
          :icon="item.refresh_icon"
          :color="item.refresh_color"
          v-bind="tooltipProps"
          :aria-label="$t('tables.platform_refresh_state_aria')"
          @click="$emit('openRegistry', item.registry_id)"
        />
      </template>
    </v-tooltip>

    <v-btn
      v-if="item.can_edit"
      icon="mdi-pencil"
      :title="$t('tables.edit_item_aria')"
      :aria-label="$t('tables.edit_item_aria')"
      @click="$emit('edit', item)"
    />

    <v-btn
      v-if="dataset !== 'badCredentials'"
      icon="mdi-delete"
      :color="item.can_delete ? 'error' : undefined"
      :disabled="!item.can_delete"
      :title="item.can_delete ? $t('tables.delete_item_aria') : $t('tables.delete_action_disabled')"
      :aria-label="item.can_delete ? $t('tables.delete_item_aria') : $t('tables.delete_action_disabled')"
      @click="$emit('delete', item)"
    />
  </div>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
  item: { type: Object, required: true },
  dataset: { type: String, required: true },
  showActions: { type: Boolean, default: false },
  isTesting: { type: Boolean, default: false },
  testingRowId: { type: [Number, String], default: null },
});

defineEmits(['testCredential', 'openRegistry', 'edit', 'delete']);

const isCredentialDataset = computed(
  () => props.dataset === 'credentials' || props.dataset === 'badCredentials'
);

const hasVisibleActions = computed(
  () => props.showActions || props.dataset === 'platforms' || isCredentialDataset.value
);

const isTestingRow = computed(() => {
  const rowCredId = props.dataset === 'credentials' ? props.item.id : props.item.credentials_id;
  return props.isTesting && props.testingRowId === rowCredId;
});
</script>
