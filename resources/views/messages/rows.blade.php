@foreach ($messages as $message)
    @php($mine = $message->from_admin === $isAdmin)
    <article class="msg {{ $mine ? 'me' : '' }}">
        <span class="avatar avatar-xs {{ $mine ? 'avatar-soft-primary' : 'avatar-soft-info' }}" aria-hidden="true">{{ \App\Models\User::initialsOf($message->sender_name) }}</span>
        <div class="min-w-0">
            <div class="msg-name" dir="auto">{{ $message->sender_name }}</div>
            <div class="bubble">
                @if ($message->body !== null)
                    <div class="msg-text" dir="auto">{{ $message->body }}</div>
                @endif
                @foreach ($message->attachments ?? [] as $file => $attachment)
                    @php
                        $params = ['message' => $message->id, 'file' => $file];
                        $url = $isAdmin
                            ? route('admin.messages.attachment', ['recipient' => $recipient->id] + $params)
                            : route('messages.attachment', $params);
                    @endphp
                    @if ($attachment['audio'])
                        <audio controls preload="none" aria-label="{{ __('Voice message') }}" src="{{ $url }}"></audio>
                    @else
                        <a class="msg-file" href="{{ $url }}"><x-icon name="paperclip" /><span class="text-truncate" dir="auto">{{ $attachment['name'] }}</span></a>
                    @endif
                @endforeach
            </div>
            <time class="msg-time d-block" datetime="{{ $message->created_at->toIso8601String() }}" title="{{ fmt_datetime($message->created_at) }}">{{ $message->created_at->isToday() ? $message->created_at->format('H:i') : $message->created_at->format('d M, H:i') }}</time>
        </div>
    </article>
@endforeach
