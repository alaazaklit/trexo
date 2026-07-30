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
            <div class="dashboard-stat dashboard-stat--disabled">
                <div class="dashboard-stat__label">Revenue</div>
                <div class="dashboard-stat__value">N/A</div>
                <div class="dashboard-stat__note">Requires order pricing data, not yet tracked</div>
            </div>
            <div class="dashboard-stat dashboard-stat--disabled">
                <div class="dashboard-stat__label">Commissions</div>
                <div class="dashboard-stat__value">N/A</div>
                <div class="dashboard-stat__note">Requires order pricing data, not yet tracked</div>
            </div>
            <div class="dashboard-stat dashboard-stat--disabled">
                <div class="dashboard-stat__label">Payouts</div>
                <div class="dashboard-stat__value">N/A</div>
                <div class="dashboard-stat__note">Driver payouts not yet implemented</div>
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
    @vite(['resources/js/dashboard.js'])
@endsection
