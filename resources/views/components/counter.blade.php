{{-- File view cho Counter component --}}
<div>
    <button wire:click="increment" class="px-4 py-2 font-bold text-white bg-blue-500 rounded hover:bg-blue-700">+</button>
    <h1 class="inline-block ml-4 text-2xl">{{ $count }}</h1>
</div>
