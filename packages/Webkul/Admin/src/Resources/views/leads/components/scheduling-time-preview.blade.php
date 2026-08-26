@php
    $value = $value ?? 'null';
    $label = $label ?? 'Selected Time';
@endphp

<div
    class="mt-3 grid gap-2 rounded-md border border-gray-200 bg-gray-50 p-3 text-xs dark:border-gray-700 dark:bg-gray-900"
    v-if="timezonePreview({{ $value }}).hasValue"
>
    <div class="font-semibold text-gray-700 dark:text-gray-200">
        {{ $label }}
    </div>

    <div class="grid gap-2 sm:grid-cols-2">
        <div>
            <div class="text-gray-500 dark:text-gray-400">
                Customer Time
            </div>

            <div class="font-semibold text-gray-800 dark:text-white" v-text="timezonePreview({{ $value }}).customer"></div>

            <div class="text-gray-500 dark:text-gray-400" v-text="timezonePreview({{ $value }}).customerMeta"></div>
        </div>

        <div>
            <div class="text-gray-500 dark:text-gray-400">
                Pakistan Time
            </div>

            <div class="font-semibold text-gray-800 dark:text-white" v-text="timezonePreview({{ $value }}).pakistan"></div>

            <div class="text-gray-500 dark:text-gray-400" v-text="timezonePreview({{ $value }}).pakistanMeta"></div>
        </div>
    </div>
</div>
