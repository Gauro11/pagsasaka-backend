<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
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
        
        return [
            'org_log_id' => ['required'],
            'name' => ['required'],
            'description' => ['required'],
            'academic_year' => ['required'],
            'submission_date' => ['required']
        ];

    }
}
