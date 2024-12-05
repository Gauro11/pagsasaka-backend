<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_name',
        'is_archived',
    ];

    public static function validateCategory($data)
    {
        $validator = Validator::make($data, [
            'category_name' => ['required', 'string', 'unique:categories,category_name'],
            'is_archived' => ['nullable', 'in:0,1'],
        ]);

        return $validator;
    }

    public static function updatevalidateCategory($data)
    {
        $validator = Validator::make($data, [
            'category_name' => ['sometimes', 'required', 'string'],
            'is_archived' => ['nullable', 'in:0,1'],
        ]);

        return $validator;
    }
}
