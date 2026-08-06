@php
    $rawValue = '';
    $displayLabel = '--';

    if (! empty($value)) {
        $carbon = $value instanceof \Carbon\Carbon
            ? $value
            : \Carbon\Carbon::parse($value);

        $rawValue = $carbon->format('Y-m-d H:i:s');
        $displayLabel = core()->formatDate($carbon);
    }
@endphp

<x-admin::form.control-group.controls.inline.datetime
    ::name="'{{ $attribute->code }}'"
    ::value="'{{ $rawValue }}'"
    :value-label="$displayLabel"
    :rules="$attribute->is_required ? 'required' : ''"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
/>
