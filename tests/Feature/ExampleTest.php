<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     *
     * `/` now renders the buyer storefront home page (Storefront\Home),
     * which queries the categories/banners/products tables — needs a
     * migrated schema, hence RefreshDatabase (not needed back when this
     * route was the static `welcome` view).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
