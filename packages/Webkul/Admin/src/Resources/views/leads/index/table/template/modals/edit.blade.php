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

                                <input
                                    type="hidden"
                                    name="schedule_followup"
                                    :value="editScheduleFollowup ? 1 : 0"
                                />

                                <x-admin::form.control-group>
                                    <label class="flex cursor-pointer items-start gap-2 rounded border border-gray-200 p-3 text-sm dark:border-gray-800">
                                        <input
                                            type="checkbox"
                                            v-model="editScheduleFollowup"
                                            class="mt-1 rounded border-gray-300 text-brandColor focus:ring-brandColor dark:border-gray-600 dark:bg-gray-900"
                                        />

                                        <span class="flex flex-col gap-1">
                                            <span class="font-medium text-gray-800 dark:text-white">
                                                @lang('admin::app.leads.create.schedule-followup')
                                            </span>

                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                @lang('admin::app.leads.create.schedule-followup-help')
                                            </span>
                                        </span>
                                    </label>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group v-if="editScheduleFollowup">
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.create.next-followup-date')
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="datetime"
                                        name="next_followup_date"
                                        ::value="editLead.next_followup_date"
                                        :label="trans('admin::app.leads.create.next-followup-date')"
                                        :placeholder="trans('admin::app.leads.create.next-followup-date')"
                                    />

                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @lang('admin::app.leads.create.next-followup-date-help')
                                    </p>

                                    <x-admin::form.control-group.error control-name="next_followup_date" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Tags
                                    </x-admin::form.control-group.label>

                                    <x-admin::attributes.edit.multiselect
                                        :attribute="(object) ['code' => 'tags', 'name' => 'Tags', 'lookup_type' => null]"
                                        :options="$editTagOptions"
                                        selected-ids-binding="editTagIds"
                                        validations=""
                                        :can-add-new="false"
                                    />

                                    <x-admin::form.control-group.error control-name="tags" />
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
                                    @person-updated="editPerson = $event"
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
