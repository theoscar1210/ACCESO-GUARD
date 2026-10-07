<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FamilyMember;
use App\Models\Property;
use App\Models\PropertyRental;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PropertyController extends Controller
{
    public function index(): Response
    {
        $properties = Property::with([
            'owners',
            'activeRental.tenant',
        ])
            ->orderBy('number')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'number' => $p->number,
                'block' => $p->block,
                'type' => $p->type,
                'description' => $p->description,
                'owners' => $p->owners->map(fn ($o) => [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                ]),
                'tenant' => $p->activeRental?->tenant ? [
                    'id' => $p->activeRental->tenant->id,
                    'full_name' => $p->activeRental->tenant->full_name,
                    'since' => $p->activeRental->start_date?->format('d/m/Y'),
                ] : null,
            ]);

        return Inertia::render('admin/Properties/Index', compact('properties'));
    }

    public function create(): Response
    {
        return Inertia::render('admin/Properties/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'number' => 'required|string|max:20|unique:properties',
            'block' => 'nullable|string|max:30',
            'type' => 'required|in:apartamento,casa,local',
            'description' => 'nullable|string|max:200',
        ]);

        Property::create($data);

        return redirect()->route('admin.properties.index')
            ->with('success', 'Inmueble registrado correctamente.');
    }

    public function show(Property $property): Response
    {
        $property->load([
            'owners',
            'rentals.tenant',
            'activeRental.tenant',
        ]);

        // Propietarios disponibles (con rol Propietario, no asignados a este inmueble)
        $ownersAvailable = User::role('Propietario')
            ->whereNotIn('id', $property->owners->pluck('id'))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'cedula']);

        // Residentes disponibles (con rol Residente, sin arrendamiento activo)
        $tenantsAvailable = User::role('Residente')
            ->whereDoesntHave('rentals', fn ($q) => $q->where('is_active', true))
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'cedula']);

        // Núcleo familiar de propietarios y residente actual
        $relatedUserIds = $property->owners->pluck('id')
            ->merge([$property->activeRental?->user_id])
            ->filter()
            ->unique();

        $familyMembers = FamilyMember::whereIn('user_id', $relatedUserIds)
            ->with('user:id,first_name,last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'full_name' => $f->full_name,
                'cedula' => $f->cedula,
                'phone' => $f->phone,
                'relationship' => $f->relationship,
                'belongs_to' => $f->user->full_name,
                'user_id' => $f->user_id,
            ]);

        return Inertia::render('admin/Properties/Show', [
            'property' => [
                'id' => $property->id,
                'number' => $property->number,
                'block' => $property->block,
                'type' => $property->type,
                'description' => $property->description,
                'owners' => $property->owners->map(fn ($o) => [
                    'id' => $o->id,
                    'full_name' => $o->full_name,
                    'cedula' => $o->cedula,
                    'since' => $o->pivot->since_date,
                ]),
                'active_rental' => $property->activeRental ? [
                    'id' => $property->activeRental->id,
                    'tenant_id' => $property->activeRental->user_id,
                    'tenant_name' => $property->activeRental->tenant->full_name,
                    'tenant_cedula' => $property->activeRental->tenant->cedula,
                    'start_date' => $property->activeRental->start_date?->format('Y-m-d'),
                    'end_date' => $property->activeRental->end_date?->format('Y-m-d'),
                ] : null,
                'rental_history' => $property->rentals->map(fn ($r) => [
                    'id' => $r->id,
                    'tenant_name' => $r->tenant->full_name,
                    'start_date' => $r->start_date->format('d/m/Y'),
                    'end_date' => $r->end_date?->format('d/m/Y') ?? 'Activo',
                    'is_active' => $r->is_active,
                ]),
            ],
            'owners_available' => $ownersAvailable,
            'tenants_available' => $tenantsAvailable,
            'family_members' => $familyMembers,
        ]);
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'number' => "required|string|max:20|unique:properties,number,{$property->id}",
            'block' => 'nullable|string|max:30',
            'type' => 'required|in:apartamento,casa,local',
            'description' => 'nullable|string|max:200',
        ]);

        $property->update($data);

        return back()->with('success', 'Inmueble actualizado.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        return redirect()->route('admin.properties.index')
            ->with('success', 'Inmueble eliminado.');
    }

    /** Asignar propietario a inmueble */
    public function assignOwner(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'since_date' => 'nullable|date',
        ]);
        $this->ensureUserHasRole($data['user_id'], 'Propietario');

        $property->owners()->syncWithoutDetaching([
            $data['user_id'] => ['since_date' => $data['since_date'] ?? null],
        ]);

        return back()->with('success', 'Propietario asignado correctamente.');
    }

    /** Quitar propietario de inmueble */
    public function removeOwner(Property $property, User $user): RedirectResponse
    {
        $property->owners()->detach($user->id);

        return back()->with('success', 'Propietario removido.');
    }

    /** Registrar arrendatario (residente) */
    public function assignTenant(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);
        $this->ensureUserHasRole($data['user_id'], 'Residente');

        // Cerrar arrendamiento activo previo si existe
        $property->rentals()->where('is_active', true)->update(['is_active' => false]);

        PropertyRental::create([
            'property_id' => $property->id,
            'user_id' => $data['user_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Arrendatario registrado correctamente.');
    }

    /** Finalizar arrendamiento activo */
    public function endRental(Property $property): RedirectResponse
    {
        $property->rentals()->where('is_active', true)
            ->update(['is_active' => false, 'end_date' => now()->toDateString()]);

        return back()->with('success', 'Arrendamiento finalizado.');
    }

    private function ensureUserHasRole(int|string $userId, string $role): void
    {
        if (! User::role($role)->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => "El usuario seleccionado no tiene el rol {$role}.",
            ]);
        }
    }
}
