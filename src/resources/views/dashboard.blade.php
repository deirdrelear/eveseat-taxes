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
        Read-only source resolution and dry-run tax calculation are available.
        Canonical tax writes and scheduling are intentionally disabled.
      </p>

      <dl class="row">
        <dt class="col-sm-3">Rule sets</dt>
        <dd class="col-sm-9">{{ number($rule_set_count) }}</dd>
        <dt class="col-sm-3">Canonical daily results</dt>
        <dd class="col-sm-9">{{ number($result_count) }}</dd>
      </dl>

      <a class="btn btn-primary mr-2" href="{{ route('taxes.diagnostics') }}">
        Diagnostics
      </a>
      @can('taxes.manage')
        <a class="btn btn-secondary mr-2" href="{{ route('taxes.rules') }}">
          Rules
        </a>
      @endcan
      @can('taxes.recalculate')
        <a class="btn btn-secondary" href="{{ route('taxes.dry-run') }}">
          Dry Run
        </a>
      @endcan
    </div>
  </div>
@stop
