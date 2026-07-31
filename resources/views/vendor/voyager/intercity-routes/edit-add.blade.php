@extends('voyager::master')

@section('page_title', __('voyager::generic.'.(isset($dataTypeContent->id) ? 'edit' : 'add')).' '.$dataType->getTranslatedAttribute('display_name_singular'))

@section('page_header')
    <h1 class="page-title">
        <i class="{{ $dataType->icon }}"></i>
        {{ __('voyager::generic.'.(isset($dataTypeContent->id) ? 'edit' : 'add')).' '.$dataType->getTranslatedAttribute('display_name_singular') }}
    </h1>
@stop

@section('content')
    @php
        // from_zone_id/to_zone_id are stored as plain columns (see migration
        // 2026_07_31_000002) rather than Voyager's 'relationship' field type,
        // because that type is stripped from browse/edit/add entirely and
        // silently skipped on save by Voyager's own controller regardless of
        // which view renders the form. They're excluded from the generic
        // loop below and rendered here instead, as proper zone-name selects.
        $zones = \App\Models\PricingZone::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $excludedFields = ['from_zone_id', 'to_zone_id'];
    @endphp

    <div class="page-content container-fluid">
        <form class="form-edit-add" role="form"
              action="@if(!is_null($dataTypeContent->getKey())){{ route('voyager.'.$dataType->slug.'.update', $dataTypeContent->getKey()) }}@else{{ route('voyager.'.$dataType->slug.'.store') }}@endif"
              method="POST" enctype="multipart/form-data" autocomplete="off">
            @if(isset($dataTypeContent->id))
                {{ method_field('PUT') }}
            @endif
            {{ csrf_field() }}

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
                            <div class="form-group col-md-6 {{ $errors->has('from_zone_id') ? 'has-error' : '' }}">
                                <label class="control-label">From Zone</label>
                                <span class="glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="top" title="Pickup zone for this fixed-fare route. Matches a ride in either direction with To Zone."></span>
                                <select class="form-control" name="from_zone_id" required>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}" @selected(old('from_zone_id', $dataTypeContent->from_zone_id) == $zone->id)>{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('from_zone_id'))
                                    @foreach ($errors->get('from_zone_id') as $error)
                                        <span class="help-block">{{ $error }}</span>
                                    @endforeach
                                @endif
                            </div>

                            <div class="form-group col-md-6 {{ $errors->has('to_zone_id') ? 'has-error' : '' }}">
                                <label class="control-label">To Zone</label>
                                <span class="glyphicon glyphicon-question-sign" data-toggle="tooltip" data-placement="top" title="Destination zone for this fixed-fare route. Matches a ride in either direction with From Zone."></span>
                                <select class="form-control" name="to_zone_id" required>
                                    @foreach ($zones as $zone)
                                        <option value="{{ $zone->id }}" @selected(old('to_zone_id', $dataTypeContent->to_zone_id) == $zone->id)>{{ $zone->name }}</option>
                                    @endforeach
                                </select>
                                @if ($errors->has('to_zone_id'))
                                    @foreach ($errors->get('to_zone_id') as $error)
                                        <span class="help-block">{{ $error }}</span>
                                    @endforeach
                                @endif
                            </div>

                            @php
                                $dataTypeRows = $dataType->{(isset($dataTypeContent->id) ? 'editRows' : 'addRows')};
                            @endphp

                            @foreach($dataTypeRows as $row)
                                @continue(in_array($row->field, $excludedFields, true))

                                @php
                                    $display_options = $row->details->display ?? null;
                                @endphp

                                <div class="form-group @if($row->type == 'hidden') hidden @endif col-md-{{ $display_options->width ?? 12 }} {{ $errors->has($row->field) ? 'has-error' : '' }}">
                                    <label class="control-label">{{ $row->getTranslatedAttribute('display_name') }}</label>
                                    {!! app('voyager')->formField($row, $dataType, $dataTypeContent) !!}

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
    </div>
@stop

@section('javascript')
    <script>
        $('document').ready(function () {
            $('.toggleswitch').bootstrapToggle();
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
@stop
