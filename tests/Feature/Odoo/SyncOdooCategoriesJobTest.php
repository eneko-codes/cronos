<?php

declare(strict_types=1);

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\Odoo\SyncOdooCategoriesJob;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Use namedMock to avoid readonly class extension issues
    $this->odooClient = Mockery::namedMock('MockOdooApiClient', OdooApiClient::class);
    $this->job = new SyncOdooCategoriesJob($this->odooClient);
});

test('SyncOdooCategoriesJob can be constructed with OdooApiClient', function (): void {
    expect($this->job)->toBeInstanceOf(SyncOdooCategoriesJob::class);
    expect($this->job->priority)->toBe(1);
});

test('SyncOdooCategoriesJob handle method fetches and processes categories', function (): void {
    // Mock categories data
    $categoriesData = collect([
        new OdooCategoryDTO(
            id: 1,
            name: 'Full Time',
            active: true
        ),
        new OdooCategoryDTO(
            id: 2,
            name: 'Part Time',
            active: true
        ),
        new OdooCategoryDTO(
            id: 3,
            name: 'Contractor',
            active: false
        ),
    ]);

    // Mock the API client to return our test data
    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn($categoriesData);

    // Execute the job
    $this->job->handle();

    // Verify that categories were created in the database
    expect(Category::count())->toBe(3);

    $fullTime = Category::where('odoo_category_id', 1)->first();
    expect($fullTime->name)->toBe('Full Time');
    expect($fullTime->active)->toBe(true);

    $partTime = Category::where('odoo_category_id', 2)->first();
    expect($partTime->name)->toBe('Part Time');
    expect($partTime->active)->toBe(true);

    $contractor = Category::where('odoo_category_id', 3)->first();
    expect($contractor->name)->toBe('Contractor');
    expect($contractor->active)->toBe(false);
});

test('SyncOdooCategoriesJob handle method works with empty categories collection', function (): void {
    // Mock empty collection
    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn(collect([]));

    // Execute the job
    $this->job->handle();

    // Verify no categories were created
    expect(Category::count())->toBe(0);
});

test('SyncOdooCategoriesJob handle method processes single category', function (): void {
    // Mock single category
    $categoriesData = collect([
        new OdooCategoryDTO(
            id: 1,
            name: 'Full Time',
            active: true
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn($categoriesData);

    // Execute the job
    $this->job->handle();

    // Verify category was created
    expect(Category::count())->toBe(1);

    $category = Category::where('odoo_category_id', 1)->first();
    expect($category->name)->toBe('Full Time');
    expect($category->active)->toBe(true);
});

test('SyncOdooCategoriesJob handle method updates existing categories', function (): void {
    // Create existing category
    Category::create([
        'odoo_category_id' => 1,
        'name' => 'Old Name',
        'active' => false,
    ]);

    // Mock updated category data
    $categoriesData = collect([
        new OdooCategoryDTO(
            id: 1,
            name: 'Updated Name',
            active: true
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn($categoriesData);

    // Execute the job
    $this->job->handle();

    // Verify category was updated, not duplicated
    expect(Category::count())->toBe(1);

    $category = Category::where('odoo_category_id', 1)->first();
    expect($category->name)->toBe('Updated Name');
    expect($category->active)->toBe(true);
});

test('SyncOdooCategoriesJob failed method triggers health check', function (): void {
    // Mock the CheckOdooHealthAction
    $healthAction = Mockery::mock(CheckOdooHealthAction::class);
    $healthAction->shouldReceive('__invoke')
        ->once()
        ->with($this->odooClient);

    // Bind the mock to the container
    $this->app->instance(CheckOdooHealthAction::class, $healthAction);

    // Call the failed method
    $this->job->failed();
});

test('SyncOdooCategoriesJob can be dispatched to queue', function (): void {
    Queue::fake();

    // Dispatch the job
    SyncOdooCategoriesJob::dispatch($this->odooClient);

    // Assert job was pushed to queue
    Queue::assertPushed(SyncOdooCategoriesJob::class);
});

test('SyncOdooCategoriesJob handles API exceptions gracefully', function (): void {
    // Mock API client to throw exception
    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andThrow(new \App\Exceptions\ApiConnectionException('Connection failed'));

    // Execute the job and expect exception
    expect(fn () => $this->job->handle())
        ->toThrow(\App\Exceptions\ApiConnectionException::class, 'Connection failed');

    // Verify no categories were created
    expect(Category::count())->toBe(0);
});

test('SyncOdooCategoriesJob skips invalid category data', function (): void {
    // Mock categories with one invalid entry (missing required fields)
    $categoriesData = collect([
        new OdooCategoryDTO(
            id: 1,
            name: 'Valid Category',
            active: true
        ),
        new OdooCategoryDTO(
            id: null, // Invalid - missing ID
            name: 'Invalid Category',
            active: true
        ),
        new OdooCategoryDTO(
            id: 3,
            name: 'Another Valid Category',
            active: false
        ),
    ]);

    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn($categoriesData);

    // Execute the job
    $this->job->handle();

    // Verify only valid categories were created (invalid one skipped)
    expect(Category::count())->toBe(2);

    expect(Category::where('odoo_category_id', 1)->exists())->toBe(true);
    expect(Category::where('odoo_category_id', 3)->exists())->toBe(true);
    expect(Category::where('name', 'Invalid Category')->exists())->toBe(false);
});

test('SyncOdooCategoriesJob processes large number of categories efficiently', function (): void {
    // Create a large collection of categories
    $categoriesData = collect();
    for ($i = 1; $i <= 100; $i++) {
        $categoriesData->push(new OdooCategoryDTO(
            id: $i,
            name: "Category {$i}",
            active: ($i % 2 === 0) // Alternate active/inactive
        ));
    }

    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn($categoriesData);

    // Execute the job
    $this->job->handle();

    // Verify all categories were processed
    expect(Category::count())->toBe(100);

    // Verify some random samples
    $category50 = Category::where('odoo_category_id', 50)->first();
    expect($category50->name)->toBe('Category 50');
    expect($category50->active)->toBe(true);

    $category51 = Category::where('odoo_category_id', 51)->first();
    expect($category51->name)->toBe('Category 51');
    expect($category51->active)->toBe(false);
});

test('SyncOdooCategoriesJob maintains data integrity during partial failures', function (): void {
    // Create some existing categories
    Category::create(['odoo_category_id' => 1, 'name' => 'Existing 1', 'active' => true]);
    Category::create(['odoo_category_id' => 2, 'name' => 'Existing 2', 'active' => false]);

    // Mock categories with mix of valid and invalid data
    $categoriesData = collect([
        new OdooCategoryDTO(id: 1, name: 'Updated Existing 1', active: false), // Update existing
        new OdooCategoryDTO(id: null, name: 'Invalid New', active: true),       // Invalid new
        new OdooCategoryDTO(id: 3, name: 'Valid New', active: true),           // Valid new
    ]);

    $this->odooClient
        ->shouldReceive('getCategories')
        ->once()
        ->andReturn($categoriesData);

    // Execute the job
    $this->job->handle();

    // Verify data integrity
    expect(Category::count())->toBe(3); // 2 existing + 1 new valid

    // Existing category should be updated
    $updated = Category::where('odoo_category_id', 1)->first();
    expect($updated->name)->toBe('Updated Existing 1');
    expect($updated->active)->toBe(false);

    // Second existing category should remain unchanged
    $unchanged = Category::where('odoo_category_id', 2)->first();
    expect($unchanged->name)->toBe('Existing 2');
    expect($unchanged->active)->toBe(false);

    // New valid category should be created
    $newCategory = Category::where('odoo_category_id', 3)->first();
    expect($newCategory->name)->toBe('Valid New');
    expect($newCategory->active)->toBe(true);

    // Invalid category should not exist
    expect(Category::where('name', 'Invalid New')->exists())->toBe(false);
});

test('SyncOdooCategoriesJob extends BaseSyncJob', function (): void {
    expect($this->job)->toBeInstanceOf(\App\Jobs\Sync\BaseSyncJob::class);
});

test('SyncOdooCategoriesJob has correct priority', function (): void {
    expect($this->job->priority)->toBe(1);
});

afterEach(function (): void {
    Mockery::close();
});
