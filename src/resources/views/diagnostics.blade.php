@extends('web::layouts.grids.12')

@section('title', 'Tax Diagnostics')
@section('page_header', 'Tax Diagnostics')

@section('full')
  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Read-only SeAT source diagnostics</h3>
    </div>
    <div class="card-body">
      <form method="GET" action="{{ route('taxes.diagnostics') }}" class="form-inline mb-3">
        <label class="mr-2" for="date">UTC date</label>
        <input class="form-control mr-2" type="date" id="date" name="date" value="{{ $report['date'] }}">
        <button class="btn btn-primary" type="submit">Inspect</button>
      </form>

      <p class="mb-0">
        This page reads SeAT source tables only. It does not calculate or write tax data.
      </p>
    </div>
  </div>

  @if (! $report['ready'])
    <div class="alert alert-danger">
      <strong>Source schema is incomplete.</strong>
      Missing tables: {{ implode(', ', $report['missing_tables']) }}
    </div>
  @else
    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">Source rows for {{ $report['date'] }} UTC</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Source</th>
                <th>Rows</th>
                <th>Characters</th>
                <th>Corporations</th>
                <th>Types</th>
                <th>Quantity / Amount</th>
                <th>First</th>
                <th>Last</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($report['sources'] as $source => $summary)
                <tr>
                  <td><code>{{ $source }}</code></td>
                  <td>{{ number($summary['rows'] ?? 0) }}</td>
                  <td>{{ isset($summary['characters']) ? number($summary['characters']) : '—' }}</td>
                  <td>{{ isset($summary['corporations']) ? number($summary['corporations']) : '—' }}</td>
                  <td>{{ isset($summary['types']) ? number($summary['types']) : '—' }}</td>
                  <td>{{ number($summary['quantity'] ?? $summary['amount'] ?? 0, 2) }}</td>
                  <td>{{ $summary['first_at'] ?? '—' }}</td>
                  <td>{{ $summary['last_at'] ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        <h3 class="card-title">Integrity checks</h3>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr>
                <th>Check</th>
                <th>Count</th>
              </tr>
            </thead>
            <tbody>
              @foreach ($report['integrity'] as $check => $count)
                <tr>
                  <td><code>{{ $check }}</code></td>
                  <td>{{ number($count) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Required SeAT tables</h3>
      </div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <tbody>
            @foreach ($report['tables'] as $table => $exists)
              <tr>
                <td><code>{{ $table }}</code></td>
                <td>{{ $exists ? 'OK' : 'MISSING' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif
@stop
