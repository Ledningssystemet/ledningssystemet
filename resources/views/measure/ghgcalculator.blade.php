@php if(Auth::user()->cannot('index', \App\Models\GhgFactor::class)) abort(403); @endphp
@extends('layouts.master')
@section('container')

<ghg-calculator />

@endsection
