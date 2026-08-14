@if($enabled && $siteKey)
<div class="recaptcha-wrap space-y-2" data-recaptcha-wrap>
    <div class="g-recaptcha" data-sitekey="{{ $siteKey }}"></div>
    <input type="hidden" name="offline_mode" value="0" data-offline-mode>
    @error('g-recaptcha-response')
        <p class="text-red-600 text-sm">{{ $message }}</p>
    @enderror
    <p class="text-xs text-slate-400 hidden" data-recaptcha-offline-note>
        Mode offline terdeteksi — reCAPTCHA dilewati.
    </p>
</div>
@once('recaptcha-assets')
    @push('head')
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endpush
    @push('scripts')
    <script>
    (function () {
        function syncWrap(wrap) {
            const offlineInput = wrap.querySelector('[data-offline-mode]');
            const widget = wrap.querySelector('.g-recaptcha');
            const note = wrap.querySelector('[data-recaptcha-offline-note]');
            const offline = !navigator.onLine;
            if (offlineInput) offlineInput.value = offline ? '1' : '0';
            if (widget) widget.classList.toggle('hidden', offline);
            if (note) note.classList.toggle('hidden', !offline);
        }
        function syncAll() {
            document.querySelectorAll('[data-recaptcha-wrap]').forEach(syncWrap);
        }
        window.addEventListener('online', syncAll);
        window.addEventListener('offline', syncAll);
        syncAll();
    })();
    </script>
    @endpush
@endonce
@endif
