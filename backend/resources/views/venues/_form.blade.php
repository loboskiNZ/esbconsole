<div class="space-y-6">
    <div>
        <x-input-label for="name" value="Venue name" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $venue->name ?? '')" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="country" value="Country (optional)" />
            <x-text-input id="country" name="country" type="text" class="mt-1 block w-full" :value="old('country', $venue->country ?? '')" />
        </div>
        <div>
            <x-input-label for="city" value="City (optional)" />
            <x-text-input id="city" name="city" type="text" class="mt-1 block w-full" :value="old('city', $venue->city ?? '')" />
        </div>
    </div>

    <div>
        <x-input-label for="address" value="Address (optional)" />
        <textarea id="address" name="address" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('address', $venue->address ?? '') }}</textarea>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <x-input-label for="contact_name" value="Venue contact name (optional)" />
            <x-text-input id="contact_name" name="contact_name" type="text" class="mt-1 block w-full" :value="old('contact_name', $venue->contact_name ?? '')" />
        </div>
        <div>
            <x-input-label for="contact_phone" value="Venue contact phone (optional)" />
            <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full" :value="old('contact_phone', $venue->contact_phone ?? '')" />
        </div>
    </div>

    <div>
        <x-input-label for="contact_email" value="Venue contact email (optional)" />
        <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email', $venue->contact_email ?? '')" />
        <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <x-input-label for="facebook_tag" value="Facebook tag/page (optional)" />
            <x-text-input id="facebook_tag" name="facebook_tag" type="text" class="mt-1 block w-full" :value="old('facebook_tag', $venue->facebook_tag ?? '')" placeholder="@venue" />
        </div>
        <div>
            <x-input-label for="instagram_tag" value="Instagram tag (optional)" />
            <x-text-input id="instagram_tag" name="instagram_tag" type="text" class="mt-1 block w-full" :value="old('instagram_tag', $venue->instagram_tag ?? '')" placeholder="@venue" />
        </div>
        <div>
            <x-input-label for="tiktok_tag" value="TikTok tag (optional)" />
            <x-text-input id="tiktok_tag" name="tiktok_tag" type="text" class="mt-1 block w-full" :value="old('tiktok_tag', $venue->tiktok_tag ?? '')" placeholder="@venue" />
        </div>
    </div>

    <div>
        <x-input-label for="notes" value="Notes (optional)" />
        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('notes', $venue->notes ?? '') }}</textarea>
    </div>
</div>
