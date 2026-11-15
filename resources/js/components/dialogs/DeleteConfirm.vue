<!-- components/dialogs/DeleteConfirm.vue -->
<script setup>
import BaseDialogLayout from './BaseDialogLayout.vue';

const props = defineProps({
  dataset: { type: String, required: true },
  item: { type: Object, required: true },
});

defineEmits(['close', 'confirm']);
</script>

<template>
  <BaseDialogLayout
    content-class="pt-6 pb-2 text-center text-sm-left"
    elevation="3"
    style="--v-shadow-color: var(--v-theme-info);"
    @close="$emit('close')"
  >
    <!-- Header Title Slot -->
    <template #title>
      {{ $t('dialogs.confirm_delete') }}
    </template>

    <!-- Content Slot -->
    <v-form @submit.prevent="$emit('confirm')">
      <i18n-t
        v-if="props.dataset === 'connections'"
        keypath="dialogs.delete_connections_warning"
        tag="div"
        class="text-subtitle-1 mb-4"
      >
        <template #target>
          <strong class="text-error text-uppercase">{{ $t('dialogs.all_connections') }}</strong>
        </template>
        <template #platform>
          <strong>{{ item.platform }}</strong>
        </template>
      </i18n-t>

      <i18n-t
        v-else
        keypath="dialogs.delete_item_warning"
        tag="div"
        class="text-subtitle-1 mb-4"
      >
        <template #item>
          <strong>{{ item.name }}</strong>
        </template>
        <template #dataset>
          <strong>{{ props.dataset }}</strong>
        </template>
      </i18n-t>

      <h3 class="text-h6 font-weight-bold mb-4">{{ $t('dialogs.are_you_sure_short') }}</h3>

      <button type="submit" class="d-none" aria-hidden="true"></button>
    </v-form>

    <!-- Footer Actions Slot -->
    <template #actions>
      <div class="px-4 pb-4 w-100 d-flex justify-start">
        <v-btn
          color="error"
          variant="flat"
          @click="$emit('confirm')"
        >
          {{ $t('dialogs.yes_delete_it') }}
        </v-btn>
        <v-btn
          variant="text"
          class="ml-2"
          @click="$emit('close')"
        >
          {{ $t('common.cancel') }}
        </v-btn>
      </div>
    </template>
  </BaseDialogLayout>
</template>
