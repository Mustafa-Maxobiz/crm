{!! view_render_event('admin.leads.view.followup.before', ['lead' => $lead]) !!}

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <div class="flex items-center gap-2">
                    <h4>Follow-up Tracking</h4>
                    @if ($lead->followup_count > 0)
                        <span class="rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                            {{ $lead->followup_count }} {{ $lead->followup_count === 1 ? 'attempt' : 'attempts' }}
                        </span>
                    @endif
                    @if ($lead->isFollowupDue())
                        <span class="rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800 dark:bg-red-900 dark:text-red-200">
                            Overdue
                        </span>
                    @elseif ($lead->isFollowupToday())
                        <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                            Due Today
                        </span>
                    @endif
                </div>
            </div>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            <div class="flex flex-col gap-4">
                <!-- Follow-up Stats -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Attempts</p>
                        <p class="text-2xl font-bold dark:text-white">{{ $lead->followup_count }}</p>
                    </div>
                    
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Next Follow-up</p>
                        <p class="text-lg font-semibold dark:text-white">
                            @if ($lead->next_followup_date)
                                {{ \Carbon\Carbon::parse($lead->next_followup_date)->format('M d, Y') }}
                            @else
                                <span class="text-gray-400">Not set</span>
                            @endif
                        </p>
                    </div>
                    
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <p class="text-sm text-gray-600 dark:text-gray-400">Last Follow-up</p>
                        <p class="text-lg font-semibold dark:text-white">
                            @if ($lead->last_followup_date)
                                {{ \Carbon\Carbon::parse($lead->last_followup_date)->diffForHumans() }}
                            @else
                                <span class="text-gray-400">Never</span>
                            @endif
                        </p>
                    </div>
                </div>

                <!-- Follow-up Notes -->
                @if ($lead->followup_notes)
                    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-800">
                        <p class="mb-2 text-sm font-semibold text-gray-600 dark:text-gray-400">Follow-up Notes</p>
                        <p class="text-gray-800 dark:text-gray-200">{{ $lead->followup_notes }}</p>
                    </div>
                @endif

                <!-- Mark Follow-up Complete Button -->
                @if ($lead->next_followup_date && bouncer()->hasPermission('leads.edit'))
                    <form method="POST" action="{{ route('admin.leads.followup.complete', $lead->id) }}">
                        @csrf
                        <button
                            type="submit"
                            class="secondary-button"
                        >
                            <span class="icon-tick text-lg"></span>
                            Mark Follow-up Complete
                        </button>
                    </form>
                @endif
            </div>
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.leads.view.followup.after', ['lead' => $lead]) !!}
