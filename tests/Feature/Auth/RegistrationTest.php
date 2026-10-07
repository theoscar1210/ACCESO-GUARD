<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El registro público está deshabilitado: los usuarios los crea el administrador.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_not_available()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_users_cannot_self_register()
    {
        $this->post('/register', [
            'name' => 'Intruso',
            'email' => 'intruso@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'intruso@example.com']);
        $this->assertGuest();
    }
}
