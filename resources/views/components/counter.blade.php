{{--
    Bây giờ, chúng ta không cần `data-component` hay `data-props` nữa.
    ComponentRenderer sẽ tự động thêm `wire:id` và `wire:snapshot`.
    JavaScript sẽ sử dụng các thuộc tính `wire:*` để tương tác.
--}}
<div class="flex items-center justify-center space-x-4 p-4 border border-gray-700 rounded-lg bg-gray-800/50">
    <button wire:click="decrement" class="px-4 py-2 text-lg font-bold bg-red-600 text-white rounded-md hover:bg-red-500 transition-colors">-</button>
    <span class="text-3xl font-bold w-12 text-center">{{ $count }}</span>
    <button wire:click="increment" class="px-4 py-2 text-lg font-bold bg-green-600 text-white rounded-md hover:bg-green-500 transition-colors">+</button>
</div>
