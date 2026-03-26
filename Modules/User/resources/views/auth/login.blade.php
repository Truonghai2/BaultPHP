@extends('layouts.app')

@section('title', 'Đăng Nhập')

@section('content')
<div class="auth-page login-page flex min-h-screen items-center justify-center px-4 py-12 sm:px-6 lg:px-8 bg-gray-900">
    <div class="w-full max-w-md">
        {{-- Logo và tiêu đề --}}
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-block group">
                <img class="mx-auto h-16 w-auto transform group-hover:scale-105 transition-transform duration-300"
                     src="{{ asset('Images/logo/BaultPHP-icon.png') }}"
                     alt="BaultPHP">
            </a>
            <h2 class="mt-6 text-3xl font-extrabold text-white">
                Chào mừng trở lại
            </h2>
            <p class="mt-2 text-sm text-gray-400">
                Đăng nhập vào tài khoản của bạn để tiếp tục
            </p>
        </div>

        {{-- Form Card: theme tối đồng bộ với trang chủ --}}
        <div class="bg-gray-800/50 backdrop-blur-xl rounded-2xl border border-white/5 shadow-2xl p-8">
            @if(session('success'))
                <div class="mb-6 rounded-xl bg-green-500/10 border border-green-500/20 p-4 animate-fade-in">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-sm font-medium text-green-300">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 rounded-xl bg-red-500/10 border border-red-500/20 p-4 animate-fade-in">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="text-sm font-medium text-red-300">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <form class="space-y-6" action="{{ route('auth.login') }}" method="POST" id="login-form">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-gray-300 mb-2">Email</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                            </svg>
                        </div>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            autocomplete="email"
                            required
                            value="{{ old('email') }}"
                            class="block w-full pl-10 pr-3 py-3 bg-gray-700/50 border border-white/10 rounded-xl text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200 @error('email') border-red-500/50 @enderror"
                            placeholder="you@example.com">
                    </div>
                    @error('email')
                        <p class="mt-2 text-sm text-red-400 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label for="password" class="block text-sm font-semibold text-gray-300">Mật khẩu</label>
                        <a href="#" class="text-sm font-medium text-indigo-400 hover:text-indigo-300 transition duration-200">Quên mật khẩu?</a>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="block w-full pl-10 pr-10 py-3 bg-gray-700/50 border border-white/10 rounded-xl text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition duration-200 @error('password') border-red-500/50 @enderror"
                            placeholder="••••••••">
                        <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                            <svg id="eye-icon" class="h-5 w-5 text-gray-500 hover:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-sm text-red-400 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="flex items-center">
                    <input
                        id="remember"
                        name="remember"
                        type="checkbox"
                        class="h-4 w-4 rounded border-white/20 bg-gray-700 text-indigo-500 focus:ring-indigo-500 cursor-pointer">
                    <label for="remember" class="ml-2 block text-sm text-gray-400 cursor-pointer select-none">Ghi nhớ đăng nhập</label>
                </div>

                <div>
                    <button
                        type="submit"
                        id="login-submit-btn"
                        class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-indigo-500 transition duration-200 hover:scale-[1.02] active:scale-[0.98] shadow-lg shadow-indigo-500/25 disabled:opacity-70 disabled:cursor-not-allowed disabled:hover:scale-100">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-white animate-spin hidden" id="login-spinner" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <svg class="h-5 w-5 text-white group-hover:text-indigo-100 transition-colors duration-200" id="login-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                            </svg>
                        </span>
                        <span id="login-text">Đăng nhập</span>
                    </button>
                </div>
            </form>

            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-white/10"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-800/50 text-gray-500">Hoặc</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-sm text-gray-400">
                    Chưa có tài khoản?
                    <a href="{{ route('auth.register.view') }}" class="font-semibold text-indigo-400 hover:text-indigo-300 transition duration-200">Đăng ký ngay</a>
                </p>
            </div>
        </div>

        <p class="mt-8 text-center text-sm text-gray-500">
            © {{ date('Y') }} BaultPHP. Được bảo vệ bởi hệ thống bảo mật cao cấp.
        </p>
    </div>
</div>

<style>
    @keyframes fade-in {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fade-in 0.3s ease-out;
    }

    .auth-page input:focus {
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.4);
    }

    .auth-page button[type="submit"]:hover:not(:disabled) {
        box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.35);
    }
</style>

<script>
// Wrap in IIFE to avoid global scope pollution and SPA navigation conflicts
(function() {
    'use strict';
    
    // Toggle password visibility
    window.togglePassword = function() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
        if (!passwordInput || !eyeIcon) return;
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>';
        } else {
            passwordInput.type = 'password';
            eyeIcon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>';
        }
    };

    // Auto-hide flash messages
    setTimeout(function() {
        const alerts = document.querySelectorAll('.animate-fade-in');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s ease-out';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // Optimistic UI feedback for login form
    const loginForm = document.getElementById('login-form');
    const submitBtn = document.getElementById('login-submit-btn');
    const loginText = document.getElementById('login-text');
    const loginIcon = document.getElementById('login-icon');
    const loginSpinner = document.getElementById('login-spinner');
    
    if (loginForm && submitBtn) {
        loginForm.addEventListener('submit', function(e) {
            // Show loading state
            submitBtn.disabled = true;
            if (loginText) loginText.textContent = 'Đang đăng nhập...';
            if (loginIcon) loginIcon.classList.add('hidden');
            if (loginSpinner) loginSpinner.classList.remove('hidden');
            
            loginForm.classList.add('opacity-75', 'pointer-events-none');
        });
    }
})();
</script>
@endsection
