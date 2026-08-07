{!! view_render_event('admin.leads.view.attributes.before', ['lead' => $lead]) !!}

@php
    $lockedLeadAttributeCodes = [
        'lead_source_id',
        'lead_type_id',
        'lead_sub_source_id',
        'industry',
    ];

    $hiddenAttributeCodes = [
        'title',
        'companies',
        'organization_id',
        'description',
        'lead_pipeline_id',
        'lead_pipeline_stage_id',
        'source_sub_type',
        'service_offered',
    ];

    if (lead_variant() === 'sdr') {
        $hiddenAttributeCodes[] = 'lead_value';
    }
@endphp

<div class="flex w-full flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-800">
    <x-admin::accordion class="select-none !border-none">
        <x-slot:header class="!p-0">
            <div class="flex w-full items-center justify-between gap-4 font-semibold dark:text-white">
                <h4>@lang('admin::app.leads.view.attributes.title')</h4>
                
                @if (bouncer()->hasPermission(lead_permission('edit')))
                    <a
                        href="{{ lead_route('edit', $lead->id) }}"
                        class="icon-edit rounded-md p-1.5 text-2xl transition-all hover:bg-gray-100 dark:hover:bg-gray-950"
                        target="_blank"
                    ></a>
                @endif
            </div>
        </x-slot>

        <x-slot:content class="mt-4 !px-0 !pb-0">
            {!! view_render_event('admin.leads.view.attributes.form_controls.before', ['lead' => $lead]) !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, () => {})">
                    {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.before', ['lead' => $lead]) !!}

                    @if (lead_variant() === 'main')
                        <div class="mb-1 flex flex-col gap-1">
                            <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                                <div class="label dark:text-white">
                                    @lang('admin::app.leads.create.title-field')
                                </div>

                                <div class="font-medium dark:text-white">
                                    <x-admin::form.control-group.controls.inline.text
                                        type="inline"
                                        ::name="'title'"
                                        :value="$lead->title ?? ''"
                                        :value-label="($lead->title ?? '') === '' ? '--' : $lead->title"
                                        position="left"
                                        rules="required"
                                        :label="trans('admin::app.leads.create.title-field')"
                                        :placeholder="trans('admin::app.leads.create.title-field')"
                                        ::errors="errors"
                                        :url="lead_route('attributes.update', $lead->id)"
                                        :allow-edit="bouncer()->hasPermission(lead_permission('edit'))"
                                    />
                                </div>
                            </div>
                        </div>
                    @endif
        
                    <x-admin::attributes.view
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            'entity_type' => 'leads',
                            ['code', 'NOTIN', $hiddenAttributeCodes]
                        ])"
                        :entity="$lead"
                        :url="lead_route('attributes.update', $lead->id)"
                        :allow-edit="true"
                        :locked-attribute-codes="$lockedLeadAttributeCodes"
                    />

                    <div class="mt-3">
                        @include('admin::leads.common.services', ['lead' => $lead])
                    </div>
        
                    {!! view_render_event('admin.leads.view.attributes.form_controls.attributes.view.after', ['lead' => $lead]) !!}
                </form>
            </x-admin::form>
        
            {!! view_render_event('admin.leads.view.attributes.form_controls.after', ['lead' => $lead]) !!}
        </x-slot>
    </x-admin::accordion>
</div>

{!! view_render_event('admin.leads.view.attributes.before', ['lead' => $lead]) !!}
