<?php
if (Request::isMethod('post'))
{
   
   auth()->user()->setUserCommunicationPreferences(array(
      'monday' => 1 == request()->input('monday', 0),
      'tuesday' => 1 == request()->input('tuesday', 0),
      'wednesday' => 1 == request()->input('wednesday', 0),
      'thursday' => 1 == request()->input('thursday', 0),
      'friday' => 1 == request()->input('friday', 0),
      'saturday' => 1 == request()->input('saturday', 0),
      'sunday' => 1 == request()->input('sunday', 0),
   ));
}
?>
@extends('layouts.master')

@section('container')

   <style type="text/css">
      .info {
         font-size: 0.8em;
         font-style: italic;
         margin-bottom: 20px;
         margin-top: 20px;
      }
   </style>
   <script>
   $(function(){
      $('#notificationChannelsTable').jtable({
         title: '{{ __("Notification channels") }}',
         tableId: 'notificationstable',
         bootstrap: true,
         accordion: true,
         messages: {
            addNewRecord: '{{ __('Add new channel') }}',
         },
         actions: {
            listAction: '/api/v1/items/UserNotificationChannel?user_id={{ auth()->user()->id }}',
            createAction: '/api/v1/items/UserNotificationChannel?user_id={{ auth()->user()->id }}',
            updateAction: '/api/v1/items/UserNotificationChannel',
            deleteAction: '/api/v1/items/UserNotificationChannel',
         },
         fields: {
            id: {
               key: true,
               list: false,
               create: false,
               edit: false,
            },
            name: {
               title: '{{ __('Name') }}',
               create: true,
               edit: true,
               list: true,
               required: true,
               maxlength: 255,
               header: true,
            },
            ignoremine: {
               title: '{{ __('Notify me when I am doing the changes') }}',
               create: true,
               edit: true,
               list: true,
               defaultValue: true,
               options: [
                  { Value: 1, DisplayText: '{{ __("No") }}' },
                  { Value: 0, DisplayText: '{{ __("Yes") }}' },
               ]
            },
            channelscopes: {
               title: '{{ __('Object scopes') }}',
               create: true,
               edit: true,
               list: true,
               multiple: true,
               options: [
@foreach(\App\Models\UserNotificationChannel::availableScopes() as $obj)
                     { Value:  @php echo(json_encode($obj['key'])); @endphp, DisplayText: @php echo(json_encode($obj['text'])); @endphp },
@endforeach
               ]
            },
            channelevents: {
               title: '{{ __('Events') }}',
               create: true,
               edit: true,
               list: true,
               multiple: true,
               options: [
@foreach(\App\Models\UserNotificationChannel::availableEvents() as $obj)
                     { Value:  @php echo(json_encode($obj['key'])); @endphp, DisplayText: @php echo(json_encode($obj['text'])); @endphp },
@endforeach
               ]
            }
         },
      });
      $('#notificationChannelsTable').jtable('load');
   });   
   </script>
   <h3>{{ __("E-mail notification settings") }}</h3>
<?php
   $userpreferences = auth()->user()->getUserCommunicationPreferences();
?>   
   <div class="notifications container row">
      <div class="d-inline-block container col-12 col-6">
         <form method="POST">
            <input type="hidden" name="_token" value="{{ csrf_token() }}" />
            <div class="info">{{ __("These settings adjust how often you want to get status overviews from the system") }}</div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="monday" id="flexCheckMonday" <?php echo($userpreferences['monday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckMonday">
              {{ __("Monday") }}
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="tuesday" id="flexCheckTuesday" <?php echo($userpreferences['tuesday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckTuesday">
              {{ __("Tuesday") }}
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="wednesday" id="flexCheckWednesday" <?php echo($userpreferences['wednesday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckWednesday">
              {{ __("Wednesday") }}
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="thursday" id="flexCheckThursday" <?php echo($userpreferences['thursday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckThursday">
              {{ __("Thursday") }}
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="friday" id="flexCheckFriday" <?php echo($userpreferences['friday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckFriday">
              {{ __("Friday") }}
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="saturday" id="flexCheckSaturday" <?php echo($userpreferences['saturday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckSaturday">
              {{ __("Saturday") }}
              </label>
            </div>
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" value="1" name="sunday" id="flexCheckSunday" <?php echo($userpreferences['sunday'] ? "checked" : ""); ?> />
              <label class="form-check-label" for="flexCheckSunday">
              {{ __("Sunday") }}
              </label>
            </div>
            <input class="btn btn-primary btn-sm text-light savebutton mt-4 mb-4" type="submit" value="{{ __('Save') }}" />
         </form>
      </div>
      <div id="notificationChannelsTable" class="d-inline-block container col-12 mt-4"></div>
   </div>
   
@endsection
