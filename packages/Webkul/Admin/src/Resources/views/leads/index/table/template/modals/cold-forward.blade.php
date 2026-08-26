            <!-- Cold Lead Forward Modal -->
            <x-admin::modal
                ref="coldLeadForwardModal"
                position="center"
                size="medium"
                @close="clearColdLeadForwardState"
            >
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Forward Cold Lead to SDR
                    </h3>
                </x-slot>

                <x-slot:content>
                    <div class="grid gap-4">
                        <p class="text-sm text-gray-600 dark:text-gray-300">
                            This lead will be forwarded to an SDR. The selected SDR will become both the Lead Owner and Sales Owner.
                        </p>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label class="required">
                                Forward To SDR
                            </x-admin::form.control-group.label>

                            <select
                                class="custom-select w-full rounded border border-gray-200 px-3 py-2.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                v-model="selectedForwardSdrUserId"
                                :disabled="isColdForwardStoring || ! activeSdrUsers.length"
                            >
                                <option value="">Select SDR</option>

                                <option
                                    v-for="user in activeSdrUsers"
                                    :key="'cold-forward-sdr-' + user.id"
                                    :value="user.id"
                                >
                                    @{{ user.name }} (@{{ user.email }})
                                </option>
                            </select>

                            <p
                                class="mt-1 text-xs text-red-600"
                                v-if="coldForwardErrors.sdr_user_id"
                            >
                                @{{ coldForwardErrors.sdr_user_id }}
                            </p>

                            <p
                                class="mt-1 text-xs text-red-600"
                                v-if="! activeSdrUsers.length"
                            >
                                No active SDR users are available.
                            </p>
                        </x-admin::form.control-group>
                    </div>
                </x-slot>

                <x-slot:footer class="gap-2.5">
                    <button
                        type="button"
                        class="secondary-button"
                        @click="closeColdLeadForwardModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="button"
                        class="primary-button"
                        :disabled="isColdForwardStoring || ! selectedForwardSdrUserId || ! activeSdrUsers.length"
                        @click="confirmColdLeadForward"
                    >
                        <template v-if="isColdForwardStoring">
                            Forwarding...
                        </template>

                        <template v-else>
                            Forward Lead
                        </template>
                    </button>
                </x-slot>
            </x-admin::modal>
