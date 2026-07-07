<?php

namespace App\Http\Requests;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Optional 10-digit Indian mobile; stored normalised as +91XXXXXXXXXX.
            'phone' => ['nullable', 'string', 'regex:/^(\+?91)?[6-9]\d{9}$/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.'];
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->phone)) {
            $digits = preg_replace('/\D/', '', (string) $this->phone);
            $this->merge(['phone' => preg_replace('/^(91|0)(?=[6-9]\d{9}$)/', '', $digits)]);
        }
    }
}
