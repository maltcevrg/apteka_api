<?php

namespace Solt\Bonus\Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    /**
     * Маршрут прогрева доступен без токена
     */
    public function test_health_is_public()
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    /**
     * Остальное API без токена по-прежнему закрыто
     */
    public function test_check_still_requires_token()
    {
        $this->getJson('/api/v1/check')->assertStatus(401);
    }
}
