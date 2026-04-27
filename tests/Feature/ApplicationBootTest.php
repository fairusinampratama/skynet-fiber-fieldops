<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApplicationBootTest extends TestCase
{
    public function test_home_redirects_to_admin(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_admin_login_route_is_available(): void
    {
        $this->get('/admin/login')->assertOk();
    }
}
