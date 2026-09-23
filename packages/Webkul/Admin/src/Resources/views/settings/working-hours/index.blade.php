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

    <div class="box-shadow rounded bg-white dark:bg-gray-900">
        <div class="grid grid-cols-[minmax(220px,1.4fr)_minmax(160px,1fr)_160px_160px_120px] gap-4 border-b border-gray-200 px-4 py-3 text-xs font-semibold uppercase text-gray-600 dark:border-gray-800 dark:text-gray-300 max-lg:hidden">
            <span>@lang('admin::app.settings.working-hours.index.user-name')</span>
            <span>@lang('admin::app.settings.working-hours.index.role')</span>
            <span>@lang('admin::app.settings.working-hours.index.start-time')</span>
            <span>@lang('admin::app.settings.working-hours.index.end-time')</span>
            <span>@lang('admin::app.settings.working-hours.index.action')</span>
        </div>

        @forelse ($users as $user)
            @php
                $workingHour = $workingHours->get($user->id);
                $hasOldInput = (int) old('user_id') === (int) $user->id;
                $startTime = $hasOldInput ? old('start_time') : ($workingHour?->start_time ? substr($workingHour->start_time, 0, 5) : '');
                $endTime = $hasOldInput ? old('end_time') : ($workingHour?->end_time ? substr($workingHour->end_time, 0, 5) : '');
            @endphp

            <form
                method="POST"
                action="{{ route('admin.settings.working_hours.store') }}"
                class="grid grid-cols-[minmax(220px,1.4fr)_minmax(160px,1fr)_160px_160px_120px] items-start gap-4 border-b border-gray-200 px-4 py-4 last:border-b-0 dark:border-gray-800 max-lg:grid-cols-1"
            >
                @csrf

                <input type="hidden" name="user_id" value="{{ $user->id }}">

                <div>
                    <div class="font-medium text-gray-800 dark:text-white">
                        {{ $user->name }}
                    </div>

                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $user->email }}
                    </div>
                </div>

                <div class="text-sm text-gray-700 dark:text-gray-300">
                    <span class="hidden text-xs font-semibold uppercase text-gray-500 max-lg:block">@lang('admin::app.settings.working-hours.index.role')</span>
                    {{ $user->role_name ?: '—' }}
                </div>

                <div>
                    <label class="hidden text-xs font-semibold uppercase text-gray-500 max-lg:block">
                        @lang('admin::app.settings.working-hours.index.start-time')
                    </label>

                    <input
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
                </div>

                <div>
                    <label class="hidden text-xs font-semibold uppercase text-gray-500 max-lg:block">
                        @lang('admin::app.settings.working-hours.index.end-time')
                    </label>

                    <input
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
                </div>

                <div>
                    <button type="submit" class="primary-button">
                        @lang('admin::app.settings.working-hours.index.save')
                    </button>

                    @if ($hasOldInput)
                        @error('user_id')
                            <p class="mt-1 text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    @endif
                </div>
            </form>
        @empty
            <div class="px-4 py-8 text-center text-sm text-gray-600 dark:text-gray-300">
                @lang('admin::app.settings.working-hours.index.no-users')
            </div>
        @endforelse
    </div>
</x-admin::layouts>
