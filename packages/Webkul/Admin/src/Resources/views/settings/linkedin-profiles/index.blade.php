<x-admin::layouts>
    <x-slot:title>
        LinkedIn Profiles
    </x-slot>

    <v-linkedin-profile-settings
        :assignable-users='@json($assignableUsers)'
        ref="profileSettings"
    >
        <x-admin::shimmer.datagrid />
    </v-linkedin-profile-settings>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-linkedin-profile-settings-template">
            <div class="flex flex-col gap-4">
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                    <div class="flex flex-col gap-2">
                        <x-admin::breadcrumbs name="settings.linkedin_profiles" />
                        <div class="text-xl font-bold dark:text-white">LinkedIn Profiles</div>
                    </div>

                    <button type="button" class="primary-button" @click="openCreate">Create Profile</button>
                </div>

                <x-admin::datagrid :src="route('admin.settings.linkedin_profiles.index')" ref="datagrid">
                    <template #body="{ isLoading, available, applied, performAction }">
                        <template v-if="isLoading">
                            <x-admin::shimmer.datagrid.table.body />
                        </template>

                        <template v-else-if="available.records.length">
                            <div
                                v-for="record in available.records"
                                :key="record[available.meta.primary_column]"
                                class="row grid items-center gap-2.5 border-b px-4 py-4 text-gray-600 dark:border-gray-800 dark:text-gray-300"
                                :style="gridRowStyle(available)"
                            >
                                <template v-for="column in available.columns">
                                    <p v-if="column.visibility" class="min-w-0 break-words" v-html="record[column.index] || '--'"></p>
                                </template>

                                <p class="flex justify-end gap-1">
                                    <span
                                        v-for="action in record.actions"
                                        :key="action.index"
                                        :class="action.icon"
                                        class="cursor-pointer rounded-md p-1.5 text-2xl hover:bg-gray-200 dark:hover:bg-gray-800"
                                        @click="action.index === 'edit' ? openEdit(action) : performAction(action)"
                                    ></span>
                                </p>
                            </div>
                        </template>

                        <template v-else>
                            <div class="px-4 py-8 text-center text-gray-500">No LinkedIn profiles found.</div>
                        </template>
                    </template>
                </x-admin::datagrid>

                <x-admin::modal ref="profileModal">
                    <x-slot:header>
                        <p class="text-lg font-bold dark:text-white" v-text="modalTitle"></p>
                    </x-slot>

                    <x-slot:content>
                        <form class="grid gap-4" @submit.prevent="saveProfile">
                            <div class="grid gap-1">
                                <label class="text-sm font-medium">Profile Name *</label>
                                <input v-model="form.name" type="text" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" required />
                            </div>

                            <div class="grid gap-1">
                                <label class="text-sm font-medium">Profile URL *</label>
                                <input v-model="form.profile_url" type="text" class="w-full rounded-md border px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900" required />
                            </div>

                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" v-model="form.is_active" />
                                Active
                            </label>

                            <div class="grid gap-2">
                                <label class="text-sm font-medium">Assigned Users</label>
                                <div class="max-h-48 overflow-auto rounded-md border p-3 dark:border-gray-800">
                                    <label
                                        v-for="user in assignableUsers"
                                        :key="user.id"
                                        class="mb-2 flex items-center gap-2 text-sm"
                                    >
                                        <input type="checkbox" :value="user.id" v-model="form.user_ids" />
                                        <span v-text="user.name + ' (' + user.role_name + ')'"></span>
                                    </label>
                                </div>
                            </div>

                            <p v-if="errorMessage" class="text-xs text-red-600" v-text="errorMessage"></p>
                        </form>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex justify-end gap-3">
                            <button type="button" class="secondary-button" @click="$refs.profileModal.close()">Cancel</button>
                            <button type="button" class="primary-button" @click="saveProfile" :disabled="isSaving">Save</button>
                        </div>
                    </x-slot>
                </x-admin::modal>
            </div>
        </script>

        <script type="module">
            app.component('v-linkedin-profile-settings', {
                template: '#v-linkedin-profile-settings-template',

                props: {
                    assignableUsers: {
                        type: Array,
                        default: () => [],
                    },
                },

                data() {
                    return {
                        modalTitle: 'Create LinkedIn Profile',
                        editingId: null,
                        isSaving: false,
                        errorMessage: '',
                        form: this.emptyForm(),
                    };
                },

                methods: {
                    emptyForm() {
                        return {
                            name: '',
                            profile_url: '',
                            is_active: true,
                            user_ids: [],
                        };
                    },

                    gridRowStyle(available) {
                        const count = available.columns.filter(column => column.visibility).length + 1;

                        return {
                            gridTemplateColumns: `repeat(${count}, minmax(0, 1fr))`,
                        };
                    },

                    openCreate() {
                        this.editingId = null;
                        this.modalTitle = 'Create LinkedIn Profile';
                        this.form = this.emptyForm();
                        this.errorMessage = '';
                        this.$refs.profileModal.open();
                    },

                    async openEdit(action) {
                        this.editingId = action.url.split('/').pop();
                        this.modalTitle = 'Edit LinkedIn Profile';
                        this.errorMessage = '';

                        const response = await this.$axios.get(action.url);
                        const data = response.data.data;

                        this.form = {
                            name: data.name,
                            profile_url: data.profile_url,
                            is_active: !! data.is_active,
                            user_ids: (data.user_ids || []).map(id => Number(id)),
                        };

                        this.$refs.profileModal.open();
                    },

                    async saveProfile() {
                        this.isSaving = true;
                        this.errorMessage = '';

                        const url = this.editingId
                            ? "{{ url('admin/settings/linkedin-profiles/edit') }}/" + this.editingId
                            : "{{ route('admin.settings.linkedin_profiles.store') }}";

                        const method = this.editingId ? 'put' : 'post';

                        try {
                            await this.$axios[method](url, this.form);
                            this.$refs.profileModal.close();
                            this.$refs.datagrid.get();
                            this.$emitter.emit('add-flash', { type: 'success', message: 'LinkedIn profile saved successfully.' });
                        } catch (error) {
                            this.errorMessage = error.response?.data?.message
                                || Object.values(error.response?.data?.errors || {}).flat().join(' ')
                                || 'Unable to save LinkedIn profile.';
                        } finally {
                            this.isSaving = false;
                        }
                    },
                },

                mounted() {
                    this.$emitter.on('linkedin-profile-edit', this.openEdit);
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
