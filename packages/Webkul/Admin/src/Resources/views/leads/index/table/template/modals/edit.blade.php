            <!-- Edit Lead Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="editModalForm"
            >
                <form @submit="handleSubmit($event, saveLead)">
                    <x-admin::modal
                        ref="editLeadModal"
                        position="center"
                        size="large"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.index.modals.edit-title')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <div
                                class="py-8 text-center text-gray-500"
                                v-if="isEditLoading"
                            >
                                @lang('admin::app.leads.index.modals.loading')
                            </div>

                            <div
                                class="flex max-h-[60vh] flex-col gap-4 overflow-y-auto overflow-x-visible pb-4"
                                v-if="! isEditLoading"
                                :key="'edit-fields-' + editLeadId"
                            >
                                <x-admin::form.control-group.control
                                    type="hidden"
                                    name="entity_type"
                                    value="leads"
                                />

                                <x-admin::form.control-group.control
                                    type="hidden"
                                    name="quick_add"
                                    value="1"
                                />

                                @if ($showTitleInEditModal)
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            @lang('admin::app.leads.create.title-field')
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="text"
                                            name="title"
                                            rules="required"
                                            ::value="editLead.title"
                                            :label="trans('admin::app.leads.create.title-field')"
                                            :placeholder="trans('admin::app.leads.create.title-field')"
                                        />

                                        <x-admin::form.control-group.error control-name="title" />
                                    </x-admin::form.control-group>
                                @endif

                                <x-admin::attributes
                                    :custom-attributes="$leadQuickAttributes"
                                    :disabled-attribute-codes="$lockedLeadAttributeCodes"
                                />

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.index.datagrid.lead-type')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="select"
                                        name="lead_type_id"
                                        ::value="editLead.lead_type_id"
                                        :label="trans('admin::app.leads.index.datagrid.lead-type')"
                                        disabled
                                        class="cursor-not-allowed opacity-70"
                                    >
                                        <option value="">
                                            @lang('admin::app.leads.index.datagrid.lead-type')
                                        </option>
                                        <option
                                            v-for="type in leadTypeOptions"
                                            :key="type.id"
                                            :value="String(type.id)"
                                        >
                                            @{{ type.name }}
                                        </option>
                                    </x-admin::form.control-group.control>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Sales Owner
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="select"
                                        name="user_id"
                                        ::value="editLead.user_id"
                                        label="Sales Owner"
                                    >
                                        <option value="">
                                            Sales Owner
                                        </option>
                                        <option
                                            v-for="owner in salesOwnerOptions"
                                            :key="owner.id"
                                            :value="String(owner.id)"
                                        >
                                            @{{ owner.name }}
                                        </option>
                                    </x-admin::form.control-group.control>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Pipeline
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="select"
                                        name="lead_pipeline_id"
                                        ::value="editLead.lead_pipeline_id"
                                        label="Pipeline"
                                    >
                                        <option value="">
                                            Pipeline
                                        </option>
                                        <option
                                            v-for="pipelineOption in pipelineOptions"
                                            :key="pipelineOption.id"
                                            :value="String(pipelineOption.id)"
                                        >
                                            @{{ pipelineOption.name }}
                                        </option>
                                    </x-admin::form.control-group.control>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.index.datagrid.stage')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="select"
                                        name="lead_pipeline_stage_id"
                                        ::value="editLead.lead_pipeline_stage_id"
                                        :label="trans('admin::app.leads.index.datagrid.stage')"
                                    >
                                        <option value="">
                                            @lang('admin::app.leads.index.datagrid.stage')
                                        </option>
                                        <option
                                            v-for="stage in editStages"
                                            :key="stage.id"
                                            :value="String(stage.id)"
                                        >
                                            @{{ stage.name }}
                                        </option>
                                    </x-admin::form.control-group.control>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.index.datagrid.next-followup-date')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="datetime"
                                        name="next_followup_date"
                                        ::value="editLead.next_followup_date"
                                        :label="trans('admin::app.leads.index.datagrid.next-followup-date')"
                                    />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.components.tags.index.title')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="tags"
                                        name="tags"
                                        label="Tags"
                                        :placeholder="trans('admin::app.components.tags.index.placeholder')"
                                        ::data="editTags"
                                        input-rules="max:100"
                                        :allow-duplicates="false"
                                        suggestions-endpoint="{{ route('admin.settings.tags.search') }}"
                                    />
                                </x-admin::form.control-group>

                                <div class="flex flex-col gap-1 border-t border-gray-200 pt-4 dark:border-gray-800">
                                    <p class="text-base font-semibold dark:text-white">
                                        @lang('admin::app.leads.edit.contact-person')
                                    </p>
                                </div>

                                <v-contact-component
                                    ref="editContact"
                                    v-if="editLeadId && ! isEditLoading"
                                    :key="'contact-' + editLeadId + '-' + (editPerson.id || 'new')"
                                    :data="editPerson"
                                    :can-edit-contact-details='@json(! app(\Webkul\Lead\Services\SourceAccessService::class)->isSdrUser())'
                                    :can-edit-company='@json(
                                        ! app(\Webkul\Lead\Services\SourceAccessService::class)->isSdrUser()
                                            && (
                                                bouncer()->hasPermission("contacts.organizations.edit")
                                                || bouncer()->hasPermission("contacts.organizations.create")
                                            )
                                    )'
                                ></v-contact-component>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                :title="trans('admin::app.leads.index.modals.edit-save-btn')"
                                ::loading="isEditSaving"
                                ::disabled="isEditSaving || isEditLoading"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
