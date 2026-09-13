<?php

namespace App\Http\Requests\Admin;

use App\Support\CmsPermission;
use Illuminate\Foundation\Http\FormRequest;

class SendTestMailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(CmsPermission::SEND_TEST_NOTIFICATIONS) === true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
        ];
    }
}
