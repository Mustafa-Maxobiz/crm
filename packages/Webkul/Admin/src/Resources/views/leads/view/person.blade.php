{!! view_render_event('admin.leads.view.person.before', ['lead' => $lead]) !!}

@if ($lead?->person)
    <div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
        <x-admin::accordion class="select-none !border-none">
            <x-slot:header class="!p-0">
                <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                    <h4 >@lang('admin::app.leads.view.persons.title')</h4>

                    @if (bouncer()->hasPermission('contacts.persons.edit'))
                        <a
                            href="{{ route('admin.contacts.persons.edit', $lead->person->id) }}"
                            class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                            target="_blank"
                        ></a>
                    @endif
                </div>
            </x-slot>
            
            <x-slot:content class="mt-4 !px-0 !pb-0">
                @php
                    $hasName = !empty($lead->person->name);
                    $hasEmails = collect($lead->person->emails)->filter(fn($email) => !empty($email['value']))->isNotEmpty();
                    $hasContactNumbers = collect($lead->person->contact_numbers)->filter(fn($number) => !empty($number['value']))->isNotEmpty();
                    $hasContactInfo = $hasName || $hasEmails || $hasContactNumbers;
                @endphp

                @if ($hasContactInfo)
                    <div class="flex gap-2">
                        {!! view_render_event('admin.leads.view.person.avatar.before', ['lead' => $lead]) !!}
            
                        <!-- Person Initials -->
                        <x-admin::avatar :name="$lead->person->name ?? 'N/A'" />
            
                        {!! view_render_event('admin.leads.view.person.avatar.after', ['lead' => $lead]) !!}
            
                        <!-- Person Details -->
                        <div class="flex flex-col gap-1">
                            {!! view_render_event('admin.leads.view.person.name.before', ['lead' => $lead]) !!}
            
                            @if ($hasName)
                                <a
                                    href="{{ route('admin.contacts.persons.view', $lead->person->id) }}"
                                    class="font-semibold text-brandColor"
                                    target="_blank"
                                >
                                    {{ $lead->person->name }}
                                </a>
                            @endif
            
                            {!! view_render_event('admin.leads.view.person.name.after', ['lead' => $lead]) !!}
            
                            {!! view_render_event('admin.leads.view.person.job_title.before', ['lead' => $lead]) !!}
            
                            @if ($lead->person->job_title)
                                <span class="dark:text-white">
                                    @if ($lead->person->organization)
                                        @lang('admin::app.leads.view.persons.job-title', [
                                            'job_title'    => $lead->person->job_title,
                                            'organization' => $lead->person->organization->name
                                        ])
                                    @else
                                        {{ $lead->person->job_title }}
                                    @endif
                                </span>
                            @endif
            
                            {!! view_render_event('admin.leads.view.person.job_title.after', ['lead' => $lead]) !!}
                        
                            {!! view_render_event('admin.leads.view.person.email.before', ['lead' => $lead]) !!}
            
                            @foreach ($lead->person->emails as $email)
                                @if (!empty($email['value']))
                                    <div class="flex gap-1">
                                        <a 
                                            class="text-brandColor"
                                            href="mailto:{{ $email['value'] }}"
                                        >
                                            {{ $email['value'] }}
                                        </a>
            
                                        <span class="text-gray-500 dark:text-gray-300">
                                            ({{ $email['label'] }})
                                        </span>
                                    </div>
                                @endif
                            @endforeach
            
                            {!! view_render_event('admin.leads.view.person.email.after', ['lead' => $lead]) !!}
            
                            {!! view_render_event('admin.leads.view.person.contact_numbers.before', ['lead' => $lead]) !!}
                        
                            @foreach ($lead->person->contact_numbers as $contactNumber)
                                @if (!empty($contactNumber['value']))
                                    <div class="flex gap-1">
                                        <a  
                                            class="text-brandColor"
                                            href="callto:{{ $contactNumber['value'] }}"
                                        >
                                            {{ $contactNumber['value'] }}
                                        </a>
            
                                        <span class="text-gray-500 dark:text-gray-300">
                                            ({{ $contactNumber['label'] }})
                                        </span>
                                    </div>
                                @endif
                            @endforeach
            
                            {!! view_render_event('admin.leads.view.person.contact_numbers.after', ['lead' => $lead]) !!}
                        </div>
                    </div>
                @else
                    <!-- Empty State Message -->
                    <div class="flex flex-col items-center justify-center gap-2 py-8">
                        <span class="icon-users text-6xl text-gray-400 dark:text-gray-600"></span>
                        <p class="text-center text-gray-600 dark:text-gray-400">
                            @lang('admin::app.leads.view.persons.empty-message')
                        </p>
                        <p class="text-center text-sm text-gray-500 dark:text-gray-500">
                            @lang('admin::app.leads.view.persons.empty-hint')
                        </p>
                    </div>
                @endif
            </x-slot>
        </x-admin::accordion>
    </div>
@endif
{!! view_render_event('admin.leads.view.person.after', ['lead' => $lead]) !!}