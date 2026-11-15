<!-- components/dialogs/SaveReport.vue -->
<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseDialogLayout from './BaseDialogLayout.vue';

const { t } = useI18n();

const props = defineProps({
  saved_reports: { type: Array, required: true },
});

const emit = defineEmits(['save', 'cancel']);

const failure = ref('');
const saveId = ref(null);
const saveTitle = ref('');
const saveType = ref('Create');

function chooseExisting() {
  const rec = props.saved_reports.find((rpt) => rpt.id == saveId.value);
  if (typeof rec !== 'undefined') {
    saveTitle.value = rec.title;
  }
}

// Save emits the ID and Title string
function saveConfig() {
  if (saveTitle.value === '' && saveId.value === null) {
    failure.value = t('dialogs.name_required_to_save');
    return;
  }
  emit('save', { id: saveId.value, title: saveTitle.value });
}
</script>

<template>
  <BaseDialogLayout @close="emit('cancel')">
    <!-- Header Title Slot -->
    <template #title>
      {{ $t('dialogs.save_report_title') }}
    </template>

    <!-- Main Content Area -->
    <v-form @submit.prevent="saveConfig">
      <v-container class="pa-0">
        <v-row class="d-flex" no-gutters>
          <v-col class="d-flex">
            <v-radio-group :label="$t('dialogs.save_type')" v-model="saveType" inline hide-details="auto">
              <v-radio :label="$t('dialogs.create_new_saved_report')" value="Create" />
              <v-radio :label="$t('dialogs.update_existing_saved_report')" value="Update" />
            </v-radio-group>
          </v-col>
        </v-row>

        <v-row v-if="saveType == 'Create' || saveTitle.length > 0" class="d-flex pt-4" no-gutters>
          <v-col>
            <v-text-field
              v-model="saveTitle"
              :label="$t('dialogs.title_label')"
              variant="outlined"
              density="compact"
              hide-details="auto"
            />
          </v-col>
        </v-row>

        <v-row v-if="props.saved_reports.length > 0 && saveType == 'Update'" class="d-flex pt-4" no-gutters>
          <v-col>
            <v-autocomplete
              v-model="saveId"
              :items="props.saved_reports"
              :label="$t('datasets.savedreports.title')"
              item-title="title"
              item-value="id"
              variant="outlined"
              density="compact"
              hide-details="auto"
              @update:modelValue="chooseExisting()"
            />
          </v-col>
        </v-row>

        <v-row v-if="failure" class="d-flex pt-3" no-gutters>
          <v-col>
            <span class="text-error text-caption" role="alert" v-text="failure"></span>
          </v-col>
        </v-row>
      </v-container>

      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <!-- Footer Actions Slot -->
    <template #actions>
      <v-spacer />
      <v-btn color="primary" variant="flat" @click="saveConfig">
        {{ $t('common.save') }}
      </v-btn>
      <v-btn variant="text" @click="emit('cancel')">
        {{ $t('common.cancel') }}
      </v-btn>
    </template>
  </BaseDialogLayout>
</template>
