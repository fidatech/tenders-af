<?php

namespace App\Http\Controllers;

use App\Models\DirectMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

class DirectMessageController extends Controller
{
    private const VOICE_TYPES = [
        'audio/webm' => 'audio/webm', 'video/webm' => 'audio/webm',
        'audio/mp4' => 'audio/mp4', 'video/mp4' => 'audio/mp4',
        'audio/mpeg' => 'audio/mpeg', 'audio/ogg' => 'audio/ogg', 'application/ogg' => 'audio/ogg',
        'audio/wav' => 'audio/wav', 'audio/x-wav' => 'audio/wav',
        'audio/x-m4a' => 'audio/mp4', 'audio/aac' => 'audio/aac',
    ];

    public function inbox(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:150']]);
        $search = trim((string) $request->query('q', ''));
        $activity = DirectMessage::query()->selectRaw(
            'user_id, MAX(id) AS latest_message, SUM(CASE WHEN from_admin = 0 AND read_at IS NULL THEN 1 ELSE 0 END) AS unread_messages'
        )->groupBy('user_id');

        $people = User::query()->select('users.*', 'activity.unread_messages')->with('company')
            ->leftJoinSub($activity, 'activity', 'activity.user_id', '=', 'users.id')
            ->when($search !== '', fn ($query) => $query->where(fn ($matches) => $matches
                ->where('users.name', 'like', '%'.$search.'%')
                ->orWhere('users.email', 'like', '%'.$search.'%')
                ->orWhereHas('company', fn ($company) => $company->where('name', 'like', '%'.$search.'%'))))
            ->orderByRaw('CASE WHEN activity.latest_message IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('activity.latest_message')->orderBy('users.name')->orderBy('users.id')->paginate(25)->withQueryString();

        return view('messages.inbox', compact('people', 'search'));
    }

    public function show(Request $request): View
    {
        $recipient = $this->recipient($request);
        $isAdmin = $request->routeIs('admin.messages.*');
        $query = DirectMessage::where('user_id', $recipient->id);
        $before = $request->integer('before');
        $messages = (clone $query)->when($before > 0, fn ($page) => $page->where('id', '<', $before))
            ->orderByDesc('id')->limit(51)->get();
        $older = $messages->count() > 50;
        $messages = $messages->take(50)->reverse()->values();
        $this->markRead($recipient, $isAdmin, $messages->modelKeys());

        return view('messages.thread', compact('recipient', 'isAdmin', 'messages', 'older', 'before'));
    }

    public function updates(Request $request): JsonResponse
    {
        $recipient = $this->recipient($request);
        $isAdmin = $request->routeIs('admin.messages.*');
        $after = max(0, $request->integer('after'));
        $messages = DirectMessage::where('user_id', $recipient->id)->where('id', '>', $after)
            ->orderBy('id')->limit(50)->get();
        $this->markRead($recipient, $isAdmin, $messages->modelKeys());

        return response()->json([
            'html' => view('messages.rows', compact('recipient', 'isAdmin', 'messages'))->render(),
            'last' => $messages->last()?->id ?? $after,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request): RedirectResponse
    {
        $recipient = $this->recipient($request);
        $isAdmin = $request->routeIs('admin.messages.*');
        abort_unless($recipient->is_active, 403);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,webp,zip'],
            'voice' => ['nullable', 'file', 'max:10240', 'mimetypes:'.implode(',', array_keys(self::VOICE_TYPES))],
        ]);
        if (empty($data['files']) && ! isset($data['voice']) && ! $request->filled('body')) {
            throw ValidationException::withMessages(['body' => __('Write a message or add an attachment.')]);
        }

        $admin = $isAdmin ? $request->user('admin') : null;
        $uploads = array_values($data['files'] ?? []);
        if (isset($data['voice'])) {
            $uploads['voice'] = $data['voice'];
        }

        $attachments = [];
        try {
            foreach ($uploads as $key => $file) {
                $path = $file->store('direct-messages', 'local');
                if ($path === false) {
                    throw new RuntimeException('Could not store a message attachment.');
                }
                $attachments[] = [
                    'path' => $path,
                    'name' => mb_substr(preg_replace('/[[:cntrl:]]/', '', mb_scrub($file->getClientOriginalName())), 0, 240) ?: 'attachment',
                    'audio' => $key === 'voice' ? self::VOICE_TYPES[$file->getMimeType()] : null,
                ];
            }

            DB::transaction(function () use ($recipient, $admin, $isAdmin, $data, $attachments) {
                // Serialize writes to the same conversation so polling sees messages in ID order.
                User::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
                DirectMessage::create([
                    'user_id' => $recipient->id, 'admin_id' => $admin?->id, 'from_admin' => $isAdmin,
                    'sender_name' => $admin?->name ?? $recipient->name,
                    'body' => $data['body'] ?? null, 'attachments' => $attachments ?: null,
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete(array_column($attachments, 'path'));

            throw $exception;
        }

        return $isAdmin ? redirect()->route('admin.messages.show', $recipient) : redirect()->route('messages.show');
    }

    public function attachment(Request $request): BinaryFileResponse
    {
        $recipient = $this->recipient($request);
        $message = DirectMessage::where('user_id', $recipient->id)->findOrFail($request->route('message'));
        $file = $message->attachments[(int) $request->route('file')] ?? null;
        $disk = Storage::disk('local');
        abort_unless($file && $disk->exists($file['path']), 404);

        $response = response()->file($disk->path($file['path']), [
            'Content-Type' => $file['audio'] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
        // BinaryFileResponse supports byte ranges for voice playback and seeking.
        $filename = str_replace(['/', '\\'], '_', $file['name']);
        $fallback = str_replace('%', '_', Str::ascii($filename)) ?: 'attachment';
        $response->setContentDisposition(
            $file['audio'] ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
            $fallback
        );
        $response->setPrivate();

        return $response;
    }

    private function recipient(Request $request): User
    {
        return $request->routeIs('admin.messages.*')
            ? User::with('company')->findOrFail($request->route('recipient'))
            : $request->user();
    }

    private function markRead(User $recipient, bool $isAdmin, array $ids): void
    {
        if ($ids && (! $isAdmin || auth('admin')->user()->role !== 'viewer')) {
            DirectMessage::where('user_id', $recipient->id)->whereIn('id', $ids)
                ->where('from_admin', ! $isAdmin)->whereNull('read_at')->update(['read_at' => now()]);
        }
    }
}
