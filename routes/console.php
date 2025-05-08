<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('embed', function () {
    $request  = Request::create(route('pulpal.initiate'), 'GET');
    $response = app()->handle($request);
    $this->info('Status: '.$response->getStatusCode());
    $this->line($response->getContent());
})->purpose('Embed pulpal payment URLs to draft zoho invoice lacking one');
