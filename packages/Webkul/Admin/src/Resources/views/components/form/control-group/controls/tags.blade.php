<v-control-tags
    :errors="errors"
    {{ $attributes }}
    v-bind="$attrs"
></v-control-tags>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-control-tags-template"
    >
        <div 
            class="flex min-h-[38px] w-full items-center rounded border border-gray-200 px-2.5 py-1.5 text-sm font-normal text-gray-800 transition-all hover:border-gray-400 dark:border-gray-800 dark:text-white dark:hover:border-gray-400"
            :class="[validationErrors[`temp-${name}`] ? 'border !border-red-600 hover:border-red-600' : '']"
        >
            <ul
                class="relative flex flex-wrap items-center gap-1"
                v-bind="$attrs"
            >
                <li
                    v-for="(tag, index) in tags"
                    :key="index"
                    class="flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-950 ltr:pl-2 rtl:pr-2"
                >
                    <x-admin::form.control-group.control
                        type="hidden"
                        ::name="name + '[' + index + ']'"
                        ::value="tag"
                    />

                    @{{ tag }}

                    <span
                        class="icon-cross-large cursor-pointer p-0.5 text-xl"
                        @click="removeTag(tag)"
                    ></span>
                </li>

                <li :class="['w-full', tags.length && 'mt-1.5']">
                    <v-field
                        v-slot="{ field, errors }"
                        :name="'temp-' + name"
                        v-model="input"
                        :rules="tags.length ? inputRules : [inputRules, rules].filter(Boolean).join('|')"
                        :label="label"
                    >
                        <input
                            type="text"
                            :name="'temp-' + name"
                            v-bind="field"
                            class="w-full dark:!bg-gray-900"
                            :placeholder="placeholder"
                            :label="label"
                            @keydown.enter.prevent="addTag"
                            autocomplete="new-email"
                            @blur="addTag"
                        />
                    </v-field>

                    <div
                        v-if="suggestionsEndpoint && showSuggestions"
                        class="absolute left-0 right-0 top-full z-10 mt-1 rounded border border-gray-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                    >
                        <ul class="max-h-48 overflow-auto py-1">
                            <li
                                v-for="suggestion in suggestions"
                                :key="suggestion.id"
                                class="cursor-pointer px-3 py-2 text-sm text-gray-800 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                @mousedown.prevent="selectSuggestion(suggestion)"
                            >
                                @{{ suggestion.name }}
                            </li>
                        </ul>
                    </div>

                    <template v-if="! tags.length && input != ''">
                        <v-field
                            v-slot="{ field, errors }"
                            :name="name + '[' + 0 +']'"
                            :value="input"
                            :rules="inputRules"
                            :label="label"
                        >
                            <input
                                type="hidden"
                                :name="name + '[0]'"
                                v-bind="field"
                            />
                        </v-field>
                    </template>
                </li>
            </ul>
        </div>

        <v-error-message
            :name="'temp-' + name"
            v-slot="{ message }"
        >
            <p
                class="mt-1 text-xs italic text-red-600"
                v-text="message"
            >
            </p>
        </v-error-message>
    </script>

    <script type="module">
        app.component('v-control-tags', {
            template: '#v-control-tags-template',

            props: {
                name: {
                    type: String,
                    required: true,
                },

                label: {
                    type: String,
                    default: '',
                },

                placeholder: {
                    type: String,
                    default: '',
                },

                rules: {
                    type: String,
                    default: '',
                },

                inputRules: {
                    type: String,
                    default: '',
                },

                data: {
                    type: Array,
                    default: () => [],
                },

                errors: {
                    type: Object,
                    default: () => {},
                },

                allowDuplicates: {
                    type: Boolean,
                    default: true,
                },

                suggestionsEndpoint: {
                    type: String,
                    default: '',
                },
            },

            data() {
                return {
                    tags: Array.isArray(this.data) ? [...this.data] : [],

                    input: '',

                    suggestions: [],

                    isSearching: false,
                }
            },

            computed: {
                showSuggestions() {
                    return this.input.trim().length >= 2 && this.suggestions.length > 0;
                },

                validationErrors() {
                    return this.errors || {};
                },
            },

            watch: {
                data: {
                    handler(value) {
                        this.tags = Array.isArray(value) ? [...value] : [];
                    },
                    deep: true,
                },

                input(newValue) {
                    this.searchSuggestions(newValue);
                },
            },

            methods: {
                addTag() {
                    if (this.validationErrors['temp-' + this.name]) {
                        return;
                    }

                    const tag = this.input.trim();

                    if (! tag) {
                        return;
                    }

                    if (
                        ! this.allowDuplicates
                        && this.tags.includes(tag)
                    ) {
                        this.input = '';

                        return;
                    }

                    this.tags.push(tag);

                    this.$emit('tags-updated', this.tags);

                    this.suggestions = [];

                    this.input = '';
                },

                removeTag: function(tag) {
                    this.tags = this.tags.filter(function (tempTag) {
                        return tempTag != tag;
                    });

                    this.$emit('tags-updated', this.tags);
                },

                selectSuggestion(suggestion) {
                    this.input = suggestion.name;

                    this.addTag();
                },

                searchSuggestions(value) {
                    const search = value.trim();

                    if (
                        ! this.suggestionsEndpoint
                        || search.length < 2
                    ) {
                        this.suggestions = [];

                        return;
                    }

                    this.isSearching = true;

                    this.$axios.get(this.suggestionsEndpoint, {
                        params: {
                            search: `name:${search}`,
                            searchFields: 'name:like',
                        },
                    }).then((response) => {
                        this.suggestions = (response.data.data || []).filter((tag) => {
                            return ! this.tags.includes(tag.name);
                        });
                    }).catch(() => {
                        this.suggestions = [];
                    }).finally(() => {
                        this.isSearching = false;
                    });
                },
            }
        });
    </script>
@endpushOnce
