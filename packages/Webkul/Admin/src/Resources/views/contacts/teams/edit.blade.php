<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.contacts.teams.edit.title')
    </x-slot>

    {!! view_render_event('admin.teams.edit.form.before', ['team' => $team]) !!}

    <x-admin::form
        :action="route('admin.contacts.teams.update', $team->id)"
        method="PUT"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.teams.edit.breadcrumbs.before', ['team' => $team]) !!}

                    <x-admin::breadcrumbs
                        name="contacts.teams.edit"
                        :entity="$team"
                    />

                    {!! view_render_event('admin.teams.edit.breadcrumbs.after', ['team' => $team]) !!}

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.contacts.teams.edit.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button
                        type="submit"
                        class="primary-button"
                    >
                        @lang('admin::app.contacts.teams.edit.save-btn')
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.contacts.teams.edit.form_controls.before', ['team' => $team]) !!}

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.contacts.teams.edit.name')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="text"
                        name="name"
                        id="name"
                        rules="required"
                        :value="old('name', $team->name)"
                        :label="trans('admin::app.contacts.teams.edit.name')"
                        :placeholder="trans('admin::app.contacts.teams.edit.name')"
                    />

                    <x-admin::form.control-group.error control-name="name" />
                </x-admin::form.control-group>

                @php
                    $selectedOrganizationIds = collect(old('organization_ids', $organization_ids))
                        ->map(fn ($id) => (string) $id)
                        ->all();
                @endphp

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label class="required">
                        @lang('admin::app.contacts.teams.edit.company')
                    </x-admin::form.control-group.label>

                    <div class="grid max-h-48 gap-2 overflow-y-auto sm:grid-cols-2">
                        @foreach ($organizations as $organization)
                            <label
                                class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700"
                            >
                                <input
                                    type="checkbox"
                                    name="organization_ids[]"
                                    value="{{ $organization->id }}"
                                    {{ in_array((string) $organization->id, $selectedOrganizationIds, true) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                />

                                <span class="dark:text-white">{{ $organization->name }}</span>
                            </label>
                        @endforeach
                    </div>

                    <x-admin::form.control-group.error control-name="organization_ids" />
                </x-admin::form.control-group>

                <x-admin::form.control-group>
                    <x-admin::form.control-group.label>
                        @lang('admin::app.contacts.teams.edit.owner')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="select"
                        name="user_id"
                        id="user_id"
                        :value="old('user_id', $team->user_id)"
                        :label="trans('admin::app.contacts.teams.edit.owner')"
                    >
                        <option value="">
                            @lang('admin::app.contacts.teams.edit.select-owner')
                        </option>

                        @foreach ($users as $user)
                            <option
                                value="{{ $user->id }}"
                                {{ (string) old('user_id', $team->user_id) === (string) $user->id ? 'selected' : '' }}
                            >
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </x-admin::form.control-group.control>

                    <x-admin::form.control-group.error control-name="user_id" />
                </x-admin::form.control-group>

                <x-admin::form.control-group class="!mb-0">
                    <x-admin::form.control-group.label>
                        @lang('admin::app.contacts.teams.edit.description')
                    </x-admin::form.control-group.label>

                    <x-admin::form.control-group.control
                        type="textarea"
                        name="description"
                        id="description"
                        :value="old('description', $team->description)"
                        :label="trans('admin::app.contacts.teams.edit.description')"
                        :placeholder="trans('admin::app.contacts.teams.edit.description')"
                    />

                    <x-admin::form.control-group.error control-name="description" />
                </x-admin::form.control-group>

                {!! view_render_event('admin.contacts.teams.edit.form_controls.after', ['team' => $team]) !!}
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.teams.edit.form.after', ['team' => $team]) !!}
</x-admin::layouts>
