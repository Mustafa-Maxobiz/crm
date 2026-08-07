<div class="p-4">
    <div class="flex items-start justify-between gap-4 max-lg:flex-wrap">
        <div class="min-w-0 flex-1">
            <a
                href="{{ lead_route('view', $lead->id) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="break-words text-base font-semibold text-gray-900 hover:text-red-600 hover:underline dark:text-white"
            >
                {{ $lead->title }}
            </a>

            <div class="mt-2 grid gap-2 text-sm text-gray-600 dark:text-gray-300 md:grid-cols-2 xl:grid-cols-4">
                <span>
                    {{ $lead->person?->name ?: '--' }}
                    @if ($lead->person?->organization?->name)
                        - {{ $lead->person->organization->name }}
                    @endif
                </span>

                <span>
                    @lang('admin::app.leads.disqualification.owner'):
                    {{ $lead->user?->name ?: '--' }}
                </span>

                <span>
                    @lang('admin::app.leads.disqualification.source'):
                    {{ $lead->source?->name ?: '--' }}
                    @if ($lead->subSource?->name)
                        / {{ $lead->subSource->name }}
                    @endif
                </span>

                <span>
                    @lang('admin::app.leads.disqualification.marked-at'):
                    {{ $lead->lead_disqualified_at?->format('M d, Y h:i A') ?: '--' }}
                </span>
            </div>
        </div>

        <a
            href="{{ lead_route('view', $lead->id) }}"
            target="_blank"
            rel="noopener noreferrer"
            class="secondary-button !min-h-[34px] shrink-0 !px-3 text-xs"
        >
            @lang('admin::app.leads.disqualification.view')
        </a>
    </div>

    @if ($lead->lead_disqualification_comment)
        <div class="mt-3 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
            <span class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                @lang('admin::app.leads.disqualification.comment')
            </span>

            <p class="mt-1 whitespace-pre-wrap break-words">
                {{ $lead->lead_disqualification_comment }}
            </p>
        </div>
    @endif

    @if ($showReassign)
        <form
            method="POST"
            action="{{ $reassignRoute ?? lead_route('incorrect_info.reassign', $lead->id) }}"
            class="mt-3 flex flex-wrap items-end gap-3"
        >
            @csrf

            <div class="min-w-[260px] flex-1">
                <label class="mb-1 block text-xs font-semibold text-gray-600 dark:text-gray-300">
                    @lang('admin::app.leads.disqualification.reassign-to')
                </label>

                <select
                    name="user_id"
                    class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                    required
                >
                    <option value="">
                        @lang('admin::app.leads.disqualification.select-user')
                    </option>

                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected($lead->user_id == $user->id)>
                            {{ $user->name }} @if ($user->email) ({{ $user->email }}) @endif
                        </option>
                    @endforeach
                </select>
            </div>

            <button
                type="submit"
                class="primary-button"
            >
                @lang('admin::app.leads.disqualification.correct-reassign')
            </button>
        </form>
    @endif
</div>
