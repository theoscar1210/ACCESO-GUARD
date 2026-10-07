<?php

namespace Tests\Feature\Concerns;

use App\Models\Authorization;
use App\Models\Entry;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\RoleSeeder;

trait CreatesUsersWithRoles
{
    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function admin(array $attributes = []): User
    {
        return $this->userWithRole('Administrador', $attributes);
    }

    protected function vigilante(array $attributes = []): User
    {
        return $this->userWithRole('Vigilante', $attributes);
    }

    protected function propietario(array $attributes = []): User
    {
        return $this->userWithRole('Propietario', $attributes);
    }

    protected function residente(array $attributes = []): User
    {
        return $this->userWithRole('Residente', $attributes);
    }

    protected function propietarioWithProperty(string $number = '101'): User
    {
        $owner = $this->propietario();
        $property = Property::create(['number' => $number, 'type' => 'apartamento']);
        $property->owners()->attach($owner->id);

        return $owner;
    }

    protected function makeEntry(User $vigilante, array $attributes = []): Entry
    {
        return Entry::create([
            'user_id' => $vigilante->id,
            'first_name' => 'Juan',
            'last_name' => 'Pérez',
            'cedula' => (string) fake()->unique()->numerify('########'),
            'apartment' => '101',
            'type' => 'visitante',
            'vehicle' => 'ninguno',
            'registered_by' => $vigilante->username,
            'entry_at' => now(),
            ...$attributes,
        ]);
    }

    protected function makeAuthorization(User $owner, array $attributes = []): Authorization
    {
        return Authorization::create([
            'user_id' => $owner->id,
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'cedula' => (string) fake()->unique()->numerify('########'),
            'type' => 'visitante',
            'status' => 'activo',
            'start_date' => today(),
            'end_date' => now()->addDay(),
            ...$attributes,
        ]);
    }
}
