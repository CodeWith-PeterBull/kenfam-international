<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'identification_type' => ['nullable', 'required_with:identification_number', Rule::enum(IdentificationType::class)],
            'identification_number' => [
                'nullable',
                'required_with:identification_type',
                'string',
                'max:100',
                Rule::unique('user_profiles', 'identification_number')->ignore($this->user()->profile),
            ],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+() .-]+$/'],
            'job_title' => ['nullable', 'string', 'max:150'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'remove_profile_photo' => ['nullable', 'boolean'],
        ];
    }
}
