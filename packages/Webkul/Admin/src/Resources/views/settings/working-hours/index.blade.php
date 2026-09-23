<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.settings.working-hours.index.title')
    </x-slot>

    <div class="mb-6 flex items-center justify-between">
        <div>
            <p class="text-xl font-bold text-gray-800 dark:text-white">
                @lang('admin::app.settings.working-hours.index.title')
            </p>

            <p class="text-sm text-gray-600 dark:text-gray-300">
                @lang('admin::app.settings.working-hours.index.description')
            </p>
        </div>
    </div>

    <div class="box-shadow overflow-x-auto rounded bg-white dark:bg-gray-900">
        <table class="w-full min-w-[900px] table-auto text-left">
            <thead>
                <tr class="border-b border-gray-200 text-xs font-semibold uppercase text-gray-600 dark:border-gray-800 dark:text-gray-300">
                    <th class="px-4 py-3">
                        @lang('admin::app.settings.working-hours.index.user-name')
                    </th>

                    <th class="px-4 py-3">
                        @lang('admin::app.settings.working-hours.index.role')
                    </th>

                    <th class="w-[190px] px-4 py-3">
                        @lang('admin::app.settings.working-hours.index.start-time')
                    </th>

                    <th class="w-[190px] px-4 py-3">
                        @lang('admin::app.settings.working-hours.index.end-time')
                    </th>

                    <th class="w-[130px] px-4 py-3">
                        @lang('admin::app.settings.working-hours.index.action')
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse ($users as $user)
                    @php
                        $workingHour = $workingHours->get($user->id);
                        $hasOldInput = (int) old('user_id') === (int) $user->id;
                        $startTime = $hasOldInput ? old('start_time') : ($workingHour?->start_time ? substr($workingHour->start_time, 0, 5) : '');
                        $endTime = $hasOldInput ? old('end_time') : ($workingHour?->end_time ? substr($workingHour->end_time, 0, 5) : '');
                        $formId = 'working-hours-form-'.$user->id;
                    @endphp

                    <tr class="border-b border-gray-200 align-top last:border-b-0 dark:border-gray-800">
                        <td class="px-4 py-4">
                            <form
                                id="{{ $formId }}"
                                method="POST"
                                action="{{ route('admin.settings.working_hours.store') }}"
                            >
                                @csrf

                                <input type="hidden" name="user_id" value="{{ $user->id }}">
                            </form>

                            <div class="font-medium text-gray-800 dark:text-white">
                                {{ $user->name }}
                            </div>

                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $user->email }}
                            </div>

                            @if ($hasOldInput)
                                @error('user_id')
                                    <p class="mt-1 text-xs italic text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </td>

                        <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                            {{ $user->role_name ?: '—' }}
                        </td>

                        <td class="px-4 py-4">
                            <input
                                form="{{ $formId }}"
                                type="time"
                                name="start_time"
                                value="{{ $startTime }}"
                                required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-800 outline-none transition focus:border-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                            >

                            @if ($hasOldInput)
                                @error('start_time')
                                    <p class="mt-1 text-xs italic text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </td>

                        <td class="px-4 py-4">
                            <input
                                form="{{ $formId }}"
                                type="time"
                                name="end_time"
                                value="{{ $endTime }}"
                                required
                                class="w-full rounded border border-gray-300 px-3 py-2 text-sm text-gray-800 outline-none transition focus:border-brandColor dark:border-gray-800 dark:bg-gray-900 dark:text-white"
                            >

                            @if ($hasOldInput)
                                @error('end_time')
                                    <p class="mt-1 text-xs italic text-red-600">{{ $message }}</p>
                                @enderror
                            @endif
                        </td>

                        <td class="px-4 py-4">
                            <button
                                form="{{ $formId }}"
                                type="submit"
                                class="primary-button"
                            >
                                @lang('admin::app.settings.working-hours.index.save')
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-600 dark:text-gray-300">
                            @lang('admin::app.settings.working-hours.index.no-users')
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="mt-4">
            {{ $users->links() }}
        </div>
    @endif
</x-admin::layouts>
