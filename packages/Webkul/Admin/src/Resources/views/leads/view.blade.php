<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.leads.view.title', ['title' => $lead->title])
    </x-slot>

    <!-- Content -->
    <div class="relative flex gap-4 max-lg:flex-wrap">
        <!-- Left Panel -->
        {!! view_render_event('admin.leads.view.left.before', ['lead' => $lead]) !!}

        <div class="max-lg:min-w-full max-lg:max-w-full [&>div:last-child]:border-b-0 lg:sticky lg:top-[73px] flex min-w-[394px] max-w-[394px] flex-col self-start rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <!-- Lead Information -->
            <div class="flex w-full flex-col gap-2 border-b border-gray-200 p-4 dark:border-gray-800">
                <!-- Breadcrumb's -->
                <div class="flex items-center justify-between">
                    <x-admin::breadcrumbs
                        name="leads.view"
                        :entity="$lead"
                    />
                </div>

                <div class="mb-2">
                    @if (($days = $lead->rotten_days) > 0)
                        @php
                            $lead->tags->prepend([
                                'name'  => '<span class="icon-rotten text-base"></span>' . trans('admin::app.leads.view.rotten-days', ['days' => $days]),
                                'color' => '#FEE2E2'
                            ]);
                        @endphp
                    @endif

                    {!! view_render_event('admin.leads.view.tags.before', ['lead' => $lead]) !!}

                    <!-- Tags -->
                    <x-admin::tags
                        :attach-endpoint="lead_route('tags.attach', $lead->id)"
                        :detach-endpoint="lead_route('tags.detach', $lead->id)"
                        :added-tags="$lead->tags"
                        :lead-context="[
                            'id' => $lead->id,
                            'title' => $lead->title,
                            'participants' => [
                                'users' => auth()->guard('user')->user()
                                    ? [[
                                        'id' => auth()->guard('user')->id(),
                                        'name' => auth()->guard('user')->user()->name,
                                    ]]
                                    : [],
                                'persons' => [],
                            ],
                        ]"
                    />

                    {!! view_render_event('admin.leads.view.tags.after', ['lead' => $lead]) !!}
                </div>


                {!! view_render_event('admin.leads.view.title.before', ['lead' => $lead]) !!}

                <!-- Title -->
                <h1 class="text-lg font-bold dark:text-white">
                    {{ $lead->title }}
                </h1>

                {!! view_render_event('admin.leads.view.title.after', ['lead' => $lead]) !!}

                <!-- Activity Actions -->
                <div class="flex flex-wrap gap-2">
                    {!! view_render_event('admin.leads.view.actions.before', ['lead' => $lead]) !!}

                    @if (bouncer()->hasPermission('mail.compose'))
                        <!-- Mail Activity Action -->
                        <x-admin::activities.actions.mail
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    @if (bouncer()->hasPermission('activities.create'))
                        <!-- File Activity Action -->
                        <x-admin::activities.actions.file
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Note Activity Action -->
                        <x-admin::activities.actions.note
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />

                        <!-- Activity Action -->
                        <x-admin::activities.actions.activity
                            :entity="$lead"
                            entity-control-name="lead_id"
                        />
                    @endif

                    {!! view_render_event('admin.leads.view.actions.after', ['lead' => $lead]) !!}
                </div>

                <div class="mt-2 flex flex-wrap items-center gap-2">
                    @if ($lead->lead_disqualification_reason)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                            @lang('admin::app.leads.disqualification.status'):
                            @lang('admin::app.leads.disqualification.' . str_replace('_', '-', $lead->lead_disqualification_reason))
                        </span>

                        @if (bouncer()->hasPermission(lead_permission('disqualified')))
                            @if ($lead->lead_disqualification_comment)
                                <div class="w-full rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300">
                                    <span class="block text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                        @lang('admin::app.leads.disqualification.comment')
                                    </span>

                                    <p class="mt-1 whitespace-pre-wrap break-words">
                                        {{ $lead->lead_disqualification_comment }}
                                    </p>
                                </div>
                            @endif

                            <form
                                method="POST"
                                action="{{ lead_route('restore_disqualified', $lead->id) }}"
                            >
                                @csrf

                                <button
                                    type="submit"
                                    class="secondary-button !min-h-[34px] !px-3 text-xs"
                                >
                                    @lang('admin::app.leads.disqualification.restore')
                                </button>
                            </form>
                        @endif
                    @elseif (bouncer()->hasPermission(lead_permission('edit')))
                        @php
                            $leadDisqualificationLabels = [
                                'doNotCall'            => trans('admin::app.leads.disqualification.do-not-call'),
                                'incorrectInfo'        => trans('admin::app.leads.disqualification.incorrect-info'),
                                'confirmDoNotCall'     => trans('admin::app.leads.disqualification.confirm-do-not-call'),
                                'confirmIncorrectInfo' => trans('admin::app.leads.disqualification.confirm-incorrect-info'),
                                'comment'              => trans('admin::app.leads.disqualification.comment'),
                                'commentPlaceholder'   => trans('admin::app.leads.disqualification.comment-placeholder'),
                                'commentRequired'      => trans('admin::app.leads.disqualification.comment-required'),
                                'disagree'             => trans('admin::app.components.modal.confirm.disagree-btn'),
                                'agree'                => trans('admin::app.components.modal.confirm.agree-btn'),
                            ];
                        @endphp

                        <v-lead-disqualification-actions
                            action-url="{{ lead_route('disqualify', $lead->id) }}"
                            csrf-token="{{ csrf_token() }}"
                            :labels='@json($leadDisqualificationLabels)'
                        ></v-lead-disqualification-actions>
                    @endif
                </div>
            </div>

            <!-- Lead Attributes -->
            @include ('admin::leads.view.attributes')

            <!-- Follow-up Tracking -->
            @include ('admin::leads.view.followup')

            <!-- Contact Person -->
            @include ('admin::leads.view.person')

            @if (bouncer()->hasPermission(lead_permission('create')))
                <div class="border-b border-gray-200 p-4 dark:border-gray-800">
                    @php
                        $replicateOrganizations = collect(app(\Webkul\Contact\Repositories\OrganizationRepository::class)->getDropdownOptions())
                            ->map(fn ($organization) => [
                                'id'   => (int) $organization['value'],
                                'name' => $organization['label'],
                            ])
                            ->values();

                        $replicateTeamsByCompany = app(\Webkul\Contact\Repositories\TeamRepository::class)
                            ->getModel()
                            ->newQuery()
                            ->with(['organizations' => fn ($query) => $query->whereIn('organizations.id', $replicateOrganizations->pluck('id')->all())])
                            ->whereHas('organizations', fn ($query) => $query->whereIn('organizations.id', $replicateOrganizations->pluck('id')->all()))
                            ->orderBy('name')
                            ->get(['id', 'name'])
                            ->flatMap(fn ($team) => $team->organizations->map(fn ($organization) => [
                                'organization_id' => (int) $organization->id,
                                'team'            => [
                                    'id'   => (int) $team->id,
                                    'name' => $team->name,
                                ],
                            ]))
                            ->groupBy('organization_id')
                            ->map(fn ($rows) => $rows->pluck('team')->values())
                            ->toArray();
                    @endphp

                    <x-admin::modal ref="replicateLeadModal">
                        <x-slot:toggle>
                            <button
                                type="button"
                                class="secondary-button w-full justify-center"
                            >
                                @lang('admin::app.leads.view.replicate.action')
                            </button>
                        </x-slot>

                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.view.replicate.title')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <v-replicate-lead
                                action-url="{{ lead_route('duplicate_to_companies', $lead->id) }}"
                                :companies='@json($replicateOrganizations)'
                                :teams-by-company='@json($replicateTeamsByCompany)'
                            ></v-replicate-lead>
                        </x-slot>
                    </x-admin::modal>
                </div>
            @endif
        </div>

        {!! view_render_event('admin.leads.view.left.after', ['lead' => $lead]) !!}

        {!! view_render_event('admin.leads.view.right.before', ['lead' => $lead]) !!}

        <!-- Right Panel -->
        <div class="flex w-full flex-col gap-4 rounded-lg">
            <!-- Stages Navigation -->
            @include ('admin::leads.view.stages')

            <!-- Activities -->
            {!! view_render_event('admin.leads.view.activities.before', ['lead' => $lead]) !!}

            <x-admin::activities
                :endpoint="lead_route('activities.index', $lead->id)"
                :email-detach-endpoint="lead_route('emails.detach', $lead->id)"
                :activeType="request()->query('from') === 'quotes' ? 'quotes' : 'all'"
                :prospect-timezone="app(\Webkul\Lead\Services\UsStateTimezoneService::class)->timezoneFromPerson($lead->person)"
                :extra-types="[
                    ['name' => 'description', 'label' => trans('admin::app.leads.view.tabs.description')],
                    ['name' => 'products', 'label' => trans('admin::app.leads.view.tabs.products')],
                    ['name' => 'quotes', 'label' => trans('admin::app.leads.view.tabs.quotes')],
                ]"
            >
                <!-- Products -->
                <x-slot:products>
                    @include ('admin::leads.view.products')
                </x-slot>

                <!-- Quotes -->
                <x-slot:quotes>
                    @include ('admin::leads.view.quotes')
                </x-slot>

                <!-- Description -->
                <x-slot:description>
                    <div class="p-4 dark:text-white">
                        {{ $lead->description }}
                    </div>
                </x-slot>
            </x-admin::activities>

            {!! view_render_event('admin.leads.view.activities.after', ['lead' => $lead]) !!}
        </div>

        {!! view_render_event('admin.leads.view.right.after', ['lead' => $lead]) !!}
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-lead-disqualification-actions-template"
        >
            <div class="flex flex-wrap items-center gap-2">
                <form
                    ref="disqualificationForm"
                    method="POST"
                    :action="actionUrl"
                    class="hidden"
                >
                    <input
                        type="hidden"
                        name="_token"
                        :value="csrfToken"
                    />

                    <input
                        type="hidden"
                        name="reason"
                        :value="selectedReason"
                    />

                    <textarea
                        name="comment"
                        :value="comment"
                    ></textarea>
                </form>

                <button
                    type="button"
                    class="secondary-button !min-h-[34px] !px-3 text-xs"
                    @click="openDisqualificationModal('do_not_call', labels.doNotCall, labels.confirmDoNotCall)"
                >
                    @{{ labels.doNotCall }}
                </button>

                <button
                    type="button"
                    class="secondary-button !min-h-[34px] !px-3 text-xs"
                    @click="openDisqualificationModal('incorrect_info', labels.incorrectInfo, labels.confirmIncorrectInfo)"
                >
                    @{{ labels.incorrectInfo }}
                </button>

                <x-admin::modal
                    ref="disqualificationModal"
                    position="center"
                >
                    <x-slot:header>
                        <h3 class="text-base font-semibold dark:text-white">
                            @{{ selectedTitle }}
                        </h3>
                    </x-slot>

                    <x-slot:content>
                        <div class="grid gap-3">
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                @{{ selectedMessage }}
                            </p>

                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    @{{ labels.comment }}
                                </x-admin::form.control-group.label>

                                <textarea
                                    ref="commentInput"
                                    class="flex min-h-[110px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                    v-model.trim="comment"
                                    :placeholder="labels.commentPlaceholder"
                                ></textarea>

                                <p
                                    class="mt-1 text-xs text-red-600"
                                    v-if="commentError"
                                >
                                    @{{ commentError }}
                                </p>
                            </x-admin::form.control-group>
                        </div>
                    </x-slot>

                    <x-slot:footer>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="secondary-button"
                                @click="$refs.disqualificationModal.close()"
                            >
                                @{{ labels.disagree }}
                            </button>

                            <button
                                type="button"
                                class="primary-button"
                                @click="submitDisqualification"
                            >
                                @{{ labels.agree }}
                            </button>
                        </div>
                    </x-slot>
                </x-admin::modal>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-replicate-lead-template"
        >
            <form
                id="replicate-lead-form"
                method="POST"
                :action="actionUrl"
                @submit="onSubmit"
            >
                <input
                    type="hidden"
                    name="_token"
                    :value="csrfToken"
                />

                <template
                    v-for="companyId in selectedCompanyIds"
                    :key="'hidden-company-' + companyId"
                >
                    <input
                        type="hidden"
                        name="organization_ids[]"
                        :value="companyId"
                    />
                </template>

                <template
                    v-for="teamId in selectedTeamIds"
                    :key="'hidden-team-' + teamId"
                >
                    <input
                        type="hidden"
                        name="team_ids[]"
                        :value="teamId"
                    />
                </template>

                <div class="mb-4 flex items-center gap-2 text-xs font-medium">
                    <span
                        class="rounded-full px-2.5 py-1"
                        :class="step === 1 ? 'bg-brandColor text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                    >
                        @lang('admin::app.leads.view.replicate.step-company')
                    </span>

                    <span class="text-gray-400">→</span>

                    <span
                        class="rounded-full px-2.5 py-1"
                        :class="step === 2 ? 'bg-brandColor text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                    >
                        @lang('admin::app.leads.view.replicate.step-team')
                    </span>
                </div>

                <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                    <template v-if="step === 1">
                        @lang('admin::app.leads.view.replicate.description-step-1')
                    </template>

                    <template v-else>
                        @lang('admin::app.leads.view.replicate.description-step-2')
                    </template>
                </p>

                <div v-show="step === 1">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.leads.view.replicate.company-label')
                        </x-admin::form.control-group.label>

                        <div
                            v-if="! companies.length"
                            class="rounded border border-dashed border-gray-200 px-3 py-4 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                        >
                            @lang('admin::app.settings.access-scope.no-companies')
                        </div>

                        <div
                            v-else
                            class="grid max-h-52 gap-2 overflow-y-auto sm:grid-cols-2"
                        >
                            <label
                                v-for="company in companies"
                                :key="'company-' + company.id"
                                class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm transition-all"
                                :class="selectedCompanyIds.includes(company.id)
                                    ? 'border-brandColor bg-red-50 dark:border-brandColor dark:bg-gray-800'
                                    : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input
                                    type="checkbox"
                                    :value="company.id"
                                    v-model="selectedCompanyIds"
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                />

                                <span class="dark:text-white">@{{ company.name }}</span>
                            </label>
                        </div>

                        <p
                            v-if="companyError"
                            class="mt-2 text-xs italic text-red-600"
                        >
                            @{{ companyError }}
                        </p>

                        @error('organization_ids')
                            <p class="mt-2 text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    </x-admin::form.control-group>
                </div>

                <div v-show="step === 2">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.leads.view.replicate.team-label')
                        </x-admin::form.control-group.label>

                        <p class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                            @lang('admin::app.leads.view.replicate.team-help')
                        </p>

                        <div class="max-h-64 space-y-4 overflow-y-auto">
                            <div
                                v-for="company in selectedCompanies"
                                :key="'teams-' + company.id"
                                class="rounded border border-gray-200 p-3 dark:border-gray-700"
                            >
                                <p class="mb-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                                    @{{ company.name }}
                                </p>

                                <div
                                    v-if="! getCompanyTeams(company.id).length"
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                >
                                    @lang('admin::app.leads.view.replicate.no-teams')
                                </div>

                                <div
                                    v-else
                                    class="grid gap-2 sm:grid-cols-2"
                                >
                                    <label
                                        v-for="team in getCompanyTeams(company.id)"
                                        :key="'team-' + team.id"
                                        class="flex cursor-pointer items-center gap-2 rounded border px-3 py-2 text-sm transition-all"
                                        :class="selectedTeamIds.includes(team.id)
                                            ? 'border-brandColor bg-red-50 dark:border-brandColor dark:bg-gray-800'
                                            : 'border-gray-100 dark:border-gray-800'"
                                    >
                                        <input
                                            type="checkbox"
                                            :value="team.id"
                                            v-model="selectedTeamIds"
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                                        />

                                        <span class="dark:text-white">@{{ team.name }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        @error('team_ids')
                            <p class="mt-2 text-xs italic text-red-600">{{ $message }}</p>
                        @enderror
                    </x-admin::form.control-group>
                </div>

                <div class="mt-5 flex items-center justify-between gap-2 border-t border-gray-200 pt-4 dark:border-gray-800">
                    <button
                        v-if="step === 2"
                        type="button"
                        class="transparent-button"
                        @click="goToStep(1)"
                    >
                        @lang('admin::app.leads.view.replicate.back')
                    </button>

                    <div class="ml-auto flex items-center gap-2">
                        <button
                            v-if="step === 1"
                            type="button"
                            class="primary-button"
                            @click="goToStep(2)"
                        >
                            @lang('admin::app.leads.view.replicate.next')
                        </button>

                        <button
                            v-else
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.leads.view.replicate.submit')
                        </button>
                    </div>
                </div>
            </form>
        </script>

        <script type="module">
            app.component('v-lead-disqualification-actions', {
                template: '#v-lead-disqualification-actions-template',

                props: {
                    actionUrl: {
                        type: String,
                        required: true,
                    },

                    csrfToken: {
                        type: String,
                        required: true,
                    },

                    labels: {
                        type: Object,
                        required: true,
                    },
                },

                data() {
                    return {
                        selectedReason: '',
                        selectedTitle: '',
                        selectedMessage: '',
                        comment: '',
                        commentError: '',
                    };
                },

                methods: {
                    openDisqualificationModal(reason, title, message) {
                        this.selectedReason = reason;
                        this.selectedTitle = title;
                        this.selectedMessage = message;
                        this.comment = '';
                        this.commentError = '';

                        this.$refs.disqualificationModal.open();

                        this.$nextTick(() => {
                            this.$refs.commentInput?.focus();
                        });
                    },

                    submitDisqualification() {
                        if (! this.comment.trim()) {
                            this.commentError = this.labels.commentRequired;

                            return;
                        }

                        this.$refs.disqualificationForm?.submit();
                    },
                },
            });

            app.component('v-replicate-lead', {
                template: '#v-replicate-lead-template',

                props: {
                    actionUrl: {
                        type: String,
                        required: true,
                    },

                    companies: {
                        type: Array,
                        default: () => [],
                    },

                    teamsByCompany: {
                        type: Object,
                        default: () => ({}),
                    },
                },

                data() {
                    return {
                        step: 1,
                        selectedCompanyIds: [],
                        selectedTeamIds: [],
                        companyError: '',
                        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content
                            || '{{ csrf_token() }}',
                    };
                },

                computed: {
                    selectedCompanies() {
                        return this.companies.filter(company => this.selectedCompanyIds.includes(company.id));
                    },
                },

                watch: {
                    selectedCompanyIds(newIds) {
                        this.companyError = '';

                        const allowedTeamIds = newIds.flatMap(companyId => {
                            return this.getCompanyTeams(companyId).map(team => team.id);
                        });

                        this.selectedTeamIds = this.selectedTeamIds.filter(teamId => allowedTeamIds.includes(teamId));
                    },
                },

                methods: {
                    getCompanyTeams(companyId) {
                        return this.teamsByCompany[companyId]
                            || this.teamsByCompany[String(companyId)]
                            || [];
                    },

                    goToStep(step) {
                        if (step === 2 && ! this.selectedCompanyIds.length) {
                            this.companyError = @json(trans('admin::app.leads.view.replicate.company-required'));

                            return;
                        }

                        this.step = step;
                    },

                    onSubmit(event) {
                        if (this.step !== 2) {
                            event.preventDefault();
                            this.goToStep(2);
                        }
                    },
                },
            });
        </script>
    @endPushOnce

    @include('admin::leads.index.partials.scripts.copy-phone')
</x-admin::layouts>
