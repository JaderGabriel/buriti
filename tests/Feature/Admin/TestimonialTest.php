<?php

namespace Tests\Feature\Admin;

use App\Enums\TestimonialStatus;
use App\Models\Contact;
use App\Models\Project;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestimonialTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_nav_and_search_include_testimonials(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.testimonials.index'))
            ->assertOk()
            ->assertSee('Depoimentos', false)
            ->assertSee('Convidar cliente', false)
            ->assertSee('Nada disto aparece no site público ainda', false);

        $this->actingAs($this->admin)
            ->getJson(route('admin.search', ['q' => 'depoi']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Depoimentos',
                'type' => 'página',
                'subtitle' => 'Entrega',
            ]);
    }

    public function test_guest_and_staff_cannot_open_admin_testimonials(): void
    {
        $this->get(route('admin.testimonials.index'))
            ->assertRedirect(route('login'));

        $staff = User::factory()->withoutAdminAccess()->create();

        $this->actingAs($staff)
            ->get(route('admin.testimonials.index'))
            ->assertRedirect(route('jirau.home'));
    }

    public function test_invite_submit_moderate_and_link_project_keep_source_seal(): void
    {
        $contact = Contact::factory()->active()->create(['name' => 'Ana Cliente']);
        $project = Project::factory()->create(['name' => 'Portal da escola']);

        $invite = $this->actingAs($this->admin)
            ->post(route('admin.testimonials.store'), [
                'contact_id' => $contact->id,
                'project_id' => $project->id,
            ])
            ->assertRedirect();

        $testimonial = Testimonial::query()->first();
        $this->assertNotNull($testimonial);
        $this->assertSame(TestimonialStatus::Invited, $testimonial->status);
        $invite->assertSessionHas('invite_url');
        $url = session('invite_url');
        $this->assertIsString($url);
        $token = basename(parse_url($url, PHP_URL_PATH));

        $this->post(route('testimonials.submit', $token), [
            'stars' => 5,
            'body' => 'Entrega no prazo e comunicação clara do início ao fim.',
            'display_name' => 'Ana C.',
        ])->assertRedirect();

        $testimonial->refresh();
        $this->assertSame(TestimonialStatus::Submitted, $testimonial->status);
        $this->assertNotNull($testimonial->source_digest);
        $this->assertTrue($testimonial->authenticity()['ok']);
        $source = $testimonial->source_digest;

        $this->actingAs($this->admin)
            ->post(route('admin.testimonials.moderate', $testimonial), [
                'decision' => 'approved',
                'use_original' => '0',
                'published_stars' => 5,
                'published_body' => 'Entrega no prazo, com comunicação clara.',
                'published_display_name' => 'Ana C.',
            ])
            ->assertRedirect(route('admin.testimonials.show', $testimonial));

        $testimonial->refresh();
        $this->assertSame(TestimonialStatus::Approved, $testimonial->status);
        $this->assertTrue($testimonial->wasMediated());
        $this->assertSame($source, $testimonial->source_digest);
        $this->assertTrue($testimonial->authenticity()['ok']);
        $result = $testimonial->result_digest;

        $other = Project::factory()->create(['name' => 'Moodle interno']);
        $this->actingAs($this->admin)
            ->put(route('admin.testimonials.project', $testimonial), [
                'project_id' => $other->id,
            ])
            ->assertRedirect();

        $testimonial->refresh();
        $this->assertSame($other->id, $testimonial->project_id);
        $this->assertSame($source, $testimonial->source_digest);
        $this->assertNotSame($result, $testimonial->result_digest);
        $this->assertTrue($testimonial->authenticity()['ok']);
    }

    public function test_used_invite_token_cannot_be_reused(): void
    {
        $contact = Contact::factory()->create();
        $this->actingAs($this->admin)
            ->post(route('admin.testimonials.store'), ['contact_id' => $contact->id]);

        $url = session('invite_url');
        $token = basename((string) parse_url((string) $url, PHP_URL_PATH));

        $this->post(route('testimonials.submit', $token), [
            'stars' => 4,
            'body' => 'Bom trabalho técnico e acompanhamento próximo.',
            'display_name' => 'Carlos',
        ])->assertRedirect();

        $this->get(route('testimonials.write', $token))->assertNotFound();
        $this->post(route('testimonials.submit', $token), [
            'stars' => 1,
            'body' => 'Tentativa de alterar o depoimento já selado.',
            'display_name' => 'Carlos',
        ])->assertNotFound();
    }

    public function test_home_does_not_show_approved_testimonial(): void
    {
        $testimonial = Testimonial::factory()->approved()->create([
            'contact_id' => Contact::factory()->create(['name' => 'Cliente Secreto']),
            'published_body' => 'Frase única do depoimento que não pode aparecer na home.',
            'published_display_name' => 'Cliente Secreto',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Frase única do depoimento que não pode aparecer na home.', false)
            ->assertDontSee($testimonial->verification_code, false)
            ->assertDontSee('Cliente Secreto', false);
    }

    public function test_public_seal_confirms_approved_testimonial_without_listing_others(): void
    {
        $mine = Testimonial::factory()->approved()->create([
            'contact_id' => Contact::factory(),
            'published_body' => 'Texto visível só no selo quando o código é conhecido.',
            'published_display_name' => 'Joana',
        ]);
        $other = Testimonial::factory()->approved()->create([
            'contact_id' => Contact::factory(),
            'published_display_name' => 'Outro Cliente Oculto',
        ]);

        $this->get(route('testimonials.seal', ['code' => $mine->verification_code]))
            ->assertOk()
            ->assertSee('Selo autêntico', false)
            ->assertSee('Joana', false)
            ->assertDontSee('Outro Cliente Oculto', false)
            ->assertSee('noindex', false);

        $this->get(route('testimonials.seal', ['code' => $other->verification_code]))
            ->assertOk()
            ->assertDontSee($mine->published_display_name, false);
    }

    public function test_tampered_result_fails_public_seal(): void
    {
        $testimonial = Testimonial::factory()->approved()->create([
            'contact_id' => Contact::factory(),
        ]);

        $testimonial->forceFill(['published_body' => 'Texto forjado no resultado'])->save();

        $this->get(route('testimonials.seal', ['code' => $testimonial->verification_code]))
            ->assertOk()
            ->assertSee('Não há um depoimento aprovado com este código, ou o selo não confere.', false);
    }

    public function test_contact_dossier_links_to_invite(): void
    {
        $contact = Contact::factory()->create(['name' => 'Paulo Convite']);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', $contact))
            ->assertOk()
            ->assertSee('Depoimento', false)
            ->assertSee(route('admin.testimonials.create', ['contact_id' => $contact->id]), false);
    }
}
