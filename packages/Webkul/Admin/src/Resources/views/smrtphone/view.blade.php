<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.smrtphone.view.title', ['id' => $callLog->id])
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-4 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="smrtphone.view" :entity="$callLog" />

                <h1 class="text-xl font-bold dark:text-white">
                    @lang('admin::app.smrtphone.view.heading', [
                        'direction' => trans('admin::app.smrtphone.directions.'.($callLog->direction ?: 'unknown')),
                        'id' => $callLog->id,
                    ])
                </h1>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    @lang('admin::app.smrtphone.view.subtitle')
                </p>
            </div>

            <div class="flex items-center gap-2">
                @if ($callLog->lead_id && bouncer()->hasPermission('leads.view'))
                    <a
                        href="{{ route('admin.leads.view', $callLog->lead_id) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="secondary-button"
                    >
                        @lang('admin::app.smrtphone.view.open-crm-lead')
                    </a>
                @endif

                @if ($callLog->person_id && bouncer()->hasPermission('contacts.persons.view'))
                    <a
                        href="{{ route('admin.contacts.persons.view', $callLog->person_id) }}"
                        class="secondary-button"
                    >
                        @lang('admin::app.smrtphone.view.open-person')
                    </a>
                @endif

                @if ($callLog->recording_url)
                    <a
                        href="{{ $callLog->recording_url }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="primary-button"
                    >
                        @lang('admin::app.smrtphone.view.play-recording')
                    </a>
                @endif

                @if (bouncer()->hasPermission('smrtphone.delete'))
                    <form
                        method="POST"
                        action="{{ route('admin.smrtphone.delete', $callLog->id) }}"
                        onsubmit="return confirm('@lang('admin::app.smrtphone.view.delete-confirm')')"
                    >
                        @csrf
                        @method('DELETE')

                        <button type="submit" class="secondary-button text-red-600">
                            @lang('admin::app.smrtphone.view.delete')
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.smrtphone.view.call-details')
                </h2>

                <dl class="grid gap-3 text-sm">
                    @foreach ([
                        'external_call_id' => trans('admin::app.smrtphone.view.external-call-id'),
                        'event' => trans('admin::app.smrtphone.view.event'),
                        'direction' => trans('admin::app.smrtphone.index.datagrid.direction'),
                        'from_number' => trans('admin::app.smrtphone.view.from'),
                        'to_number' => trans('admin::app.smrtphone.view.to'),
                        'contact_phone' => trans('admin::app.smrtphone.index.datagrid.phone'),
                        'contact_name' => trans('admin::app.smrtphone.index.datagrid.contact'),
                        'caller_id_name' => trans('admin::app.smrtphone.view.caller-id'),
                        'user_name' => trans('admin::app.smrtphone.index.datagrid.agent'),
                        'device' => trans('admin::app.smrtphone.view.device'),
                        'call_status' => trans('admin::app.smrtphone.index.datagrid.status'),
                        'call_outcome' => trans('admin::app.smrtphone.index.datagrid.outcome'),
                    ] as $field => $label)
                        <div class="grid grid-cols-3 gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="col-span-2 break-all dark:text-white">
                                @if ($field === 'direction')
                                    {{ trans('admin::app.smrtphone.directions.'.($callLog->direction ?: 'unknown')) }}
                                @else
                                    {{ $callLog->{$field} ?: '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach

                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.index.datagrid.called-at')</dt>
                        <dd class="col-span-2 dark:text-white">
                            {{ $callLog->called_at?->format('M d, Y H:i') ?: '—' }}
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.index.datagrid.source')</dt>
                        <dd class="col-span-2 dark:text-white">
                            {{ $callLog->is_dialer
                                ? trans('admin::app.smrtphone.index.datagrid.dialer')
                                : trans('admin::app.smrtphone.index.datagrid.phone') }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.smrtphone.view.crm-links')
                </h2>

                <dl class="grid gap-3 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.view.person')</dt>
                        <dd class="col-span-2 dark:text-white">
                            @if ($callLog->person_id)
                                <a href="{{ route('admin.contacts.persons.view', $callLog->person_id) }}" class="text-blue-600 hover:underline">
                                    {{ $callLog->person?->name ?: '#'.$callLog->person_id }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.view.crm-lead')</dt>
                        <dd class="col-span-2 dark:text-white">
                            @if ($callLog->lead_id)
                                <a
                                    href="{{ route('admin.leads.view', $callLog->lead_id) }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="text-blue-600 hover:underline"
                                >
                                    {{ $callLog->lead?->title ?: '#'.$callLog->lead_id }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.view.activity')</dt>
                        <dd class="col-span-2 dark:text-white">
                            @if ($callLog->activity_id)
                                #{{ $callLog->activity_id }}
                                @if ($callLog->activity?->title)
                                    — {{ $callLog->activity->title }}
                                @endif
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
            <h2 class="mb-4 text-base font-semibold dark:text-white">
                @lang('admin::app.smrtphone.view.notes')
            </h2>

            <p class="whitespace-pre-wrap text-sm dark:text-white">
                {{ $callLog->call_notes ?: '—' }}
            </p>
        </div>

        @if ($callLog->ai_summary || $callLog->ai_keywords || $callLog->ai_transcript)
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.smrtphone.view.ai-insights')
                </h2>

                <dl class="grid gap-3 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.view.ai-summary')</dt>
                        <dd class="col-span-2 whitespace-pre-wrap dark:text-white">{{ $callLog->ai_summary ?: '—' }}</dd>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.view.ai-keywords')</dt>
                        <dd class="col-span-2 dark:text-white">
                            @if (is_array($callLog->ai_keywords) && count($callLog->ai_keywords))
                                {{ implode(', ', $callLog->ai_keywords) }}
                            @else
                                —
                            @endif
                        </dd>
                    </div>

                    @if ($callLog->ai_transcript)
                        <div class="grid grid-cols-3 gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">@lang('admin::app.smrtphone.view.ai-transcript')</dt>
                            <dd class="col-span-2">
                                <pre class="overflow-x-auto rounded bg-gray-50 p-3 text-xs dark:bg-gray-800 dark:text-gray-200">{{ json_encode($callLog->ai_transcript, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>
        @endif

        @if (! empty($callLog->raw_payload))
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-4 text-base font-semibold dark:text-white">
                    @lang('admin::app.smrtphone.view.raw-payload')
                </h2>

                <pre class="overflow-x-auto rounded bg-gray-50 p-3 text-xs dark:bg-gray-800 dark:text-gray-200">{{ json_encode($callLog->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</x-admin::layouts>
