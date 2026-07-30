@extends('voyager::master')

@section('page_title', __('voyager::generic.'.(isset($dataTypeContent->id) ? 'edit' : 'add')).' '.$dataType->getTranslatedAttribute('display_name_singular'))

@section('css')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .order-summary-card .panel-heading {
            background: #f7f9fc;
            border-bottom: 1px solid #e5e9f2;
        }

        .order-summary-table {
            margin-bottom: 0;
        }

        .order-summary-table td {
            padding: 8px 12px !important;
            vertical-align: top;
        }

        .order-summary-table td:first-child {
            width: 180px;
            font-weight: 600;
            color: #5e6b7a;
        }

        .order-summary-pre {
            max-height: 260px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-word;
            margin: 0;
        }
    </style>
@stop

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ __('voyager::generic.'.(isset($dataTypeContent->id) ? 'edit' : 'add')).' '.$dataType->getTranslatedAttribute('display_name_singular') }}
    </h1>
@stop

@section('content')
    @php
        $dataTypeContent->loadMissing(['seller', 'driver', 'startAddress', 'endAddress']);

        $routePoints = $dataTypeContent->route_points ?? [];
        if (is_string($routePoints)) {
            $decodedRoutePoints = json_decode($routePoints, true);
            $routePoints = is_array($decodedRoutePoints) ? $decodedRoutePoints : [];
        }

        $seller = $dataTypeContent->seller;
        $driver = $dataTypeContent->driver;
        $startAddress = $dataTypeContent->startAddress;
        $endAddress = $dataTypeContent->endAddress;

        $formatAddress = function ($address) {
            if (!$address) {
                return 'Not available';
            }

            $parts = array_filter([
                $address->address_line1 ?? null,
                $address->city ?? null,
                $address->region ?? null,
                $address->country ?? null,
            ]);

            return implode(', ', $parts);
        };
    @endphp

    <div class="page-content container-fluid">
        <form class="form-edit-add" role="form"
              action="@if(!is_null($dataTypeContent->getKey())){{ route('voyager.'.$dataType->slug.'.update', $dataTypeContent->getKey()) }}@else{{ route('voyager.'.$dataType->slug.'.store') }}@endif"
              method="POST" enctype="multipart/form-data" autocomplete="off">
            @if(isset($dataTypeContent->id))
                {{ method_field('PUT') }}
            @endif
            {{ csrf_field() }}

            <div class="panel panel-bordered order-summary-card">
                <div class="panel-heading">
                    <h3 class="panel-title">Order Summary</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-striped order-summary-table">
                                <tbody>
                                    <tr>
                                        <td>Order ID</td>
                                        <td>{{ $dataTypeContent->id }}</td>
                                    </tr>
                                    <tr>
                                        <td>Tracking ID</td>
                                        <td>{{ $dataTypeContent->tracking_id ?: 'Not set' }}</td>
                                    </tr>
                                    <tr>
                                        <td>Status</td>
                                        <td>{{ $dataTypeContent->status ?: 'Not set' }}</td>
                                    </tr>
                                    <tr>
                                        <td>Route Distance</td>
                                        <td>{{ $dataTypeContent->route_distance_km ?? 'Not set' }} km</td>
                                    </tr>
                                    <tr>
                                        <td>Route Points</td>
                                        <td>{{ is_array($routePoints) ? count($routePoints) : 0 }} points</td>
                                    </tr>
                                    <tr>
                                        <td>Created At</td>
                                        <td>{{ $dataTypeContent->created_at ?: 'Not set' }}</td>
                                    </tr>
                                    <tr>
                                        <td>Updated At</td>
                                        <td>{{ $dataTypeContent->updated_at ?: 'Not set' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="table table-striped order-summary-table">
                                <tbody>
                                    <tr>
                                        <td>Seller</td>
                                        <td>
                                            @if($seller)
                                                <div><strong>{{ $seller->name }}</strong></div>
                                                <div>{{ $seller->phone ?: 'No phone' }}</div>
                                                <div>{{ $seller->email ?: 'No email' }}</div>
                                                <div style="margin-top:6px;">
                                                    <a href="{{ route('voyager.users.edit', $seller->id) }}" class="btn btn-sm btn-link" style="padding-left:0;">
                                                        Open seller profile
                                                    </a>
                                                </div>
                                            @else
                                                Not assigned
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Driver</td>
                                        <td>
                                            @if($driver)
                                                <div><strong>{{ $driver->name }}</strong></div>
                                                <div>{{ $driver->phone ?: 'No phone' }}</div>
                                                <div>{{ $driver->email ?: 'No email' }}</div>
                                                <div style="margin-top:6px;">
                                                    <a href="{{ route('voyager.users.edit', $driver->id) }}" class="btn btn-sm btn-link" style="padding-left:0;">
                                                        Open driver profile
                                                    </a>
                                                </div>
                                            @else
                                                Not assigned
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Start Address</td>
                                        <td>
                                            <div>{{ $formatAddress($startAddress) }}</div>
                                            @if($startAddress)
                                                <div class="text-muted" style="margin-top:4px;">
                                                    Lat: {{ $startAddress->latitude }}, Lng: {{ $startAddress->longitude }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td>Destination Address</td>
                                        <td>
                                            <div>{{ $formatAddress($endAddress) }}</div>
                                            @if($endAddress)
                                                <div class="text-muted" style="margin-top:4px;">
                                                    Lat: {{ $endAddress->latitude }}, Lng: {{ $endAddress->longitude }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <div class="panel panel-default" style="margin-bottom:0;">
                                <div class="panel-heading">
                                    <h3 class="panel-title">Route Points JSON</h3>
                                </div>
                                <div class="panel-body">
                                    <pre class="order-summary-pre">{{ json_encode($routePoints, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if (isset($dataTypeContent->id) && !in_array($dataTypeContent->status, ['failed_delivery', 'canceled', 'delivered'], true))
                <div class="panel panel-bordered order-summary-card">
                    <div class="panel-heading">
                        <h3 class="panel-title">Admin Ops</h3>
                    </div>
                    <div class="panel-body">
                        @if (session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif
                        @if ($errors->has('order'))
                            <div class="alert alert-danger">{{ $errors->first('order') }}</div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <h4>Reassign Driver</h4>
                                <form method="POST" action="{{ route('admin.order-ops.reassign', $dataTypeContent) }}" class="form-inline">
                                    @csrf
                                    <select id="reassign-driver-select" name="driver_id" class="form-control" style="min-width:220px;">
                                        <option value="">Loading drivers…</option>
                                    </select>
                                    <button type="submit" class="btn btn-default">Assign</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <h4>Cancel Order</h4>
                                <form method="POST" action="{{ route('admin.order-ops.cancel', $dataTypeContent) }}" class="form-inline" onsubmit="return confirm('Cancel this order?');">
                                    @csrf
                                    <input type="text" name="reason" class="form-control" placeholder="Reason" required style="min-width:220px;">
                                    <button type="submit" class="btn btn-danger">Cancel Order</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    fetch('{{ route('admin.order-ops.candidate-drivers', $dataTypeContent) }}', { headers: { Accept: 'application/json' } })
                        .then((response) => response.json())
                        .then((payload) => {
                            const select = document.getElementById('reassign-driver-select');
                            select.innerHTML = '';

                            if (!payload.result || !payload.drivers.length) {
                                select.innerHTML = '<option value="">No available drivers</option>';
                                return;
                            }

                            payload.drivers.forEach((driver) => {
                                const option = document.createElement('option');
                                option.value = driver.id;
                                const distance = driver.distance_km ? ` — ${driver.distance_km.toFixed(1)} km` : '';
                                option.textContent = `${driver.name} (${driver.phone})${distance}`;
                                select.appendChild(option);
                            });
                        })
                        .catch(() => {
                            document.getElementById('reassign-driver-select').innerHTML = '<option value="">Failed to load drivers</option>';
                        });
                </script>
            @endif

            <div class="row">
                <div class="col-md-8">
                    <div class="panel panel-bordered">
                        @if (count($errors) > 0)
                            <div class="alert alert-danger">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="panel-body">
                            @php
                                $dataTypeRows = $dataType->{(isset($dataTypeContent->id) ? 'editRows' : 'addRows' )};
                            @endphp

                            @foreach($dataTypeRows as $row)
                                @php
                                    $display_options = $row->details->display ?? NULL;
                                    if ($dataTypeContent->{$row->field.'_'.(isset($dataTypeContent->id) ? 'edit' : 'add')}) {
                                        $dataTypeContent->{$row->field} = $dataTypeContent->{$row->field.'_'.(isset($dataTypeContent->id) ? 'edit' : 'add')};
                                    }
                                @endphp

                                @if (isset($row->details->legend) && isset($row->details->legend->text))
                                    <legend class="text-{{ $row->details->legend->align ?? 'center' }}" style="background-color: {{ $row->details->legend->bgcolor ?? '#f0f0f0' }};padding: 5px;">{{ $row->details->legend->text }}</legend>
                                @endif

                                <div class="form-group @if($row->type == 'hidden') hidden @endif col-md-{{ $display_options->width ?? 12 }} {{ $errors->has($row->field) ? 'has-error' : '' }}" @if(isset($display_options->id)){{ "id=$display_options->id" }}@endif>
                                    {{ $row->slugify }}
                                    <label class="control-label" for="name">{{ $row->getTranslatedAttribute('display_name') }}</label>
                                    @include('voyager::multilingual.input-hidden-bread-edit-add')
                                    @if ($row->type == 'relationship')
                                        @include('voyager::formfields.relationship', ['options' => $row->details])
                                    @else
                                        {!! app('voyager')->formField($row, $dataType, $dataTypeContent) !!}
                                    @endif

                                    @foreach (app('voyager')->afterFormFields($row, $dataType, $dataTypeContent) as $after)
                                        {!! $after->handle($row, $dataType, $dataTypeContent) !!}
                                    @endforeach
                                    @if ($errors->has($row->field))
                                        @foreach ($errors->get($row->field) as $error)
                                            <span class="help-block">{{ $error }}</span>
                                        @endforeach
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div class="panel-footer">
                            @section('submit-buttons')
                                <button type="submit" class="btn btn-primary save">{{ __('voyager::generic.save') }}</button>
                            @stop
                            @yield('submit-buttons')
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div style="display:none">
            <input type="hidden" id="upload_url" value="{{ route('voyager.upload') }}">
            <input type="hidden" id="upload_type_slug" value="{{ $dataType->slug }}">
        </div>
    </div>
@stop

@section('javascript')
    <script>
        var params = {};
        var $file;

        function deleteHandler(tag, isMulti) {
          return function() {
            $file = $(this).siblings(tag);

            params = {
                slug:   '{{ $dataType->slug }}',
                filename:  $file.data('file-name'),
                id:     $file.data('id'),
                field:  $file.parent().data('field-name'),
                multi: isMulti,
                _token: '{{ csrf_token() }}'
            }
          };
        }

        $('document').ready(function () {
            $('.toggleswitch').bootstrapToggle();

            $('.form-group input[type=date]').each(function (idx, elt) {
                if (elt.hasAttribute('data-datepicker')) {
                    elt.type = 'text';
                    $(elt).datetimepicker($(elt).data('datepicker'));
                } else if (elt.type != 'date') {
                    elt.type = 'text';
                    $(elt).datetimepicker({
                        format: 'L',
                        extraFormats: [ 'YYYY-MM-DD' ]
                    }).datetimepicker($(elt).data('datepicker'));
                }
            });

            @if ($isModelTranslatable)
                $('.side-body').multilingual({"editing": true});
            @endif

            $('.side-body input[data-slug-origin]').each(function(i, el) {
                $(el).slugify();
            });

            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
@stop
