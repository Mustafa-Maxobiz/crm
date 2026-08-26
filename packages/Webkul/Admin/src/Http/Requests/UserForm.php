<?php

namespace Webkul\Admin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserForm extends FormRequest
{
    protected $rules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $this->rules = [
            'name'                  => 'required',
            'email'                 => 'email|unique:users,email',
            'password'              => 'nullable',
            'password_confirmation' => 'nullable|required_with:password|same:password',
            'status'                => 'sometimes',
            'role_ids'              => 'required|array|min:1',
            'role_ids.*'            => 'integer|exists:roles,id',
            'role_id'               => 'nullable|integer|exists:roles,id',
        ];

        if ($this->method() == 'PUT') {
            $this->rules['email'] = 'email|unique:users,email,'.$this->route('id');
        }

        return $this->rules;
    }
}
