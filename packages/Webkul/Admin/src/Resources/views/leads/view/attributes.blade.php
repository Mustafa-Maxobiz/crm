{!! view_render_event('admin.leads.view.attributes.before', ['lead' => $lead]) !!}

@php
    $lockedLeadAttributeCodes = [
        'lead_source_id',
        'lead_type_id',
        'lead_sub_source_id',
        'industry',
    ];

    $hiddenAttributeCodes = [
        'title',
        'companies',
        'organization_id',
        'description',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'source_sub_type',
        'service_offered',
        'user_id', // Hide user_id from attributes - we'll show it with custom edit button
    ];

    if (lead_variant() !== 'main') {
        $hiddenAttributeCodes[] = 'lead_value';
    }

    $salesOwnerTransferService = app(\Webkul\Lead\Services\SalesOwnerTransferService::class);
    $currentSalesOwnerLabel = $salesOwnerTransferService->ownerLabel($lead->user_id);
    $canChangeSalesOwner = bouncer()->hasPermission(lead_permission('edit'))
        && ! ($readOnlyForCurrentUser ?? false)
        && $salesOwnerTransferService->eligibleOwnersForLead($lead)->isNotEmpty();
@endphp

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>@lang('admin::app.leads.view.attributes.title')</h4>
                
                @if (bouncer()->hasPermission(lead_permission('edit')))
                    <a
                        href="{{ lead_route('edit', $lead->id) }}"
                        class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                        target="_blank"
                    ></a>
                @endif
            </div>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.leads.view.attributes.form_controls.before', ['lead' => $lead]) !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, () => {})">
                    {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.before', ['lead' => $lead]) !!}

                    @if (lead_variant() === 'main')
                        <div class="mb-1 flex flex-col gap-1">
                            <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                                <div class="label dark:text-white">
                                    @lang('admin::app.leads.create.title-field')
                                </div>

                                <div class="font-medium dark:text-white">
                                    <x-admin::form.control-group.controls.inline.text
                                        type="inline"
                                        ::name="'title'"
                                        :value="$lead->title ?? ''"
                                        :value-label="($lead->title ?? '') === '' ? '--' : $lead->title"
                                        position="left"
                                        rules="required"
                                        :label="trans('admin::app.leads.create.title-field')"
                                        :placeholder="trans('admin::app.leads.create.title-field')"
                                        ::errors="errors"
                                        :url="lead_route('attributes.update', $lead->id)"
                                        :allow-edit="bouncer()->hasPermission(lead_permission('edit'))"
                                    />
                                </div>
                            </div>
                        </div>
                    @endif
        
                    <x-admin::attributes.view
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            'entity_type' => 'leads',
                            ['code', 'NOTIN', $hiddenAttributeCodes]
                        ])"
                        :entity="$lead"
                        :url="lead_route('attributes.update', $lead->id)"
                        :allow-edit="true"
                        :locked-attribute-codes="$lockedLeadAttributeCodes"
                    />

                    <div class="mb-1 flex flex-col gap-1" id="sales-owner-section">
                        <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                            <div class="label dark:text-white">
                                Sales Owner
                            </div>

                            <div class="flex items-center gap-2 font-medium dark:text-white">
                                <span id="current-owner-name">{{ $currentSalesOwnerLabel }}</span>

                                @if ($canChangeSalesOwner)
                                    <v-sales-owner-transfer
                                        eligible-url="{{ lead_route('eligible_sales_owners', $lead->id) }}"
                                        transfer-url="{{ lead_route('change_sales_owner', $lead->id) }}"
                                        csrf-token="{{ csrf_token() }}"
                                    ></v-sales-owner-transfer>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        @include('admin::leads.common.services', ['lead' => $lead])
                    </div>
        
                    {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.after', ['lead' => $lead]) !!}
                </form>
            </x-admin::form>
        
            {!! view_render_event('admin.leads.view.attributes.form_controls.after', ['lead' => $lead]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

<!-- Sales Owner Change Modal -->
@if ($canChangeSalesOwner)
    @pushOnce('scripts')
        <script type="text/x-template" id="v-sales-owner-transfer-template">
            <div>
                <div>
                    <button
                        type="button"
                        class="icon-edit rounded-md p-1 text-lg transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                        title="Change Sales Owner"
                        @click="openModal"
                    ></button>
                </div>

                <Teleport to="body">
                    <div v-if="isOpen">
                        <div class="fixed inset-0 z-[100050] bg-gray-500 bg-opacity-50"></div>

                        <div class="fixed inset-0 z-[100051] flex items-center justify-center overflow-hidden p-4">
                            <div class="box-shadow isolate flex max-h-full w-full max-w-[525px] flex-col overflow-hidden rounded-lg bg-white dark:bg-gray-900">
                                <div class="flex flex-shrink-0 items-center justify-between gap-2.5 border-b px-4 py-3 dark:border-gray-800">
                                    <h3 class="text-base font-semibold dark:text-white">
                                        Change Sales Owner
                                    </h3>

                                    <span
                                        class="icon-cross-large cursor-pointer text-3xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                                        @click="closeModal"
                                    ></span>
                                </div>

                                <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain border-b px-4 py-2.5 dark:border-gray-800">
                                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                                        Select a new sales owner for this lead.
                                    </p>

                                    <div>
                                        <label class="mb-1 block text-sm font-medium text-gray-800 dark:text-white required">
                                            New Sales Owner
                                        </label>

                                        <select
                                            v-model="selectedUserId"
                                            class="w-full rounded border border-gray-200 bg-white px-2.5 py-2 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                            :disabled="isLoading"
                                        >
                                            <option value="">
                                                @{{ isLoading ? 'Loading sales owners...' : 'Select a user...' }}
                                            </option>

                                            <option
                                                v-for="user in eligibleUsers"
                                                :key="user.id"
                                                :value="String(user.id)"
                                            >
                                                @{{ user.label }}
                                            </option>
                                        </select>

                                        <p
                                            v-if="errorMessage"
                                            class="mt-1 text-xs text-red-600"
                                        >
                                            @{{ errorMessage }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex flex-shrink-0 justify-end border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="secondary-button"
                                            @click="closeModal"
                                            :disabled="isSubmitting"
                                        >
                                            Cancel
                                        </button>

                                        <button
                                            type="button"
                                            class="primary-button"
                                            @click="changeSalesOwner"
                                            :disabled="! selectedUserId || isSubmitting"
                                        >
                                            <template v-if="isSubmitting">Changing...</template>
                                            <template v-else>Change Owner</template>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </Teleport>
            </div>
        </script>

        <script type="module">
            app.component('v-sales-owner-transfer', {
                template: '#v-sales-owner-transfer-template',

                props: {
                    eligibleUrl: {
                        type: String,
                        required: true,
                    },

                    transferUrl: {
                        type: String,
                        required: true,
                    },

                    csrfToken: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        eligibleUsers: [],
                        selectedUserId: '',
                        isLoading: false,
                        isSubmitting: false,
                        errorMessage: '',
                        isOpen: false,
                    };
                },

                methods: {
                    openModal() {
                        this.isOpen = true;
                        document.body.style.overflow = 'hidden';
                        this.fetchEligibleUsers();
                    },

                    fetchEligibleUsers() {
                        this.isLoading = true;
                        this.errorMessage = '';

                        fetch(this.eligibleUrl, {
                            headers: {
                                'Accept': 'application/json',
                            },
                        })
                            .then(async response => {
                                const data = await response.json();

                                if (! response.ok) {
                                    throw new Error(data.message || 'Failed to load users.');
                                }

                                return data;
                            })
                            .then(data => {
                                this.eligibleUsers = data.users || [];
                                this.isLoading = false;
                            })
                            .catch(error => {
                                this.errorMessage = error.message || 'Failed to load users.';
                                this.isLoading = false;
                                console.error('Error fetching eligible users:', error);
                            });
                    },

                    changeSalesOwner() {
                        if (!this.selectedUserId) {
                            this.errorMessage = 'Please select a user.';
                            return;
                        }

                        this.isSubmitting = true;
                        this.errorMessage = '';

                        fetch(this.transferUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken
                            },
                            body: JSON.stringify({ user_id: this.selectedUserId })
                        })
                        .then(async response => {
                            const data = await response.json();

                            if (! response.ok) {
                                throw new Error(data.message || data.errors?.user_id?.[0] || 'Failed to change sales owner.');
                            }

                            return data;
                        })
                        .then(data => {
                            if (data.message) {
                                this.closeModal();
                                window.location.reload();
                            } else {
                                throw new Error(data.message || 'Failed to change sales owner.');
                            }
                        })
                        .catch(error => {
                            this.isSubmitting = false;
                            this.errorMessage = error.message || 'Failed to change sales owner.';
                            console.error('Error changing sales owner:', error);
                        });
                    },

                    closeModal() {
                        this.selectedUserId = '';
                        this.errorMessage = '';
                        this.isOpen = false;
                        this.isSubmitting = false;
                        document.body.style.overflow = 'auto';
                    },
                }
            });
        </script>
    @endPushOnce
@endif

{!! view_render_event('admin.leads.view.attributes.after', ['lead' => $lead]) !!}
