<?php

namespace Solt\Bonus\Tests\Feature\Errors;

use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_404_page_is_rendered_with_custom_layout()
    {
        $this->get('/net-takoy-stranicy')
            ->assertNotFound()
            ->assertSee('Такой страницы нет')
            ->assertSee('/net-takoy-stranicy');
    }

    public function test_api_404_stays_json()
    {
        $this->getJson('/api/v1/net-takogo-metoda')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
