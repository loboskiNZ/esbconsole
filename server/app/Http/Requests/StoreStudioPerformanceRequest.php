<?php

namespace App\Http\Requests;

use App\Models\Performance;
use App\Models\Show;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudioPerformanceRequest extends FormRequest
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
        $bandId = (int) config('portal.band_id', 1);

        return [
            'show_id' => [
                'required',
                'integer',
                Rule::exists('shows', 'id')->where(fn ($query) => $query->where('band_id', $bandId)),
            ],
            'performance_type' => ['required', 'string', Rule::in([Performance::TYPE_REHEARSAL, Performance::TYPE_LIVE])],
            'status' => ['required', 'string', Rule::in([Performance::STATUS_NOT_CONFIRMED, Performance::STATUS_CONFIRMED])],
            'location_name' => ['required', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:5000'],
            'performance_date' => ['required', 'date'],
            'prep_time' => ['nullable', 'date_format:H:i'],
            'performance_time' => ['nullable', 'date_format:H:i'],
            'performance_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'packup_time' => ['nullable', 'date_format:H:i'],
            'briefing_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array{
     *     show_id: int,
     *     performance_type: string,
     *     status: string,
     *     location_name: string,
     *     location_address: string|null,
     *     performance_date: string,
     *     prep_time: string|null,
     *     performance_time: string|null,
     *     performance_duration_minutes: int|null,
     *     packup_time: string|null,
     *     briefing_notes: string|null,
     * }
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        return [
            'show_id' => (int) $validated['show_id'],
            'performance_type' => $validated['performance_type'],
            'status' => $validated['status'],
            'location_name' => trim((string) $validated['location_name']),
            'location_address' => $validated['location_address'] ?? null,
            'performance_date' => $validated['performance_date'],
            'prep_time' => $validated['prep_time'] ?? null,
            'performance_time' => $validated['performance_time'] ?? null,
            'performance_duration_minutes' => isset($validated['performance_duration_minutes'])
                ? (int) $validated['performance_duration_minutes']
                : null,
            'packup_time' => $validated['packup_time'] ?? null,
            'briefing_notes' => $validated['briefing_notes'] ?? null,
        ];
    }
}
