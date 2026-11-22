(function () {
    const chat = document.querySelector('[data-chat]');
    if (!chat) return;

    const history = chat.querySelector('[data-chat-history]');
    const scrollToLatest = () => { history.scrollTop = history.scrollHeight; };
    if (chat.dataset.updates) {
        scrollToLatest();
        let last = Number(chat.dataset.last);
        const refresh = async () => {
            try {
                if (document.visibilityState !== 'visible') return;
                const url = new URL(chat.dataset.updates, window.location.href);
                url.searchParams.set('after', last);
                const response = await fetch(url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (response.status === 401 || response.status === 403) {
                    window.location.reload();
                    return;
                }
                if (!response.ok) return;
                const update = await response.json();
                if (update.last > last) {
                    const atBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 70;
                    history.querySelector('[data-chat-empty]')?.remove();
                    history.insertAdjacentHTML('beforeend', update.html);
                    last = update.last;
                    if (atBottom) scrollToLatest();
                }
            } catch {
                // The next poll retries after a temporary network interruption.
            } finally {
                setTimeout(refresh, 5000);
            }
        };
        setTimeout(refresh, 5000);
    }

    const form = chat.querySelector('[data-chat-form]');
    if (!form) return;

    const record = form.querySelector('[data-chat-record]');
    const recordLabel = record.querySelector('[data-chat-record-label]') || record;
    const text = form.querySelector('[data-chat-text]');
    const voice = form.querySelector('[data-chat-voice]');
    const files = form.querySelector('[data-chat-files]');
    const preview = form.querySelector('[data-chat-preview]');
    const audio = preview.querySelector('audio');
    const status = form.querySelector('[data-chat-status]');
    const send = form.querySelector('[type="submit"]');
    const limit = 10 * 1024 * 1024;
    let previewUrl;
    let recorder;
    let timeout;

    function showSelection() {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const clip = voice.files[0];
        previewUrl = clip ? URL.createObjectURL(clip) : null;
        if (clip) audio.src = previewUrl;
        else audio.removeAttribute('src');
        preview.hidden = !clip;
        const selected = [...files.files, ...voice.files];
        status.textContent = selected.length ? selected.map(file => file.name).join(', ') : status.dataset.limit;
        status.dataset.invalid = files.files.length > 5 || selected.some(file => file.size > limit) ? 'true' : 'false';
    }

    preview.querySelector('[data-chat-remove]').addEventListener('click', () => {
        voice.value = '';
        showSelection();
    });
    form.addEventListener('submit', event => {
        if (status.dataset.invalid === 'true') {
            event.preventDefault();
            status.textContent = status.dataset.limit;
            form.querySelector('label[for="chat-files"]').scrollIntoView({ block: 'nearest' });
        }
    });
    // Ctrl + Enter (Cmd + Enter on a Mac) sends; Enter alone starts a new line
    text?.addEventListener('keydown', event => {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey) && !send.disabled) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    files.addEventListener('change', showSelection);
    voice.addEventListener('change', showSelection);

    if (!window.MediaRecorder || !window.DataTransfer || !navigator.mediaDevices?.getUserMedia) return;
    record.hidden = false;
    record.addEventListener('click', async () => {
        if (recorder?.state === 'recording') {
            record.disabled = true;
            recorder.stop();
            return;
        }

        let stream;
        record.disabled = true;
        send.disabled = true;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            const format = ['audio/webm;codecs=opus', 'audio/mp4', 'audio/ogg;codecs=opus'].find(type => MediaRecorder.isTypeSupported(type));
            recorder = new MediaRecorder(stream, format ? { mimeType: format } : undefined);
            const parts = [];
            recorder.addEventListener('dataavailable', event => {
                if (event.data.size) parts.push(event.data);
            });
            recorder.addEventListener('stop', () => {
                clearTimeout(timeout);
                stream.getTracks().forEach(track => track.stop());
                const mime = (recorder.mimeType || parts[0]?.type || 'audio/webm').split(';')[0];
                const extensions = { 'audio/webm': 'webm', 'audio/mp4': 'm4a', 'audio/ogg': 'ogg' };
                const clip = new File(parts, 'voice-message.' + (extensions[mime] || 'webm'), { type: mime });
                if (clip.size > 0 && clip.size <= limit) {
                    const transfer = new DataTransfer();
                    transfer.items.add(clip);
                    voice.files = transfer.files;
                    showSelection();
                } else {
                    status.textContent = status.dataset.limit;
                }
                recordLabel.textContent = record.dataset.start;
                record.classList.remove('is-recording');
                record.disabled = false;
                send.disabled = false;
            }, { once: true });
            recorder.start();
            timeout = setTimeout(() => {
                if (recorder.state === 'recording') recorder.stop();
            }, 120000);
            status.textContent = record.dataset.capturing;
            recordLabel.textContent = record.dataset.stop;
            record.classList.add('is-recording');
            record.disabled = false;
        } catch {
            stream?.getTracks().forEach(track => track.stop());
            status.textContent = record.dataset.error;
            record.disabled = false;
            send.disabled = false;
        }
    });
})();
