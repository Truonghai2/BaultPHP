@if (app('session')->getFlashBag()->has('success'))
    @foreach (app('session')->getFlashBag()->get('success') as $message)
        <div class="fixed top-5 right-5 bg-green-500 text-white py-2 px-4 rounded-xl text-sm z-50">
            <p>{{ $message }}</p>
        </div>
    @endforeach
@endif

@if (app('session')->getFlashBag()->has('error'))
    @foreach (app('session')->getFlashBag()->get('error') as $message)
        <div class="fixed top-5 right-5 bg-red-500 text-white py-2 px-4 rounded-xl text-sm z-50">
            <p>{{ $message }}</p>
        </div>
    @endforeach
@endif

<script>
    // Optional: Auto-hide the flash message after a few seconds
    document.addEventListener('DOMContentLoaded', (event) => {
        // Find all flash messages
        const flashMessages = document.querySelectorAll('.fixed.top-5.right-5');
        
        flashMessages.forEach(flashMessage => {
            if (flashMessage) {
                setTimeout(() => {
                    flashMessage.style.transition = 'opacity 0.5s ease';
                    flashMessage.style.opacity = '0';
                    setTimeout(() => flashMessage.remove(), 500);
                }, 4000); // Hide after 4 seconds
            }
        });
    });
</script>