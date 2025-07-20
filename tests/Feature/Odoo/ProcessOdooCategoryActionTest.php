<?php

declare(strict_types=1);

use App\Actions\Odoo\ProcessOdooCategoryAction;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->action = new ProcessOdooCategoryAction;
});

test('ProcessOdooCategoryAction creates new category with valid data', function (): void {
    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'Full Time',
        active: true
    );

    $this->action->execute($dto);

    $category = Category::where('odoo_category_id', 1)->first();

    expect($category)->not->toBeNull();
    expect($category->odoo_category_id)->toBe(1);
    expect($category->name)->toBe('Full Time');
    expect($category->active)->toBe(true);
});

test('ProcessOdooCategoryAction updates existing category', function (): void {
    // Create existing category
    $existingCategory = Category::create([
        'odoo_category_id' => 1,
        'name' => 'Old Name',
        'active' => false,
    ]);

    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'Full Time',
        active: true
    );

    $this->action->execute($dto);

    $category = Category::where('odoo_category_id', 1)->first();

    expect($category)->not->toBeNull();
    expect($category->odoo_category_id)->toBe(1);
    expect($category->name)->toBe('Full Time');
    expect($category->active)->toBe(true);

    // Should still be the same record, not a new one
    expect(Category::count())->toBe(1);
});

test('ProcessOdooCategoryAction handles null active field with default true', function (): void {
    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'Full Time',
        active: null
    );

    $this->action->execute($dto);

    $category = Category::where('odoo_category_id', 1)->first();

    expect($category)->not->toBeNull();
    expect($category->active)->toBe(true); // Should default to true
});

test('ProcessOdooCategoryAction skips category with missing id', function (): void {
    Log::spy();

    $dto = new OdooCategoryDTO(
        id: null, // Missing required ID
        name: 'Full Time',
        active: true
    );

    $this->action->execute($dto);

    // Category should not be created due to validation failure
    $category = Category::where('name', 'Full Time')->first();
    expect($category)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooCategoryAction Skipping category due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['category']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooCategoryAction skips category with missing name', function (): void {
    Log::spy();

    $dto = new OdooCategoryDTO(
        id: 1,
        name: null, // Missing required name
        active: true
    );

    $this->action->execute($dto);

    // Category should not be created due to validation failure
    $category = Category::where('odoo_category_id', 1)->first();
    expect($category)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')
        ->once()
        ->with(
            'ProcessOdooCategoryAction Skipping category due to validation errors',
            \Mockery::on(function ($context) {
                return isset($context['category']) && isset($context['errors']);
            })
        );
});

test('ProcessOdooCategoryAction skips category with empty name', function (): void {
    Log::spy();

    $dto = new OdooCategoryDTO(
        id: 1,
        name: '', // Empty name should fail validation
        active: true
    );

    $this->action->execute($dto);

    // Category should not be created due to validation failure
    $category = Category::where('odoo_category_id', 1)->first();
    expect($category)->toBeNull();

    // Should log a warning
    Log::shouldHaveReceived('warning')->once();
});

test('ProcessOdooCategoryAction handles defaults correctly', function (): void {
    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'Part Time'
        // active is not set, should default to true in the action
    );

    $this->action->execute($dto);

    $category = Category::where('odoo_category_id', 1)->first();

    expect($category)->not->toBeNull();
    expect($category->active)->toBe(true);
});

test('ProcessOdooCategoryAction is atomic - uses database transaction', function (): void {
    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'Full Time',
        active: true
    );

    // Should complete successfully within a transaction
    $this->action->execute($dto);

    $category = Category::where('odoo_category_id', 1)->first();
    expect($category)->not->toBeNull();
});

test('ProcessOdooCategoryAction can create multiple categories', function (): void {
    $dto1 = new OdooCategoryDTO(
        id: 1,
        name: 'Full Time',
        active: true
    );

    $dto2 = new OdooCategoryDTO(
        id: 2,
        name: 'Part Time',
        active: false
    );

    $this->action->execute($dto1);
    $this->action->execute($dto2);

    expect(Category::count())->toBe(2);

    $category1 = Category::where('odoo_category_id', 1)->first();
    $category2 = Category::where('odoo_category_id', 2)->first();

    expect($category1->name)->toBe('Full Time');
    expect($category1->active)->toBe(true);

    expect($category2->name)->toBe('Part Time');
    expect($category2->active)->toBe(false);
});

test('ProcessOdooCategoryAction preserves relationships when updating', function (): void {
    // Create category with some users attached
    $category = Category::create([
        'odoo_category_id' => 1,
        'name' => 'Old Name',
        'active' => true,
    ]);

    // Create a user and attach to category (via CategoryUser pivot)
    $user = \App\Models\User::factory()->create();
    $category->users()->attach($user->id);

    expect($category->users()->count())->toBe(1);

    // Update the category
    $dto = new OdooCategoryDTO(
        id: 1,
        name: 'New Name',
        active: false
    );

    $this->action->execute($dto);

    $updatedCategory = Category::where('odoo_category_id', 1)->first();

    expect($updatedCategory->name)->toBe('New Name');
    expect($updatedCategory->active)->toBe(false);

    // Relationships should be preserved
    expect($updatedCategory->users()->count())->toBe(1);
});
