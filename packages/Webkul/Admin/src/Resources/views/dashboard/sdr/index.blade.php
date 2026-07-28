<x-admin::layouts>
    <x-slot:title>
        SDR Dashboard
    </x-slot>

    {!! view_render_event('admin.dashboard.sdr.index.header.before') !!}

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                SDR Dashboard
            </p>
        </div>
    </div>

    {!! view_render_event('admin.dashboard.sdr.index.header.after') !!}

    {!! view_render_event('admin.dashboard.sdr.index.content.before') !!}

    <div class="sdr-dashboard-split mt-4 grid grid-cols-2 gap-4 max-xl:grid-cols-1">
        <div class="sdr-dashboard-left-scroll min-w-0">
            <v-sdr-lead-sections
                sections-url="{{ route('admin.dashboard.lead_sections') }}"
            >
                <div class="light-shimmer-bg dark:shimmer h-[620px] rounded-lg"></div>
            </v-sdr-lead-sections>
        </div>

        <div class="sdr-dashboard-right-static grid min-w-0 content-start gap-4">
            <v-sdr-today-summary
                sections-url="{{ route('admin.dashboard.lead_sections') }}"
            >
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-3 grid gap-1">
                        <div class="light-shimmer-bg dark:shimmer h-5 w-40 rounded-md"></div>
                        <div class="light-shimmer-bg dark:shimmer h-4 w-56 rounded-md"></div>
                    </div>

                    <div class="grid grid-cols-3 gap-2.5 max-sm:grid-cols-1">
                        <div class="light-shimmer-bg dark:shimmer h-[84px] rounded-md"></div>
                        <div class="light-shimmer-bg dark:shimmer h-[84px] rounded-md"></div>
                        <div class="light-shimmer-bg dark:shimmer h-[84px] rounded-md"></div>
                    </div>
                </div>
            </v-sdr-today-summary>

            <v-sdr-call-summary
                summary-url="{{ route('admin.dashboard.call_summary') }}"
            >
                <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div class="grid gap-1">
                            <p class="text-lg font-semibold text-gray-800 dark:text-white">
                                Call Summary
                            </p>

                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Daily, weekly, and monthly SDR call performance.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 max-sm:grid-cols-1">
                        <div class="light-shimmer-bg dark:shimmer h-[104px] rounded-md"></div>
                        <div class="light-shimmer-bg dark:shimmer h-[104px] rounded-md"></div>
                        <div class="light-shimmer-bg dark:shimmer h-[104px] rounded-md"></div>
                        <div class="light-shimmer-bg dark:shimmer h-[104px] rounded-md"></div>
                    </div>
                </div>
            </v-sdr-call-summary>

            @include('admin::dashboard.sdr.us-state-times', [
                'stateTimezones' => $stateTimezones,
                'isPreview'      => true,
            ])
        </div>
    </div>

    {!! view_render_event('admin.dashboard.sdr.index.content.after') !!}

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-sdr-today-summary-template"
        >
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-3 dark:border-gray-800">
                    <div class="grid gap-1">
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">
                            Today's Schedule
                        </p>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Meetings and follow-ups scheduled for today.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2.5 p-3 max-sm:grid-cols-1">
                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Meetings
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white">
                            @{{ summary.meetings }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Scheduled today
                        </p>
                    </div>

                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Follow-ups
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white">
                            @{{ summary.followups }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Due today
                        </p>
                    </div>

                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Total
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white">
                            @{{ summary.total }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            On today's calendar
                        </p>
                    </div>
                </div>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-sdr-call-summary-template"
        >
            <div class="rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-3 dark:border-gray-800 max-lg:flex-wrap">
                    <div class="grid gap-1">
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">
                            Call Summary
                        </p>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            @{{ periodLabel }} SDR call performance.
                        </p>
                    </div>

                    <div class="flex items-center gap-2 max-lg:flex-wrap">
                        <div class="flex overflow-hidden rounded-md border border-gray-200 dark:border-gray-800">
                            <button
                                v-for="option in periodOptions"
                                :key="option.value"
                                type="button"
                                class="px-3 py-2 text-sm font-semibold transition-all"
                                :class="period === option.value
                                    ? 'bg-brandColor text-white'
                                    : 'bg-white text-gray-600 hover:bg-brandColor hover:text-white dark:bg-gray-900 dark:text-gray-300'"
                                @click="setPeriod(option.value)"
                            >
                                @{{ option.label }}
                            </button>
                        </div>

                        <div class="relative">
                            <input
                                ref="summaryRangeInput"
                                type="text"
                                class="sr-only"
                                :value="rangeLabel"
                                tabindex="-1"
                            />

                            <button
                                type="button"
                                class="secondary-button !min-h-[37px] !px-3"
                                :title="rangeLabel"
                                @click="openRangePicker"
                            >
                                <span class="icon-calendar text-xl"></span>
                                <span>@{{ compactRangeLabel }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid flex-1 grid-cols-2 gap-2.5 p-3 max-sm:grid-cols-1">
                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Total Calls
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white">
                            @{{ summary.calls.total }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @{{ summary.period.start }} - @{{ summary.period.end }}
                        </p>
                    </div>

                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Avg Answered Calls
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white">
                            @{{ summary.calls.answered_average_per_day }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @{{ summary.calls.answered }} answered · @{{ summary.calls.answer_rate }}%
                        </p>
                    </div>

                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Won / Lost
                        </p>

                        <div class="mt-2 flex items-end gap-3">
                            <p class="text-2xl font-semibold text-green-600">
                                @{{ summary.outcomes.won }}
                            </p>

                            <p class="pb-1 text-sm font-semibold text-gray-400">
                                /
                            </p>

                            <p class="text-2xl font-semibold text-red-600">
                                @{{ summary.outcomes.lost }}
                            </p>
                        </div>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @{{ summary.outcomes.won_percent }}% won · @{{ summary.outcomes.lost_percent }}% lost
                        </p>
                    </div>

                    <div class="rounded-md border border-gray-200 p-3 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                            Booked Meetings
                        </p>

                        <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white">
                            @{{ summary.meetings.booked }}
                        </p>

                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Meetings scheduled in selected period
                        </p>
                    </div>
                </div>
            </div>
        </script>

        <script
            type="text/x-template"
            id="v-sdr-lead-sections-template"
        >
            <div class="grid grid-cols-1 gap-4">
                <div
                    v-for="column in layoutColumns"
                    :key="column.key"
                    class="grid min-w-0 content-start gap-4"
                >
                    <div
                        v-for="section in column.sections"
                        :key="section.key"
                        class="min-w-0 flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
                        :class="section.tall ? 'min-h-[620px]' : 'min-h-[300px]'"
                    >
                        <div class="border-b border-gray-200 p-3 dark:border-gray-800">
                            <div class="flex items-center justify-between gap-3 max-sm:flex-wrap">
                                <div>
                                    <p class="text-lg font-semibold text-gray-800 dark:text-white">
                                        @{{ section.title }}
                                    </p>

                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        @{{ section.description }}
                                    </p>
                                </div>

                                <div
                                    v-if="section.key === 'today-calendar'"
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <span class="sdr-summary-badge meeting">
                                        Meetings @{{ summary.meetings }}
                                    </span>

                                    <span class="sdr-summary-badge followup">
                                        Follow-ups @{{ summary.followups }}
                                    </span>

                                    <span class="sdr-summary-badge total">
                                        Total @{{ summary.total }}
                                    </span>
                                </div>

                                <span
                                    v-else
                                    class="sdr-section-count"
                                >
                                    @{{ section.items.length }}
                                </span>
                            </div>
                        </div>

                        <div class="grid flex-1 content-start gap-2 p-3">
                            <div
                                v-for="item in paginatedItems(section)"
                                :key="item.id"
                                role="link"
                                tabindex="0"
                                class="min-w-0 cursor-pointer rounded-md border border-gray-200 p-3 transition-all hover:border-brandColor hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950"
                                :class="{'sdr-row-card-warm': isWarm(item)}"
                                @click="openItem(item)"
                                @keydown.enter="openItem(item)"
                            >
                                <div class="grid min-w-0 grid-cols-[minmax(0,1fr)_auto] gap-3">
                                    <div class="min-w-0 overflow-hidden">
                                        <div class="mb-2 flex min-w-0 flex-wrap items-center gap-1.5">
                                            <span
                                                v-if="item.type"
                                                class="sdr-row-type-badge"
                                                :class="itemTypeClass(item)"
                                            >
                                                @{{ item.type }}
                                            </span>

                                            <span
                                                v-if="item.source"
                                                class="sdr-row-source-badge"
                                                :class="{'warm': isWarm(item)}"
                                            >
                                                @{{ item.source }}
                                            </span>
                                        </div>

                                        <a
                                            :href="item.lead_url || item.url"
                                            class="sdr-row-lead-title block text-sm font-semibold text-gray-800 dark:text-white"
                                            @click.stop.prevent="openLead(item)"
                                        >
                                            @{{ item.title }}
                                        </a>

                                        <p
                                            class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400"
                                            v-if="item.person"
                                        >
                                            @{{ item.person }}
                                        </p>

                                        <p class="mt-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                            @{{ item.meta }}
                                        </p>
                                    </div>

                                    <div class="flex justify-end">
                                        <span
                                            class="sdr-row-time-badge"
                                            v-if="item.time"
                                        >
                                            @{{ item.time }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="! paginatedItems(section).length"
                                class="flex min-h-[120px] items-center justify-center rounded-md border border-dashed border-gray-200 p-4 text-center text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400"
                            >
                                No records found.
                            </div>
                        </div>

                        <div
                            v-if="section.items.length > pageSizeFor(section)"
                            class="flex items-center justify-between gap-3 border-t border-gray-200 p-3 dark:border-gray-800 max-sm:flex-wrap"
                        >
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                @{{ paginationLabel(section) }}
                            </p>

                            <div class="flex flex-wrap items-center gap-1.5">
                                <button
                                    type="button"
                                    class="secondary-button !min-h-[31px] !px-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="pageFor(section.key) === 1"
                                    @click="previousPage(section.key)"
                                >
                                    Prev
                                </button>

                                <button
                                    v-for="page in visiblePages(section)"
                                    :key="`${section.key}-${page}`"
                                    type="button"
                                    class="min-h-[31px] min-w-[31px] rounded-md border px-2 text-sm font-semibold transition-all"
                                    :class="pageFor(section.key) === page
                                        ? 'border-brandColor bg-brandColor text-white'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-brandColor hover:bg-brandColor hover:text-white dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300'"
                                    @click="goToPage(section.key, page)"
                                >
                                    @{{ page }}
                                </button>

                                <button
                                    type="button"
                                    class="secondary-button !min-h-[31px] !px-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="pageFor(section.key) === totalPages(section)"
                                    @click="nextPage(section.key, section)"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </script>

        <script type="module">
            app.component('v-sdr-lead-sections', {
                template: '#v-sdr-lead-sections-template',

                props: {
                    sectionsUrl: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        todayCalendar: [],
                        summary: {
                            meetings: 0,
                            followups: 0,
                            total: 0,
                        },
                        refreshInterval: null,
                        pages: {
                            'today-calendar': 1,
                        },
                    };
                },

                computed: {
                    layoutColumns() {
                        return [
                            {
                                key: 'today-column',
                                sections: [
                                    {
                                        key: 'today-calendar',
                                        title: "Today's Calendar",
                                        description: 'Meetings and follow-ups due today.',
                                        items: this.todayCalendar,
                                        pageSize: 10,
                                        tall: true,
                                    },
                                ],
                            },
                        ];
                    },
                },

                mounted() {
                    this.getSections();

                    this.refreshInterval = setInterval(this.getSections, 60000);
                },

                beforeUnmount() {
                    clearInterval(this.refreshInterval);
                },

                methods: {
                    pageFor(key) {
                        return this.pages[key] || 1;
                    },

                    pageSizeFor(section) {
                        return section.pageSize || 5;
                    },

                    totalPages(section) {
                        return Math.max(1, Math.ceil(section.items.length / this.pageSizeFor(section)));
                    },

                    paginatedItems(section) {
                        const pageSize = this.pageSizeFor(section);
                        const start = (this.pageFor(section.key) - 1) * pageSize;

                        return section.items.slice(start, start + pageSize);
                    },

                    paginationLabel(section) {
                        const pageSize = this.pageSizeFor(section);
                        const start = ((this.pageFor(section.key) - 1) * pageSize) + 1;
                        const end = Math.min(this.pageFor(section.key) * pageSize, section.items.length);

                        return `${start}-${end} of ${section.items.length}`;
                    },

                    visiblePages(section) {
                        return Array.from({ length: this.totalPages(section) }, (_, index) => index + 1);
                    },

                    goToPage(key, page) {
                        this.pages[key] = page;
                    },

                    previousPage(key) {
                        this.pages[key] = Math.max(1, this.pageFor(key) - 1);
                    },

                    nextPage(key, section) {
                        this.pages[key] = Math.min(this.totalPages(section), this.pageFor(key) + 1);
                    },

                    itemTypeClass(item) {
                        const sourceClass = this.isWarm(item) ? ' warm-source' : '';

                        if (item.type === 'Meeting') {
                            return `meeting${sourceClass}`;
                        }

                        if (item.type === 'Call') {
                            return `call${sourceClass}`;
                        }

                        return `followup${sourceClass}`;
                    },

                    isWarm(item) {
                        return item.source_group === 'warm'
                            || String(item.source || '').toLowerCase() === 'warm leads';
                    },

                    openItem(item) {
                        if (item.url) {
                            window.location.href = item.url;
                        }
                    },

                    openLead(item) {
                        if (item.lead_url || item.url) {
                            window.location.href = item.lead_url || item.url;
                        }
                    },

                    getSections() {
                        this.$axios.get(this.sectionsUrl)
                            .then(response => {
                                const summary = response.data.summary || {};

                                this.todayCalendar = response.data.today_calendar || [];
                                this.summary = {
                                    meetings: summary.meetings || 0,
                                    followups: summary.followups || 0,
                                    total: summary.total || 0,
                                };
                                this.pages = {
                                    'today-calendar': 1,
                                };
                            })
                            .catch(error => {
                                console.log(error);

                                this.todayCalendar = [];
                                this.summary = {
                                    meetings: 0,
                                    followups: 0,
                                    total: 0,
                                };
                            });
                    },
                },
            });

            app.component('v-sdr-today-summary', {
                template: '#v-sdr-today-summary-template',

                props: {
                    sectionsUrl: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        summary: {
                            meetings: 0,
                            followups: 0,
                            total: 0,
                        },
                        refreshInterval: null,
                    };
                },

                mounted() {
                    this.getSummary();

                    this.refreshInterval = setInterval(this.getSummary, 60000);
                },

                beforeUnmount() {
                    clearInterval(this.refreshInterval);
                },

                methods: {
                    getSummary() {
                        this.$axios.get(this.sectionsUrl)
                            .then(response => {
                                const summary = response.data.summary || {};

                                this.summary = {
                                    meetings: summary.meetings || 0,
                                    followups: summary.followups || 0,
                                    total: summary.total || 0,
                                };
                            })
                            .catch(error => {
                                console.log(error);

                                this.summary = {
                                    meetings: 0,
                                    followups: 0,
                                    total: 0,
                                };
                            });
                    },
                },
            });

            app.component('v-sdr-call-summary', {
                template: '#v-sdr-call-summary-template',

                props: {
                    summaryUrl: {
                        type: String,
                        required: true,
                    },
                },

                data() {
                    return {
                        period: 'day',
                        periodOptions: [
                            { label: 'Day', value: 'day' },
                            { label: 'Week', value: 'week' },
                            { label: 'Month', value: 'month' },
                        ],
                        filters: {
                            startDate: '',
                            endDate: '',
                        },
                        rangePicker: null,
                        summary: {
                            period: {
                                start: '',
                                end: '',
                            },
                            calls: {
                                total: 0,
                                answered: 0,
                                answer_rate: 0,
                                answered_average_per_day: 0,
                            },
                            outcomes: {
                                won: 0,
                                lost: 0,
                                won_percent: 0,
                                lost_percent: 0,
                            },
                            meetings: {
                                booked: 0,
                            },
                        },
                    };
                },

                computed: {
                    periodLabel() {
                        return this.periodOptions.find((option) => option.value === this.period)?.label || 'Day';
                    },

                    rangeLabel() {
                        return `${this.filters.startDate} to ${this.filters.endDate}`;
                    },

                    compactRangeLabel() {
                        if (! this.filters.startDate || ! this.filters.endDate) {
                            return 'Date Range';
                        }

                        if (this.filters.startDate === this.filters.endDate) {
                            return this.filters.startDate;
                        }

                        return `${this.shortDate(this.filters.startDate)} - ${this.shortDate(this.filters.endDate)}`;
                    },
                },

                mounted() {
                    this.filters = this.defaultPeriodDates(this.period);

                    this.$nextTick(() => this.activateRangePicker());

                    this.getSummary();
                },

                watch: {
                    filters: {
                        handler() {
                            this.getSummary();
                        },

                        deep: true,
                    },
                },

                methods: {
                    setPeriod(period) {
                        this.period = period;
                        this.filters = this.defaultPeriodDates(period);
                        this.rangePicker?.setDate([this.filters.startDate, this.filters.endDate], false);
                    },

                    activateRangePicker() {
                        this.rangePicker = new Flatpickr(this.$refs.summaryRangeInput, {
                            mode: 'range',
                            dateFormat: 'Y-m-d',
                            defaultDate: [this.filters.startDate, this.filters.endDate],
                            clickOpens: false,
                            allowInput: false,
                            onChange: (selectedDates) => {
                                if (selectedDates.length < 2) {
                                    return;
                                }

                                this.filters.startDate = this.formatDateForInput(selectedDates[0]);
                                this.filters.endDate = this.formatDateForInput(selectedDates[1]);
                            },
                        });
                    },

                    openRangePicker() {
                        this.rangePicker?.open();
                    },

                    defaultPeriodDates(period) {
                        const now = new Date();
                        let start = new Date(now);
                        let end = new Date(now);

                        if (period === 'week') {
                            const day = now.getDay() || 7;
                            start.setDate(now.getDate() - day + 1);
                            end = new Date(start);
                            end.setDate(start.getDate() + 6);
                        } else if (period === 'month') {
                            start = new Date(now.getFullYear(), now.getMonth(), 1);
                            end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                        }

                        return {
                            startDate: this.formatDateForInput(start),
                            endDate: this.formatDateForInput(end),
                        };
                    },

                    formatDateForInput(date) {
                        const value = new Date(date);
                        const year = value.getFullYear();
                        const month = `${value.getMonth() + 1}`.padStart(2, '0');
                        const day = `${value.getDate()}`.padStart(2, '0');

                        return `${year}-${month}-${day}`;
                    },

                    shortDate(date) {
                        const value = new Date(`${date}T00:00:00`);

                        return new Intl.DateTimeFormat('en-US', {
                            month: 'short',
                            day: 'numeric',
                        }).format(value);
                    },

                    getSummary() {
                        this.$axios.get(this.summaryUrl, {
                            params: {
                                period: this.period,
                                start_date: this.filters.startDate,
                                end_date: this.filters.endDate,
                            },
                        })
                            .then(response => {
                                this.summary = response.data;
                            })
                            .catch(error => {
                                console.log(error);
                            });
                    },
                },
            });

        </script>
    @endPushOnce

    @pushOnce('styles')
        <style>
            .sdr-dashboard-split {
                align-items: start;
            }

            .sdr-dashboard-left-scroll {
                max-height: calc(100vh - 150px);
                overflow-y: auto;
                padding-right: 4px;
            }

            .sdr-dashboard-right-static {
                position: sticky;
                top: 16px;
            }

            .sdr-section-count {
                align-items: center;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 9999px;
                color: #111827;
                display: inline-flex;
                font-size: 12px;
                font-weight: 700;
                height: 28px;
                justify-content: center;
                min-width: 34px;
                padding: 0 10px;
            }

            .sdr-summary-badge {
                align-items: center;
                border-radius: 9999px;
                display: inline-flex;
                font-size: 11px;
                font-weight: 700;
                height: 28px;
                padding: 0 10px;
                white-space: nowrap;
            }

            .sdr-summary-badge.meeting {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .sdr-summary-badge.followup {
                background: #fee2e2;
                color: #b91c1c;
            }

            .sdr-summary-badge.total {
                background: #f3f4f6;
                color: #111827;
            }

            .dark .sdr-summary-badge.meeting {
                background: rgba(30, 58, 138, 0.45);
                color: #bfdbfe;
            }

            .dark .sdr-summary-badge.followup {
                background: rgba(127, 29, 29, 0.45);
                color: #fecaca;
            }

            .dark .sdr-summary-badge.total {
                background: rgba(55, 65, 81, 0.6);
                color: #e5e7eb;
            }

            .sdr-row-type-badge {
                align-items: center;
                border-radius: 9999px;
                display: inline-flex;
                font-size: 10px;
                font-weight: 800;
                gap: 5px;
                letter-spacing: 0;
                line-height: 1;
                padding: 5px 8px;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .sdr-row-type-badge::before {
                border-radius: 9999px;
                content: "";
                height: 6px;
                width: 6px;
            }

            .sdr-row-type-badge.followup {
                background: #fee2e2;
                color: #b91c1c;
            }

            .sdr-row-type-badge.followup::before {
                background: #ef4444;
            }

            .sdr-row-type-badge.meeting {
                background: #dbeafe;
                color: #1d4ed8;
            }

            .sdr-row-type-badge.meeting::before {
                background: #2563eb;
            }

            .sdr-row-type-badge.call {
                background: #ccfbf1;
                color: #0f766e;
            }

            .sdr-row-type-badge.call::before {
                background: #14b8a6;
            }

            .sdr-row-type-badge.warm-source {
                background: #fee2e2;
                color: #991b1b;
            }

            .sdr-row-type-badge.warm-source::before {
                background: #dc2626;
            }

            .sdr-row-source-badge {
                align-items: center;
                background: #dbeafe;
                border-radius: 9999px;
                color: #1e3a8a;
                display: inline-flex;
                font-size: 10px;
                font-weight: 700;
                line-height: 1;
                padding: 5px 8px;
                white-space: nowrap;
            }

            .sdr-row-source-badge.warm {
                background: #fecaca;
                color: #7f1d1d;
            }

            .sdr-row-card-warm {
                background: #fff7f7;
                border-color: #fecaca !important;
            }

            .sdr-row-card-warm:hover {
                background: #fee2e2 !important;
                border-color: #ef4444 !important;
            }

            .sdr-row-lead-title {
                cursor: pointer;
                overflow-wrap: anywhere;
                text-decoration: none;
                text-underline-offset: 3px;
            }

            .sdr-row-lead-title:hover {
                text-decoration: underline;
            }

            .sdr-row-time-badge {
                align-items: center;
                background: #fff7ed;
                border: 1px solid #fed7aa;
                border-radius: 9999px;
                color: #ea580c;
                display: inline-flex;
                flex-shrink: 0;
                font-size: 11px;
                font-weight: 800;
                line-height: 1;
                padding: 6px 8px;
                white-space: nowrap;
            }

            .dark .sdr-section-count {
                background: #111827;
                border-color: #1f2937;
                color: #e5e7eb;
            }

            .dark .sdr-row-type-badge.followup {
                background: rgba(127, 29, 29, 0.35);
                color: #fca5a5;
            }

            .dark .sdr-row-type-badge.meeting {
                background: rgba(30, 64, 175, 0.35);
                color: #bfdbfe;
            }

            .dark .sdr-row-type-badge.warm-source {
                background: rgba(127, 29, 29, 0.55);
                color: #fecaca;
            }

            .dark .sdr-row-source-badge {
                background: rgba(30, 58, 138, 0.45);
                color: #bfdbfe;
            }

            .dark .sdr-row-source-badge.warm {
                background: rgba(127, 29, 29, 0.45);
                color: #fecaca;
            }

            .dark .sdr-row-card-warm {
                background: rgba(127, 29, 29, 0.16);
                border-color: rgba(248, 113, 113, 0.35) !important;
            }

            .dark .sdr-row-card-warm:hover {
                background: rgba(127, 29, 29, 0.28) !important;
                border-color: rgba(248, 113, 113, 0.7) !important;
            }

            .dark .sdr-row-time-badge {
                background: rgba(124, 45, 18, 0.28);
                border-color: rgba(251, 146, 60, 0.35);
                color: #fdba74;
            }

            @media (max-width: 1279px) {
                .sdr-dashboard-left-scroll {
                    max-height: none;
                    overflow-y: visible;
                    padding-right: 0;
                }

                .sdr-dashboard-right-static {
                    position: static;
                }
            }

        </style>
    @endPushOnce
</x-admin::layouts>
