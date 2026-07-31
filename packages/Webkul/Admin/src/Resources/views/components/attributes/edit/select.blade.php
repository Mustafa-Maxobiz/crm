@props([
    'attribute'   => null,
    'value'       => null,
    'validations' => '',
    'disabled'    => false,
])

@php
    $options = $attribute->lookup_type
        ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
        : $attribute->options()->orderBy('sort_order')->get();

    $isDisabled = (bool) $disabled;
@endphp

<x-admin::form.control-group.control
    type="select"
    id="{{ $attribute->code }}"
    name="{{ $attribute->code }}"
    rules="{{ $validations }}"
    :label="$attribute->name"
    :placeholder="$attribute->name"
    :value="old($attribute->code) ?? $value"
    :disabled="$isDisabled"
    :class="$isDisabled ? 'cursor-not-allowed opacity-70' : ''"
>
    @foreach ($options as $option)
        <option value="{{ $option->id }}">
            {{ $option->name }}
        </option>
    @endforeach
</x-admin::form.control-group.control>