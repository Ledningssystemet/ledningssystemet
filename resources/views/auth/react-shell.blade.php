@extends('layouts.framework')
@section('bodycontents')
<auth-app data-props="{{ json_encode($props ?? []) }}"></auth-app>
@endsection
