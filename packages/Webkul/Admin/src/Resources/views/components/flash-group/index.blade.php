<v-flash-group ref='flashes'></v-flash-group>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-flash-group-template"
    >
        <transition-group
            tag='div'
            name="flash-group"
            enter-from-class="translate-x-full opacity-0"
            enter-active-class="transform transition duration-300 ease-out"
            enter-to-class="translate-x-0 opacity-100"
            leave-from-class="translate-x-0 opacity-100"
            leave-active-class="transform transition duration-300 ease-in"
            leave-to-class="translate-x-full opacity-0"
            class='fixed z-[10003] flex flex-col items-end gap-2.5'
            style="top: 20px; right: 20px; left: auto; max-width: 400px;"
        >
            <x-admin::flash-group.item />
        </transition-group>
    </script>

    <script type="module">
        app.component('v-flash-group', {
            template: '#v-flash-group-template',

            data() {
                return {
                    uid: 0,

                    flashes: []
                }
            },

            created() {
                @foreach (['success', 'warning', 'error', 'info'] as $key)
                    @if (session()->has($key))
                        this.flashes.push({'type': '{{ $key }}', 'message': "{{ session($key) }}", 'uid':  this.uid++});
                    @endif
                @endforeach

                this.registerGlobalEvents();
            },

            methods: {
                add(flash) {
                    flash.uid = this.uid++;

                    this.flashes.push(flash);
                },

                remove(flash) {
                    let index = this.flashes.indexOf(flash);

                    this.flashes.splice(index, 1);
                },

                registerGlobalEvents() {
                    this.$emitter.on('add-flash', this.add);
                },
            }
        });
    </script>
@endpushOnce