<template x-if="modalOpen && activeCard">
    <div class="esb-studio__rsvp-overlay" @keydown.escape.window="closeRsvp()">
        <div class="esb-studio__rsvp-backdrop" @click="closeRsvp()"></div>
        <section
            class="esb-studio__rsvp-dialog esb-portal__panel"
            role="dialog"
            aria-modal="true"
            aria-labelledby="studio-rsvp-title"
        >
            <h2 id="studio-rsvp-title" class="esb-studio__card-title">RSVP</h2>
            <p class="esb-studio__card-body mt-2" x-text="activeCard.show_name + ' · ' + activeCard.date"></p>

            <template x-if="!hasMusicianLink">
                <p class="esb-portal__error mt-4" role="alert">
                    Your account is not linked to a musician profile yet. Ask a director to complete your roster link.
                </p>
            </template>

            <template x-if="hasMusicianLink">
                <form class="mt-4" method="POST" :action="activeCard.rsvp_url">
                    @csrf
                    <fieldset class="esb-studio__rsvp-options">
                        <legend class="sr-only">RSVP response</legend>
                        <label class="esb-studio__rsvp-option">
                            <input type="radio" name="response" value="yes" x-model="response">
                            <span>Yes</span>
                        </label>
                        <label class="esb-studio__rsvp-option">
                            <input type="radio" name="response" value="no" x-model="response">
                            <span>No</span>
                        </label>
                        <label class="esb-studio__rsvp-option">
                            <input type="radio" name="response" value="maybe" x-model="response">
                            <span>Maybe</span>
                        </label>
                    </fieldset>

                    <div class="mt-4" x-show="showNotesField()" x-cloak>
                        <label class="esb-portal__label mb-2 block" for="rsvp-notes">Notes</label>
                        <textarea
                            id="rsvp-notes"
                            name="notes"
                            rows="4"
                            class="esb-portal__input esb-studio__band-textarea"
                            placeholder="Optional, but encouraged if you cannot attend."
                            x-model="notes"
                        ></textarea>
                    </div>

                    <div class="esb-studio__band-form-actions mt-6">
                        <button type="submit" class="esb-portal__button esb-portal__button--primary">
                            Save RSVP
                        </button>
                        <button type="button" class="esb-portal__button esb-portal__button--secondary" @click="closeRsvp()">
                            Cancel
                        </button>
                    </div>
                </form>
            </template>

            <template x-if="!hasMusicianLink">
                <div class="esb-studio__band-form-actions mt-6">
                    <button type="button" class="esb-portal__button esb-portal__button--secondary" @click="closeRsvp()">
                        Close
                    </button>
                </div>
            </template>
        </section>
    </div>
</template>
