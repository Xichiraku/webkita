<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public const CATEGORIES = ['website', 'mobile', 'desktop', 'design', 'marketing', 'other'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_title' => ['nullable', 'string', 'max:255'],
            'price_range' => ['nullable', 'string', 'max:255'],
            'project_title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(self::CATEGORIES)],
            'description' => ['required', 'string', 'min:100'],
            'skills' => ['required', 'string'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:30'],
            'additional_notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'project_title.required' => 'Project title wajib diisi',
            'category.required' => 'Category wajib diisi',
            'category.in' => 'Category tidak valid',
            'description.required' => 'Description minimal 100 karakter',
            'description.min' => 'Description minimal 100 karakter',
            'skills.required' => 'Skills wajib diisi',
            'contact_name.required' => 'Contact name wajib diisi',
            'contact_phone.required' => 'Contact phone wajib diisi',
        ];
    }
}
