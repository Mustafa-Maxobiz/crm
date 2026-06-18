@foreach ($customAttributes as $attribute)
    @php
        $validations = [];
        $value = isset($entity) ? $entity[$attribute->code] : null;

        if ($attribute->is_required) {
            $validations[] = 'required';
        }

        if ($attribute->type == 'price') {
            $validations[] = 'decimal';
        }

        $validations[] = $attribute->validation;

        $validations = implode('|', array_filter($validations));

        if (
            $attribute->code === 'lead_value'
            && is_numeric($value)
            && str_contains((string) $value, '.')
        ) {
            $value = rtrim(rtrim((string) $value, '0'), '.');
        }
    @endphp

    <x-admin::form.control-group class="mb-2.5 w-full">
        <x-admin::form.control-group.label
            for="{{ $attribute->code }}"
            :class="$attribute->is_required ? 'required' : ''"
        >
            {{ $attribute->name }}

            @if ($attribute->type == 'price')
                <span class="currency-code">({{ core()->currencySymbol(config('app.currency')) }})</span>
            @endif
        </x-admin::form.control-group.label>

        @if (isset($attribute))
            <x-admin::attributes.edit.index
                :attribute="$attribute"
                :validations="$validations"
                :value="$value"
            />
        @endif

        <x-admin::form.control-group.error :control-name="$attribute->code" />
    </x-admin::form.control-group>
@endforeach
