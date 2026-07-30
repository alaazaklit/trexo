import '../css/driver-simulator.css';

const app = {
    el: null,
    mapEl: null,
    map: null,
    infoWindow: null,
    routePolyline: null,
    markers: new Map(),
    drivers: [],
    rides: [],
    stats: {},
    selectedDriverId: null,
    selectedRideId: null,
    pollTimer: null,
    tickTimer: null,
    initialized: false,
    defaultCenter: { lat: 33.557, lng: 35.372 },
    defaultZoom: 12,
    endpoints: {},
};

const STATUS_STYLES = {
    offline: { label: 'Offline', className: 'driver-simulator-badge--danger' },
    online: { label: 'Online', className: 'driver-simulator-badge--accent' },
    available: { label: 'Available', className: 'driver-simulator-badge--success' },
    busy: { label: 'Busy', className: 'driver-simulator-badge--warning' },
    driving_to_pickup: { label: 'Driving to Pickup', className: 'driver-simulator-badge--warning' },
    waiting_passenger: { label: 'Waiting Passenger', className: 'driver-simulator-badge--warning' },
    trip_started: { label: 'Trip Started', className: 'driver-simulator-badge--success' },
    trip_finished: { label: 'Trip Finished', className: 'driver-simulator-badge--accent' },
    pending: { label: 'Pending', className: 'driver-simulator-badge--warning' },
    accepted: { label: 'Accepted', className: 'driver-simulator-badge--success' },
    rejected: { label: 'Rejected', className: 'driver-simulator-badge--danger' },
    finished: { label: 'Finished', className: 'driver-simulator-badge--accent' },
};

function boot() {
    app.el = document.getElementById('driver-simulator-app');

    if (!app.el || app.initialized) {
        return;
    }

    app.initialized = true;
    app.mapEl = document.getElementById('driver-simulator-map');
    app.endpoints = {
        state: app.el.dataset.stateUrl,
        tick: app.el.dataset.tickUrl,
        spawn: app.el.dataset.spawnUrl,
        drivers: app.el.dataset.driversUrl,
        rides: app.el.dataset.ridesUrl,
        googleMapsKey: app.el.dataset.googleMapsKey,
    };

    setupForms();
    setupFilters();
    setupButtons();
    setupMapFallback();
    loadGoogleMaps()
        .then(() => {
            initMap();
            refreshState(false);
            startPolling();
        })
        .catch(() => {
            renderStatus('Google Maps failed to load. Check GOOGLE_MAPS_KEY.', 'danger');
            refreshState(false);
            startPolling();
        });
}

function setupMapFallback() {
    if (!app.mapEl) {
        return;
    }

    app.mapEl.innerHTML = '<div class="driver-simulator-empty" style="margin: 1rem;">Loading Google Maps...</div>';
}

function setupForms() {
    document.getElementById('driver-form')?.addEventListener('submit', handleDriverSubmit);
    document.getElementById('spawn-form')?.addEventListener('submit', handleSpawnSubmit);
    document.getElementById('ride-form')?.addEventListener('submit', handleRideSubmit);
    document.getElementById('clear-driver-form')?.addEventListener('click', clearDriverForm);
    document.getElementById('refresh-state-button')?.addEventListener('click', () => refreshState(false));
    document.getElementById('tick-button')?.addEventListener('click', () => refreshTick());
}

function setupFilters() {
    ['driver-search', 'driver-status-filter', 'vehicle-filter', 'online-filter', 'busy-filter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('input', debounce(() => refreshState(false), 250));
        document.getElementById(id)?.addEventListener('change', debounce(() => refreshState(false), 250));
    });
}

function setupButtons() {
    document.getElementById('use-selected-driver-button')?.addEventListener('click', () => {
        const driver = app.drivers.find((candidate) => Number(candidate.id) === Number(app.selectedDriverId));
        if (!driver) {
            return;
        }

        fillDriverForm(driver);
    });
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
            ...(options.headers || {}),
        },
        ...options,
    });

    const contentType = response.headers.get('content-type') || '';
    const data = contentType.includes('application/json') ? await response.json() : await response.text();

    if (!response.ok) {
        const message = data && typeof data === 'object'
            ? data.message || data.error || 'Request failed.'
            : 'Request failed.';
        throw new Error(message);
    }

    return data;
}

function renderStatus(message, tone = 'neutral') {
    const el = document.getElementById('simulator-status');

    if (!el) {
        return;
    }

    el.textContent = message;
    el.dataset.tone = tone;
}

function renderStats(stats = {}) {
    const map = {
        drivers_online: 'stat-drivers-online',
        drivers_offline: 'stat-drivers-offline',
        busy_drivers: 'stat-busy-drivers',
        available_drivers: 'stat-available-drivers',
        trips_running: 'stat-trips-running',
        average_response_time: 'stat-average-response-time',
    };

    Object.entries(map).forEach(([key, id]) => {
        const el = document.getElementById(id);

        if (!el) {
            return;
        }

        const value = stats[key] ?? 0;
        el.textContent = key === 'average_response_time'
            ? `${Number(value || 0).toFixed(2)}s`
            : String(value);
    });
}

function badgeMarkup(status) {
    const config = STATUS_STYLES[status] ?? { label: status || 'Unknown', className: 'driver-simulator-badge--accent' };
    return `<span class="driver-simulator-badge ${config.className}">${escapeHtml(config.label)}</span>`;
}

function renderDrivers(drivers = []) {
    const list = document.getElementById('driver-list');

    if (!list) {
        return;
    }

    if (!drivers.length) {
        list.innerHTML = '<div class="driver-simulator-empty">No drivers match the current filters.</div>';
        syncMarkers([]);
        return;
    }

    list.innerHTML = drivers.map((driver) => {
        const selected = Number(driver.id) === Number(app.selectedDriverId) ? 'is-selected' : '';
        const status = driver.status || (driver.is_online ? 'online' : 'offline');
        return `
            <article class="driver-simulator-card ${selected}" data-driver-id="${driver.id}">
                <div class="driver-simulator-card__top">
                    <div>
                        <strong>${escapeHtml(driver.name || `Driver ${driver.id}`)}</strong>
                        <div class="driver-simulator-muted">#${driver.id} | ${escapeHtml(driver.vehicle_type || 'Unassigned')}</div>
                    </div>
                    ${badgeMarkup(status)}
                </div>
                <div class="driver-simulator-card__bottom">
                    <span class="driver-simulator-muted">${escapeHtml(driver.ride_response_mode || 'manual')} | ${Number(driver.speed_kmh || 0).toFixed(0)} km/h</span>
                    <span class="driver-simulator-muted">${driver.is_online ? 'Online' : 'Offline'}</span>
                </div>
                <div class="driver-simulator-muted">
                    ${driver.latitude && driver.longitude ? `${Number(driver.latitude).toFixed(5)}, ${Number(driver.longitude).toFixed(5)}` : 'No position yet'}
                </div>
            </article>
        `;
    }).join('');

    list.querySelectorAll('[data-driver-id]').forEach((card) => {
        card.addEventListener('click', () => {
            const id = Number(card.dataset.driverId);
            const driver = app.drivers.find((item) => Number(item.id) === id);
            if (driver) {
                selectDriver(driver);
            }
        });
    });

    syncMarkers(drivers);
}

function renderRides(rides = []) {
    const list = document.getElementById('ride-list');

    if (!list) {
        return;
    }

    if (!rides.length) {
        list.innerHTML = '<div class="driver-simulator-empty">No ride simulations yet.</div>';
        return;
    }

    list.innerHTML = rides.map((ride) => {
        const selected = Number(ride.id) === Number(app.selectedRideId) ? 'is-selected' : '';
        const driverName = ride.driver?.name || 'Unassigned';
        const matched = Array.isArray(ride.matched_driver_ids) ? ride.matched_driver_ids.join(', ') : '';
        const actionable = ['pending', 'matched'].includes(ride.status);

        return `
            <article class="driver-simulator-ride ${selected}" data-ride-id="${ride.id}">
                <div class="driver-simulator-ride__header">
                    <strong>${escapeHtml(ride.passenger_name || `Ride #${ride.id}`)}</strong>
                    ${badgeMarkup(ride.status || 'pending')}
                </div>
                <div class="driver-simulator-ride__body">
                    <div>Pickup: ${escapeHtml(formatCoordinate(ride.pickup_latitude, ride.pickup_longitude))}</div>
                    <div>Dropoff: ${escapeHtml(formatCoordinate(ride.dropoff_latitude, ride.dropoff_longitude))}</div>
                    <div>Driver: ${escapeHtml(driverName)}</div>
                    <div>Matched: ${escapeHtml(matched || 'None')}</div>
                </div>
                <div class="driver-simulator-ride__footer" style="margin-top: .75rem;">
                    <button class="driver-simulator-button driver-simulator-button--ghost" type="button" data-focus-ride="${ride.id}">Focus</button>
                    ${actionable ? `
                        <div class="driver-simulator-actions" style="margin: 0;">
                            <button class="driver-simulator-button driver-simulator-button--success" type="button" data-accept-ride="${ride.id}">Accept</button>
                            <button class="driver-simulator-button driver-simulator-button--danger" type="button" data-reject-ride="${ride.id}">Reject</button>
                        </div>
                    ` : ''}
                </div>
            </article>
        `;
    }).join('');

    list.querySelectorAll('[data-focus-ride]').forEach((button) => {
        button.addEventListener('click', () => focusRide(Number(button.dataset.focusRide)));
    });

    list.querySelectorAll('[data-accept-ride]').forEach((button) => {
        button.addEventListener('click', () => updateRideDecision(Number(button.dataset.acceptRide), 'accept'));
    });

    list.querySelectorAll('[data-reject-ride]').forEach((button) => {
        button.addEventListener('click', () => updateRideDecision(Number(button.dataset.rejectRide), 'reject'));
    });
}

function syncMarkers(drivers) {
    if (!app.map || !window.google?.maps) {
        return;
    }

    const activeIds = new Set();
    const bounds = new google.maps.LatLngBounds();

    drivers.forEach((driver) => {
        if (driver.latitude === null || driver.longitude === null || driver.latitude === undefined || driver.longitude === undefined) {
            return;
        }

        const position = new google.maps.LatLng(Number(driver.latitude), Number(driver.longitude));
        activeIds.add(Number(driver.id));

        let marker = app.markers.get(Number(driver.id));

        if (!marker) {
            marker = new google.maps.Marker({
                map: app.map,
                position,
                draggable: true,
                title: driver.name || `Driver ${driver.id}`,
                icon: markerIcon(driver.status || (driver.is_online ? 'online' : 'offline')),
            });

            marker.addListener('click', () => {
                const latest = app.drivers.find((candidate) => Number(candidate.id) === Number(driver.id)) || driver;
                selectDriver(latest);
                openDriverInfo(latest);
            });

            marker.addListener('dragend', async (event) => {
                try {
                    const latest = app.drivers.find((candidate) => Number(candidate.id) === Number(driver.id)) || driver;
                    const updated = await moveDriver(driver.id, {
                        latitude: event.latLng.lat(),
                        longitude: event.latLng.lng(),
                        heading: latest.heading,
                        speed_kmh: latest.speed_kmh,
                    });
                    mergeDriver(updated.driver);
                    app.stats = updated.stats || app.stats;
                    renderState();
                } catch (error) {
                    renderStatus(error.message, 'danger');
                    refreshState(false);
                }
            });

            app.markers.set(Number(driver.id), marker);
        } else {
            marker.setPosition(position);
            marker.setIcon(markerIcon(driver.status || (driver.is_online ? 'online' : 'offline')));
        }

        bounds.extend(position);
    });

    Array.from(app.markers.entries()).forEach(([id, marker]) => {
        if (!activeIds.has(id)) {
            marker.setMap(null);
            app.markers.delete(id);
        }
    });

    if (!bounds.isEmpty() && !app.map.__simBoundsApplied) {
        app.map.fitBounds(bounds);
        app.map.__simBoundsApplied = true;
    }
}

function markerIcon(status) {
    const colors = {
        offline: '#fb7185',
        online: '#67d1ff',
        available: '#2dd4bf',
        busy: '#fbbf24',
        driving_to_pickup: '#fbbf24',
        waiting_passenger: '#f59e0b',
        trip_started: '#34d399',
        trip_finished: '#8f7bff',
    };

    const fill = colors[status] || '#67d1ff';
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="44" height="44" viewBox="0 0 44 44">
            <path d="M22 3c6.6 0 12 5.4 12 12 0 8.9-12 26-12 26S10 23.9 10 15c0-6.6 5.4-12 12-12z" fill="${fill}" opacity="0.2"/>
            <path d="M22 6c5.5 0 10 4.5 10 10 0 7.7-10 22.5-10 22.5S12 23.7 12 16c0-5.5 4.5-10 10-10z" fill="${fill}"/>
            <circle cx="22" cy="16" r="4.5" fill="#06101d"/>
        </svg>
    `;

    return {
        url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
        scaledSize: new google.maps.Size(40, 40),
        anchor: new google.maps.Point(20, 34),
    };
}

function initMap() {
    if (!app.mapEl || !window.google?.maps) {
        return;
    }

    const center = {
        lat: Number(app.el.dataset.defaultCenterLat || app.defaultCenter.lat),
        lng: Number(app.el.dataset.defaultCenterLng || app.defaultCenter.lng),
    };

    app.map = new google.maps.Map(app.mapEl, {
        center,
        zoom: Number(app.el.dataset.defaultZoom || app.defaultZoom),
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: true,
        clickableIcons: false,
        styles: [
            { elementType: 'geometry', stylers: [{ color: '#0a1628' }] },
            { elementType: 'labels.text.stroke', stylers: [{ color: '#0a1628' }] },
            { elementType: 'labels.text.fill', stylers: [{ color: '#9ab0c8' }] },
            { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#7fa0c7' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#13263f' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#081423' }] },
        ],
    });

    app.infoWindow = new google.maps.InfoWindow();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatCoordinate(latitude, longitude) {
    if (latitude === null || latitude === undefined || longitude === null || longitude === undefined) {
        return 'Unknown';
    }

    return `${Number(latitude).toFixed(5)}, ${Number(longitude).toFixed(5)}`;
}

function selectDriver(driver) {
    app.selectedDriverId = Number(driver.id);
    fillDriverForm(driver);
    renderDrivers(app.drivers);
    openDriverInfo(driver);
}

function openDriverInfo(driver) {
    if (!app.map || !app.infoWindow) {
        return;
    }

    const content = `
        <div style="font-family: system-ui, sans-serif; min-width: 220px;">
            <strong>${escapeHtml(driver.name || `Driver ${driver.id}`)}</strong><br>
            <span>Status: ${escapeHtml(driver.status || 'offline')}</span><br>
            <span>Vehicle: ${escapeHtml(driver.vehicle_type || 'N/A')}</span><br>
            <span>Speed: ${Number(driver.speed_kmh || 0).toFixed(0)} km/h</span>
        </div>
    `;

    app.infoWindow.setContent(content);
    app.infoWindow.setPosition({ lat: Number(driver.latitude || 0), lng: Number(driver.longitude || 0) });
    app.infoWindow.open({ map: app.map });
}

function fillDriverForm(driver) {
    setField('driver-id', driver.id);
    setField('driver-name', driver.name || '');
    setField('driver-phone', driver.phone || '');
    setField('driver-email', driver.email || '');
    setField('driver-license-number', driver.license_number || '');
    setField('driver-vehicle-type', driver.vehicle_type || '');
    setField('driver-vehicle-id', driver.vehicle_id || '');
    setField('driver-rating', driver.rating ?? 5);
    setField('driver-speed-kmh', driver.speed_kmh ?? 40);
    setField('driver-status', driver.status || 'available');
    setField('driver-online', driver.is_online ? '1' : '0');
    setField('driver-response-mode', driver.ride_response_mode || 'manual');
    setField('driver-latitude', driver.latitude ?? '');
    setField('driver-longitude', driver.longitude ?? '');
    setField('driver-heading', driver.heading ?? '');
    setField('driver-notes', driver.simulation_notes || '');
}

function clearDriverForm() {
    const form = document.getElementById('driver-form');
    if (!form) {
        return;
    }

    form.reset();
    setField('driver-id', '');
    app.selectedDriverId = null;
    renderDrivers(app.drivers);
}

function setField(id, value) {
    const el = document.getElementById(id);
    if (el) {
        el.value = value ?? '';
    }
}

function selectedDriverPayload(form) {
    return {
        name: getValue(form, 'driver-name'),
        phone: getValue(form, 'driver-phone'),
        email: getValue(form, 'driver-email'),
        license_number: getValue(form, 'driver-license-number'),
        vehicle_type: getValue(form, 'driver-vehicle-type'),
        vehicle_id: getValue(form, 'driver-vehicle-id') || null,
        rating: getValue(form, 'driver-rating') || 5,
        speed_kmh: getValue(form, 'driver-speed-kmh') || 40,
        status: getValue(form, 'driver-status') || 'available',
        is_online: getValue(form, 'driver-online') === '1',
        ride_response_mode: getValue(form, 'driver-response-mode') || 'manual',
        latitude: getValue(form, 'driver-latitude') || null,
        longitude: getValue(form, 'driver-longitude') || null,
        heading: getValue(form, 'driver-heading') || null,
        simulation_notes: getValue(form, 'driver-notes') || null,
    };
}

async function handleDriverSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const driverId = getValue(form, 'driver-id');
    const payload = selectedDriverPayload(form);

    try {
        const endpoint = driverId ? `${app.endpoints.drivers}/${driverId}` : app.endpoints.drivers;
        const data = await api(endpoint, {
            method: driverId ? 'PUT' : 'POST',
            body: JSON.stringify(payload),
        });

        mergeDriver(data.driver);
        app.stats = data.stats || app.stats;
        renderState();
        renderStatus(data.message || 'Driver saved.', 'success');
    } catch (error) {
        renderStatus(error.message, 'danger');
    }
}

async function handleSpawnSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;

    try {
        const data = await api(app.endpoints.spawn, {
            method: 'POST',
            body: JSON.stringify({
                count: Number(getValue(form, 'spawn-count') || 1),
                radius_km: Number(getValue(form, 'spawn-radius') || 5),
                center_latitude: Number(getValue(form, 'spawn-latitude') || app.defaultCenter.lat),
                center_longitude: Number(getValue(form, 'spawn-longitude') || app.defaultCenter.lng),
                vehicle_type: getValue(form, 'spawn-vehicle-type') || null,
                speed_kmh: Number(getValue(form, 'spawn-speed') || 40),
                ride_response_mode: getValue(form, 'spawn-response-mode') || 'manual',
            }),
        });

        app.drivers = data.drivers ? data.drivers : app.drivers;
        app.stats = data.stats || app.stats;
        renderState();
        renderStatus(data.message || 'Drivers spawned.', 'success');
    } catch (error) {
        renderStatus(error.message, 'danger');
    }
}

async function handleRideSubmit(event) {
    event.preventDefault();
    const form = event.currentTarget;

    try {
        const data = await api(app.endpoints.rides, {
            method: 'POST',
            body: JSON.stringify({
                passenger_name: getValue(form, 'ride-passenger-name') || null,
                passenger_phone: getValue(form, 'ride-passenger-phone') || null,
                pickup_label: getValue(form, 'ride-pickup-label') || null,
                dropoff_label: getValue(form, 'ride-dropoff-label') || null,
                pickup_latitude: Number(getValue(form, 'ride-pickup-latitude')),
                pickup_longitude: Number(getValue(form, 'ride-pickup-longitude')),
                dropoff_latitude: Number(getValue(form, 'ride-dropoff-latitude')),
                dropoff_longitude: Number(getValue(form, 'ride-dropoff-longitude')),
                auto_response_mode: getValue(form, 'ride-auto-response-mode') || 'manual',
                order_id: getValue(form, 'ride-order-id') || null,
            }),
        });

        app.rides = [data.ride, ...app.rides.filter((ride) => Number(ride.id) !== Number(data.ride.id))];
        app.stats = data.stats || app.stats;
        renderState();
        renderStatus(data.message || 'Ride request created.', 'success');
    } catch (error) {
        renderStatus(error.message, 'danger');
    }
}

async function updateRideDecision(rideId, decision) {
    const ride = app.rides.find((candidate) => Number(candidate.id) === Number(rideId));
    if (!ride) {
        return;
    }

    try {
        const data = await api(`${app.endpoints.rides}/${rideId}/decision`, {
            method: 'POST',
            body: JSON.stringify({
                decision,
                driver_id: ride.driver?.id || app.selectedDriverId || null,
            }),
        });

        app.rides = [data.ride, ...app.rides.filter((candidate) => Number(candidate.id) !== Number(data.ride.id))];
        app.stats = data.stats || app.stats;
        renderState();
        renderStatus(data.message || `Ride ${decision}d.`, 'success');
    } catch (error) {
        renderStatus(error.message, 'danger');
    }
}

async function moveDriver(driverId, payload) {
    return api(`${app.endpoints.drivers}/${driverId}/move`, {
        method: 'POST',
        body: JSON.stringify(payload),
    });
}

function getValue(form, id) {
    const el = form.querySelector(`[name="${id}"]`) || document.getElementById(id);
    return el ? el.value : '';
}

function mergeDriver(driver) {
    if (!driver) {
        return;
    }

    const index = app.drivers.findIndex((candidate) => Number(candidate.id) === Number(driver.id));
    if (index >= 0) {
        app.drivers[index] = driver;
    } else {
        app.drivers.unshift(driver);
    }
}

function renderState() {
    renderStats(app.stats);
    renderDrivers(app.drivers);
    renderRides(app.rides);
    renderRoutePolyline();
}

function renderRoutePolyline() {
    if (!app.map || !window.google?.maps) {
        return;
    }

    if (app.routePolyline) {
        app.routePolyline.setMap(null);
        app.routePolyline = null;
    }

    const ride = app.rides.find((candidate) => Number(candidate.id) === Number(app.selectedRideId)) || app.rides[0];
    if (!ride || !Array.isArray(ride.route_points) || ride.route_points.length < 2) {
        return;
    }

    const path = ride.route_points
        .filter((point) => point && point.lat !== undefined && point.lng !== undefined)
        .map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }));

    if (path.length < 2) {
        return;
    }

    app.routePolyline = new google.maps.Polyline({
        map: app.map,
        path,
        strokeColor: '#67d1ff',
        strokeOpacity: 0.9,
        strokeWeight: 4,
    });
}

function focusRide(rideId) {
    app.selectedRideId = Number(rideId);
    renderState();

    const ride = app.rides.find((candidate) => Number(candidate.id) === Number(rideId));
    if (!ride || !app.map) {
        return;
    }

    const bounds = new google.maps.LatLngBounds();
    bounds.extend({ lat: Number(ride.pickup_latitude), lng: Number(ride.pickup_longitude) });
    bounds.extend({ lat: Number(ride.dropoff_latitude), lng: Number(ride.dropoff_longitude) });
    app.map.fitBounds(bounds);
}

async function refreshState(useTick = true) {
    try {
        const endpoint = useTick ? app.endpoints.tick : app.endpoints.state;
        const data = await api(endpoint, {
            method: useTick ? 'POST' : 'GET',
        });

        app.drivers = data.drivers || [];
        app.rides = data.rides || [];
        app.stats = data.stats || {};
        renderState();
        renderStatus('Simulator updated.', 'success');
    } catch (error) {
        renderStatus(error.message, 'danger');
    }
}

async function refreshTick() {
    return refreshState(true);
}

function startPolling() {
    stopPolling();
    app.pollTimer = window.setInterval(() => {
        refreshTick();
    }, 1200);
}

function stopPolling() {
    if (app.pollTimer) {
        window.clearInterval(app.pollTimer);
        app.pollTimer = null;
    }
}

async function loadGoogleMaps() {
    if (!app.endpoints.googleMapsKey) {
        throw new Error('Google Maps key missing.');
    }

    if (window.google?.maps) {
        return;
    }

    await new Promise((resolve, reject) => {
        const existing = document.getElementById('google-maps-script');
        if (existing) {
            existing.addEventListener('load', resolve, { once: true });
            existing.addEventListener('error', reject, { once: true });
            return;
        }

        const script = document.createElement('script');
        script.id = 'google-maps-script';
        script.async = true;
        script.defer = true;
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(app.endpoints.googleMapsKey)}&callback=__driverSimulatorMapReady`;

        window.__driverSimulatorMapReady = () => resolve();
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

document.addEventListener('DOMContentLoaded', boot);

window.DriverSimulator = {
    refreshState,
    refreshTick,
};

function debounce(callback, wait) {
    let timeoutId;

    return (...args) => {
        window.clearTimeout(timeoutId);
        timeoutId = window.setTimeout(() => callback(...args), wait);
    };
}
