    <script type="module">
        app.component('v-leads-kanban', {
            template: '#v-leads-kanban-template',

            data() {
                return {
                    available: {
                        columns: @json($columns),
                    },

                    applied: {
                        filters: {
                            columns: [],
                        },
                        sort: {
                            by: 'created_at',
                            order: 'desc',
                        }
                    },

                    finalized: {
                        lead: null,
                        stage: null,
                        updating: false,
                    },

                    pendingStageLeadId: null,

                    pendingStageId: null,

                    pendingFollowupLead: null,

                    pendingFollowupStage: null,

                    followupMode: null,

                    customFollowupDate: '',

                    isFollowupSaving: false,

                    isMeetingSaving: false,

                    meetingErrors: {},

                    meetingOwnerOptions: [],

                    meetingOwnersLoading: false,

                    meetingOwnersEmpty: false,

                    defaultMeetingParticipants: @json($defaultMeetingParticipants),
                    currentUserId: @json($currentUserId),
                    isLgeLeadVariant: @json($isLgeLeadVariant),
                    isCallingRoleLeadVariant: @json($isCallingRoleLeadVariant),

                    stages: @json($accessibleStages->all()),

                    stageLeads: {},

                    isLoading: true,

                    tagTextColor: {
                        '#FEE2E2': '#DC2626',
                        '#FFEDD5': '#EA580C',
                        '#FEF3C7': '#D97706',
                        '#FEF9C3': '#CA8A04',
                        '#ECFCCB': '#65A30D',
                        '#DCFCE7': '#16A34A',
                    },
                };
            },

            computed: {
                /**
                 * Computes the total amount of leads across all stages.
                 *
                 * @return {number} The total amount of leads.
                 */
                totalStagesAmount() {
                    let totalAmount = 0;

                    for (let [key, stage] of Object.entries(this.stageLeads)) {
                        totalAmount += parseFloat(stage.lead_value);
                    }

                    return totalAmount;
                },

                /**
                 * Returns the label for the current sort option.
                 *
                 * @return {string} The sort label.
                 */
                sortLabel() {
                    const sortOptions = {
                        'created_at_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')',
                        'created_at_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.oldest-first')',
                        'lead_value_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.value-high-low')',
                        'lead_value_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.value-low-high')',
                        'title_asc': '@lang('admin::app.leads.index.kanban.toolbar.sort.title-az')',
                        'title_desc': '@lang('admin::app.leads.index.kanban.toolbar.sort.title-za')',
                    };

                    return sortOptions[`${this.applied.sort.by}_${this.applied.sort.order}`] || '@lang('admin::app.leads.index.kanban.toolbar.sort.newest-first')';
                }
            },

            mounted () {
                this.boot();

                this.unsubscribeLeadsSync = window.crmLeadsSync?.subscribe(() => {
                    this.refreshFromLeadSync();
                });

                document.addEventListener('visibilitychange', this.handleLeadsVisibilityRefresh);
            },

            beforeUnmount() {
                document.removeEventListener('visibilitychange', this.handleLeadsVisibilityRefresh);
                this.unsubscribeLeadsSync?.();
            },

            methods: {
                refreshFromLeadSync() {
                    clearTimeout(this._leadsSyncTimer);

                    this._leadsSyncTimer = setTimeout(() => {
                        this.get()
                            .then(response => {
                                for (let [sortOrder, data] of Object.entries(response.data)) {
                                    this.stageLeads[sortOrder] = data;
                                }
                            });
                    }, 250);
                },

                handleLeadsVisibilityRefresh() {
                    if (document.visibilityState === 'visible') {
                        this.refreshFromLeadSync();
                    }
                },

                /**
                 * Initialization: This function checks for any previously saved filters in local storage and applies them as needed.
                 *
                 * @returns {void}
                 */
                boot() {
                    let kanbans = this.getKanbans();

                    if (kanbans?.length) {
                        const currentKanban = kanbans.find(({ src }) => src === this.src);

                        if (currentKanban) {
                            this.applied.filters = currentKanban.applied.filters;
                            
                            // Restore sort settings if available
                            if (currentKanban.applied.sort) {
                                this.applied.sort = currentKanban.applied.sort;
                            }

                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            return;
                        }
                    }

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Fetches the leads based on the applied filters.
                 *
                 * @param {object} requestedParams - The requested parameters.
                 * @returns {Promise} The promise object representing the request.
                 */
                get(requestedParams = {}) {
                    let params = {
                        search: '',
                        searchFields: '',
                        searchJoin: 'and',
                        lead_search: '',
                        pipeline_id: "{{ request('pipeline_id') }}",
                        limit: 10,
                        sort_by: this.applied.sort.by,
                        sort_order: this.applied.sort.order,
                    };

                    this.applied.filters.columns.forEach((column) => {
                        if (column.index === 'all') {
                            if (! column.value.length) {
                                return;
                            }

                            const searchValue = column.value.join(',');

                            params['lead_search'] = searchValue;

                            return;
                        }

                        /**
                         * If the column is a searchable dropdown, then we need to append the column value
                         * with the column label. Otherwise, we can directly append the column value.
                         */
                        params['search'] += column.filterable_type === 'searchable_dropdown'
                            ? `${column.index}:${column.value.map(option => option.value).join(',')};`
                            : `${column.index}:${column.value.join(',')};`;

                        params['searchFields'] += `${column.index}:${column.search_field};`;
                    });

                    return this.$axios
                        .get("{{ lead_route('get') }}", {
                            params: {
                                ...params,

                                ...requestedParams,
                            }
                        })
                        .then(response => {
                            this.isLoading = false;

                            this.updateKanbans();

                            return response;
                        })
                        .catch(error => {
                            console.log(error);

                            this.isLoading = false;

                            return { data: this.stageLeads || {} };
                        });
                },

                /**
                 * Filters the leads based on the applied filters.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                filter(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index === 'all')),
                        ...filters.columns,
                    ];

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Searches the leads based on the applied filters.
                 *
                 * @param {object} filters - The filters to be applied.
                 * @returns {void}
                 */
                search(filters) {
                    this.applied.filters.columns = [
                        ...(this.applied.filters.columns.filter((column) => column.index !== 'all')),
                        ...filters.columns,
                    ];

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Sorts the leads based on the selected sort option.
                 *
                 * @param {string} by - The field to sort by.
                 * @param {string} order - The sort order (asc/desc).
                 * @returns {void}
                 */
                sort(by, order) {
                    this.applied.sort.by = by;
                    this.applied.sort.order = order;

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Appends the leads to the stage.
                 *
                 * @param {object} params - The parameters to be appended.
                 * @returns {void}
                 */
                append(params) {
                    this.get(params)
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                if (! this.stageLeads[sortOrder]) {
                                    this.stageLeads[sortOrder] = data;
                                } else {
                                    this.stageLeads[sortOrder].leads.data = this.stageLeads[sortOrder].leads.data.concat(data.leads.data);

                                    this.stageLeads[sortOrder].leads.meta = data.leads.meta;
                                }
                            }
                        });
                },

                /**
                 * Updates the stage with the latest lead data.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The event object.
                 * @returns {void}
                 */
                handleUpdate(stage, event) {
                    if (event.moved) {
                        return;
                    }

                    if (
                        event.added
                        && event.added.element
                        && this.isStageEditingLocked(event.added.element)
                    ) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'You can view this lead, but stage changes are locked after meeting assignment.',
                        });

                        this.refreshKanban();

                        return;
                    }

                    if (
                        event.added
                        && event.added.element
                        && ! this.canUseStage(stage)
                    ) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'You can move SDR/LGE leads up to Meeting only.',
                        });

                        this.refreshKanban();

                        return;
                    }

                    if (
                        (stage.code === "won" || stage.code === "lost")
                        && event.added
                        && event.added.element
                    ) {
                        this.finalized.lead = event.added.element;

                        this.finalized.stage = stage;

                        this.toggleStageUpdateModal();

                        return;
                    }

                    if (event.removed) {
                        stage.lead_value = parseFloat(stage.lead_value) - parseFloat(event.removed.element.lead_value);

                        this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total - 1;

                        return;
                    }

                    if (
                        stage.code === 'meeting'
                        && event.added
                        && event.added.element
                    ) {
                        this.pendingStageLeadId = event.added.element.id;
                        this.pendingStageId = stage.id;
                        this.meetingErrors = {};
                        this.loadEligibleMeetingOwners(event.added.element.id).then(() => {
                            this.$refs.meetingActivityModal.open();
                        });

                        return;
                    }

                    if (
                        event.added
                        && event.added.element
                        && this.shouldPromptFollowupStage(event.added.element, stage)
                    ) {
                        this.pendingFollowupLead = event.added.element;
                        this.pendingFollowupStage = stage;
                        this.followupMode = null;
                        this.customFollowupDate = '';
                        this.$refs.followupStageModal.open();

                        return;
                    }

                    stage.lead_value = parseFloat(stage.lead_value) + parseFloat(event.added.element.lead_value);

                    this.stageLeads[stage.sort_order].leads.meta.total = this.stageLeads[stage.sort_order].leads.meta.total + 1;

                    this.updateStage('{{ lead_route('stage.update', '__LEAD_ID__') }}'.replace('__LEAD_ID__', event.added.element.id), {
                        'lead_pipeline_stage_id': stage.id
                    })
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });;
                },

                /**
                 * Updates the stage with the latest lead data.
                 *
                 * @param {string} url - The URL to update the stage.
                 * @param {object} params - The parameters to be updated.
                 *
                 * @returns {Promise} The promise object representing the request.
                 */
                updateStage(url, params) {
                    return this.$axios.put(url, params);
                },

                shouldPromptFollowupStage(lead, stage) {
                    if ((stage?.code || '').toLowerCase() !== 'follow-up') {
                        return false;
                    }

                    return ! this.isCurrentLeadStage(lead, 'follow-up');
                },

                isCurrentLeadStage(lead, stageCode) {
                    const normalizedStageCode = stageCode.toLowerCase();

                    if ((lead.stage_code || lead.stage?.code || '').toLowerCase() === normalizedStageCode) {
                        return true;
                    }

                    const stageId = lead.lead_pipeline_stage_id || lead.stage?.id;
                    const currentStage = this.stages.find(stage => stage.id == stageId);

                    return (currentStage?.code || '').toLowerCase() === normalizedStageCode;
                },

                isNewStageLead(lead) {
                    return this.isCurrentLeadStage(lead, 'new');
                },

                applyFollowupStage(mode) {
                    if (! this.pendingFollowupLead || ! this.pendingFollowupStage) {
                        return;
                    }

                    if (mode === 'custom' && ! this.customFollowupDate) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Please select a next follow-up date.',
                        });

                        return;
                    }

                    const payload = {
                        lead_pipeline_stage_id: this.pendingFollowupStage.id,
                        followup_mode: mode,
                    };

                    if (mode === 'custom') {
                        payload.next_followup_date = this.customFollowupDate.replace('T', ' ') + ':00';
                    }

                    this.isFollowupSaving = true;

                    this.updateStage('{{ lead_route('stage.update', '__LEAD_ID__') }}'.replace('__LEAD_ID__', this.pendingFollowupLead.id), payload)
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.followupStageModal.close();
                            this.pendingFollowupLead = null;
                            this.pendingFollowupStage = null;
                            this.followupMode = null;
                            this.customFollowupDate = '';
                            this.refreshKanban();
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || error.response?.data?.errors?.next_followup_date?.[0] || 'Update failed.',
                            });

                            this.refreshKanban();
                        })
                        .finally(() => {
                            this.isFollowupSaving = false;
                        });
                },

                isStageEditingLocked(lead) {
                    if (! this.isCallingRoleLeadVariant) {
                        return false;
                    }

                    if (Number(lead.user_id || 0) === Number(this.currentUserId)) {
                        return false;
                    }

                    return Number(lead.lead_owner_id || 0) === Number(this.currentUserId);
                },

                findStageForList(list) {
                    if (! list) {
                        return null;
                    }

                    return this.stages.find(stage => {
                        return this.stageLeads[stage.sort_order]?.leads?.data === list;
                    });
                },

                canUseStage(stage) {
                    if (! this.isCallingRoleLeadVariant || ! stage) {
                        return true;
                    }

                    const meetingStage = this.stages.find(item => item.code === 'meeting');

                    if (! meetingStage) {
                        return true;
                    }

                    return Number(stage.sort_order || 0) <= Number(meetingStage.sort_order || 0);
                },

                canMoveLead(event) {
                    const lead = event.draggedContext?.element;

                    if (! lead || this.isStageEditingLocked(lead)) {
                        return false;
                    }

                    return this.canUseStage(this.findStageForList(event.relatedContext?.list));
                },

                isHandoffLead(lead) {
                    if (! this.isCallingRoleLeadVariant) {
                        return false;
                    }

                    return Number(lead.lead_owner_id || 0) === Number(this.currentUserId)
                        && Number(lead.user_id || 0) !== Number(this.currentUserId);
                },

                hasParticipants(participants = {}) {
                    return ['users', 'persons'].some(type => {
                        return (participants[type] || []).some(participantId => !! participantId);
                    });
                },

                loadEligibleMeetingOwners(leadId) {
                    this.meetingOwnerOptions = [];
                    this.meetingOwnersLoading = true;
                    this.meetingOwnersEmpty = false;

                    return this.$axios.get(`{{ lead_url() }}/${leadId}/eligible-meeting-owners`)
                        .then(response => {
                            this.meetingOwnerOptions = response.data.data || [];
                            this.meetingOwnersEmpty = this.meetingOwnerOptions.length === 0;
                        })
                        .catch(() => {
                            this.meetingOwnerOptions = [];
                            this.meetingOwnersEmpty = true;
                        })
                        .finally(() => {
                            this.meetingOwnersLoading = false;
                        });
                },

                saveMeetingAndMove(params, { setErrors }) {
                    this.meetingErrors = {};

                    this.isMeetingSaving = true;

                    this.$axios.post("{{ route('admin.activities.store') }}", {
                        ...params,
                        type: 'meeting',
                        activity_status: 'scheduled',
                        stage_meeting: 1,
                        lead_id: this.pendingStageLeadId,
                        lead_pipeline_stage_id: this.pendingStageId,
                    }).then((response) => {
                        this.isMeetingSaving = false;
                        this.pendingStageLeadId = null;
                        this.pendingStageId = null;
                        this.$refs.meetingActivityModal.close();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                        this.refreshKanban();
                    }).catch(error => {
                        this.isMeetingSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});
                            this.meetingErrors = {
                                participants: error.response.data.errors?.participants?.[0],
                            };

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Meeting could not be saved.',
                        });
                    });
                },

                handleMeetingModalToggle(state) {
                    if (state.isActive || this.isMeetingSaving) {
                        return;
                    }

                    if (this.pendingStageLeadId || this.pendingStageId) {
                        this.pendingStageLeadId = null;
                        this.pendingStageId = null;
                        this.meetingErrors = {};
                        this.refreshKanban();
                    }
                },

                handleFollowupModalToggle(state) {
                    if (state.isActive || this.isFollowupSaving) {
                        return;
                    }

                    if (this.pendingFollowupLead || this.pendingFollowupStage) {
                        this.pendingFollowupLead = null;
                        this.pendingFollowupStage = null;
                        this.followupMode = null;
                        this.customFollowupDate = '';
                        this.refreshKanban();
                    }
                },

                refreshKanban() {
                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                handleFormSubmit(params) {
                    if (this.isStageEditingLocked(this.finalized.lead || {})) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'You can view this lead, but stage changes are locked after meeting assignment.',
                        });

                        this.toggleStageUpdateModal();
                        this.resetFinalized();
                        this.refreshKanban();

                        return;
                    }

                    this.finalized.updating = true;

                    this.updateStage("{{ lead_route('stage.update', '__LEAD_ID__') }}".replace('__LEAD_ID__', this.finalized.lead.id), {
                        ...params,
                        lead_pipeline_stage_id: this.finalized.stage.id,
                    })
                        .then(response => {
                            this.toggleStageUpdateModal();

                            this.resetFinalized();

                            this.get()
                                .then(response => {
                                    for (let [sortOrder, data] of Object.entries(response.data)) {
                                        this.stageLeads[sortOrder] = data;
                                    }
                                });

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        }).finally(() => {
                            this.finalized.updating = false;
                        });
                },

                /**
                 * Resets the finalized lead and stage data.
                 *
                 * @returns {void}
                 */
                resetFinalized() {
                    this.finalized = {
                        lead: null,
                        stage: null,
                        updating: false,
                    };
                },

                /**
                 * Handles the close event of the modal.
                 *
                 * @returns {void}
                 */
                handleCloseModal(state) {
                    if (state.isActive) {
                        return;
                    }

                    this.resetFinalized();

                    this.get()
                        .then(response => {
                            for (let [sortOrder, data] of Object.entries(response.data)) {
                                this.stageLeads[sortOrder] = data;
                            }
                        });
                },

                /**
                 * Toggles the stage update modal.
                 *
                 * @returns {void}
                 */
                toggleStageUpdateModal() {
                    this.$refs.stageUpdateModal.toggle();
                },

                /**
                 * Handles the scroll event on the stage leads.
                 *
                 * @param {object} stage - The stage object.
                 * @param {object} event - The scroll event.
                 * @returns {void}
                 */
                handleScroll(stage, event) {
                    const bottom = event.target.scrollHeight - event.target.scrollTop === event.target.clientHeight;

                    if (! bottom) {
                        return;
                    }

                    if (this.stageLeads[stage.sort_order].leads.meta.current_page == this.stageLeads[stage.sort_order].leads.meta.last_page) {
                        return;
                    }

                    this.append({
                        pipeline_stage_id: stage.id,
                        pipeline_id: stage.lead_pipeline_id,
                        page: this.stageLeads[stage.sort_order].leads.meta.current_page + 1,
                        limit: 10,
                    });
                },

                getTagTextColor(color) {
                    if (! color) {
                        return '#111827';
                    }

                    if (this.tagTextColor[color]) {
                        return this.tagTextColor[color];
                    }

                    const hex = color.replace('#', '');

                    if (! /^[0-9A-Fa-f]{6}$/.test(hex)) {
                        return '#111827';
                    }

                    const red = parseInt(hex.substring(0, 2), 16);
                    const green = parseInt(hex.substring(2, 4), 16);
                    const blue = parseInt(hex.substring(4, 6), 16);
                    const brightness = ((red * 299) + (green * 587) + (blue * 114)) / 1000;

                    return brightness < 150 ? '#FFFFFF' : '#111827';
                },

                //=======================================================================================
                // Support for previous applied values in kanban's. All code is based on local storage.
                //=======================================================================================

                /**
                 * Updates the kanban's stored in local storage with the latest data.
                 *
                 * @returns {void}
                 */
                 updateKanbans() {
                    let kanbans = this.getKanbans();

                    if (kanbans?.length) {
                        const currentKanban = kanbans.find(({ src }) => src === this.src);

                        if (currentKanban) {
                            kanbans = kanbans.map(kanban => {
                                if (kanban.src === this.src) {
                                    return {
                                        ...kanban,
                                        requestCount: ++kanban.requestCount,
                                        available: this.available,
                                        applied: this.applied,
                                    };
                                }

                                return kanban;
                            });
                        } else {
                            kanbans.push(this.getKanbanInitialProperties());
                        }
                    } else {
                        kanbans = [this.getKanbanInitialProperties()];
                    }

                    this.setKanbans(kanbans);
                },

                /**
                 * Returns the initial properties for a kanban.
                 *
                 * @returns {object} Initial properties for a kanban.
                 */
                getKanbanInitialProperties() {
                    return {
                        src: this.src,
                        requestCount: 0,
                        available: this.available,
                        applied: this.applied,
                    };
                },

                /**
                 * Returns the storage key for kanban's in local storage.
                 *
                 * @returns {string} Storage key for kanban's.
                 */
                getKanbansStorageKey() {
                    return 'kanbans';
                },

                /**
                 * Retrieves the kanban's stored in local storage.
                 *
                 * @returns {Array} Kanban's stored in local storage.
                 */
                getKanbans() {
                    let kanbans = localStorage.getItem(
                        this.getKanbansStorageKey()
                    );

                    return JSON.parse(kanbans) ?? [];
                },

                /**
                 * Sets the kanban's in local storage.
                 *
                 * @param {Array} kanbans - Kanban's to be stored in local storage.
                 * @returns {void}
                 */
                setKanbans(kanbans) {
                    localStorage.setItem(
                        this.getKanbansStorageKey(),
                        JSON.stringify(kanbans)
                    );
                },
            }
        });
    </script>
