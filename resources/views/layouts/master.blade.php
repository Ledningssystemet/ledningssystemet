@extends('layouts.framework')
@section('bodycontents')
      <div class="wrapper d-flex flex-column min-vh-100 bg-light dark:bg-transparent">
         <div data-ui-slot="layout.before-header"></div>
         <!-- Header -->
         @include('layouts.header')

         <!-- Sidebar -->
         @include('layouts.sidebar')

         <div data-ui-slot="layout.after-sidebar"></div>

         <!-- Container -->
         <div class="body flex-grow-1 px-3">
           <div class="container-lg">
               @section('container')
               @show
           </div>
         </div>

         <div data-ui-slot="layout.before-footer"></div>

         <!-- Footer -->
         @include('layouts.footer')

         <div data-ui-slot="layout.after-footer"></div>

         <!-- AI chat widget -->
         @include('layouts.aichat')

         <div data-ui-slot="layout.end"></div>
      </div>      
@endsection
