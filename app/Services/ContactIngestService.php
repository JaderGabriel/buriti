<?php

namespace App\Services;

use App\Enums\ContactSource;
use App\Enums\ContactStatus;
use App\Enums\CrmActivityType;
use App\Enums\NotificationType;
use App\Enums\OpportunityStage;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Services\Telegram\TelegramBotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactIngestService
{
    public function __construct(
        private TelegramBotService $telegram,
        private CompanyResolver $companies,
        private UserNotificationService $notifications,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, preferred_channel?: ?string, company?: ?string, subject: string, message: string}  $payload
     */
    public function ingestFromWebsiteMessage(array $payload): ContactMessage
    {
        $message = DB::transaction(function () use ($payload) {
            $email = strtolower(trim($payload['email']));
            $company = $this->companies->findOrCreateByName($payload['company'] ?? null);

            $contact = $this->matchOrCreateContact(
                $email,
                $payload['name'],
                $payload['phone'] ?? null,
                $payload['preferred_channel'] ?? null,
                $company,
                $payload['company'] ?? null,
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

            $opportunity = $this->ensureLeadOpportunity($contact, $payload);

            CrmActivity::query()->create([
                'contact_id' => $contact->id,
                'opportunity_id' => $opportunity->id,
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

        try {
            $this->notifications->notifyAdmins(
                NotificationType::Message,
                'Nova mensagem do site',
                $message->name.': '.$message->subject,
                route('admin.messages.show', $message),
            );
        } catch (\Throwable $e) {
            Log::warning('Falha ao registrar notificação da mensagem do site', [
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
            $company = $this->companies->findOrCreateByName($message->company);

            $contact = $this->matchOrCreateContact(
                $email,
                $message->name,
                $message->phone,
                $message->preferred_channel,
                $company,
                $message->company,
            );

            $message->update(['contact_id' => $contact->id]);

            $this->ensureLeadOpportunity($contact, [
                'subject' => $message->subject,
                'message' => $message->message,
                'name' => $message->name,
            ]);

            return $contact;
        });
    }

    private function matchOrCreateContact(
        string $email,
        string $name,
        ?string $phone,
        ?string $preferredChannel,
        ?Company $company,
        ?string $companyName,
    ): Contact {
        $contact = Contact::query()->where('email', $email)->first();

        if ($contact) {
            $updates = [];
            if (blank($contact->phone) && filled($phone)) {
                $updates['phone'] = $phone;
            }
            if (blank($contact->preferred_channel) && filled($preferredChannel)) {
                $updates['preferred_channel'] = $preferredChannel;
            }
            if ($contact->company_id === null && $company) {
                $updates['company_id'] = $company->id;
                $updates['company'] = $company->name;
            }
            if ($updates !== []) {
                $contact->update($updates);
            }

            return $contact->fresh() ?? $contact;
        }

        return Contact::query()->create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'company' => $company?->name ?? $companyName,
            'company_id' => $company?->id,
            'preferred_channel' => $preferredChannel,
            'status' => ContactStatus::Lead,
            'source' => ContactSource::Website,
        ]);
    }

    /**
     * Garante um card Lead no pipeline (reutiliza oportunidade aberta do contato).
     *
     * @param  array{subject?: ?string, message?: ?string, name?: ?string}  $payload
     */
    private function ensureLeadOpportunity(Contact $contact, array $payload): Opportunity
    {
        $existing = Opportunity::query()
            ->where('contact_id', $contact->id)
            ->open()
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        $subject = trim((string) ($payload['subject'] ?? ''));
        $body = trim((string) ($payload['message'] ?? ''));
        $name = trim((string) ($payload['name'] ?? $contact->name));

        return Opportunity::query()->create([
            'contact_id' => $contact->id,
            'company_id' => $contact->company_id,
            'title' => $subject !== ''
                ? Str::limit($subject, 160)
                : 'Lead do site — '.Str::limit($name, 120),
            'description' => $body !== '' ? Str::limit($body, 5000) : null,
            'stage' => OpportunityStage::Lead,
        ]);
    }
}
