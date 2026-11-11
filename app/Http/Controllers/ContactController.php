<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __invoke(): Response
    {
        $contact = Contact::current();

        return Inertia::render('Contact', [
            'contact' => [
                'company_name' => $contact->company_name,
                'street' => $contact->street,
                'city' => $contact->city,
                'contact_person' => $contact->contact_person,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'registration_number' => $contact->registration_number,
                'vat_number' => $contact->vat_number,
                'data_box' => $contact->data_box,
                'bank_account' => $contact->bank_account,
                'registry_note' => $contact->registry_note,
                'google_maps_url' => $contact->google_maps_url,
                'waze_url' => $contact->waze_url,
                'seznam_maps_url' => $contact->seznam_maps_url,
            ],
        ]);
    }
}
