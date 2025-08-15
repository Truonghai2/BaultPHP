@props(['member'])

<div class="flex flex-col items-center text-center p-4 bg-gray-800 rounded-lg transition-transform transform hover:-translate-y-1">
    <img class="w-24 h-24 rounded-full mb-4" src="{{ $member['avatar'] }}" alt="{{ $member['name'] }}">
    <h3 class="text-lg font-semibold text-white">{{ $member['name'] }}</h3>
    <p class="text-sm text-indigo-400">{{ $member['role'] }}</p>
</div>
