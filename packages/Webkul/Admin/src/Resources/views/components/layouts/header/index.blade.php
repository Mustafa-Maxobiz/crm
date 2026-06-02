<header class="sticky top-0 z-[10001] flex items-center justify-between gap-1 border-b border-gray-200 bg-white px-4 py-2.5 transition-all dark:border-gray-800 dark:bg-gray-900">  
    <!-- logo -->
    <div class="flex items-center gap-1.5">
        <!-- Sidebar Menu -->
        <x-admin::layouts.sidebar.mobile />
        
        <a href="{{ route('admin.dashboard.index') }}">
            @if ($logo = core()->getConfigData('general.general.admin_logo.logo_image'))
                <img
                    class="h-10"
                    src="{{ Storage::url($logo) }}"
                    alt="{{ config('app.name') }}"
                />
            @else
                <img
                    class="h-10 max-sm:hidden"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/dark-logo.svg') : vite()->asset('images/logo.svg') }}"
                    id="logo-image"
                    alt="{{ config('app.name') }}"
                />

                <img
                    class="h-8 max-w-[130px] object-contain sm:hidden"
                    src="{{ request()->cookie('dark_mode') ? vite()->asset('images/mobile-dark-logo.svg') : vite()->asset('images/mobile-light-logo.svg') }}"
                    id="logo-image"
                    alt="{{ config('app.name') }}"
                />
            @endif
        </a>
    </div>

    <div class="flex items-center gap-1.5 max-md:hidden">
        <!-- Mega Search Bar -->
        @include('admin::components.layouts.header.desktop.mega-search')

        <!-- Quick Creation Bar -->
        @include('admin::components.layouts.header.quick-creation')
    </div>

    <div class="flex items-center gap-2.5">
        <div class="md:hidden">
            <!-- Mega Search Bar -->
            @include('admin::components.layouts.header.mobile.mega-search')
        </div>
        
        <!-- Follow-up notifications -->
        <v-followup-notifications></v-followup-notifications>

        <!-- Dark mode -->
        <v-dark>
            <div class="flex">
                <span
                    class="{{ request()->cookie('dark_mode') ? 'icon-light' : 'icon-dark' }} flex h-9 w-9 cursor-pointer items-center justify-center rounded-md text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                ></span>
            </div>
        </v-dark>

        <div class="md:hidden">
            <!-- Quick Creation Bar -->
            @include('admin::components.layouts.header.quick-creation')
        </div>
        
        <!-- Admin profile -->
        <x-admin::dropdown position="bottom-{{ in_array(app()->getLocale(), ['fa', 'ar']) ? 'left' : 'right' }}">
            <x-slot:toggle>
                @php($user = auth()->guard('user')->user())

                @if ($user->image && Storage::exists($user->image))
                    <button class="flex h-9 w-9 cursor-pointer overflow-hidden rounded-full hover:opacity-80 focus:opacity-80">
                        <img
                            src="{{ $user->image_url }}"
                            class="h-full w-full object-cover"
                        />
                    </button>
                @else
                    <button class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-full bg-pink-400 font-semibold leading-6 text-white">
                        {{ substr($user->name, 0, 1) }}
                    </button>
                @endif
            </x-slot>

            <!-- Admin Dropdown -->
            <x-slot:content class="mt-2 border-t-0 !p-0">
                <div class="flex items-center gap-1.5 border border-x-0 border-b-gray-300 px-5 py-2.5 dark:border-gray-800">
                    <img
                        src="{{ vite()->asset('images/logo.svg') }}"
                        class="h-6 w-auto max-w-24 object-contain"
                    />

                    <!-- Version -->
                    <p class="text-gray-400">
                        @lang('admin::app.layouts.app-version', ['version' => core()->version()])
                    </p>
                </div>

                <div class="grid gap-1 pb-2.5">
                    <a
                        class="cursor-pointer px-5 py-2 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        href="{{ route('admin.user.account.edit') }}"
                    >
                        @lang('admin::app.layouts.my-account')
                    </a>

                    <!--Admin logout-->
                    <x-admin::form
                        method="DELETE"
                        action="{{ route('admin.session.destroy') }}"
                        id="adminLogout"
                    >
                    </x-admin::form>

                    <a
                        class="cursor-pointer px-5 py-2 text-base text-gray-800 hover:bg-gray-100 dark:text-white dark:hover:bg-gray-950"
                        href="{{ route('admin.session.destroy') }}"
                        onclick="event.preventDefault(); document.getElementById('adminLogout').submit();"
                    >
                        @lang('admin::app.layouts.sign-out')
                    </a>
                </div>
            </x-slot>
        </x-admin::dropdown>
    </div>
</header>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-dark-template"
    >
        <div class="flex">
            <span
                class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-md text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                :class="[isDarkMode ? 'icon-light' : 'icon-dark']"
                @click="toggle"
            ></span>
        </div>
    </script>

    <script
        type="text/x-template"
        id="v-followup-notifications-template"
    >
        <div
            class="relative flex h-9 w-9 items-center justify-center"
            ref="notificationDropdown"
        >
            <button
                type="button"
                class="relative flex h-9 w-9 items-center justify-center rounded-md text-2xl text-gray-700 transition-all hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-950"
                title="@lang('admin::app.activities.notifications.title')"
                @click.stop="toggle"
            >
                <span class="icon-notification"></span>

                <span
                    class="absolute flex min-h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white"
                    style="top: -5px; right: -5px;"
                    v-if="count"
                >
                    @{{ countLabel }}
                </span>
            </button>

            <span
                v-if="hasUnreadNotifications"
                aria-hidden="true"
                style="position: absolute; top: 5px; right: 7px; z-index: 20; display: block; width: 8px; height: 8px; border-radius: 9999px; background: #dc2626; border: 2px solid #ffffff; pointer-events: none;"
            ></span>

            <div
                class="absolute top-11 z-10 w-[360px] overflow-hidden rounded border border-gray-300 bg-white shadow-[0px_10px_20px_0px_#0000001F] ltr:right-0 rtl:left-0 dark:border-gray-800 dark:bg-gray-900 max-sm:fixed max-sm:left-3 max-sm:right-3 max-sm:top-14 max-sm:w-auto"
                v-show="isOpen"
            >
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
                    <p class="text-base font-semibold text-gray-800 dark:text-white">
                        @lang('admin::app.activities.notifications.title')
                    </p>

                    <span
                        class="rounded-full bg-brandColor px-2 py-0.5 text-xs font-semibold text-white"
                        v-if="count"
                    >
                        @{{ countLabel }}
                    </span>
                </div>

                <div
                    class="flex max-h-[420px] flex-col overflow-y-auto"
                    v-if="notifications.length"
                >
                    <div
                        class="border-b border-gray-100 px-4 py-3 last:border-b-0 dark:border-gray-800"
                        v-for="notification in notifications"
                        :key="notification.id"
                    >
                        <div class="flex gap-3">
                            <span
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-lg"
                                :class="typeIconClass(notification.type)"
                            ></span>

                            <div class="min-w-0 flex-1">
                                <a
                                    class="block truncate text-sm font-semibold text-gray-800 hover:text-brandColor dark:text-white"
                                    :href="notification.edit_url"
                                >
                                    @{{ notification.title || capitalize(notification.type) }}
                                </a>

                                <p
                                    class="mt-1 truncate text-xs text-gray-600 dark:text-gray-300"
                                    v-if="notification.lead_title"
                                >
                                    @lang('admin::app.activities.notifications.lead'): @{{ notification.lead_title }}
                                </p>

                                <p
                                    class="mt-1 truncate text-xs text-gray-600 dark:text-gray-300"
                                    v-if="notification.person_name"
                                >
                                    @{{ notification.person_name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    @lang('admin::app.activities.notifications.due'):
                                    @{{ formatDate(notification.schedule_from) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="secondary-button px-3 py-1.5 text-xs"
                                :disabled="processingId === notification.id"
                                @click.stop="markAsDone(notification)"
                            >
                                @lang('admin::app.activities.notifications.mark-done')
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                    v-else
                >
                    @lang('admin::app.activities.notifications.empty')
                </div>

                <a
                    class="block border-t border-gray-200 px-4 py-3 text-center text-sm font-medium text-brandColor hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950"
                    href="{{ route('admin.activities.index') }}"
                >
                    @lang('admin::app.activities.notifications.view-all')
                </a>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-followup-notifications', {
            template: '#v-followup-notifications-template',

            data() {
                return {
                    isOpen: false,

                    count: 0,

                    unreadMessagesCount: 0,

                    notifications: [],

                    processingId: null,

                    refreshInterval: null,

                    timezone: "{{ config('app.timezone') }}",
                };
            },

            computed: {
                hasUnreadNotifications() {
                    return this.count > 0 || this.unreadMessagesCount > 0;
                },

                countLabel() {
                    return this.count > 99 ? '99+' : this.count;
                },
            },

            mounted() {
                this.fetchNotifications();

                this.refreshInterval = setInterval(this.fetchNotifications, 15000);

                window.addEventListener('click', this.handleOutsideClick);

                window.addEventListener('focus', this.fetchNotifications);

                document.addEventListener('visibilitychange', this.handleVisibilityChange);

                this.$emitter.on('activity-created', this.fetchNotifications);
            },

            beforeUnmount() {
                clearInterval(this.refreshInterval);

                window.removeEventListener('click', this.handleOutsideClick);

                window.removeEventListener('focus', this.fetchNotifications);

                document.removeEventListener('visibilitychange', this.handleVisibilityChange);

                this.$emitter.off('activity-created', this.fetchNotifications);
            },

            methods: {
                toggle() {
                    this.isOpen = ! this.isOpen;

                    if (this.isOpen) {
                        this.fetchNotifications();
                    }
                },

                handleOutsideClick(event) {
                    if (! this.$refs.notificationDropdown.contains(event.target)) {
                        this.isOpen = false;
                    }
                },

                handleVisibilityChange() {
                    if (! document.hidden) {
                        this.fetchNotifications();
                    }
                },

                fetchNotifications() {
                    this.$axios
                        .get("{{ route('admin.activities.notifications') }}")
                        .then(response => {
                            this.count = response.data.count;

                            this.unreadMessagesCount = response.data.unread_messages_count;

                            this.notifications = response.data.notifications;
                        });
                },

                markAsDone(notification) {
                    this.processingId = notification.id;

                    this.$axios
                        .put("{{ route('admin.activities.notifications.done', 'replaceId') }}".replace('replaceId', notification.id))
                        .then(response => {
                            this.notifications = this.notifications.filter(item => item.id !== notification.id);

                            this.count = Math.max(this.count - 1, 0);

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .finally(() => {
                            this.processingId = null;
                        });
                },

                formatDate(date) {
                    if (! date) {
                        return '';
                    }

                    return this.$admin.formatDate(date, 'd MMM yyyy, h:mm A', this.timezone);
                },

                capitalize(value) {
                    if (! value) {
                        return '';
                    }

                    return value.charAt(0).toUpperCase() + value.slice(1);
                },

                typeIconClass(type) {
                    const icons = {
                        call: 'icon-call bg-cyan-200 text-cyan-800 dark:!text-cyan-800',
                        meeting: 'icon-activity bg-blue-200 text-blue-800 dark:!text-blue-800',
                        lunch: 'icon-activity bg-blue-200 text-blue-800 dark:!text-blue-800',
                        followup: 'icon-notification bg-yellow-200 text-yellow-900 dark:!text-yellow-900',
                    };

                    return icons[type] ?? 'icon-activity bg-blue-200 text-blue-800 dark:!text-blue-800';
                },
            },
        });

        app.component('v-dark', {
            template: '#v-dark-template',

            data() {
                return {
                    isDarkMode: {{ request()->cookie('dark_mode') ?? 0 }},

                    logo: "{{ vite()->asset('images/logo.svg') }}",

                    dark_logo: "{{ vite()->asset('images/dark-logo.svg') }}",
                };
            },

            methods: {
                toggle() {
                    this.isDarkMode = parseInt(this.isDarkModeCookie()) ? 0 : 1;

                    var expiryDate = new Date();

                    expiryDate.setMonth(expiryDate.getMonth() + 1);

                    document.cookie = 'dark_mode=' + this.isDarkMode + '; path=/; expires=' + expiryDate.toGMTString();

                    document.documentElement.classList.toggle('dark', this.isDarkMode === 1);

                    if (this.isDarkMode) {
                        this.$emitter.emit('change-theme', 'dark');

                        document.getElementById('logo-image').src = this.dark_logo;
                    } else {
                        this.$emitter.emit('change-theme', 'light');

                        document.getElementById('logo-image').src = this.logo;
                    }
                },

                isDarkModeCookie() {
                    const cookies = document.cookie.split(';');

                    for (const cookie of cookies) {
                        const [name, value] = cookie.trim().split('=');

                        if (name === 'dark_mode') {
                            return value;
                        }
                    }

                    return 0;
                },
            },
        });
    </script>
@endPushOnce
