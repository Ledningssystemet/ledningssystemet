<?php
   $announcements = Cache::rememberForever('announcements', function() {
      return \App\Models\Announcement::where('visible_until', '>=', date("Y-m-d"))->orderBy('created_at', 'desc')->get();
   });
$tabcolor = 'text-success';
foreach($announcements as $obj)
{
   switch($obj->severity)
   {
      case 'danger':
         $tabcolor = 'text-danger';
         break;
      case 'warning':
         if($tabcolor != 'text-danger')
            $tabcolor = 'text-danger';
         break;
   }
}

?>
@if(count($announcements))
<div class="announcements announcements-hidden">
   <div class="announcements-content row">
@foreach($announcements as $obj)
      <div class="announcement severity-{{ $obj->severity }}">
         <div class="announcement-header">
            <span class="material-symbols-rounded">campaign</span>
            <span>{{ $obj->header }} (@php echo(date("Y-m-d", strtotime($obj->created_at))); @endphp)</span>
         </div>
         <div class="announcement-description">{{ $obj->description }}</div>
      </div>
@endforeach   
   </div>
   <div class="align-top announcements-tab" onClick="$('.announcements').data('menuwashidden', $('#sidebar').hasClass('hide'));$('#sidebar').addClass('hide');$('.announcements').removeClass('announcements-hidden');">
      <span class="material-symbols-rounded {{ $tabcolor }}">campaign</span>
   </div>
   <div class="align-top announcements-close" onClick="$('.announcements').addClass('announcements-hidden');if(!$('.announcements').data('menuwashidden')) $('#sidebar').removeClass('hide');">
      <span class="material-symbols-rounded">keyboard_arrow_up</span>
   </div>
</div>
@endif
