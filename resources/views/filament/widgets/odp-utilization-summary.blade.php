<x-filament-widgets::widget>
    @include('filament.widgets._table-styles')

    <x-filament::section heading="Dashboard Utilisasi ODP">
        <div class="fieldops-table-wrap">
            <table class="fieldops-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Jumlah</th>
                        <th>Ambang</th>
                        <th>Keterangan</th>
                        <th>Prioritas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="fieldops-table__primary">{{ $row['category'] }}</td>
                            <td>{{ $row['count'] }}</td>
                            <td>{{ $row['threshold'] }}</td>
                            <td>{{ $row['description'] }}</td>
                            <td>
                                <x-filament::badge :color="$row['color']">{{ $row['priority'] }}</x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
