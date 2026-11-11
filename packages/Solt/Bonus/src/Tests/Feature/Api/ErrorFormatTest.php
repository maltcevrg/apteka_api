<?php

namespace Solt\Bonus\Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The API has no web pages, so every error must come back as JSON,
 * even when the client did not send the "Accept: application/json" header.
 */
class ErrorFormatTest extends TestCase
{

    use RefreshDatabase;

    /**
     * A request without a token gets a JSON 401 instead of a redirect to a login page.
     *
     * @return void
     */
    public function test_request_without_token_gets_json_401()
    {
        $this->get( route('api.check') )
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'message' => 'Unauthenticated.'
            ]);
    }

    /**
     * A validation error gets a JSON 422 with the list of errors instead of a redirect.
     *
     * @return void
     */
    public function test_validation_error_gets_json_422()
    {
        Passport::actingAsClient(
            Client::factory()->create(),
            ['*']
        );

        $this->post( route('api.bonus.increase.store'), [] )
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors([
                'short_code',
                'amount'
            ]);
    }

    /**
     * An unknown API address gets a JSON 404 instead of an HTML page.
     *
     * @return void
     */
    public function test_unknown_api_route_gets_json_404()
    {
        $this->get('/api/v1/nothing-here')
            ->assertStatus(404)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'message'
            ]);
    }
}
