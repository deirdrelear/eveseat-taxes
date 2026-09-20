@extends('web::layouts.grids.12')

@section('title', 'Taxes')
@section('page_header', 'Taxes')

@section('full')
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">SeAT Taxes</h3>
    </div>
    <div class="card-body">
      <p>
        Read-only SeAT source resolution is available. Canonical tax calculation
        is still intentionally disabled until RAtaxes formula parity is tested.
      </p>

      <dl class="row">
        <dt class="col-sm-3">Rule sets</dt>
        <dd class="col-sm-9">{{ number($rule_set_count) }}</dd>
        <dt class="col-sm-3">Daily results</dt>
        <dd class="col-sm-9">{{ number($result_count) }}</dd>
      </dl>

      <a class="btn btn-primary" href="{{ route('taxes.diagnostics') }}">
        Open source diagnostics
      </a>
    </div>
  </div>
@stop
