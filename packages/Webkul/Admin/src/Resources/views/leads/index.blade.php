<x-admin::layouts>
    <x-slot:title>
        @if (($leadVariant ?? 'main') === 'sdr')
            @lang('admin::app.layouts.leads-sdr')
        @elseif (($leadVariant ?? 'main') === 'lge')
            @lang('admin::app.layouts.leads-lge')
        @elseif (($leadVariant ?? 'main') === 'lead_clouser')
            @lang('admin::app.layouts.leads-lead-clouser')
        @else
            @lang('admin::app.leads.index.title')
        @endif
    </x-slot>

    <!-- Header -->
    {!! view_render_event('admin.leads.index.header.before') !!}

    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        {!! view_render_event('admin.leads.index.header.left.before') !!}

        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="leads" />

            <div class="text-xl font-bold dark:text-white">
                @if (($leadVariant ?? 'main') === 'sdr')
                    @lang('admin::app.layouts.leads-sdr')
                @elseif (($leadVariant ?? 'main') === 'lge')
                    @lang('admin::app.layouts.leads-lge')
                @elseif (($leadVariant ?? 'main') === 'lead_clouser')
                    @lang('admin::app.layouts.leads-lead-clouser')
                @else
                    @lang('admin::app.leads.index.title')
                @endif
            </div>
        </div>

        {!! view_render_event('admin.leads.index.header.left.after') !!}

        {!! view_render_event('admin.leads.index.header.right.before') !!}

        <div class="flex items-center gap-x-2.5">
            <!-- Upload File for Lead Creation -->
            @if(core()->getConfigData('general.magic_ai.doc_generation.enabled'))
                @include('admin::leads.index.upload')
            @endif

            @if ((request()->view_type ?? "table") == "table")
                <!-- Export Modal -->
                <x-admin::datagrid.export :src="route($leadsIndexRoute ?? 'admin.leads.index')" />
            @endif

            <!-- Create button for Leads -->
            <div class="flex items-center gap-x-2.5">
                @if (bouncer()->hasPermission(lead_permission('import')))
                    @php
                        $importSources = app(\Webkul\Lead\Repositories\SourceRepository::class)->getRootDropdownOptions();
                    @endphp

                    <x-admin::modal
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
                                        Select a <strong>Lead Source</strong> below — it will be applied to every imported lead. All bulk-imported leads get the <strong>Cold Lead</strong> tag.
                                    </p>

                                    <p class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400">
                                        Current local PHP upload limit is 2 MB per file. Use split CSV files or increase upload_max_filesize.
                                    </p>

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Required: companies*, lead_value*, type*, pricing_type*
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

                                            <button
                                                id="lead-import-retry"
                                                type="button"
                                                class="primary-button !min-h-[34px] !px-3 text-xs"
                                            >
                                                Fix &amp; Re-import Failed
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
                            <button
                                id="lead-import-submit"
                                type="submit"
                                form="lead-import-form"
                                class="primary-button"
                            >
                                Upload Leads
                            </button>
                        </x-slot>
                    </x-admin::modal>
                @endif

                @if (bouncer()->hasPermission(lead_permission('disqualified')))
                    <a
                        href="{{ lead_route('disqualified') }}"
                        class="secondary-button"
                    >
                        @lang('admin::app.leads.disqualification.short-title')
                    </a>
                @endif

                @if (bouncer()->hasPermission(lead_permission('create')))
                    <a
                        href="{{ lead_route('create', request()->query()) }}"
                        class="primary-button"
                    >
                        @lang('admin::app.leads.index.create-btn')
                    </a>
                @endif
            </div>
        </div>

        {!! view_render_event('admin.leads.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.leads.index.header.after') !!}

    {!! view_render_event('admin.leads.index.content.before') !!}

    <!-- Content -->
    <div class="[&>*>*>*.toolbarRight]:max-lg:w-full [&>*>*>*.toolbarRight]:max-lg:justify-between [&>*>*>*.toolbarRight]:max-md:gap-y-2 [&>*>*>*.toolbarRight]:max-md:flex-wrap mt-3.5 [&>*>*:nth-child(1)]:max-lg:!flex-wrap">
        @if ((request()->view_type ?? "table") == "table")
            @include('admin::leads.index.table')
        @else
            @include('admin::leads.index.kanban')
        @endif
    </div>

    {!! view_render_event('admin.leads.index.content.after') !!}

    @pushOnce('scripts')
        <script>
            window.copyLeadPhone = async function (button, phone) {
                if (! phone) {
                    return;
                }

                const originalLabel = button.textContent;

                try {
                    if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(phone);
                    } else {
                        const textarea = document.createElement('textarea');
                        textarea.value = phone;
                        textarea.setAttribute('readonly', '');
                        textarea.style.position = 'fixed';
                        textarea.style.opacity = '0';
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand('copy');
                        document.body.removeChild(textarea);
                    }

                    button.textContent = @json(trans('admin::app.leads.index.datagrid.copied'));

                    setTimeout(() => {
                        button.textContent = originalLabel;
                    }, 1500);
                } catch (error) {
                    console.log(error);
                }
            };
        </script>

        @if (bouncer()->hasPermission(lead_permission('import')))
        <script type="module">
            const leadImportStartUrl = @json(lead_route('import.start'));
            const leadImportProcessUrl = @json(lead_route('import.process'));
            const leadImportRetryUrl = @json(lead_route('import.retry'));
            const leadImportMaxFileSize = 2 * 1024 * 1024;
            const failedEditFields = [
                'companies',
                'lead_value',
                'type',
                'pricing_type',
                'person_name',
                'email',
                'phone',
                'address',
                'city',
                'state',
                'country',
                'postcode',
            ];

            let importLeadSourceId = null;
            let failedImportRows = [];

            const importElements = () => ({
                form: document.getElementById('lead-import-form'),
                file: document.getElementById('lead-import-file'),
                submit: document.getElementById('lead-import-submit'),
                retry: document.getElementById('lead-import-retry'),
                progress: document.getElementById('lead-import-progress'),
                bar: document.getElementById('lead-import-progress-bar'),
                percent: document.getElementById('lead-import-progress-percent'),
                status: document.getElementById('lead-import-progress-status'),
                failed: document.getElementById('lead-import-failed'),
                failedBody: document.getElementById('lead-import-failed-body'),
                failedSummary: document.getElementById('lead-import-failed-summary'),
                failedTopScroll: document.getElementById('lead-import-failed-top-scroll'),
                failedTopSpacer: document.getElementById('lead-import-failed-top-scroll-spacer'),
                failedTableScroll: document.getElementById('lead-import-failed-table-scroll'),
                failedTable: document.getElementById('lead-import-failed-table'),
            });

            const setProgress = (elements, percent, status, isError = false) => {
                const boundedPercent = Math.max(0, Math.min(100, Number(percent)));
                const formattedPercent = boundedPercent.toFixed(2);

                elements.progress.classList.remove('hidden');
                elements.bar.style.width = `${boundedPercent}%`;
                elements.percent.textContent = `${formattedPercent}%`;
                elements.status.textContent = status;
                elements.bar.classList.toggle('bg-green-600', boundedPercent === 100 && ! isError);
                elements.bar.classList.toggle('bg-red-600', boundedPercent !== 100 || isError);
            };

            const flash = (type, message) => {
                window.emitter?.emit('add-flash', { type, message });
            };

            const escapeHtml = (value) => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#39;');

            const fieldInput = (rowIndex, field, value) => `
                <td class="px-2 py-2 align-top">
                    <input
                        type="text"
                        data-row-index="${rowIndex}"
                        data-field="${field}"
                        value="${escapeHtml(value ?? '')}"
                        class="min-w-[120px] rounded border border-gray-200 px-2 py-1 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                    />
                </td>
            `;

            const syncFailedRowsFromTable = () => {
                failedImportRows = collectFailedRowsFromTable().map((row, index) => ({
                    ...row,
                    error: failedImportRows[index]?.error || 'Import failed',
                }));
            };

            const removeFailedRow = (index) => {
                syncFailedRowsFromTable();
                failedImportRows.splice(index, 1);
                renderFailedRows(failedImportRows, importLeadSourceId);

                if (! failedImportRows.length) {
                    flash('success', 'All failed rows removed from correction list.');
                }
            };

            const removeAllFailedRows = () => {
                if (! failedImportRows.length) {
                    return;
                }

                if (! confirm(`Remove all ${failedImportRows.length} failed row(s) from correction?`)) {
                    return;
                }

                renderFailedRows([], importLeadSourceId);
                flash('success', 'Failed rows removed from correction list.');
            };

            const syncFailedTableScrollbars = () => {
                const elements = importElements();
                const topScroll = elements.failedTopScroll;
                const topSpacer = elements.failedTopSpacer;
                const tableScroll = elements.failedTableScroll;
                const table = elements.failedTable;

                if (! topScroll || ! topSpacer || ! tableScroll || ! table) {
                    return;
                }

                topSpacer.style.width = `${table.scrollWidth}px`;

                if (topScroll.dataset.scrollBound === '1') {
                    return;
                }

                topScroll.dataset.scrollBound = '1';
                tableScroll.dataset.scrollBound = '1';

                let syncing = false;

                topScroll.addEventListener('scroll', () => {
                    if (syncing) {
                        return;
                    }

                    syncing = true;
                    tableScroll.scrollLeft = topScroll.scrollLeft;
                    syncing = false;
                });

                tableScroll.addEventListener('scroll', () => {
                    if (syncing) {
                        return;
                    }

                    syncing = true;
                    topScroll.scrollLeft = tableScroll.scrollLeft;
                    syncing = false;
                });
            };

            const renderFailedRows = (rows, sourceId) => {
                const elements = importElements();

                failedImportRows = Array.isArray(rows) ? rows : [];
                importLeadSourceId = sourceId || importLeadSourceId;

                if (! failedImportRows.length) {
                    elements.failed.classList.add('hidden');
                    elements.failedBody.innerHTML = '';
                    elements.failedSummary.textContent = '';

                    return;
                }

                elements.failed.classList.remove('hidden');
                elements.failedSummary.textContent = `${failedImportRows.length} row(s) need fixes before re-import.`;
                elements.failedBody.innerHTML = failedImportRows.map((row, index) => {
                    const data = row.data || {};

                    return `
                        <tr class="bg-white dark:bg-gray-900" data-failed-index="${index}">
                            <td class="whitespace-nowrap px-2 py-2">
                                <button
                                    type="button"
                                    data-remove-index="${index}"
                                    class="secondary-button !min-h-[28px] !px-2 text-[11px]"
                                >
                                    Remove
                                </button>
                            </td>
                            <td class="whitespace-nowrap px-2 py-2 font-semibold text-gray-700 dark:text-gray-200">${escapeHtml(row.row_number)}</td>
                            <td class="min-w-[220px] px-2 py-2 text-red-600 dark:text-red-400">${escapeHtml(row.error || 'Import failed')}</td>
                            ${fieldInput(index, 'companies', data.companies)}
                            ${fieldInput(index, 'lead_value', data.lead_value)}
                            ${fieldInput(index, 'type', data.type)}
                            ${fieldInput(index, 'pricing_type', data.pricing_type)}
                            ${fieldInput(index, 'person_name', data.person_name)}
                            ${fieldInput(index, 'email', data.email)}
                            ${fieldInput(index, 'phone', data.phone)}
                            ${fieldInput(index, 'address', data.address)}
                            ${fieldInput(index, 'city', data.city)}
                            ${fieldInput(index, 'state', data.state)}
                            ${fieldInput(index, 'country', data.country)}
                            ${fieldInput(index, 'postcode', data.postcode)}
                        </tr>
                    `;
                }).join('');

                requestAnimationFrame(() => {
                    syncFailedTableScrollbars();
                });
            };

            const collectFailedRowsFromTable = () => {
                const elements = importElements();

                return failedImportRows.map((row, index) => {
                    const data = { ...(row.data || {}) };

                    failedEditFields.forEach((field) => {
                        const input = elements.failedBody.querySelector(
                            `input[data-row-index="${index}"][data-field="${field}"]`
                        );

                        if (input) {
                            data[field] = input.value.trim();
                        }
                    });

                    return {
                        row_number: row.row_number,
                        data,
                    };
                });
            };

            const processImportChunk = async (token, offset) => {
                const response = await window.axios.post(leadImportProcessUrl, {
                    token,
                    offset,
                }, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                return response.data;
            };

            const processImport = async (elements, token, total) => {
                let offset = 0;
                let result = null;

                do {
                    result = await processImportChunk(token, offset);
                    offset = result.processed;

                    const percent = total ? (result.processed / total) * 100 : 100;

                    setProgress(
                        elements,
                        percent,
                        `Imported ${result.processed} of ${total} rows. Created ${result.created}.`
                    );
                } while (! result.done);

                const failedCount = result.failed_rows?.length || 0;

                setProgress(
                    elements,
                    100,
                    `${result.message} Processed ${result.processed} of ${total} rows. Created ${result.created}. Failed ${failedCount}.`,
                    failedCount > 0
                );

                if (failedCount > 0) {
                    renderFailedRows(result.failed_rows, result.lead_source_id);
                    flash('warning', `${result.message} ${failedCount} row(s) failed. Fix them below and retry.`);
                    elements.submit.disabled = false;
                    elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');

                    return;
                }

                renderFailedRows([], null);
                flash('success', result.message);
                setTimeout(() => window.location.reload(), 900);
            };

            document.addEventListener('submit', async (event) => {
                if (event.target?.id !== 'lead-import-form') {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const elements = importElements();

                if (! elements.file?.files?.length) {
                    flash('error', 'Please select a file to import.');

                    return;
                }

                const sourceSelect = document.getElementById('lead-import-source');

                if (! sourceSelect?.value) {
                    flash('error', 'Please select a lead source for this import.');

                    return;
                }

                if (elements.file.files[0].size > leadImportMaxFileSize) {
                    flash('error', 'This file is larger than the current 2 MB PHP upload limit. Please use a split CSV file or increase upload_max_filesize.');

                    return;
                }

                importLeadSourceId = Number(sourceSelect.value);
                renderFailedRows([], importLeadSourceId);

                elements.submit.disabled = true;
                elements.submit.classList.add('opacity-70', 'cursor-not-allowed');

                setProgress(elements, 0, 'Uploading file...');

                const formData = new FormData(elements.form);

                try {
                    const startResponse = await window.axios.post(leadImportStartUrl, formData, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'multipart/form-data',
                        },

                        onUploadProgress(uploadEvent) {
                            if (! uploadEvent.total) {
                                return;
                            }

                            setProgress(elements, (uploadEvent.loaded / uploadEvent.total) * 10, 'Uploading file...');
                        },
                    });

                    const { token, total } = startResponse.data;

                    setProgress(elements, 10, `Prepared ${total} rows. Starting import...`);

                    await processImport(elements, token, total);
                } catch (error) {
                    const response = error.response?.data;
                    const message = response?.errors?.length
                        ? `${response.message || 'Import failed.'} ${response.errors.slice(0, 3).join(' ')}`
                        : response?.message || 'Import failed. Please check the file and try again.';

                    setProgress(elements, 100, 'Import failed.', true);
                    flash('error', message);

                    elements.submit.disabled = false;
                    elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                }
            }, true);

            document.addEventListener('click', async (event) => {
                const removeButton = event.target.closest('#lead-import-failed [data-remove-index]');

                if (removeButton) {
                    event.preventDefault();
                    event.stopPropagation();

                    const index = Number(removeButton.getAttribute('data-remove-index'));

                    if (! Number.isNaN(index)) {
                        removeFailedRow(index);
                    }

                    return;
                }

                if (event.target.closest('#lead-import-remove-all')) {
                    event.preventDefault();
                    event.stopPropagation();
                    removeAllFailedRows();

                    return;
                }

                if (! event.target.closest('#lead-import-retry')) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();

                const elements = importElements();
                const sourceId = importLeadSourceId || Number(document.getElementById('lead-import-source')?.value || 0);

                if (! sourceId) {
                    flash('error', 'Please select a lead source for this import.');

                    return;
                }

                if (! failedImportRows.length) {
                    flash('error', 'There are no failed rows to retry.');

                    return;
                }

                const rows = collectFailedRowsFromTable();

                elements.retry.disabled = true;
                elements.retry.classList.add('opacity-70', 'cursor-not-allowed');
                setProgress(elements, 0, `Retrying ${rows.length} failed row(s)...`);

                try {
                    const response = await window.axios.post(leadImportRetryUrl, {
                        lead_source_id: sourceId,
                        rows,
                    }, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        validateStatus: (status) => status >= 200 && status < 500,
                    });

                    const result = response.data;
                    const stillFailed = result.failed_rows || [];

                    renderFailedRows(stillFailed, sourceId);

                    if (stillFailed.length) {
                        setProgress(
                            elements,
                            100,
                            `${result.message || 'Retry finished.'} ${stillFailed.length} row(s) still failing.`,
                            true
                        );
                        flash('warning', `${result.message || 'Retry finished.'} ${stillFailed.length} row(s) still need fixes.`);
                    } else {
                        setProgress(elements, 100, result.message || 'All failed rows imported successfully.');
                        flash('success', result.message || 'All failed rows imported successfully.');
                        setTimeout(() => window.location.reload(), 900);
                    }
                } catch (error) {
                    const message = error.response?.data?.message || 'Retry failed. Please check the rows and try again.';

                    setProgress(elements, 100, 'Retry failed.', true);
                    flash('error', message);
                } finally {
                    const retryButton = document.getElementById('lead-import-retry');

                    if (retryButton) {
                        retryButton.disabled = false;
                        retryButton.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }
            });
        </script>
        @endif
    @endPushOnce
</x-admin::layouts>
