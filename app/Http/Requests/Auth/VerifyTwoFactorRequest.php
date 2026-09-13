<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the email-OTP challenge submission.
 *
 * The requester is a guest mid-challenge (identified only by the
 * `two_factor:user_id` session key), so authorization is unconditional here;
 * TwoFactorController re-resolves and re-checks the pending user itself.
 */
class VerifyTwoFactorRequest extends FormRequest
{
    /**
     * The challenge is submitted by design from an unauthenticated context.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The code must be exactly the configured number of digits — this also
     * hard-rejects non-numeric payloads before they reach the service.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits:'.(int) config('two-factor.code.length', 6)],
        ];
    }

    /**
     * Human-readable validation messages for the challenge screen.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $length = (int) config('two-factor.code.length', 6);

        return [
            'code.required' => __('Enter the verification code from your email.'),
            'code.digits' => __('The verification code must be exactly :length digits.', ['length' => $length]),
        ];
    }
}
