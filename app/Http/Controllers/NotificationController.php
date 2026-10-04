<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        $viewData = ['notifications' => Notification::where('user_id', auth()->id())->latest()->paginate(25)];

        if (auth()->user()->hasRole('super-admin')) {
            $viewData['users'] = User::where('is_active', true)->where('id', '!=', auth()->id())->orderBy('name')->get();
            $viewData['departments'] = config('department_roles.mapping');
        }

        return view('notifications.index', $viewData);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'in:system,department,user'],
            'department_target' => ['nullable', 'string'],
            'user_target' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'type' => ['required', 'in:info,success,warning,urgent'],
        ]);

        $target = $data['target_type'] === 'department' ? ($data['department_target'] ?? null) : ($data['user_target'] ?? null);
        abort_if($data['target_type'] !== 'system' && blank($target), 422, 'Please select a notification recipient.');
        $recipients = match ($data['target_type']) {
            'system' => User::where('is_active', true)->pluck('id'),
            'department' => User::where('is_active', true)->get()->filter(fn (User $user) => $user->department === $target || in_array($user->getRoleNames()->first(), config('department_roles.mapping')[$target] ?? [], true))->pluck('id'),
            'user' => User::where('is_active', true)->whereKey($target)->pluck('id'),
        };

        abort_if($recipients->isEmpty(), 422, 'No active recipients were found for this target.');

        DB::transaction(function () use ($recipients, $data, $target, $request): void {
            foreach ($recipients as $userId) {
                Notification::create(['user_id' => $userId, 'title' => $data['title'], 'message' => $data['message'], 'type' => $data['type'], 'metadata' => ['sent_by' => $request->user()->id, 'target_type' => $data['target_type'], 'target' => $target]]);
            }
            AuditLog::create(['user_id' => $request->user()->id, 'user_type' => $request->user()::class, 'action' => 'create', 'module_name' => 'notifications', 'model_type' => Notification::class, 'description' => 'Notification sent to '.$data['target_type'].' ('.$recipients->count().' recipient(s)).', 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
        });

        return back()->with('success', 'Notification sent to '.$recipients->count().' recipient(s).');
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);
        $notification->update(['read_at' => now()]);
        return redirect()->route('notifications.index');
    }

}
