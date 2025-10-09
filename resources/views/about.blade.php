@extends('layouts.app')

@section('title', 'Giới thiệu về ' . $company['name'])

@section('content')
    <div class="text-center max-w-4xl mx-auto">
        <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight text-white">
            Về {{ $company['name'] }}
        </h1>

        <p class="mt-6 text-lg md:text-xl text-gray-400">
            Thành lập năm {{ $company['founded'] }}
        </p>

        <div class="mt-8 text-left bg-gray-800/50 p-8 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold text-indigo-400">Sứ mệnh của chúng tôi</h2>
            <p class="mt-4 text-gray-300">
                {{ $company['mission'] }}
            </p>
        </div>

        <div class="mt-12">
            <h2 class="text-3xl font-bold tracking-tight text-white">Đội ngũ của chúng tôi</h2>
            <div class="mt-8 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($company['team'] as $member)
                    <x-team-member-card :member="$member" />
                @endforeach
            </div>
        </div>
    </div>
@endsection
