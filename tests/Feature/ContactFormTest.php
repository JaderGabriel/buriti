<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_stores_message_with_phone(): void
    {
        $payload = [
            'name' => 'Cliente Teste',
            'email' => 'cliente@empresa.com',
            'phone_country' => 'BR',
            'phone_number' => '38991758416',
            'preferred_channel' => 'whatsapp',
            'company' => 'Empresa X',
            'subject' => 'Proposta de TI',
            'message' => 'Preciso de consultoria e desenvolvimento.',
            'privacy_consent' => '1',
        ];

        $response = $this->from(route('home'))->post(route('contact.store'), $payload);

        $response->assertRedirect(route('home').'#contato');
        $response->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'cliente@empresa.com',
            'phone' => '+55 38 99175-8416',
            'preferred_channel' => 'whatsapp',
            'subject' => 'Proposta de TI',
            'name' => 'Cliente Teste',
        ]);

        $this->assertDatabaseHas('contacts', [
            'email' => 'cliente@empresa.com',
            'name' => 'Cliente Teste',
            'company' => 'Empresa X',
            'source' => 'website',
            'status' => 'lead',
        ]);

        $this->assertDatabaseHas('companies', [
            'name' => 'Empresa X',
        ]);

        $contact = Contact::query()->where('email', 'cliente@empresa.com')->first();
        $message = ContactMessage::query()->where('email', 'cliente@empresa.com')->first();

        $this->assertNotNull($contact);
        $this->assertNotNull($contact->company_id);
        $this->assertNotNull($message);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertDatabaseHas('crm_activities', [
            'contact_id' => $contact->id,
            'type' => 'email',
            'subject' => 'Proposta de TI',
        ]);
    }

    public function test_contact_form_updates_existing_contact_by_email(): void
    {
        $contact = Contact::factory()->create([
            'email' => 'cliente@empresa.com',
            'name' => 'Nome Antigo',
            'phone' => '+55 11000000000',
        ]);

        $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Nome Novo',
            'email' => 'cliente@empresa.com',
            'phone_country' => 'BR',
            'phone_number' => '38991758416',
            'preferred_channel' => 'whatsapp',
            'company' => 'Empresa Nova',
            'subject' => 'Atualização',
            'message' => 'Segunda mensagem',
            'privacy_consent' => '1',
        ])->assertSessionHas('contact_success');

        $this->assertSame(1, Contact::query()->where('email', 'cliente@empresa.com')->count());
        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'name' => 'Nome Novo',
            'phone' => '+55 38 99175-8416',
            'company' => 'Empresa Nova',
        ]);

        $this->assertDatabaseHas('companies', ['name' => 'Empresa Nova']);
        $this->assertNotNull($contact->fresh()->company_id);
    }

    public function test_contact_form_accepts_foreign_country_dial_code(): void
    {
        $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Cliente PT',
            'email' => 'pt@empresa.com',
            'phone_country' => 'PT',
            'phone_number' => '912345678',
            'preferred_channel' => 'phone',
            'subject' => 'Proposta',
            'message' => 'Olá de Portugal',
            'privacy_consent' => '1',
        ])->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'pt@empresa.com',
            'phone' => '+351 912345678',
        ]);
    }

    public function test_contact_form_requires_phone_and_core_fields(): void
    {
        $response = $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Cliente',
            'email' => 'cliente@empresa.com',
            'subject' => 'Proposta',
            'message' => 'Quero falar sobre TI',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors(['phone_number', 'preferred_channel', 'privacy_consent']);
        $this->assertSame(0, ContactMessage::query()->count());
        $this->assertSame(0, Contact::query()->count());
    }

    public function test_contact_form_requires_privacy_consent(): void
    {
        $response = $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Cliente',
            'email' => 'cliente@empresa.com',
            'phone_country' => 'BR',
            'phone_number' => '38991758416',
            'preferred_channel' => 'whatsapp',
            'subject' => 'Proposta',
            'message' => 'Quero falar sobre TI',
        ]);

        $response->assertSessionHasErrors('privacy_consent');
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_contact_form_rejects_honeypot_spam(): void
    {
        $response = $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@spam.com',
            'phone_country' => 'BR',
            'phone_number' => '11999999999',
            'preferred_channel' => 'phone',
            'subject' => 'Spam',
            'message' => 'Mensagem automática',
            'privacy_consent' => '1',
            'website' => 'https://spam.example',
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertSame(0, ContactMessage::query()->count());
        $this->assertSame(0, CrmActivity::query()->count());
    }

    public function test_home_page_shows_country_flag_selector(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('🇧🇷 Brasil', false)
            ->assertSee('🇵🇹 Portugal', false)
            ->assertSee('Escolha o país pela bandeira/nome', false)
            ->assertSee('buritiPhoneCountryField', false)
            ->assertSee('data-phone-field', false)
            ->assertDontSee('c.iso === this.iso', false);
    }
}
