<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyFaviconTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Storage::fake('public');
    }

    public function test_creating_company_with_website_downloads_favicon(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Http::fake([
            'https://exemplo.com' => Http::response(
                '<html><head><link rel="icon" href="/brand-icon.png"></head></html>',
                200,
                ['Content-Type' => 'text/html']
            ),
            'https://exemplo.com/brand-icon.png' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.companies.store'), [
                'name' => 'Empresa Favicon',
                'status' => 'active',
                'website_url' => 'https://exemplo.com',
            ])
            ->assertRedirect();

        $company = Company::query()->where('name', 'Empresa Favicon')->first();
        $this->assertNotNull($company);
        $this->assertNotNull($company->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($company->logo_path));
        $this->assertNotNull($company->logoUrl());
    }

    public function test_updating_website_refetches_favicon_for_new_origin(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $company = Company::factory()->create([
            'name' => 'Troca Site',
            'website_url' => 'https://antiga.com',
            'logo_path' => 'companies/logos/old.png',
        ]);
        Storage::disk('public')->put('companies/logos/old.png', 'old');

        Http::fake([
            'https://nova.com' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
            'https://nova.com/favicon.ico' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.companies.update', $company), [
                'name' => 'Troca Site',
                'status' => 'active',
                'website_url' => 'https://nova.com',
            ])
            ->assertRedirect();

        $company->refresh();
        $this->assertNotNull($company->logo_path);
        $this->assertNotSame('companies/logos/old.png', $company->logo_path);
        $this->assertTrue(Storage::disk('public')->exists($company->logo_path));
        $this->assertFalse(Storage::disk('public')->exists('companies/logos/old.png'));
    }

    public function test_same_website_keeps_existing_logo(): void
    {
        $company = Company::factory()->create([
            'name' => 'Mesmo Site',
            'website_url' => 'https://mesmo.com',
            'logo_path' => 'companies/logos/keep.png',
        ]);
        Storage::disk('public')->put('companies/logos/keep.png', 'keep');

        Http::fake();

        $this->actingAs($this->admin)
            ->put(route('admin.companies.update', $company), [
                'name' => 'Mesmo Site',
                'status' => 'active',
                'website_url' => 'https://mesmo.com/',
            ])
            ->assertRedirect();

        $company->refresh();
        $this->assertSame('companies/logos/keep.png', $company->logo_path);
        Http::assertNothingSent();
    }
}
