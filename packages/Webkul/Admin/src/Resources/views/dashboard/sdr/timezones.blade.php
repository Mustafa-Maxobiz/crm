<x-admin::layouts>
    <x-slot:title>
        USA State Times
    </x-slot>

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                USA State Times
            </p>
        </div>

        <a
            href="{{ route('admin.dashboard.sdr') }}"
            class="secondary-button"
        >
            Back to Dashboard
        </a>
    </div>

    @include('admin::dashboard.sdr.us-state-times', [
        'stateTimezones' => $stateTimezones,
        'isPreview'      => false,
    ])
</x-admin::layouts>
