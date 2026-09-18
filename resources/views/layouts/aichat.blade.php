@if(auth()->user()->can('useai') && config('ledningssystemet.ai_chat_enabled'))
<div id="ai-chat-widget" data-enabled="1" data-csrf-token="{{ csrf_token() }}">
   <button class="ai-chat-toggle ai-chat-widget-icon" type="button" aria-expanded="false" aria-controls="ai-chat-panel">
      <span class="material-symbols-rounded">smart_toy</span>
   </button>

   <section id="ai-chat-panel" class="ai-chat-panel" hidden>
      <div class="ai-chat-header">
         <div class="ai-chat-header-icon">
            <span class="material-symbols-rounded">smart_toy</span>
         </div>
         <div class="ai-chat-header-title-container">
            <span class="ai-chat-header-title">{{ config('ledningssystemet.ai_chat_name', __('AI assistant')) }}</span>
            <span class="ai-chat-header-status-text ai-chat-header-status-online">{{ __('Online') }}</span>
            <span class="ai-chat-header-status-text ai-chat-header-status-processing">{{ __('Processing') }}</span>
         </div>
         <button class="ai-chat-toggle ai-chat-close-button" type="button" aria-expanded="false" aria-controls="ai-chat-panel">
            <span class="material-symbols-rounded">close</span>
         </button>
      </div>
      <div id="ai-chat-messages" class="ai-chat-messages"></div>
      <div class="ai-chat-input-row">
         <small class="ai-chat-hint">{{ __('Each question is handled separately.') }} {{ __('Add context from previous answers if needed.') }}</small>
         <textarea id="ai-chat-input" class="form-control" rows="2" placeholder="{{ __('Write your question') }}"></textarea>
         <div class="ai-chat-actions">
            <button id="ai-chat-clear" class="ai-chat-clear-button" type="button"><span class="material-symbols-rounded">delete_history</span></button>
            <button id="ai-chat-stop" class="ai-chat-stop-button" type="button" disabled><span class="material-symbols-rounded">stop</span></button>
            <button id="ai-chat-send" class="ai-chat-send-button" type="button"><span class="material-symbols-rounded">send</span></button>
         </div>
      </div>
   </section>
</div>
@endif

