<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PricingPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // dijaga middleware role:admin,developer di route
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:255'],
            'tier' => [$required, 'string', 'max:100'],
            'min_price' => [$required, 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric'],
            'price_range' => [$required, 'string', 'max:255'],
            'timeline' => ['nullable', 'string', 'max:255'],
            'badge' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'upgrade_note' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
            'features' => ['sometimes', 'array'],
            'features.*.feature' => ['required', 'string', 'max:255'],
            'features.*.sort_order' => ['nullable', 'integer'],
            'not_included' => ['sometimes', 'array'],
            'not_included.*.feature' => ['required', 'string', 'max:255'],
            'not_included.*.sort_order' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'title, tier, min_price, price_range wajib diisi',
            'tier.required' => 'title, tier, min_price, price_range wajib diisi',
            'min_price.required' => 'title, tier, min_price, price_range wajib diisi',
            'price_range.required' => 'title, tier, min_price, price_range wajib diisi',
            'min_price.numeric' => 'min_price harus berupa angka 0 atau lebih',
            'min_price.min' => 'min_price harus berupa angka 0 atau lebih',
            'max_price.numeric' => 'max_price harus berupa angka atau null (tanpa batas atas)',
        ];
    }
}
