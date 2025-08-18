@extends('layouts.app')

@section('title', 'Log In')

@section('content')
<div class="flex min-h-full flex-col justify-center px-6 py-12 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-sm">
        <a href="{{ route('home') }}">
            <img class="mx-auto h-16 w-auto" src="{{ asset('images/logo/BaultPHP.png') }}" alt="BaultPHP">
        </a>
        <h2 class="mt-10 text-center text-2xl font-bold leading-9 tracking-tight text-white">
            Sign in to your account
        </h2>
    </div>

    <div class="mt-10 sm:mx-auto sm:w-full sm:max-w-sm">
        <form class="space-y-6" action="{{ route('auth.login') }}" method="POST">
            {{-- CSRF token would go here if implemented in the future --}}

            <div>
                <label for="email" class="block text-sm font-medium leading-6 text-white">Email address</label>
                <div class="mt-2">
                    <input id="email" name="email" type="email" autocomplete="email" required
                           class="block w-full rounded-md border-0 bg-white/5 py-1.5 text-white shadow-sm ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6"
                           value="{{ old('email') }}">
                </div>
                @if(isset($errors) && $errors->has('email'))
                    <p class="mt-2 text-sm text-red-400">{{ $errors->first('email') }}</p>
                @endif
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium leading-6 text-white">Password</label>
                    <div class="text-sm">
                        <a href="#" class="font-semibold text-indigo-400 hover:text-indigo-300">Forgot password?</a>
                    </div>
                </div>
                <div class="mt-2">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           class="block w-full rounded-md border-0 bg-white/5 py-1.5 text-white shadow-sm ring-1 ring-inset ring-white/10 focus:ring-2 focus:ring-inset focus:ring-indigo-500 sm:text-sm sm:leading-6">
                </div>
            </div>

            <div class="flex items-center">
                <input id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600 bg-white/10">
                <label for="remember" class="ml-3 block text-sm leading-6 text-gray-300">Remember me</label>
            </div>

            <div>
                <button type="submit"
                        class="flex w-full justify-center rounded-md bg-indigo-500 px-3 py-1.5 text-sm font-semibold leading-6 text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500">
                    Sign in
                </button>
            </div>
        </form>

        <p class="mt-10 text-center text-sm text-gray-400">
            Not a member?
            <a href="#" class="font-semibold leading-6 text-indigo-400 hover:text-indigo-300">Register here</a>
        </p>
    </div>
</div>
@endsection
