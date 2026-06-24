@props([
    'sources' => collect(),
    'selectedIds' => [],
    'name' => 'source_ids',
])

<div class="grid gap-2 sm:grid-cols-2">
    @forelse ($sources as $source)
        <label class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
            <input
                type="checkbox"
                name="{{ $name }}[]"
                value="{{ $source->id }}"
                @checked(in_array($source->id, $selectedIds))
                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
            />

            <span class="dark:text-white">{{ $source->name }}</span>
        </label>
    @empty
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @lang('admin::app.settings.sources.index.no-sources')
        </p>
    @endforelse
</div>
