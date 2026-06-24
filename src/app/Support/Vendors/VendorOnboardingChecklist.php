<?php

namespace App\Support\Vendors;

class VendorOnboardingChecklist
{
    /**
     * @param array<string, mixed> $vendor
     * @param array<int, array<string, mixed>|object> $documents
     * @return array<string, mixed>
     */
    public static function evaluate(array $vendor, array $documents = []): array
    {
        $items = [
            self::item('business_profile', 'Business profile', self::filled($vendor, ['Vendor_Name', 'Email_1', 'Phone_No']), true, self::missing($vendor, ['Vendor_Name', 'Email_1', 'Phone_No'])),
            self::item('trade_details', 'Trade and tax details', self::filled($vendor, ['Trade_Name', 'CR_Number', 'VAT_Number']), true, self::missing($vendor, ['Trade_Name', 'CR_Number', 'VAT_Number'])),
            self::item('address', 'Registered address', self::filled($vendor, ['Address_Line1', 'Country_Id', 'Region_Id', 'City_Id']), true, self::missing($vendor, ['Address_Line1', 'Country_Id', 'Region_Id', 'City_Id'])),
            self::item('bank_payout', 'Bank and payout setup', self::bankComplete($vendor), true, self::bankMissing($vendor)),
            self::item('documents', 'Required documents', empty(self::missingDocuments($documents)), true, self::missingDocuments($documents)),
        ];

        $total = count($items);
        $completed = count(array_filter($items, fn (array $item) => (bool) $item['complete']));
        $missingRequired = array_values(array_map(
            fn (array $item) => $item['key'],
            array_filter($items, fn (array $item) => $item['required'] && !$item['complete'])
        ));

        return [
            'items' => array_values($items),
            'completed_count' => $completed,
            'total_count' => $total,
            'completeness_percent' => $total === 0 ? 100 : (int) round(($completed / $total) * 100),
            'missing_required' => $missingRequired,
            'readiness' => empty($missingRequired) ? 'ready_for_review' : 'incomplete',
        ];
    }

    /**
     * @param array<string, mixed> $vendor
     * @param array<int, string> $fields
     */
    private static function filled(array $vendor, array $fields): bool
    {
        return empty(self::missing($vendor, $fields));
    }

    /**
     * @param array<string, mixed> $vendor
     * @param array<int, string> $fields
     * @return array<int, string>
     */
    private static function missing(array $vendor, array $fields): array
    {
        return array_values(array_filter($fields, function (string $field) use ($vendor) {
            $value = $vendor[$field] ?? null;

            return $value === null || (is_string($value) && trim($value) === '');
        }));
    }

    /**
     * @param array<string, mixed> $vendor
     */
    private static function bankComplete(array $vendor): bool
    {
        return self::filled($vendor, ['Bank_Name', 'Bank_Account_Name', 'Payout_Method'])
            && self::hasAny($vendor, ['Bank_IBAN', 'Bank_Account_Number']);
    }

    /**
     * @param array<string, mixed> $vendor
     * @return array<int, string>
     */
    private static function bankMissing(array $vendor): array
    {
        $missing = self::missing($vendor, ['Bank_Name', 'Bank_Account_Name', 'Payout_Method']);

        if (!self::hasAny($vendor, ['Bank_IBAN', 'Bank_Account_Number'])) {
            $missing[] = 'Bank_IBAN_or_Bank_Account_Number';
        }

        return $missing;
    }

    /**
     * @param array<string, mixed> $vendor
     * @param array<int, string> $fields
     */
    private static function hasAny(array $vendor, array $fields): bool
    {
        foreach ($fields as $field) {
            $value = $vendor[$field] ?? null;

            if ($value !== null && (!is_string($value) || trim($value) !== '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>|object> $documents
     * @return array<int, string>
     */
    private static function missingDocuments(array $documents): array
    {
        $required = ['commercial_registration', 'vat_certificate', 'bank_letter'];
        $approved = [];

        foreach ($documents as $document) {
            $type = self::value($document, 'Document_Type');
            $status = strtolower((string) self::value($document, 'Status'));

            if ($type && in_array($status, ['approved', 'verified'], true)) {
                $approved[] = $type;
            }
        }

        return array_values(array_diff($required, $approved));
    }

    /**
     * @param array<string, mixed>|object $row
     */
    private static function value(array|object $row, string $key): mixed
    {
        return is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null);
    }

    /**
     * @param array<int, string> $missing
     * @return array<string, mixed>
     */
    private static function item(string $key, string $label, bool $complete, bool $required, array $missing): array
    {
        return compact('key', 'label', 'complete', 'required') + ['missing_fields' => $missing];
    }
}
