@extends('web::layouts.grids.12')

@section('title', 'Taxes')
@section('page_header', 'Taxes')

@section('full')
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">SeAT Taxes</h3>
    </div>
    <div class="card-body">
      <p>The plugin scaffold is installed correctly.</p>
      <dl class="row mb-0">
        <dt class="col-sm-3">Rule sets</dt>
        <dd class="col-sm-9">{{ number($rule_set_count) }}</dd>
        <dt class="col-sm-3">Daily results</dt>
        <dd class="col-sm-9">{{ number($result_count) }}</dd>
      </dl>
    </div>
  </div>
@stop
