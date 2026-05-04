window.fieldopsAssetMap = function ($wire, config) {
    return {
        mapId: config.mapId,
        dataUrl: config.dataUrl,
        filters: config.filters ?? {},
        payload: { assets: [], center: [-7.966620, 112.632632] },
        map: null,
        pointRenderer: null,
        markers: new Map(),
        isMapReady: false,
        visibleAssetCount: 0,
        counts: { olt: 0, odc: 0, odp: 0 },
        layers: { olt: true, odc: true, odp: true },
        isLoading: false,

        init() {
            if (! window.L) {
                return;
            }

            this.initializeMap();
            this.bindLayerControls();
            this.fetchPayload(this.filters);

            if (window.Livewire) {
                window.Livewire.on('asset-map-filters-updated', (event) => {
                    this.fetchPayload(event.filters ?? event[0]?.filters ?? event[0] ?? {});
                });
            }
        },

        initializeMap() {
            const mapElement = document.getElementById(this.mapId);

            if (! mapElement) {
                return;
            }

            this.pointRenderer = L.canvas({
                padding: 0.35,
            });

            this.map = L.map(mapElement, {
                preferCanvas: true,
                scrollWheelZoom: true,
            }).setView([-7.966620, 112.632632], 13);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 20,
                attribution: '&copy; OpenStreetMap',
            }).addTo(this.map);

            this.isMapReady = true;

            requestAnimationFrame(() => this.map.invalidateSize());
            setTimeout(() => this.map.invalidateSize(), 300);
        },

        fetchPayload(filters = {}) {
            if (! this.dataUrl) {
                return;
            }

            this.isLoading = true;
            this.filters = filters;

            const url = new URL(this.dataUrl, window.location.origin);

            for (const [key, value] of Object.entries(filters)) {
                if (value !== null && value !== undefined && value !== '') {
                    url.searchParams.set(key, value);
                }
            }

            fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => {
                    if (! response.ok) {
                        throw new Error(`Map data request failed with ${response.status}`);
                    }

                    return response.json();
                })
                .then((payload) => this.renderPayload(payload))
                .catch(() => this.renderPayload({ assets: [], center: [-7.966620, 112.632632] }))
                .finally(() => {
                    this.isLoading = false;
                });
        },

        bindLayerControls() {
            document
                .querySelectorAll('[data-fieldops-layer]')
                .forEach((button) => {
                    button.addEventListener('click', () => {
                        const layer = button.dataset.fieldopsLayer;

                        this.layers[layer] = ! this.layers[layer];
                        button.classList.toggle('is-active', this.layers[layer]);

                        this.renderPayload(this.payload, { shouldFitBounds: false });
                    });
                });
        },

        renderPayload(payload, { shouldFitBounds = true } = {}) {
            this.payload = payload ?? { assets: [], center: [-7.966620, 112.632632] };

            if (! this.map) {
                return;
            }

            this.clearMap();
            this.counts = { olt: 0, odc: 0, odp: 0 };

            const bounds = [];

            for (const asset of this.payload.assets ?? []) {
                if (! this.layers[asset.type]) {
                    continue;
                }

                const marker = L.circleMarker([asset.lat, asset.lng], {
                    ...this.styleFor(asset.type),
                    renderer: this.pointRenderer,
                }).addTo(this.map);

                marker.on('click', () => {
                    marker.bindPopup(this.popupFor(asset), {
                        maxWidth: 320,
                    }).openPopup();
                });

                this.markers.set(this.assetKey(asset.type, asset.id), marker);
                this.counts[asset.type] = (this.counts[asset.type] ?? 0) + 1;
                bounds.push([asset.lat, asset.lng]);
            }

            this.visibleAssetCount = bounds.length;

            if (shouldFitBounds) {
                this.fitBounds(bounds);
            }
        },

        clearMap() {
            for (const marker of this.markers.values()) {
                marker.remove();
            }

            this.markers.clear();
        },

        fitBounds(bounds) {
            if (bounds.length === 0) {
                this.map.setView(this.payload.center ?? [-7.966620, 112.632632], 13);
                return;
            }

            if (bounds.length === 1) {
                this.map.setView(bounds[0], 17);
                return;
            }

            this.map.fitBounds(bounds, {
                padding: [34, 34],
                maxZoom: 17,
            });
        },

        styleFor(type) {
            const styles = {
                olt: { radius: 7, color: '#0369a1', fillColor: '#0284c7', weight: 2 },
                odc: { radius: 5, color: '#15803d', fillColor: '#16a34a', weight: 1.5 },
                odp: { radius: 3.5, color: '#b45309', fillColor: '#d97706', weight: 1 },
            };

            return {
                ...(styles[type] ?? styles.odp),
                fillOpacity: 0.82,
                opacity: 0.92,
            };
        },

        popupFor(asset) {
            const rows = [
                ['Proyek', asset.project],
                ['Area', asset.area],
                ['Status', this.statusLabel(asset.status)],
            ];

            if (asset.type === 'olt') {
                rows.push(['Nama', asset.metadata?.name], ['Lokasi', asset.metadata?.location]);
            }

            if (asset.type === 'odc') {
                rows.push(
                    ['OLT', asset.metadata?.olt],
                    ['PON', asset.metadata?.pon ? `PON ${asset.metadata.pon}` : null],
                    ['Mapping', asset.metadata?.mapping],
                );
            }

            if (asset.type === 'odp') {
                rows.push(
                    ['ODC', asset.metadata?.odc],
                    ['OLT/PON', asset.metadata?.olt ? `${asset.metadata.olt}${asset.metadata.pon ? ` / PON ${asset.metadata.pon}` : ''}` : null],
                    ['Core', asset.metadata?.core_color],
                    ['Utilisasi', `${asset.metadata?.used ?? 0}/${asset.metadata?.capacity ?? 0} port (${asset.metadata?.utilization ?? 0}%)`],
                    ['Mapping', asset.metadata?.mapping],
                );
            }

            const rowsHtml = rows
                .filter(([, value]) => value !== null && value !== undefined && value !== '')
                .map(([label, value]) => `<div><dt>${this.escapeHtml(label)}</dt><dd>${this.escapeHtml(value)}</dd></div>`)
                .join('');

            return `
                <article class="fieldops-asset-map-popup">
                    <p class="fieldops-asset-map-popup-type">${this.escapeHtml(asset.type.toUpperCase())}</p>
                    <h3>${this.escapeHtml(asset.label)}</h3>
                    <dl>${rowsHtml}</dl>
                    <p class="fieldops-asset-map-popup-coordinate">${Number(asset.lat).toFixed(8)}, ${Number(asset.lng).toFixed(8)}</p>
                    <a href="${this.escapeAttribute(asset.url)}">Buka/Edit</a>
                </article>
            `;
        },

        statusLabel(status) {
            return {
                active: 'Aktif',
                unmapped: 'Belum Mapping',
                inactive: 'Tidak Aktif',
                maintenance: 'Maintenance',
            }[status] ?? status ?? '-';
        },

        assetKey(type, id) {
            return `${type}:${id}`;
        },

        escapeHtml(value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');
        },

        escapeAttribute(value) {
            return this.escapeHtml(value ?? '#');
        },
    };
};
