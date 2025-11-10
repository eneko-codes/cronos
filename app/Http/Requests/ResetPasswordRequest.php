<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Anyone can reset password with valid token
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:16|confirmed',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $password = $validator->getData()['password'] ?? null;

            if ($password) {
                // Check for uppercase letter
                if (! preg_match('/[A-Z]/', $password)) {
                    $validator->errors()->add('password', 'The password must contain at least one uppercase letter.');
                }

                // Check for lowercase letter
                if (! preg_match('/[a-z]/', $password)) {
                    $validator->errors()->add('password', 'The password must contain at least one lowercase letter.');
                }

                // Check for number
                if (! preg_match('/\d/', $password)) {
                    $validator->errors()->add('password', 'The password must contain at least one number.');
                }

                // Check for special character
                if (! preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
                    $validator->errors()->add('password', 'The password must contain at least one special character.');
                }
            }
        });
    }
}
