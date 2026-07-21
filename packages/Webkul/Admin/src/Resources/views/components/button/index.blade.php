<v-button {{ $attributes }}></v-button>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-button-template"
    >
        <button
            v-if="! loading"
            v-bind="buttonAttrs"
            :type="buttonNativeType"
            :class="[buttonClass, '']"
        >
            @{{ title }}
        </button>

        <button
            v-else
            v-bind="buttonAttrs"
            :type="buttonNativeType"
            :class="[buttonClass, '']"
        >
            <!-- Spinner -->
            <x-admin::spinner class="absolute" />

            <span class="relative h-full w-full opacity-0">
                @{{ title }}
            </span>
        </button>
    </script>

    <script type="module">
        app.component('v-button', {
            template: '#v-button-template',

            inheritAttrs: false,

            props: {
                loading: Boolean,
                buttonType: String,
                title: String,
                buttonClass: String,
            },

            computed: {
                buttonNativeType() {
                    return this.buttonType || this.$attrs.type;
                },

                buttonAttrs() {
                    const { type, ...attrs } = this.$attrs;

                    return attrs;
                },
            },
        });
    </script>
@endPushOnce
