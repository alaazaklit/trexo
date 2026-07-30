@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Driver Simulator')

@section('content')
    @php
        $defaultCenterLat = 33.5570;
        $defaultCenterLng = 35.3720;
    @endphp

    <div
        id="driver-simulator-app"
        class="driver-simulator-shell"
        data-state-url="{{ route('admin.driver-simulator.state') }}"
        data-tick-url="{{ route('admin.driver-simulator.tick') }}"
        data-spawn-url="{{ route('admin.driver-simulator.spawn') }}"
        data-drivers-url="{{ route('admin.driver-simulator.drivers.store') }}"
        data-rides-url="{{ route('admin.driver-simulator.ride-requests.store') }}"
        data-google-maps-key="{{ config('services.google_maps.key') }}"
        data-default-center-lat="{{ $defaultCenterLat }}"
        data-default-center-lng="{{ $defaultCenterLng }}"
        data-default-zoom="13"
    >
        <div id="driver-simulator-map" class="driver-simulator-map"></div>

        <div class="driver-simulator-overlay">
            <div class="driver-simulator-column">
                <section class="driver-simulator-panel driver-simulator-panel--solid">
                    <div class="driver-simulator-panel__header">
                        <div>
                            <h1 class="driver-simulator-title">Driver Simulator</h1>
                            <p class="driver-simulator-subtitle">Create fake drivers, move them live, and simulate ride dispatch flow.</p>
                        </div>
                        <div id="simulator-status" class="driver-simulator-hint">Ready</div>
                    </div>
                    <div class="driver-simulator-panel__body">
                        <div class="driver-simulator-stats">
                            <div class="driver-simulator-stat">
                                <div class="driver-simulator-stat__label">Drivers Online</div>
                                <div class="driver-simulator-stat__value" id="stat-drivers-online">0</div>
                            </div>
                            <div class="driver-simulator-stat">
                                <div class="driver-simulator-stat__label">Drivers Offline</div>
                                <div class="driver-simulator-stat__value" id="stat-drivers-offline">0</div>
                            </div>
                            <div class="driver-simulator-stat">
                                <div class="driver-simulator-stat__label">Busy Drivers</div>
                                <div class="driver-simulator-stat__value" id="stat-busy-drivers">0</div>
                            </div>
                            <div class="driver-simulator-stat">
                                <div class="driver-simulator-stat__label">Available Drivers</div>
                                <div class="driver-simulator-stat__value" id="stat-available-drivers">0</div>
                            </div>
                            <div class="driver-simulator-stat">
                                <div class="driver-simulator-stat__label">Trips Running</div>
                                <div class="driver-simulator-stat__value" id="stat-trips-running">0</div>
                            </div>
                            <div class="driver-simulator-stat">
                                <div class="driver-simulator-stat__label">Avg Response</div>
                                <div class="driver-simulator-stat__value" id="stat-average-response-time">0.00s</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="driver-simulator-panel">
                    <div class="driver-simulator-panel__header">
                        <div>
                            <h2 class="driver-simulator-title">Driver List</h2>
                            <p class="driver-simulator-subtitle">Search, filter, and click a marker or row to edit the driver.</p>
                        </div>
                    </div>
                    <div class="driver-simulator-panel__body">
                        <div class="driver-simulator-toolbar" style="margin-bottom: .75rem;">
                            <input id="driver-search" class="driver-simulator-input" type="text" placeholder="Search by ID, name, or license">
                            <select id="driver-status-filter" class="driver-simulator-select">
                                <option value="">All statuses</option>
                                <option value="available">Available</option>
                                <option value="busy">Busy</option>
                                <option value="offline">Offline</option>
                                <option value="driving_to_pickup">Driving to Pickup</option>
                                <option value="waiting_passenger">Waiting Passenger</option>
                                <option value="trip_started">Trip Started</option>
                                <option value="trip_finished">Trip Finished</option>
                            </select>
                            <select id="vehicle-filter" class="driver-simulator-select">
                                <option value="">All vehicles</option>
                                @foreach($vehicleTypes as $vehicleType)
                                    <option value="{{ $vehicleType }}">{{ $vehicleType }}</option>
                                @endforeach
                            </select>
                            <select id="online-filter" class="driver-simulator-select">
                                <option value="">Online state</option>
                                <option value="1">Online only</option>
                                <option value="0">Offline only</option>
                            </select>
                            <select id="busy-filter" class="driver-simulator-select">
                                <option value="">Busy filter</option>
                                <option value="1">Busy only</option>
                            </select>
                        </div>
                        <div id="driver-list" class="driver-simulator-list"></div>
                    </div>
                </section>
            </div>

            <div class="driver-simulator-column">
                <section class="driver-simulator-panel">
                    <div class="driver-simulator-panel__header">
                        <div>
                            <h2 class="driver-simulator-title">Driver Controls</h2>
                            <p class="driver-simulator-subtitle">Create, edit, or remove simulated drivers.</p>
                        </div>
                        <div class="driver-simulator-actions" style="margin: 0;">
                            <button id="refresh-state-button" type="button" class="driver-simulator-button driver-simulator-button--ghost">Refresh</button>
                            <button id="tick-button" type="button" class="driver-simulator-button">Tick Now</button>
                        </div>
                    </div>
                    <div class="driver-simulator-panel__body">
                        <form id="driver-form" class="driver-simulator-controls">
                            <input type="hidden" id="driver-id" name="driver-id">
                            <div class="driver-simulator-form">
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-name">Name</label>
                                    <input id="driver-name" name="driver-name" class="driver-simulator-input" type="text" placeholder="Driver name">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-phone">Phone</label>
                                    <input id="driver-phone" name="driver-phone" class="driver-simulator-input" type="text" placeholder="+961...">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-email">Email</label>
                                    <input id="driver-email" name="driver-email" class="driver-simulator-input" type="email" placeholder="driver@example.test">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-license-number">License</label>
                                    <input id="driver-license-number" name="driver-license-number" class="driver-simulator-input" type="text" placeholder="SIM-0001">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-vehicle-type">Vehicle Type</label>
                                    <select id="driver-vehicle-type" name="driver-vehicle-type" class="driver-simulator-select">
                                        <option value="">Select vehicle</option>
                                        @foreach($vehicleTypes as $vehicleType)
                                            <option value="{{ $vehicleType }}">{{ $vehicleType }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-vehicle-id">Vehicle ID</label>
                                    <input id="driver-vehicle-id" name="driver-vehicle-id" class="driver-simulator-input" type="number" placeholder="Optional">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-rating">Rating</label>
                                    <input id="driver-rating" name="driver-rating" class="driver-simulator-input" type="number" step="0.1" min="0" max="5" value="5">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-speed-kmh">Speed km/h</label>
                                    <input id="driver-speed-kmh" name="driver-speed-kmh" class="driver-simulator-input" type="number" step="1" min="0" max="180" value="40">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-status">Status</label>
                                    <select id="driver-status" name="driver-status" class="driver-simulator-select">
                                        <option value="available">Available</option>
                                        <option value="busy">Busy</option>
                                        <option value="offline">Offline</option>
                                        <option value="driving_to_pickup">Driving to Pickup</option>
                                        <option value="waiting_passenger">Waiting Passenger</option>
                                        <option value="trip_started">Trip Started</option>
                                        <option value="trip_finished">Trip Finished</option>
                                    </select>
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-online">Online</label>
                                    <select id="driver-online" name="driver-online" class="driver-simulator-select">
                                        <option value="1">Online</option>
                                        <option value="0">Offline</option>
                                    </select>
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-response-mode">Ride Response Mode</label>
                                    <select id="driver-response-mode" name="driver-response-mode" class="driver-simulator-select">
                                        <option value="manual">Manual</option>
                                        <option value="auto_accept">Auto Accept</option>
                                        <option value="auto_reject">Auto Reject</option>
                                    </select>
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-latitude">Latitude</label>
                                    <input id="driver-latitude" name="driver-latitude" class="driver-simulator-input" type="number" step="0.0000001" placeholder="33.5570000">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-longitude">Longitude</label>
                                    <input id="driver-longitude" name="driver-longitude" class="driver-simulator-input" type="number" step="0.0000001" placeholder="35.3720000">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="driver-heading">Heading</label>
                                    <input id="driver-heading" name="driver-heading" class="driver-simulator-input" type="number" step="1" min="0" max="360" placeholder="0">
                                </div>
                                <div class="driver-simulator-field" style="grid-column: 1 / -1;">
                                    <label class="driver-simulator-label" for="driver-notes">Notes</label>
                                    <textarea id="driver-notes" name="driver-notes" class="driver-simulator-textarea" placeholder="Optional notes for this simulated driver"></textarea>
                                </div>
                            </div>

                            <div class="driver-simulator-actions">
                                <button type="submit" class="driver-simulator-button">Save Driver</button>
                                <button id="clear-driver-form" type="button" class="driver-simulator-button driver-simulator-button--ghost">Clear</button>
                                <button id="use-selected-driver-button" type="button" class="driver-simulator-button driver-simulator-button--ghost">Load Selected</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="driver-simulator-panel">
                    <div class="driver-simulator-panel__header">
                        <div>
                            <h2 class="driver-simulator-title">Spawn Drivers</h2>
                            <p class="driver-simulator-subtitle">Generate many drivers inside a city radius for stress testing.</p>
                        </div>
                    </div>
                    <div class="driver-simulator-panel__body">
                        <form id="spawn-form" class="driver-simulator-controls">
                            <div class="driver-simulator-form">
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="spawn-count">Number of Drivers</label>
                                    <input id="spawn-count" name="spawn-count" class="driver-simulator-input" type="number" min="1" max="500" value="100">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="spawn-radius">Radius (km)</label>
                                    <input id="spawn-radius" name="spawn-radius" class="driver-simulator-input" type="number" step="0.1" min="0.1" max="100" value="5">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="spawn-latitude">Center Latitude</label>
                                    <input id="spawn-latitude" name="spawn-latitude" class="driver-simulator-input" type="number" step="0.0000001" value="{{ $defaultCenterLat }}">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="spawn-longitude">Center Longitude</label>
                                    <input id="spawn-longitude" name="spawn-longitude" class="driver-simulator-input" type="number" step="0.0000001" value="{{ $defaultCenterLng }}">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="spawn-vehicle-type">Vehicle Type</label>
                                    <select id="spawn-vehicle-type" name="spawn-vehicle-type" class="driver-simulator-select">
                                        <option value="">Random</option>
                                        @foreach($vehicleTypes as $vehicleType)
                                            <option value="{{ $vehicleType }}">{{ $vehicleType }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="spawn-speed">Speed km/h</label>
                                    <input id="spawn-speed" name="spawn-speed" class="driver-simulator-input" type="number" min="5" max="180" value="40">
                                </div>
                                <div class="driver-simulator-field" style="grid-column: 1 / -1;">
                                    <label class="driver-simulator-label" for="spawn-response-mode">Ride Response Mode</label>
                                    <select id="spawn-response-mode" name="spawn-response-mode" class="driver-simulator-select">
                                        <option value="manual">Manual</option>
                                        <option value="auto_accept">Auto Accept</option>
                                        <option value="auto_reject">Auto Reject</option>
                                    </select>
                                </div>
                            </div>

                            <div class="driver-simulator-actions">
                                <button type="submit" class="driver-simulator-button">Spawn Drivers</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="driver-simulator-panel">
                    <div class="driver-simulator-panel__header">
                        <div>
                            <h2 class="driver-simulator-title">Ride Controls</h2>
                            <p class="driver-simulator-subtitle">Simulate a passenger request and drive the same workflow as the mobile app.</p>
                        </div>
                    </div>
                    <div class="driver-simulator-panel__body">
                        <form id="ride-form" class="driver-simulator-controls">
                            <div class="driver-simulator-form">
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-passenger-name">Passenger Name</label>
                                    <input id="ride-passenger-name" name="ride-passenger-name" class="driver-simulator-input" type="text" placeholder="Test Passenger">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-passenger-phone">Passenger Phone</label>
                                    <input id="ride-passenger-phone" name="ride-passenger-phone" class="driver-simulator-input" type="text" placeholder="+961...">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-order-id">Order ID</label>
                                    <input id="ride-order-id" name="ride-order-id" class="driver-simulator-input" type="number" placeholder="Optional existing order">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-auto-response-mode">Auto Response Mode</label>
                                    <select id="ride-auto-response-mode" name="ride-auto-response-mode" class="driver-simulator-select">
                                        <option value="manual">Manual</option>
                                        <option value="auto_accept">Auto Accept</option>
                                        <option value="auto_reject">Auto Reject</option>
                                    </select>
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-pickup-label">Pickup Label</label>
                                    <input id="ride-pickup-label" name="ride-pickup-label" class="driver-simulator-input" type="text" placeholder="Pickup address">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-dropoff-label">Dropoff Label</label>
                                    <input id="ride-dropoff-label" name="ride-dropoff-label" class="driver-simulator-input" type="text" placeholder="Dropoff address">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-pickup-latitude">Pickup Latitude</label>
                                    <input id="ride-pickup-latitude" name="ride-pickup-latitude" class="driver-simulator-input" type="number" step="0.0000001" placeholder="33.5570000">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-pickup-longitude">Pickup Longitude</label>
                                    <input id="ride-pickup-longitude" name="ride-pickup-longitude" class="driver-simulator-input" type="number" step="0.0000001" placeholder="35.3720000">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-dropoff-latitude">Dropoff Latitude</label>
                                    <input id="ride-dropoff-latitude" name="ride-dropoff-latitude" class="driver-simulator-input" type="number" step="0.0000001" placeholder="33.5600000">
                                </div>
                                <div class="driver-simulator-field">
                                    <label class="driver-simulator-label" for="ride-dropoff-longitude">Dropoff Longitude</label>
                                    <input id="ride-dropoff-longitude" name="ride-dropoff-longitude" class="driver-simulator-input" type="number" step="0.0000001" placeholder="35.3800000">
                                </div>
                            </div>

                            <div class="driver-simulator-actions">
                                <button type="submit" class="driver-simulator-button">Create Ride Request</button>
                            </div>
                        </form>
                    </div>
                </section>

                <section class="driver-simulator-panel">
                    <div class="driver-simulator-panel__header">
                        <div>
                            <h2 class="driver-simulator-title">Ride Queue</h2>
                            <p class="driver-simulator-subtitle">Review live ride requests, matched drivers, and accept/reject actions.</p>
                        </div>
                    </div>
                    <div class="driver-simulator-panel__body">
                        <div id="ride-list" class="driver-simulator-rides"></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection

@section('javascript')
    @vite(['resources/js/driver-simulator.js'])
@endsection
