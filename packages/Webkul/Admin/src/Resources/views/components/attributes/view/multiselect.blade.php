@php
    $options = $attribute->lookup_type
        ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
        : $attribute->options()->orderBy('sort_order')->get(['id', 'name']);

    $selectedOption = old($attribute->code) ?: $value;

    $canAddNew = $attribute->code === 'service_offered' && (
        bouncer()->hasPermission('settings.lead.services_offered.create')
        || bouncer()->hasPermission('leads.create')
        || bouncer()->hasPermission('leads.edit')
        || strtolower((string) auth()->guard('user')->user()?->role?->name) === 'sdr'
    );

    $storeUrl = $attribute->code === 'service_offered'
        ? route('admin.leads.services_offered.store')
        : null;
@endphp

<x-admin::form.control-group.controls.inline.multiselect
    ::name="'{{ $attribute->code }}'"
    ::value="'{{ is_array($selectedOption) ? implode(',', $selectedOption) : $selectedOption }}'"
    :data="$options"
    rules="required"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
    :can-add-new="$canAddNew"
    :store-url="$storeUrl"
/>
