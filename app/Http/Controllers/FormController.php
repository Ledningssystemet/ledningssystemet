<?php

namespace App\Http\Controllers;
use App\Models\Form;


class FormController extends Controller
{
   protected ?Form $form;

   public function __construct(Form $form = null)
   {
      $this->form = $form;
   }

   public function upload(){
      // Validate correct state
      if(($this->form->getStateAttribute() != 'draft') && ($this->form->getStateAttribute() != 'answered'))
         abort(400, __("Cannot upload this form since it is not in draft mode or answered mode"));

      // Collect e-mail addresses of relations
      $rcpts = $this->form->int_relations()->pluck('email');

      // Remove any duplicates
      $rcpts = $rcpts->unique();

      // Remove any empty addresses
      $rcpts = $rcpts->filter(function($item){ return "" != $item; })->map(function($item){ return ['email' => $item]; });

      if(0 == count($rcpts))
         abort(400, __("Cannot upload this form since it has no relations to involve"));

      // Ensure that there is any formdata configured
      if(!$this->form->formdata)
         abort(400, __("Cannot upload this form since it has no formdata configured"));

      // Convert json to array
      $formdata = json_decode($this->form->formdata, true);
      if(JSON_ERROR_NONE !== json_last_error())
         abort(400, __("Cannot upload this form since the formdata is not valid"));

      $formdata = array_merge($formdata, [
         'name' => $this->form->name,
         'company_name' => config('ledningssystemet.company_name'),
         'description' => '',
         'users' => $rcpts->toArray(),
         'due' => $this->form->due,
      ]);

      $creationdata = FormController::exchangeProxyRequest('POST', '/ledningssystemet/forms', ['form' => $formdata]);
      $this->form->server_form_id = $creationdata['id'];
      $this->form->uploaded_at = now();
      $this->form->downloaded_at = null;
      $this->form->save();
   }

   public function recall(){
      // Validate correct state
      if(($this->form->uploaded_at == null) || ($this->form->downloaded_at != null))
          abort(400, __("Cannot recall form since it has not been uploaded yet"));

      FormController::exchangeProxyRequest('DELETE', '/ledningssystemet/forms/' . $this->form->server_form_id);
      $this->form->server_form_id = null;
      $this->form->uploaded_at = null;
      $this->form->save();
   }

   public static function syncForms(){
      // Fetch all forms from the external proxy in order to ensure that the state parameter is correct
      $formdata = FormController::exchangeProxyRequest('GET', '/ledningssystemet/forms');

      // Loop through all local forms in order to make sure that we do not have forms locally that the external proxy does not know about
      foreach(Form::whereNotNull('uploaded_at')->whereNull('downloaded_at')->get() as $localform){
         $found = false;
         foreach($formdata as $externalform){
            if($externalform['id'] == $localform->server_form_id)
            {
               $found = true;
               break;
            }
         }

         if(!$found)
         {
            // Log invalid state
            \Log::error('Form ' . $localform->id . ' was marked as transmitted to server (state: '.$localform->getStateAttribute().') but could not be found in the external proxy');

            // Update state to draft mode
            $localform->uploaded_at = null;
            $localform->archived_at = null;
            $localform->finished_at = null;
            $localform->downloaded_at = null;
            $localform->finished_by = null;
            $localform->server_form_id = null;
            $localform->save();
         }
      }

      // Loop through all proxy server forms to ensure that we have all forms locally, otherwise they shall be deleted
      foreach($formdata as $externalform){
         $localform = Form::where('server_form_id', $externalform['id'])->first();
         if(null == $localform)
         {
            // Log invalid state
            \Log::error('Form ' . $externalform['id'] . ' was found in the external proxy but could not be found locally. It will be deleted from the external proxy');

            // Delete form from external proxy
            FormController::exchangeProxyRequest('DELETE', '/ledningssystemet/forms/' . $externalform['id']);
         }
      }

      // Check if there are any finished forms that shall be downloaded, or where users shall be synchronized
      foreach($formdata as $externalform){
         if($externalform['finished_at'] != null)
         {
            $form = FormController::exchangeProxyRequest('GET', '/ledningssystemet/forms/' . $externalform['id']);

            // Ensure there is formdata available
            if(!$form['form'])
               abort(400, __("Cannot download form since the formdata is not available"));

            $form = $form['form'];

            // Update local data
            $formModel = Form::where('server_form_id', $form['id'])->first();
            if(null == $formModel)
            {
               \Log::error('Form ' . $form['id'] . ' was marked as finished but could not be found locally. Data: ' . json_encode($form));
            }
            else
            {
               $formdata = json_decode($formModel->formdata, true);
               $formdata['form_chapters'] = $form['form_chapters'];
               $formdata['form_items'] = $form['form_items'];
               $formModel->formdata = json_encode($formdata);
               $formModel->downloaded_at = now();
               $formModel->finished_by = $externalform['finished_by'];
               $formModel->finished_at = $externalform['finished_at'];
               $formModel->save();
            }

            // Delete remote data
            FormController::exchangeProxyRequest('DELETE', '/ledningssystemet/forms/' . $form['id']);

         }
         else{
            $formModel = Form::where('server_form_id', $externalform['id'])->first();
            if(null == $formModel)
            {
               \Log::error('Form ' . $externalform['id'] . ' could not be found locally. Data: ' . json_encode($externalform));
            }

            $users = $externalform['users'];
            $formModelUsers = $formModel->int_relations()->pluck('email')->toArray();

            // Diff the two arrays
            $diff = array_merge(array_diff($formModelUsers, $users), array_diff($users, $formModelUsers));
            if(count($diff))
            {
               // Post new user configuration to proxy
               FormController::exchangeProxyRequest('POST', '/ledningssystemet/forms/' . $externalform['id'] . '/users', ['users' => $formModelUsers]);
            }
         }
      }
   }

   // This function performs REST-calls to an external form proxy
   private static function exchangeProxyRequest($method, $url, $data = [], $headers = []){
      $server = config('ledningssystemet.exchangeproxy_url');
      $bearerToken = config('ledningssystemet.exchangeproxy_token');

      // Validate that server and token are set
      if(null == $server || null == $bearerToken)
         throw new \Exception("Exchange proxy is not configured");

      // Add bearer token to header
      $headers['Authorization'] = 'Bearer ' . $bearerToken;
      $headers['Accept'] = 'application/json';
      $headers['Content-Type'] = 'application/json';
      $headers['X-Requested-With'] = 'XMLHttpRequest';

      $client = new \GuzzleHttp\Client();
      $requestOptions = [
         'headers' => $headers,
      ];

      if(strtoupper($method) === 'GET') {
         if(!empty($data)) {
            $requestOptions['query'] = $data;
         }
      } else {
         $requestOptions['json'] = $data;
      }

      $response = $client->request($method, $server . $url, $requestOptions);
      // Make sure we got a 2XX response
      $statuscode = "".$response->getStatusCode();
      if(substr($statuscode, 0, 1) != '2')
         throw new \Exception("Exchange proxy returned an error: " . $response->getBody());

      $returnValue = $response->getBody();
      if(null == $returnValue)
         $returnValue = [];

      return json_decode($returnValue, true);
   }

}
