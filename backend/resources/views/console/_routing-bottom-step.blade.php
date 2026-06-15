<span class="vx32-routing-bottom__step-number">{{ $step['number'] }}</span>
<span class="vx32-routing-bottom__step-label">{{ $step['label'] }}</span>
<p class="vx32-routing-bottom__step-desc">{{ $step['description'] }}</p>
<span @class([
    'vx32-routing-bottom__step-status',
    'vx32-routing-bottom__step-status--available' => $step['state'] === 'available',
    'vx32-routing-bottom__step-status--not-available' => $step['state'] === 'not_available',
    'vx32-routing-bottom__step-status--coming-later' => $step['state'] === 'coming_later',
])>{{ $step['status_label'] }}</span>
