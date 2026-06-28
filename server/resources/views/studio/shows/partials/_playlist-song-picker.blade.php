<div
    class="esb-studio__playlist-picker"
    x-data="studioPlaylistPicker({
        searchUrl: @js(route('studio.shows.playlist.songs.search', $show)),
        addUrl: @js(route('studio.shows.playlist.items.store', $show)),
    })"
>
    <button
        type="button"
        class="esb-portal__button esb-portal__button--secondary esb-studio__playlist-add-button"
        @click="openPicker()"
        aria-haspopup="dialog"
        :aria-expanded="open ? 'true' : 'false'"
        aria-controls="playlist-song-picker-dialog"
    >
        Add song
    </button>

    <p
        id="playlist-add-feedback"
        class="esb-portal__success mt-2"
        role="status"
        x-show="feedback"
        x-cloak
        x-text="feedback"
        :class="{ 'esb-portal__error': feedbackError }"
    ></p>

    <div
        x-show="open"
        x-cloak
        class="esb-studio__playlist-picker-backdrop"
        @click.self="closePicker()"
    >
        <div
            id="playlist-song-picker-dialog"
            class="esb-studio__playlist-picker-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="playlist-song-picker-title"
            @keydown.escape.prevent="closePicker()"
        >
            <div class="esb-studio__playlist-picker-head">
                <h3 id="playlist-song-picker-title" class="esb-studio__card-title">Add song to playlist</h3>
                <button
                    type="button"
                    class="esb-studio__playlist-picker-close"
                    @click="closePicker()"
                    aria-label="Close song picker"
                >
                    ×
                </button>
            </div>

            <label class="sr-only" for="playlist-song-picker-search">Search songs</label>
            <input
                id="playlist-song-picker-search"
                type="search"
                class="esb-portal__input esb-studio__playlist-picker-search"
                placeholder="Search by song code, name, or reference"
                autocomplete="off"
                spellcheck="false"
                x-model="query"
                x-ref="searchInput"
                @keydown="onSearchKeydown($event)"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="open && results.length > 0 ? 'true' : 'false'"
                aria-controls="playlist-song-picker-results"
                :aria-activedescendant="activeIndex >= 0 ? resultId(activeIndex) : null"
            >

            <p class="esb-studio__card-body mt-2" x-show="loading" x-cloak>Searching…</p>
            <p class="esb-studio__card-body mt-2" x-show="! loading && query.trim() !== '' && results.length === 0" x-cloak>
                No songs match that search.
            </p>

            <ul
                id="playlist-song-picker-results"
                class="esb-studio__playlist-picker-results mt-3"
                role="listbox"
                x-show="results.length > 0"
                x-cloak
            >
                <template x-for="(result, index) in results" :key="result.song_id">
                    <li
                        :id="resultId(index)"
                        role="option"
                        class="esb-studio__playlist-picker-result"
                        :class="{
                            'esb-studio__playlist-picker-result--active': index === activeIndex,
                            'esb-studio__playlist-picker-result--on-playlist': result.on_playlist,
                        }"
                        @mouseenter="activeIndex = index"
                        @mousedown.prevent="selectResult(result)"
                        @dblclick.prevent="selectResult(result)"
                    >
                        <span class="esb-studio__playlist-picker-result-code" x-text="result.song_code"></span>
                        <span class="esb-studio__playlist-picker-result-title" x-text="result.name"></span>
                        <span
                            class="esb-studio__playlist-picker-result-artist"
                            x-show="result.artist"
                            x-text="result.artist"
                        ></span>
                        <span
                            class="esb-studio__playlist-picker-result-note"
                            x-show="result.on_playlist"
                        >Already on playlist</span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>
