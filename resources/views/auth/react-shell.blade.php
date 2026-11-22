@extends('layouts.reactframework')
@section('bodycontents')
@vite(['resources/js/apps/core/componentRegistry.jsx', 'resources/js/apps/core/bootstrap.jsx'])
<auth-app data-props="{{ json_encode($props ?? []) }}"></auth-app>
@endsection


