<section
    class="esb-portal__panel esb-studio__card esb-studio__director-tools"
    aria-labelledby="studio-director-tools-title"
>
    <h2 id="studio-director-tools-title" class="esb-studio__card-title">Director tools</h2>

    <ul class="esb-studio__director-tools-list mt-3">
        <li>
            <a href="{{ route('studio.shows.create') }}" class="esb-studio__director-tools-link">
                Add Show
            </a>
        </li>
        <li>
            <a href="{{ route('studio.performances.create') }}" class="esb-studio__director-tools-link">
                Add Performance
            </a>
        </li>
        <li>
            <a href="{{ route('studio.band.edit') }}" class="esb-studio__director-tools-link">
                Manage Band
            </a>
        </li>
        <li>
            <a href="{{ route('studio.users.index') }}" class="esb-studio__director-tools-link">
                Manage Users
            </a>
        </li>
    </ul>
</section>
