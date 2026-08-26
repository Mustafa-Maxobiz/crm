                @if (bouncer()->hasPermission(lead_permission('import')))
                    @php
                        $importSources = app(\Webkul\Lead\Repositories\SourceRepository::class)->getRootDropdownOptions();
                        $importTags = \Illuminate\Support\Facades\DB::table('tags')
                            ->orderBy('name')
                            ->get(['id', 'name']);
                        $defaultImportTagId = $importTags->firstWhere('name', 'Cold Lead')?->id
                            ?? $importTags->first()?->id;
                        $leadForwardService = app(\Webkul\Lead\Services\LeadForwardService::class);
                        $coldLeadImportTagId = $leadForwardService->coldLeadTagId();
                        $isAdminLeadImport = app(\Webkul\Lead\Services\SourceAccessService::class)->isAdmin();
                        $importSdrUsers = $leadForwardService->activeSdrUsers();
                    @endphp

                    <x-admin::modal
                        ref="leadImportModal"
                        size="extra-large"
                        :is-active="request('action') === 'import'"
                    >
                        <x-slot:toggle>
                            <button
                                type="button"
                                class="secondary-button"
                            >
                                Import Leads
                            </button>
                        </x-slot>

                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                Import Leads
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <form
                                id="lead-import-form"
                                method="POST"
                                action="{{ lead_route('import') }}"
                                enctype="multipart/form-data"
                                class="grid gap-4"
                            >
                                @csrf

                                <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                    <p class="font-semibold text-gray-800 dark:text-white">
                                        Accepted files: .xlsx, .xls, .csv
                                    </p>

                                    <p class="mt-1">
                                        Required columns are marked with * in the template. Blank optional columns are imported as null. Blank schedule_followup uses auto schedule.
                                        Select a <strong>Lead Source</strong> below — it will be applied to every imported lead. Choose a <strong>Tag</strong> to apply to all imported leads (default: Cold Lead).
                                        @if ($isAdminLeadImport)
                                            Choose one or more <strong>SDR users</strong> to own these leads (multiple users share them equally) and an <strong>Industry</strong> for the whole file.
                                        @elseif (lead_variant() === 'lge')
                                            If the selected tag is <strong>Cold Lead</strong>, choose one or more <strong>SDR users</strong> to receive the imported leads.
                                        @endif
                                    </p>

                                    <p class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400">
                                        Maximum 500 leads per upload. Current local PHP upload limit is 2 MB per file. Use split CSV files or increase upload_max_filesize.
                                    </p>

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Required: companies*, lead_value*, type*, pricing_type*
                                    </p>

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        For multiple phone numbers, separate values with commas.
                                        Example: +13055551111,+13055552222
                                    </p>
                                </div>

                                <a
                                    href="{{ lead_route('import.template') }}"
                                    class="secondary-button w-max"
                                >
                                    Download Template
                                </a>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Lead Source
                                    </x-admin::form.control-group.label>

                                    <select
                                        id="lead-import-source"
                                        name="lead_source_id"
                                        required
                                        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option value="">
                                            Select Lead Source
                                        </option>
                                        @foreach ($importSources as $source)
                                            <option value="{{ $source['value'] }}">
                                                {{ $source['label'] }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-admin::form.control-group.error control-name="lead_source_id" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Tag
                                    </x-admin::form.control-group.label>

                                    <select
                                        id="lead-import-tag"
                                        name="import_tag_id"
                                        required
                                        class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option value="">
                                            Select Tag
                                        </option>
                                        @foreach ($importTags as $tag)
                                            <option
                                                value="{{ $tag->id }}"
                                                data-cold-lead="{{ (string) $coldLeadImportTagId === (string) $tag->id ? '1' : '0' }}"
                                                @selected((string) $defaultImportTagId === (string) $tag->id)
                                            >
                                                {{ $tag->name }}
                                            </option>
                                        @endforeach
                                    </select>

                                    <x-admin::form.control-group.error control-name="import_tag_id" />
                                </x-admin::form.control-group>

                                @if (lead_variant() === 'lge')
                                    @php
                                        $importLinkedInProfiles = app(\Webkul\Lead\Services\LinkedInProfileAccessService::class)->getFilterOptions();
                                    @endphp

                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            LinkedIn Working Profile
                                        </x-admin::form.control-group.label>

                                        <select
                                            id="lead-import-linkedin-profile"
                                            name="import_linkedin_profile_id"
                                            required
                                            class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            <option value="">Select LinkedIn Profile</option>

                                            @foreach ($importLinkedInProfiles as $profile)
                                                <option value="{{ $profile['value'] }}">
                                                    {{ $profile['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </x-admin::form.control-group>
                                @endif

                                @if ($isAdminLeadImport || lead_variant() === 'lge')
                                    @php
                                        $importIndustries = \Illuminate\Support\Facades\DB::table('attribute_options')
                                            ->join('attributes', 'attributes.id', '=', 'attribute_options.attribute_id')
                                            ->where('attributes.entity_type', 'leads')
                                            ->where('attributes.code', 'industry')
                                            ->orderBy('attribute_options.sort_order')
                                            ->orderBy('attribute_options.name')
                                            ->get([
                                                'attribute_options.id',
                                                'attribute_options.name',
                                            ]);
                                    @endphp

                                    <div id="lead-import-sdr-assignment-group">
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label class="required">
                                                {{ $isAdminLeadImport ? 'Assign to SDR Users' : 'Forward Cold Leads To SDRs' }}
                                            </x-admin::form.control-group.label>

                                            <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                                                Select one or more SDR users. If you select more than one, leads are divided equally between them.
                                            </p>

                                            <div class="max-h-44 overflow-auto rounded-md border border-gray-200 p-2 dark:border-gray-800">
                                                @forelse ($importSdrUsers as $sdrUser)
                                                    <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 text-sm text-gray-800 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-950">
                                                        <input
                                                            type="checkbox"
                                                            name="assignee_user_ids[]"
                                                            value="{{ $sdrUser->id }}"
                                                            class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                                                        />
                                                        <span>
                                                            {{ $sdrUser->name }}
                                                            @if ($sdrUser->email)
                                                                <span class="text-xs text-gray-500">({{ $sdrUser->email }})</span>
                                                            @endif
                                                        </span>
                                                    </label>
                                                @empty
                                                    <p class="px-2 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                        No active SDR users found.
                                                    </p>
                                                @endforelse
                                            </div>
                                        </x-admin::form.control-group>
                                    </div>

                                    @if ($isAdminLeadImport)
                                        <x-admin::form.control-group>
                                            <x-admin::form.control-group.label class="required">
                                                Industry
                                            </x-admin::form.control-group.label>

                                            <select
                                                id="lead-import-industry"
                                                name="industry_id"
                                                required
                                                class="custom-select w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                                <option value="">
                                                    Select Industry
                                                </option>
                                                @foreach ($importIndustries as $industry)
                                                    <option value="{{ $industry->id }}">
                                                        {{ $industry->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </x-admin::form.control-group>
                                    @endif
                                @endif

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Upload File
                                    </x-admin::form.control-group.label>

                                    <input
                                        id="lead-import-file"
                                        type="file"
                                        name="file"
                                        accept=".csv,.xlsx,.xls"
                                        required
                                        class="w-full rounded border border-gray-200 px-3 py-2 text-sm text-gray-800 transition-all file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:file:bg-gray-800 dark:file:text-gray-300"
                                    />

                                    <x-admin::form.control-group.error control-name="file" />
                                </x-admin::form.control-group>

                                <div
                                    id="lead-import-progress"
                                    class="hidden rounded-md border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div class="flex items-center justify-between text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        <span id="lead-import-progress-status">Preparing import...</span>
                                        <span id="lead-import-progress-percent">0%</span>
                                    </div>

                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div
                                            id="lead-import-progress-bar"
                                            class="h-full w-0 rounded-full bg-red-600 transition-all duration-300"
                                        ></div>
                                    </div>
                                </div>

                                <div
                                    id="lead-import-failed"
                                    class="hidden"
                                >
                                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                                Failed rows
                                            </p>
                                            <p
                                                id="lead-import-failed-summary"
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                            ></p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                id="lead-import-remove-all"
                                                type="button"
                                                class="secondary-button !min-h-[34px] !px-3 text-xs"
                                            >
                                                Remove All
                                            </button>
                                        </div>
                                    </div>

                                    <div
                                        id="lead-import-failed-top-scroll"
                                        class="mb-1 overflow-x-auto overflow-y-hidden rounded-md border border-gray-200 dark:border-gray-800"
                                    >
                                        <div
                                            id="lead-import-failed-top-scroll-spacer"
                                            class="h-3"
                                        ></div>
                                    </div>

                                    <div
                                        id="lead-import-failed-table-scroll"
                                        class="max-h-80 overflow-auto rounded-md border border-gray-200 dark:border-gray-800"
                                    >
                                        <table
                                            id="lead-import-failed-table"
                                            class="min-w-full text-left text-xs"
                                        >
                                            <thead class="sticky top-0 bg-gray-50 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                                                <tr>
                                                    <th class="whitespace-nowrap px-2 py-2">Action</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Row</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Error</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Company*</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Lead Value*</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Type*</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Pricing Type*</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Person</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Email</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Phone</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Address</th>
                                                    <th class="whitespace-nowrap px-2 py-2">City</th>
                                                    <th class="whitespace-nowrap px-2 py-2">State</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Country</th>
                                                    <th class="whitespace-nowrap px-2 py-2">Postcode</th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                id="lead-import-failed-body"
                                                class="divide-y divide-gray-100 dark:divide-gray-800"
                                            ></tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
                        </x-slot>

                        <x-slot:footer>
                            <div class="flex w-full items-center justify-end gap-3">
                                <button
                                    type="button"
                                    class="secondary-button"
                                    @click="$refs.leadImportModal.close()"
                                >
                                    Close
                                </button>

                                <button
                                    id="lead-import-submit"
                                    type="submit"
                                    form="lead-import-form"
                                    class="primary-button"
                                >
                                    Upload Leads
                                </button>

                                <button
                                    id="lead-import-retry"
                                    type="button"
                                    class="primary-button hidden"
                                >
                                    Retry Correct
                                </button>
                            </div>
                        </x-slot>
                    </x-admin::modal>
                @endif
