<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccountRequest extends FormRequest
{
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
        $id = $this->route('account'); 

        return [
            'name' => 'required|min:5|max:150',
            'email' => [
                'required',
                'email',
                 Rule::unique('accounts')->ignore($id) // For updates, make sure $id is properly set
            ],
            'password' => 'required',
            'role' => 'required',
            'entityid' => 'required'
        ];
    }
}
