<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_cannot_be_rendered(): void
    {
        User::factory()->create(); // Exercise registration on an installed app.
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_cannot_register(): void
    {
        User::factory()->create(); // Exercise registration on an installed app.
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
        $this->assertGuest();
    }
}
