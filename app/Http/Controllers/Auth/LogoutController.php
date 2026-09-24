<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class LogoutController extends Controller
{
   /**
    * Log out account user.
    *
    * @return \Illuminate\Routing\Redirector
    */
   public function perform()
   {
      // Get login mechanism
      $authmechanism = session('auth_mechanism');

      // Flush session and logout user
      Session::flush();
      Auth::logout();

      // If SSO - redirect to logout
      if((null !== $authmechanism) &&
         (str_starts_with($authmechanism, 'SSO_')))
         return redirect(SsoController::logoutRedirectUrl(route('login')));
      
      // Default: Redirect to login prompt
      return redirect('login');
   }
}
