@extends('emails.layout')

@section('content')
    <h2>Welcome to {{ config('app.name') }}!</h2>
    
    <p class="mb-3">
        Hello <strong>{{ $user->username ?? 'there' }}</strong>,
    </p>
    
    <p class="mb-4">
        Thank you for joining {{ config('app.name') }}! We're excited to have you on board.
    </p>
    
    <div class="text-center mb-4">
        <a href="{{ $verificationUrl ?? config('app.url') }}" class="button">
            Get Started
        </a>
    </div>
    
    <p>
        If you have any questions, feel free to reach out to our support team.
    </p>
    
    <p class="mb-3">
        Best regards,<br>
        The {{ config('app.name') }} Team
    </p>
@endsection
