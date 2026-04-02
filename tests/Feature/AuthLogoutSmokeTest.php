<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class AuthLogoutSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::share('errors', new ViewErrorBag());
    }

    public function test_get_logout_redirects_to_login_without_csrf_error(): void
    {
        $user = tap(new User(), function (User $user) {
            $user->forceFill([
                'id' => 91,
                'account_id' => 1,
                'name' => 'Документы',
                'role' => 'documents_operator',
                'is_active' => true,
                'email' => 'documents@example.com',
            ]);
            $user->exists = true;
        });

        $response = $this->actingAs($user)->get(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
