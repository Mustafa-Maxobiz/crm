@props([
    'isActive' => false,
    'position' => 'center',
    'size'     => 'normal',
])

<v-modal
    is-active="{{ $isActive }}"
    position="{{ $position }}"
    size="{{ $size }}"
    {{ $attributes }}
>
    @isset($toggle)
        <template v-slot:toggle>
            {{ $toggle }}
        </template>
    @endisset

    @isset($header)
        <template v-slot:header="{ toggle, isOpen }">
            <div {{ $header->attributes->merge(['class' => 'flex items-center justify-between gap-2.5 border-b px-4 py-3 dark:border-gray-800']) }}>
                {{ $header }}

                <span
                    class="icon-cross-large cursor-pointer text-3xl hover:rounded-md hover:bg-gray-100 dark:hover:bg-gray-950"
                    @click="toggle"
                >
                </span>
            </div>
        </template>
    @endisset

    @isset($content)
        <template v-slot:content>
            <div {{ $content->attributes->merge(['class' => 'min-h-0 border-b px-4 py-2.5 dark:border-gray-800']) }}>
                {{ $content }}
            </div>
        </template>
    @endisset

    @isset($footer)
        <template v-slot:footer>
            <div {{ $footer->attributes->merge(['class' => 'flex flex-shrink-0 justify-end border-t border-gray-200 px-4 py-3 dark:border-gray-800']) }}>
                {{ $footer }}
            </div>
        </template>
    @endisset
</v-modal>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-modal-template"
    >
        <div>
            <div @click="toggle">
                <slot name="toggle">
                </slot>
            </div>

            <Teleport
                to="body"
                :disabled="isInsideForm"
            >
                <transition
                    tag="div"
                    name="modal-overlay"
                    enter-class="duration-300 ease-[cubic-bezier(.4,0,.2,1)]"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-class="duration-200 ease-[cubic-bezier(.4,0,.2,1)]"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        class="fixed inset-0 z-[10002] bg-gray-500 bg-opacity-50 transition-opacity"
                        v-show="isOpen"
                    ></div>
                </transition>

                <transition
                    tag="div"
                    name="modal-content"
                    enter-class="duration-300 ease-[cubic-bezier(.4,0,.2,1)]"
                    :enter-from-class="enterFromLeaveToClasses"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-class="duration-300 ease-[cubic-bezier(.4,0,.2,1)]"
                    leave-from-class="translate-y-0 opacity-100"
                    :leave-to-class="enterFromLeaveToClasses"
                >
                    <div
                        class="fixed inset-0 z-[10003] flex items-center justify-center overflow-hidden p-4 transition"
                        v-if="isOpen"
                    >
                        <div
                            class="box-shadow z-[999] flex max-h-full w-full flex-col overflow-hidden rounded-lg bg-white dark:bg-gray-900"
                            :class="sizeClass"
                            :style="{ maxHeight: 'calc(100vh - 2rem)' }"
                        >
                            <!-- Header Slot -->
                            <div class="flex-shrink-0">
                                <slot
                                    name="header"
                                    :toggle="toggle"
                                    :isOpen="isOpen"
                                >
                                </slot>
                            </div>

                            <!-- Content Slot -->
                            <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain">
                                <slot name="content"></slot>
                            </div>
                            
                            <!-- Footer Slot -->
                            <div class="flex-shrink-0">
                                <slot name="footer"></slot>
                            </div>
                        </div>
                    </div>
                </transition>
            </Teleport>
        </div>
    </script>

    <script type="module">
        app.component('v-modal', {
            template: '#v-modal-template',

            props: [
                'isActive',
                'position',
                'size'
            ],

            emits: [
                'toggle',
                'open',
                'close',
            ],

            data() {
                return {
                    isOpen: this.isActive,

                    isMobile: window.innerWidth < 640,

                    isInsideForm: false,
                };
            },

            created() {
                window.addEventListener('resize', this.checkScreenSize);
            },

            mounted() {
                this.isInsideForm = !! this.$el.closest('form');
            },

            beforeUnmount() {
                window.removeEventListener('resize', this.checkScreenSize);
            },

            computed: {
                positionClass() {
                    return {
                        'center': 'items-center justify-center',
                        'top-center': 'top-4',
                        'bottom-center': 'bottom-4',
                        'bottom-right': 'bottom-4 right-4',
                        'bottom-left': 'bottom-4 left-4',
                        'top-right': 'top-4 right-4',
                        'top-left': 'top-4 left-4',
                    }[this.position];
                },

                finalPositionClass() {
                    return this.isMobile 
                        ? 'items-center justify-center' 
                        : this.positionClass;
                },

                sizeClass() {
                    return {
                        'normal': 'max-w-[525px]',
                        'medium': 'max-w-[768px]',
                        'large': 'max-w-[950px]',
                    }[this.size] || 'max-w-[525px]';
                },

                enterFromLeaveToClasses() {
                    const effectivePosition = this.isMobile ? 'center' : this.position;
                    
                    return {
                        'center': '-translate-y-4 opacity-0',
                        'top-center': '-translate-y-4 opacity-0',
                        'bottom-center': 'translate-y-4 opacity-0',
                        'bottom-right': 'translate-y-4 opacity-0',
                        'bottom-left': 'translate-y-4 opacity-0',
                        'top-right': '-translate-y-4 opacity-0',
                        'top-left': '-translate-y-4 opacity-0',
                    }[effectivePosition];
                }
            },

            methods: {
                checkScreenSize() {
                    this.isMobile = window.innerWidth < 640;
                },
                
                toggle() {
                    this.isOpen = ! this.isOpen;

                    if (this.isOpen) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow ='auto';
                    }

                    this.$emit('toggle', { isActive: this.isOpen });
                },

                open() {
                    this.isOpen = true;

                    document.body.style.overflow = 'hidden';

                    this.$emit('open', { isActive: this.isOpen });
                },

                close() {
                    this.isOpen = false;

                    document.body.style.overflow = 'auto';

                    this.$emit('close', { isActive: this.isOpen });
                }
            }
        });
    </script>
@endPushOnce
