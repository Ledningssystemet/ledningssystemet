@extends('layouts.reactframework')
@section('bodycontents')
@vite(['resources/js/components/core/componentRegistry.jsx', 'resources/js/components/core/bootstrap.jsx'])
<auth-app data-props="{{ json_encode($props ?? []) }}"></auth-app>
@endsection


