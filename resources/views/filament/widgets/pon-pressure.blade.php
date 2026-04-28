<x-filament-widgets::widget>
    @include('filament.widgets._table-styles')

    <x-filament::section heading="PON Bermasalah">
        <div class="fieldops-table-wrap">
            <table class="fieldops-table">
                <thead>
                    <tr>
                        <th>OLT</th>
                        <th>PON</th>
                        <th>ODC</th>
                        <th>ODP</th>
                        <th>Pelanggan</th>
                        <th>Kapasitas</th>
                        <th>Utilisasi</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fieldops-table__primary">
                                <a href="{{ $row['olt_url'] }}">{{ $row['olt'] }}</a>
                            </td>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['label'] }}</a>
                            </td>
                            <td>{{ $row['odc_count'] }}</td>
                            <td>{{ $row['odp_count'] }}</td>
                            <td>{{ $row['active_customers'] }}</td>
                            <td>{{ $row['capacity'] }}</td>
                            <td>{{ number_format($row['utilization'], 2) }}%</td>
                            <td>
                                <x-filament::badge :color="$row['color']">{{ $row['status'] }}</x-filament::badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="fieldops-table__empty" colspan="8">Tidak ada PON bermasalah.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
