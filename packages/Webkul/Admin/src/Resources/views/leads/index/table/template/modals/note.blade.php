            <!-- Add Note Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="noteModalForm"
            >
                <form @submit="handleSubmit($event, saveNote)">
                    <x-admin::modal
                        ref="noteLeadModal"
                        position="center"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.index.modals.note-title')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="type"
                                value="note"
                            />

                            <x-admin::form.control-group.control
                                type="hidden"
                                name="lead_id"
                                ::value="noteLeadId"
                            />

                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label class="required">
                                    @lang('admin::app.leads.index.modals.note-comment')
                                </x-admin::form.control-group.label>

                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="comment"
                                    rules="required"
                                    :label="trans('admin::app.leads.index.modals.note-comment')"
                                />

                                <x-admin::form.control-group.error control-name="comment" />
                            </x-admin::form.control-group>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                :title="trans('admin::app.leads.index.modals.note-save-btn')"
                                ::loading="isNoteSaving"
                                ::disabled="isNoteSaving"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
