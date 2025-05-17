<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

/**
 * Form Request for validating the incoming magic link verification request.
 * Ensures the token format is correct before proceeding to the verification action.
 */
class VerifyLoginTokenRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool Always true, as any user (even guests) can attempt verification.
     */
    public function authorize(): bool
    {
        // Assuming anyone can attempt to verify a token
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => 'required|string|size:60',
            'remember' => 'boolean',
        ];
    }

    /**
     * Get the custom validation messages for the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Provide a generic error message if the token format is wrong,
            // avoiding leaking specifics like the required length.
            'token.size' => 'Incorrect validation token',
            'token.required' => 'Incorrect validation token', // Generic message also for missing token
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * Logs the specific validation errors for internal debugging and redirects the user
     * back to the login page with a generic error message flashed to the session.
     * This prevents exposing detailed validation failure reasons to the user.
     *
     * @param  Validator  $validator  The validator instance containing the errors.
     * @return void
     *
     * @throws HttpResponseException Redirects the user back to the login route with errors.
     */
    protected function failedValidation(Validator $validator)
    {
        // Log the actual validation errors internally for debugging purposes.
        Log::warning('Login token request validation failed:', [
            // No user info available here
            'ip_address' => request()->ip(), // Use request helper
            'user_agent' => request()->header('User-Agent'), // Use request helper
            'errors' => $validator->errors()->toArray(),
        ]);

        // Instead of letting Laravel redirect with the validation error (default behavior),
        // throw a response exception that redirects back to login but flashes a
        // specific, generic error message derived from the `messages()` method.
        // This prevents revealing *why* the format validation failed to the user.
        throw new HttpResponseException(
            redirect()->route('login')
                ->withErrors(['token' => $this->messages()['token.required'] ?? 'Incorrect validation token'])
        );
    }
}
