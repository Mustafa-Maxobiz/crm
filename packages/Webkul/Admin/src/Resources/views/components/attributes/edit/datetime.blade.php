@php
    $dateTimeRules = ($validations ? $validations.'|' : '').'regex:^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$';
@endphp

<x-admin::form.control-group.control
    type="datetime"
    :id="$attribute->code"
    :name="$attribute->code"
    :value="old($attribute->code) ?? $value"
    :rules="$dateTimeRules"
    :label="$attribute->name"
/>
