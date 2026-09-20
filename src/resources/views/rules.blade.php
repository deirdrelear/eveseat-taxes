@extends('web::layouts.grids.12')

@section('title', 'Tax Rules')
@section('page_header', 'Tax Rules')

@section('full')
  @if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger">
      <strong>Rule set was not saved.</strong>
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-header">
      <h3 class="card-title">Create versioned rule set</h3>
    </div>
    <div class="card-body">
      <p>
        Rates and scope are immutable after creation. To change tax policy,
        close the current period and create a new rule set.
      </p>

      <form method="POST" action="{{ route('taxes.rules.store') }}">
        @csrf

        <div class="row">
          <div class="col-md-6 form-group">
            <label for="name">Name</label>
            <input class="form-control" id="name" name="name"
                   value="{{ old('name', 'RAtaxes compatible') }}" required>
          </div>
          <div class="col-md-3 form-group">
            <label for="effective_from">Effective from (UTC)</label>
            <input class="form-control" type="date" id="effective_from"
                   name="effective_from" value="{{ old('effective_from') }}" required>
          </div>
          <div class="col-md-3 form-group">
            <label for="effective_to">Effective to (UTC)</label>
            <input class="form-control" type="date" id="effective_to"
                   name="effective_to" value="{{ old('effective_to') }}">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4 form-group">
            <label for="refine_efficiency">Refine efficiency</label>
            <input class="form-control" type="number" min="0.000001" max="1" step="0.000001"
                   id="refine_efficiency" name="refine_efficiency"
                   value="{{ old('refine_efficiency', '0.9063') }}" required>
          </div>
          <div class="col-md-4 form-group">
            <label>Price source</label>
            <input class="form-control" value="EVE average (SeAT market_prices.average_price)" disabled>
          </div>
        </div>

        <h5>Tax rates</h5>
        <div class="row">
          @php
            $rateDefaults = [
              'mineral' => '0.10',
              'ice' => '0.10',
              'R4' => '0.10',
              'R8' => '0.10',
              'R16' => '0.10',
              'R32' => '0.10',
              'R64' => '0.20',
              'ratting' => '0.08',
            ];
          @endphp
          @foreach ($rateDefaults as $taxClass => $default)
            <div class="col-md-3 form-group">
              <label for="rate_{{ $taxClass }}">{{ $taxClass }}</label>
              <input class="form-control" type="number" min="0" max="1" step="0.000001"
                     id="rate_{{ $taxClass }}" name="rate_{{ $taxClass }}"
                     value="{{ old('rate_' . $taxClass, $default) }}" required>
            </div>
          @endforeach
        </div>

        <h5>Accounting scope</h5>
        <p class="text-muted">
          Enter IDs separated by commas, spaces or semicolons. Empty mineral regions disable
          ordinary ore/ice. Empty moon holding corporations disable the moon source.
        </p>

        <div class="row">
          <div class="col-md-6 form-group">
            <label for="alliance_ids">Alliance IDs</label>
            <input class="form-control" id="alliance_ids" name="alliance_ids"
                   value="{{ old('alliance_ids') }}" required>
          </div>
          <div class="col-md-6 form-group">
            <label for="mining_holding_corporation_ids">Moon holding corporation IDs</label>
            <input class="form-control" id="mining_holding_corporation_ids"
                   name="mining_holding_corporation_ids"
                   value="{{ old('mining_holding_corporation_ids') }}">
          </div>
          <div class="col-md-6 form-group">
            <label for="mineral_region_ids">Ordinary mining region IDs</label>
            <input class="form-control" id="mineral_region_ids" name="mineral_region_ids"
                   value="{{ old('mineral_region_ids') }}">
          </div>
          <div class="col-md-6 form-group">
            <label for="excluded_corporation_ids">Corporations excluded from all taxes</label>
            <input class="form-control" id="excluded_corporation_ids"
                   name="excluded_corporation_ids"
                   value="{{ old('excluded_corporation_ids') }}">
          </div>
          <div class="col-md-6 form-group">
            <label for="wallet_excluded_corporation_ids">Additional corporations excluded from PvE/wallet tax</label>
            <input class="form-control" id="wallet_excluded_corporation_ids"
                   name="wallet_excluded_corporation_ids"
                   value="{{ old('wallet_excluded_corporation_ids') }}">
          </div>
        </div>

        <button class="btn btn-primary" type="submit">Create rule set</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Existing rule sets</h3>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name / period</th>
              <th>Rates</th>
              <th>Scope</th>
              <th>Close period</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rule_sets as $ruleSet)
              @php
                $rates = $ruleSet->rates->pluck('rate', 'tax_class');
                $settings = $ruleSet->settings ?? [];
              @endphp
              <tr>
                <td>{{ $ruleSet->id }}</td>
                <td>
                  <strong>{{ $ruleSet->name }}</strong><br>
                  {{ $ruleSet->effective_from->format('Y-m-d') }}
                  —
                  {{ $ruleSet->effective_to ? $ruleSet->effective_to->format('Y-m-d') : 'open' }}<br>
                  <small>
                    refine {{ $ruleSet->refine_efficiency }},
                    {{ $ruleSet->price_source }}
                  </small>
                </td>
                <td>
                  @foreach ($tax_classes as $taxClass)
                    <span class="badge badge-secondary">
                      {{ $taxClass }} {{ number_format(((float) ($rates[$taxClass] ?? 0)) * 100, 2) }}%
                    </span>
                  @endforeach
                </td>
                <td>
                  Alliances: {{ implode(', ', $settings['alliance_ids'] ?? []) ?: '—' }}<br>
                  Moon holders: {{ implode(', ', $settings['mining_holding_corporation_ids'] ?? []) ?: '—' }}<br>
                  Mining regions: {{ implode(', ', $settings['mineral_region_ids'] ?? []) ?: '—' }}<br>
                  Excluded: {{ implode(', ', $settings['excluded_corporation_ids'] ?? []) ?: '—' }}
                </td>
                <td>
                  @if (! $ruleSet->effective_to)
                    <form method="POST" action="{{ route('taxes.rules.close', ['ruleSet' => $ruleSet->id]) }}">
                      @csrf
                      <div class="input-group input-group-sm">
                        <input class="form-control" type="date" name="effective_to" required>
                        <div class="input-group-append">
                          <button class="btn btn-outline-warning" type="submit">Close</button>
                        </div>
                      </div>
                    </form>
                  @else
                    <span class="text-muted">Closed</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-muted p-4">
                  No rule sets yet.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
@stop
