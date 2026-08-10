<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.create.title')
    </x-slot>

    {!! view_render_event('admin.leads.create.form.before') !!}

    @php
        $valueAndPricingAttributeCodes = lead_variant() === 'sdr'
            ? ['pricing_type']
            : ['lead_value', 'pricing_type'];

        $detailsExcludedAttributeCodes = [
            'lead_value',
            'pricing_type',
            'lead_type_id',
            'lead_source_id',
            'lead_sub_source_id',
            'source_sub_type',
            'source_link',
            'expected_close_date',
            'next_followup_date',
            'user_id',
            'lead_pipeline_id',
            'lead_pipeline_stage_id',
            'service_offered',
            // Company is edited only under Contact Person (avoids duplicate Company Name).
            'companies',
            'organization_id',
        ];

        // Main create uses Title; company stays under Contact Person for both variants.
        if (lead_variant() === 'main') {
            $detailsExcludedAttributeCodes[] = 'title';
        }
    @endphp

    <!-- Create Lead Form -->
    <x-admin::form :action="lead_route('store')">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <x-admin::breadcrumbs name="leads.create" />

                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.leads.create.title')
                    </div>
                </div>

                {!! view_render_event('admin.leads.create.save_button.before') !!}

                <div class="flex items-center gap-x-2.5">
                    <!-- Save button for person -->
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.leads.create.form_buttons.before') !!}

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.leads.create.save-btn')
                        </button>

                        {!! view_render_event('admin.leads.create.form_buttons.after') !!}
                    </div>
                </div>

                {!! view_render_event('admin.leads.create.save_button.after') !!}
            </div>

            @if (request('stage_id'))
                <input
                    type="hidden"
                    id="lead_pipeline_stage_id"
                    name="lead_pipeline_stage_id"
                    value="{{ request('stage_id') }}"
                />
            @endif

            @if (request('pipeline_id'))
                <input
                    type="hidden"
                    id="lead_pipeline_id"
                    name="lead_pipeline_id"
                    value="{{ request('pipeline_id') }}"
                />
            @endif

            <!-- Lead Create Component -->
            <v-lead-create>
                <x-admin::shimmer.leads.datagrid />
            </v-lead-create>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.leads.create.form.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-lead-create-template"
        >
            <div class="box-shadow flex flex-col gap-4 rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.leads.edit.form_controls.before') !!}

                <div class="flex w-full gap-2 border-b border-gray-200 dark:border-gray-800">
                    <!-- Tabs -->
                    <template
                        v-for="tab in tabs"
                        :key="tab.id"
                    >
                        {!! view_render_event('admin.leads.create.tabs.before') !!}

                        <a
                            :href="'#' + tab.id"
                            :class="[
                                'inline-block px-3 py-2.5 border-b-2  text-sm font-medium ',
                                activeTab === tab.id
                                ? 'text-brandColor border-brandColor dark:brandColor dark:brandColor'
                                : 'text-gray-600 dark:text-gray-300  border-transparent hover:text-gray-800 hover:border-gray-400 dark:hover:border-gray-400  dark:hover:text-white'
                            ]"
                            @click="scrollToSection(tab.id)"
                            :text="tab.label"
                        >
                        </a>

                        {!! view_render_event('admin.leads.create.tabs.after') !!}
                    </template>
                </div>

                <div class="flex flex-col gap-4 px-4 py-2">
                    {!! view_render_event('admin.leads.create.details.before') !!}

                    <!-- Details section -->
                    <div
                        class="flex flex-col gap-4"
                        id="lead-details"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.details')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.details-info')
                            </p>
                        </div>

                        <div class="w-1/2 max-md:w-full">
                            {!! view_render_event('admin.leads.create.details.attributes.before') !!}

                            @if (lead_variant() === 'main')
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        @lang('admin::app.leads.create.title-field')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="title"
                                        rules="required"
                                        value="{{ old('title') }}"
                                        :label="trans('admin::app.leads.create.title-field')"
                                        :placeholder="trans('admin::app.leads.create.title-field')"
                                    />

                                    <x-admin::form.control-group.error control-name="title" />
                                </x-admin::form.control-group>
                            @endif

                            <!-- Lead Details Title and Description -->
                            <x-admin::attributes
                                :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                    ['code', 'NOTIN', $detailsExcludedAttributeCodes],
                                    'entity_type' => 'leads',
                                    'quick_add'   => 1
                                ])"
                                :custom-validations="[
                                    'expected_close_date' => [
                                        'date_format:yyyy-MM-dd',
                                        'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                    ],
                                ]"
                            />

                            @include('admin::leads.common.services')

                            <!-- Lead Details Other input fields -->
                            <div class="flex gap-4 max-sm:flex-wrap">
                                <div class="w-full">
                                    <!-- Lead Value and Pricing Type -->
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', $valueAndPricingAttributeCodes],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                    />
                                    
                                    <!-- Lead Source -->
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['lead_source_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        ::key="sourceKey"
                                        @on-change="handleSourceChange"
                                    />
                                    
                                    <!-- Sub-Source (conditional - only if parent source has sub-sources) -->
                                    <div v-show="showSubSourceDropdown && availableSubSources.length > 0">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label>
                                                Sub-Source
                                            </x-admin::form.control-group.label>

                                            <x-admin::form.control-group.control
                                                type="select"
                                                name="lead_sub_source_id"
                                                v-model="selectedSubSource"
                                                :label="'Sub-Source'"
                                            >
                                                <option value="">Select Sub-Source</option>
                                                <option 
                                                    v-for="subSource in availableSubSources" 
                                                    :key="subSource.id" 
                                                    :value="subSource.id"
                                                >
                                                    @{{ subSource.name }}
                                                </option>
                                            </x-admin::form.control-group.control>

                                            <x-admin::form.control-group.error control-name="lead_sub_source_id" />
                                        </x-admin::form.control-group>
                                    </div>
                                    
                                    <!-- Source Link -->
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['source_link']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                    />
                                </div>

                                <div class="w-full">
                                    <!-- Lead Type -->
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['lead_type_id']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                    />
                                    
                                    <!-- Sales Owner and Expected Close Date (editable; changing owner forwards the lead) -->
                                    <x-admin::attributes
                                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                                            ['code', 'IN', ['user_id', 'expected_close_date']],
                                            'entity_type' => 'leads',
                                            'quick_add'   => 1
                                        ])"
                                        :custom-validations="[
                                            'expected_close_date' => [
                                                'date_format:yyyy-MM-dd',
                                                'after:' .  \Carbon\Carbon::yesterday()->format('Y-m-d')
                                            ],
                                        ]"
                                    />

                                    <input
                                        type="hidden"
                                        name="schedule_followup"
                                        :value="scheduleFollowup ? 1 : 0"
                                    />

                                    <x-admin::form.control-group>
                                        <label class="flex cursor-pointer items-start gap-2 rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                                            <input
                                                type="checkbox"
                                                v-model="scheduleFollowup"
                                                class="mt-1 rounded border-gray-300 text-brandColor focus:ring-brandColor dark:border-gray-600 dark:bg-gray-900"
                                            />

                                            <span class="flex flex-col gap-1">
                                                <span class="font-medium text-gray-800 dark:text-white">
                                                    @lang('admin::app.leads.create.schedule-followup')
                                                </span>

                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    @lang('admin::app.leads.create.schedule-followup-help')
                                                </span>
                                            </span>
                                        </label>
                                    </x-admin::form.control-group>

                                    <x-admin::form.control-group v-if="scheduleFollowup">
                                        <x-admin::form.control-group.label>
                                            @lang('admin::app.leads.create.next-followup-date')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="datetime"
                                            name="next_followup_date"
                                            :label="trans('admin::app.leads.create.next-followup-date')"
                                            :placeholder="trans('admin::app.leads.create.next-followup-date')"
                                        />

                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @lang('admin::app.leads.create.next-followup-date-help')
                                        </p>

                                        <x-admin::form.control-group.error control-name="next_followup_date" />
                                    </x-admin::form.control-group>

                                </div>
                            </div>

                            {!! view_render_event('admin.leads.create.details.attributes.after') !!}
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.details.after') !!}

                    {!! view_render_event('admin.leads.create.contact_person.before') !!}

                    <!-- Contact Person -->
                    <div
                        class="flex flex-col gap-4"
                        id="contact-person"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.contact-person')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.contact-info')
                            </p>
                        </div>

                        <div class="w-full max-w-3xl">
                            <!-- Contact Person Component -->
                            @include('admin::leads.common.contact')
                        </div>
                    </div>

                    {!! view_render_event('admin.leads.create.contact_person.after') !!}

                    <!-- Product Section -->
                    <div
                        class="flex flex-col gap-4"
                        id="products"
                    >
                        <div class="flex flex-col gap-1">
                            <p class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.create.products')
                            </p>

                            <p class="text-gray-600 dark:text-white">
                                @lang('admin::app.leads.create.products-info')
                            </p>
                        </div>

                        <div>
                            <!-- Product Component -->
                            @include('admin::leads.common.products')
                        </div>
                    </div>
                </div>

                {!! view_render_event('admin.leads.form_controls.after') !!}
            </div>
        </script>

        <script type="module">
            app.component('v-lead-create', {
                template: '#v-lead-create-template',

                data() {
                    return {
                        activeTab: 'lead-details',

                        tabs: [
                            { id: 'lead-details', label: '@lang('admin::app.leads.create.details')' },
                            { id: 'contact-person', label: '@lang('admin::app.leads.create.contact-person')' },
                            { id: 'products', label: '@lang('admin::app.leads.create.products')' }
                        ],
                        
                        showSubSourceDropdown: false,
                        availableSubSources: [],
                        selectedSubSource: '',
                        scheduleFollowup: false,
                        sourceKey: 0,
                    };
                },
                
                mounted() {
                    // Listen for source changes
                    document.addEventListener('change', (e) => {
                        if (e.target.name === 'lead_source_id') {
                            this.handleSourceChange(e);
                        }
                    });
                },

                methods: {
                    /**
                     * Scroll to the section.
                     *
                     * @param {String} tabId
                     *
                     * @returns {void}
                     */
                    scrollToSection(tabId) {
                        const section = document.getElementById(tabId);

                        if (section) {
                            section.scrollIntoView({ behavior: 'smooth' });
                        }
                    },
                    
                    /**
                     * Handle source change event.
                     *
                     * @param {Event} event
                     *
                     * @returns {void}
                     */
                    handleSourceChange(event) {
                        const sourceId = parseInt(event.target.value);
                        
                        console.log('Source changed to:', sourceId);
                        
                        if (sourceId) {
                            // Fetch sub-sources for the selected source
                            console.log('Fetching sub-sources from:', `/admin/settings/api/sources/${sourceId}/sub-sources`);
                            
                            this.$axios.get(`/admin/settings/api/sources/${sourceId}/sub-sources`)
                                .then(response => {
                                    console.log('Sub-sources response:', response.data);
                                    this.availableSubSources = response.data.sub_sources || [];
                                    this.showSubSourceDropdown = this.availableSubSources.length > 0;
                                    this.selectedSubSource = '';
                                    console.log('Show dropdown:', this.showSubSourceDropdown, 'Available:', this.availableSubSources);
                                })
                                .catch(error => {
                                    console.error('Error fetching sub-sources:', error);
                                    this.availableSubSources = [];
                                    this.showSubSourceDropdown = false;
                                });
                        } else {
                            this.availableSubSources = [];
                            this.showSubSourceDropdown = false;
                            this.selectedSubSource = '';
                        }
                    },
                },
            });
        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            html {
                scroll-behavior: smooth;
            }
        </style>
    @endPushOnce
</x-admin::layouts>
