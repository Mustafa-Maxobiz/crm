@props([
    'attachEndpoint',
    'detachEndpoint',
    'addedTags' => [],
    'leadContext' => null,
])

@php
    $canManageTags = auth()->guard('user')->user()?->role?->permission_type === 'all';
@endphp

<v-tags
    attach-endpoint="{{ $attachEndpoint }}"
    detach-endpoint="{{ $detachEndpoint }}"
    :added-tags='@json($addedTags)'
    :lead-context='@json($leadContext)'
    :can-manage-tags='@json((bool) $canManageTags)'
>
    <x-admin::shimmer.tags count="3" />
</v-tags>

@pushOnce('scripts')
    <script type="text/x-template" id="v-tags-template">
        <div class="flex flex-wrap items-center gap-1">
            <!-- Tags -->
            <span
                class="flex items-center gap-1 break-all rounded-md bg-rose-100 px-3 py-1.5 text-xs font-medium"
                :style="{
                    'background-color': tag.color,
                    'color': backgroundColors.find(color => color.background === tag.color)?.text
                }"
                v-for="(tag, index) in tags"
                v-safe-html="tag.name"
            >
            </span>

            <!-- Add Button -->
            <x-admin::dropdown
                ::close-on-click="false"
                position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'right' : 'left' }}"
            >
                <x-slot:toggle>
                    <button class="icon-settings-tag rounded-md p-1 text-xl transition-all hover:bg-gray-200 dark:hover:bg-gray-950"></button>
                </x-slot>

                <x-slot:content class="!p-0">
                    <!-- Dropdown Container !-->
                    <div class="flex flex-col gap-2">
                        <!-- Search Input -->
                        <div class="flex flex-col gap-1 px-4 py-2">
                            <label class="font-semibold text-gray-600 dark:text-gray-300">
                                @lang('admin::app.components.tags.index.title')
                            </label>

                            <!-- Search Button -->
                            <div class="relative">
                                <div class="relative rounded border border-gray-200 p-2 hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:hover:border-gray-400 dark:focus:border-gray-400" role="button">
                                    <input
                                        type="text"
                                        class="w-full cursor-pointer pr-6 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="@lang('admin::app.components.tags.index.placeholder')"
                                        v-model="searchTerm"
                                        @focus="loadAvailableTags"
                                    />

                                    <template v-if="! isSearching">
                                        <span
                                            class="absolute right-1.5 top-1.5 text-2xl"
                                            :class="[showTagOptions ? 'icon-up-arrow' : 'icon-down-arrow']"
                                        ></span>
                                    </template>

                                    <template v-else>
                                        <x-admin::spinner class="absolute right-2 top-2" />
                                    </template>
                                </div>

                                <!-- Search Tags Dropdown -->
                                <div
                                    class="absolute z-10 w-full rounded bg-white shadow-[0px_10px_20px_0px_#0000001F] dark:bg-gray-800"
                                    v-if="showTagOptions"
                                >
                                    <ul class="max-h-60 overflow-y-auto p-2">
                                        <li
                                            class="cursor-pointer break-all rounded-sm px-5 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                                            v-for="tag in filteredAvailableTags"
                                            @click="attachToEntity(tag)"
                                        >
                                            @{{ tag.name }}
                                        </li>

                                        <template v-if="! filteredAvailableTags.length && ! isSearching">
                                            <li class="rounded-sm px-5 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                @lang('admin::app.components.datagrid.table.no-records-available')
                                            </li>

                                            <li
                                                v-if="canManageTags && searchTerm.length >= 2"
                                                class="cursor-pointer rounded-sm bg-gray-100 px-5 py-2 text-sm text-gray-800 dark:bg-gray-950 dark:text-white"
                                                @click="create"
                                            >
                                                @{{ `@lang('admin::app.components.tags.index.add-tag', ['term' => 'replaceTerm'])`.replace('replaceTerm', searchTerm) }}
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Tags -->
                        <div
                            class="flex flex-col gap-2 px-4 py-1.5"
                            v-if="tags.length"
                        >
                            <label class="text-gray-600 dark:text-gray-300">
                                @lang('admin::app.components.tags.index.added-tags')
                            </label>

                            <!-- Added Tags List -->
                            <ul class="flex flex-col">
                                <template v-for="tag in tags">
                                    <li
                                        class="flex items-center justify-between gap-2.5 rounded-sm p-2 text-sm text-gray-800 dark:text-white"
                                        v-if="tag.id"
                                    >
                                        <!-- Name -->
                                        <span
                                            class="break-all rounded-md bg-rose-100 px-3 py-1.5 text-xs font-medium"
                                            :style="{
                                                'background-color': tag.color,
                                                'color': backgroundColors.find(color => color.background === tag.color)?.text
                                            }"
                                        >
                                            @{{ tag.name }}
                                        </span>

                                        <!-- Action -->
                                        <div class="flex items-center gap-1">
                                            <x-admin::dropdown
                                                v-if="canManageTags"
                                                position="bottom-right"
                                            >
                                                <x-slot:toggle>
                                                    <button class="flex cursor-pointer items-center gap-1 rounded border border-gray-200 px-2 py-0.5 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:hover:border-gray-400 dark:focus:border-gray-400">
                                                        <span
                                                            class="h-4 w-4 break-all rounded-full"
                                                            :style="'background-color: ' + (tag.color ? tag.color : '#546E7A')"
                                                        >
                                                        </span>

                                                        <span class="icon-down-arrow text-xl"></span>
                                                    </button>
                                                </x-slot>

                                                <x-slot:menu class="!top-7 !p-0">
                                                    <x-admin::dropdown.menu.item
                                                        class="top-5 flex gap-2"
                                                        ::class="{ 'bg-gray-100 dark:bg-gray-950': tag.color === color.background }"
                                                        v-for="color in backgroundColors"
                                                        @click="update(tag, color)"
                                                    >
                                                        <span
                                                            class="flex h-4 w-4 break-all rounded-full"
                                                            :style="'background-color: ' + color.background"
                                                        >
                                                        </span>

                                                        @{{ color.label }}
                                                    </x-admin::dropdown.menu.item>
                                                </x-slot>
                                            </x-admin::dropdown>

                                            <div class="flex items-center">
                                                <span
                                                    class="icon-cross-large flex cursor-pointer rounded-md p-1 text-xl text-gray-600 transition-all hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-800"
                                                    v-show="! isRemoving[tag.id]"
                                                    @click="detachFromEntity(tag)"
                                                ></span>

                                                <span
                                                    class="p-1"
                                                    v-show="isRemoving[tag.id]"
                                                >
                                                    <x-admin::spinner />
                                                </span>
                                            </div>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </x-slot>
            </x-admin::dropdown>

            <x-admin::form
                v-if="leadContext"
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="notAnswerActivityForm"
            >
                <form @submit="handleSubmit($event, saveNotAnswerActivity)">
                    <x-admin::modal
                        ref="notAnswerActivityModal"
                        position="center"
                        size="medium"
                    >
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                Add Not Answer Call
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
                                            rules="required|after_datetime:@schedule_from"
                                            label="Schedule To"
                                        />

                                        <x-admin::form.control-group.error control-name="schedule_to" />
                                    </x-admin::form.control-group>
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
                                        value="Call attempted, no answer."
                                    />

                                    <x-admin::form.control-group.error control-name="comment" />
                                </x-admin::form.control-group>

                                <x-admin::form.control-group class="!mb-0">
                                    <x-admin::form.control-group.label class="required">
                                        Participants
                                    </x-admin::form.control-group.label>

                                    <v-activity-participants :participants="defaultNotAnswerParticipants"></v-activity-participants>

                                    <p
                                        class="mt-1 text-xs text-red-600"
                                        v-if="notAnswerErrors.participants"
                                    >
                                        @{{ notAnswerErrors.participants }}
                                    </p>
                                </x-admin::form.control-group>
                            </div>
                        </x-slot>

                        <x-slot:footer>
                            <button
                                type="button"
                                class="secondary-button"
                                @click="$refs.notAnswerActivityModal.close()"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                class="primary-button"
                                :disabled="isNotAnswerStoring"
                            >
                                <template v-if="isNotAnswerStoring">
                                    Saving...
                                </template>

                                <template v-else>
                                    Save Not Answer
                                </template>
                            </button>
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </div>
    </script>

    <script type="module">
        app.component('v-tags', {
            template: '#v-tags-template',

            props: {
                attachEndpoint: {
                    type: String,
                    default: '',
                },

                detachEndpoint: {
                    type: String,
                    default: '',
                },

                addedTags: {
                    type: Array,
                    default: () => [],
                },

                leadContext: {
                    type: Object,
                    default: null,
                },

                canManageTags: {
                    type: Boolean,
                    default: false,
                },
            },

            data: function () {
                return {
                    searchTerm: '',

                    isStoring: false,

                    isSearching: false,

                    showTagOptions: false,

                    isRemoving: {},

                    isNotAnswerStoring: false,

                    pendingNotAnswerTag: null,

                    notAnswerErrors: {},

                    tags: [],

                    availableTags: [],

                    backgroundColors: [
                        {
                            label: "@lang('admin::app.components.tags.index.aquarelle-red')",
                            text: '#DC2626',
                            background: '#FEE2E2',
                        }, {
                            label: "@lang('admin::app.components.tags.index.crushed-cashew')",
                            text: '#EA580C',
                            background: '#FFEDD5',
                        }, {
                            label: "@lang('admin::app.components.tags.index.beeswax')",
                            text: '#D97706',
                            background: '#FEF3C7',
                        }, {
                            label: "@lang('admin::app.components.tags.index.lemon-chiffon')",
                            text: '#CA8A04',
                            background: '#FEF9C3',
                        }, {
                            label: "@lang('admin::app.components.tags.index.snow-flurry')",
                            text: '#65A30D',
                            background: '#ECFCCB',
                        }, {
                            label: "@lang('admin::app.components.tags.index.honeydew')",
                            text: '#16A34A',
                            background: '#DCFCE7',
                        },
                    ],
                }
            },

            computed: {
                defaultNotAnswerParticipants() {
                    return this.leadContext?.participants || {
                        users: [],
                        persons: [],
                    };
                },

                filteredAvailableTags() {
                    const term = (this.searchTerm || '').trim().toLowerCase();
                    const addedIds = this.tags.map(tag => Number(tag.id));

                    return this.availableTags.filter(tag => {
                        if (addedIds.includes(Number(tag.id))) {
                            return false;
                        }

                        if (! term) {
                            return true;
                        }

                        return (tag.name || '').toLowerCase().includes(term);
                    });
                },
            },

            watch: {
                searchTerm() {
                    this.showTagOptions = true;
                    this.loadAvailableTags();
                },
            },

            mounted() {
                this.tags = this.addedTags;
                this.loadAvailableTags();
            },

            methods: {
                openModal(type) {
                    this.$refs.mailActivityModal.open();
                },

                loadAvailableTags() {
                    this.showTagOptions = true;

                    if (this.availableTags.length || this.isSearching) {
                        return;
                    }

                    this.isSearching = true;

                    this.$axios.get("{{ route('admin.settings.tags.search') }}")
                        .then(response => {
                            this.availableTags = response.data.data || [];
                            this.isSearching = false;
                        })
                        .catch(() => {
                            this.isSearching = false;
                        });
                },

                create() {
                    if (! this.canManageTags) {
                        return;
                    }

                    this.isStoring = true;

                    var self = this;

                    this.$axios.post("{{ route('admin.settings.tags.store') }}", {
                        name: this.searchTerm,
                        color: this.backgroundColors[Math.floor(Math.random() * this.backgroundColors.length)].background,
                    })
                        .then(response => {
                            self.availableTags.push(response.data.data);
                            self.attachToEntity(response.data.data);
                        })
                        .catch(error => {
                            self.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                            self.isStoring = false;
                        });
                },

                update(tag, color) {
                    if (! this.canManageTags) {
                        return;
                    }

                    var self = this;

                    this.$axios.put("{{ route('admin.settings.tags.update', 'replaceTagId') }}".replace('replaceTagId', tag.id), {
                        name: tag.name,
                        color: color.background,
                    })
                        .then(response => {
                            tag.color = color.background;

                            self.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            self.refreshAfterTagChange();
                        })
                        .catch(error => {
                            self.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                },

                attachToEntity(params) {
                    this.isStoring = true;

                    var self = this;

                    this.$axios.post(this.attachEndpoint, {
                        tag_id: params.id,
                    })
                        .then(response => {
                            self.searchTerm = '';
                            self.showTagOptions = false;
                            self.isStoring = false;

                            self.removeDetachedTags(response.data.detached_tag_ids || []);

                            if (! self.tags.some(tag => tag.id === params.id)) {
                                self.tags.push(params);
                            }

                            if (response.data.data) {
                                self.$emitter.emit('on-activity-added', response.data.data);
                                self.$emitter.emit('activity-created');
                            }

                            self.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            self.refreshAfterTagChange();
                        })
                        .catch(error => {
                            if (
                                error.response?.status === 409
                                && error.response?.data?.requires_call_activity
                                && this.isNotAnswerTag(params)
                            ) {
                                self.pendingNotAnswerTag = params;
                                self.notAnswerErrors = {};
                                self.isStoring = false;
                                self.$refs.notAnswerActivityModal.open();

                                return;
                            }

                            self.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                            self.isStoring = false;
                        });
                },

                saveNotAnswerActivity(params) {
                    if (! this.hasParticipants(params.participants || {})) {
                        this.notAnswerErrors = {
                            participants: 'Please select at least one participant.',
                        };

                        return;
                    }

                    this.notAnswerErrors = {};
                    this.isNotAnswerStoring = true;

                    var self = this;

                    this.$axios.post(this.attachEndpoint, {
                        ...params,
                        tag_id: this.pendingNotAnswerTag.id,
                    })
                        .then(response => {
                            self.searchTerm = '';
                            self.showTagOptions = false;
                            self.isNotAnswerStoring = false;

                            self.removeDetachedTags(response.data.detached_tag_ids || []);

                            if (! self.tags.some(tag => tag.id === self.pendingNotAnswerTag.id)) {
                                self.tags.push(self.pendingNotAnswerTag);
                            }

                            self.pendingNotAnswerTag = null;
                            self.$refs.notAnswerActivityModal.close();

                            if (response.data.data) {
                                self.$emitter.emit('on-activity-added', response.data.data);
                            }

                            self.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                            self.$emitter.emit('activity-created');

                            self.refreshAfterTagChange();
                        })
                        .catch(error => {
                            self.isNotAnswerStoring = false;

                            if (error.response?.status === 422) {
                                setErrors(error.response.data.errors || {});

                                self.notAnswerErrors = {
                                    participants: error.response.data.errors?.participants?.[0],
                                };

                                return;
                            }

                            self.$emitter.emit('add-flash', { type: 'error', message: error.response?.data?.message || 'Not Answer call could not be saved.' });
                        });
                },

                isNotAnswerTag(tag) {
                    return (tag.name || '').trim().toLowerCase() === 'not answer';
                },

                removeDetachedTags(tagIds) {
                    if (! tagIds.length) {
                        return;
                    }

                    const detachedIds = tagIds.map(tagId => Number(tagId));

                    this.tags = this.tags.filter(tag => ! detachedIds.includes(Number(tag.id)));
                },

                refreshAfterTagChange() {
                    setTimeout(() => window.location.reload(), 250);
                },

                hasParticipants(participants) {
                    return ['users', 'persons'].some(type => {
                        return (participants[type] || []).some(participantId => !! participantId);
                    });
                },

                detachFromEntity(tag) {
                    var self = this;

                    this.$emitter.emit('open-confirm-modal', {
                        agree: () => {
                            this.isRemoving[tag.id] = true;

                            this.$axios.delete(this.detachEndpoint, {
                                    data: {
                                        tag_id: tag.id,
                                    }
                                })
                                .then(response => {
                                    self.isRemoving[tag.id] = false;

                                    const index = self.tags.indexOf(tag);

                                    self.tags.splice(index, 1);

                                    self.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                                    self.refreshAfterTagChange();
                                })
                                .catch(error => {
                                    self.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                                    self.isRemoving[tag.id] = false;
                                });
                        },
                    });
                },
            },
        });
    </script>
@endPushOnce
