<?php if(auth()->user()->cannot('update', $template)) abort(403); ?>
<?php
$formdataRaw = $template->formdata ?? '{}';
$formdataParsed = json_decode($formdataRaw, true) ?? [];
if (!isset($formdataParsed['form_chapters']) || !is_array($formdataParsed['form_chapters'])) $formdataParsed['form_chapters'] = [];
if (!isset($formdataParsed['form_items']) || !is_array($formdataParsed['form_items']))       $formdataParsed['form_items'] = [];
?>
@extends('layouts.master')
@section('container')

<form-builder data-props="{{ json_encode([
    'templateId'      => $template->id,
    'templateName'    => $template->name,
    'initialFormdata' => $formdataParsed,
]) }}"></form-builder>

@endsection
