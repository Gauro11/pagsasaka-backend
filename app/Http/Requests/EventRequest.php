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
        
        $currentYear = now()->format('Y');

        return [
            'org_log_id' => ['required', 'exists:organizational_logs,id'],
            'name' => ['required'],
            'description' => ['required'],
            'academic_year' => [
                'required',
                'string',
                'regex:/^' . $currentYear . '-\d{4}$/',
            ],
            'submission_date' => [
                'required',
                'string',
                'date_format:F j Y', 
                'after_or_equal:' . now()->format('F j Y') 
            ]
        ];
        
        

    }
}
