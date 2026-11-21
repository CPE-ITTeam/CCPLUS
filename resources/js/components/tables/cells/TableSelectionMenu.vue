<!-- resources/js/components/tables/cells/TableSelectionMenu.vue -->

<template>
  <v-menu v-if="showMenu" location="bottom start">
    <template #activator="{ props: menuProps }">
      <button
        v-bind="menuProps"
        type="button"
        class="d-inline-flex align-center justify-center border-0 bg-transparent pa-0 cursor-pointer text-white"
        style="margin-inline-start: 18px;"
        :aria-label="$t('tables.selection_options', 'Selection options')"
        :title="$t('tables.selection_options', 'Selection options')"
      >
        <i
          :class="[
            'mdi',
            isAllSelected
              ? 'mdi-checkbox-marked'
              : isSomeSelected
                ? 'mdi-minus-box'
                : 'mdi-checkbox-blank-outline',
          ]"
          style="font-size: 20px; line-height: 1;"
          aria-hidden="true"
        />
        <i class="mdi mdi-menu-down" style="font-size: 16px; line-height: 1;" aria-hidden="true" />
      </button>
    </template>

    <v-list density="compact" min-width="200">
      <v-list-item
        prepend-icon="mdi-checkbox-marked-outline"
        :title="$t('tables.select_visible', { count: visibleCount }, `Select visible (${visibleCount})`)"
        :disabled="areAllVisibleSelected"
        @click="$emit('selectVisible')"
      />
      <v-list-item
        prepend-icon="mdi-checkbox-multiple-marked"
        :title="$t('tables.select_all_count', { count: totalCount }, `Select all (${totalCount})`)"
        :disabled="isAllSelected"
        @click="$emit('selectAll')"
      />
      <template v-if="selectedCount > 0">
        <v-divider class="my-1" />
        <v-list-item
          prepend-icon="mdi-close-box-outline"
          :title="$t('tables.clear_all', 'Clear selection')"
          @click="$emit('clear')"
        />
      </template>
    </v-list>
  </v-menu>

  <input
    v-else
    type="checkbox"
    :checked="isAllSelected"
    :indeterminate.prop="isSomeSelected"
    :aria-label="$t('common.select_all', 'Select All')"
    class="cursor-pointer"
    style="width: 18px; height: 18px; accent-color: white;"
    @change="$emit('toggleAll', $event.target.checked)"
  />
</template>

<script setup>
defineProps({
  showMenu: { type: Boolean, required: true },
  isAllSelected: { type: Boolean, required: true },
  isSomeSelected: { type: Boolean, required: true },
  areAllVisibleSelected: { type: Boolean, required: true },
  visibleCount: { type: Number, required: true },
  totalCount: { type: Number, required: true },
  selectedCount: { type: Number, required: true },
});

defineEmits(['selectVisible', 'selectAll', 'clear', 'toggleAll']);
</script>
