<x-admin::layouts>
    <x-slot:title>
        @if (($leadVariant ?? 'main') === 'sdr')
            @lang('admin::app.layouts.leads-sdr')
        @elseif (($leadVariant ?? 'main') === 'lge')
            @lang('admin::app.layouts.leads-lge')
        @elseif (($leadVariant ?? 'main') === 'lead_clouser')
            @lang('admin::app.layouts.leads-lead-clouser')
        @else
            @lang('admin::app.leads.index.title')
        @endif
    </x-slot>

    @include('admin::leads.index.partials.header')

    {!! view_render_event('admin.leads.index.content.before') !!}

    <!-- Content -->
    <div class="[&>*>*>*.toolbarRight]:max-lg:w-full [&>*>*>*.toolbarRight]:max-lg:justify-between [&>*>*>*.toolbarRight]:max-md:gap-y-2 [&>*>*>*.toolbarRight]:max-md:flex-wrap mt-3.5 [&>*>*:nth-child(1)]:max-lg:!flex-wrap">
        @if ((request()->view_type ?? "table") == "table")
            @include('admin::leads.index.table')
        @else
            @include('admin::leads.index.kanban')
        @endif
    </div>

    {!! view_render_event('admin.leads.index.content.after') !!}

    @pushOnce('scripts')
        @include('admin::leads.index.partials.scripts.copy-phone')
        @include('admin::leads.index.partials.scripts.import')
    @endPushOnce
</x-admin::layouts>
