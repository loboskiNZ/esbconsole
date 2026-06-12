<div class="space-y-6">
    <div>
        <x-input-label for="name" value="Festival name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $festival->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="country" value="Country (optional)" />
            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $festival->country ?? '')" />
        </div>
        <div>
            <x-input-label for="city" value="City (optional)" />
            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $festival->city ?? '')" />
        </div>
    </div>

    <div>
        <x-input-label for="website" value="Website (optional)" />
        <x-text-input id="website" name="website" type="url" class="mt-1 block w-full" :value="old('website', $festival->website ?? '')" />
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="contact_name" value="Primary contact name (optional)" />
            <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" :value="old('contact_name', $festival->contact_name ?? '')" />
        </div>
        <div>
            <x-input-label for="contact_phone" value="Primary contact phone (optional)" />
            <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full" :value="old('contact_phone', $festival->contact_phone ?? '')" />
        </div>
    </div>

    <div>
        <x-input-label for="contact_email" value="Primary contact email (optional)" />
        <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email', $festival->contact_email ?? '')" />
        <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="application_url" value="Application URL (optional)" />
        <x-text-input id="application_url" name="application_url" type="url" class="mt-1 block w-full" :value="old('application_url', $festival->application_url ?? '')" />
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="application_deadline" value="Application deadline (optional)" />
            <x-text-input
                id="application_deadline"
                name="application_deadline"
                type="date"
                class="mt-1 block w-full"
                :value="old('application_deadline', isset($festival) && $festival->application_deadline ? $festival->application_deadline->format('Y-m-d') : '')"
            />
        </div>
        <div>
            <x-input-label for="application_status" value="Application status" />
            <select id="application_status" name="application_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                @foreach ($applicationStatuses as $status)
                    <option
                        value="{{ $status->value }}"
                        @selected(old('application_status', isset($festival) ? $festival->application_status->value : App\Enums\FestivalApplicationStatus::NotApplied->value) === $status->value)
                    >
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('application_status')" class="mt-2" />
        </div>
    </div>

    <div>
        <x-input-label for="festival_date_notes" value="Festival date notes (optional)" />
        <textarea id="festival_date_notes" name="festival_date_notes" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('festival_date_notes', $festival->festival_date_notes ?? '') }}</textarea>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="facebook_tag" value="Facebook tag/page (optional)" />
            <x-text-input id="facebook_tag" name="facebook_tag" type="text" class="mt-1 block w-full" :value="old('facebook_tag', $festival->facebook_tag ?? '')" placeholder="@festival" />
        </div>
        <div>
            <x-input-label for="instagram_tag" value="Instagram tag (optional)" />
            <x-text-input id="instagram_tag" name="instagram_tag" type="text" class="mt-1 block w-full" :value="old('instagram_tag', $festival->instagram_tag ?? '')" placeholder="@festival" />
        </div>
        <div>
            <x-input-label for="tiktok_tag" value="TikTok tag (optional)" />
            <x-text-input id="tiktok_tag" name="tiktok_tag" type="text" class="mt-1 block w-full" :value="old('tiktok_tag', $festival->tiktok_tag ?? '')" placeholder="@festival" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Notes (optional)" />
        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $festival->notes ?? '') }}</textarea>
    </div>
</div>
