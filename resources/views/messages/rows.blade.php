@foreach ($messages as $message)
    <article class="chat-bubble {{ $message->from_admin === $isAdmin ? 'chat-outgoing' : '' }}">
        <header class="chat-meta">
            <strong dir="auto">{{ $message->sender_name }}</strong>
            <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d M, H:i') }}</time>
        </header>
        @if ($message->body !== null)
            <div class="chat-text" dir="auto">{{ $message->body }}</div>
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
                <a class="chat-file" href="{{ $url }}" dir="auto">{{ $attachment['name'] }}</a>
            @endif
        @endforeach
    </article>
@endforeach
