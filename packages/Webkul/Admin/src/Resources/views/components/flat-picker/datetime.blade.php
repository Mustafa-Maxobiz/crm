<v-datetime-picker {{ $attributes }}>
    {{ $slot }}
</v-datetime-picker>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-datetime-picker-template"
    >
        <span class="relative inline-block w-full">
            <slot></slot>

            <i class="icon-calendar pointer-events-none absolute top-1/2 -translate-y-1/2 text-2xl text-gray-400 ltr:right-2 rtl:left-2"></i>
        </span>
    </script>

    <script type="module">
        app.component('v-datetime-picker', {
            template: '#v-datetime-picker-template',

            props: {
                name: String,

                value: String,

                allowInput: {
                    type: Boolean,
                    default: true,
                },

                disable: Array,

                minDate: String,

                maxDate: String,
            },

            data: function() {
                return {
                    datepicker: null
                };
            },

            mounted: function() {
                let options = this.setOptions();

                this.activate(options);
            },

            methods: {
                setOptions: function() {
                    let self = this;
                    let syncInputValue = function(date, instance) {
                        let dateStr = date instanceof Date
                            ? instance.formatDate(date, instance.config.dateFormat)
                            : date;
                        let displayValue = date instanceof Date
                            ? instance.formatDate(date, instance.config.altFormat)
                            : dateStr;

                        instance.input.value = dateStr;

                        if (instance.altInput) {
                            instance.altInput.value = displayValue;
                        }

                        instance.input.dispatchEvent(new Event('input', { bubbles: true }));
                        instance.input.dispatchEvent(new Event('change', { bubbles: true }));

                        self.$emit("onChange", dateStr);
                    };
                    let dateWithCurrentTime = function(instance) {
                        let selectedDate = instance.selectedDates?.[0]
                            || instance.latestSelectedDateObj
                            || new Date();
                        let date = new Date(selectedDate.getTime());
                        let hour = Number.parseInt(instance.hourElement?.value, 10);
                        let minute = Number.parseInt(instance.minuteElement?.value, 10);

                        if (Number.isNaN(hour)) {
                            hour = date.getHours();
                        }

                        if (Number.isNaN(minute)) {
                            minute = date.getMinutes();
                        }

                        if (! instance.config.time_24hr && instance.amPM) {
                            let meridian = String(instance.amPM.textContent || instance.amPM.value || '').toUpperCase();

                            if (meridian === 'PM' && hour < 12) {
                                hour += 12;
                            }

                            if (meridian === 'AM' && hour === 12) {
                                hour = 0;
                            }
                        }

                        date.setHours(hour, minute, 0, 0);

                        return date;
                    };
                    let syncCurrentValue = function(instance) {
                        let date = dateWithCurrentTime(instance);

                        instance.selectedDates = [date];
                        instance.latestSelectedDateObj = date;

                        syncInputValue(date, instance);
                    };
                    let bindTimeSync = function(instance) {
                        if (! instance.calendarContainer || instance.__maxobizTimeSyncBound) {
                            return;
                        }

                        instance.__maxobizTimeSyncBound = true;

                        ['input', 'change', 'keyup', 'mouseup', 'click'].forEach(function(eventName) {
                            instance.calendarContainer.addEventListener(eventName, function(event) {
                                if (! event.target.closest('.flatpickr-time')) {
                                    return;
                                }

                                setTimeout(function() {
                                    syncCurrentValue(instance);
                                }, 0);
                            });
                        });
                    };

                    return {
                        allowInput: this.allowInput ?? true,
                        disable: this.disable ?? [],
                        minDate: this.minDate ?? '',
                        maxDate: this.maxDate ?? '',
                        altInput: true,
                        altFormat: "Y-m-d h:i K",
                        dateFormat: "Y-m-d H:i:S",
                        enableTime: true,
                        time_24hr: false,
                        weekNumbers: true,

                        onChange: function(selectedDates, dateStr, instance) {
                            syncInputValue(selectedDates[0] || dateStr, instance);
                        },

                        onValueUpdate: function(selectedDates, dateStr, instance) {
                            syncInputValue(selectedDates[0] || dateStr, instance);
                        },

                        onClose: function(selectedDates, dateStr, instance) {
                            syncInputValue(selectedDates[0] || dateStr, instance);
                        },

                        onReady: function(selectedDates, dateStr, instance) {
                            bindTimeSync(instance);
                        },

                        onOpen: function(selectedDates, dateStr, instance) {
                            bindTimeSync(instance);
                        }
                    };
                },

                activate: function(options) {
                    let element = this.$el.getElementsByTagName("input")[0];

                    this.datepicker = new Flatpickr(element, options);
                },

                clear: function() {
                    this.datepicker.clear();
                }
            }
        });
    </script>
@endPushOnce
