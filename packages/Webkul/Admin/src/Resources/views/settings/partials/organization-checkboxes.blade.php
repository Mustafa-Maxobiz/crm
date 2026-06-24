@props([
    'organizations' => collect(),
    'selectedIds' => [],
    'name' => 'organization_ids',
])

<div class="grid gap-2 sm:grid-cols-2">
    @forelse ($organizations as $organization)
        <label class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
            <input
                type="checkbox"
                name="{{ $name }}[]"
                value="{{ $organization->id }}"
                @checked(in_array($organization->id, $selectedIds))
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
            />

            <span class="dark:text-white">{{ $organization->name }}</span>
        </label>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @lang('admin::app.settings.access-scope.no-companies')
        </p>
    @endforelse
</div>
