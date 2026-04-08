<x-mail::message>
# Login to AdmixCentral

Click the button below to log in to your account. This link will expire in 15 minutes.

<x-mail::button :url="$url">
Log in securely
</x-mail::button>

If you didn't request this email, you can safely ignore it.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>