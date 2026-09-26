<?php

namespace App\Http\Requests;

use App\Support\Iso4217;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class UpdatePerformanceTicketingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDirector() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['required', 'timezone'],
            'sales_open_at' => ['nullable', 'date'],
            'sales_close_at' => ['nullable', 'date'],
            'public_sales_enabled' => ['sometimes', 'boolean'],
            'walk_in_sales_enabled' => ['sometimes', 'boolean'],
            'interest_registration_enabled' => ['sometimes', 'boolean'],
            'private_offers_enabled' => ['sometimes', 'boolean'],
            'marketing_registration_enabled' => ['sometimes', 'boolean'],
            'offer_validity_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'abandoned_checkout_reminder_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'complimentary_allocation' => ['required', 'integer', 'min:0', 'max:100000'],
            'promotional_allocation' => ['required', 'integer', 'min:0', 'max:100000'],
            'tiers' => ['nullable', 'array'],
            'tiers.*.public_id' => ['nullable', 'uuid'],
            'tiers.*.name' => ['nullable', 'string', 'max:120'],
            'tiers.*.amount' => ['nullable', 'string', 'max:20'],
            'tiers.*.currency' => ['nullable', 'string', 'max:3'],
            'tiers.*.category' => ['nullable', 'string', 'max:80'],
            'tiers.*.starts_at' => ['nullable', 'date'],
            'tiers.*.ends_at' => ['nullable', 'date'],
            'tiers.*.enabled' => ['nullable', 'boolean'],
            'tiers.*.private_offer' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $enabled = $this->boolean('enabled');
            $currency = strtoupper(trim((string) $this->input('currency', '')));
            $capacity = $this->input('capacity');
            $capacityValue = $capacity === null || $capacity === '' ? null : (int) $capacity;
            $complimentary = (int) $this->input('complimentary_allocation');
            $promotional = (int) $this->input('promotional_allocation');

            if ($currency !== '' && ! Iso4217::isValid($currency)) {
                $validator->errors()->add('currency', 'Enter an ISO 4217 currency code.');
            }

            if ($enabled && $currency === '') {
                $validator->errors()->add('currency', 'Choose a currency before enabling ticketing.');
            }

            if (($enabled || $complimentary > 0 || $promotional > 0) && $capacityValue === null) {
                $validator->errors()->add('capacity', 'Set a venue capacity before enabling ticketing or reservations.');
            }

            $open = $this->input('sales_open_at');
            $close = $this->input('sales_close_at');
            if (is_string($open) && $open !== '' && is_string($close) && $close !== '' && strtotime($close) <= strtotime($open)) {
                $validator->errors()->add('sales_close_at', 'Sales must close after they open.');
            }

            $tiers = $this->filledTiers($validator);
            $needsTier = $this->boolean('public_sales_enabled') || $this->boolean('private_offers_enabled');
            $enabledTier = false;
            $enabledPrivateOffer = false;
            $disabledPrivateOffer = false;

            foreach ($tiers as $tier) {
                if ($tier['enabled']) {
                    $enabledTier = true;
                }

                if ($tier['private_offer'] && $tier['enabled']) {
                    $enabledPrivateOffer = true;
                }

                if ($tier['private_offer'] && ! $tier['enabled']) {
                    $disabledPrivateOffer = true;
                }

                if ($tier['starts_at'] !== null && $tier['ends_at'] !== null && strtotime($tier['ends_at']) <= strtotime($tier['starts_at'])) {
                    $validator->errors()->add('tiers', 'A price tier must end after it starts.');
                }

                try {
                    Money::minorUnits($tier['amount'], $tier['currency']);
                } catch (InvalidArgumentException $exception) {
                    $validator->errors()->add('tiers', $exception->getMessage());
                }
            }

            if ($needsTier && ! $enabledTier) {
                $validator->errors()->add('tiers', 'Add an enabled price tier before opening public sales or private offers.');
            }

            if ($disabledPrivateOffer) {
                $validator->errors()->add('tiers', 'A private-offer tier must be enabled.');
            }

            if ($this->boolean('private_offers_enabled') && ! $enabledPrivateOffer) {
                $validator->errors()->add('tiers', 'Mark at least one enabled price tier as a private offer.');
            }
        });
    }

    /**
     * @return array{
     *     enabled: bool,
     *     capacity: ?int,
     *     currency: ?string,
     *     timezone: string,
     *     sales_open_at: ?string,
     *     sales_close_at: ?string,
     *     public_sales_enabled: bool,
     *     walk_in_sales_enabled: bool,
     *     interest_registration_enabled: bool,
     *     private_offers_enabled: bool,
     *     marketing_registration_enabled: bool,
     *     offer_validity_minutes: int,
     *     abandoned_checkout_reminder_minutes: int,
     *     complimentary_allocation: int,
     *     promotional_allocation: int,
     *     tiers: list<array{
     *         public_id: ?string,
     *         name: string,
     *         amount: string,
     *         currency: string,
     *         category: ?string,
     *         starts_at: ?string,
     *         ends_at: ?string,
     *         enabled: bool,
     *         private_offer: bool,
     *     }>,
     * }
     */
    public function validatedPayload(): array
    {
        $capacity = $this->input('capacity');
        $currency = strtoupper(trim((string) $this->input('currency', '')));
        $tiers = $this->filledTiers();

        return [
            'enabled' => $this->boolean('enabled'),
            'capacity' => $capacity === null || $capacity === '' ? null : (int) $capacity,
            'currency' => $currency === '' ? null : $currency,
            'timezone' => (string) $this->input('timezone'),
            'sales_open_at' => $this->nullableString('sales_open_at'),
            'sales_close_at' => $this->nullableString('sales_close_at'),
            'public_sales_enabled' => $this->boolean('public_sales_enabled'),
            'walk_in_sales_enabled' => $this->boolean('walk_in_sales_enabled'),
            'interest_registration_enabled' => $this->boolean('interest_registration_enabled'),
            'private_offers_enabled' => $this->boolean('private_offers_enabled'),
            'marketing_registration_enabled' => $this->boolean('marketing_registration_enabled'),
            'offer_validity_minutes' => (int) $this->input('offer_validity_minutes'),
            'abandoned_checkout_reminder_minutes' => (int) $this->input('abandoned_checkout_reminder_minutes'),
            'complimentary_allocation' => (int) $this->input('complimentary_allocation'),
            'promotional_allocation' => (int) $this->input('promotional_allocation'),
            'tiers' => $tiers,
        ];
    }

    /**
     * @return list<array{
     *     public_id: ?string,
     *     name: string,
     *     amount: string,
     *     currency: string,
     *     category: ?string,
     *     starts_at: ?string,
     *     ends_at: ?string,
     *     enabled: bool,
     *     private_offer: bool,
     * }>
     */
    private function filledTiers(?Validator $validator = null): array
    {
        $rows = $this->input('tiers', []);
        if (! is_array($rows)) {
            return [];
        }

        $filled = [];

        foreach (array_values($rows) as $index => $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $publicId = $this->nullableValue($tier['public_id'] ?? null);
            $name = trim((string) ($tier['name'] ?? ''));
            $amount = trim((string) ($tier['amount'] ?? ''));
            $currency = strtoupper(trim((string) ($tier['currency'] ?? '')));
            $category = $this->nullableValue($tier['category'] ?? null);
            $starts = $this->nullableValue($tier['starts_at'] ?? null);
            $ends = $this->nullableValue($tier['ends_at'] ?? null);
            $blank = $publicId === null && $name === '' && $amount === '' && $currency === '' && $category === null && $starts === null && $ends === null;

            if ($blank) {
                continue;
            }

            if ($validator !== null && ($name === '' || $amount === '' || $currency === '')) {
                $validator->errors()->add('tiers', 'Each price tier needs a name, amount, and currency.');
            }

            if ($validator !== null && $currency !== '' && ! Iso4217::isValid($currency)) {
                $validator->errors()->add('tiers', 'Price tiers must use an ISO 4217 currency code.');
            }

            $filled[] = [
                'public_id' => $publicId,
                'name' => $name,
                'amount' => $amount,
                'currency' => $currency,
                'category' => $category,
                'starts_at' => $starts,
                'ends_at' => $ends,
                'enabled' => filter_var($tier['enabled'] ?? false, FILTER_VALIDATE_BOOL),
                'private_offer' => filter_var($tier['private_offer'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return $filled;
    }

    private function nullableString(string $key): ?string
    {
        return $this->nullableValue($this->input($key));
    }

    private function nullableValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
