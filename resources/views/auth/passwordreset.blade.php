@extends('layouts.framework')
@section('bodycontents')
<div class="bg-light min-vh-100 d-flex flex-row align-items-center bg-light passwordreset">
   <div class="container mx-1">
      <div class="row justify-content-center">
         <div class="col-lg-8">
            <form method="POST" action="{{ route(( Request::has('email') && Request::has('token')) ? 'password.update' : 'password.email') }}">
               <input type="hidden" name="_token" value="{{ csrf_token() }}" />
               <div class="card-group d-block d-md-flex row">
                  <div class="card col-12 p-0 mb-0" style="border-radius: 12px;">
                     <div class="card-header header" style="border-radius: 12px 12px 0 0;">
                        <div class="container d-block text-center">
                           <img alt="Logo" src="@php echo(file_exists(resource_path().'/brand/logo/logo_passwordreset.png') ? 'data:image/png;base64,'.base64_encode(file_get_contents(resource_path().'/brand/logo/logo_passwordreset.png')) : '/images/logo_'.app()->getLocale().'_white.png'); @endphp">
                        </div>
                     </div>
                     <div class="card-body p-4">
                     @if(isset($errors) && count($errors) > 0)
                        <div class="alert alert-warning" role="alert">
                           <ul class="list-unstyled mb-0">
                              @foreach($errors->all() as $error)
                                 <li>{{ $error }}</li>
                              @endforeach
                           </ul>
                        </div>
                     @endif
                     @if(session('status'))
                        <div class="alert alert-info" role="alert">
                           <ul class="list-unstyled mb-0">
                           {{ session('status') }}
                           </ul>
                        </div>
                     @endif
                     @if( Request::has('email') && Request::has('token'))
                     <input type="hidden" name="email" value="{{ Request::get('email') }}" />
                     <input type="hidden" name="token" value="{{ Request::get('token') }}" />
                     <div class="input-group mb-4">
                        <span class="input-group-text input-rounded">
                           <span class="material-symbols-rounded">lock</span>
                        </span>
                        <input class="form-control input-rounded" name="password" type="password" placeholder="{{ __('auth.reset_password.new_password') }}" required>
                     </div>
                     <div class="input-group mb-4">
                        <span class="input-group-text input-rounded">
                           <span class="material-symbols-rounded">lock</span>
                        </span>
                        <input class="form-control input-rounded" name="password_confirmation" type="password" placeholder="{{ __('auth.reset_password.confirm_password') }}" required>
                     </div>

                     @else
                        <div class="input-group mb-3">
                           <span class="input-group-text input-rounded">
                              <span class="material-symbols-rounded">mail</span>
                           </span>
                           <input class="form-control input-rounded" name="email" type="email" placeholder="{{ __('auth.login.email') }}" required>
                        </div>
                     @endif
                     <div class="row">
                        <div class="col-12">
                           <button class="btn btn-outline-dark px-4" type="submit">
                           <span class="material-symbols-rounded">lock_reset</span>
                           {{ __('auth.forgot_password.heading') }}
                           </button>
                        </div>
                     </div>
                  </div>
               </div>
            </div>
         </form>
      </div>
   </div>
</div>
</div>

@endsection
