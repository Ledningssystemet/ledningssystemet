@php if(Auth::user()->cannot('index', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class)) abort(403); @endphp
@extends('layouts.master')

@section('container')
@php
   $userOptions = \Ledningssystemet\Ledningssystemet\Models\User::orderBy('name')->get()
      ->each->setAppends([])
      ->map(fn ($user) => ['value' => (string) $user->id, 'label' => $user->name])
      ->values();
   $tableProps = [
      'data' => [],
      'config' => [
         'listTitle' => __('API tokens'),
         'tableId' => 'tokenslist',
         'titleKey' => 'name',
         'subtitleKeys' => [],
         'defaultView' => 'table',
         'editMode' => 'inline',
         'perPage' => 10,
         'searchKeys' => ['name'],
         'dataSource' => [
            'baseUrl' => '/api/v1/crud',
            'model' => 'PersonalAccessToken',
            'sort' => ['name'],
            'headers' => ['X-CSRF-TOKEN' => csrf_token()],
         ],
         'permissions' => [
            'create' => false,
            'update' => Auth::user()->can('update', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class),
            'delete' => Auth::user()->can('delete', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class),
         ],
         'columns' => [
            ['key' => 'name', 'label' => __('Name'), 'form' => 'both', 'required' => true, 'maxLength' => 255, 'sortable' => true],
            ['key' => 'user_id', 'label' => __('User'), 'type' => 'select', 'form' => 'create', 'options' => $userOptions],
         ],
         'tableColumns' => ['name', 'user_id'],
         'detailSections' => [],
         'filters' => [],
         'newItemDefaults' => [],
      ],
   ];
@endphp
<a class="btn btn-outline-secondary mt-3 me-2 mb-3" href="/systemadmin/swagger">{{ __("To the API documentation") }}</a>

@if(Auth::user()->can('create', \Ledningssystemet\Ledningssystemet\Models\PersonalAccessToken::class))
@php
   $tokenWarning = __('A new token has been created, and it will be displayed only this time. A new token needs to be created if this gets lost.');
   $tokenError = __('Due to an unknown reason, a requested action failed');
@endphp
<button id="create-api-token" type="button" class="btn btn-primary text-white">{{ __('Create API token') }}</button>
<script>
document.getElementById('create-api-token').addEventListener('click', function () {
   const name = $('<input />').attr({ type: 'text', maxlength: 255, id: 'api-token-name' }).addClass('form-control');
   const user = $('<select />').attr('id', 'api-token-user').addClass('form-select')
      .append($('<option />').val('').text('-- ' + @json(__('User')) + ' --'));
   @json($userOptions).forEach(function (option) {
      user.append($('<option />').val(option.value).text(option.label));
   });
   const error = $('<p />').addClass('text-danger').attr('role', 'alert').hide();
   const content = $('<div />')
      .append($('<label />').attr('for', 'api-token-name').addClass('form-label').text(@json(__('Name'))), name)
      .append($('<label />').attr('for', 'api-token-user').addClass('form-label mt-3').text(@json(__('User'))), user, error);
   let pending = false;
   let created = false;
   let result = null;

   showDialog(@json(__('Create API token')), content, {
      actionLabel: @json(__('Create')),
      beforeClose: function () { return pending; },
      onAction: function (data, dialog) {
         if (pending) return true;
         if (!name.val().trim() || !user.val()) {
            error.text(@json(__('Required'))).show();
            return true;
         }
         error.hide();
         pending = true;
         dialog.find('.action-button').prop('disabled', true);
         const url = '/api/v1/items/User/' + encodeURIComponent(user.val()) + '/issuetoken';
         const complete = function (event, xhr, settings) {
            if (settings.type !== 'POST' || settings.url.split('?')[0] !== url) return;
            $(document).off('ajaxComplete', complete);
            if (pending) {
               pending = false;
               dialog.find('.action-button').prop('disabled', false);
            }
         };
         $(document).on('ajaxComplete', complete);
         ajaxPost(url, { name: name.val() }, function (response) {
            result = response;
            created = true;
            pending = false;
            dialog.find('.dismiss-button').first().trigger('click');
         });
         return true;
      },
      afterClose: function () {
         if (!created) return;
         const message = $('<div />');
         if (result && typeof result.plainTextToken === 'string' && result.plainTextToken) {
            const copyStatus = $('<span />').attr('aria-live', 'polite').addClass('ms-2');
            const copyButton = $('<button />').attr('type', 'button').addClass('btn btn-outline-secondary mt-2').text(@json(__('Copy')));
            copyButton.on('click', function () {
               if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                  copyStatus.removeClass('text-success').addClass('text-danger').text(@json(__('Unable to copy to clipboard')));
                  return;
               }
               navigator.clipboard.writeText(result.plainTextToken).then(function () {
                  copyStatus.removeClass('text-danger').addClass('text-success').text(@json(__('Copied to clipboard')));
               }).catch(function () {
                  copyStatus.removeClass('text-success').addClass('text-danger').text(@json(__('Unable to copy to clipboard')));
               });
            });
            message.text(@json($tokenWarning))
               .append($('<div />').text(result.plainTextToken).css({ 'font-weight': 'bold', 'margin-top': '20px', 'overflow-wrap': 'anywhere' }))
               .append(copyButton, copyStatus);
         } else {
            message.text(@json($tokenError));
         }
         showDialog(@json(__('API token')), message, { afterClose: function () { window.location.reload(); }, modalClass: 'modal-xl' });
      },
   });
});
</script>
@endif
<table-module data-props="{{ json_encode($tableProps) }}"></table-module>
@endsection
