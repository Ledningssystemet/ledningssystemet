@extends('layouts.framework')
@section('bodycontents')
      <div class="wrapper d-flex flex-column min-vh-100 bg-light dark:bg-transparent">
         <!-- Header -->
         @include('layouts.header')
         
         <!-- Sidebar -->
         @include('layouts.sidebar')
         
         <!-- Container -->
         <div class="body flex-grow-1 px-3">
           <div class="container-lg">
               @section('container')
               @show
           </div>
         </div>
         
         <!-- Footer -->
         @include('layouts.footer')

         <!-- AI chat widget -->
         @include('layouts.aichat')

      </div>      
@endsection
