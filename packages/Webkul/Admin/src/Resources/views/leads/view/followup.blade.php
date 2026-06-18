{!! view_render_event('admin.leads.view.followup.before', ['lead' => $lead]) !!}

@php
    $currentFollowupDate = $lead->next_followup_date
        ? \Carbon\Carbon::parse($lead->next_followup_date)->format('Y-m-d H:i:s')
        : null;
@endphp

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
                    <form
                        id="followup-complete-form-{{ $lead->id }}"
                        method="POST"
                        action="{{ route('admin.leads.followup.complete', $lead->id) }}"
                        onsubmit="const submitButton = document.querySelector('[data-followup-submit=&quot;{{ $lead->id }}&quot;]'); const triggerButton = document.querySelector('[data-followup-trigger=&quot;{{ $lead->id }}&quot;]'); if (submitButton) { submitButton.disabled = true; submitButton.textContent = 'Completing...'; } if (triggerButton) { triggerButton.classList.add('hidden'); }"
                    >
                        @csrf

                        <input
                            type="hidden"
                            name="current_followup_date"
                            value="{{ $currentFollowupDate }}"
                        />

                        <input
                            type="hidden"
                            name="schedule_next_followup"
                            value="0"
                            data-followup-schedule-input
                        />

                        <button
                            type="button"
                            class="secondary-button disabled:cursor-not-allowed disabled:opacity-60"
                            data-followup-trigger="{{ $lead->id }}"
                            onclick="const modal = document.getElementById('followup-complete-modal-{{ $lead->id }}'); if (! modal.open && modal.showModal) { modal.showModal(); } else { modal.setAttribute('open', 'open'); } document.body.classList.add('overflow-hidden');"
                        >
                            <span class="icon-tick text-lg"></span>
                            <span data-followup-label>Mark Follow-up Complete</span>
                        </button>
                    </form>
                @endif
            </div>
        </x-slot>
    </x-admin::accordion>
</div>

@if ($lead->next_followup_date && bouncer()->hasPermission('leads.edit'))
    <dialog
        id="followup-complete-modal-{{ $lead->id }}"
        class="m-0 h-screen max-h-none w-screen max-w-none bg-transparent p-0 backdrop:bg-black/50"
        style="z-index: 2147483647;"
        aria-modal="true"
        role="alertdialog"
        onclose="document.body.classList.remove('overflow-hidden')"
    >
        <form
            method="dialog"
            class="absolute inset-0"
            onclick="document.getElementById('followup-complete-modal-{{ $lead->id }}').close();"
        ></form>

        <div
            class="absolute left-1/2 top-1/2 w-[92vw] max-w-[520px] -translate-x-1/2 -translate-y-1/2 rounded-lg bg-white shadow-xl dark:bg-gray-900"
            style="z-index: 1;"
        >
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    Complete Follow-up
                </p>

                <button
                    type="button"
                    class="icon-cross-large rounded-md p-1.5 text-xl hover:bg-gray-100 dark:hover:bg-gray-800"
                    onclick="document.getElementById('followup-complete-modal-{{ $lead->id }}').close();"
                ></button>
            </div>

            <div class="flex flex-col gap-4 px-5 py-4">
                <p class="text-sm font-medium text-gray-800 dark:text-white">
                    Would you like to schedule a next follow-up?
                </p>

                <div class="grid grid-cols-3 gap-3 max-sm:grid-cols-1">
                    <div class="rounded border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Total Attempts</p>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $lead->followup_count }}</p>
                    </div>

                    <div class="rounded border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Current Follow-up</p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">
                            {{ \Carbon\Carbon::parse($lead->next_followup_date)->format('M d, Y h:i A') }}
                        </p>
                    </div>

                    <div class="rounded border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs text-gray-500 dark:text-gray-400">Last Follow-up</p>
                        <p class="text-sm font-semibold text-gray-800 dark:text-white">
                            @if ($lead->last_followup_date)
                                {{ \Carbon\Carbon::parse($lead->last_followup_date)->diffForHumans() }}
                            @else
                                Never
                            @endif
                        </p>
                    </div>
                </div>

                @if ($lead->followup_notes)
                    <div class="rounded border border-gray-200 p-3 dark:border-gray-800">
                        <p class="mb-1 text-xs font-semibold text-gray-500 dark:text-gray-400">Notes</p>
                        <p class="text-sm text-gray-800 dark:text-gray-200">{{ $lead->followup_notes }}</p>
                    </div>
                @endif

                <div
                    id="next-followup-field-{{ $lead->id }}"
                    class="hidden flex-col gap-2"
                >
                    <label
                        class="text-sm font-medium text-gray-800 dark:text-white"
                        for="next-followup-date-{{ $lead->id }}"
                    >
                        Next follow-up date/time
                    </label>

                    <input
                        id="next-followup-date-{{ $lead->id }}"
                        form="followup-complete-form-{{ $lead->id }}"
                        type="datetime-local"
                        name="next_followup_date"
                        class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    />
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2 border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                <button
                    type="button"
                    class="secondary-button"
                    onclick="document.getElementById('followup-complete-modal-{{ $lead->id }}').close();"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    form="followup-complete-form-{{ $lead->id }}"
                    class="secondary-button"
                    data-followup-submit="{{ $lead->id }}"
                    onclick="const form = document.getElementById('followup-complete-form-{{ $lead->id }}'); const field = document.getElementById('next-followup-field-{{ $lead->id }}'); const input = document.getElementById('next-followup-date-{{ $lead->id }}'); form.querySelector('[data-followup-schedule-input]').value = '0'; input.required = false; input.value = ''; field.classList.add('hidden'); field.classList.remove('flex');"
                >
                    No, Close Follow-up
                </button>

                <button
                    type="button"
                    class="primary-button"
                    onclick="const form = document.getElementById('followup-complete-form-{{ $lead->id }}'); const field = document.getElementById('next-followup-field-{{ $lead->id }}'); const input = document.getElementById('next-followup-date-{{ $lead->id }}'); if (field.classList.contains('hidden')) { field.classList.remove('hidden'); field.classList.add('flex'); input.required = true; input.focus(); return; } form.querySelector('[data-followup-schedule-input]').value = '1'; form.requestSubmit();"
                >
                    Yes, Schedule Next
                </button>
            </div>
        </div>
    </dialog>
@endif

{!! view_render_event('admin.leads.view.followup.after', ['lead' => $lead]) !!}
