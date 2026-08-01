<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.index.title')
    </x-slot>

    <!-- Header -->
    {!! view_render_event('admin.leads.index.header.before') !!}

    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        {!! view_render_event('admin.leads.index.header.left.before') !!}

        <div class="flex flex-col gap-2">
            <!-- Breadcrumb's -->
            <x-admin::breadcrumbs name="leads" />

            <div class="text-xl font-bold dark:text-white">
                @lang('admin::app.leads.index.title')
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
                <x-admin::datagrid.export :src="route('admin.leads.index')" />
            @endif

            <!-- Create button for Leads -->
            <div class="flex items-center gap-x-2.5">
                @if (app(\Webkul\Lead\Services\SourceAccessService::class)->isAdmin())
                    <x-admin::modal>
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
                                action="{{ route('admin.leads.import') }}"
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
                                        Bulk imports always use <strong>Cold Call</strong> as the source and get the <strong>Cold Lead</strong> tag.
                                    </p>

                                    <p class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400">
                                        Current local PHP upload limit is 2 MB per file. Use split CSV files or increase upload_max_filesize.
                                    </p>

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Required: companies*, lead_value*, type*, pricing_type*
                                    </p>
                                </div>

                                <a
                                    href="{{ route('admin.leads.import.template') }}"
                                    class="secondary-button w-max"
                                >
                                    Download Template
                                </a>

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

                    <a
                        href="{{ route('admin.leads.disqualified') }}"
                        class="secondary-button"
                    >
                        @lang('admin::app.leads.disqualification.short-title')
                    </a>
                @endif

                @if (bouncer()->hasPermission('leads.create'))
                    <a
                        href="{{ route('admin.leads.create', request()->query()) }}"
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

        <script type="module">
            const leadImportStartUrl = @json(route('admin.leads.import.start'));
            const leadImportProcessUrl = @json(route('admin.leads.import.process'));
            const leadImportMaxFileSize = 2 * 1024 * 1024;

            const importElements = () => ({
                form: document.getElementById('lead-import-form'),
                file: document.getElementById('lead-import-file'),
                submit: document.getElementById('lead-import-submit'),
                progress: document.getElementById('lead-import-progress'),
                bar: document.getElementById('lead-import-progress-bar'),
                percent: document.getElementById('lead-import-progress-percent'),
                status: document.getElementById('lead-import-progress-status'),
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

                setProgress(
                    elements,
                    100,
                    `${result.message} Processed ${result.processed} of ${total} rows. Created ${result.created}.`
                );

                flash(
                    result.errors?.length ? 'warning' : 'success',
                    result.errors?.length
                        ? `${result.message} ${result.errors.length} row(s) failed.`
                        : result.message
                );

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

                if (elements.file.files[0].size > leadImportMaxFileSize) {
                    flash('error', 'This file is larger than the current 2 MB PHP upload limit. Please use a split CSV file or increase upload_max_filesize.');

                    return;
                }

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
        </script>
    @endPushOnce
</x-admin::layouts>
