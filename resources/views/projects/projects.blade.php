@php
    $user = Auth::user();
    if ($user->cannot('viewAny', \Ledningssystemet\Ledningssystemet\Models\Project::class)) abort(403);
    $projectPermissions = [
        'currentUserId' => $user->id,
        'canCreateProject' => $user->can('create', \Ledningssystemet\Ledningssystemet\Models\Project::class),
    ];
@endphp
@extends('layouts.master')

@section('container')
<projects-module data-props='@json($projectPermissions)'></projects-module>
@endsection
