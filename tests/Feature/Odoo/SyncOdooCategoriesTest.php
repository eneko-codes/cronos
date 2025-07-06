<?php

declare(strict_types=1);

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\Odoo\SyncOdooCategories;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

describe('SyncOdooCategories job', function (): void {
    beforeEach(function (): void {
        DB::beginTransaction();
    });
    afterEach(function (): void {
        DB::rollBack();
    });

    it('creates a new category from OdooCategoryDTO', function (): void {
        $dto = new OdooCategoryDTO(
            id: 1,
            name: 'Category1',
            active: true
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getCategories')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooCategories($mockOdoo);
        $job->handle();

        $category = Category::where('odoo_category_id', 1)->first();
        expect($category)->not()->toBeNull();
        expect($category->name)->toBe('Category1');
        expect($category->active)->toBeTrue();
    });

    it('updates an existing category from OdooCategoryDTO', function (): void {
        $category = Category::factory()->create([
            'odoo_category_id' => 2,
            'name' => 'Old',
            'active' => false,
        ]);
        $dto = new OdooCategoryDTO(
            id: 2,
            name: 'New',
            active: true
        );
        $mockOdoo = Mockery::mock(OdooApiClient::class);
        $mockOdoo->shouldReceive('getCategories')->once()->andReturn(collect([$dto]));

        $job = new SyncOdooCategories($mockOdoo);
        $job->handle();

        $category->refresh();
        expect($category->name)->toBe('New');
        expect($category->active)->toBeTrue();
    });
});
