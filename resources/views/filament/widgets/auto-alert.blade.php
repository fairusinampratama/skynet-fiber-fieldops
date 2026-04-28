<x-filament-widgets::widget>
    @include('filament.widgets._table-styles')

    <x-filament::section heading="Alert Operasional">
        <div class="fieldops-table-wrap">
            <table class="fieldops-table">
                <thead>
                    <tr>
                        <th>Level</th>
                        <th>Jenis Alert</th>
                        <th>Objek</th>
                        <th>Jumlah/Nilai</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <x-filament::badge :color="$row['color']">{{ $row['level'] }}</x-filament::badge>
                            </td>
                            <td class="fieldops-table__primary">{{ $row['type'] }}</td>
                            <td>{{ $row['object'] }}</td>
                            <td>{{ $row['value'] }}</td>
                            <td>
                                <a href="{{ $row['url'] }}">
                                    {{ $row['action'] }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="fieldops-table__empty" colspan="5">Tidak ada alert aktif.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
