<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * socle-prod : /up ne répond 200 que si la base répond aussi.
 */
class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_is_up_when_the_database_answers(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_fails_when_the_database_is_unreachable(): void
    {
        $connection = config('database.default');
        config(["database.connections.{$connection}.database" => '/nonexistent/dir/morex.sqlite']);
        DB::purge($connection);

        $this->get('/up')->assertStatus(500);
    }
}
