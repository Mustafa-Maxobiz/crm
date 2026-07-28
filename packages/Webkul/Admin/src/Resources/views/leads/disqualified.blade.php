<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.disqualification.page-title')
    </x-slot>

    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
        <div class="flex flex-col gap-2">
            <div class="text-sm text-red-600">
                <a href="{{ route('admin.leads.index') }}">
                    @lang('admin::app.leads.index.title')
                </a>
                /
                @lang('admin::app.leads.disqualification.page-title')
            </div>

            <h1 class="text-xl font-bold dark:text-white">
                @lang('admin::app.leads.disqualification.page-title')
            </h1>
        </div>

        <a
            href="{{ route('admin.leads.index') }}"
            class="secondary-button"
        >
            @lang('admin::app.leads.disqualification.back-to-leads')
        </a>
    </div>

    <div class="mt-4 flex flex-col gap-6">
        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-lg font-semibold dark:text-white">
                        @lang('admin::app.leads.disqualification.do-not-call')
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.leads.disqualification.dnc-info')
                    </p>
                </div>

                <span class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                    {{ $doNotCallLeads->total() }}
                </span>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($doNotCallLeads as $lead)
                    @include('admin::leads.partials.disqualified-card', [
                        'lead' => $lead,
                        'showReassign' => false,
                        'users' => $users,
                    ])
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.leads.disqualification.no-dnc')
                    </div>
                @endforelse
            </div>

            @if ($doNotCallLeads->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                    {{ $doNotCallLeads->appends(request()->except('dnc_page'))->links() }}
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-lg font-semibold dark:text-white">
                        @lang('admin::app.leads.disqualification.incorrect-info')
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.leads.disqualification.incorrect-info-info')
                    </p>
                </div>

                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                    {{ $incorrectInfoLeads->total() }}
                </span>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($incorrectInfoLeads as $lead)
                    @include('admin::leads.partials.disqualified-card', [
                        'lead' => $lead,
                        'showReassign' => true,
                        'users' => $users,
                        'reassignRoute' => route('admin.leads.incorrect_info.reassign', $lead->id),
                    ])
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.leads.disqualification.no-incorrect-info')
                    </div>
                @endforelse
            </div>

            @if ($incorrectInfoLeads->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                    {{ $incorrectInfoLeads->appends(request()->except('incorrect_page'))->links() }}
                </div>
            @endif
        </section>

        <section class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                <div>
                    <h2 class="text-lg font-semibold dark:text-white">
                        @lang('admin::app.leads.disqualification.ended')
                    </h2>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.leads.disqualification.ended-info')
                    </p>
                </div>

                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    {{ $endedLeads->total() }}
                </span>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                @forelse ($endedLeads as $lead)
                    @include('admin::leads.partials.disqualified-card', [
                        'lead' => $lead,
                        'showReassign' => true,
                        'users' => $users,
                        'reassignRoute' => route('admin.leads.ended.reassign', $lead->id),
                    ])
                @empty
                    <div class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        @lang('admin::app.leads.disqualification.no-ended')
                    </div>
                @endforelse
            </div>

            @if ($endedLeads->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                    {{ $endedLeads->appends(request()->except('ended_page'))->links() }}
                </div>
            @endif
        </section>
    </div>
</x-admin::layouts>
