<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_stores_message(): void
    {
        $payload = [
            'name' => 'Cliente Teste',
            'email' => 'cliente@empresa.com',
            'phone' => '38991758416',
            'company' => 'Empresa X',
            'subject' => 'Proposta de TI',
            'message' => 'Preciso de consultoria e desenvolvimento.',
        ];

        $response = $this->from(route('home'))->post(route('contact.store'), $payload);

        $response->assertRedirect(route('home').'#contato');
        $response->assertSessionHas('contact_success');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'cliente@empresa.com',
            'subject' => 'Proposta de TI',
            'name' => 'Cliente Teste',
        ]);
    }

    public function test_contact_form_requires_core_fields(): void
    {
        $response = $this->from(route('home'))->post(route('contact.store'), []);

        $response->assertRedirect(route('home'));
        $response->assertSessionHasErrors(['name', 'email', 'subject', 'message']);
        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_contact_form_rejects_honeypot_spam(): void
    {
        $response = $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Bot',
            'email' => 'bot@spam.com',
            'subject' => 'Spam',
            'message' => 'Mensagem automática',
            'website' => 'https://spam.example',
        ]);

        $response->assertSessionHasErrors('website');
        $this->assertSame(0, ContactMessage::query()->count());
    }
}
