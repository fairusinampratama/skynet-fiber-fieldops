@php
    $mapId = 'asset-map-'.str()->uuid();
@endphp

<x-filament-panels::page>
    <div
        class="fieldops-asset-map"
        x-data="window.fieldopsAssetMap($wire, {
            mapId: @js($mapId),
            dataUrl: @js(route('asset-map.data')),
            filters: @js($this->filters()),
        })"
        x-init="init()"
    >
        <section class="fieldops-asset-map-filters">
            <label class="fieldops-asset-map-field">
                <span>Proyek</span>
                <select wire:model.live="projectId">
                    <option value="">Semua Proyek</option>
                    @foreach ($this->projectOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="fieldops-asset-map-field">
                <span>Area</span>
                <select wire:model.live="areaId">
                    <option value="">Semua Area</option>
                    @foreach ($this->areaOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="fieldops-asset-map-field">
                <span>Status</span>
                <select wire:model.live="status">
                    <option value="">Semua Status</option>
                    @foreach ($this->statusOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="fieldops-asset-map-field">
                <span>Mapping</span>
                <select wire:model.live="mappingState">
                    @foreach ($this->mappingStateOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </section>

        <section class="fieldops-asset-map-toolbar" wire:ignore>
            <div class="fieldops-asset-map-layer-controls" role="group" aria-label="Layer peta aset">
                <button type="button" class="is-active" data-fieldops-layer="olt">OLT</button>
                <button type="button" class="is-active" data-fieldops-layer="odc">ODC</button>
                <button type="button" class="is-active" data-fieldops-layer="odp">ODP</button>
            </div>

            <div class="fieldops-asset-map-counts" aria-live="polite">
                <span><strong x-text="counts.olt">0</strong> OLT</span>
                <span><strong x-text="counts.odc">0</strong> ODC</span>
                <span><strong x-text="counts.odp">0</strong> ODP</span>
            </div>
        </section>

        <section class="fieldops-asset-map-shell" wire:ignore>
            <div
                x-show="! isMapReady"
                class="fieldops-asset-map-loading"
            >
                Memuat peta aset...
            </div>

            <div id="{{ $mapId }}" class="fieldops-asset-map-canvas"></div>

            <div
                x-show="isMapReady && visibleAssetCount === 0"
                x-cloak
                class="fieldops-asset-map-empty"
            >
                Tidak ada aset berkoordinat valid untuk filter ini.
            </div>
        </section>
    </div>
</x-filament-panels::page>
