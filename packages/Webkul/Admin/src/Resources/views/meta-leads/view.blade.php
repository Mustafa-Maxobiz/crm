<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.meta-leads.view.title', ['name' => $metaLead->full_name ?: '#'.$metaLead->id])
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="meta_leads.view" :entity="$metaLead" />

                <h1 class="text-xl font-bold dark:text-white">
                    {{ $metaLead->full_name ?: trans('admin::app.meta-leads.view.untitled') }}
                </h1>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    @lang('admin::app.meta-leads.view.subtitle')
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($metaLead->lead_id)
                    <a
                        href="{{ route('admin.leads.view', $metaLead->lead_id) }}"
                        class="secondary-button"
                    >
                        @lang('admin::app.meta-leads.view.open-crm-lead')
                    </a>
                @endif

                @if (bouncer()->hasPermission('meta_leads.delete'))
                    <form
                        method="POST"
                        action="{{ route('admin.meta_leads.delete', $metaLead->id) }}"
                        onsubmit="return confirm('@lang('admin::app.meta-leads.view.delete-confirm')')"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="secondary-button text-red-600">
                            @lang('admin::app.meta-leads.view.delete')
                        </button>
                    </form>
                @endif
            </div>
        </div>

        @if (! empty($metaLead->raw_payload['graph_fetch_failed']))
            <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-200">
                @lang('admin::app.meta-leads.view.fetch-failed-notice')
            </div>
        @endif

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.meta-leads.view.contact-info')
                </h2>

                <dl class="grid gap-3 text-sm">
                    @foreach ([
                        'full_name' => trans('admin::app.meta-leads.index.datagrid.name'),
                        'phone' => trans('admin::app.meta-leads.index.datagrid.phone'),
                        'email' => trans('admin::app.meta-leads.index.datagrid.email'),
                        'campaign_name' => trans('admin::app.meta-leads.index.datagrid.campaign'),
                        'form_name' => trans('admin::app.meta-leads.index.datagrid.form-name'),
                    ] as $field => $label)
                        <div class="grid grid-cols-3 gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="col-span-2 dark:text-white">{{ $metaLead->{$field} ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.meta-leads.view.meta-info')
                </h2>

                <dl class="grid gap-3 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.meta-leads.index.datagrid.status')</dt>
                        <dd class="col-span-2 dark:text-white">
                            {{ trans('admin::app.meta-leads.statuses.'.$metaLead->status) }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.meta-leads.view.leadgen-id')</dt>
                        <dd class="col-span-2 break-all dark:text-white">{{ $metaLead->leadgen_id }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.meta-leads.index.datagrid.received-date')</dt>
                        <dd class="col-span-2 dark:text-white">
                            {{ $metaLead->received_at?->format('M d, Y H:i') ?: '—' }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.meta-leads.view.crm-lead')</dt>
                        <dd class="col-span-2 dark:text-white">
                            @if ($metaLead->lead_id)
                                <a href="{{ route('admin.leads.view', $metaLead->lead_id) }}" class="text-blue-600 hover:underline">
                                    #{{ $metaLead->lead_id }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.meta-leads.view.duplicate')</dt>
                        <dd class="col-span-2 dark:text-white">
                            {{ $metaLead->is_duplicate ? trans('admin::app.meta-leads.view.yes') : trans('admin::app.meta-leads.view.no') }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        @if (bouncer()->hasPermission('meta_leads.edit'))
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.meta-leads.view.update-status')
                </h2>

                <form
                    method="POST"
                    action="{{ route('admin.meta_leads.update_status', $metaLead->id) }}"
                    class="flex flex-wrap items-end gap-3"
                >
                    @csrf
                    @method('PUT')

                    <div class="flex flex-col gap-1">
                        <label class="text-sm text-gray-600 dark:text-gray-400">
                            @lang('admin::app.meta-leads.index.datagrid.status')
                        </label>
                        <select name="status" class="rounded border border-gray-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            @foreach (\Webkul\MetaLead\Models\MetaLead::STATUSES as $status)
                                <option value="{{ $status }}" @selected($metaLead->status === $status)>
                                    {{ trans('admin::app.meta-leads.statuses.'.$status) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="primary-button">
                        @lang('admin::app.meta-leads.view.save-status')
                    </button>
                </form>
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-2 text-base font-semibold dark:text-white">
                @lang('admin::app.meta-leads.view.assigned-users')
            </h2>

            @if ($canAssignUsers)
                <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                    @lang('admin::app.meta-leads.view.assigned-users-help')
                </p>

                <form
                    method="POST"
                    action="{{ route('admin.meta_leads.update_users', $metaLead->id) }}"
                    class="flex flex-col gap-4"
                >
                    @csrf
                    @method('PUT')

                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse ($users as $user)
                            <label class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                                <input
                                    type="checkbox"
                                    name="user_ids[]"
                                    value="{{ $user->id }}"
                                    @checked(in_array($user->id, $assignedUserIds))
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                />

                                <span class="dark:text-white">
                                    {{ $user->name }}
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</span>
                                </span>
                            </label>
                        @empty
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @lang('admin::app.meta-leads.view.no-assigned-users')
                            </p>
                        @endforelse
                    </div>

                    <div>
                        <button type="submit" class="primary-button">
                            @lang('admin::app.meta-leads.view.assign-users')
                        </button>
                    </div>
                </form>
            @else
                @if ($metaLead->users->isNotEmpty())
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($metaLead->users as $user)
                            <li class="rounded-md bg-slate-100 px-3 py-1 text-sm dark:bg-slate-950 dark:text-gray-300">
                                {{ $user->name }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.meta-leads.view.no-assigned-users')
                    </p>
                @endif
            @endif
        </div>
    </div>
</x-admin::layouts>
