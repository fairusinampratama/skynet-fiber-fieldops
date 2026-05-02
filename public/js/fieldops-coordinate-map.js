window.fieldopsCoordinateMap = function ($wire, config) {
    return {
        ...config,
        map: null,
        marker: null,
        plannedMarker: null,
        $wire: $wire,
        isMapReady: false,
        hasSelectedCoordinate: false,
        selectedCoordinateText: '-',
        plannedCoordinateText: '-',
        distanceText: '-',
        isDistanceWarning: false,
        statusText: config.manualStatusText ?? 'Klik peta atau geser pin untuk memilih koordinat.',
        statusTone: 'default',
        syncTimer: null,
        hasTriedAutomaticLocation: false,

        init() {
            if (! window.L) {
                this.statusText = 'Gagal memuat peta. Leaflet belum tersedia.';
                this.statusTone = 'danger';
                return;
            }

            this.initializeMap();
        },

        initializeMap() {
            const mapElement = document.getElementById(this.mapId);

            if (! mapElement) {
                this.statusText = 'Gagal memuat peta. Elemen peta tidak ditemukan.';
                this.statusTone = 'danger';
                return;
            }

            if (mapElement.dataset.fieldopsMapInitializing === 'true' || mapElement.dataset.fieldopsMapReady === 'true') {
                this.isMapReady = true;
                return;
            }

            mapElement.dataset.fieldopsMapInitializing = 'true';

            const target = this.getCoordinate(this.targetLatitudeField, this.targetLongitudeField);
            const planned = this.getCoordinate(this.plannedLatitudeField, this.plannedLongitudeField);
            const center = target ?? planned ?? [-7.966620, 112.632632];
            const zoom = target || planned ? 16 : 13;

            this.map = L.map(mapElement, {
                scrollWheelZoom: false,
            }).setView(center, zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 20,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);

            if (! this.isReadOnly && this.allowManualSelection !== false) {
                this.map.on('click', (event) => this.setTargetCoordinate(event.latlng.lat, event.latlng.lng, 'Koordinat dipilih dari peta.'));
            }

            this.syncFromState({ shouldPan: false });
            this.isMapReady = true;
            mapElement.dataset.fieldopsMapInitializing = 'false';
            mapElement.dataset.fieldopsMapReady = 'true';

            requestAnimationFrame(() => this.map.invalidateSize());
            setTimeout(() => this.map.invalidateSize(), 300);
            this.syncTimer = setInterval(() => this.syncFromState({ shouldPan: false }), 1200);
            this.seedCoordinateAutomatically();
        },

        getState() {
            // In Livewire 3/Filament v3, form data is typically in the 'data' property
            return this.$wire?.get('data') || {};
        },

        normalizeCoordinate(value, decimals = 8) {
            const number = Number(value);

            if (! Number.isFinite(number)) {
                return null;
            }

            return number.toFixed(decimals);
        },

        isValidCoordinate(latitude, longitude) {
            return Number.isFinite(latitude)
                && Number.isFinite(longitude)
                && latitude >= -90
                && latitude <= 90
                && longitude >= -180
                && longitude <= 180;
        },

        getCoordinate(latitudeField, longitudeField) {
            if (! latitudeField || ! longitudeField) {
                return null;
            }

            const state = this.getState();
            const latitude = parseFloat(state[latitudeField]);
            const longitude = parseFloat(state[longitudeField]);

            if (this.isValidCoordinate(latitude, longitude)) {
                return [latitude, longitude];
            }

            return null;
        },

        formatCoordinate(coordinate) {
            if (! coordinate) {
                return '-';
            }

            return `${this.normalizeCoordinate(coordinate[0])}, ${this.normalizeCoordinate(coordinate[1])}`;
        },

        syncFromState({ shouldPan = false } = {}) {
            if (! this.map) {
                return;
            }

            const target = this.getCoordinate(this.targetLatitudeField, this.targetLongitudeField);
            const planned = this.getCoordinate(this.plannedLatitudeField, this.plannedLongitudeField);

            this.selectedCoordinateText = this.formatCoordinate(target);
            this.plannedCoordinateText = this.formatCoordinate(planned);
            this.hasSelectedCoordinate = Boolean(target);
            this.updateDistance(target, planned);

            this.placePlannedMarker(planned);

            if (target) {
                this.placeMarker(target[0], target[1], shouldPan);
            }
        },

        updateDistance(target, planned) {
            this.distanceText = '-';
            this.isDistanceWarning = false;

            if (! this.showDistance || ! target || ! planned) {
                return;
            }

            const distance = this.calculateDistanceInMeters(planned[0], planned[1], target[0], target[1]);
            this.distanceText = this.formatDistance(distance);
            this.isDistanceWarning = distance > 100;

            if (this.isDistanceWarning && this.statusTone !== 'danger') {
                this.statusText = 'Lokasi laporan jauh dari titik tugas. Pastikan pin sudah benar sebelum dikirim.';
                this.statusTone = 'warning';
            }
        },

        calculateDistanceInMeters(latitudeA, longitudeA, latitudeB, longitudeB) {
            const earthRadius = 6371000;
            const toRadians = (degrees) => degrees * Math.PI / 180;
            const latitudeDelta = toRadians(latitudeB - latitudeA);
            const longitudeDelta = toRadians(longitudeB - longitudeA);
            const startLatitude = toRadians(latitudeA);
            const endLatitude = toRadians(latitudeB);
            const haversine = Math.sin(latitudeDelta / 2) ** 2
                + Math.cos(startLatitude) * Math.cos(endLatitude) * Math.sin(longitudeDelta / 2) ** 2;

            return earthRadius * 2 * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
        },

        formatDistance(distance) {
            if (distance < 1000) {
                return `${Math.round(distance)} m`;
            }

            return `${(distance / 1000).toFixed(2)} km`;
        },

        seedCoordinateAutomatically() {
            if (this.isReadOnly || this.hasTriedAutomaticLocation) {
                return;
            }

            this.hasTriedAutomaticLocation = true;

            const target = this.getCoordinate(this.targetLatitudeField, this.targetLongitudeField);

            if (target) {
                return;
            }

            const planned = this.getCoordinate(this.plannedLatitudeField, this.plannedLongitudeField);

            if (planned && this.showUsePlanButton) {
                this.setTargetCoordinate(planned[0], planned[1], 'Koordinat awal memakai titik tugas.');
            }

            if (! this.autoLocate) {
                if (! planned) {
                    this.statusText = this.manualStatusText;
                }

                return;
            }

            setTimeout(() => this.useCurrentLocation({ isAutomatic: true }), planned ? 700 : 250);
        },

        setTargetCoordinate(latitude, longitude, message = 'Koordinat dipilih.') {
            const normalizedLatitude = this.normalizeCoordinate(latitude);
            const normalizedLongitude = this.normalizeCoordinate(longitude);

            if (! normalizedLatitude || ! normalizedLongitude) {
                this.statusText = 'Koordinat tidak valid.';
                this.statusTone = 'danger';
                return;
            }

            if (this.$wire) {
                this.$wire.set(`data.${this.targetLatitudeField}`, normalizedLatitude);
                this.$wire.set(`data.${this.targetLongitudeField}`, normalizedLongitude);
            }

            this.placeMarker(Number(normalizedLatitude), Number(normalizedLongitude), true);
            this.selectedCoordinateText = `${normalizedLatitude}, ${normalizedLongitude}`;
            this.hasSelectedCoordinate = true;
            this.statusText = `${message} ${normalizedLatitude}, ${normalizedLongitude}`;
            this.statusTone = 'default';
            this.updateDistance(
                [Number(normalizedLatitude), Number(normalizedLongitude)],
                this.getCoordinate(this.plannedLatitudeField, this.plannedLongitudeField),
            );
        },

        placeMarker(latitude, longitude, shouldPan = true) {
            if (! this.map) {
                return;
            }

            const coordinate = [latitude, longitude];

            if (! this.marker) {
                this.marker = L.marker(coordinate, {
                    draggable: ! this.isReadOnly && this.allowManualSelection !== false,
                    icon: L.divIcon({
                        className: 'fieldops-map-target-marker',
                        html: '<span></span>',
                        iconAnchor: [12, 28],
                        iconSize: [24, 28],
                        popupAnchor: [0, -28],
                    }),
                })
                    .addTo(this.map)
                    .bindTooltip(this.targetLabel ?? 'Koordinat terpilih');

                if (! this.isReadOnly && this.allowManualSelection !== false) {
                    this.marker.on('dragend', (event) => {
                        const position = event.target.getLatLng();
                        this.setTargetCoordinate(position.lat, position.lng, 'Marker digeser ke');
                    });
                }
            }

            this.marker.setLatLng(coordinate);

            if (shouldPan) {
                this.map.panTo(coordinate);
            }
        },

        placePlannedMarker(coordinate) {
            if (! this.map || ! coordinate) {
                return;
            }

            if (! this.plannedMarker) {
                this.plannedMarker = L.circleMarker(coordinate, {
                    radius: 7,
                    color: '#2563eb',
                    fillColor: '#60a5fa',
                    fillOpacity: 0.85,
                    weight: 2,
                }).addTo(this.map).bindTooltip(this.plannedLabel ?? 'Titik Tugas dari Admin');
            }

            this.plannedMarker.setLatLng(coordinate);
        },

        fitToSelected() {
            const target = this.getCoordinate(this.targetLatitudeField, this.targetLongitudeField);

            if (! target) {
                this.statusText = 'Koordinat terpilih belum tersedia.';
                this.statusTone = 'danger';
                return;
            }

            this.map.setView(target, Math.max(this.map.getZoom(), 17));
            this.statusText = 'Peta diarahkan ke koordinat terpilih.';
            this.statusTone = 'default';
        },

        usePlannedCoordinate() {
            const planned = this.getCoordinate(this.plannedLatitudeField, this.plannedLongitudeField);

            if (! planned) {
                this.statusText = 'Titik tugas belum tersedia.';
                this.statusTone = 'danger';
                return;
            }

            this.setTargetCoordinate(planned[0], planned[1], 'Lokasi laporan memakai titik tugas.');
        },

        useCurrentLocation({ isAutomatic = false } = {}) {
            const canSelectManually = this.allowManualSelection !== false;

            if (! navigator.geolocation) {
                this.statusText = isAutomatic
                    ? (canSelectManually
                        ? 'GPS browser tidak tersedia. Geser pin atau klik peta untuk mengatur lokasi.'
                        : 'GPS browser tidak tersedia. Lokasi laporan harus diambil dari perangkat teknisi.')
                    : 'Browser tidak mendukung lokasi perangkat.';
                this.statusTone = isAutomatic ? 'default' : 'danger';
                return;
            }

            this.statusText = isAutomatic ? 'Mencoba mengisi lokasi aktual dari GPS perangkat...' : 'Mengambil lokasi perangkat...';
            this.statusTone = 'default';

            navigator.geolocation.getCurrentPosition(
                (position) => this.setTargetCoordinate(position.coords.latitude, position.coords.longitude, 'Lokasi berhasil diambil.'),
                (error) => {
                    let errorMessage = isAutomatic
                        ? (canSelectManually
                            ? 'GPS belum aktif. Geser pin atau klik peta untuk mengisi lokasi.'
                            : 'GPS belum aktif. Aktifkan izin lokasi perangkat untuk mengisi lokasi laporan.')
                        : 'Gagal mengambil lokasi perangkat.';
                    if (error.code === error.PERMISSION_DENIED) errorMessage = isAutomatic
                        ? (canSelectManually ? 'Izin GPS ditolak. Geser pin atau klik peta untuk mengisi lokasi.' : 'Izin GPS ditolak. Aktifkan izin lokasi perangkat untuk mengisi lokasi laporan.')
                        : 'Izin lokasi ditolak.';
                    else if (error.code === error.POSITION_UNAVAILABLE) errorMessage = isAutomatic
                        ? (canSelectManually ? 'GPS perangkat tidak tersedia. Geser pin atau klik peta untuk mengisi lokasi.' : 'GPS perangkat tidak tersedia. Lokasi laporan harus diambil dari perangkat teknisi.')
                        : 'Informasi lokasi tidak tersedia.';
                    else if (error.code === error.TIMEOUT) errorMessage = isAutomatic
                        ? (canSelectManually ? 'GPS terlalu lama merespons. Geser pin atau klik peta untuk mengisi lokasi.' : 'GPS terlalu lama merespons. Coba ambil lokasi GPS lagi.')
                        : 'Waktu pengambilan lokasi habis.';

                    this.statusText = errorMessage;
                    this.statusTone = isAutomatic ? 'default' : 'danger';
                    console.error('Geolocation error:', error);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 12000,
                    maximumAge: 30000,
                },
            );
        },
    };
};
