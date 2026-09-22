<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes prometheus metrics in text format', function () {
    $this->get('/api/metrics')
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; version=0.0.4; charset=utf-8')
        ->assertSee('nexi_erp_up 1')
        ->assertSee('nexi_erp_http_requests_total ')
        ->assertSee('nexi_erp_http_request_duration_seconds_bucket{le="+Inf"}', false);
});

it('records requests through the metrics middleware', function () {
    $this->get('/api/health')->assertOk();

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_http_requests_total ')
        ->assertSee('nexi_erp_health{component="database"} 1', false);
});
