<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_admin_and_login_renders(): void
    {
        $this->get('/')->assertRedirect('/admin');
        $this->get('/admin/login')->assertOk();
    }

    public function test_inactive_user_cannot_access_admin_panel(): void
    {
        $inactive = User::factory()->admin()->inactive()->create();

        $this->assertFalse($inactive->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($inactive)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin_resources(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['projects', 'areas', 'users', 'submissions', 'olt-assets', 'olt-pon-ports', 'odc-assets', 'odp-assets', 'asset-map'] as $resource) {
            $this->actingAs($admin)->get("/admin/{$resource}")->assertOk();
        }
    }

    public function test_technician_can_access_submissions_but_not_admin_only_resources(): void
    {
        $technician = User::factory()->technician()->create();

        $this->actingAs($technician)->get('/admin/submissions')->assertOk();
        $this->actingAs($technician)->get('/admin/submissions/create')->assertForbidden();

        foreach (['projects', 'areas', 'users', 'olt-assets', 'olt-pon-ports', 'odc-assets', 'odp-assets', 'asset-map'] as $resource) {
            $this->actingAs($technician)->get("/admin/{$resource}")->assertForbidden();
        }
    }
}
