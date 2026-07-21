<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.followup-schedule.index.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="settings.followup_schedule" />

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.settings.followup-schedule.index.title')
                </div>
            </div>
        </div>

        <v-followup-schedule-form
            action-url="{{ route('admin.settings.followup_schedule.update') }}"
            :enabled='@json((bool) old('enabled', $enabled))'
            :steps='@json(old('steps', $settings['steps']))'
            :max-days='@json((int) old('max_days', $settings['max_days']))'
            :units='@json($units)'
        ></v-followup-schedule-form>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-followup-schedule-form-template"
        >
            <form
                method="POST"
                :action="actionUrl"
            >
                <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                />

                <input
                    type="hidden"
                    name="_method"
                    value="PUT"
                />

                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-5 text-sm text-gray-600 dark:text-gray-300">
                        @lang('admin::app.settings.followup-schedule.index.info')
                    </p>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.settings.followup-schedule.index.enabled')
                        </x-admin::form.control-group.label>

                        <label class="flex items-center gap-2 text-sm dark:text-gray-300">
                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                v-model="isEnabled"
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                            />

                            @lang('admin::app.settings.followup-schedule.index.enabled-help')
                        </label>
                    </x-admin::form.control-group>

                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-gray-800 dark:text-white">
                                @lang('admin::app.settings.followup-schedule.index.steps-title')
                            </p>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @lang('admin::app.settings.followup-schedule.index.steps-help')
                            </p>
                        </div>

                        <button
                            type="button"
                            class="secondary-button"
                            @click="addStep"
                        >
                            @lang('admin::app.settings.followup-schedule.index.add-step')
                        </button>
                    </div>

                    <div class="mb-5 space-y-3">
                        <div
                            v-for="(step, index) in steps"
                            :key="'step-' + index"
                            class="grid items-end gap-3 rounded border border-gray-200 p-3 dark:border-gray-700 md:grid-cols-[70px_1fr_1fr_1fr_auto]"
                        >
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">
                                    @lang('admin::app.settings.followup-schedule.index.step')
                                </p>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                    #@{{ index + 1 }}
                                </p>
                            </div>

                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.settings.followup-schedule.index.interval-value')
                                </x-admin::form.control-group.label>

                                <input
                                    type="number"
                                    min="1"
                                    required
                                    :name="'steps[' + index + '][value]'"
                                    v-model.number="steps[index].value"
                                    class="w-full rounded border border-gray-200 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                />
                            </x-admin::form.control-group>

                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.settings.followup-schedule.index.interval-unit')
                                </x-admin::form.control-group.label>

                                <select
                                    required
                                    :name="'steps[' + index + '][unit]'"
                                    v-model="steps[index].unit"
                                    class="w-full rounded border border-gray-200 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option
                                        v-for="unit in units"
                                        :key="unit"
                                        :value="unit"
                                    >
                                        @{{ unitLabels[unit] }}
                                    </option>
                                </select>
                            </x-admin::form.control-group>

                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.settings.followup-schedule.index.frequency')
                                </x-admin::form.control-group.label>

                                <input
                                    type="number"
                                    min="1"
                                    required
                                    :name="'steps[' + index + '][frequency]'"
                                    v-model.number="steps[index].frequency"
                                    class="w-full rounded border border-gray-200 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                />

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    @lang('admin::app.settings.followup-schedule.index.frequency-help')
                                </p>
                            </x-admin::form.control-group>

                            <button
                                type="button"
                                class="transparent-button text-red-600"
                                :disabled="steps.length <= 1"
                                @click="removeStep(index)"
                            >
                                @lang('admin::app.settings.followup-schedule.index.remove-step')
                            </button>
                        </div>

                        @error('steps')
                            <p class="text-xs italic text-red-600">{{ $message }}</p>
                        @enderror

                        @error('steps.*')
                            <p class="text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.settings.followup-schedule.index.max-days')
                        </x-admin::form.control-group.label>

                        <input
                            type="number"
                            min="1"
                            required
                            name="max_days"
                            v-model.number="endAfterDays"
                            class="w-full max-w-xs rounded border border-gray-200 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        />

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @lang('admin::app.settings.followup-schedule.index.max-days-help')
                        </p>

                        @error('max_days')
                            <p class="mt-1 text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    </x-admin::form.control-group>

                    <div class="mt-2 flex justify-end">
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.settings.followup-schedule.index.save-btn')
                        </button>
                    </div>
                </div>
            </form>
        </script>

        <script type="module">
            app.component('v-followup-schedule-form', {
                template: '#v-followup-schedule-form-template',

                props: {
                    actionUrl: {
                        type: String,
                        required: true,
                    },

                    enabled: {
                        type: Boolean,
                        default: true,
                    },

                    steps: {
                        type: Array,
                        default: () => ([]),
                    },

                    maxDays: {
                        type: Number,
                        default: 30,
                    },

                    units: {
                        type: Array,
                        default: () => (['minutes', 'hours', 'days', 'weeks', 'months']),
                    },
                },

                data() {
                    const incoming = Array.isArray(this.steps) && this.steps.length
                        ? this.steps
                        : [{ value: 4, unit: 'hours', frequency: 1 }];

                    return {
                        isEnabled: this.enabled,
                        steps: incoming.map((step) => {
                            if (typeof step === 'number') {
                                return {
                                    value: Number(step) || 1,
                                    unit: 'hours',
                                    frequency: 1,
                                };
                            }

                            return {
                                value: Number(step?.value) || 1,
                                unit: step?.unit || 'hours',
                                frequency: Number(step?.frequency) || 1,
                            };
                        }),
                        endAfterDays: Number(this.maxDays) || 30,
                        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content
                            || '{{ csrf_token() }}',
                        unitLabels: {
                            minutes: @json(trans('admin::app.settings.followup-schedule.index.units.minutes')),
                            hours: @json(trans('admin::app.settings.followup-schedule.index.units.hours')),
                            days: @json(trans('admin::app.settings.followup-schedule.index.units.days')),
                            weeks: @json(trans('admin::app.settings.followup-schedule.index.units.weeks')),
                            months: @json(trans('admin::app.settings.followup-schedule.index.units.months')),
                        },
                    };
                },

                created() {
                    if (! this.steps.length) {
                        this.steps = [this.defaultStep()];
                    }
                },

                methods: {
                    defaultStep() {
                        return {
                            value: 4,
                            unit: 'hours',
                            frequency: 1,
                        };
                    },

                    addStep() {
                        const last = this.steps[this.steps.length - 1] || this.defaultStep();

                        this.steps.push({
                            value: Number(last.value) || 4,
                            unit: last.unit || 'hours',
                            frequency: Number(last.frequency) || 1,
                        });
                    },

                    removeStep(index) {
                        if (this.steps.length <= 1) {
                            return;
                        }

                        this.steps.splice(index, 1);
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
