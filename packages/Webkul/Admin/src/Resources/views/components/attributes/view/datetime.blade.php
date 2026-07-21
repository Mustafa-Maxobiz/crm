<x-admin::form.control-group.controls.inline.datetime
    ::name="'{{ $attribute->code }}'"
    ::value="'{{ $value }}'"
    :rules="$attribute->is_required ? 'required' : ''"
    position="left"
    :label="$attribute->name"
    ::errors="errors"
    :placeholder="$attribute->name"
    :url="$url"
    :allow-edit="$allowEdit"
/>
