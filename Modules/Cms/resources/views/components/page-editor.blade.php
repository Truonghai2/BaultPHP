<div>
    <div class="mb-4">
        <h2 class="text-2xl font-bold">Editing: {{ $page->title }}</h2>
    </div>

    <div class="mb-6 p-4 border rounded-md shadow-sm">
        <label for="featuredImage" class="block font-semibold mb-2">Featured Image</label>
        <input type="file" id="featuredImage" wire:model="featuredImage">

        {{-- Hiển thị ảnh preview nếu có --}}
        @if ($featuredImage)
            <div class="mt-4">
                @if (is_string($featuredImage))
                    {{-- Ảnh đã lưu --}}
                    <img src="{{ asset('storage/' . $featuredImage) }}" alt="Current Image" class="max-w-xs rounded">
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-4">
        @foreach($blocks as $index => $block)
            <div wire:key="block-{{ $index }}" class="p-4 border rounded-md shadow-sm">
                <div class="flex justify-between items-center mb-2">
                    <span class="font-semibold capitalize">{{ $block['type'] }} Block</span>
                    <button wire:click="removeBlock({{ $index }})" class="text-red-500 hover:text-red-700">&times;</button>
                </div>
                @if($block['type'] === 'text')
                    <textarea wire:model.debounce.500ms="blocks.{{ $index }}.content" class="w-full p-2 border rounded @error('blocks.' . $index . '.content') border-red-500 @enderror"></textarea>
                @elseif($block['type'] === 'image')
                    <input type="text" wire:model.debounce.500ms="blocks.{{ $index }}.content" placeholder="Image URL" class="w-full p-2 border rounded @error('blocks.' . $index . '.content') border-red-500 @enderror">
                @endif

                {{-- Hiển thị lỗi cho trường content của khối hiện tại --}}
                @error('blocks.' . $index . '.content') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
            </div>
        @endforeach
    </div>

    <div class="mt-6 border-t pt-4 flex items-center justify-between">
        <div>
            <select wire:model="newBlockType" class="p-2 border rounded">
                <option value="text">Text Block</option>
                <option value="image">Image Block</option>
            </select>
            <button wire:click="addBlock" class="ml-2 px-4 py-2 font-bold text-white bg-green-500 rounded hover:bg-green-700">Add Block</button>
        </div>
        <button wire:click="save" class="px-6 py-2 font-bold text-white bg-indigo-500 rounded hover:bg-indigo-700">Save Page</button>
    </div>
</div>
