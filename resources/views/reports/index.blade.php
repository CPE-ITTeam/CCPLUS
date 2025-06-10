@extends('layouts.app')
@section('page_title')
    {{ "Reports -- CC-Plus" }}
@endsection
@section('content')
<reports :reports="{{ json_encode($report_data) }}"
         :counter_reports="{{ json_encode($counter_reports) }}"
         :filters="{{ json_encode($filters) }}"
         :conso="{{ json_encode($conso) }}"
         :fy_month="{{ json_encode($fy_month) }}"
></reports>
@endsection
