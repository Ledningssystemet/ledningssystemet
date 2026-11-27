@php
if (!auth()->user()->canAny(['systemadministrator.edit'])) abort(403);
$props = [
   'page' => 'swagger',
   'specUrl' => route('api.v1.swagger', [], false),
];
@endphp
@extends('layouts.master')

@section('container')
@include('systemadmin.app')
@endsection
