<v-us-state-times
    :states='@json($stateTimezones)'
    :is-preview='@json($isPreview ?? false)'
    view-more-url="{{ $viewMoreUrl ?? route('admin.dashboard.us_timezones') }}"
></v-us-state-times>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-us-state-times-template"
    >
        <div class="flex h-full flex-col rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-3 dark:border-gray-800 max-lg:flex-wrap">
                <div class="grid gap-1">
                    <p class="text-lg font-semibold text-gray-800 dark:text-white">
                        USA State Times
                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Live local times. States from 11:00 AM to 4:00 PM are prioritized.
                    </p>
                </div>

                <div class="flex items-center gap-2 max-sm:w-full max-sm:flex-wrap">
                    <div class="relative max-sm:w-full">
                        <input
                            type="text"
                            class="min-h-[37px] w-[260px] rounded-md border border-gray-200 px-3 py-1.5 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 max-sm:w-full"
                            v-model="searchTerm"
                            placeholder="Search any state"
                        />
                    </div>

                    <a
                        v-if="isPreview"
                        :href="viewMoreUrl"
                        class="secondary-button min-h-[37px]"
                    >
                        View More
                    </a>
                </div>
            </div>

            <div class="flex-1 p-3">
                <div
                    v-if="visibleStates.length"
                    class="grid gap-2.5"
                    :class="isPreview ? 'grid-cols-2 max-sm:grid-cols-1' : 'grid-cols-4 max-xl:grid-cols-3 max-lg:grid-cols-2 max-sm:grid-cols-1'"
                >
                    <div
                        v-for="state in visibleStates"
                        :key="state.abbr"
                        class="rounded-md border border-gray-200 p-2.5 transition-all hover:border-brandColor hover:shadow-sm dark:border-gray-800"
                    >
                        <div class="mb-2">
                            <div>
                                <p class="text-sm font-semibold text-gray-800 dark:text-white">
                                    @{{ state.state }}
                                </p>

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    @{{ state.abbr }} · @{{ timezoneLabel(state.timezone) }}
                                </p>
                            </div>
                        </div>

                        <p class="font-semibold text-gray-800 dark:text-white" :class="isPreview ? 'text-xl' : 'text-2xl'">
                            @{{ state.localTime }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @{{ state.localDate }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="rounded-md border border-dashed border-gray-200 p-6 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400"
                >
                    No state found for this search.
                </div>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-us-state-times', {
            template: '#v-us-state-times-template',

            props: {
                states: {
                    type: Array,
                    required: true,
                },

                isPreview: {
                    type: Boolean,
                    default: false,
                },

                viewMoreUrl: {
                    type: String,
                    required: true,
                },
            },

            data() {
                return {
                    searchTerm: '',
                    now: new Date(),
                    timer: null,
                };
            },

            computed: {
                enrichedStates() {
                    return this.states.map((state) => {
                        const dateParts = this.datePartsForTimezone(state.timezone);

                        return {
                            ...state,
                            localTime: this.timeForTimezone(state.timezone),
                            localDate: this.dateForTimezone(state.timezone),
                            localHour: Number(dateParts.hour),
                            localMinute: Number(dateParts.minute),
                        };
                    });
                },

                filteredStates() {
                    const query = this.searchTerm.trim().toLowerCase();

                    if (! query) {
                        return this.enrichedStates;
                    }

                    return this.enrichedStates.filter((state) => {
                        return state.state.toLowerCase().includes(query)
                            || state.abbr.toLowerCase().includes(query)
                            || this.timezoneLabel(state.timezone).toLowerCase().includes(query);
                    });
                },

                visibleStates() {
                    if (this.searchTerm.trim() && ! this.isPreview) {
                        return this.filteredStates;
                    }

                    if (this.searchTerm.trim()) {
                        return this.filteredStates.slice(0, 8);
                    }

                    const sorted = [...this.filteredStates].sort((first, second) => {
                        if (this.isPriorityTime(first) !== this.isPriorityTime(second)) {
                            return this.isPriorityTime(first) ? -1 : 1;
                        }

                        if (first.popular !== second.popular) {
                            return first.popular ? -1 : 1;
                        }

                        return first.state.localeCompare(second.state);
                    });

                    if (! this.isPreview) {
                        return sorted;
                    }

                    const popularPriority = sorted.filter((state) => state.popular && this.isPriorityTime(state));
                    const otherPriority = sorted.filter((state) => ! state.popular && this.isPriorityTime(state));
                    const popularFallback = sorted.filter((state) => state.popular && ! this.isPriorityTime(state));

                    return [...popularPriority, ...otherPriority, ...popularFallback].slice(0, 8);
                },
            },

            mounted() {
                this.timer = setInterval(() => {
                    this.now = new Date();
                }, 1000);
            },

            beforeUnmount() {
                clearInterval(this.timer);
            },

            methods: {
                isPriorityTime(state) {
                    const localMinutes = (state.localHour * 60) + state.localMinute;

                    return localMinutes >= (11 * 60) && localMinutes <= (16 * 60);
                },

                timezoneLabel(timezone) {
                    return timezone.replace('America/', '').replace('Pacific/', '').replaceAll('_', ' ');
                },

                timeForTimezone(timezone) {
                    return new Intl.DateTimeFormat('en-US', {
                        hour: 'numeric',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: true,
                        timeZone: timezone,
                    }).format(this.now);
                },

                dateForTimezone(timezone) {
                    return new Intl.DateTimeFormat('en-US', {
                        weekday: 'short',
                        month: 'short',
                        day: 'numeric',
                        timeZone: timezone,
                    }).format(this.now);
                },

                datePartsForTimezone(timezone) {
                    return Object.fromEntries(new Intl.DateTimeFormat('en-US', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hourCycle: 'h23',
                        timeZone: timezone,
                    }).formatToParts(this.now).map((part) => [part.type, part.value]));
                },
            },
        });
    </script>
@endPushOnce
