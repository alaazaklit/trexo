@extends('voyager::master')

@section('page_title', $pageTitle ?? 'Dashboard')

@section('content')
    <div
        id="admin-dashboard-app"
        class="dashboard-shell"
        data-data-url="{{ route('admin.dashboard.data') }}"
        data-range-days="{{ $rangeDays }}"
    >
        <div class="dashboard-range">
            <a href="{{ route('admin.dashboard') }}?range=1" class="btn btn-{{ $rangeDays === 1 ? 'primary' : 'default' }}">Today</a>
            <a href="{{ route('admin.dashboard') }}?range=7" class="btn btn-{{ $rangeDays === 7 ? 'primary' : 'default' }}">7 days</a>
            <a href="{{ route('admin.dashboard') }}?range=30" class="btn btn-{{ $rangeDays === 30 ? 'primary' : 'default' }}">30 days</a>
        </div>

        <div class="dashboard-stats">
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Active Drivers</div>
                <div class="dashboard-stat__value" id="stat-active-drivers">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Active Customers</div>
                <div class="dashboard-stat__value" id="stat-active-customers">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Live Trips (Taxi)</div>
                <div class="dashboard-stat__value" id="stat-live-taxi">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Live Deliveries</div>
                <div class="dashboard-stat__value" id="stat-live-delivery">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Completed Orders</div>
                <div class="dashboard-stat__value" id="stat-completed">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Cancellations</div>
                <div class="dashboard-stat__value" id="stat-cancellations">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Trexo Revenue Today</div>
                <div class="dashboard-stat__value" id="stat-revenue-today">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Trexo Revenue This Month</div>
                <div class="dashboard-stat__value" id="stat-revenue-month">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Trexo Revenue All Time</div>
                <div class="dashboard-stat__value" id="stat-revenue-alltime">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Plus Subscribers</div>
                <div class="dashboard-stat__value" id="stat-subs-plus">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Pending Subscriptions</div>
                <div class="dashboard-stat__value" id="stat-subs-pending">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Lapsed Subscriptions</div>
                <div class="dashboard-stat__value" id="stat-subs-lapsed">—</div>
            </div>
            <div class="dashboard-stat">
                <div class="dashboard-stat__label">Total Commission Owed</div>
                <div class="dashboard-stat__value" id="stat-commission-owed">—</div>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3>Order Volume Trend</h3>
            </div>
            <div class="panel-body">
                <canvas id="volume-trend-chart" height="90"></canvas>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3>Cancellation Breakdown</h3>
            </div>
            <div class="panel-body">
                <canvas id="cancellations-chart" height="90"></canvas>
            </div>
        </div>

        <div class="panel panel-bordered">
            <div class="panel-heading">
                <h3>Top Drivers by Earnings</h3>
            </div>
            <div class="panel-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Driver</th>
                            <th>Completed Orders</th>
                            <th>Total Earnings</th>
                            <th>Rating</th>
                        </tr>
                    </thead>
                    <tbody id="top-drivers-body">
                        <tr><td colspan="4">Loading…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    @vite(['resources/js/dashboard.js'])
@endsection
