<!-- resources/js/components/panels/ManualHarvestPanel.vue -->
<template>
    <SettingsPanelShell v-if="consoKey !== ''" class="mt-4" :title="$t('routes.manual_harvest')"
        icon="mdi-cloud-download-outline" :loading="loading" :success="success" :failure="failure" @submit="formSubmit">
        <div v-if="!optionsLoaded" class="d-flex justify-center align-center py-8">
            <v-progress-circular indeterminate color="primary" />
        </div>
        <v-alert v-else-if="institution_options.length === 0" type="warning" variant="tonal"
            :text="$t('harvest.no_institutions')" />
        <v-alert v-else-if="available_platforms.length === 0" type="warning" variant="tonal"
            :text="$t('harvest.no_platforms')" />

        <v-row v-else class="mt-0">

            <!-- COLUMN 1: Platforms, then Institutions / Institution Groups -->
            <v-col cols="12" md="4" class="d-flex flex-column ga-3">
                <SectionHeading class="mb-1">
                    <v-icon start size="20" class="me-2" aria-hidden="true">mdi-target</v-icon>
                    {{ $t('harvest.scope_and_platforms') }}
                </SectionHeading>

                <MultiSelectCombobox v-model="form.plat" :items="available_platforms" :label="$t('routes.platforms')"
                    item-value="id" item-title="name" @update:model-value="onPlatChange" />

                <template v-if="institution_options.length > 1">
                    <InstitutionScopeSwitch
                        v-if="inst_groups.length > 0"
                        v-model="useInstGroups"
                        class="mb-n2"
                        @update:model-value="onScopeModeChange"
                    />

                    <MultiSelectCombobox v-if="!useInstGroups" v-model="form.inst" :items="institution_options"
                        :label="$t('routes.institutions')" item-value="id" item-title="name"
                        @update:model-value="onInstChange" />

                    <MultiSelectCombobox v-else v-model="group_ids" :items="inst_groups"
                        :label="$t('routes.institution_groups')" item-value="id" item-title="name"
                        @update:model-value="onGroupChange" />
                </template>

                <v-text-field v-else :model-value="inst_name" :label="$t('fields.institution')" readonly />
            </v-col>


            <!-- COLUMN 2: Reports and Dates -->
            <v-col cols="12" md="4" class="d-flex flex-column ga-3">
                <SectionHeading class="mb-1">
                    <v-icon start size="20" class="me-2" aria-hidden="true">mdi-calendar-range</v-icon>
                    {{ $t('harvest.reports_and_period') }}
                </SectionHeading>

                <v-sheet
                    class="border border-opacity-25 rounded bg-transparent px-3 d-flex flex-column justify-center mb-3"
                    style="min-height: 48px;">
                    <div class="d-flex align-center justify-space-between" style="min-height: 48px;">
                        <strong class="text-subtitle-2 text-primary text-uppercase font-weight-bold">
                            {{ $t('harvest.reports_to_harvest') }}
                        </strong>
                        <v-switch v-model="selectAllAvailableReports"
                            :label="$t('harvest.all_available_reports', 'All Available')" color="primary"
                            @update:model-value="onToggleAllReportsSwitch" />
                    </div>

                    <template v-if="!selectAllAvailableReports">
                        <v-divider class="mt-0 mb-2 border-opacity-25" />
                        <div class="ps-4 pb-2 compact-checkbox-list">
                            <v-checkbox v-for="rpt in master_reports" :key="rpt.name" v-model="form.reports"
                                :value="rpt.name" density="compact" color="primary" hide-details class="ma-0">
                                <template #label>
                                    <span class="ms-2">{{ rpt.legend || rpt.name }}</span>
                                </template>
                            </v-checkbox>
                        </div>
                    </template>

                </v-sheet>

                <YmRangeSelector v-model="harvestDates" :fy-start="fyMo" default-preset="fYTD" />
            </v-col>

            <!-- COLUMN 3: Queue Options -->
            <v-col cols="12" md="4" class="d-flex flex-column ga-3">
                <SectionHeading class="mb-1">
                    <v-icon start size="20" class="me-2" aria-hidden="true">mdi-cog-play-outline</v-icon>
                    {{ $t('harvest.queue_options') }}
                </SectionHeading>

                <div>
                    <div class="text-label-medium text-uppercase font-weight-bold text-medium-emphasis mb-1">
                        {{ $t('harvest.queue_timing') }}
                    </div>
                    <v-radio-group v-model="form.when" inline hide-details>
                        <v-radio :label="$t('harvest.now')" value="now" />
                        <v-radio :label="$t('harvest.overnight')" value="later" />
                    </v-radio-group>
                </div>

                <v-switch v-model="form.skip_harvested" :label="$t('harvest.skip_previously_harvested')" :true-value="1"
                    :false-value="0" />
            </v-col>
        </v-row>

        <template v-if="submit_enabled || selections_made" #actions>
            <v-btn v-if="submit_enabled" type="submit" color="primary" class="px-6" :loading="loading">
                {{ $t('common.submit') }}
            </v-btn>

            <v-btn v-if="selections_made" variant="tonal" color="secondary" @click="resetForm">
                {{ $t('harvest.reset_selections') }}
            </v-btn>
        </template>
    </SettingsPanelShell>
</template>

<script setup>
import { ref, computed, watch, onBeforeMount } from 'vue';
import { useI18n } from 'vue-i18n';
import api from '@/services/api.js';
import { useAuthStore } from '@/stores/useAuthStore.js';
import SettingsPanelShell from '@/components/ui/SettingsPanelShell.vue';
import YmRangeSelector from '@/components/ui/YmRangeSelector.vue';
import MultiSelectCombobox from '@/components/ui/MultiSelectCombobox.vue';
import InstitutionScopeSwitch from '@/components/ui/InstitutionScopeSwitch.vue';

const { t } = useI18n();
const authStore = useAuthStore();
const group_ids = ref([]);
const useInstGroups = ref(false);

defineOptions({ name: 'ManualHarvestPanel', inheritAttrs: false });
const emit = defineEmits(['newHarvests', 'updatedHarvests', 'updateConso']);

const consoKey = computed(() => authStore.ccp_key);
const loading = ref(false);
const optionsLoaded = ref(false);
const success = ref('');
const failure = ref('');
const inst_name = ref('');
const allPlats = ref(false);
const allConsoPlats = ref(false);
const allInsts = ref(false);
const institutions = ref([]);
const institution_options = ref([]);
const inst_groups = ref([]);
const selectedInstitutions = ref([]);
const platforms = ref([]);
const available_platforms = ref([]);
const master_reports = ref([]);
const available_reports = ref([]);
const fields = ref([]);
const fyMo = ref(null);
const group_id = ref(null);
const selectAllAvailableReports = ref(true);

const form = ref({
    inst: [],
    inst_group_id: 0,
    plat: [],
    reports: [],
    fromYM: '',
    toYM: '',
    when: 'now',
    skip_harvested: 1,
});

const harvestDates = computed({
    get: () => ({ fromYM: form.value.fromYM, toYM: form.value.toYM }),
    set: (val) => {
        form.value.fromYM = val?.fromYM || '';
        form.value.toYM = val?.toYM || '';
    },
});

function onToggleAllReportsSwitch(val) {
    if (val) {
        form.value.reports = master_reports.value.map((r) => r.name);
    } else {
        form.value.reports = [];
    }
}

const initializeOptions = async () => {
    try {
        const response = await api.get('/api/harvests/options');
        const records = response?.data?.records ?? response?.records ?? {};
        institutions.value = [...(records.institutions || [])];
        institution_options.value = [...(records.institutions || [])];
        inst_groups.value = [...(records.groups || [])];
        platforms.value = [...(records.platforms || [])];
        available_platforms.value = [...(records.platforms || [])];
        master_reports.value = [...(records.master_reports || [])];
        fields.value = [...(records.fields || [])];
        fyMo.value = records.fyMo ?? null;

        if (selectAllAvailableReports.value) {
            form.value.reports = master_reports.value.map((r) => r.name);
        }

        if (institutions.value.length === 1) {
            inst_name.value = institutions.value[0].name;
            form.value.inst = [institutions.value[0].id];
            onInstChange();
        }
    } catch (error) {
        console.error('Error loading harvest options:', error.message);
    } finally {
        optionsLoaded.value = true;
    }
};

const selections_made = computed(() => selectedInstitutions.value.length > 0 || form.value.plat.length > 0);
const submit_enabled = computed(
    () =>
        form.value.plat.length > 0 &&
        form.value.reports.length > 0 &&
        selectedInstitutions.value.length > 0 &&
        form.value.fromYM !== '' &&
        form.value.toYM !== ''
);

function resetForm() {
    selectAllAvailableReports.value = true;
    useInstGroups.value = false;
    form.value = {
        inst: institutions.value.length === 1 ? [institutions.value[0].id] : [],
        inst_group_id: 0,
        plat: [],
        reports: master_reports.value.map((r) => r.name),
        fromYM: '',
        toYM: '',
        when: 'now',
        skip_harvested: 1,
    };
    group_ids.value = [];
    available_reports.value = [];
    allInsts.value = false;
    allPlats.value = false;
    allConsoPlats.value = false;
    available_platforms.value = [...platforms.value];
    selectedInstitutions.value = institutions.value.length === 1 ? [institutions.value[0].id] : [];
    success.value = '';
    failure.value = '';
}


function onScopeModeChange(isGroups) {
    failure.value = '';
    if (isGroups) {
        form.value.inst = [];
        allInsts.value = false;
        selectedInstitutions.value = [];
    } else {
        group_ids.value = [];
        form.value.inst_group_id = 0;
        selectedInstitutions.value = [];
    }
    available_platforms.value = [...platforms.value];
    onPlatChange(true);
}


function onGroupChange() {
    failure.value = '';
    form.value.inst_group_id = group_ids.value.length === 1 ? group_ids.value : 0;
    form.value.inst = [];
    selectedInstitutions.value = [];

    if (group_ids.value.length === 0) {
        available_platforms.value = [...platforms.value];
    } else {
        const instSet = new Set();
        group_ids.value.forEach((gid) => {
            const group = inst_groups.value.find((g) => g.id === gid);
            if (group?.institutions) {
                group.institutions.forEach((inst) => instSet.add(inst.id));
            }
        });
        selectedInstitutions.value = [...instSet];
        if (group_ids.value.length > 1) {
            form.value.inst = [...selectedInstitutions.value];
        }
        updatePlatforms();
    }
    onPlatChange(true);
}

function onInstChange() {
    failure.value = '';
    group_ids.value = [];
    form.value.inst_group_id = 0;
    allInsts.value = form.value.inst.length === institutions.value.length && institutions.value.length > 0;
    if (allInsts.value) {
        form.value.inst = institutions.value.map((ii) => ii.id);
    }
    selectedInstitutions.value = [...form.value.inst];
    if (form.value.inst.length === 0) {
        available_platforms.value = [...platforms.value];
    } else {
        updatePlatforms();
    }
    onPlatChange(true);
}

function updatePlatforms() {
    if (selectedInstitutions.value.length === 0) {
        available_platforms.value = [...platforms.value];
    } else {
        available_platforms.value = platforms.value.filter((plat) =>
            plat.institutions.some((id) => id === 1 || selectedInstitutions.value.includes(id))
        );
    }
}

function onPlatChange(preserveChecked = false) {
    failure.value = '';
    const conso_list = platforms.value.filter((p) => p.institutions.some((id) => id === 1)).map((p2) => p2.id);
    allPlats.value = platforms.value.length === form.value.plat.length && form.value.plat.length > 0;
    allConsoPlats.value = !allPlats.value && conso_list.length === form.value.plat.length && form.value.plat.length > 0;

    const plat_list = allPlats.value
        ? platforms.value.map((p) => p.id)
        : allConsoPlats.value
            ? [...conso_list]
            : [...form.value.plat];

    const prevReports = preserveChecked ? [...form.value.reports] : [];
    form.value.reports = selectAllAvailableReports.value ? master_reports.value.map((r) => r.name) : [];
    available_reports.value = [];
    if (plat_list.length === 0) return;

    const hasInstSelection = selectedInstitutions.value.length > 0;

    plat_list.forEach((pid) => {
        const cur_plat = platforms.value.find((p) => p.id === pid);
        if (!cur_plat?.reports) return;
        master_reports.value.forEach((rpt) => {
            if (available_reports.value.some((r) => r.name === rpt.name) || !cur_plat.reports[rpt.name]) return;
            const rptMeta = cur_plat.reports[rpt.name];
            const add =
                rptMeta.conso ||
                (!hasInstSelection && Array.isArray(rptMeta.insts) && rptMeta.insts.length > 0) ||
                (hasInstSelection && Array.isArray(rptMeta.insts) && rptMeta.insts.some((inst) => selectedInstitutions.value.includes(inst)));
            if (add) available_reports.value.push(rpt);
        });
    });

    if (!selectAllAvailableReports.value && preserveChecked && prevReports.length > 0) {
        const validNames = new Set(available_reports.value.map((r) => r.name));
        form.value.reports = prevReports.filter((name) => validNames.has(name));
    }
}

async function formSubmit() {
    if (form.value.reports.length === 0) {
        failure.value = t('harvest.no_reports_selected');
        return;
    }
    if (!form.value.fromYM || !form.value.toYM) {
        failure.value = t('harvest.date_range_required');
        return;
    }

    loading.value = true;
    success.value = '';
    failure.value = '';

    try {
        const response = await api.post('/api/harvests/store', { ...form.value });
        const resData = response?.data !== undefined ? response.data : response;
        if (resData?.result) {
            success.value = resData.msg;
            if (resData.new_harvests?.length > 0) {
                emit('newHarvests', { harvests: resData.new_harvests, bounds: resData.bounds });
            }
            if (resData.upd_harvests?.length > 0) {
                emit('updatedHarvests', resData.upd_harvests);
            }
        } else {
            failure.value = resData?.msg || t('harvest.store_failed');
        }
    } catch (error) {
        console.error('Error queuing harvests:', error.message);
        failure.value = t('harvest.store_failed');
    } finally {
        loading.value = false;
    }
}

function updateAllPlats(scope) {
    if (scope === 'Clear') {
        allPlats.value = false;
        allConsoPlats.value = false;
        form.value.plat = [];
        available_platforms.value = [...platforms.value];
    } else if (scope === 'ALL') {
        allPlats.value = true;
        allConsoPlats.value = false;
        form.value.plat = platforms.value.map((p) => p.id);
        available_platforms.value = [...platforms.value];
    } else {
        allPlats.value = false;
        allConsoPlats.value = true;
        form.value.plat = platforms.value.filter((plat) => plat.institutions.some((id) => id === 1)).map((p2) => p2.id);
        available_platforms.value = platforms.value.filter((p) => form.value.plat.includes(p.id));
    }
    onPlatChange();
}

function updateAllInsts() {
    group_id.value = null;
    form.value.inst_group_id = 0;
    if (allInsts.value) {
        allInsts.value = false;
        form.value.inst = [];
    } else {
        allInsts.value = true;
        form.value.inst = institutions.value.map((ii) => ii.id);
    }
    selectedInstitutions.value = [...form.value.inst];
    updatePlatforms();
    onPlatChange(true);
}

watch(
    () => authStore.ccp_key,
    () => {
        resetForm();
        initializeOptions();
    }
);

onBeforeMount(() => {
    initializeOptions();
});
</script>

<style scoped>
.compact-checkbox-list :deep(.v-checkbox) {
  --v-input-control-height: 24px;
}
.compact-checkbox-list :deep(.v-selection-control) {
  min-height: 24px;
}
</style>

