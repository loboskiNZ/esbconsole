@if ($deploymentPlan === null)
    <p class="vx32-effects-workspace__placeholder-text">Select a package to preview deployment planning.</p>
@else
    <section class="vx32-effects-workspace__detail-section">
        <dl class="vx32-effects-deployment-plan__meta">
            <div class="vx32-effects-deployment-plan__meta-row">
                <dt>Package</dt>
                <dd>{{ $deploymentPlan['package_name'] }}</dd>
            </div>
            <div class="vx32-effects-deployment-plan__meta-row">
                <dt>Type</dt>
                <dd>{{ $deploymentPlan['package_type_label'] }}</dd>
            </div>
            <div class="vx32-effects-deployment-plan__meta-row">
                <dt>FX slots used</dt>
                <dd>{{ $deploymentPlan['fx_slots_used'] }}</dd>
            </div>
        </dl>
    </section>

    <section class="vx32-effects-workspace__detail-section">
        <h3 class="vx32-effects-workspace__detail-heading">FX Slot Plan</h3>
        <div class="vx32-effects-deployment-plan__table-wrap">
            <table class="vx32-effects-deployment-plan__table">
                <thead>
                    <tr>
                        <th scope="col">Slot</th>
                        <th scope="col">Effect</th>
                        <th scope="col">Code</th>
                        <th scope="col">Alg</th>
                        <th scope="col">Source</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deploymentPlan['slot_rows'] as $row)
                        <tr class="vx32-effects-deployment-plan__row--{{ $row['status_class'] }}">
                            <td>{{ $row['slot_label'] }}</td>
                            <td>{{ $row['effect_name'] }}</td>
                            <td>{{ $row['effect_code'] }}</td>
                            <td>{{ $row['algorithm_id'] }}</td>
                            <td>{{ $row['package_source'] }}</td>
                            <td>
                                <span class="vx32-effects-deployment-plan__status vx32-effects-deployment-plan__status--{{ $row['status_class'] }}">
                                    {{ $row['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($deploymentPlan['unallocated_effects'] !== [])
        <section class="vx32-effects-workspace__detail-section">
            <h3 class="vx32-effects-workspace__detail-heading">Unallocated Effects</h3>
            <ul class="vx32-effects-deployment-plan__list">
                @foreach ($deploymentPlan['unallocated_effects'] as $effect)
                    <li>{{ $effect['effect_name'] }} ({{ $effect['effect_code'] }})</li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($deploymentPlan['permanent_reservations'] !== [])
        <section class="vx32-effects-workspace__detail-section">
            <h3 class="vx32-effects-workspace__detail-heading">Permanent Slot Reservations</h3>
            <ul class="vx32-effects-deployment-plan__list">
                @foreach ($deploymentPlan['permanent_reservations'] as $reservation)
                    <li>
                        {{ $reservation['slot_label'] }} reserved by {{ $reservation['package_name'] }}
                        ({{ $reservation['effect_name'] }})
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($deploymentPlan['conflicts'] !== [])
        <section class="vx32-effects-workspace__detail-section">
            <h3 class="vx32-effects-workspace__detail-heading">Conflicts</h3>
            <ul class="vx32-effects-deployment-plan__list vx32-effects-deployment-plan__list--conflicts">
                @foreach ($deploymentPlan['conflicts'] as $conflict)
                    <li>{{ $conflict }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="vx32-effects-workspace__detail-section">
        <h3 class="vx32-effects-workspace__detail-heading">Status</h3>
        <p class="vx32-effects-deployment-plan__overall-status vx32-effects-deployment-plan__overall-status--{{ $deploymentPlan['status']->value }}">
            {{ $deploymentPlan['status_label'] }}
        </p>
    </section>
@endif
