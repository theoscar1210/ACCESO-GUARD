<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('roles')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'username' => $u->username,
                'email' => $u->email,
                'cedula' => $u->cedula,
                'phone' => $u->phone,
                'role' => $u->getRoleNames()->first(),
                'created_at' => $u->created_at->format('d/m/Y'),
                'can_manage' => $this->canManage($u),
            ]);

        return Inertia::render('admin/Users/Index', compact('users'));
    }

    public function create(): Response
    {
        $roles = $this->assignableRoles();

        return Inertia::render('admin/Users/Create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'cedula' => 'required|string|max:20|unique:users',
            'phone' => 'nullable|string|max:15',
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in($this->assignableRoles())],
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(User $user): Response
    {
        abort_unless($this->canManage($user), 403);

        $roles = $this->assignableRoles();

        return Inertia::render('admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'cedula' => $user->cedula,
                'phone' => $user->phone,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->canManage($user), 403);

        $data = $request->validate([
            'first_name' => 'required|string|max:50',
            'last_name' => 'required|string|max:50',
            'cedula' => "required|string|max:20|unique:users,cedula,{$user->id}",
            'phone' => 'nullable|string|max:15',
            'username' => "required|string|max:50|unique:users,username,{$user->id}",
            'email' => "required|email|unique:users,email,{$user->id}",
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::in($this->assignableRoles())],
        ]);

        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'cedula' => $data['cedula'],
            'phone' => $data['phone'] ?? null,
            'username' => $data['username'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        $user->update($updateData);

        $user->syncRoles([$data['role']]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        abort_unless($this->canManage($user), 403);

        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'No puedes eliminar tu propio usuario.']);
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }

    /** Solo un superusuario puede crear o asignar el rol Superusuario */
    private function assignableRoles(): array
    {
        $roles = Role::pluck('name')->all();

        return auth()->user()->hasRole('Superusuario')
            ? $roles
            : array_values(array_diff($roles, ['Superusuario']));
    }

    /** Un administrador no puede editar ni eliminar a un superusuario */
    private function canManage(User $target): bool
    {
        return auth()->user()->hasRole('Superusuario') || ! $target->hasRole('Superusuario');
    }
}
