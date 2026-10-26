<x-admin::layouts>
    <x-slot:title>Follow-up Automation</x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
            <div>
                <x-admin::breadcrumbs name="settings.followup_schedule" />

                <div class="mt-1 text-xl font-bold dark:text-white">
                    Follow-up Automation
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Create follow-up groups by role, user, source, and tag. Enable only the filters you want to use.
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('admin.settings.followup_schedule.update') }}"
                class="flex items-center gap-3"
            >
                @csrf
                @method('PUT')

                <input type="hidden" name="enabled" value="0">

                <label class="flex items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                    <input
                        type="checkbox"
                        name="enabled"
                        value="1"
                        @checked($enabled)
                        class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                    >

                    Automatic Follow-up Enabled
                </label>

                <button type="submit" class="secondary-button">
                    Save
                </button>
            </form>
        </div>

        <v-followup-rule-manager
            :initial-rules='@json($rules)'
            :roles='@json($roles)'
            :users='@json($users)'
            :sources='@json($sources)'
            :tags='@json($tags)'
            :units='@json($units)'
            :behaviors='@json($behaviors)'
            store-url="{{ route('admin.settings.followup_schedule.rules.store') }}"
            update-url-template="{{ route('admin.settings.followup_schedule.rules.update', ['id' => '__ID__']) }}"
            delete-url-template="{{ route('admin.settings.followup_schedule.rules.delete', ['id' => '__ID__']) }}"
        ></v-followup-rule-manager>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-followup-rule-manager-template">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(420px,0.8fr)]">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div>
                            <h2 class="text-base font-semibold text-gray-800 dark:text-white">
                                Follow-up Rules
                            </h2>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                The most specific matching rule wins when automatic matching is used.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="primary-button"
                            @click="startCreate"
                        >
                            + Create Rule
                        </button>
                    </div>

                    <div
                        v-if="! rules.length"
                        class="rounded-lg border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400"
                    >
                        No follow-up rules found.
                    </div>

                    <div
                        v-for="rule in rules"
                        :key="rule.id"
                        class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-semibold text-gray-800 dark:text-white">
                                        @{{ rule.name }}
                                    </h3>

                                    <span
                                        v-if="rule.is_default"
                                        class="rounded bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-300"
                                    >
                                        Default
                                    </span>

                                    <span
                                        class="rounded px-2 py-0.5 text-xs font-medium"
                                        :class="rule.is_active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'"
                                    >
                                        @{{ rule.is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span>Schedule duration: <strong>@{{ rule.max_days }} day@{{ Number(rule.max_days) === 1 ? '' : 's' }}</strong></span>
                                    <span>After final: <strong>@{{ behaviorLabel(rule.exhaustion_behavior) }}</strong></span>
                                </div>
                            </div>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="secondary-button"
                                    @click="editRule(rule)"
                                >
                                    Edit
                                </button>

                                <button
                                    v-if="! rule.is_default"
                                    type="button"
                                    class="transparent-button text-red-600"
                                    @click="deleteRule(rule)"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3 text-xs md:grid-cols-2">
                            <div class="rounded border border-gray-100 p-3 dark:border-gray-800">
                                <div class="mb-1 font-semibold text-gray-700 dark:text-gray-300">Roles</div>
                                <div class="text-gray-500 dark:text-gray-400">@{{ selectedNames(rule.role_ids, roles, 'Any role') }}</div>
                            </div>

                            <div class="rounded border border-gray-100 p-3 dark:border-gray-800">
                                <div class="mb-1 font-semibold text-gray-700 dark:text-gray-300">Users</div>
                                <div class="text-gray-500 dark:text-gray-400">@{{ selectedNames(rule.user_ids, users, 'Any user') }}</div>
                            </div>

                            <div class="rounded border border-gray-100 p-3 dark:border-gray-800">
                                <div class="mb-1 font-semibold text-gray-700 dark:text-gray-300">Sources</div>
                                <div class="text-gray-500 dark:text-gray-400">@{{ selectedNames(rule.source_ids, sources, 'Any source') }}</div>
                            </div>

                            <div class="rounded border border-gray-100 p-3 dark:border-gray-800">
                                <div class="mb-1 font-semibold text-gray-700 dark:text-gray-300">Tags</div>
                                <div class="text-gray-500 dark:text-gray-400">@{{ selectedNames(rule.tag_ids, tags, 'Any tag') }}</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Timing
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="(step, index) in rule.steps"
                                    :key="rule.id + '-step-' + index"
                                    class="rounded border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    #@{{ index + 1 }} every @{{ step.value }} @{{ step.unit }} × @{{ step.frequency }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:sticky xl:top-4 xl:self-start">
                    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h2 class="text-base font-semibold text-gray-800 dark:text-white">
                                    @{{ form.id ? 'Edit Rule' : 'Create Rule' }}
                                </h2>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Within each category selections are OR. Categories are combined with AND.
                                </p>
                            </div>

                            <button
                                v-if="form.id"
                                type="button"
                                class="transparent-button"
                                @click="startCreate"
                            >
                                New
                            </button>
                        </div>

                        <form class="space-y-4" @submit.prevent="saveRule">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Rule Name</label>
                                <input
                                    v-model.trim="form.name"
                                    type="text"
                                    required
                                    class="w-full rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                            </div>

                            <div class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800/60">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Calculated Schedule Duration
                                </div>

                                <div class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    @{{ calculatedMaxDays }} day@{{ calculatedMaxDays === 1 ? '' : 's' }}
                                </div>

                                <div class="mt-1 text-xs text-gray-400">
                                    Calculated automatically from the timing rows below.
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">After Final Timing</label>
                                <select
                                    v-model="form.exhaustion_behavior"
                                    class="w-full rounded border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option v-for="behavior in behaviors" :key="behavior" :value="behavior">
                                        @{{ behaviorLabel(behavior) }}
                                    </option>
                                </select>
                            </div>

                            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                                >
                                Active
                            </label>

                            <template v-if="! form.is_default">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <input
                                                    v-model="form.use_roles"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                                                >
                                                <span>Filter by Roles</span>
                                            </label>

                                            <button
                                                v-if="form.use_roles"
                                                type="button"
                                                class="text-xs font-medium text-brandColor hover:underline"
                                                @click="toggleAllRoles"
                                            >
                                                @{{ allRolesSelected ? 'Clear All' : 'Select All' }}
                                            </button>
                                        </div>

                                        <div
                                            v-if="form.use_roles"
                                            class="flex max-h-40 flex-col gap-2 overflow-y-auto rounded-md border p-3"
                                            :class="filterErrorClass('role_ids')"
                                        >
                                            <label
                                                v-for="item in roles"
                                                :key="'role-' + item.id"
                                                class="flex cursor-pointer items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="Number(item.id)"
                                                    v-model="form.role_ids"
                                                    class="rounded border-gray-300 text-brandColor"
                                                >
                                                <span>@{{ item.name }}</span>
                                            </label>
                                        </div>

                                        <p v-if="fieldErrors.role_ids" class="mt-1 text-xs text-red-600">
                                            @{{ fieldErrors.role_ids }}
                                        </p>

                                        <p v-else-if="! form.use_roles" class="text-xs text-gray-400">
                                            Disabled — any role can match.
                                        </p>
                                    </div>

                                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <input
                                                    v-model="form.use_users"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                                                >
                                                <span>Filter by Users</span>
                                            </label>

                                            <button
                                                v-if="form.use_users && availableRuleUsers.length"
                                                type="button"
                                                class="text-xs font-medium text-brandColor hover:underline"
                                                @click="toggleAllUsers"
                                            >
                                                @{{ allUsersSelected ? 'Clear All' : 'Select All' }}
                                            </button>
                                        </div>

                                        <div
                                            v-if="form.use_users"
                                            class="flex max-h-40 min-h-24 flex-col gap-2 overflow-y-auto rounded-md border p-3"
                                            :class="filterErrorClass('user_ids')"
                                        >
                                            <div v-if="! availableRuleUsers.length" class="text-sm text-gray-400">
                                                No active users are available for the current role filter.
                                            </div>

                                            <label
                                                v-for="item in availableRuleUsers"
                                                :key="'user-' + item.id"
                                                class="flex cursor-pointer items-start gap-2 text-sm text-gray-800 dark:text-gray-200"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="Number(item.id)"
                                                    v-model="form.user_ids"
                                                    class="mt-0.5 rounded border-gray-300 text-brandColor"
                                                >
                                                <span>
                                                    @{{ item.name }}
                                                    <span class="text-xs text-gray-400">(@{{ item.email }})</span>
                                                </span>
                                            </label>
                                        </div>

                                        <p v-if="fieldErrors.user_ids" class="mt-1 text-xs text-red-600">
                                            @{{ fieldErrors.user_ids }}
                                        </p>

                                        <p v-else-if="! form.use_users" class="text-xs text-gray-400">
                                            Disabled — any user can match.
                                        </p>
                                    </div>

                                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <input
                                                    v-model="form.use_sources"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                                                >
                                                <span>Filter by Sources</span>
                                            </label>

                                            <button
                                                v-if="form.use_sources && availableRuleSources.length"
                                                type="button"
                                                class="text-xs font-medium text-brandColor hover:underline"
                                                @click="toggleAllSources"
                                            >
                                                @{{ allSourcesSelected ? 'Clear All' : 'Select All' }}
                                            </button>
                                        </div>

                                        <div
                                            v-if="form.use_sources"
                                            class="flex max-h-40 min-h-24 flex-col gap-2 overflow-y-auto rounded-md border p-3"
                                            :class="filterErrorClass('source_ids')"
                                        >
                                            <div v-if="! availableRuleSources.length" class="text-sm text-gray-400">
                                                No sources are available for the current user filter.
                                            </div>

                                            <label
                                                v-for="item in availableRuleSources"
                                                :key="'source-' + item.id"
                                                class="flex cursor-pointer items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="Number(item.id)"
                                                    v-model="form.source_ids"
                                                    class="rounded border-gray-300 text-brandColor"
                                                >
                                                <span>@{{ item.name }}</span>
                                            </label>
                                        </div>

                                        <p v-if="fieldErrors.source_ids" class="mt-1 text-xs text-red-600">
                                            @{{ fieldErrors.source_ids }}
                                        </p>

                                        <p v-else-if="! form.use_sources" class="text-xs text-gray-400">
                                            Disabled — any source can match.
                                        </p>
                                    </div>

                                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-700">
                                        <div class="mb-2 flex items-center justify-between gap-2">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                                <input
                                                    v-model="form.use_tags"
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-brandColor focus:ring-brandColor"
                                                >
                                                <span>Filter by Tags</span>
                                            </label>

                                            <button
                                                v-if="form.use_tags"
                                                type="button"
                                                class="text-xs font-medium text-brandColor hover:underline"
                                                @click="toggleAllTags"
                                            >
                                                @{{ allTagsSelected ? 'Clear All' : 'Select All' }}
                                            </button>
                                        </div>

                                        <div
                                            v-if="form.use_tags"
                                            class="flex max-h-40 flex-col gap-2 overflow-y-auto rounded-md border p-3"
                                            :class="filterErrorClass('tag_ids')"
                                        >
                                            <label
                                                v-for="item in tags"
                                                :key="'tag-' + item.id"
                                                class="flex cursor-pointer items-center gap-2 text-sm text-gray-800 dark:text-gray-200"
                                            >
                                                <input
                                                    type="checkbox"
                                                    :value="Number(item.id)"
                                                    v-model="form.tag_ids"
                                                    class="rounded border-gray-300 text-brandColor"
                                                >
                                                <span>@{{ item.name }}</span>
                                            </label>
                                        </div>

                                        <p v-if="fieldErrors.tag_ids" class="mt-1 text-xs text-red-600">
                                            @{{ fieldErrors.tag_ids }}
                                        </p>

                                        <p v-else-if="! form.use_tags" class="text-xs text-gray-400">
                                            Disabled — any tag can match.
                                        </p>
                                    </div>
                                </div>
                            </template>

                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300">Follow-up Timing</div>
                                        <div class="text-xs text-gray-400">Each row can repeat multiple times before moving to the next row.</div>
                                    </div>

                                    <button type="button" class="secondary-button" @click="addStep">
                                        + Add Time
                                    </button>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="(step, index) in form.steps"
                                        :key="'form-step-' + index"
                                        class="grid grid-cols-[36px_1fr_1fr_1fr_auto] items-end gap-2 rounded border border-gray-200 p-2 dark:border-gray-700"
                                    >
                                        <div class="pb-2 text-center text-xs font-semibold text-gray-500">
                                            #@{{ index + 1 }}
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs text-gray-500">Value</label>
                                            <input
                                                v-model.number="step.value"
                                                type="number"
                                                min="1"
                                                required
                                                class="w-full rounded border border-gray-200 px-2 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs text-gray-500">Unit</label>
                                            <select
                                                v-model="step.unit"
                                                class="w-full rounded border border-gray-200 px-2 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                                <option v-for="unit in units" :key="unit" :value="unit">@{{ unit }}</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label class="mb-1 block text-xs text-gray-500">Frequency</label>
                                            <input
                                                v-model.number="step.frequency"
                                                type="number"
                                                min="1"
                                                required
                                                class="w-full rounded border border-gray-200 px-2 py-2 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                        </div>

                                        <button
                                            type="button"
                                            class="mb-1 text-red-600"
                                            :disabled="form.steps.length <= 1"
                                            @click="removeStep(index)"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="errorMessage"
                                class="rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-900/20 dark:text-red-300"
                            >
                                @{{ errorMessage }}
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="primary-button"
                                    :disabled="saving"
                                >
                                    @{{ saving ? 'Saving...' : (form.id ? 'Update Rule' : 'Create Rule') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-followup-rule-manager', {
                template: '#v-followup-rule-manager-template',

                props: {
                    initialRules: Array,
                    roles: Array,
                    users: Array,
                    sources: Array,
                    tags: Array,
                    units: Array,
                    behaviors: Array,
                    storeUrl: String,
                    updateUrlTemplate: String,
                    deleteUrlTemplate: String,
                },

                data() {
                    return {
                        rules: (this.initialRules || []).map(rule => this.normalizeRule(rule)),
                        form: this.blankForm(),
                        saving: false,
                        errorMessage: '',
                        fieldErrors: {},
                    };
                },

                computed: {
                    allRolesSelected() {
                        return this.allAvailableSelected(this.form.role_ids, this.roles);
                    },

                    allUsersSelected() {
                        return this.allAvailableSelected(this.form.user_ids, this.availableRuleUsers);
                    },

                    allSourcesSelected() {
                        return this.allAvailableSelected(this.form.source_ids, this.availableRuleSources);
                    },

                    allTagsSelected() {
                        return this.allAvailableSelected(this.form.tag_ids, this.tags);
                    },

                    calculatedMaxDays() {
                        const factors = {
                            minutes: 1,
                            hours: 60,
                            days: 1440,
                            weeks: 10080,
                            months: 43200,
                        };

                        const minutes = (this.form.steps || []).reduce((total, step) => {
                            const value = Math.max(1, Number(step.value || 1));
                            const frequency = Math.max(1, Number(step.frequency || 1));
                            const factor = factors[step.unit] || 60;

                            return total + (value * frequency * factor);
                        }, 0);

                        return Math.max(1, Math.ceil(minutes / 1440));
                    },

                    availableRuleUsers() {
                        if (! this.form.use_users) {
                            return [];
                        }

                        if (! this.form.use_roles) {
                            return this.users || [];
                        }

                        const selectedRoleIds = new Set((this.form.role_ids || []).map(Number));

                        if (! selectedRoleIds.size) {
                            return [];
                        }

                        return (this.users || []).filter(user =>
                            (user.role_ids || [])
                                .map(Number)
                                .some(roleId => selectedRoleIds.has(roleId))
                        );
                    },

                    availableRuleSources() {
                        if (! this.form.use_sources) {
                            return [];
                        }

                        if (! this.form.use_users) {
                            return this.sources || [];
                        }

                        const selectedUserIds = new Set((this.form.user_ids || []).map(Number));

                        if (! selectedUserIds.size) {
                            return [];
                        }

                        const selectedRoleIds = new Set((this.form.role_ids || []).map(Number));
                        const allSourceIds = (this.sources || []).map(source => Number(source.id));
                        const allowedSourceIds = new Set();

                        (this.users || [])
                            .filter(user => selectedUserIds.has(Number(user.id)))
                            .forEach(user => {
                                const userSourceIds = (user.source_ids || []).map(Number);
                                let matchingRoleIds = (user.role_ids || []).map(Number);

                                if (this.form.use_roles) {
                                    matchingRoleIds = matchingRoleIds.filter(roleId => selectedRoleIds.has(roleId));
                                }

                                if (! matchingRoleIds.length) {
                                    if (userSourceIds.length) {
                                        userSourceIds.forEach(id => allowedSourceIds.add(id));
                                    } else if (! this.form.use_roles) {
                                        allSourceIds.forEach(id => allowedSourceIds.add(id));
                                    }

                                    return;
                                }

                                matchingRoleIds.forEach(roleId => {
                                    const role = (this.roles || []).find(item => Number(item.id) === roleId);
                                    const roleSourceIds = (role?.source_ids || []).map(Number);

                                    let effectiveSourceIds;

                                    if (userSourceIds.length && roleSourceIds.length) {
                                        const roleSourceSet = new Set(roleSourceIds);
                                        effectiveSourceIds = userSourceIds.filter(id => roleSourceSet.has(id));
                                    } else if (userSourceIds.length) {
                                        effectiveSourceIds = userSourceIds;
                                    } else if (roleSourceIds.length) {
                                        effectiveSourceIds = roleSourceIds;
                                    } else {
                                        effectiveSourceIds = allSourceIds;
                                    }

                                    effectiveSourceIds.forEach(id => allowedSourceIds.add(Number(id)));
                                });
                            });

                        return (this.sources || []).filter(source =>
                            allowedSourceIds.has(Number(source.id))
                        );
                    },
                },
                watch: {
                    'form.use_roles'(enabled) {
                        if (! enabled) {
                            this.form.role_ids = [];
                            delete this.fieldErrors.role_ids;
                        }

                        this.pruneRuleUsers();
                    },

                    'form.use_users'(enabled) {
                        if (! enabled) {
                            this.form.user_ids = [];
                            delete this.fieldErrors.user_ids;
                        }

                        this.pruneRuleSources();
                    },

                    'form.use_sources'(enabled) {
                        if (! enabled) {
                            this.form.source_ids = [];
                            delete this.fieldErrors.source_ids;
                        } else {
                            this.pruneRuleSources();
                        }
                    },

                    'form.use_tags'(enabled) {
                        if (! enabled) {
                            this.form.tag_ids = [];
                            delete this.fieldErrors.tag_ids;
                        }
                    },

                    'form.role_ids': {
                        deep: true,
                        handler() {
                            delete this.fieldErrors.role_ids;
                            this.pruneRuleUsers();
                        },
                    },

                    'form.user_ids': {
                        deep: true,
                        handler() {
                            delete this.fieldErrors.user_ids;
                            this.pruneRuleSources();
                        },
                    },

                    'form.source_ids': {
                        deep: true,
                        handler() {
                            delete this.fieldErrors.source_ids;
                        },
                    },

                    'form.tag_ids': {
                        deep: true,
                        handler() {
                            delete this.fieldErrors.tag_ids;
                        },
                    },
                },
                methods: {
                    blankForm() {
                        return {
                            id: null,
                            name: '',
                            is_active: true,
                            is_default: false,
                            exhaustion_behavior: 'repeat_last',
                            use_roles: false,
                            use_users: false,
                            use_sources: false,
                            use_tags: false,
                            role_ids: [],
                            user_ids: [],
                            source_ids: [],
                            tag_ids: [],
                            steps: [{ value: 4, unit: 'hours', frequency: 1 }],
                        };
                    },

                    normalizeRule(rule) {
                        return {
                            ...rule,
                            id: Number(rule.id),
                            max_days: Number(rule.max_days || 1),
                            is_active: Boolean(rule.is_active),
                            is_default: Boolean(rule.is_default),
                            use_roles: ! rule.is_default && Boolean((rule.role_ids || []).length),
                            use_users: ! rule.is_default && Boolean((rule.user_ids || []).length),
                            use_sources: ! rule.is_default && Boolean((rule.source_ids || []).length),
                            use_tags: ! rule.is_default && Boolean((rule.tag_ids || []).length),
                            role_ids: (rule.role_ids || []).map(Number),
                            user_ids: (rule.user_ids || []).map(Number),
                            source_ids: (rule.source_ids || []).map(Number),
                            tag_ids: (rule.tag_ids || []).map(Number),
                            steps: (rule.steps || []).map(step => ({
                                value: Number(step.value || 1),
                                unit: step.unit || 'hours',
                                frequency: Number(step.frequency || 1),
                            })),
                        };
                    },

                    startCreate() {
                        this.form = this.blankForm();
                        this.errorMessage = '';
                        this.fieldErrors = {};
                    },

                    editRule(rule) {
                        this.form = JSON.parse(JSON.stringify(this.normalizeRule(rule)));
                        this.errorMessage = '';
                        this.fieldErrors = {};
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },

                    addStep() {
                        const last = this.form.steps[this.form.steps.length - 1] || { value: 4, unit: 'hours', frequency: 1 };

                        this.form.steps.push({
                            value: Number(last.value || 1),
                            unit: last.unit || 'hours',
                            frequency: Number(last.frequency || 1),
                        });
                    },

                    removeStep(index) {
                        if (this.form.steps.length > 1) {
                            this.form.steps.splice(index, 1);
                        }
                    },

                    pruneRuleUsers() {
                        if (! this.form.use_users) {
                            return;
                        }

                        const allowedUserIds = new Set(
                            this.availableRuleUsers.map(user => Number(user.id))
                        );

                        const nextUserIds = (this.form.user_ids || [])
                            .map(Number)
                            .filter(id => allowedUserIds.has(id));

                        if (! this.sameIdList(this.form.user_ids, nextUserIds)) {
                            this.form.user_ids = nextUserIds;
                        }

                        this.pruneRuleSources();
                    },

                    pruneRuleSources() {
                        if (! this.form.use_sources) {
                            return;
                        }

                        const allowedSourceIds = new Set(
                            this.availableRuleSources.map(source => Number(source.id))
                        );

                        const nextSourceIds = (this.form.source_ids || [])
                            .map(Number)
                            .filter(id => allowedSourceIds.has(id));

                        if (! this.sameIdList(this.form.source_ids, nextSourceIds)) {
                            this.form.source_ids = nextSourceIds;
                        }
                    },

                    filterErrorClass(field) {
                        return this.fieldErrors[field]
                            ? 'border-red-500 dark:border-red-500'
                            : 'border-gray-200 dark:border-gray-700';
                    },

                    validateFiltersBeforeSave() {
                        if (this.form.is_default) {
                            return true;
                        }

                        const checks = [
                            ['use_roles', 'role_ids', 'Roles'],
                            ['use_users', 'user_ids', 'Users'],
                            ['use_sources', 'source_ids', 'Sources'],
                            ['use_tags', 'tag_ids', 'Tags'],
                        ];

                        for (const [enabledField, idsField, label] of checks) {
                            if (this.form[enabledField] && ! (this.form[idsField] || []).length) {
                                this.fieldErrors[idsField] = 'Select at least one ' + label + ' option or turn off the ' + label + ' filter.';
                            }
                        }

                        if (Object.keys(this.fieldErrors).length) {
                            this.errorMessage = 'Complete the enabled filters before saving.';
                            return false;
                        }

                        return true;
                    },

                    sameIdList(current, next) {
                        const left = (current || []).map(Number);
                        const right = (next || []).map(Number);

                        return left.length === right.length
                            && left.every((id, index) => id === right[index]);
                    },

                    allAvailableSelected(selectedIds, options) {
                        if (! options?.length) {
                            return false;
                        }

                        const selected = new Set((selectedIds || []).map(Number));

                        return options.every(item => selected.has(Number(item.id)));
                    },

                    toggleAllRoles() {
                        this.form.role_ids = this.allRolesSelected
                            ? []
                            : this.roles.map(item => Number(item.id));
                    },

                    toggleAllUsers() {
                        this.form.user_ids = this.allUsersSelected
                            ? []
                            : this.availableRuleUsers.map(item => Number(item.id));
                    },

                    toggleAllSources() {
                        this.form.source_ids = this.allSourcesSelected
                            ? []
                            : this.availableRuleSources.map(item => Number(item.id));
                    },

                    toggleAllTags() {
                        this.form.tag_ids = this.allTagsSelected
                            ? []
                            : this.tags.map(item => Number(item.id));
                    },

                    selectedNames(ids, options, fallback) {
                        if (! ids || ! ids.length) {
                            return fallback;
                        }

                        const selected = new Set(ids.map(Number));

                        return options
                            .filter(item => selected.has(Number(item.id)))
                            .map(item => item.name)
                            .join(', ') || fallback;
                    },

                    behaviorLabel(value) {
                        return {
                            stop: 'Stop automatic follow-up',
                            repeat_last: 'Repeat last interval',
                            restart: 'Restart sequence',
                        }[value] || value;
                    },

                    payload() {
                        const isDefault = Boolean(this.form.is_default);

                        return {
                            name: this.form.name,
                            is_active: this.form.is_active ? 1 : 0,
                            exhaustion_behavior: this.form.exhaustion_behavior,
                            use_roles: ! isDefault && this.form.use_roles ? 1 : 0,
                            use_users: ! isDefault && this.form.use_users ? 1 : 0,
                            use_sources: ! isDefault && this.form.use_sources ? 1 : 0,
                            use_tags: ! isDefault && this.form.use_tags ? 1 : 0,
                            role_ids: ! isDefault && this.form.use_roles ? this.form.role_ids.map(Number) : [],
                            user_ids: ! isDefault && this.form.use_users ? this.form.user_ids.map(Number) : [],
                            source_ids: ! isDefault && this.form.use_sources ? this.form.source_ids.map(Number) : [],
                            tag_ids: ! isDefault && this.form.use_tags ? this.form.tag_ids.map(Number) : [],
                            steps: this.form.steps.map(step => ({
                                value: Number(step.value || 1),
                                unit: step.unit,
                                frequency: Number(step.frequency || 1),
                            })),
                        };
                    },

                    async saveRule() {
                        this.errorMessage = '';
                        this.fieldErrors = {};

                        if (! this.validateFiltersBeforeSave()) {
                            return;
                        }

                        this.saving = true;

                        try {
                            const url = this.form.id
                                ? this.updateUrlTemplate.replace('__ID__', this.form.id)
                                : this.storeUrl;

                            const response = this.form.id
                                ? await this.$axios.put(url, this.payload())
                                : await this.$axios.post(url, this.payload());

                            const saved = this.normalizeRule(response.data.rule);
                            const index = this.rules.findIndex(rule => Number(rule.id) === Number(saved.id));

                            if (index >= 0) {
                                this.rules.splice(index, 1, saved);
                            } else {
                                this.rules.push(saved);
                            }

                            this.rules.sort((a, b) => {
                                if (a.is_default !== b.is_default) {
                                    return a.is_default ? -1 : 1;
                                }

                                return String(a.name || '').localeCompare(String(b.name || ''));
                            });

                            this.form = JSON.parse(JSON.stringify(saved));

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message,
                            });
                        } catch (error) {
                            const errors = error.response?.data?.errors;
                            this.errorMessage = errors
                                ? Object.values(errors).flat().join(' ')
                                : (error.response?.data?.message || 'Unable to save follow-up rule.');
                        } finally {
                            this.saving = false;
                        }
                    },

                    async deleteRule(rule) {
                        if (! confirm(`Delete "${rule.name}"?`)) {
                            return;
                        }

                        try {
                            const response = await this.$axios.delete(
                                this.deleteUrlTemplate.replace('__ID__', rule.id)
                            );

                            this.rules = this.rules.filter(item => Number(item.id) !== Number(rule.id));

                            if (Number(this.form.id) === Number(rule.id)) {
                                this.startCreate();
                            }

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message,
                            });
                        } catch (error) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Unable to delete follow-up rule.',
                            });
                        }
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
