<?php

declare(strict_types=1);

use App\DataTransferObjects\Odoo\OdooUserDTO;

test('OdooUserDTO can be instantiated with all properties', function (): void {
    $dto = new OdooUserDTO(
        id: 1,
        work_email: 'john.doe@company.com',
        name: 'John Doe',
        tz: 'Europe/Madrid',
        active: true,
        department_id: [2, 'Engineering'],
        category_ids: [1, 3],
        resource_calendar_id: [1, 'Standard 40h'],
        job_title: 'Senior Developer',
        parent_id: [5, 'Jane Manager']
    );

    expect($dto)
        ->toBeInstanceOf(OdooUserDTO::class)
        ->toHaveProperty('id', 1)
        ->toHaveProperty('work_email', 'john.doe@company.com')
        ->toHaveProperty('name', 'John Doe')
        ->toHaveProperty('tz', 'Europe/Madrid')
        ->toHaveProperty('active', true)
        ->toHaveProperty('department_id', [2, 'Engineering'])
        ->toHaveProperty('category_ids', [1, 3])
        ->toHaveProperty('resource_calendar_id', [1, 'Standard 40h'])
        ->toHaveProperty('job_title', 'Senior Developer')
        ->toHaveProperty('parent_id', [5, 'Jane Manager']);
});

test('OdooUserDTO can be instantiated with minimal properties', function (): void {
    $dto = new OdooUserDTO;

    expect($dto)
        ->toBeInstanceOf(OdooUserDTO::class)
        ->toHaveProperty('id', null)
        ->toHaveProperty('work_email', null)
        ->toHaveProperty('name', null)
        ->toHaveProperty('tz', null)
        ->toHaveProperty('active', true) // Default value
        ->toHaveProperty('department_id', null)
        ->toHaveProperty('category_ids', []) // Default value
        ->toHaveProperty('resource_calendar_id', null)
        ->toHaveProperty('job_title', null)
        ->toHaveProperty('parent_id', null);
});

test('OdooUserDTO handles null and false values correctly', function (): void {
    // Simulating how OdooApiClient would create the DTO with null conversions
    $dto = new OdooUserDTO(
        id: 3,
        work_email: null, // converted from false
        name: 'Bob Wilson',
        tz: null, // converted from false
        active: false,
        department_id: null, // converted from false
        category_ids: [], // empty array
        resource_calendar_id: null, // converted from false
        job_title: null, // converted from false
        parent_id: null // converted from false
    );

    expect($dto)
        ->toBeInstanceOf(OdooUserDTO::class)
        ->toHaveProperty('id', 3)
        ->toHaveProperty('work_email', null)
        ->toHaveProperty('name', 'Bob Wilson')
        ->toHaveProperty('tz', null)
        ->toHaveProperty('active', false)
        ->toHaveProperty('department_id', null)
        ->toHaveProperty('category_ids', [])
        ->toHaveProperty('resource_calendar_id', null)
        ->toHaveProperty('job_title', null)
        ->toHaveProperty('parent_id', null);
});

test('OdooUserDTO category_ids is always an array', function (): void {
    $dtoWithCategories = new OdooUserDTO(category_ids: [1, 2, 3]);
    $dtoEmptyCategories = new OdooUserDTO(category_ids: []);
    $dtoDefaultCategories = new OdooUserDTO;

    expect($dtoWithCategories->category_ids)
        ->toBeArray()
        ->toHaveCount(3)
        ->toContain(1, 2, 3);

    expect($dtoEmptyCategories->category_ids)
        ->toBeArray()
        ->toBeEmpty();

    expect($dtoDefaultCategories->category_ids)
        ->toBeArray()
        ->toBeEmpty();
});

test('OdooUserDTO relation fields accept arrays or null', function (): void {
    // Test with valid relation arrays
    $dtoWithRelations = new OdooUserDTO(
        department_id: [2, 'Engineering'],
        resource_calendar_id: [1, 'Standard 40h'],
        parent_id: [5, 'Jane Manager']
    );

    expect($dtoWithRelations->department_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([2, 'Engineering']);

    expect($dtoWithRelations->resource_calendar_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([1, 'Standard 40h']);

    expect($dtoWithRelations->parent_id)
        ->toBeArray()
        ->toHaveCount(2)
        ->toEqual([5, 'Jane Manager']);

    // Test with null values
    $dtoWithNulls = new OdooUserDTO(
        department_id: null,
        resource_calendar_id: null,
        parent_id: null
    );

    expect($dtoWithNulls->department_id)->toBeNull();
    expect($dtoWithNulls->resource_calendar_id)->toBeNull();
    expect($dtoWithNulls->parent_id)->toBeNull();
});
