@extends('emails.layout')

@section('content')
    <h2>Password Reset Request</h2>
    
    <p class="mb-3">
        Hello <strong>{{ $user->username ?? 'there' }}</strong>,
    </p>
    
    <p class="mb-4">
        You recently requested to reset your password. Click the button below to reset it:
    </p>
    
    <div class="text-center mb-4">
        <a href="{{ $resetUrl }}" class="button">
            Reset Password
        </a>
    </div>
    
    <p class="mb-3">
        This link will expire in {{ $expiresIn ?? 60 }} minutes.
    </p>
    
    <p>
        If you didn't request a password reset, please ignore this email or contact support if you have concerns.
    </p>
    
    <p class="mb-3">
        Best regards,<br>
        The {{ config('app.name') }} Team
    </p>
@endsection
