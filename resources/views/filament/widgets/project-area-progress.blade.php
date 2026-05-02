<x-filament-widgets::widget>
    @include('filament.widgets._table-styles')

    <x-filament::section heading="Progress Proyek / Area">
        <div class="fieldops-table-wrap">
            <table class="fieldops-table">
                <thead>
                    <tr>
                        <th>Proyek</th>
                        <th>Area</th>
                        <th>ODP</th>
                        <th>Kapasitas</th>
                        <th>Terpakai</th>
                        <th>Utilisasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="fieldops-table__primary">{{ $row['project'] }}</td>
                            <td>
                                <a href="{{ $row['url'] }}">{{ $row['area'] }}</a>
                            </td>
                            <td>{{ $row['odp_count'] }}</td>
                            <td>{{ $row['capacity'] }}</td>
                            <td>{{ $row['used'] }}</td>
                            <td>{{ number_format($row['utilization'], 2) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="fieldops-table__empty" colspan="6">Belum ada area.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
