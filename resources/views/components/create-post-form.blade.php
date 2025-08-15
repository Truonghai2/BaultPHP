<form wire:submit.prevent="save">
    <div class="mb-4">
        <label for="title" class="block mb-1 font-medium">Tiêu đề</label>
        <input type="text" id="title" wire:model="title" placeholder="Tiêu đề bài viết..." class="border p-2 rounded w-full {{ $errors->has('title') ? 'border-red-500' : '' }}">
        {{-- Hiển thị lỗi nếu có cho trường 'title' --}}
        @error('title') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
    </div>

    <div class="mb-4">
        <label for="content" class="block mb-1 font-medium">Nội dung</label>
        <textarea id="content" wire:model="content" class="border p-2 rounded w-full {{ $errors->has('content') ? 'border-red-500' : '' }}" rows="4"></textarea>
        {{-- Hiển thị lỗi nếu có cho trường 'content' --}}
        @error('content') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
    </div>

    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Lưu</button>
</form>
