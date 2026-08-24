            <!-- Meeting Activity Modal -->
            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="meetingModalForm"
            >
                <form @submit="handleSubmit($event, saveMeetingAndMove)">
                    <x-admin::modal
                        ref="meetingActivityModal"
                        position="center"
                        size="medium"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                Add Meeting
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <div class="grid gap-4">
                                <div class="flex gap-4 max-sm:flex-wrap">
                                    <x-admin::form.control-group class="w-full">
                                        <x-admin::form.control-group.label class="required">
                                            Schedule From
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="datetime"
                                            name="schedule_from"
                                            v-model="meetingScheduleFrom"
                                            rules="required"
                                            label="Schedule From"
                                        />

                                        <x-admin::form.control-group.error control-name="schedule_from" />
                                    </x-admin::form.control-group>

                                    <x-admin::form.control-group class="w-full">
                                        <x-admin::form.control-group.label class="required">
                                            Schedule To
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="datetime"
                                            name="schedule_to"
                                            v-model="meetingScheduleTo"
                                            rules="required|after_datetime:@schedule_from"
                                            label="Schedule To"
                                        />

                                        <x-admin::form.control-group.error control-name="schedule_to" />
                                    </x-admin::form.control-group>
                                </div>

                                <div class="grid gap-3 md:grid-cols-2">
                                    @include('admin::leads.components.scheduling-time-preview', [
                                        'value' => 'meetingScheduleFrom',
                                        'label' => 'Meeting Start Preview',
                                    ])

                                    @include('admin::leads.components.scheduling-time-preview', [
                                        'value' => 'meetingScheduleTo',
                                        'label' => 'Meeting End Preview',
                                    ])
                                </div>

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label class="required">
                                        Comment
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="comment"
                                        rules="required|max:500"
                                        label="Comment"
                                    />

                                    <x-admin::form.control-group.error control-name="comment" />
                                </x-admin::form.control-group>

                                @if ($isCallingRoleLeadVariant)
                                    <x-admin::form.control-group>
                                        <x-admin::form.control-group.label class="required">
                                            Assigned Owner
                                        </x-admin::form.control-group.label>

                                        <x-admin::form.control-group.control
                                            type="select"
                                            name="assigned_user_id"
                                            rules="required"
                                            label="Assigned Owner"
                                            ::disabled="meetingOwnersLoading || meetingOwnersEmpty"
                                        >
                                            <option value="">
                                                @{{ meetingOwnersLoading ? 'Loading eligible owners...' : 'Select Admin / Lead User' }}
                                            </option>

                                            <option
                                                v-for="user in meetingOwnerOptions"
                                                :key="'meeting-owner-' + user.id"
                                                :value="user.id"
                                            >
                                                @{{ user.name }}<template v-if="user.role_name"> - @{{ user.role_name }}</template><template v-if="user.email"> (@{{ user.email }})</template>
                                            </option>
                                        </x-admin::form.control-group.control>

                                        <p
                                            v-if="meetingOwnersEmpty && ! meetingOwnersLoading"
                                            class="mt-1 text-xs text-red-600"
                                        >
                                            No Lead Closers/Admin users are assigned to the selected Services Offered. Please contact an administrator.
                                        </p>

                                        <x-admin::form.control-group.error control-name="assigned_user_id" />
                                    </x-admin::form.control-group>
                                @endif

                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        Participants
                                    </x-admin::form.control-group.label>

                                    <v-activity-participants
                                        :participants="defaultMeetingParticipants"
                                        :show-all-users="true"
                                        :users-only="true"
                                        :user-role-names="['administrator', 'lead', 'lead clouser', 'lead closer']"
                                    ></v-activity-participants>

                                    <p
                                        class="mt-1 text-xs text-red-600"
                                        v-if="meetingErrors.participants"
                                    >
                                        @{{ meetingErrors.participants }}
                                    </p>
                                </x-admin::form.control-group>

                                <x-admin::form.control-group class="!mb-0">
                                    <x-admin::form.control-group.label class="required">
                                        Meeting Channel
                                    </x-admin::form.control-group.label>

                                    <x-admin::form.control-group.control
                                        type="text"
                                        name="location"
                                        rules="required"
                                        label="Meeting Channel"
                                    />

                                    <x-admin::form.control-group.error control-name="location" />
                                </x-admin::form.control-group>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <x-admin::button
                                button-type="submit"
                                class="primary-button"
                                title="Save Meeting"
                                ::loading="isMeetingSaving"
                                ::disabled="isMeetingSaving || meetingOwnersLoading || meetingOwnersEmpty"
                            />
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
