<?php

namespace App\Services;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\CrmActivityType;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContactIngestService
{
    public function __construct(private TelegramBotService $telegram) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, preferred_channel?: ?string, company?: ?string, subject: string, message: string}  $payload
     */
    public function ingestFromWebsiteMessage(array $payload): ContactMessage
    {
        $message = DB::transaction(function () use ($payload) {
            $email = strtolower(trim($payload['email']));

            $contact = Contact::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $payload['name'],
                    'phone' => $payload['phone'] ?? null,
                    'company' => $payload['company'] ?? null,
                    'preferred_channel' => $payload['preferred_channel'] ?? null,
                    'status' => ContactStatus::Lead,
                    'source' => ContactSource::Website,
                ]
            );

            $message = ContactMessage::query()->create([
                'contact_id' => $contact->id,
                'name' => $payload['name'],
                'email' => $email,
                'phone' => $payload['phone'] ?? null,
                'preferred_channel' => $payload['preferred_channel'] ?? null,
                'company' => $payload['company'] ?? null,
                'subject' => $payload['subject'],
                'message' => $payload['message'],
            ]);

            CrmActivity::query()->create([
                'contact_id' => $contact->id,
                'type' => CrmActivityType::Email,
                'subject' => $payload['subject'],
                'body' => $payload['message'],
                'happened_at' => now(),
            ]);

            return $message;
        });

        try {
            $this->telegram->notifyContactMessage($message);
        } catch (\Throwable $e) {
            Log::warning('Falha ao notificar Telegram sobre mensagem do site', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $message;
    }

    public function linkOrCreateFromMessage(ContactMessage $message): Contact
    {
        return DB::transaction(function () use ($message) {
            if ($message->contact_id && $message->contact) {
                return $message->contact;
            }

            $email = strtolower(trim($message->email));

            $contact = Contact::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $message->name,
                    'phone' => $message->phone,
                    'company' => $message->company,
                    'preferred_channel' => $message->preferred_channel,
                    'status' => ContactStatus::Lead,
                    'source' => ContactSource::Website,
                ]
            );

            $message->update(['contact_id' => $contact->id]);

            return $contact;
        });
    }
}
