<x-filament-widgets::widget>
    @include('filament.widgets._table-styles')

    <x-filament::section heading="ODP Kritis">
        <div class="fieldops-table-wrap">
            <table class="fieldops-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>OLT</th>
                        <th>PON</th>
                        <th>ODC</th>
                        <th>ODP</th>
                        <th>Area</th>
                        <th>Kapasitas</th>
                        <th>Utilisasi</th>
                        <th>Rekomendasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                @if ($row['olt_url'])
                                    <a href="{{ $row['olt_url'] }}">{{ $row['olt'] }}</a>
                                @else
                                    <span>{{ $row['olt'] }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['pon_url'])
                                    <a href="{{ $row['pon_url'] }}">PON {{ $row['pon'] }}</a>
                                @else
                                    <span>{{ $row['pon'] ? 'PON ' . $row['pon'] : '-' }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['odc_url'])
                                    <a href="{{ $row['odc_url'] }}">{{ $row['odc'] }}</a>
                                @else
                                    <span>{{ $row['odc'] }}</span>
                                @endif
                            </td>
                            <td class="fieldops-table__primary">
                                <a href="{{ $row['url'] }}">{{ $row['box_id'] }}</a>
                            </td>
                            <td>{{ $row['area'] }}</td>
                            <td>{{ $row['used'] + $row['reserved'] }}/{{ $row['capacity'] }} ({{ $row['available'] }} kosong)</td>
                            <td>
                                <x-filament::badge :color="$row['color']">{{ $row['status'] }} - {{ number_format($row['utilization'], 2) }}%</x-filament::badge>
                            </td>
                            <td>{{ $row['recommendation'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="fieldops-table__empty" colspan="9">Tidak ada ODP kritis.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
