<?php

use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\FuelTypeController;
use App\Models\DocumentType;
use App\Models\FuelType;
use App\Tenancy\TenantContext;
use Database\Factories\DocumentTypeFactory;
use Database\Factories\DriverComplianceDocumentFactory;
use Database\Factories\DriverFactory;
use Database\Factories\VehicleFactory;
use Tests\Support\Actors;

/*
| Shared lookups are global (03 §2), so "is it still in use?" before a delete must count every
| company's rows, not only those of the company in context (WP5 put the tenant scope on them).
*/

it('refuses to delete a fuel type another company\'s vehicle uses', function () {
    $fuelType = FuelType::create(['name' => 'Diesel']);
    VehicleFactory::new()->forCompany(Actors::company())->create(['fuel_type_id' => $fuelType->id]);

    $response = app(TenantContext::class)->run(Actors::company()->id, fn () => app(FuelTypeController::class)->destroy($fuelType->id));

    expect($response->getStatusCode())->toBe(400)
        ->and(FuelType::find($fuelType->id))->not->toBeNull();
});

it('refuses to delete a document type another company\'s documents use', function () {
    $type = DocumentTypeFactory::new()->module('driver')->create();
    DriverComplianceDocumentFactory::new()->forDriver(DriverFactory::new()->forCompany(Actors::company())->create(), $type)->create();

    $response = app(TenantContext::class)->run(Actors::company()->id, fn () => app(DocumentTypeController::class)->destroy($type->id));

    expect($response->getStatusCode())->toBe(400)
        ->and(DocumentType::find($type->id))->not->toBeNull();
});
