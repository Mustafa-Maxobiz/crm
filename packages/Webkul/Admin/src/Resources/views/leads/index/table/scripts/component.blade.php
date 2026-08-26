    <script type="module">
        app.component('v-leads-table', {
            template: '#v-leads-table-template',

            data() {
                return {
                    src: "{{ route($leadsIndexRoute) }}",
                    inlineOptions: @json($inlineOptions),
                    canAddServiceOffered: @json($canAddServiceOffered),
                    canEditLeadValue: @json($canEditLeadValue),
                    currentUserId: @json($currentUserId),
                    isCallingRoleLeadVariant: @json($isCallingRoleLeadVariant),
                    openServiceLeadId: null,
                    openServiceRecord: null,
                    serviceDropdownStyle: {},
                    serviceListMaxHeight: '220px',
                    serviceTriggerRefs: {},
                    serviceSearchTerm: '',
                    serviceDraftIds: {},
                    isCreatingService: false,
                    serviceAddLabel: @json(__('admin::app.leads.services-offered.add-option')),
                    serviceCreatingLabel: @json(__('admin::app.leads.services-offered.creating-option')),
                    editLeadId: null,
                    editLead: {},
                    editPerson: { name: '' },
                    editTags: [],
                    editStages: [],
                    leadTypeOptions: @json($leadTypeOptions),
                    salesOwnerOptions: @json($salesOwnerOptions),
                    pipelineOptions: @json($pipelineOptions),
                    isEditLoading: false,
                    isEditSaving: false,
                    noteLeadId: null,
                    isNoteSaving: false,
                    incorrectInfoLeadId: null,
                    incorrectInfoTagId: null,
                    incorrectInfoOldTagId: null,
                    isIncorrectInfoSaving: false,
                    pendingStageLeadId: null,
                    pendingStageId: null,
                    isMeetingSaving: false,
                    meetingErrors: {},
                    meetingScheduleFrom: '',
                    meetingScheduleTo: '',
                    schedulingContext: {},
                    defaultMeetingParticipants: @json($defaultMeetingParticipants),
                    isLgeLeadVariant: @json($isLgeLeadVariant),
                    isLgeUser: @json($isLgeUser),
                    isCallingRoleLeadVariant: @json($isCallingRoleLeadVariant),
                    activeSdrUsers: @json($activeSdrUsers),
                    meetingOwnerOptions: [],
                    meetingOwnersLoading: false,
                    meetingOwnersEmpty: false,
                    pendingHandoffLeadId: null,
                    pendingHandoffStageId: null,
                    pendingHandoffSdrUserId: '',
                    isHandoffSaving: false,
                    followupMode: null,
                    customFollowupDate: '',
                    isFollowupSaving: false,
                    pendingColdForwardRecord: null,
                    pendingColdForwardOldTagId: null,
                    pendingColdForwardNewTagId: null,
                    selectedForwardSdrUserId: '',
                    isColdForwardStoring: false,
                    coldForwardErrors: {},
                };
            },

            computed: {
                filteredServiceOptions() {
                    const items = this.inlineOptions.service_offered?.items || [];
                    const term = this.serviceSearchTerm.trim().toLowerCase();

                    if (! term) {
                        return items;
                    }

                    return items.filter(opt => String(opt.label).toLowerCase().includes(term));
                },

                serviceSearchableNewLabel() {
                    const term = this.serviceSearchTerm.trim();

                    if (! term) {
                        return '';
                    }

                    const exists = (this.inlineOptions.service_offered?.items || []).some(
                        opt => String(opt.label).toLowerCase() === term.toLowerCase()
                    );

                    return exists ? '' : term;
                },
            },

            mounted() {
                document.addEventListener('keydown', this.handleServiceEscape);

                this.unsubscribeLeadsSync = window.crmLeadsSync?.subscribe(() => {
                    this.refreshFromLeadSync();
                });

                document.addEventListener('visibilitychange', this.handleLeadsVisibilityRefresh);
            },

            beforeUnmount() {
                document.removeEventListener('keydown', this.handleServiceEscape);
                document.removeEventListener('visibilitychange', this.handleLeadsVisibilityRefresh);
                this.unsubscribeLeadsSync?.();
            },

            methods: {
                refreshFromLeadSync() {
                    clearTimeout(this._leadsSyncTimer);

                    this._leadsSyncTimer = setTimeout(() => {
                        this.$refs.datagrid?.get?.();
                    }, 250);
                },

                handleLeadsVisibilityRefresh() {
                    if (document.visibilityState === 'visible') {
                        this.refreshFromLeadSync();
                    }
                },

                setServiceTriggerRef(leadId, el) {
                    if (el) {
                        this.serviceTriggerRefs[leadId] = el;
                    } else {
                        delete this.serviceTriggerRefs[leadId];
                    }
                },

                parseServiceIds(record) {
                    const raw = record.service_option_ids;

                    if (Array.isArray(raw)) {
                        return raw.map(Number).filter(Boolean);
                    }

                    if (! raw) {
                        return [];
                    }

                    return String(raw)
                        .split(',')
                        .map(id => Number(String(id).trim()))
                        .filter(Boolean);
                },

                serviceOfferedLabel(record) {
                    const ids = this.serviceDraftIds[record.id] ?? this.parseServiceIds(record);
                    const items = this.inlineOptions.service_offered?.items || [];
                    const labels = items
                        .filter(opt => ids.includes(Number(opt.value)))
                        .map(opt => opt.label);

                    return labels.length ? labels.join(', ') : '--';
                },

                isServiceSelected(record, optionId) {
                    const ids = this.serviceDraftIds[record.id] ?? this.parseServiceIds(record);

                    return ids.includes(Number(optionId));
                },

                toggleServiceDropdown(record, event) {
                    if (this.isStageEditingLocked(record, this.inlineOptions.stage?.items || [])) {
                        return;
                    }

                    if (this.openServiceLeadId === record.id) {
                        this.closeServiceDropdown();

                        return;
                    }

                    const trigger = event?.currentTarget || this.serviceTriggerRefs[record.id];

                    if (! trigger) {
                        return;
                    }

                    const rect = trigger.getBoundingClientRect();
                    const dropdownWidth = 288;
                    const chromeHeight = 110; // search + footer approx
                    const spaceBelow = window.innerHeight - rect.bottom - 12;
                    const spaceAbove = rect.top - 12;
                    const openUp = spaceBelow < 260 && spaceAbove > spaceBelow;
                    const available = Math.max(160, openUp ? spaceAbove : spaceBelow);
                    const panelMax = Math.min(380, available);
                    const listMax = Math.max(120, panelMax - chromeHeight);

                    const left = Math.min(
                        Math.max(8, rect.left),
                        window.innerWidth - dropdownWidth - 8
                    );

                    const top = openUp
                        ? Math.max(8, rect.top - panelMax - 4)
                        : rect.bottom + 4;

                    this.openServiceLeadId = record.id;
                    this.openServiceRecord = record;
                    this.serviceSearchTerm = '';
                    this.serviceListMaxHeight = `${listMax}px`;
                    this.serviceDropdownStyle = {
                        top: `${top}px`,
                        left: `${left}px`,
                        maxHeight: `${panelMax}px`,
                        background: '#ffffff',
                        opacity: '1',
                        isolation: 'isolate',
                    };
                    this.serviceDraftIds = {
                        ...this.serviceDraftIds,
                        [record.id]: this.parseServiceIds(record),
                    };
                },

                closeServiceDropdown() {
                    this.openServiceLeadId = null;
                    this.openServiceRecord = null;
                    this.serviceSearchTerm = '';
                    this.serviceDropdownStyle = {};
                    this.serviceListMaxHeight = '220px';
                },

                handleServiceEscape(event) {
                    if (event.key === 'Escape' && this.openServiceLeadId) {
                        this.closeServiceDropdown();
                    }
                },

                toggleServiceOption(record, optionId) {
                    const id = Number(optionId);
                    const current = [...(this.serviceDraftIds[record.id] ?? this.parseServiceIds(record))];
                    const index = current.indexOf(id);

                    if (index >= 0) {
                        current.splice(index, 1);
                    } else {
                        current.push(id);
                    }

                    this.serviceDraftIds = {
                        ...this.serviceDraftIds,
                        [record.id]: current,
                    };
                },

                handleServiceEnter(record) {
                    if (this.filteredServiceOptions.length === 1) {
                        this.toggleServiceOption(record, this.filteredServiceOptions[0].value);

                        return;
                    }

                    if (this.canAddServiceOffered && this.serviceSearchableNewLabel) {
                        this.createServiceOption(record);
                    }
                },

                createServiceOption(record) {
                    if (! this.canAddServiceOffered || ! this.serviceSearchableNewLabel || this.isCreatingService) {
                        return;
                    }

                    this.isCreatingService = true;

                    this.$axios.post("{{ lead_route('services_offered.store') }}", {
                        name: this.serviceSearchableNewLabel,
                    }).then(response => {
                        const option = response.data.data;
                        const items = this.inlineOptions.service_offered?.items || [];

                        items.push({
                            value: Number(option.id),
                            label: option.name,
                        });

                        this.inlineOptions.service_offered.items = items;
                        this.toggleServiceOption(record, option.id);
                        this.serviceSearchTerm = '';

                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                    }).catch(error => {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message
                                || Object.values(error.response?.data?.errors || {})?.[0]?.[0]
                                || 'Unable to add service offered option.',
                        });
                    }).finally(() => {
                        this.isCreatingService = false;
                    });
                },

                saveServiceOffered(record) {
                    const ids = this.serviceDraftIds[record.id] ?? this.parseServiceIds(record);

                    this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${record.id}`, {
                        entity_type: 'leads',
                        services: ids,
                        service_offered: ids,
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });

                        record.service_option_ids = ids.join(',');
                        this.closeServiceDropdown();
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Update failed.',
                        });
                    });
                },

                updateLeadValue(record, rawValue) {
                    if (this.isStageEditingLocked(record, this.inlineOptions.stage?.items || [])) {
                        this.$refs.datagrid.get();

                        return;
                    }

                    if (! this.canEditLeadValue) {
                        return;
                    }

                    const previous = record.lead_value;
                    const value = rawValue === '' || rawValue === null ? 0 : Number(rawValue);

                    if (Number.isNaN(value) || value < 0) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Lead value must be a valid number.',
                        });
                        this.$refs.datagrid.get();

                        return;
                    }

                    if (Number(previous) === value) {
                        return;
                    }

                    record.lead_value = value;

                    this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${record.id}`, {
                        entity_type: 'leads',
                        lead_value: value,
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                    }).catch(error => {
                        record.lead_value = previous;
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Update failed.',
                        });
                        this.$refs.datagrid.get();
                    });
                },

                inlineUpdate(record, columnIndex, newValue) {
                    if (this.isStageEditingLocked(record, this.inlineOptions.stage?.items || [])) {
                        this.$refs.datagrid.get();

                        return;
                    }

                    const config = this.inlineOptions[columnIndex];
                    if (! config) return;

                    const leadId = record.id;
                    const field = config.field;
                    const value = newValue ? parseInt(newValue) : null;

                    const fieldMap = {
                        lead_source_id: 'lead_source_id',
                        lead_type_id: 'lead_type_id',
                        lead_pipeline_stage_id: 'lead_pipeline_stage_id',
                        tag_id: 'tag_id',
                        industry_option_id: 'industry',
                    };

                    if (field === 'tag_id') {
                        if (! value) return;

                        const selectedOpt = config.items.find(o => o.value == value);
                        const tagName = (selectedOpt?.label || '').trim().toLowerCase();

                        if (tagName === 'incorrect info') {
                            this.incorrectInfoLeadId = leadId;
                            this.incorrectInfoTagId = value;
                            this.incorrectInfoOldTagId = record.tag_id;
                            this.$refs.incorrectInfoModal.open();

                            return;
                        }

                        if (tagName === 'do not call') {
                            this.attachTagAndDisqualify(leadId, record.tag_id, value, 'do_not_call');

                            return;
                        }

                        if (this.shouldOpenColdLeadForwardModal(record, tagName)) {
                            this.pendingColdForwardRecord = record;
                            this.pendingColdForwardOldTagId = record.tag_id || null;
                            this.pendingColdForwardNewTagId = value;
                            this.selectedForwardSdrUserId = '';
                            this.coldForwardErrors = {};
                            this.$refs.coldLeadForwardModal.open();

                            return;
                        }

                        this.replaceTag(leadId, record.tag_id, value).then(() => {
                            this.$refs.datagrid.get();
                        });

                        return;
                    }

                    if (field === 'lead_pipeline_stage_id') {
                        if (! value) return;

                        if (this.isStageEditingLocked(record, config.items)) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'You can view this lead, but stage changes are locked after meeting assignment.',
                            });

                            this.$refs.datagrid.get();

                            return;
                        }

                        const selectedOpt = config.items.find(o => o.value == value);
                        const stageCode = selectedOpt?.code || '';

                        if (this.shouldPromptFollowupStage(record, selectedOpt, config.items)) {
                            this.pendingStageLeadId = leadId;
                            this.pendingStageId = value;
                            this.followupMode = null;
                            this.customFollowupDate = '';
                            this.loadSchedulingContext(leadId).then(() => {
                                this.$refs.followupStageModal.open();
                            });

                            return;
                        }

                        if (stageCode === 'meeting') {
                            if (Number(record.meeting_activity_count) > 0) {
                                this.updateStage(leadId, value)
                                    .then(() => this.$refs.datagrid.get())
                                    .catch(error => {
                                        this.$emitter.emit('add-flash', {
                                            type: 'error',
                                            message: error.response?.data?.message || 'Update failed.',
                                        });
                                        this.$refs.datagrid.get();
                                    });

                                return;
                            }

                            this.pendingStageLeadId = leadId;
                            this.pendingStageId = value;
                            this.meetingErrors = {};
                            this.loadEligibleMeetingOwners(leadId).then(() => {
                                this.$refs.meetingActivityModal.open();
                            });

                            return;
                        }

                        if (this.requiresLgeSdrHandoff(record, selectedOpt, config.items)) {
                            this.pendingHandoffLeadId = leadId;
                            this.pendingHandoffStageId = value;
                            this.pendingHandoffSdrUserId = '';
                            this.loadEligibleMeetingOwners(leadId).then(() => {
                                this.$refs.lgeSdrHandoffModal.open();
                            });

                            return;
                        }

                        this.updateStage(leadId, value)
                            .then(() => this.$refs.datagrid.get())
                            .catch(error => {
                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: error.response?.data?.message || 'Update failed.',
                                });
                                this.$refs.datagrid.get();
                            });

                        return;
                    }

                    const payload = {
                        entity_type: 'leads',
                    };

                    if (field === 'industry_option_id') {
                        payload.industry = value;
                    } else {
                        payload[field] = value;
                    }

                    this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${leadId}`, payload)
                        .then(response => {
                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            this.$refs.datagrid.get();
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Update failed.' });
                        });
                },

                gridRowStyle(available) {
                    const dataColumnMin = 160;
                    const tracks = [];
                    const visibleColumns = available.columns.filter(column => column.visibility).length;

                    if (available.massActions.length) {
                        tracks.push('40px');
                    }

                    for (let i = 0; i < visibleColumns; i++) {
                        tracks.push(`minmax(${dataColumnMin}px, 1fr)`);
                    }

                    const actionsWidth = available.actions.length > 2 ? 160 : 72;

                    if (available.actions.length) {
                        tracks.push(`${actionsWidth}px`);
                    }

                    const minWidth =
                        (available.massActions.length ? 40 : 0)
                        + (visibleColumns * dataColumnMin)
                        + (available.actions.length ? actionsWidth : 0)
                        + ((tracks.length - 1) * 10);

                    return {
                        gridTemplateColumns: tracks.join(' '),
                        minWidth: `${minWidth}px`,
                    };
                },

                handleAction(action, record, performAction) {
                    if (action.index === 'edit') {
                        this.openEditModal(record, action.url);

                        return;
                    }

                    if (action.index === 'note') {
                        this.openNoteModal(record);

                        return;
                    }

                    performAction(action);
                },

                openEditModal(record, url) {
                    this.editLeadId = record.id;
                    this.isEditLoading = true;
                    this.editLead = { id: record.id };
                    this.editPerson = { name: '' };
                    this.editTags = [];
                    this.editStages = [];

                    this.$refs.editLeadModal.open();

                    this.$axios.get(url)
                        .then(response => {
                            const data = response.data.data || {};

                            this.editLead = data;
                            this.editPerson = data.person || { name: '' };
                            this.editTags = data.tags || [];
                            this.editStages = data.stages || [];
                            this.isEditLoading = false;

                            this.$nextTick(() => {
                                this.$refs.editModalForm.setValues(data);
                            });
                        })
                        .catch(error => {
                            this.isEditLoading = false;
                            this.$refs.editLeadModal.close();
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Unable to load lead.',
                            });
                        });
                },

                saveLead(params, { setErrors }) {
                    this.isEditSaving = true;

                    const contactPerson = this.$refs.editContact?.person;
                    const personPayload = {
                        ...(params.person || {}),
                    };

                    if (contactPerson) {
                        personPayload.id = contactPerson.id ?? personPayload.id ?? null;
                        personPayload.name = contactPerson.name || personPayload.name || '';
                        personPayload.organization_id = contactPerson.organization_id
                            || contactPerson.organization?.id
                            || null;
                        personPayload.organization_name = contactPerson.organization_name || null;
                        personPayload.address = contactPerson.address ?? personPayload.address ?? null;
                        personPayload.website = contactPerson.website ?? personPayload.website ?? null;
                        personPayload.emails = contactPerson.emails ?? personPayload.emails;
                        personPayload.contact_numbers = contactPerson.contact_numbers ?? personPayload.contact_numbers;
                    }

                    if (! personPayload.organization_name) {
                        delete personPayload.organization_name;
                    }

                    if (personPayload.website === '') {
                        personPayload.website = null;
                    }

                    // Lead company FK comes from the contact company picker.
                    const organizationPayload = {};

                    if (personPayload.organization_name) {
                        organizationPayload.organization_name = personPayload.organization_name;
                        organizationPayload.organization_id = null;
                    } else if (Object.prototype.hasOwnProperty.call(personPayload, 'organization_id')) {
                        organizationPayload.organization_id = personPayload.organization_id || null;
                    }

                    this.$axios.post(`{{ lead_url() . '/edit' }}/${this.editLeadId}`, {
                        ...params,
                        ...organizationPayload,
                        person: personPayload,
                        entity_type: 'leads',
                        quick_add: 1,
                        _method: 'put',
                    }, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).then(response => {
                        this.isEditSaving = false;
                        this.$refs.editLeadModal.close();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isEditSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Unable to update lead.',
                        });
                    });
                },

                openNoteModal(record) {
                    this.noteLeadId = record.id;
                    this.$refs.noteLeadModal.open();
                },

                saveNote(params, { resetForm, setErrors }) {
                    this.isNoteSaving = true;

                    this.$axios.post("{{ route('admin.activities.store') }}", {
                        ...params,
                        type: 'note',
                        lead_id: this.noteLeadId,
                    }).then(response => {
                        this.isNoteSaving = false;
                        this.$refs.noteLeadModal.close();
                        resetForm();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                    }).catch(error => {
                        this.isNoteSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Unable to save note.',
                        });
                    });
                },

                updateStage(leadId, stageId, extra = {}) {
                    return this.$axios.put(`{{ lead_url() . '/stage/edit' }}/${leadId}`, {
                        lead_pipeline_stage_id: stageId,
                        ...extra,
                    }).then(response => {
                        this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                        return response;
                    });
                },

                shouldPromptFollowupStage(record, targetStage, stages) {
                    if ((targetStage?.code || '').toLowerCase() !== 'follow-up') {
                        return false;
                    }

                    return ! this.isCurrentRecordStage(record, 'follow-up', stages);
                },

                isCurrentRecordStage(record, stageCode, stages) {
                    const normalizedStageCode = stageCode.toLowerCase();

                    if ((record.stage_code || '').toLowerCase() === normalizedStageCode) {
                        return true;
                    }

                    const currentStage = stages.find(stage => stage.value == record.lead_pipeline_stage_id);

                    return (currentStage?.code || '').toLowerCase() === normalizedStageCode;
                },

                requiresLgeSdrHandoff(record, targetStage, stages) {
                    if (! this.isLgeLeadVariant || ! targetStage) {
                        return false;
                    }

                    const currentStage = stages.find(stage => stage.value == record.lead_pipeline_stage_id);
                    const meetingStage = stages.find(stage => stage.code === 'meeting');

                    return currentStage?.code === 'meeting'
                        && meetingStage
                        && Number(targetStage.sort_order) > Number(meetingStage.sort_order);
                },

                applyLgeSdrHandoff() {
                    if (! this.pendingHandoffSdrUserId) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Please select an SDR user.',
                        });

                        return;
                    }

                    this.isHandoffSaving = true;

                    this.updateStage(this.pendingHandoffLeadId, this.pendingHandoffStageId, {
                        sdr_user_id: this.pendingHandoffSdrUserId,
                    }).then(() => {
                        this.isHandoffSaving = false;
                        this.$refs.lgeSdrHandoffModal.close();
                        this.pendingHandoffLeadId = null;
                        this.pendingHandoffStageId = null;
                        this.pendingHandoffSdrUserId = '';
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isHandoffSaving = false;
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || error.response?.data?.errors?.sdr_user_id?.[0] || 'Update failed.',
                        });
                    });
                },

                isStageEditingLocked(record, stages) {
                    if (! this.isCallingRoleLeadVariant) {
                        return false;
                    }

                    if (Number(record.user_id) === Number(this.currentUserId)) {
                        return false;
                    }

                    return Number(record.lead_owner_id || 0) === Number(this.currentUserId);
                },

                hasParticipants(participants = {}) {
                    return ['users', 'persons'].some(type => {
                        return (participants[type] || []).some(participantId => !! participantId);
                    });
                },

                defaultSchedulingContext() {
                    return {
                        customer_timezone: null,
                        customer_state: null,
                        customer_city: null,
                        customer_country: null,
                        app_timezone: @json(config('app.timezone')),
                        pakistan_timezone: 'Asia/Karachi',
                    };
                },

                setSchedulingContext(context = {}) {
                    this.schedulingContext = {
                        ...this.defaultSchedulingContext(),
                        ...(context || {}),
                    };
                },

                loadSchedulingContext(leadId) {
                    this.setSchedulingContext();

                    return this.$axios.get(`{{ lead_url() }}/${leadId}/scheduling-context`)
                        .then(response => {
                            this.setSchedulingContext(response.data.data || {});
                        })
                        .catch(() => {
                            this.setSchedulingContext();
                        });
                },

                parseScheduleValue(value) {
                    const raw = String(value || '').trim();

                    if (! raw) {
                        return null;
                    }

                    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*(AM|PM))?$/i);

                    if (! match) {
                        return null;
                    }

                    let hour = Number(match[4]);
                    const meridian = match[7]?.toUpperCase();

                    if (meridian === 'PM' && hour < 12) {
                        hour += 12;
                    } else if (meridian === 'AM' && hour === 12) {
                        hour = 0;
                    }

                    return {
                        year: Number(match[1]),
                        month: Number(match[2]),
                        day: Number(match[3]),
                        hour,
                        minute: Number(match[5]),
                        second: Number(match[6] || 0),
                    };
                },

                timeZoneOffsetMinutes(date, timeZone) {
                    const parts = new Intl.DateTimeFormat('en-US', {
                        timeZone,
                        year: 'numeric',
                        month: '2-digit',
                        day: '2-digit',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hourCycle: 'h23',
                    }).formatToParts(date).reduce((carry, part) => {
                        if (part.type !== 'literal') {
                            carry[part.type] = part.value;
                        }

                        return carry;
                    }, {});

                    const asUtc = Date.UTC(
                        Number(parts.year),
                        Number(parts.month) - 1,
                        Number(parts.day),
                        Number(parts.hour),
                        Number(parts.minute),
                        Number(parts.second)
                    );

                    return Math.round((asUtc - date.getTime()) / 60000);
                },

                dateFromPartsInTimeZone(parts, timeZone) {
                    const utcGuess = Date.UTC(parts.year, parts.month - 1, parts.day, parts.hour, parts.minute, parts.second);
                    const firstOffset = this.timeZoneOffsetMinutes(new Date(utcGuess), timeZone);
                    const firstInstant = new Date(utcGuess - firstOffset * 60000);
                    const secondOffset = this.timeZoneOffsetMinutes(firstInstant, timeZone);

                    return new Date(utcGuess - secondOffset * 60000);
                },

                formatInTimeZone(date, timeZone) {
                    try {
                        return new Intl.DateTimeFormat('en-US', {
                            timeZone,
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true,
                            timeZoneName: 'short',
                        }).format(date);
                    } catch (error) {
                        return null;
                    }
                },

                timezonePreview(value) {
                    const parts = this.parseScheduleValue(value);
                    const context = {
                        ...this.defaultSchedulingContext(),
                        ...(this.schedulingContext || {}),
                    };
                    const pakistanTimezone = context.pakistan_timezone || 'Asia/Karachi';
                    const appTimezone = context.app_timezone || pakistanTimezone;

                    if (! parts) {
                        return { hasValue: false };
                    }

                    let instant = null;

                    try {
                        instant = this.dateFromPartsInTimeZone(parts, appTimezone);
                    } catch (error) {
                        instant = this.dateFromPartsInTimeZone(parts, pakistanTimezone);
                    }

                    const location = [context.customer_city, context.customer_state, context.customer_country].filter(Boolean).join(', ');
                    const customer = context.customer_timezone
                        ? this.formatInTimeZone(instant, context.customer_timezone)
                        : null;

                    return {
                        hasValue: true,
                        customer: customer || 'Unavailable',
                        customerMeta: context.customer_timezone
                            ? [context.customer_timezone, location].filter(Boolean).join(' · ')
                            : 'Customer timezone unavailable',
                        pakistan: this.formatInTimeZone(instant, pakistanTimezone) || 'Unavailable',
                        pakistanMeta: pakistanTimezone,
                    };
                },

                loadEligibleMeetingOwners(leadId) {
                    this.meetingOwnerOptions = [];
                    this.meetingOwnersLoading = true;
                    this.meetingOwnersEmpty = false;
                    this.meetingScheduleFrom = '';
                    this.meetingScheduleTo = '';
                    this.setSchedulingContext();

                    return this.$axios.get(`{{ lead_url() }}/${leadId}/eligible-meeting-owners`)
                        .then(response => {
                            this.meetingOwnerOptions = response.data.data || [];
                            this.meetingOwnersEmpty = this.meetingOwnerOptions.length === 0;
                            this.setSchedulingContext(response.data.scheduling_context || {});
                        })
                        .catch(() => {
                            this.meetingOwnerOptions = [];
                            this.meetingOwnersEmpty = true;
                            this.setSchedulingContext();
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
                        this.$refs.meetingActivityModal.close();
                        this.meetingScheduleFrom = '';
                        this.meetingScheduleTo = '';
                        this.setSchedulingContext();
                        this.pendingStageLeadId = null;
                        this.pendingStageId = null;
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });
                        this.$refs.datagrid.get();
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

                applyFollowupStage(mode) {
                    if (mode === 'custom') {
                        if (! this.customFollowupDate) {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'Please select a next follow-up date.',
                            });

                            return;
                        }
                    }

                    this.isFollowupSaving = true;

                    const payload = {
                        followup_mode: mode,
                    };

                    if (mode === 'custom') {
                        // datetime-local -> Y-m-d H:i:s
                        payload.next_followup_date = this.customFollowupDate.replace('T', ' ') + ':00';
                    }

                    this.updateStage(this.pendingStageLeadId, this.pendingStageId, payload)
                        .then(() => {
                            this.isFollowupSaving = false;
                            this.$refs.followupStageModal.close();
                            this.pendingStageLeadId = null;
                            this.pendingStageId = null;
                            this.followupMode = null;
                            this.customFollowupDate = '';
                            this.setSchedulingContext();
                            this.$refs.datagrid.get();
                        })
                        .catch(error => {
                            this.isFollowupSaving = false;
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || error.response?.data?.errors?.next_followup_date?.[0] || 'Update failed.',
                            });
                            this.$refs.datagrid.get();
                        });
                },

                replaceTag(leadId, oldTagId, newTagId) {
                    return this.$axios.patch(`{{ lead_url() }}/${leadId}/tags`, {
                        tag_id: newTagId,
                        old_tag_id: oldTagId || null,
                    }).catch(error => {
                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Tag update failed.' });
                        throw error;
                    });
                },

                shouldOpenColdLeadForwardModal(record, selectedTagName) {
                    if (
                        ! this.isLgeUser
                        || selectedTagName !== 'cold lead'
                        || (record.tag_name || '').trim().toLowerCase() !== 'warm lead'
                    ) {
                        return false;
                    }

                    const ownerId = Number(record.user_id || 0);
                    const leadOwnerId = Number(record.lead_owner_id || record.user_id || 0);

                    return ownerId === Number(this.currentUserId)
                        && leadOwnerId === Number(this.currentUserId);
                },

                confirmColdLeadForward() {
                    if (! this.selectedForwardSdrUserId) {
                        this.coldForwardErrors = {
                            sdr_user_id: 'Please select an SDR user.',
                        };

                        return;
                    }

                    if (! this.pendingColdForwardRecord || ! this.pendingColdForwardNewTagId) {
                        return;
                    }

                    this.coldForwardErrors = {};
                    this.isColdForwardStoring = true;

                    this.$axios.patch(`{{ lead_url() }}/${this.pendingColdForwardRecord.id}/tags`, {
                        tag_id: this.pendingColdForwardNewTagId,
                        old_tag_id: this.pendingColdForwardOldTagId || null,
                        sdr_user_id: this.selectedForwardSdrUserId,
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message,
                        });

                        this.closeColdLeadForwardModal();
                    }).catch(error => {
                        if (error.response?.status === 422) {
                            this.coldForwardErrors = {
                                sdr_user_id: error.response.data.errors?.sdr_user_id?.[0]
                                    || error.response.data.errors?.forward_to_sdr_user_id?.[0]
                                    || error.response.data.message,
                            };

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Cold lead could not be forwarded.',
                        });
                    }).finally(() => {
                        this.isColdForwardStoring = false;
                    });
                },

                closeColdLeadForwardModal() {
                    this.clearColdLeadForwardState();
                    this.$refs.coldLeadForwardModal.close();
                    this.$refs.datagrid.get();
                },

                clearColdLeadForwardState() {
                    this.pendingColdForwardRecord = null;
                    this.pendingColdForwardOldTagId = null;
                    this.pendingColdForwardNewTagId = null;
                    this.selectedForwardSdrUserId = '';
                    this.isColdForwardStoring = false;
                    this.coldForwardErrors = {};
                },

                attachTagAndDisqualify(leadId, oldTagId, newTagId, reason) {
                    this.replaceTag(leadId, oldTagId, newTagId).then(() => {
                        return this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${leadId}`, {
                            entity_type: 'leads',
                            lead_disqualification_reason: reason,
                        });
                    }).then(response => {
                        this.$emitter.emit('add-flash', { type: 'success', message: 'Tag applied and lead disqualified.' });
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Update failed.' });
                    });
                },

                saveIncorrectInfo(params, { resetForm, setErrors }) {
                    this.isIncorrectInfoSaving = true;

                    const leadId = this.incorrectInfoLeadId;
                    const tagId = this.incorrectInfoTagId;
                    const oldTagId = this.incorrectInfoOldTagId;

                    this.replaceTag(leadId, oldTagId, tagId).then(() => {
                        return this.$axios.post("{{ route('admin.activities.store') }}", {
                            type: 'note',
                            comment: params.incorrect_info_comment,
                            lead_id: leadId,
                        });
                    }).then(() => {
                        return this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${leadId}`, {
                            entity_type: 'leads',
                            lead_disqualification_reason: 'incorrect_info',
                        });
                    }).then(response => {
                        this.isIncorrectInfoSaving = false;
                        this.$refs.incorrectInfoModal.close();
                        resetForm();
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: 'Tag applied, comment saved, and lead disqualified.',
                        });
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isIncorrectInfoSaving = false;

                        if (error.response?.status === 422) {
                            setErrors(error.response.data.errors || {});
                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Unable to save.',
                        });
                    });
                },
            },
        });
