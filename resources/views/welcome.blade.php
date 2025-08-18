@extends('layouts.app')

@section('title', 'Welcome to BaultPHP')

@section('content')
<div class="relative isolate overflow-hidden">
    {{-- Background Effects --}}
    <div class="absolute inset-x-0 -top-40 -z-10 transform-gpu overflow-hidden blur-3xl sm:-top-80" aria-hidden="true">
        <div class="relative left-[calc(50%-11rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 rotate-[30deg] bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-20 sm:left-[calc(50%-30rem)] sm:w-[72.1875rem]"></div>
    </div>

    {{-- Hero Section --}}
    <div class="mx-auto max-w-7xl px-6 pb-24 pt-10 sm:pb-32 lg:flex lg:px-8 lg:py-40">
        <div class="mx-auto max-w-2xl lg:mx-0 lg:max-w-xl lg:flex-shrink-0 lg:pt-8">
            <div class="flex items-center gap-x-4">
                <img class="h-12" src="{{ asset('images/logo/BaultPHP.png') }}" alt="BaultPHP">
                <div class="rounded-full px-3 py-1 text-sm leading-6 text-gray-300 ring-1 ring-gray-700/10">
                    Just Released <a href="#" class="font-semibold text-indigo-400">v1.0.0 <span aria-hidden="true">&rarr;</span></a>
                </div>
            </div>
            <h1 class="mt-10 text-4xl font-bold tracking-tight text-white sm:text-6xl">
                Build Fast, Scale Easy with BaultPHP
            </h1>
            <p class="mt-6 text-lg leading-8 text-gray-300">
                A modern, high-performance PHP framework designed for building scalable applications. Powered by Swoole, DDD architecture, and cutting-edge features.
            </p>
            <div class="mt-10 flex items-center gap-x-6">
                <a href="#" class="rounded-md bg-indigo-500 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400">
                    Quick Start
                </a>
                <a href="https://github.com/Truonghai2/BaultPHP" target="_blank" class="group text-sm font-semibold leading-6 text-white">
                    View on GitHub
                    <span class="inline-block transition-transform group-hover:translate-x-1" aria-hidden="true">→</span>
                </a>
            </div>
        </div>

        {{-- Code Preview Section --}}
        <div class="mx-auto mt-16 flex max-w-2xl sm:mt-24 lg:ml-10 lg:mt-0 lg:mr-0 lg:max-w-none lg:flex-none xl:ml-32">
            <div class="max-w-3xl flex-none sm:max-w-5xl lg:max-w-none">
                <div class="-m-2 rounded-xl bg-gray-900/5 p-2 ring-1 ring-inset ring-gray-900/10 lg:-m-4 lg:rounded-2xl lg:p-4">
                    <div class="relative w-full md:w-[36rem] lg:w-[76rem] rounded-md bg-gray-800/90 p-4 shadow-2xl ring-1 ring-white/10">
                        {{-- Code Header --}}
                        <div class="flex items-center justify-between mb-4 px-4">
                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            </div>
                            <div class="text-sm text-gray-400">CreateUser.php</div>
                        </div>

                        {{-- Line Numbers + Code --}}
                        <div class="relative overflow-x-auto" style="max-height: 480px;">
                            <pre class="code-with-line-numbers text-sm line-numbers"><code class="language-php">namespace App\Modules\User\Domain\UseCases;

class CreateUser implements CommandHandler
{
    public function handle(CreateUserCommand $command): User
    {
        // Validate input using value objects
        $email = new Email($command->email);
        $password = new Password($command->password);

        // Create user entity
        $user = new User(
            UserId::generate(),
            $email,
            $password
        );

        // Save and dispatch events
        $this->repository->save($user);
        $this->eventDispatcher->dispatch(
            new UserCreated($user)
        );

        return $user;
    }
}</code></pre>
                        </div>

                        {{-- Code Footer --}}
                        <div class="mt-4 flex flex-col sm:flex-row items-center justify-between px-4 py-2 border-t border-gray-700/50">
                            <div class="flex items-center space-x-2 text-xs text-gray-400 mb-2 sm:mb-0">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                                </svg>
                                <span>DDD Example</span>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="text-xs text-gray-400">PHP 8.2+</span>
                                <span class="text-xs px-2 py-1 rounded-full bg-indigo-500/20 text-indigo-400">Domain Logic</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Features Grid --}}
    <div class="mx-auto mt-32 max-w-7xl px-6 sm:mt-40 lg:px-8">
        <div class="mx-auto max-w-2xl lg:text-center">
            <h2 class="text-base font-semibold leading-7 text-indigo-400">Production Ready</h2>
            <p class="mt-2 text-3xl font-bold tracking-tight text-white sm:text-4xl">
                Everything you need for modern PHP development
            </p>
            <p class="mt-6 text-lg leading-8 text-gray-300">
                BaultPHP combines best practices from DDD, CQRS, and Event Sourcing with modern PHP features to deliver a robust development experience.
            </p>
        </div>

        {{-- Feature Cards --}}
        <div class="mx-auto mt-16 max-w-2xl sm:mt-20 lg:mt-24 lg:max-w-none">
            <dl class="grid max-w-xl grid-cols-1 gap-x-8 gap-y-16 lg:max-w-none lg:grid-cols-3">
                {{-- Performance Card --}}
                <div class="flex flex-col rounded-lg bg-gray-800/50 p-6 ring-1 ring-white/10 transition-all hover:bg-gray-800/70">
                    <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-white">
                        <svg class="h-5 w-5 flex-none text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.31h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z" clip-rule="evenodd" />
                        </svg>
                        High Performance
                    </dt>
                    <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-300">
                        <p class="flex-auto">Built on Swoole for lightning-fast response times. Handle thousands of concurrent connections with ease.</p>
                    </dd>
                </div>

                {{-- Architecture Card --}}
                <div class="flex flex-col rounded-lg bg-gray-800/50 p-6 ring-1 ring-white/10 transition-all hover:bg-gray-800/70">
                    <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-white">
                        <svg class="h-5 w-5 flex-none text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3.5 2A1.5 1.5 0 002 3.5V15a3 3 0 106 0V3.5A1.5 1.5 0 006.5 2h-3zm11.753 6.99L9.5 14.743V6.257l1.51-1.51a1.5 1.5 0 012.122 0l2.121 2.121a1.5 1.5 0 010 2.122zM8.364 18H16.5a1.5 1.5 0 001.5-1.5v-3a1.5 1.5 0 00-1.5-1.5h-2.136l-6 6zM5 16a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                        </svg>
                        Domain Driven Design
                    </dt>
                    <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-300">
                        <p class="flex-auto">Built with DDD principles. Organize your business logic into clear, maintainable domains.</p>
                    </dd>
                </div>

                {{-- Modularity Card --}}
                <div class="flex flex-col rounded-lg bg-gray-800/50 p-6 ring-1 ring-white/10 transition-all hover:bg-gray-800/70">
                    <dt class="flex items-center gap-x-3 text-base font-semibold leading-7 text-white">
                        <svg class="h-5 w-5 flex-none text-indigo-400" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M2 4.25A2.25 2.25 0 014.25 2h11.5A2.25 2.25 0 0118 4.25v8.5A2.25 2.25 0 0115.75 15h-3.105a3.501 3.501 0 001.1 1.677A.75.75 0 0113.26 18H6.74a.75.75 0 01-.484-1.323A3.501 3.501 0 007.355 15H4.25A2.25 2.25 0 012 12.75v-8.5zm1.5 0a.75.75 0 01.75-.75h11.5a.75.75 0 01.75.75v7.5a.75.75 0 01-.75.75H4.25a.75.75 0 01-.75-.75v-7.5z" />
                        </svg>
                        Plugin System
                    </dt>
                    <dd class="mt-4 flex flex-auto flex-col text-base leading-7 text-gray-300">
                        <p class="flex-auto">Extend functionality with a powerful plugin system. Install and manage modules with ease.</p>
                    </dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Stats Section --}}
    <div class="mx-auto mt-32 max-w-7xl px-6 sm:mt-40 lg:px-8">
        <div class="mx-auto max-w-2xl lg:max-w-none">
            <div class="text-center">
                <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                    Trusted by developers worldwide
                </h2>
                <p class="mt-4 text-lg leading-8 text-gray-300">
                    Join the growing community of developers building with BaultPHP
                </p>
            </div>
            <dl class="mt-16 grid grid-cols-1 gap-0.5 overflow-hidden rounded-2xl text-center sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex flex-col bg-gray-800/50 p-8">
                    <dt class="text-sm font-semibold leading-6 text-gray-300">Active Installations</dt>
                    <dd class="order-first text-3xl font-semibold tracking-tight text-white">12k+</dd>
                </div>
                <div class="flex flex-col bg-gray-800/50 p-8">
                    <dt class="text-sm font-semibold leading-6 text-gray-300">GitHub Stars</dt>
                    <dd class="order-first text-3xl font-semibold tracking-tight text-white">5k+</dd>
                </div>
                <div class="flex flex-col bg-gray-800/50 p-8">
                    <dt class="text-sm font-semibold leading-6 text-gray-300">Contributors</dt>
                    <dd class="order-first text-3xl font-semibold tracking-tight text-white">250+</dd>
                </div>
                <div class="flex flex-col bg-gray-800/50 p-8">
                    <dt class="text-sm font-semibold leading-6 text-gray-300">Available Plugins</dt>
                    <dd class="order-first text-3xl font-semibold tracking-tight text-white">100+</dd>
                </div>
            </dl>
        </div>
    </div>

    {{-- Background Effects Bottom --}}
    <div class="absolute inset-x-0 top-[calc(100%-13rem)] -z-10 transform-gpu overflow-hidden blur-3xl sm:top-[calc(100%-30rem)]" aria-hidden="true">
        <div class="relative left-[calc(50%+3rem)] aspect-[1155/678] w-[36.125rem] -translate-x-1/2 bg-gradient-to-tr from-[#ff80b5] to-[#9089fc] opacity-20 sm:left-[calc(50%+36rem)] sm:w-[72.1875rem]"></div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.css">
<style>
    /* Code container styling */
    .code-with-line-numbers {
        background: #1e1e1e !important;
        border-radius: 0.5rem;
        margin: 0 !important;
        padding: 1.5rem 0 !important;
    }

    /* Base code styling */
    pre[class*="language-"] {
        background: transparent !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    code[class*="language-"] {
        text-shadow: none !important;
        font-family: 'JetBrains Mono', Consolas, Monaco, monospace !important;
        font-size: 0.875rem !important;
        line-height: 1.6 !important;
        tab-size: 4;
    }

    /* Line numbers container */
    .line-numbers .line-numbers-rows {
        top: -1.5rem !important;
        left: 0 !important;
        min-width: 3rem !important;
        border-right: 1px solid #404040 !important;
        padding: 1.5rem 0 !important;
        background: rgba(0, 0, 0, 0.2) !important;
    }

    /* Line number styling */
    .line-numbers-rows > span:before {
        color: #858585 !important;
        text-align: center !important;
        padding-right: 0.8em !important;
    }

    /* Token colors */
    .token.comment { color: #6A9955 !important; }
    .token.keyword { color: #569CD6 !important; }
    .token.string { color: #CE9178 !important; }
    .token.function { color: #DCDCAA !important; }
    .token.class-name { color: #4EC9B0 !important; }
    .token.variable { color: #9CDCFE !important; }
    .token.operator { color: #D4D4D4 !important; }
    .token.punctuation { color: #D4D4D4 !important; }

    /* Code block scrollbar */
    .code-with-line-numbers::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .code-with-line-numbers::-webkit-scrollbar-track {
        background: #1e1e1e;
        border-radius: 4px;
    }

    .code-with-line-numbers::-webkit-scrollbar-thumb {
        background: #424242;
        border-radius: 4px;
        border: 2px solid #1e1e1e;
    }

    .code-with-line-numbers::-webkit-scrollbar-thumb:hover {
        background: #4f4f4f;
    }

    /* Code block padding */
    .code-with-line-numbers code {
        padding: 0 1.5rem 0 4rem !important;
        display: block;
    }

    /* Fix Firefox scrollbar */
    .code-with-line-numbers {
        scrollbar-width: thin;
        scrollbar-color: #424242 #1e1e1e;
    }

    /* Add some space between line numbers and code */
    .line-numbers-rows {
        margin-right: 1rem;
    }

    /* Override Prism default padding */
    pre[class*="language-"].line-numbers {
        padding-left: 0 !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/line-numbers/prism-line-numbers.min.js"></script>
<script>
    // Reinitialize Prism after page load
    document.addEventListener('DOMContentLoaded', (event) => {
        Prism.highlightAll();
    });
</script>
@endpush