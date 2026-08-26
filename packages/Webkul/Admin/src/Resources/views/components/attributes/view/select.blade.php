@php
    if (
        $attribute->code === 'lead_sub_source_id'
        && isset($entity)
        && filled($entity->lead_source_id ?? null)
    ) {
        $subSourceIds = app(\Webkul\Lead\Services\SourceAccessService::class)
            ->getAccessibleSubSourceIdsForParent((int) $entity->lead_source_id) ?? [];

        $options = empty($subSourceIds)
            ? collect()
            : app(\Webkul\Lead\Repositories\SourceRepository::class)
                ->getModel()
                ->newQuery()
                ->whereIn('id', $subSourceIds)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']);
    } else {
        $options = $attribute->lookup_type
            ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
            : $attribute->options()->orderBy('sort_order')->get();
    }

    $valueLabel = null;

    if ($attribute->code === 'user_id' && isset($entity)) {
        $valueLabel = $entity->user?->name;
    }
@endphp

<x-admin::form.control-group.controls.inline.select
    ::name="'{{ $attribute->code }}'"
    :value="$value"
    value-label="{{ $valueLabel }}"
    :options="$options"
    rules="required"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
/>
