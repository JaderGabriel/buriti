<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;

class ContactController extends Controller
{
    public function store(StoreContactRequest $request): RedirectResponse
    {
        ContactMessage::query()->create($request->safe()->except('website'));

        return redirect()
            ->to(url()->previous().'#contato')
            ->with('contact_success', 'Mensagem enviada. A BURI-TI responde em breve.');
    }
}
