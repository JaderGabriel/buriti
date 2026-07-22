<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Services\ContactIngestService;
use Illuminate\Http\RedirectResponse;

class ContactController extends Controller
{
    public function __construct(private ContactIngestService $ingest) {}

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $payload = $request->safe()->except([
            'website',
            'phone_country',
            'phone_number',
        ]);

        $this->ingest->ingestFromWebsiteMessage($payload);

        return redirect()
            ->to(url()->previous().'#contato')
            ->with('contact_success', 'Mensagem enviada. A BURI-TI responde em breve.');
    }
}
