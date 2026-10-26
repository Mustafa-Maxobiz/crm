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
                    canManageAllLeadOwners: @json($canManageAllLeadOwners),
                    isCallingRoleLeadVariant: @json($isCallingRoleLeadVariant),
                    openServiceLeadId: null,
                    openServiceRecord: null,
                    serviceDropdownStyle: {},
                    serviceListMaxHeight: '220px',
                    serviceTriggerRefs: {},
                    serviceSearchTerm: '',
                    newServiceName: '',
                    addingOtherServiceLeadId: null,
                    serviceDraftIds: {},
                    isCreatingService: false,
                    serviceAddLabel: @json(__('admin::app.leads.services-offered.add-option')),
                    serviceCreatingLabel: @json(__('admin::app.leads.services-offered.creating-option')),
                    editLeadId: null,
                    editLead: {},
                    editPerson: { name: '' },
                    editTags: [],
                    editTagIds: [],
                    editScheduleFollowup: false,
                    editStages: [],
                    leadTypeOptions: @json($leadTypeOptions),
                    salesOwnerOptions: @json($salesOwnerOptions),
                    salesOwnerOptionsByLead: {},
                    salesOwnerOptionsLoading: {},
                    openSalesOwnerLeadId: null,
                    openSalesOwnerRecord: null,
                    salesOwnerDropdownStyle: {},
                    salesOwnerTriggerRefs: {},
                    closureTransferRecord: null,
                    closureTransferOwner: null,
                    closureTransferError: '',
                    isClosureTransferSaving: false,
                    closureMeetingTransferRecord: null,
                    closureMeetingTransferOwner: null,
                    selectedMeetingOwnerId: '',
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
                    isOwnerRestrictedLeadVariant: @json($isOwnerRestrictedLeadVariant),
                    activeSdrUsers: @json($activeSdrUsers),
                    meetingOwnerOptions: [],
                    meetingOwnersLoading: false,
                    meetingOwnersEmpty: false,
                    pendingHandoffLeadId: null,
                    pendingHandoffStageId: null,
                    pendingHandoffSdrUserId: '',
                    isHandoffSaving: false,
                    followupMode: null,
                    followupRuleOptions: @json($followupRuleOptions),
                    selectedFollowupRuleId: '',
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

                    if (this.isOtherServiceSelected(this.openServiceRecord) && ! term) {
                        const otherId = this.otherServiceId();
                        const otherOption = items.find(opt => Number(opt.value) === Number(otherId));

                        return otherOption ? [otherOption] : [];
                    }

                    if (! term) {
                        return items.filter(opt => opt.is_show !== false);
                    }

                    return items.filter(opt => String(opt.label).toLowerCase().includes(term));
                },

                serviceSearchableNewLabel() {
                    const term = this.newServiceName.trim();

                    if (! term) {
                        return '';
                    }

                    const exists = (this.inlineOptions.service_offered?.items || []).some(
                        opt => String(opt.label).toLowerCase() === term.toLowerCase()
                    );

                    return exists ? '' : term;
                },

                selectedMeetingOwner() {
                    const ownerId = Number(this.selectedMeetingOwnerId || 0);

                    if (! ownerId) {
                        return null;
                    }

                    return this.meetingOwnerOptions.find(user => Number(user.id) === ownerId) || null;
                },

                selectedMeetingOwnerHasNoWorkingHours() {
                    return !! this.selectedMeetingOwner && this.selectedMeetingOwner.has_working_hours === false;
                },

                selectedMeetingOwnerWorkingHoursLabel() {
                    return this.selectedMeetingOwner?.working_hour_label || '';
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
                    const otherId = this.otherServiceId();
                    const labels = items
                        .filter(opt => ids.includes(Number(opt.value)) && Number(opt.value) !== Number(otherId))
                        .map(opt => opt.label);

                    if (ids.length) {
                        return labels.length ? labels.join(', ') : '--';
                    }

                    return record.service_offered || '--';
                },

                isServiceSelected(record, optionId) {
                    if (this.isOtherServiceId(optionId)) {
                        return this.isOtherServiceSelected(record);
                    }

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
                    const chromeHeight = 58; // search area approx
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
                    this.newServiceName = '';
                    this.addingOtherServiceLeadId = null;
                    this.serviceDropdownStyle = {};
                    this.serviceListMaxHeight = '220px';
                },

                handleServiceEscape(event) {
                    if (event.key === 'Escape' && this.openServiceLeadId) {
                        this.closeServiceDropdown();
                    }

                    if (event.key === 'Escape' && this.openSalesOwnerLeadId) {
                        this.closeSalesOwnerDropdown();
                    }
                },

                toggleServiceOption(record, optionId) {
                    const id = Number(optionId);
                    const otherId = this.otherServiceId();

                    if (this.isOtherServiceId(id)) {
                        this.addingOtherServiceLeadId = record.id;
                        this.serviceSearchTerm = '';
                        this.serviceDraftIds = {
                            ...this.serviceDraftIds,
                            [record.id]: [...(this.serviceDraftIds[record.id] ?? this.parseServiceIds(record))]
                                .filter(existingId => Number(existingId) !== Number(otherId)),
                        };

                        this.$nextTick(() => {
                            const input = Array.isArray(this.$refs.newServiceInput)
                                ? this.$refs.newServiceInput[0]
                                : this.$refs.newServiceInput;

                            input?.focus();
                        });

                        return;
                    }

                    const current = [...(this.serviceDraftIds[record.id] ?? this.parseServiceIds(record))]
                        .filter(existingId => Number(existingId) !== Number(otherId));
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

                    this.persistServiceOffered(record, current);
                },

                handleServiceEnter(record) {
                    if (this.filteredServiceOptions.length === 1) {
                        this.toggleServiceOption(record, this.filteredServiceOptions[0].value);
                    }
                },

                createOtherServiceOption(record) {
                    const serviceName = String(this.newServiceName || '').trim();

                    if (! this.canAddServiceOffered || ! serviceName || this.isCreatingService) {
                        return;
                    }

                    this.isCreatingService = true;

                    this.$axios.post("{{ lead_route('services_offered.store') }}", {
                        name: serviceName,
                    }).then(response => {
                        const option = response.data.data;
                        const items = this.inlineOptions.service_offered?.items || [];

                        items.push({
                            value: Number(option.id),
                            label: option.name,
                            is_show: option.is_show ?? false,
                        });

                        this.inlineOptions.service_offered.items = items;

                        // Add the new service to the current selections
                        const currentIds = [...(this.serviceDraftIds[record.id] ?? this.parseServiceIds(record))]
                            .filter(id => Number(id) !== Number(this.otherServiceId()));
                        currentIds.push(Number(option.id));

                        // Remove "Other" option if it's selected
                        const otherId = this.otherServiceId();
                        const finalIds = currentIds.filter(id => Number(id) !== Number(otherId));

                        // Save immediately to the database
                        this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${record.id}`, {
                            entity_type: 'leads',
                            services: finalIds,
                            service_offered: finalIds,
                        }).then(saveResponse => {
                            // Update the record display
                            record.service_option_ids = finalIds.join(',');
                            record.service_offered = this.serviceNamesForIds(finalIds).join(', ');

                            // Clear draft state
                            if (this.serviceDraftIds[record.id]) {
                                delete this.serviceDraftIds[record.id];
                            }

                            // Clear the input field
                            this.newServiceName = '';
                            this.addingOtherServiceLeadId = null;

                            // Show success notification
                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: `${option.name} added successfully`,
                            });

                            // Close the dropdown
                            this.closeServiceDropdown();
                        }).catch(saveError => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: saveError.response?.data?.message || 'Failed to save service',
                            });
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

                isOtherServiceId(optionId) {
                    const option = (this.inlineOptions.service_offered?.items || [])
                        .find(item => Number(item.value) === Number(optionId));

                    return this.isOtherServiceOption(option);
                },

                isOtherServiceOption(option) {
                    return String(option?.label || '').trim().toLowerCase() === 'other';
                },

                otherServiceId() {
                    return (this.inlineOptions.service_offered?.items || [])
                        .find(item => String(item.label || '').trim().toLowerCase() === 'other')?.value;
                },

                isOtherServiceSelected(record) {
                    const otherId = this.otherServiceId();

                    if (! otherId || ! record) {
                        return false;
                    }

                    const ids = this.serviceDraftIds[record.id] ?? this.parseServiceIds(record);

                    return this.addingOtherServiceLeadId === record.id || ids.includes(Number(otherId));
                },

                unselectOtherService(record) {
                    const otherId = this.otherServiceId();

                    if (! otherId || ! record) {
                        return;
                    }

                    const current = [...(this.serviceDraftIds[record.id] ?? this.parseServiceIds(record))]
                        .filter(id => Number(id) !== Number(otherId));

                    this.serviceDraftIds = {
                        ...this.serviceDraftIds,
                        [record.id]: current,
                    };
                },

                saveServiceOffered(record) {
                    const otherId = this.otherServiceId();
                    const ids = (this.serviceDraftIds[record.id] ?? this.parseServiceIds(record))
                        .filter(id => Number(id) !== Number(otherId));

                    this.persistServiceOffered(record, ids, null, true);
                },

                persistServiceOffered(record, ids, successMessage = null, closeAfterSave = false) {
                    const otherId = this.otherServiceId();
                    const serviceIds = ids.filter(id => Number(id) !== Number(otherId));

                    this.$axios.put(`{{ lead_url() . '/attributes/edit' }}/${record.id}`, {
                        entity_type: 'leads',
                        services: serviceIds,
                        service_offered: serviceIds,
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: successMessage || response.data.message,
                        });

                        record.service_option_ids = serviceIds.join(',');
                        record.service_offered = this.serviceNamesForIds(serviceIds).join(', ');

                        if (closeAfterSave) {
                            this.closeServiceDropdown();
                        }
                    }).catch(error => {
                        this.serviceDraftIds = {
                            ...this.serviceDraftIds,
                            [record.id]: this.parseServiceIds(record),
                        };

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Update failed.',
                        });
                    });
                },

                serviceNamesForIds(ids) {
                    const selectedIds = ids.map(Number);
                    const otherId = this.otherServiceId();

                    return (this.inlineOptions.service_offered?.items || [])
                        .filter(opt => selectedIds.includes(Number(opt.value)) && Number(opt.value) !== Number(otherId))
                        .map(opt => opt.label);
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

                    if (field === 'user_id') {
                        this.changeSalesOwner(record, value);

                        return;
                    }

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
                            this.selectedFollowupRuleId = '';
                            this.customFollowupDate = '';
                            this.loadSchedulingContext(leadId).then(() => {
                                this.$refs.followupStageModal.open();
                            });

                            return;
                        }

                        if (stageCode === 'meeting') {
                            if (! this.isCallingRoleLeadVariant && Number(record.meeting_activity_count) > 0) {
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

                canChangeSalesOwner(record) {
                    return this.canManageAllLeadOwners
                        || Number(record.user_id || 0) === Number(this.currentUserId || 0);
                },

                setSalesOwnerTriggerRef(leadId, el) {
                    if (el) {
                        this.salesOwnerTriggerRefs[leadId] = el;
                    } else {
                        delete this.salesOwnerTriggerRefs[leadId];
                    }
                },

                salesOwnerLabel(record) {
                    return record.user || record.user_name || '--';
                },

                salesOwnerSelectOptions(record) {
                    const cached = this.salesOwnerOptionsByLead[record.id] || [];
                    const currentOption = {
                        id: Number(record.user_id || 0),
                        value: Number(record.user_id || 0),
                        label: record.user || record.user_name || '--',
                    };

                    const options = currentOption.id ? [currentOption, ...cached] : cached;
                    const seen = new Set();

                    return options.filter(option => {
                        const id = Number(option.id ?? option.value ?? 0);

                        if (! id || seen.has(id)) {
                            return false;
                        }

                        seen.add(id);

                        return true;
                    });
                },

                loadSalesOwnerOptions(record) {
                    if (! this.canChangeSalesOwner(record) || this.salesOwnerOptionsByLead[record.id] || this.salesOwnerOptionsLoading[record.id]) {
                        return Promise.resolve();
                    }

                    this.salesOwnerOptionsLoading = {
                        ...this.salesOwnerOptionsLoading,
                        [record.id]: true,
                    };

                    return this.$axios.get(`{{ lead_url() }}/${record.id}/eligible-sales-owners`)
                        .then(response => {
                            this.salesOwnerOptionsByLead = {
                                ...this.salesOwnerOptionsByLead,
                                [record.id]: response.data.users || [],
                            };
                        })
                        .catch(error => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Unable to load sales owners.',
                            });
                        })
                        .finally(() => {
                            this.salesOwnerOptionsLoading = {
                                ...this.salesOwnerOptionsLoading,
                                [record.id]: false,
                            };
                        });
                },

                toggleSalesOwnerDropdown(record, event) {
                    if (! this.canChangeSalesOwner(record) || this.isStageEditingLocked(record, this.inlineOptions.stage?.items || [])) {
                        return;
                    }

                    if (this.openSalesOwnerLeadId === record.id) {
                        this.closeSalesOwnerDropdown();

                        return;
                    }

                    const trigger = event?.currentTarget || this.salesOwnerTriggerRefs[record.id];

                    if (! trigger) {
                        return;
                    }

                    const rect = trigger.getBoundingClientRect();
                    const dropdownWidth = 320;
                    const panelMax = 280;
                    const spaceBelow = window.innerHeight - rect.bottom - 12;
                    const spaceAbove = rect.top - 12;
                    const openUp = spaceBelow < 180 && spaceAbove > spaceBelow;
                    const left = Math.min(
                        Math.max(8, rect.left),
                        window.innerWidth - dropdownWidth - 8
                    );
                    const top = openUp
                        ? Math.max(8, rect.top - Math.min(panelMax, spaceAbove) - 4)
                        : rect.bottom + 4;

                    this.openSalesOwnerLeadId = record.id;
                    this.openSalesOwnerRecord = record;
                    this.salesOwnerDropdownStyle = {
                        top: `${top}px`,
                        left: `${left}px`,
                        width: `${dropdownWidth}px`,
                        maxHeight: `${panelMax}px`,
                    };

                    this.loadSalesOwnerOptions(record);
                },

                closeSalesOwnerDropdown() {
                    this.openSalesOwnerLeadId = null;
                    this.openSalesOwnerRecord = null;
                    this.salesOwnerDropdownStyle = {};
                },

                selectSalesOwner(record, ownerId) {
                    const owner = this.salesOwnerSelectOptions(record).find(option => {
                        return Number(option.id ?? option.value ?? 0) === Number(ownerId);
                    });

                    this.closeSalesOwnerDropdown();

                    if (owner?.is_closure_user) {
                        this.closureTransferRecord = record;
                        this.closureTransferOwner = owner;
                        this.closureTransferError = '';

                        return;
                    }

                    this.changeSalesOwner(record, Number(ownerId));
                },

                changeSalesOwner(record, ownerId, extraPayload = {}) {
                    const previousOwnerId = Number(record.user_id || 0);

                    if (! ownerId || Number(ownerId) === previousOwnerId) {
                        return;
                    }

                    this.$axios.post(`{{ lead_url() }}/${record.id}/change-sales-owner`, {
                        user_id: ownerId,
                        ...extraPayload,
                    }, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message || 'Sales owner changed successfully.',
                        });

                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || error.response?.data?.errors?.user_id?.[0] || 'Unable to change sales owner.',
                        });

                        this.$refs.datagrid.get();
                    });
                },

                assignClosureDirectly() {
                    if (! this.closureTransferRecord || ! this.closureTransferOwner) {
                        return;
                    }

                    this.isClosureTransferSaving = true;
                    this.closureTransferError = '';

                    this.$axios.post(`{{ lead_url() }}/${this.closureTransferRecord.id}/change-sales-owner`, {
                        user_id: Number(this.closureTransferOwner.id ?? this.closureTransferOwner.value),
                        closure_assignment_mode: 'direct',
                    }, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    }).then(response => {
                        this.$emitter.emit('add-flash', {
                            type: 'success',
                            message: response.data.message || 'Sales owner changed successfully.',
                        });

                        this.cancelClosureTransfer();
                        this.$refs.datagrid.get();
                    }).catch(error => {
                        this.isClosureTransferSaving = false;
                        this.closureTransferError = error.response?.data?.message || error.response?.data?.errors?.user_id?.[0] || 'Unable to change sales owner.';
                    });
                },

                openClosureMeetingTransfer() {
                    if (! this.closureTransferRecord || ! this.closureTransferOwner) {
                        return;
                    }

                    const record = this.closureTransferRecord;
                    const owner = this.closureTransferOwner;

                    this.closureMeetingTransferRecord = record;
                    this.closureMeetingTransferOwner = owner;
                    this.selectedMeetingOwnerId = String(owner.id ?? owner.value);
                    this.pendingStageLeadId = record.id;
                    this.pendingStageId = null;
                    this.meetingErrors = {};
                    this.meetingScheduleFrom = '';
                    this.meetingScheduleTo = '';
                    this.closureTransferError = '';

                    this.loadEligibleMeetingOwners(record.id).then(() => {
                        const ownerId = Number(owner.id ?? owner.value);
                        const hasOwnerOption = this.meetingOwnerOptions.some(user => Number(user.id) === ownerId);

                        if (! hasOwnerOption) {
                            this.meetingOwnerOptions = [
                                {
                                    id: ownerId,
                                    name: owner.name || owner.label || 'Selected owner',
                                    email: owner.email || '',
                                    role_name: owner.role_name || '',
                                    has_working_hours: owner.has_working_hours === true,
                                    working_hour_start_time: owner.working_hour_start_time || null,
                                    working_hour_end_time: owner.working_hour_end_time || null,
                                    working_hour_label: owner.working_hour_label || '',
                                },
                                ...this.meetingOwnerOptions,
                            ];
                        }

                        this.meetingOwnersEmpty = false;
                        this.selectedMeetingOwnerId = String(ownerId);
                        this.cancelClosureTransfer(false);
                        this.$refs.meetingActivityModal.open();
                    });
                },

                cancelClosureTransfer(clearMeetingTransfer = true) {
                    this.closureTransferRecord = null;
                    this.closureTransferOwner = null;
                    this.closureTransferError = '';
                    this.isClosureTransferSaving = false;

                    if (clearMeetingTransfer) {
                        this.closureMeetingTransferRecord = null;
                        this.closureMeetingTransferOwner = null;
                        this.selectedMeetingOwnerId = '';
                    }
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
                    this.editTagIds = [];
                    this.editScheduleFollowup = false;
                    this.editStages = [];

                    this.$refs.editLeadModal.open();

                    this.$axios.get(url)
                        .then(response => {
                            const data = response.data.data || {};

                            this.editLead = data;
                            this.editPerson = data.person || { name: '' };
                            this.editTags = data.tags || [];
                            this.editTagIds = (data.tags || []).map(id => Number(id)).filter(id => ! Number.isNaN(id));
                            this.editScheduleFollowup = Boolean(data.schedule_followup ?? data.next_followup_date);
                            this.editStages = data.stages || [];
                            this.salesOwnerOptions = data.sales_owner_options || this.salesOwnerOptions;
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

                    const editPayload = {
                        ...params,
                        ...organizationPayload,
                        person: personPayload,
                        tags: [...this.editTagIds],
                        schedule_followup: this.editScheduleFollowup ? 1 : 0,
                        next_followup_date: this.editScheduleFollowup
                            ? (params.next_followup_date || null)
                            : null,
                        entity_type: 'leads',
                        quick_add: 1,
                    };

                    this.$axios.put(`{{ lead_url() . '/edit' }}/${this.editLeadId}`, editPayload, {
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
                    if (! this.isOwnerRestrictedLeadVariant) {
                        return false;
                    }

                    if (Number(record.user_id) === Number(this.currentUserId)) {
                        return false;
                    }

                    if (
                        record.forwarded_from_current_user === true
                        || Number(record.forwarded_from_current_user || 0) === 1
                    ) {
                        return true;
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

                    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*(A\.?M\.?|P\.?M\.?))?$/i);

                    if (! match) {
                        return null;
                    }

                    let hour = Number(match[4]);
                    const meridian = match[7]?.replace(/\./g, '').toUpperCase();

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

                formatBackendDateTimeInTimeZone(date, timeZone) {
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

                    return `${parts.year}-${parts.month}-${parts.day} ${parts.hour}:${parts.minute}:${parts.second}`;
                },

                scheduleValueToPakistan(value) {
                    const parts = this.parseScheduleValue(value);
                    const context = {
                        ...this.defaultSchedulingContext(),
                        ...(this.schedulingContext || {}),
                    };
                    const pakistanTimezone = context.pakistan_timezone || 'Asia/Karachi';
                    const inputTimezone = context.customer_timezone || pakistanTimezone;

                    if (! parts) {
                        return value;
                    }

                    const instant = this.dateFromPartsInTimeZone(parts, inputTimezone);

                    return this.formatBackendDateTimeInTimeZone(instant, pakistanTimezone);
                },

                convertMeetingScheduleToPakistan(params) {
                    return {
                        ...params,
                        schedule_from: this.scheduleValueToPakistan(params.schedule_from),
                        schedule_to: this.scheduleValueToPakistan(params.schedule_to),
                    };
                },

                timezonePreview(value) {
                    const parts = this.parseScheduleValue(value);
                    const context = {
                        ...this.defaultSchedulingContext(),
                        ...(this.schedulingContext || {}),
                    };
                    const pakistanTimezone = context.pakistan_timezone || 'Asia/Karachi';
                    const inputTimezone = context.customer_timezone || pakistanTimezone;

                    if (! parts) {
                        return { hasValue: false };
                    }

                    let instant = null;

                    try {
                        instant = this.dateFromPartsInTimeZone(parts, inputTimezone);
                    } catch (error) {
                        instant = this.dateFromPartsInTimeZone(parts, pakistanTimezone);
                    }

                    const location = [context.customer_city, context.customer_state, context.customer_country].filter(Boolean).join(', ');
                    const customer = this.formatInTimeZone(instant, inputTimezone);

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
                    this.selectedMeetingOwnerId = this.closureMeetingTransferOwner
                        ? String(this.closureMeetingTransferOwner.id ?? this.closureMeetingTransferOwner.value)
                        : '';
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
                    const payload = this.convertMeetingScheduleToPakistan(params);

                    if (this.closureMeetingTransferRecord && this.closureMeetingTransferOwner) {
                        this.$axios.post(`{{ lead_url() }}/${this.closureMeetingTransferRecord.id}/change-sales-owner`, {
                            user_id: Number(this.closureMeetingTransferOwner.id ?? this.closureMeetingTransferOwner.value),
                            closure_assignment_mode: 'meeting',
                            meeting: {
                                schedule_from: payload.schedule_from,
                                schedule_to: payload.schedule_to,
                                comment: payload.comment,
                                location: payload.location,
                                participants: payload.participants || {},
                            },
                        }, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        }).then(response => {
                            this.isMeetingSaving = false;
                            this.$refs.meetingActivityModal.close();
                            this.meetingScheduleFrom = '';
                            this.meetingScheduleTo = '';
                            this.selectedMeetingOwnerId = '';
                            this.setSchedulingContext();
                            this.pendingStageLeadId = null;
                            this.pendingStageId = null;
                            this.closureMeetingTransferRecord = null;
                            this.closureMeetingTransferOwner = null;

                            this.$emitter.emit('add-flash', {
                                type: 'success',
                                message: response.data.message || 'Meeting scheduled and sales owner changed successfully.',
                            });
                            this.$refs.datagrid.get();
                        }).catch(error => {
                            this.isMeetingSaving = false;

                            if (error.response?.status === 422) {
                                const errors = error.response.data.errors || {};
                                const validationMessage = Object.values(errors)?.[0]?.[0] || error.response.data.message || 'Meeting could not be saved.';

                                setErrors(errors);
                                this.meetingErrors = {
                                    participants: errors?.participants?.[0],
                                };

                                this.$emitter.emit('add-flash', {
                                    type: 'error',
                                    message: validationMessage,
                                });

                                return;
                            }

                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: error.response?.data?.message || 'Meeting could not be saved.',
                            });
                        });

                        return;
                    }

                    this.$axios.post("{{ route('admin.activities.store') }}", {
                        ...payload,
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
                        this.selectedMeetingOwnerId = '';
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
                            const errors = error.response.data.errors || {};
                            const validationMessage = Object.values(errors)?.[0]?.[0] || error.response.data.message || 'Meeting could not be saved.';

                            setErrors(errors);
                            this.meetingErrors = {
                                participants: errors?.participants?.[0],
                            };

                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: validationMessage,
                            });

                            return;
                        }

                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: error.response?.data?.message || 'Meeting could not be saved.',
                        });
                    });
                },

                applyFollowupStage(mode) {
                    if (mode === 'auto' && ! this.selectedFollowupRuleId) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Please select a follow-up group.',
                        });

                        return;
                    }

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

                    if (mode === 'auto') {
                        payload.followup_rule_group_id = Number(this.selectedFollowupRuleId);
                    }

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
                            this.selectedFollowupRuleId = '';
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
