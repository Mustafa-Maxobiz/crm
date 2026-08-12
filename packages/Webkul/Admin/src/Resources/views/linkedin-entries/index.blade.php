<x-admin::layouts>
    <x-slot:title>
        LinkedIn Entries
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4 rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900 max-sm:flex-wrap">
            <div class="grid gap-1">
                <p class="text-xl font-bold text-gray-800 dark:text-white">
                    LinkedIn Entries
                </p>

                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Track LinkedIn profile requests handled by lead generation users.
                </p>
            </div>

            @if (bouncer()->hasPermission('linkedin_entries.create') || bouncer()->hasPermission('linkedin_entries.edit'))
                <div class="flex items-center gap-3">
                    @if (bouncer()->hasPermission('linkedin_entries.edit'))
                        <x-admin::modal ref="linkedinAcceptedImportModal">
                            <x-slot:toggle>
                                <button
                                    type="button"
                                    class="secondary-button"
                                >
                                    Import Accepted
                                </button>
                            </x-slot>

                            <x-slot:header>
                                <p class="text-lg font-bold text-gray-800 dark:text-white">
                                    Import Accepted Requests
                                </p>
                            </x-slot>

                            <x-slot:content>
                                <form
                                    id="linkedin-accepted-import-form"
                                    class="grid gap-4"
                                >
                                    @csrf

                                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                        <p class="font-semibold text-gray-800 dark:text-white">
                                            Accepted files: .csv, .xlsx, .xls
                                        </p>

                                        <p class="mt-1">
                                            Upload profile URLs only. Matching entries will be marked as Accepted.
                                        </p>

                                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                            Required: profile_url*
                                        </p>
                                    </div>

                                    <a
                                        href="{{ route('admin.linkedin_entries.accepted_import_template') }}"
                                        class="secondary-button w-max"
                                    >
                                        Download Template
                                    </a>

                                    <div class="grid gap-1">
                                        <label class="text-sm font-medium text-gray-800 dark:text-white">
                                            Upload File *
                                        </label>

                                        <input
                                            id="linkedin-accepted-import-file"
                                            type="file"
                                            name="file"
                                            accept=".csv,.txt,.xlsx,.xls"
                                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                            required
                                        />
                                    </div>

                                    <div
                                        id="linkedin-accepted-import-progress"
                                        class="hidden rounded-md border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                    >
                                        <div class="flex items-center justify-between text-xs font-semibold text-gray-700 dark:text-gray-300">
                                            <span id="linkedin-accepted-import-progress-status">Preparing import...</span>
                                            <span id="linkedin-accepted-import-progress-percent">0.00%</span>
                                        </div>

                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div
                                                id="linkedin-accepted-import-progress-bar"
                                                class="h-full w-0 rounded-full bg-red-600 transition-all duration-300"
                                            ></div>
                                        </div>
                                    </div>

                                    <div
                                        id="linkedin-accepted-import-overview"
                                        class="hidden rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                    ></div>

                                    <div
                                        id="linkedin-accepted-import-missing"
                                        class="hidden rounded-md border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                    >
                                        <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-white">
                                            Skipped Profiles
                                        </p>

                                        <div class="max-h-64 overflow-auto rounded-md border border-gray-200 dark:border-gray-800">
                                            <table class="w-full table-fixed text-left text-xs">
                                                <thead class="sticky top-0 bg-gray-50 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                                                    <tr>
                                                        <th class="w-[70px] px-2 py-2">Row</th>
                                                        <th class="w-[170px] px-2 py-2">Reason</th>
                                                        <th class="px-2 py-2">Profile URL</th>
                                                    </tr>
                                                </thead>

                                                <tbody
                                                    id="linkedin-accepted-import-missing-body"
                                                    class="divide-y divide-gray-100 dark:divide-gray-800"
                                                ></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </form>
                            </x-slot>

                            <x-slot:footer>
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        class="secondary-button"
                                        @click="$refs.linkedinAcceptedImportModal.close()"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        id="linkedin-accepted-import-submit"
                                        type="submit"
                                        form="linkedin-accepted-import-form"
                                        class="primary-button"
                                    >
                                        Upload Accepted
                                    </button>
                                </div>
                            </x-slot>
                        </x-admin::modal>
                    @endif

                    @if (bouncer()->hasPermission('linkedin_entries.create'))
                        <x-admin::modal
                            ref="linkedinEntryImportModal"
                            :is-active="$errors->has('file') || $errors->has('import_user_id')"
                        >
                        <x-slot:toggle>
                            <button
                                type="button"
                                class="secondary-button"
                            >
                                Import Entries
                            </button>
                        </x-slot>

                        <x-slot:header>
                            <p class="text-lg font-bold text-gray-800 dark:text-white">
                                Import LinkedIn Entries
                            </p>
                        </x-slot>

                        <x-slot:content>
                            <form
                                id="linkedin-entry-import-form"
                                method="POST"
                                action="{{ route('admin.linkedin_entries.import') }}"
                                enctype="multipart/form-data"
                                class="grid gap-4"
                            >
                                @csrf

                                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                    <p class="font-semibold text-gray-800 dark:text-white">
                                        Accepted files: .csv, .xlsx, .xls
                                    </p>

                                    <p class="mt-1">
                                        Required columns are marked with *. New imported entries are saved as Pending.
                                    </p>

                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                        Required: name*, profile_url*
                                    </p>
                                </div>

                                <a
                                    href="{{ route('admin.linkedin_entries.import_template') }}"
                                    class="secondary-button w-max"
                                >
                                    Download Template
                                </a>

                                @if ($isAdmin)
                                    <div class="grid gap-1">
                                        <label class="text-sm font-medium text-gray-800 dark:text-white">
                                            Entry Owner *
                                        </label>

                                        <select
                                            name="import_user_id"
                                            class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                            required
                                        >
                                            <option value="">Select user</option>

                                            @foreach ($availableUsers as $availableUser)
                                                <option
                                                    value="{{ $availableUser->id }}"
                                                    @selected(old('import_user_id') == $availableUser->id)
                                                >
                                                    {{ $availableUser->name }}{{ isset($availableUser->email) ? ' ('.$availableUser->email.')' : '' }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('import_user_id')
                                            <p class="text-xs italic text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif

                                <div class="grid gap-1">
                                    <label class="text-sm font-medium text-gray-800 dark:text-white">
                                        Upload File *
                                    </label>

                                    <input
                                        id="linkedin-entry-import-file"
                                        type="file"
                                        name="file"
                                        accept=".csv,.txt,.xlsx,.xls"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                        required
                                    />

                                    @error('file')
                                        <p class="text-xs italic text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div
                                    id="linkedin-entry-import-progress"
                                    class="hidden rounded-md border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div class="flex items-center justify-between text-xs font-semibold text-gray-700 dark:text-gray-300">
                                        <span id="linkedin-entry-import-progress-status">Preparing import...</span>
                                        <span id="linkedin-entry-import-progress-percent">0.00%</span>
                                    </div>

                                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div
                                            id="linkedin-entry-import-progress-bar"
                                            class="h-full w-0 rounded-full bg-red-600 transition-all duration-300"
                                        ></div>
                                    </div>
                                </div>

                                <div
                                    id="linkedin-entry-import-overview"
                                    class="hidden rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                ></div>

                                <div
                                    id="linkedin-entry-import-failed"
                                    class="hidden rounded-md border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                >
                                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                                Entries Need Review
                                            </p>

                                            <p
                                                id="linkedin-entry-import-failed-summary"
                                                class="text-xs text-gray-500 dark:text-gray-400"
                                            ></p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                id="linkedin-entry-import-skip-all"
                                                type="button"
                                                class="secondary-button !min-h-[34px] !px-3 text-xs"
                                            >
                                                Skip All
                                            </button>

                                            <button
                                                id="linkedin-entry-import-retry"
                                                type="button"
                                                class="primary-button !min-h-[34px] !px-3 text-xs"
                                            >
                                                Retry Corrected
                                            </button>
                                        </div>
                                    </div>

                                    <div class="max-h-72 overflow-auto rounded-md border border-gray-200 dark:border-gray-800">
                                        <table class="w-full table-fixed text-left text-xs">
                                            <thead class="sticky top-0 bg-gray-50 text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                                                <tr>
                                                    <th class="w-[70px] px-2 py-2">Action</th>
                                                    <th class="w-[48px] px-2 py-2">Row</th>
                                                    <th class="w-[120px] px-2 py-2">Issue</th>
                                                    <th class="px-2 py-2">Name*</th>
                                                    <th class="px-2 py-2">Profile URL*</th>
                                                </tr>
                                            </thead>

                                            <tbody
                                                id="linkedin-entry-import-failed-body"
                                                class="divide-y divide-gray-100 dark:divide-gray-800"
                                            ></tbody>
                                        </table>
                                    </div>
                                </div>
                            </form>
                        </x-slot>

                        <x-slot:footer>
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    type="button"
                                    class="secondary-button"
                                    @click="$refs.linkedinEntryImportModal.close()"
                                >
                                    Cancel
                                </button>

                                <button
                                    id="linkedin-entry-import-submit"
                                    type="submit"
                                    form="linkedin-entry-import-form"
                                    class="primary-button"
                                >
                                    Upload Entries
                                </button>
                            </div>
                        </x-slot>
                        </x-admin::modal>

                    <x-admin::modal
                        ref="linkedinEntryCreateModal"
                        :is-active="$errors->has('user_id') || $errors->has('name') || $errors->has('url')"
                    >
                    <x-slot:toggle>
                        <button
                            type="button"
                            class="primary-button"
                        >
                            Add Entry
                        </button>
                    </x-slot>

                    <x-slot:header>
                        <p class="text-lg font-bold text-gray-800 dark:text-white">
                            Add LinkedIn Entry
                        </p>
                    </x-slot>

                    <x-slot:content>
                        <form
                            id="linkedin-entry-create-form"
                            method="POST"
                            action="{{ route('admin.linkedin_entries.store') }}"
                            class="grid gap-4"
                        >
                            @csrf

                            @if ($isAdmin)
                                <div class="grid gap-1">
                                    <label class="text-sm font-medium text-gray-800 dark:text-white">
                                        Entry Owner *
                                    </label>

                                    <select
                                        name="user_id"
                                        class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                        required
                                    >
                                        <option value="">Select user</option>

                                        @foreach ($availableUsers as $availableUser)
                                            <option
                                                value="{{ $availableUser->id }}"
                                                @selected(old('user_id') == $availableUser->id)
                                            >
                                                {{ $availableUser->name }}{{ isset($availableUser->email) ? ' ('.$availableUser->email.')' : '' }}
                                            </option>
                                        @endforeach
                                    </select>

                                    @error('user_id')
                                        <p class="text-xs italic text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endif

                            <div class="grid gap-1">
                                <label class="text-sm font-medium text-gray-800 dark:text-white">
                                    User Name *
                                </label>

                                <input
                                    type="text"
                                    name="name"
                                    value="{{ old('name') }}"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                    placeholder="LinkedIn profile name"
                                    required
                                />

                                @error('name')
                                    <p class="text-xs italic text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid gap-1">
                                <label class="text-sm font-medium text-gray-800 dark:text-white">
                                    Profile URL *
                                </label>

                                <input
                                    type="text"
                                    name="url"
                                    value="{{ old('url') }}"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                    placeholder="https://www.linkedin.com/in/..."
                                    required
                                />

                                @error('url')
                                    <p class="text-xs italic text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                        </form>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                class="secondary-button"
                                @click="$refs.linkedinEntryCreateModal.close()"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                form="linkedin-entry-create-form"
                                class="primary-button"
                            >
                                Save Entry
                            </button>
                        </div>
                    </x-slot>
                    </x-admin::modal>
                    @endif
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                <form
                    id="linkedin-entry-filter-form"
                    method="GET"
                    action="{{ route('admin.linkedin_entries.index') }}"
                    class="grid gap-3"
                >
                    <div class="flex items-center justify-between gap-3 max-lg:flex-wrap">
                        <div class="relative w-full">
                            <input
                                id="linkedin-entry-search"
                                type="text"
                                name="search"
                                value="{{ $filters['search'] }}"
                                class="min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white ltr:pr-10 rtl:pl-10"
                                placeholder="{{ $isAdmin ? 'Search by name, URL, status, or entry owner' : 'Search by name, URL, or status' }}"
                                autocomplete="off"
                                oninput="clearTimeout(window.linkedinEntrySearchTimer); if (! this.value.trim()) { document.getElementById('linkedin-entry-search-spinner')?.classList.add('hidden'); this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit(); return; } document.getElementById('linkedin-entry-search-spinner')?.classList.remove('hidden'); window.linkedinEntrySearchTimer = setTimeout(() => { this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit(); }, 2000);"
                                onkeydown="if (event.key === 'Enter') { clearTimeout(window.linkedinEntrySearchTimer); document.getElementById('linkedin-entry-search-spinner')?.classList.add('hidden'); }"
                            />

                            <div
                                id="linkedin-entry-search-spinner"
                                class="hidden absolute top-1/2 -translate-y-1/2 ltr:right-3 rtl:left-3"
                                title="Searching..."
                            >
                                <div class="app-search-spinner"></div>
                            </div>
                        </div>

                        <button
                            id="linkedin-entry-filter-toggle"
                            type="button"
                            class="secondary-button shrink-0"
                            onclick="document.getElementById('linkedin-entry-filter-panel')?.classList.toggle('hidden')"
                        >
                            Filter
                        </button>

                        <p class="shrink-0 text-sm text-gray-500 dark:text-gray-400">
                            {{ $entries->firstItem() ?? 0 }} - {{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }}
                        </p>
                    </div>

                    <div
                        id="linkedin-entry-filter-panel"
                        class="{{ $hasFilters ? '' : 'hidden' }} rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-gray-950"
                    >
                        <div class="grid grid-cols-4 gap-3 max-xl:grid-cols-2 max-sm:grid-cols-1">
                            <div class="grid gap-1">
                                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Status
                                </label>

                                <select
                                    name="status"
                                    class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                >
                                    <option value="">All statuses</option>

                                    @foreach ($statuses as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            @selected($filters['status'] === $value)
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            @if ($isAdmin)
                                <div class="grid gap-1">
                                    <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                        Entry Owner
                                    </label>

                                    <select
                                        name="user_id"
                                        class="custom-select min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                    >
                                        <option value="">All users</option>

                                        @foreach ($availableUsers as $availableUser)
                                            <option
                                                value="{{ $availableUser->id }}"
                                                @selected((string) $filters['user_id'] === (string) $availableUser->id)
                                            >
                                                {{ $availableUser->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="grid gap-1">
                                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Created From
                                </label>

                                <input
                                    type="date"
                                    name="date_from"
                                    value="{{ $filters['date_from'] }}"
                                    class="min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                    Created To
                                </label>

                                <input
                                    type="date"
                                    name="date_to"
                                    value="{{ $filters['date_to'] }}"
                                    class="min-h-[39px] w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                />
                            </div>
                        </div>

                        <div class="mt-3 flex items-center justify-end gap-3">
                            <a
                                href="{{ route('admin.linkedin_entries.index') }}"
                                class="secondary-button"
                            >
                                Reset
                            </a>

                            <button
                                type="submit"
                                class="primary-button"
                            >
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full {{ $isAdmin ? 'min-w-[900px]' : 'min-w-[720px]' }} border-collapse text-left">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-sm text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                            <th class="w-[90px] px-4 py-3 font-semibold">ID</th>
                            <th class="w-[220px] px-4 py-3 font-semibold">User Name</th>
                            <th class="px-4 py-3 font-semibold">Profile URL</th>
                            @if ($isAdmin)
                                <th class="w-[180px] px-4 py-3 font-semibold">Entry Owner</th>
                            @endif
                            <th class="w-[190px] px-4 py-3 font-semibold">Status</th>
                            <th class="w-[170px] px-4 py-3 font-semibold">Created At</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($entries as $entry)
                            <tr class="border-b border-gray-200 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-300">
                                <td class="px-4 py-3 align-middle">{{ $entry->id }}</td>

                                <td class="px-4 py-3 align-middle font-medium text-gray-800 dark:text-white">
                                    {{ $entry->name }}
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <a
                                        href="{{ $entry->url }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="break-all text-brandColor hover:underline"
                                    >
                                        {{ $entry->url }}
                                    </a>
                                </td>

                                @if ($isAdmin)
                                    <td class="px-4 py-3 align-middle">{{ $entry->owner_name }}</td>
                                @endif

                                <td class="px-4 py-3 align-middle">
                                    @if (bouncer()->hasPermission('linkedin_entries.edit'))
                                        <form
                                            method="POST"
                                            action="{{ route('admin.linkedin_entries.update_status', $entry->id) }}"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <select
                                                name="status"
                                                class="custom-select min-h-[34px] w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                                                onchange="this.form.submit()"
                                            >
                                                @foreach ($statuses as $value => $label)
                                                    <option
                                                        value="{{ $value }}"
                                                        @selected($entry->status === $value)
                                                    >
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @else
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                            'bg-yellow-100 text-yellow-800' => $entry->status === 'pending',
                                            'bg-green-100 text-green-800' => $entry->status === 'accepted',
                                            'bg-blue-100 text-blue-800' => $entry->status === 'response',
                                        ])>
                                            {{ $statuses[$entry->status] ?? ucfirst($entry->status) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    {{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('d M Y h:i A') : '--' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td
                                    colspan="{{ $isAdmin ? 6 : 5 }}"
                                    class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    No LinkedIn entries found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 p-4 dark:border-gray-800">
                {{ $entries->links() }}
            </div>
        </div>
    </div>

    @if (bouncer()->hasPermission('linkedin_entries.create') || bouncer()->hasPermission('linkedin_entries.edit'))
        @pushOnce('scripts')
            <script type="module">
                const linkedinImportStartUrl = @json(route('admin.linkedin_entries.import_start'));
                const linkedinImportProcessUrl = @json(route('admin.linkedin_entries.import_process'));
                const linkedinImportRetryUrl = @json(route('admin.linkedin_entries.import_retry'));
                const linkedinAcceptedImportStartUrl = @json(route('admin.linkedin_entries.accepted_import_start'));
                const linkedinAcceptedImportProcessUrl = @json(route('admin.linkedin_entries.accepted_import_process'));
                const linkedinImportMaxFileSize = 10 * 1024 * 1024;
                let failedLinkedinImportRows = [];

                const linkedinImportElements = () => ({
                    form: document.getElementById('linkedin-entry-import-form'),
                    file: document.getElementById('linkedin-entry-import-file'),
                    submit: document.getElementById('linkedin-entry-import-submit'),
                    retry: document.getElementById('linkedin-entry-import-retry'),
                    progress: document.getElementById('linkedin-entry-import-progress'),
                    bar: document.getElementById('linkedin-entry-import-progress-bar'),
                    percent: document.getElementById('linkedin-entry-import-progress-percent'),
                    status: document.getElementById('linkedin-entry-import-progress-status'),
                    overview: document.getElementById('linkedin-entry-import-overview'),
                    failed: document.getElementById('linkedin-entry-import-failed'),
                    failedSummary: document.getElementById('linkedin-entry-import-failed-summary'),
                    failedBody: document.getElementById('linkedin-entry-import-failed-body'),
                });

                const linkedinAcceptedImportElements = () => ({
                    form: document.getElementById('linkedin-accepted-import-form'),
                    file: document.getElementById('linkedin-accepted-import-file'),
                    submit: document.getElementById('linkedin-accepted-import-submit'),
                    progress: document.getElementById('linkedin-accepted-import-progress'),
                    bar: document.getElementById('linkedin-accepted-import-progress-bar'),
                    percent: document.getElementById('linkedin-accepted-import-progress-percent'),
                    status: document.getElementById('linkedin-accepted-import-progress-status'),
                    overview: document.getElementById('linkedin-accepted-import-overview'),
                    missing: document.getElementById('linkedin-accepted-import-missing'),
                    missingBody: document.getElementById('linkedin-accepted-import-missing-body'),
                });

                const linkedinFlash = (type, message) => {
                    window.emitter?.emit('add-flash', { type, message });
                };

                const escapeLinkedinImportHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#39;');

                const setLinkedinImportProgress = (elements, percent, status, isError = false) => {
                    const boundedPercent = Math.max(0, Math.min(100, Number(percent)));

                    elements.progress.classList.remove('hidden');
                    elements.bar.style.width = `${boundedPercent}%`;
                    elements.percent.textContent = `${boundedPercent.toFixed(2)}%`;
                    elements.status.textContent = status;
                    elements.bar.classList.toggle('bg-green-600', boundedPercent === 100 && ! isError);
                    elements.bar.classList.toggle('bg-red-600', boundedPercent !== 100 || isError);
                };

                const linkedinFieldInput = (rowIndex, field, value) => `
                    <td class="px-2 py-2 align-top">
                        <input
                            type="text"
                            data-linkedin-row-index="${rowIndex}"
                            data-linkedin-field="${field}"
                            value="${escapeLinkedinImportHtml(value ?? '')}"
                            class="w-full min-w-0 rounded border border-gray-200 px-2 py-1 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200"
                        />
                    </td>
                `;

                const collectLinkedinFailedRowsFromTable = () => {
                    const elements = linkedinImportElements();

                    return failedLinkedinImportRows.map((row, index) => {
                        const data = { ...(row.data || {}) };

                        ['name', 'profile_url'].forEach((field) => {
                            const input = elements.failedBody.querySelector(
                                `input[data-linkedin-row-index="${index}"][data-linkedin-field="${field}"]`
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

                const renderLinkedinFailedRows = (rows) => {
                    const elements = linkedinImportElements();
                    failedLinkedinImportRows = Array.isArray(rows) ? rows : [];

                    if (! failedLinkedinImportRows.length) {
                        elements.failed.classList.add('hidden');
                        elements.failedBody.innerHTML = '';
                        elements.failedSummary.textContent = '';

                        return;
                    }

                    elements.failed.classList.remove('hidden');
                    elements.failedSummary.textContent = `${failedLinkedinImportRows.length} row(s) need correction. Correct them and retry, or skip the rows.`;
                    elements.failedBody.innerHTML = failedLinkedinImportRows.map((row, index) => {
                        const data = row.data || {};

                        return `
                            <tr class="bg-white dark:bg-gray-900" data-linkedin-failed-index="${index}">
                                <td class="px-2 py-2 align-top">
                                    <button
                                        type="button"
                                        data-linkedin-remove-index="${index}"
                                        class="secondary-button !min-h-[28px] !px-2 text-[11px]"
                                    >
                                        Skip
                                    </button>
                                </td>
                                <td class="px-2 py-2 align-top font-semibold text-gray-700 dark:text-gray-200">${escapeLinkedinImportHtml(row.row_number)}</td>
                                <td class="break-words px-2 py-2 align-top text-red-600 dark:text-red-400">${escapeLinkedinImportHtml(row.error || 'Import failed')}</td>
                                ${linkedinFieldInput(index, 'name', data.name)}
                                ${linkedinFieldInput(index, 'profile_url', data.profile_url)}
                            </tr>
                        `;
                    }).join('');
                };

                const renderLinkedinImportOverview = (elements, result) => {
                    const failed = Number(result.failed || 0);
                    const skipped = Number(result.skipped || 0);
                    const created = Number(result.created || 0);
                    const processed = Number(result.processed || 0);
                    const total = Number(result.total || 0);
                    const errorItems = (result.errors || [])
                        .slice(0, 8)
                        .map((error) => `<li>${escapeLinkedinImportHtml(error)}</li>`)
                        .join('');

                    elements.overview.classList.remove('hidden');
                    elements.overview.innerHTML = `
                        <div class="grid gap-2">
                            <p class="font-semibold text-gray-800 dark:text-white">Import Overview</p>
                            <div class="grid grid-cols-4 gap-2 max-sm:grid-cols-2">
                                <div><span class="block text-xs text-gray-500">Processed</span><strong>${processed} / ${total}</strong></div>
                                <div><span class="block text-xs text-gray-500">Created</span><strong>${created}</strong></div>
                                <div><span class="block text-xs text-gray-500">Skipped Duplicates</span><strong>${skipped}</strong></div>
                                <div><span class="block text-xs text-gray-500">Failed</span><strong>${failed}</strong></div>
                            </div>
                            ${errorItems ? `<ul class="mt-1 list-disc pl-5 text-xs text-red-600 dark:text-red-400">${errorItems}</ul>` : ''}
                        </div>
                    `;
                };

                const renderLinkedinAcceptedImportOverview = (elements, result) => {
                    const processed = Number(result.processed || 0);
                    const total = Number(result.total || 0);
                    const updated = Number(result.updated || 0);
                    const missing = Number(result.missing || 0);
                    const failed = Number(result.failed || 0);

                    elements.overview.classList.remove('hidden');
                    elements.overview.innerHTML = `
                        <div class="grid gap-2">
                            <p class="font-semibold text-gray-800 dark:text-white">Import Overview</p>
                            <div class="grid grid-cols-4 gap-2 max-sm:grid-cols-2">
                                <div><span class="block text-xs text-gray-500">Processed</span><strong>${processed} / ${total}</strong></div>
                                <div><span class="block text-xs text-gray-500">Marked Accepted</span><strong>${updated}</strong></div>
                                <div><span class="block text-xs text-gray-500">Not Sent Request</span><strong>${missing}</strong></div>
                                <div><span class="block text-xs text-gray-500">Failed</span><strong>${failed}</strong></div>
                            </div>
                        </div>
                    `;
                };

                const renderLinkedinAcceptedSkippedRows = (elements, result) => {
                    const rows = [
                        ...(result.missing_rows || []),
                        ...(result.failed_rows || []),
                    ];

                    if (! rows.length) {
                        elements.missing.classList.add('hidden');
                        elements.missingBody.innerHTML = '';

                        return;
                    }

                    elements.missing.classList.remove('hidden');
                    elements.missingBody.innerHTML = rows.map((row) => `
                        <tr class="bg-white dark:bg-gray-900">
                            <td class="px-2 py-2 align-top font-semibold text-gray-700 dark:text-gray-200">${escapeLinkedinImportHtml(row.row_number)}</td>
                            <td class="break-words px-2 py-2 align-top text-red-600 dark:text-red-400">${escapeLinkedinImportHtml(row.error || 'Skipped')}</td>
                            <td class="break-all px-2 py-2 align-top text-gray-700 dark:text-gray-200">${escapeLinkedinImportHtml(row.profile_url || '--')}</td>
                        </tr>
                    `).join('');
                };

                const retryLinkedinFailedRows = async () => {
                    if (! failedLinkedinImportRows.length) {
                        linkedinFlash('error', 'There are no failed rows to retry.');

                        return;
                    }

                    const elements = linkedinImportElements();
                    const rows = collectLinkedinFailedRowsFromTable();
                    const formData = new FormData(elements.form);

                    elements.retry.disabled = true;
                    elements.retry.classList.add('opacity-70', 'cursor-not-allowed');
                    setLinkedinImportProgress(elements, 0, `Retrying ${rows.length} corrected row(s)...`);

                    try {
                        const response = await window.axios.post(linkedinImportRetryUrl, {
                            import_user_id: formData.get('import_user_id') || null,
                            rows,
                        }, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        const result = response.data;

                        setLinkedinImportProgress(
                            elements,
                            100,
                            `Retry finished. Created ${result.created}. Skipped duplicates ${result.skipped}. Failed ${result.failed}.`,
                            Number(result.failed || 0) > 0
                        );

                        renderLinkedinImportOverview(elements, result);
                        renderLinkedinFailedRows(result.failed_rows || []);

                        if (Number(result.failed || 0) > 0) {
                            linkedinFlash('warning', `Retry finished with ${result.failed} row(s) still needing correction.`);
                        } else {
                            linkedinFlash('success', result.message || 'Corrected rows imported.');
                        }
                    } catch (error) {
                        const message = error.response?.data?.message || 'Retry failed. Please check the rows and try again.';

                        setLinkedinImportProgress(elements, 100, 'Retry failed.', true);
                        linkedinFlash('error', message);
                    } finally {
                        elements.retry.disabled = false;
                        elements.retry.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                };

                const processLinkedinImportChunk = async (token, offset) => {
                    const response = await window.axios.post(linkedinImportProcessUrl, {
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

                const processLinkedinImport = async (elements, token, total) => {
                    let offset = 0;
                    let result = null;

                    do {
                        result = await processLinkedinImportChunk(token, offset);
                        offset = result.processed;

                        const percent = total ? (result.processed / total) * 100 : 100;

                        setLinkedinImportProgress(
                            elements,
                            percent,
                            `Imported ${result.processed} of ${total} rows. Created ${result.created}. Skipped ${result.skipped}.`
                        );
                    } while (! result.done);

                    const failed = Number(result.failed || 0);

                    setLinkedinImportProgress(
                        elements,
                        100,
                        `Finished. Created ${result.created}. Skipped duplicates ${result.skipped}. Failed ${failed}.`,
                        failed > 0
                    );

                    renderLinkedinImportOverview(elements, result);
                    renderLinkedinFailedRows(result.failed_rows || []);

                    if (failed > 0) {
                        linkedinFlash('warning', `Import finished with ${failed} failed row(s). Created ${result.created}, skipped ${result.skipped}.`);
                    } else {
                        linkedinFlash('success', `Import finished. Created ${result.created}, skipped ${result.skipped}.`);
                    }

                    elements.submit.disabled = false;
                    elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                };

                const processLinkedinAcceptedImportChunk = async (token, offset) => {
                    const response = await window.axios.post(linkedinAcceptedImportProcessUrl, {
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

                const processLinkedinAcceptedImport = async (elements, token, total) => {
                    let offset = 0;
                    let result = null;

                    do {
                        result = await processLinkedinAcceptedImportChunk(token, offset);
                        offset = result.processed;

                        const percent = total ? (result.processed / total) * 100 : 100;

                        setLinkedinImportProgress(
                            elements,
                            percent,
                            `Processed ${result.processed} of ${total} URLs. Accepted ${result.updated}. Not sent request ${result.missing}.`
                        );
                    } while (! result.done);

                    const failed = Number(result.failed || 0);
                    const missing = Number(result.missing || 0);

                    setLinkedinImportProgress(
                        elements,
                        100,
                        `Finished. Accepted ${result.updated}. Not sent request ${missing}. Failed ${failed}.`,
                        failed > 0
                    );

                    renderLinkedinAcceptedImportOverview(elements, result);
                    renderLinkedinAcceptedSkippedRows(elements, result);

                    if (failed > 0 || missing > 0) {
                        linkedinFlash('warning', `Accepted import finished. Accepted ${result.updated}, not sent request ${missing}, failed ${failed}.`);
                    } else {
                        linkedinFlash('success', result.message || `Accepted import finished. Accepted ${result.updated}.`);
                    }

                    elements.submit.disabled = false;
                    elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                };

                document.addEventListener('submit', async (event) => {
                    if (event.target?.id === 'linkedin-accepted-import-form') {
                        event.preventDefault();
                        event.stopPropagation();

                        const elements = linkedinAcceptedImportElements();

                        if (! elements.file?.files?.length) {
                            linkedinFlash('error', 'Please select a file to import.');

                            return;
                        }

                        if (elements.file.files[0].size > linkedinImportMaxFileSize) {
                            linkedinFlash('error', 'This file is larger than 10 MB. Please use a smaller file.');

                            return;
                        }

                        elements.overview.classList.add('hidden');
                        elements.overview.innerHTML = '';
                        elements.missing.classList.add('hidden');
                        elements.missingBody.innerHTML = '';
                        elements.submit.disabled = true;
                        elements.submit.classList.add('opacity-70', 'cursor-not-allowed');

                        setLinkedinImportProgress(elements, 0, 'Uploading file...');

                        try {
                            const startResponse = await window.axios.post(linkedinAcceptedImportStartUrl, new FormData(elements.form), {
                                headers: {
                                    Accept: 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Content-Type': 'multipart/form-data',
                                },

                                onUploadProgress(uploadEvent) {
                                    if (! uploadEvent.total) {
                                        return;
                                    }

                                    setLinkedinImportProgress(elements, (uploadEvent.loaded / uploadEvent.total) * 10, 'Uploading file...');
                                },
                            });

                            const { token, total } = startResponse.data;

                            setLinkedinImportProgress(elements, 10, `Prepared ${total} URLs. Starting status update...`);

                            await processLinkedinAcceptedImport(elements, token, total);
                        } catch (error) {
                            const message = error.response?.data?.message || 'Accepted import failed. Please check the file and try again.';

                            setLinkedinImportProgress(elements, 100, 'Accepted import failed.', true);
                            linkedinFlash('error', message);

                            elements.submit.disabled = false;
                            elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                        }

                        return;
                    }

                    if (event.target?.id !== 'linkedin-entry-import-form') {
                        return;
                    }

                    event.preventDefault();
                    event.stopPropagation();

                    const elements = linkedinImportElements();

                    if (! elements.file?.files?.length) {
                        linkedinFlash('error', 'Please select a file to import.');

                        return;
                    }

                    if (elements.file.files[0].size > linkedinImportMaxFileSize) {
                        linkedinFlash('error', 'This file is larger than 10 MB. Please use a smaller file.');

                        return;
                    }

                    elements.overview.classList.add('hidden');
                    elements.overview.innerHTML = '';
                    renderLinkedinFailedRows([]);
                    elements.submit.disabled = true;
                    elements.submit.classList.add('opacity-70', 'cursor-not-allowed');

                    setLinkedinImportProgress(elements, 0, 'Uploading file...');

                    try {
                        const startResponse = await window.axios.post(linkedinImportStartUrl, new FormData(elements.form), {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Content-Type': 'multipart/form-data',
                            },

                            onUploadProgress(uploadEvent) {
                                if (! uploadEvent.total) {
                                    return;
                                }

                                setLinkedinImportProgress(elements, (uploadEvent.loaded / uploadEvent.total) * 10, 'Uploading file...');
                            },
                        });

                        const { token, total } = startResponse.data;

                        setLinkedinImportProgress(elements, 10, `Prepared ${total} rows. Starting import...`);

                        await processLinkedinImport(elements, token, total);
                    } catch (error) {
                        const message = error.response?.data?.message || 'Import failed. Please check the file and try again.';

                        setLinkedinImportProgress(elements, 100, 'Import failed.', true);
                        linkedinFlash('error', message);

                        elements.submit.disabled = false;
                        elements.submit.classList.remove('opacity-70', 'cursor-not-allowed');
                    }
                }, true);

                document.addEventListener('click', async (event) => {
                    const removeButton = event.target.closest('#linkedin-entry-import-failed [data-linkedin-remove-index]');

                    if (removeButton) {
                        event.preventDefault();
                        event.stopPropagation();

                        failedLinkedinImportRows = collectLinkedinFailedRowsFromTable().map((row, index) => ({
                            ...row,
                            error: failedLinkedinImportRows[index]?.error || 'Import failed',
                        }));

                        const index = Number(removeButton.getAttribute('data-linkedin-remove-index'));

                        if (! Number.isNaN(index)) {
                            failedLinkedinImportRows.splice(index, 1);
                            renderLinkedinFailedRows(failedLinkedinImportRows);
                            linkedinFlash('success', 'Row skipped from retry list.');
                        }

                        return;
                    }

                    if (event.target.closest('#linkedin-entry-import-skip-all')) {
                        event.preventDefault();
                        event.stopPropagation();

                        renderLinkedinFailedRows([]);
                        linkedinFlash('success', 'All failed rows skipped.');

                        return;
                    }

                    if (event.target.closest('#linkedin-entry-import-retry')) {
                        event.preventDefault();
                        event.stopPropagation();

                        await retryLinkedinFailedRows();
                    }
                });
            </script>
        @endPushOnce
    @endif
</x-admin::layouts>
