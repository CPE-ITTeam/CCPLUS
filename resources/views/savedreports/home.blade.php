@extends('layouts.app')
@section('content')
@if (sizeof($system_alerts) > 0)
    @foreach ($system_alerts as $alert)
      <div class="alert alert-{{ $alert->severity->name }}">
        System Alert ::<strong>{{ $alert->severity->name }}</strong> :: {{ $alert->text }}
      </div>
    @endforeach
@endif
<v-main>
  <h1>{{ auth()->user()->name }}'s dashboard</h1>
  <h3 class="component-subhead">{{ $inst_count }} institution(s) and {{ $prov_count }} provider(s) connected</h3>
  @if (sizeof($data_alerts) > 0)
    <div class="dashboard-section">
      <alert-summary-table :alerts="{{ json_encode($data_alerts) }}"></alert-summary-table>
    </div>
  @endif
	<div class="dashboard-section">
    <h3>Recent Activity</h3>
    <harvestlog-summary-table :harvests="{{ json_encode($harvests) }}"></harvestlog-summary-table>
  </div>
</v-main>
@endsection
