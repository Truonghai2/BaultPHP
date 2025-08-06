import morph from '@alpinejs/morph';
import Alpine from 'alpinejs';

Alpine.plugin(morph)

document.addEventListener('alpine:init', () => {
    Alpine.data('baultComponent', (initialSnapshot) => ({
        snapshot: initialSnapshot,
        html: '',

        init() {
            this.html = this.$el.innerHTML;
        },

        call(method, ...params) {
            this.update({
                method,
                params
            });
        },

        async update(call) {
            const response = await fetch('/bault/update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    snapshot: JSON.stringify(this.snapshot),
                    calls: call
                })
            });

            if (!response.ok) {
                console.error('Bault component update failed.');
                return;
            }

            const data = await response.json();
            this.snapshot = JSON.parse(data.snapshot);

            this.$el.morph(data.html);
        }
    }));
});

Alpine.start()