<?php

namespace App\Support;

use App\Models\Contact;

final class MailContacts
{
    /**
     * @return list<array{id: int, name: string, email: string, company: ?string}>
     */
    public static function search(?string $query, int $limit = 12): array
    {
        $query = trim((string) $query);
        $limit = max(1, min(20, $limit));

        $rows = Contact::query()
            ->with('clientCompany:id,name')
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when(mb_strlen($query) >= 2, function ($builder) use ($query) {
                $term = '%'.$query.'%';
                $builder->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('company', 'like', $term)
                        ->orWhereHas('clientCompany', fn ($company) => $company->where('name', 'like', $term));
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $rows
            ->map(fn (Contact $contact) => self::present($contact))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function emailsMatching(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(
                fn (array $hit) => strtolower($hit['email']),
                self::search($query, 8),
            ),
            fn (string $email) => $email !== '' && ! str_contains(strtolower($query), $email)
        )));
    }

    /**
     * @return array{id: int, name: string, email: string, company: ?string}|null
     */
    private static function present(Contact $contact): ?array
    {
        $email = strtolower(trim((string) $contact->email));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $email,
            'company' => $contact->companyLabel(),
        ];
    }
}
