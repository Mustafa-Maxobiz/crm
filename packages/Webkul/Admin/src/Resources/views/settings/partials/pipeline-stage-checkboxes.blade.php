@props([
    'pipelines' => collect(),
    'selectedStages' => [],
])

@php
    $pipelinesPayload = $pipelines->map(fn ($pipeline) => [
        'id'     => $pipeline->id,
        'name'   => $pipeline->name,
        'stages' => $pipeline->stages->map(fn ($stage) => [
            'id'   => $stage->id,
            'name' => $stage->name,
            'code' => $stage->code,
        ])->values()->all(),
    ])->values()->all();

    $selectedPayload = collect($selectedStages)->map(fn ($stage) => [
        'id'        => (int) (is_array($stage) ? ($stage['id'] ?? 0) : $stage->id),
        'is_shared' => (bool) (is_array($stage)
            ? ($stage['is_shared'] ?? false)
            : ($stage->pivot->is_shared ?? false)),
    ])->filter(fn ($stage) => $stage['id'] > 0)->values()->all();
@endphp

<v-role-pipeline-stages
    :pipelines='@json($pipelinesPayload)'
    :initial-selected='@json($selectedPayload)'
>
    <div class="space-y-3">
        <div class="shimmer h-11 w-full rounded-md"></div>
        <div class="shimmer h-24 w-full rounded-md"></div>
    </div>
</v-role-pipeline-stages>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-role-pipeline-stages-template"
    >
        <div class="space-y-4">
            <div>
                <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
                    @lang('admin::app.settings.roles.create.pipeline')
                </label>

                <select
                    v-model="selectedPipelineId"
                    class="custom-select w-full rounded-md border border-gray-200 bg-white px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                >
                    <option value="">
                        @lang('admin::app.settings.roles.create.select-pipeline')
                    </option>
                    <option
                        v-for="pipeline in pipelines"
                        :key="pipeline.id"
                        :value="String(pipeline.id)"
                    >
                        @{{ pipeline.name }}
                    </option>
                </select>
            </div>

            <div v-if="currentStages.length">
                <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white">
                    @lang('admin::app.settings.roles.create.pipeline-stages')
                </p>

                <div class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="stage in currentStages"
                        :key="stage.id"
                        class="flex flex-col gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700"
                    >
                        <span class="flex items-center gap-2 dark:text-white">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                :checked="isSelected(stage.id)"
                                @change="toggleStage(stage.id, $event.target.checked)"
                            />
                            <span>@{{ stage.name }}</span>
                        </span>

                        <span
                            v-if="isSelected(stage.id)"
                            class="flex items-center gap-2 pl-6 text-xs text-gray-600 dark:text-gray-400"
                        >
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                :checked="isShared(stage.id)"
                                @change="toggleShared(stage.id, $event.target.checked)"
                            />
                            <span>@lang('admin::app.settings.roles.create.stage-shared')</span>
                        </span>
                    </label>
                </div>
            </div>

            <p
                v-else-if="selectedPipelineId"
                class="text-sm text-gray-500 dark:text-gray-400"
            >
                @lang('admin::app.settings.roles.create.no-pipeline-stages')
            </p>

            <div
                v-if="selectedList.length"
                class="rounded border border-dashed border-gray-200 p-3 dark:border-gray-700"
            >
                <p class="mb-2 text-sm font-medium text-gray-800 dark:text-white">
                    @lang('admin::app.settings.roles.create.selected-stages')
                </p>

                <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                    <li
                        v-for="item in selectedList"
                        :key="item.id"
                    >
                        @{{ item.label }}
                        <span
                            v-if="item.is_shared"
                            class="ml-1 text-xs text-blue-600 dark:text-blue-400"
                        >
                            (@lang('admin::app.settings.roles.create.stage-shared'))
                        </span>
                    </li>
                </ul>
            </div>

            <template v-for="item in selected">
                <input
                    :key="'stage-' + item.id"
                    type="hidden"
                    name="pipeline_stage_ids[]"
                    :value="item.id"
                />
                <input
                    v-if="item.is_shared"
                    :key="'shared-' + item.id"
                    type="hidden"
                    name="shared_pipeline_stage_ids[]"
                    :value="item.id"
                />
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-role-pipeline-stages', {
            template: '#v-role-pipeline-stages-template',

            props: {
                pipelines: {
                    type: Array,
                    default: () => [],
                },

                initialSelected: {
                    type: Array,
                    default: () => [],
                },
            },

            data() {
                return {
                    selectedPipelineId: this.pipelines?.[0]
                        ? String(this.pipelines[0].id)
                        : '',
                    selected: (this.initialSelected || []).map((item) => ({
                        id: Number(item.id),
                        is_shared: Boolean(item.is_shared),
                    })),
                };
            },

            computed: {
                currentStages() {
                    const pipeline = this.pipelines.find(
                        (item) => String(item.id) === String(this.selectedPipelineId)
                    );

                    return pipeline?.stages || [];
                },

                stageLookup() {
                    const map = {};

                    this.pipelines.forEach((pipeline) => {
                        (pipeline.stages || []).forEach((stage) => {
                            map[stage.id] = {
                                ...stage,
                                pipeline_name: pipeline.name,
                            };
                        });
                    });

                    return map;
                },

                selectedList() {
                    return this.selected
                        .map((item) => {
                            const stage = this.stageLookup[item.id];

                            if (! stage) {
                                return null;
                            }

                            return {
                                id: item.id,
                                is_shared: item.is_shared,
                                label: `${stage.pipeline_name} → ${stage.name}`,
                            };
                        })
                        .filter(Boolean);
                },
            },

            methods: {
                isSelected(stageId) {
                    return this.selected.some((item) => Number(item.id) === Number(stageId));
                },

                isShared(stageId) {
                    return this.selected.some(
                        (item) => Number(item.id) === Number(stageId) && item.is_shared
                    );
                },

                toggleStage(stageId, checked) {
                    const id = Number(stageId);

                    if (checked) {
                        if (! this.isSelected(id)) {
                            this.selected.push({ id, is_shared: false });
                        }

                        return;
                    }

                    this.selected = this.selected.filter((item) => Number(item.id) !== id);
                },

                toggleShared(stageId, checked) {
                    const id = Number(stageId);
                    const row = this.selected.find((item) => Number(item.id) === id);

                    if (row) {
                        row.is_shared = Boolean(checked);
                    }
                },
            },
        });
    </script>
@endPushOnce
