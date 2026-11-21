<!-- components/tables/BulkActions.vue -->
<script setup>
import { ref, computed, nextTick } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, te } = useI18n();
const bulkOptions = defineModel({ type: Object, required: true });
const emit = defineEmits(['bulkAction']);

const option = ref(null);
const groupDialog = ref(false);
const deleteDialog = ref(false);
const groupingType = ref(null);
const addToGroupID = ref(null);
const newGroupName = ref('');
const activeActionContext = ref(null);

const localizedItems = computed(() =>
  (bulkOptions.value?.items || []).map((item) => ({
    title: te(`bulk.${item}`) ? t(`bulk.${item}`) : item,
    value: item,
  }))
);

function cancelDialog() {
  deleteDialog.value = false;
  groupDialog.value = false;
  activeActionContext.value = null;
}

function changeOption(val) {
  const selected = typeof val === 'object' && val !== null ? val.value : (val ?? option.value);
  if (!selected) return;
  activeActionContext.value = selected;

  if (selected === 'Create New Group' || selected === 'Add to Existing Group') {
    groupingType.value = selected === 'Create New Group' ? 'Create' : 'Add';
    groupDialog.value = true;
  } else if (selected === 'Delete') {
    deleteDialog.value = true;
  } else {
    emit('bulkAction', { action: selected });
    activeActionContext.value = null;
  }

  nextTick(() => {
    option.value = null;
  });
}


function groupFormSubmit() {
  if (groupingType.value === 'Add') {
    emit('bulkAction', { action: activeActionContext.value, group_id: addToGroupID.value });
    addToGroupID.value = null;
  } else {
    emit('bulkAction', { action: activeActionContext.value, name: newGroupName.value });
    newGroupName.value = '';
  }
  groupDialog.value = false;
  activeActionContext.value = null;
}

function confirmDelete() {
  emit('bulkAction', { action: 'Delete' });
  deleteDialog.value = false;
  activeActionContext.value = null;
}
</script>

<template>
  <div>
    <v-autocomplete
      v-model="option"
      :label="$t('tables.bulk_actions')"
      :items="localizedItems"
      item-title="title"
      item-value="value"
      @update:modelValue="changeOption"
    />

    <!-- LAYOUT PANEL: INSTITUTION GROUPS OPERATOR -->
    <v-dialog v-model="groupDialog" width="500">
      <v-card class="pa-6">
        <div class="d-flex justify-space-between align-center mb-4">
          <span class="text-h6 font-weight-bold">
            {{ groupingType === 'Create' ? $t('dialogs.create_group_title') : $t('dialogs.add_to_group_title') }}
          </span>
          <v-btn icon="mdi-close" variant="text" @click="cancelDialog" />
        </div>

        <v-form @submit.prevent="groupFormSubmit">
          <div class="mb-6">
            <v-text-field
              v-if="groupingType === 'Create'"
              v-model="newGroupName"
              :label="$t('common.name')"
              required
            />
            <v-select
              v-if="groupingType === 'Add'"
              v-model="addToGroupID"
              :items="bulkOptions.groups"
              item-title="name"
              item-value="id"
              :label="$t('datasets.groups.title')"
              required
            />
          </div>

          <div class="d-flex ga-3 justify-end">
            <v-btn color="primary" class="font-weight-bold text-none" @click="cancelDialog">
              {{ $t('common.cancel') }}
            </v-btn>
            <v-btn type="submit" class="font-weight-bold text-none px-6">
              {{ $t('common.submit') }}
            </v-btn>
          </div>
        </v-form>
      </v-card>
    </v-dialog>

    <!-- LAYOUT PANEL: BULK DELETION VERIFIER -->
    <v-dialog v-model="deleteDialog" width="450">
      <v-card class="pa-6 text-center">
        <div class="d-flex justify-space-between align-center mb-4">
          <span class="text-h6 font-weight-bold text-left">{{ $t('dialogs.confirm_delete') }}</span>
          <v-btn icon="mdi-close" variant="text" size="small" @click="cancelDialog" />
        </div>

        <p class="text-body-1 mb-2">
          {{ $t('dialogs.bulk_delete_warning', { dataset: bulkOptions.dataset }) }}
        </p>
        <h3 class="text-h5 font-weight-bold text-error mb-6">{{ $t('dialogs.are_you_sure') }}</h3>

        <div class="d-flex ga-3 justify-center">
          <v-btn class="font-weight-bold text-none" @click="cancelDialog">
            {{ $t('common.cancel') }}
          </v-btn>
          <v-btn color="error" variant="flat" class="font-weight-bold text-none px-6" @click="confirmDelete">
            {{ $t('dialogs.yes_delete_them') }}
          </v-btn>
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>
