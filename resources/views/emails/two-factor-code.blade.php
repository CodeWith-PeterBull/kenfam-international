{{--
    Two-factor authentication OTP mail (markdown).

    Rendered by App\Notifications\TwoFactorCodeNotification with:
    - $userName       recipient display name
    - $code           plaintext one-time code (exists only in this mail)
    - $expiryMinutes  validity window in minutes
--}}
<x-mail::message>
# Your verification code

Hello {{ $userName }},

Use the code below to complete your sign in to {{ config('app.name') }}.

<x-mail::panel>
<p style="text-align: center; font-size: 30px; font-weight: 700; letter-spacing: 10px; margin: 0;">{{ $code }}</p>
</x-mail::panel>

| Detail | Value |
| :-- | :-- |
| Expires in | {{ $expiryMinutes }} {{ Str::plural('minute', $expiryMinutes) }} |
| Requested at | {{ now()->format('d M Y, H:i') }} ({{ config('app.timezone') }}) |

<x-mail::button :url="route('login')">
Return to sign in
</x-mail::button>

**Security notice:** {{ config('app.name') }} will never ask you for this code by phone, chat, or email. If you did not attempt to sign in, do not share the code and consider changing your password.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
