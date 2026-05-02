@php
    $mapId = 'coordinate-map-'.str()->uuid();
@endphp

<div
    class="fieldops-coordinate-map"
    x-data="window.fieldopsCoordinateMap($wire, {
        mapId: @js($mapId),
        title: @js($title),
        targetLatitudeField: @js($targetLatitudeField),
        targetLongitudeField: @js($targetLongitudeField),
        plannedLatitudeField: @js($plannedLatitudeField),
        plannedLongitudeField: @js($plannedLongitudeField),
        showUsePlanButton: @js($showUsePlanButton ?? true),
        targetLabel: @js($targetLabel ?? 'Koordinat tersimpan'),
        plannedLabel: @js($plannedLabel ?? 'Titik Tugas dari Admin'),
        gpsButtonLabel: @js($gpsButtonLabel ?? 'Ambil Lokasi GPS'),
        manualStatusText: @js($manualStatusText ?? 'Klik peta atau geser pin untuk memilih koordinat.'),
        allowManualSelection: @js($allowManualSelection ?? true),
        autoLocate: @js($autoLocate ?? false),
        showDistance: @js($showDistance ?? false),
        isReadOnly: @js($isReadOnly ?? false),
    })"
    x-init="init()"
    wire:ignore
>
    <div class="fieldops-map-header">
        <div>
            <p class="fieldops-map-title" x-text="title"></p>
            <p class="fieldops-map-description">{{ $description }}</p>
        </div>

        <div class="fieldops-map-actions">
            <button
                x-show="showUsePlanButton && ! isReadOnly"
                x-cloak
                x-on:click.prevent="usePlannedCoordinate()"
                type="button"
                class="fieldops-map-button"
            >
                Pakai Titik Tugas
            </button>

            <button
                x-show="! isReadOnly"
                x-cloak
                x-on:click.prevent="useCurrentLocation()"
                type="button"
                class="fieldops-map-button fieldops-map-button-primary"
                x-text="gpsButtonLabel"
            >
            </button>
        </div>
    </div>

    <div class="fieldops-map-shell">
        <div
            x-show="! isMapReady"
            class="fieldops-map-loading"
        >
            Memuat peta...
        </div>

        <div id="{{ $mapId }}" class="fieldops-map-canvas"></div>
    </div>

    <div class="fieldops-map-coordinate-grid">
        <div>
            <p class="fieldops-map-coordinate-label" x-text="targetLabel"></p>
            <p class="fieldops-map-coordinate-value" x-text="selectedCoordinateText"></p>
        </div>

        <div x-show="plannedLatitudeField && plannedLongitudeField">
            <p class="fieldops-map-coordinate-label" x-text="plannedLabel"></p>
            <p class="fieldops-map-coordinate-value" x-text="plannedCoordinateText"></p>
        </div>

        <div x-show="showDistance && distanceText !== '-'">
            <p class="fieldops-map-coordinate-label">Jarak dari titik tugas</p>
            <p
                class="fieldops-map-coordinate-value"
                :class="isDistanceWarning ? 'fieldops-map-distance-warning' : ''"
                x-text="distanceText"
            ></p>
        </div>
    </div>

    <p
        class="fieldops-map-status"
        :class="{
            'fieldops-map-status-danger': statusTone === 'danger',
            'fieldops-map-status-warning': statusTone === 'warning',
        }"
        x-text="isReadOnly ? 'Titik tugas hanya bisa diubah oleh admin.' : statusText"
    ></p>
</div>
