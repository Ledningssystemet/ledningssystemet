@extends('layouts.master')

@section('container')
   <h3>{{ __('General settings') }}</h3>

   @if(session('status'))
      <div class="alert alert-success" role="status">{{ session('status') }}</div>
   @endif
   @if($errors->any())
      <div class="alert alert-danger" role="alert">
         @foreach($errors->all() as $error)
            <div>{{ $error }}</div>
         @endforeach
      </div>
   @endif

   <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
      <li class="nav-item" role="presentation">
         <button class="nav-link active" id="features-tab" data-bs-toggle="tab" data-bs-target="#features-panel"
                 type="button" role="tab" aria-controls="features-panel" aria-selected="true">{{ __('Features') }}</button>
      </li>
      <li class="nav-item" role="presentation">
         <button class="nav-link" id="privacy-tab" data-bs-toggle="tab" data-bs-target="#privacy-panel"
                 type="button" role="tab" aria-controls="privacy-panel" aria-selected="false">{{ __('Privacy') }}</button>
      </li>
   </ul>

   <form method="POST" action="{{ route('systemadmin.settings.update') }}">
      @csrf
      @method('PUT')
      <div class="tab-content pt-3">
         <div class="tab-pane fade show active" id="features-panel" role="tabpanel" aria-labelledby="features-tab">
            <h4>{{ __('Modules') }}</h4>
            <p>{{ __('Enabled modules apply to all users. Disabling a module does not delete its data.') }}</p>
            @foreach(\Ledningssystemet\Ledningssystemet\Support\Modules::LABELS as $module => $label)
               <div class="form-check form-switch mb-3">
                  <input type="hidden" name="modules[{{ $module }}]" value="0">
                  <input class="form-check-input" type="checkbox" role="switch" id="module-{{ $module }}"
                         name="modules[{{ $module }}]" value="1" @checked(old('modules.'.$module, $modules[$module]))>
                  <label class="form-check-label" for="module-{{ $module }}">{{ __($label) }}</label>
               </div>
            @endforeach
         </div>
         <div class="tab-pane fade" id="privacy-panel" role="tabpanel" aria-labelledby="privacy-tab">
            <h4>{{ __('Data protection contact') }}</h4>
            @foreach(\Ledningssystemet\Ledningssystemet\Support\SystemSettings::CONTACT_FIELDS as $name => $label)
               <div class="mb-3">
                  <label class="form-label" for="{{ $name }}">{{ __($label) }}</label>
                  <input class="form-control" type="{{ $name === 'data_privacy_contact_email' ? 'email' : 'text' }}"
                         id="{{ $name }}" name="{{ $name }}" maxlength="255" value="{{ old($name, $contact[$name]) }}">
               </div>
            @endforeach
         </div>
      </div>
      <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
   </form>
@endsection
