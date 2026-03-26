{{-- Contact Block – Giao diện trang Liên hệ (theme tối đồng bộ) --}}
<section class="contact-block py-12 lg:py-16">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-4xl font-extrabold text-white sm:text-5xl">
                {{ $title ?? 'Liên hệ với chúng tôi' }}
            </h2>
            <p class="mt-4 text-lg text-gray-400">
                {{ $subtitle ?? '' }}
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
            {{-- Thông tin liên hệ --}}
            <div class="space-y-6">
                <div class="bg-gray-800/50 rounded-xl border border-white/5 p-6">
                    <h3 class="text-lg font-semibold text-white mb-4">Thông tin liên hệ</h3>
                    <ul class="space-y-4">
                        @if(!empty($address))
                        <li class="flex items-start gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-indigo-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span>{{ $address }}</span>
                        </li>
                        @endif
                        @if(!empty($email))
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <a href="mailto:{{ $email }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">{{ $email }}</a>
                        </li>
                        @endif
                        @if(!empty($phone))
                        <li class="flex items-center gap-3 text-gray-300">
                            <svg class="w-5 h-5 text-indigo-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            <a href="tel:{{ $phone }}" class="text-indigo-400 hover:text-indigo-300 transition-colors">{{ $phone }}</a>
                        </li>
                        @endif
                    </ul>
                </div>
            </div>

            {{-- Form liên hệ --}}
            @if(!empty($show_form))
            <div class="bg-gray-800/50 rounded-xl border border-white/5 p-6">
                <h3 class="text-lg font-semibold text-white mb-4">Gửi tin nhắn</h3>
                <form action="{{ $form_action ?? '#' }}" method="POST" class="space-y-4 contact-form">
                    @csrf
                    <div>
                        <label for="contact_name" class="block text-sm font-medium text-gray-300 mb-1">Họ tên</label>
                        <input type="text" id="contact_name" name="name" required
                               class="block w-full px-3 py-2.5 bg-gray-700/50 border border-white/10 rounded-xl text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                               placeholder="Nguyễn Văn A">
                    </div>
                    <div>
                        <label for="contact_email" class="block text-sm font-medium text-gray-300 mb-1">Email</label>
                        <input type="email" id="contact_email" name="email" required
                               class="block w-full px-3 py-2.5 bg-gray-700/50 border border-white/10 rounded-xl text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                               placeholder="you@example.com">
                    </div>
                    <div>
                        <label for="contact_subject" class="block text-sm font-medium text-gray-300 mb-1">Chủ đề</label>
                        <input type="text" id="contact_subject" name="subject"
                               class="block w-full px-3 py-2.5 bg-gray-700/50 border border-white/10 rounded-xl text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                               placeholder="Tiêu đề tin nhắn">
                    </div>
                    <div>
                        <label for="contact_message" class="block text-sm font-medium text-gray-300 mb-1">Nội dung</label>
                        <textarea id="contact_message" name="message" rows="4" required
                                  class="block w-full px-3 py-2.5 bg-gray-700/50 border border-white/10 rounded-xl text-gray-100 placeholder-gray-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-none"
                                  placeholder="Nội dung tin nhắn..."></textarea>
                    </div>
                    <div>
                        <button type="submit"
                                class="w-full py-3 px-4 rounded-xl font-semibold text-white bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 focus:ring-2 focus:ring-offset-2 focus:ring-offset-gray-800 focus:ring-indigo-500 transition shadow-lg shadow-indigo-500/25">
                            Gửi tin nhắn
                        </button>
                    </div>
                </form>
            </div>
            @endif
        </div>
    </div>
</section>
