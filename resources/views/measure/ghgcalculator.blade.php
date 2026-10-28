@php if(Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\GhgFactor::class)) abort(403); @endphp
@extends('layouts.master')
@section('container')

<ghg-calculator />

@endsection
