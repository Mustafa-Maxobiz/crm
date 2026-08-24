            <!-- Follow-up Schedule Modal -->
            <x-admin::modal
                ref="followupStageModal"
                position="center"
                @toggle="handleFollowupModalToggle"
            >
                <x-slot:header>
                    <h3 class="text-base font-semibold dark:text-white">
                        Schedule Follow-up
                    </h3>
                </x-slot>

                <x-slot:content>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                        Choose how to set the next follow-up for this lead.
                    </p>

                    <div
                        class="mb-4"
                        v-if="followupMode === 'custom'"
                    >
                        <label class="mb-1.5 block text-sm font-medium text-gray-800 dark:text-white">
                            Next Follow-up Date <span class="text-red-500">*</span>
                        </label>

                        <input
                            type="datetime-local"
                            v-model="customFollowupDate"
                            class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 outline-none focus:border-brandColor dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >

                        @include('admin::leads.components.scheduling-time-preview', [
                            'value' => 'customFollowupDate',
                            'label' => 'Next Follow-up Preview',
                        ])
                    </div>
                </x-slot>

                <x-slot:footer>
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <button
                            type="button"
                            class="transparent-button"
                            :disabled="isFollowupSaving"
                            @click="applyFollowupStage('auto')"
                        >
                            Use Auto
                        </button>

                        <button
                            type="button"
                            class="secondary-button"
                            :disabled="isFollowupSaving"
                            @click="followupMode === 'custom' ? applyFollowupStage('custom') : (followupMode = 'custom')"
                        >
                            @{{ followupMode === 'custom' ? 'Save Custom' : 'Custom' }}
                        </button>
                    </div>
                </x-slot>
            </x-admin::modal>
