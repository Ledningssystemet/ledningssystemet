@extends('layouts.master')

@section('container')
   <h3>{{ __('Function settings') }}</h3>

   @if(session('status'))
      <div class="alert alert-success" role="status">{{ session('status') }}</div>
   @endif

@php
   $moduleSettings = collect(\Ledningssystemet\Ledningssystemet\Support\Modules::LABELS)
      ->map(fn ($label, $module) => [
         'key' => $module,
         'label' => __($label),
         'enabled' => (bool) $modules[$module],
         'action' => route('systemadmin.settings.modules.update', ['module' => $module]),
      ])
      ->values();
   $contactFields = collect(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::CONTACT_FIELDS)
      ->map(fn ($label, $key) => [
         'key' => $key,
         'label' => __($label),
         'email' => $key === 'data_privacy_contact_email',
      ])
      ->values();
   $assessmentFields = collect(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::ASSESSMENT_FIELDS)
      ->map(fn ($label, $key) => ['key' => $key, 'label' => __($label)])
      ->values();
@endphp
<system-settings-form data-props="{{ json_encode([
   'action' => route('systemadmin.settings.update'),
   'csrfToken' => csrf_token(),
   'modules' => $moduleSettings,
   'contact' => $contact,
   'assessment' => $assessment,
   'contactFields' => $contactFields,
   'assessmentFields' => $assessmentFields,
]) }}"></system-settings-form>
@endsection
