            <!-- LGE Meeting Owner Handoff Modal -->
            <x-admin::modal
                ref="lgeSdrHandoffModal"
                position="center"
            >
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Assign Admin/Lead Owner
                    </h3>
                </x-slot>

                <x-slot:content>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                        Select the Admin or Lead user who will own this lead after the meeting.
                    </p>

                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label class="required">
                            Admin / Lead User
                        </x-admin::form.control-group.label>

                        <select
                            v-model="pendingHandoffSdrUserId"
                            class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                            :disabled="meetingOwnersLoading || meetingOwnersEmpty"
                        >
                            <option value="">
                                @{{ meetingOwnersLoading ? 'Loading eligible owners...' : 'Select Admin / Lead User' }}
                            </option>

                            <option
                                v-for="user in meetingOwnerOptions"
                                :key="'handoff-owner-' + user.id"
                                :value="user.id"
                            >
                                @{{ user.name }} <template v-if="user.role_name">- @{{ user.role_name }}</template> <template v-if="user.email">(@{{ user.email }})</template>
                            </option>
                        </select>

                        <p
                            v-if="meetingOwnersEmpty && ! meetingOwnersLoading"
                            class="mt-2 text-xs text-red-600"
                        >
                            No Lead Closers/Admin users are assigned to the selected Services Offered. Please contact an administrator.
                        </p>
                    </x-admin::form.control-group>
                </x-slot>

                <x-slot:footer>
                    <button
                        type="button"
                        class="primary-button"
                        :disabled="isHandoffSaving || meetingOwnersLoading || meetingOwnersEmpty"
                        @click="applyLgeSdrHandoff"
                    >
                        <template v-if="isHandoffSaving">Saving...</template>
                        <template v-else>Assign & Move</template>
                    </button>
                </x-slot>
            </x-admin::modal>
