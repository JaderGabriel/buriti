<?php

namespace App\Console\Commands;

use App\Services\CompanyResolver;
use App\Models\Contact;
use Illuminate\Console\Command;

class BackfillContactCompaniesCommand extends Command
{
    protected $signature = 'crm:backfill-companies {--dry-run : Só mostra o que seria feito}';

    protected $description = 'Importa contacts.company (texto) para companies + company_id';

    public function handle(CompanyResolver $resolver): int
    {
        $dry = (bool) $this->option('dry-run');
        $query = Contact::query()
            ->whereNull('company_id')
            ->whereNotNull('company')
            ->where('company', '!=', '');

        $count = $query->count();
        $this->info("Contactos com texto de empresa e sem FK: {$count}");

        $updated = 0;
        $query->orderBy('id')->each(function (Contact $contact) use ($resolver, $dry, &$updated) {
            $company = $resolver->findOrCreateByName($contact->company);
            if (! $company) {
                return;
            }

            if (! $dry) {
                $contact->forceFill([
                    'company_id' => $company->id,
                    'company' => $company->name,
                ])->save();
            }
            $updated++;
        });

        $this->info(($dry ? 'Seriam actualizados' : 'Actualizados').": {$updated}");

        $orphans = \App\Models\Opportunity::query()
            ->whereNull('company_id')
            ->whereHas('contact', fn ($q) => $q->whereNotNull('company_id'))
            ->count();

        if (! $dry) {
            \App\Models\Opportunity::query()
                ->whereNull('company_id')
                ->whereHas('contact', fn ($q) => $q->whereNotNull('company_id'))
                ->each(function (\App\Models\Opportunity $opportunity) {
                    $opportunity->update(['company_id' => $opportunity->contact?->company_id]);
                });
        }

        $this->info(($dry ? 'Oportunidades que herdariam empresa do contacto' : 'Oportunidades alinhadas ao contacto').": {$orphans}");

        return self::SUCCESS;
    }
}
