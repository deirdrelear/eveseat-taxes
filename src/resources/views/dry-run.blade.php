@extends('web::layouts.grids.12')

@section('title', 'Tax Dry Run')
@section('page_header', 'Tax Dry Run')

@section('full')
  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Calculate a closed UTC day without writing tax data</h3>
    </div>
    <div class="card-body">
      <form method="GET" action="{{ route('taxes.dry-run') }}" class="form-inline">
        <input type="hidden" name="run" value="1">
        <label class="mr-2" for="date">UTC date</label>
        <input class="form-control mr-2" type="date" id="date" name="date"
               value="{{ $date }}" required>
        <button class="btn btn-primary" type="submit">Run dry calculation</button>
      </form>
      <p class="text-muted mt-2 mb-0">
        This can read a large amount of SeAT data. It does not write canonical tax facts or results.
      </p>
    </div>
  </div>

  @if ($error)
    <div class="alert alert-danger">{{ $error }}</div>
  @endif

  @if ($result)
    <div class="alert alert-info">
      Rule set #{{ $result['rule_set']['id'] }}:
      <strong>{{ $result['rule_set']['name'] }}</strong>.
      No tax data was written.
    </div>

    <div class="row">
      <div class="col-md-3">
        <div class="small-box bg-light">
          <div class="inner">
            <h3>{{ number($result['summary']['accepted_facts']) }}</h3>
            <p>Accepted source facts</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="small-box bg-light">
          <div class="inner">
            <h3>{{ number($result['summary']['skipped_facts']) }}</h3>
            <p>Skipped source facts</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="small-box bg-light">
          <div class="inner">
            <h3>{{ number($result['summary']['gross_value'], 0) }}</h3>
            <p>Gross value, ISK</p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="small-box bg-light">
          <div class="inner">
            <h3>{{ number($result['summary']['tax_amount'], 0) }}</h3>
            <p>Tax, ISK</p>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">By tax class</h3></div>
      <div class="card-body p-0">
        <table class="table table-striped mb-0">
          <thead>
            <tr><th>Class</th><th>Facts</th><th>Gross ISK</th><th>Tax ISK</th></tr>
          </thead>
          <tbody>
            @foreach ($result['summary']['by_class'] as $class => $bucket)
              <tr>
                <td>{{ $class }}</td>
                <td>{{ number($bucket['facts']) }}</td>
                <td>{{ number($bucket['gross_value'], 0) }}</td>
                <td>{{ number($bucket['tax_amount'], 0) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header"><h3 class="card-title">By corporation</h3></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-striped mb-0">
            <thead>
              <tr><th>Corporation</th><th>Facts</th><th>Gross ISK</th><th>Tax ISK</th></tr>
            </thead>
            <tbody>
              @foreach (collect($result['summary']['by_corporation'])->sortByDesc('tax_amount')->take(100) as $corpId => $bucket)
                <tr>
                  <td>{{ $corporation_names[$corpId] ?? 'Unknown' }} ({{ $corpId }})</td>
                  <td>{{ number($bucket['facts']) }}</td>
                  <td>{{ number($bucket['gross_value'], 0) }}</td>
                  <td>{{ number($bucket['tax_amount'], 0) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>

    @if ($result['warnings'])
      <div class="card mb-3">
        <div class="card-header"><h3 class="card-title">Warnings</h3></div>
        <div class="card-body p-0">
          <table class="table table-striped mb-0">
            <thead><tr><th>Warning</th><th>Count</th></tr></thead>
            <tbody>
              @foreach ($result['warnings'] as $warning => $count)
                <tr><td><code>{{ $warning }}</code></td><td>{{ number($count) }}</td></tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif

    <div class="card">
      <div class="card-header"><h3 class="card-title">Current limitations</h3></div>
      <div class="card-body">
        <ul class="mb-0">
          @foreach ($result['limitations'] as $limitation)
            <li>{{ $limitation }}</li>
          @endforeach
        </ul>
      </div>
    </div>
  @endif
@stop
