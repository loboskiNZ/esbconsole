@props([
    'shows',
    'performance' => null,
])

@php
    $selectedShowId = old('show_id', $performance?->show_id);
@endphp

<div class="esb-studio__band-form-grid">
    <div>
        <label class="esb-portal__label mb-2 block" for="performance-show">Show</label>
        <select id="performance-show" name="show_id" class="esb-portal__input" required>
            <option value="" disabled @selected($selectedShowId === null)>Select a show</option>
            @foreach ($shows as $show)
                <option value="{{ $show->id }}" @selected((string) $selectedShowId === (string) $show->id)>
                    {{ $show->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-type">Type</label>
        <select id="performance-type" name="performance_type" class="esb-portal__input" required>
            <option value="rehearsal" @selected(old('performance_type', $performance?->performance_type ?? 'rehearsal') === 'rehearsal')>Rehearsal</option>
            <option value="live" @selected(old('performance_type', $performance?->performance_type) === 'live')>Live</option>
        </select>
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-status">Status</label>
        <select id="performance-status" name="status" class="esb-portal__input" required>
            <option value="not_confirmed" @selected(old('status', $performance?->status ?? 'not_confirmed') === 'not_confirmed')>Not confirmed</option>
            <option value="confirmed" @selected(old('status', $performance?->status) === 'confirmed')>Confirmed</option>
        </select>
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-location-name">Location name</label>
        <input
            id="performance-location-name"
            name="location_name"
            type="text"
            class="esb-portal__input"
            value="{{ old('location_name', $performance?->location_name ?? $performance?->venue) }}"
            required
        >
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-location-address">Location address</label>
        <textarea
            id="performance-location-address"
            name="location_address"
            rows="3"
            class="esb-portal__input esb-studio__band-textarea"
        >{{ old('location_address', $performance?->location_address) }}</textarea>
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-date">Date</label>
        <input
            id="performance-date"
            name="performance_date"
            type="date"
            class="esb-portal__input"
            value="{{ old('performance_date', $performance?->performance_date?->format('Y-m-d')) }}"
            required
        >
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-prep-time">Prep time</label>
        <input
            id="performance-prep-time"
            name="prep_time"
            type="time"
            class="esb-portal__input"
            value="{{ old('prep_time', $performance ? substr((string) $performance->prep_time, 0, 5) : null) }}"
        >
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-time">Performance time</label>
        <input
            id="performance-time"
            name="performance_time"
            type="time"
            class="esb-portal__input"
            value="{{ old('performance_time', $performance ? substr((string) $performance->performance_time, 0, 5) : null) }}"
        >
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-duration">Duration (minutes)</label>
        <input
            id="performance-duration"
            name="performance_duration_minutes"
            type="number"
            min="1"
            max="1440"
            class="esb-portal__input"
            value="{{ old('performance_duration_minutes', $performance?->performance_duration_minutes) }}"
        >
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-packup-time">Packup time</label>
        <input
            id="performance-packup-time"
            name="packup_time"
            type="time"
            class="esb-portal__input"
            value="{{ old('packup_time', $performance ? substr((string) $performance->packup_time, 0, 5) : null) }}"
        >
    </div>

    <div>
        <label class="esb-portal__label mb-2 block" for="performance-briefing-notes">Briefing notes</label>
        <textarea
            id="performance-briefing-notes"
            name="briefing_notes"
            rows="5"
            class="esb-portal__input esb-studio__band-textarea"
        >{{ old('briefing_notes', $performance?->briefing_notes ?? $performance?->notes) }}</textarea>
    </div>
</div>
