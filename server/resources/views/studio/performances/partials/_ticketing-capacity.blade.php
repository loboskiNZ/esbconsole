<dl class="esb-studio__ticketing-capacity mt-4">
    <div>
        <dt>Venue capacity</dt>
        <dd>{{ $capacity['capacity'] ?? '—' }}</dd>
    </div>
    <div>
        <dt>Paid tickets</dt>
        <dd>{{ $capacity['paid_tickets'] }}</dd>
    </div>
    <div>
        <dt>Manual admissions</dt>
        <dd>{{ $capacity['manual_admissions'] }}</dd>
    </div>
    <div>
        <dt>Guest / complimentary</dt>
        <dd>{{ $capacity['guest_quantity'] }}</dd>
    </div>
    <div>
        <dt>Promo</dt>
        <dd>{{ $capacity['promo_claimed'] }}</dd>
    </div>
    <div>
        <dt>Total allocated</dt>
        <dd>{{ $capacity['seats_held'] }}</dd>
    </div>
    <div>
        <dt>Checked in</dt>
        <dd>{{ $capacity['checked_in'] }}</dd>
    </div>
    <div>
        <dt>Not yet arrived</dt>
        <dd>{{ $capacity['not_yet_arrived'] }}</dd>
    </div>
    <div>
        <dt>Remaining capacity</dt>
        <dd>{{ $capacity['remaining'] ?? '—' }}</dd>
    </div>
</dl>
<p class="esb-studio__field-hint mt-3">Remaining capacity is seats still available to allocate. Checked in is people already at the door. Not yet arrived still hold their seats. Manual admissions are not payments.</p>
