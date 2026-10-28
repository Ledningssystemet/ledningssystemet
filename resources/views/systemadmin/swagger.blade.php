<?php
if(!auth()->user()->canAny(['systemadministrator.edit']))
   abort(403);
?>

@extends('layouts.master')
@section('container')
<link rel="stylesheet" href="/vendors/swagger/swagger-ui.css" />
<script src="/vendors/swagger/swagger-ui-bundle.js" crossorigin></script>
<script src="/vendors/swagger/swagger-ui-standalone-preset.js" crossorigin></script>

<script>
$(function(){
   SwaggerUIBundle({
      url: @json(route('api.v1.swagger', [], false)),
      dom_id: '#swaggerContainer',
   });
});
</script>
<div id="swaggerContainer"></div>

@endsection
