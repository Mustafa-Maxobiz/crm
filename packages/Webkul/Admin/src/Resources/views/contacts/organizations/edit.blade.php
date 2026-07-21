
<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.contacts.organizations.edit.title')
    </x-slot>

    {!! view_render_event('admin.organizations.edit.form.before') !!}

    <x-admin::form
        :action="route('admin.contacts.organizations.update', $organization->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.organizations.edit.breadcrumbs.before', ['organization' => $organization]) !!}

                    <x-admin::breadcrumbs 
                        name="contacts.organizations.edit" 
                        :entity="$organization"
                    />

                    {!! view_render_event('admin.organizations.edit.breadcrumbs.before', ['organization' => $organization]) !!}

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.contacts.organizations.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.organizations.edit.save_button.before', ['organization' => $organization]) !!}

                        <!-- Save button for person -->
                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.contacts.organizations.edit.save-btn')
                        </button>

                        {!! view_render_event('admin.organizations.edit.save_button.after', ['organization' => $organization]) !!}
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.contacts.organizations.edit.form_controls.before') !!}

                <x-admin::attributes
                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                        'entity_type' => 'organizations',
                    ])"
                    :custom-validations="[
                        'name' => [
                            'max:100',
                        ],
                        'address' => [
                            'max:100',
                        ],
                        'postcode' => [
                            'postcode',
                        ],
                    ]"
                    :entity="$organization"
                />
                
                {!! view_render_event('admin.contacts.organizations.edit.form_controls.after') !!}
            </div>

            @if (bouncer()->hasPermission('contacts.teams'))
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <p class="text-base font-semibold text-gray-800 dark:text-white">
                            @lang('admin::app.contacts.organizations.edit.teams')
                        </p>

                        @if (bouncer()->hasPermission('contacts.teams.create'))
                            <a
                                href="{{ route('admin.contacts.teams.create', ['organization_id' => $organization->id]) }}"
                                class="secondary-button"
                            >
                                @lang('admin::app.contacts.organizations.edit.add-team')
                            </a>
                        @endif
                    </div>

                    @php
                        $organizationTeams = $organization->teams()->with('user')->orderBy('name')->get();
                    @endphp

                    @forelse ($organizationTeams as $team)
                        <div class="flex items-center justify-between border-b border-gray-100 py-3 last:border-0 dark:border-gray-800">
                            <div>
                                <p class="font-medium text-gray-800 dark:text-gray-200">
                                    {{ $team->name }}
                                </p>

                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $team->user?->name ?: '-' }}
                                    @if ($team->description)
                                        · {{ \Illuminate\Support\Str::limit($team->description, 80) }}
                                    @endif
                                </p>
                            </div>

                            @if (bouncer()->hasPermission('contacts.teams.edit'))
                                <a
                                    href="{{ route('admin.contacts.teams.edit', $team->id) }}"
                                    class="text-sm text-brandColor hover:underline"
                                >
                                    @lang('admin::app.contacts.teams.index.datagrid.edit')
                                </a>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @lang('admin::app.contacts.organizations.edit.no-teams')
                        </p>
                    @endforelse
                </div>
            @endif
        </div>
    </x-admin::form>

    {!! view_render_event('admin.organizations.edit.form.after') !!}
</x-admin::layouts>
