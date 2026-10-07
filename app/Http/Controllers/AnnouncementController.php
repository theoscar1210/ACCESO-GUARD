<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $announcements = $this->addressedTo($user)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'title'      => $a->title,
                'body'       => $a->body,
                'created_at' => $a->created_at->format('d/m/Y H:i'),
                'read_at'    => $a->readers()->where('user_id', $user->id)->first()?->pivot?->read_at,
            ]);

        return Inertia::render('Announcements/Index', compact('announcements'));
    }

    public function markRead(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();

        $a = $this->addressedTo($user)->findOrFail($id);
        $a->readers()->syncWithoutDetaching([
            $user->id => ['read_at' => now()],
        ]);

        return back();
    }

    /** Comunicados dirigidos al usuario: los generales y los de su rol */
    private function addressedTo(User $user): Builder
    {
        $role = strtolower($user->getRoleNames()->first() ?? '');

        return Announcement::whereIn('target', ['all', $role]);
    }
}
