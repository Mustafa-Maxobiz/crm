<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.smrtphone.settings.title')
    </x-slot>

    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
            <div class="flex flex-col gap-2">
                <x-admin::breadcrumbs name="smrtphone" />

                <div class="text-xl font-bold dark:text-white">
                    @lang('admin::app.smrtphone.settings.title')
                </div>

                <p class="text-gray-600 dark:text-gray-400">
                    @lang('admin::app.smrtphone.settings.subtitle')
                </p>
            </div>

            <a href="{{ route('admin.smrtphone.index') }}" class="secondary-button">
                @lang('admin::app.smrtphone.settings.back')
            </a>
        </div>

        <v-smrtphone-settings
            action-url="{{ route('admin.smrtphone.settings.update') }}"
            webhook-url="{{ $webhookUrl }}"
            :settings='@json($settings)'
        ></v-smrtphone-settings>
    </div>

    @pushOnce('scripts')
        <script type="text/x-template" id="v-smrtphone-settings-template">
            <form method="POST" :action="actionUrl" class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <input type="hidden" name="_token" :value="csrfToken" />
                <input type="hidden" name="_method" value="PUT" />

                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-white">
                            @lang('admin::app.smrtphone.settings.webhook-url')
                        </label>

                        <div class="flex gap-2">
                            <input
                                type="text"
                                :value="webhookUrl"
                                readonly
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                            />

                            <button type="button" class="secondary-button" @click="copy(webhookUrl)">
                                @lang('admin::app.smrtphone.settings.copy')
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-white">
                            @lang('admin::app.smrtphone.settings.webhook-url-with-secret')
                        </label>

                        <div class="flex gap-2">
                            <input
                                :type="showSecretUrl ? 'text' : 'password'"
                                :value="endpointWithSecret || webhookUrl"
                                readonly
                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                            />

                            <button type="button" class="secondary-button" @click="showSecretUrl = ! showSecretUrl" v-text="showSecretUrl ? hideText : revealText">
                            </button>

                            <button
                                type="button"
                                class="secondary-button"
                                :disabled="! endpointWithSecret"
                                @click="copy(endpointWithSecret || webhookUrl)"
                            >
                                @lang('admin::app.smrtphone.settings.copy')
                            </button>
                        </div>

                        <p v-if="! endpointWithSecret" class="text-xs text-gray-500 dark:text-gray-400">
                            @lang('admin::app.smrtphone.settings.set-secret-to-generate-url')
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <label class="text-sm font-medium text-gray-800 dark:text-white">
                            @lang('admin::app.smrtphone.settings.webhook-secret')
                        </label>

                        <div class="flex gap-2">
                            <input
                                :type="showSecret ? 'text' : 'password'"
                                name="webhook_secret"
                                v-model="form.webhook_secret"
                                minlength="8"
                                maxlength="255"
                                autocomplete="new-password"
                                class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                placeholder="@lang('admin::app.smrtphone.settings.webhook-secret-placeholder')"
                            />

                            <button type="button" class="secondary-button" @click="showSecret = ! showSecret" v-text="showSecret ? hideText : revealText">
                            </button>
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            @lang('admin::app.smrtphone.settings.webhook-secret-help')
                        </p>

                        @error('webhook_secret')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid gap-3 rounded-md border border-gray-200 p-4 dark:border-gray-800">
                        <div>
                            <label class="text-sm font-medium text-gray-800 dark:text-white">
                                @lang('admin::app.smrtphone.settings.allowed-numbers')
                            </label>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @lang('admin::app.smrtphone.settings.allowed-numbers-help')
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <div
                                v-for="(number, index) in form.allowed_numbers"
                                :key="'allowed-number-' + index"
                                class="flex gap-2"
                            >
                                <input
                                    type="text"
                                    name="allowed_numbers[]"
                                    v-model="form.allowed_numbers[index]"
                                    class="w-full rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-300"
                                    placeholder="@lang('admin::app.smrtphone.settings.allowed-number-placeholder')"
                                />

                                <button
                                    type="button"
                                    class="secondary-button"
                                    :disabled="form.allowed_numbers.length === 1"
                                    @click="removePhoneNumber(index)"
                                >
                                    @lang('admin::app.smrtphone.settings.remove')
                                </button>
                            </div>
                        </div>

                        <div>
                            <button type="button" class="secondary-button" @click="addPhoneNumber">
                                @lang('admin::app.smrtphone.settings.add-phone-number')
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <label
                            v-for="toggle in toggles"
                            :key="toggle.key"
                            class="flex items-start gap-3 rounded-md border border-gray-200 p-4 dark:border-gray-800"
                        >
                            <input
                                type="checkbox"
                                :name="toggle.key"
                                value="1"
                                v-model="form[toggle.key]"
                                class="mt-1 rounded border-gray-300 text-brandColor focus:ring-brandColor dark:border-gray-700"
                            />

                            <span>
                                <span class="block text-sm font-medium text-gray-800 dark:text-white" v-text="toggle.label"></span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400" v-text="toggle.help"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="submit" class="primary-button">
                        @lang('admin::app.smrtphone.settings.save')
                    </button>
                </div>
            </form>
        </script>

        <script type="module">
            app.component('v-smrtphone-settings', {
                template: '#v-smrtphone-settings-template',

                props: {
                    actionUrl: {
                        type: String,
                        required: true,
                    },

                    webhookUrl: {
                        type: String,
                        required: true,
                    },

                    settings: {
                        type: Object,
                        required: true,
                    },
                },

                data() {
                    return {
                        csrfToken: '{{ csrf_token() }}',
                        revealText: '@lang('admin::app.smrtphone.settings.reveal')',
                        hideText: '@lang('admin::app.smrtphone.settings.hide')',
                        showSecret: false,
                        showSecretUrl: false,
                        form: {
                            webhook_secret: this.settings.webhook_secret || '',
                            create_activities: !! this.settings.create_activities,
                            save_ai_summary: !! this.settings.save_ai_summary,
                            auto_match_lead: !! this.settings.auto_match_lead,
                            create_activity_only_when_matched: !! this.settings.create_activity_only_when_matched,
                            allowed_numbers: this.normalizeAllowedNumbers(this.settings.allowed_numbers),
                        },
                        toggles: [
                            {
                                key: 'create_activities',
                                label: '@lang('admin::app.smrtphone.settings.create-activities')',
                                help: '@lang('admin::app.smrtphone.settings.create-activities-help')',
                            },
                            {
                                key: 'save_ai_summary',
                                label: '@lang('admin::app.smrtphone.settings.save-ai-summary')',
                                help: '@lang('admin::app.smrtphone.settings.save-ai-summary-help')',
                            },
                            {
                                key: 'auto_match_lead',
                                label: '@lang('admin::app.smrtphone.settings.auto-match-lead')',
                                help: '@lang('admin::app.smrtphone.settings.auto-match-lead-help')',
                            },
                            {
                                key: 'create_activity_only_when_matched',
                                label: '@lang('admin::app.smrtphone.settings.activity-only-when-matched')',
                                help: '@lang('admin::app.smrtphone.settings.activity-only-when-matched-help')',
                            },
                        ],
                    };
                },

                computed: {
                    hasSecret() {
                        return String(this.form.webhook_secret || '').trim() !== '';
                    },

                    endpointWithSecret() {
                        const secret = String(this.form.webhook_secret || '').trim();

                        if (! secret) {
                            return '';
                        }

                        const separator = this.webhookUrl.includes('?') ? '&' : '?';

                        return this.webhookUrl + separator + 'secret=' + encodeURIComponent(secret);
                    },
                },

                methods: {
                    copy(value) {
                        if (! value) {
                            return;
                        }

                        if (navigator.clipboard?.writeText) {
                            navigator.clipboard.writeText(value);

                            return;
                        }

                        const input = document.createElement('textarea');
                        input.value = value;
                        input.setAttribute('readonly', 'readonly');
                        input.style.position = 'fixed';
                        input.style.opacity = '0';
                        document.body.appendChild(input);
                        input.select();
                        document.execCommand('copy');
                        document.body.removeChild(input);
                    },

                    addPhoneNumber() {
                        this.form.allowed_numbers.push('');
                    },

                    removePhoneNumber(index) {
                        if (this.form.allowed_numbers.length === 1) {
                            return;
                        }

                        this.form.allowed_numbers.splice(index, 1);
                    },

                    normalizeAllowedNumbers(numbers) {
                        if (! Array.isArray(numbers) || ! numbers.length) {
                            return [''];
                        }

                        return numbers;
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
