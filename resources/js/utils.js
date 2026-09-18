/* This file contains misc utility functions used system-wide */

/* General event handler to ignore ajax errors because leaving the page */
var globalIsLeavingPage = false;

$( window ).on( "beforeunload", function( ) {
   globalIsLeavingPage = true;
});

/* Ajax calls */
$( document ).on( "ajaxStart", function( ) {
   $('#global-ajax-spinner').addClass('show');
});

$( document ).on( "ajaxStop", function( ) {
   $('#global-ajax-spinner').removeClass('show');
});

$( document ).on( "ajaxError", function( event, response, settings ) {

   // Check if because we are leaving the page, if so ignore
   if(globalIsLeavingPage)
      return;
   
   var errormessage = translateString('Due to an unknown reason, a requested action failed');

   switch(response.status)
   {
      case 401:
         errormessage = translateString('You are no longer authenticated, which typically indicate that you have been logged out due to own actions or that you have been inactive for a long time.');
         break;
      case 403:
         errormessage = translateString('You have requested access to content that you are not allowed to access.');
         break;
      case 404:
         errormessage = translateString('A request was made to an object that could not be found. If the problem remains, please contact the support and report your finding.');
         break;
      default:
         // Try to find the most suitable message to display
         if(response.responseJSON && response.responseJSON.message)
         {
            errormessage = translateString(response.responseJSON.message.toString());
            
            // Catch CSRF token issues
            if(errormessage.includes('CSRF'))
               errormessage = translateString('You are no longer authenticated, which typically indicate that you have been logged out due to own actions or that you have been inactive for a long time.');
           
            // Special case if there are error messages presented
            if(response.responseJSON.errors)
            {
               errormessage = $('<ul />');
               Object.values(response.responseJSON.errors).forEach(errStr => {
                  errormessage.append($('<li />')
                     .text(errStr));
               });
            }
         }
         break;
   }

   showDialog(translateString('Action failed'), errormessage, { warning: true });
   
});

window.ajaxCall = function(options){
   $.extend(options, {
      accepts: { text: 'application/json' },
      dataType: 'json',
      cache: false,
   });
   
   $.ajax(options);
}

window.ajaxGet = function(url, success, dataType = 'json')
{
   ajaxCall({
      method: 'GET',
      url: url,
      data: {},
      success: success,
      dataType: dataType,
   });
}

window.ajaxPost = function(url, data, success, dataType = 'json')
{
   ajaxCall({
      method: 'POST',
      url: url,
      data: data,
      success: success,
      dataType: dataType,
   });
}

window.ajaxPut = function(url, data, success, dataType = 'json')
{
   ajaxCall({
      method: 'PUT',
      url: url,
      data: data,
      success: success,
      dataType: dataType,
   });
}

window.ajaxPatch = function(url, data, success, dataType = 'json')
{
   ajaxCall({
      method: 'PATCH',
      url: url,
      data: data,
      success: success,
      dataType: dataType,
   });
}

window.ajaxDelete = function(url, success)
{
   ajaxCall({
      method: 'DELETE',
      url: url,
      success: success,
   });
}


/* Translate strings using global translate file */
window.translateString = function(origString)
{
   if(('undefined' != typeof window) &&
      ('undefined' != typeof window.translations))
   {
      for(var i = 0; i < window.translations.length; i++)
      {
         if(window.translations[i].in == origString)
            return window.translations[i].out;
      }
   }
   
   return origString;
}

/* Show a message dialog */
window.showDialog=function(title, message, options = {})
{
   // Set dialog options
   var dialogOptions = $.extend({
      dismissLabel: translateString('Close'),
      actionLabel: translateString('Ok'),
      onAction: null,
      closeOnAction: true,
      beforeClose: null,
      afterClose: null,
      beforeFormCreated: null,
      formCreated: null,
      modalClass: null,
      warning: false,
   }, options);
   
   // Create dialog container
   var dialogDiv = $('<div />')
      .addClass('modal'+(dialogOptions.modalClass ? ' '+dialogOptions.modalClass : ''))
      .prop('tabindex', -1)
      .append($('<div />')
         .addClass('modal-dialog modal-dialog-scrollable')
         .prop('role', 'document')
         .append($('<div />')
            .addClass('modal-content')
            .append($('<div />')
               .addClass('modal-header')
               .append($('<h5 />')
                  .addClass('modal-title')
                  .append(title)
               )
               .append($('<button />')
                  .prop('type', 'button')
                  .prop('aria-label', dialogOptions.dismissLabel)
                  .addClass('btn-close dismiss-button')
               )
            )
            .append($('<div />')
               .addClass('modal-body')
               .append(message)
            )
            .append($('<div />')
               .addClass('modal-footer')
               .append($('<button />')
                  .prop('type', 'button')
                  .prop('aria-label', dialogOptions.dismissLabel)
                  .addClass('btn btn-outline-primary dismiss-button')
                  .text(dialogOptions.dismissLabel)
               )
            )
         )
      );
      
      
   if(dialogOptions.warning)
   {
      dialogDiv.addClass('modal-dialog-warning');
      dialogDiv.find('.modal-header').prepend($('<span></span>')
         .addClass('material-symbols-rounded warning-dialog-icon')
         .text('warning'));
   }
   
   // Create secondary button if configured
   if(null != dialogOptions.onAction)
   {
      dialogDiv.find('.modal-footer').prepend(
         $('<button />')
            .prop('type', 'button')
            .prop('aria-label', dialogOptions.actionLabel)
            .addClass('btn btn-primary action-button text-light')
            .text(dialogOptions.actionLabel)
      );
   }
   
   // Bind action button events
   dialogDiv.find('button.action-button').click(function(){
      
      if(null != dialogOptions.onAction)
      {
         // Decode form data
         var formdata = dialogDiv.find('input,select,textarea');
         var data = [];
         
         formdata.each(function(){
            var skipvalue = false;
            var value = $(this).val();
            var typeattr = $(this).attr('type');
            if('checkbox' == typeattr)
               value = $(this).prop('checked');
            else if('radio' == typeattr)
            {
               if($(this).prop('checked'))
                  value = $(this).val();
               else
                  skipvalue = true;
            }
            
            if(!skipvalue && ('undefined' != typeof $(this).attr('name')))
               data.push({name: $(this).attr('name'), value: value});
         });
         
         // If true, then don't close
         if(dialogOptions.onAction(data, dialogDiv))
            return;
      }
      
      // Call before close action
      if(null != dialogOptions.beforeClose)
      {
         // If true, then don't close
         if(dialogOptions.beforeClose(dialogDiv))
            return;
      }
      
      // Close and destroy modal
      dialogModal.hide();
      dialogModal.dispose();
      $(this).closest('.modal-dialog').remove();
      
      // Call after close action
      if(null != dialogOptions.afterClose)
      {
         dialogOptions.afterClose(dialogDiv);
      }
   });
   
   // Bind dismiss button(s)
   dialogDiv.find('button.dismiss-button').click(function(){
      
      // Call before close action
      if(null != dialogOptions.beforeClose)
      {
         // If true, then don't close
         if(dialogOptions.beforeClose(dialogDiv))
            return;
      }
      
      // Close and destroy modal
      dialogModal.hide();
      dialogModal.dispose();
      $(this).closest('.modal-dialog').remove();
      
      // Call after close action
      if(null != dialogOptions.afterClose)
      {
         dialogOptions.afterClose(dialogDiv);
      }
   });   
   
   // Create select2 for selects
   dialogDiv.find('select').each(function(){
      // Check if already select2
      if(undefined == $(this).prop('data-select2-id'))
         $(this).select2({ width: '100%', dropdownParent: dialogDiv });
   });

   // Create modal
   var dialogModal = new bootstrap.Modal(dialogDiv, {'backdrop': 'static'});
   
   // Call before dialog is shown
   if(null != dialogOptions.beforeFormCreated)
   {
      // If true, then don't open
      if(dialogOptions.beforeFormCreated(dialogDiv, dialogModal))
      {
         // Destroy modal
         dialogModal.dispose();
         $(this).closest('.modal-dialog').remove();
      }
   }
   
   // Display modal
   dialogModal.show();
   $(dialogModal._element).on('hidden.bs.modal', function (event) {
      $(event.target).remove();
   });
   
   // Call after dialog is shown
   if(null != dialogOptions.formCreated)
   {
      dialogOptions.formCreated(dialogDiv, dialogModal);
   }
   
}

/* Show OK/Cancel dialog */
window.confirmDialog = function(title, message, onOkCallback=null, options = {})
{
   var dialogSettings = $.extend({
      dismissLabel: translateString('Cancel'),
      actionLabel: translateString('Ok'),
      onAction: onOkCallback,
      closeOnAction: true,
      beforeClose: null,
      afterClose: null,
      warning: false,
   }, options);
   
   showDialog(title, message, dialogSettings);
}

/* Open a dialog with select2 */
window.openSelectDialog = function(title, settings, onSaveCallback, options)
{
   var dialogSettings = $.extend({
      dismissLabel: translateString('Close'),
      actionLabel: translateString('Ok'),
      onAction: function(data, target){
         var selectDiv = target.find('select#selectContainer');
         var value = selectDiv.select2('data');
         
         if(!selectDiv.attr('multiple'))
            value = (1 == value.length) ? value[0] : null;
         
         onSaveCallback(value);         
      },
      closeOnAction: true,
      beforeClose: null,
      afterClose: null,
      beforeFormCreated: function(dialogDiv, dialogModal){
         // Set select settings
         settings.dropdownParent = dialogDiv;
         
         settings.sorter = function(inputarray)
         {
            return inputarray.sort(function(a,b)
            {
               return a.text.localeCompare(b.text);
            });
         }
         
         dialogDiv.find('select').select2(settings);
         dialogDiv.find('select').on('select2:close', function(e){
            $('button.action-button').focus();
         });      
         
      }
   }, options);
   
   var contentDiv = $('<div class="form-group"></div>')
      .append($('<label class="form-label" for="selectContainer></label>')
         .text(title))
      .append($('<select class="form-select form-select-sm editable-property" id="selectContainer" style="width: 100%;"></select>'));
  
  showDialog(title, contentDiv, dialogSettings);
}

/* Show tag editor */
window.editTags = function(model, modelid,  tagContainer, onSaveCallback)
{
   ajaxGet('/api/v1/items/getAllTags/'+model+'/'+modelid+'', function(data){
      var settings = {
         multiple: true,
         tags: true,
         data: data,
         createTag: function (params) {
          var term = $.trim(params.term);

          if (term === '') {
            return null;
          }

          return {
            id: term,
            text: term,
          }
         }         
      };
      
      openSelectDialog(translateString('Edit tags'),settings,function(data){
         var dataArray = [];
         Object.keys(data).forEach(key => {
            dataArray.push(data[key].id);
         });

         ajaxPost('/api/v1/items/setTags/'+model+'/'+modelid+'', {tags: dataArray}, function(data){
            if(tagContainer)
            {
               var tagobjs = $('<span />')
                  .addClass('tagcontainer');
                  
               data.forEach((tag) => 
                  tagobjs.append(
                     $('<span />')
                        .addClass('badge')
                        .addClass('rounded-pill')
                        .text(tag.text)
                  )
               );
               
               tagContainer.replaceWith(tagobjs);
            }            
            
            if(onSaveCallback)
               onSaveCallback(data);
         })
      });
   });
}

/* JTable repeatable components */
window.showHistoryField = function(modeltype, parentContainer)
{
   return {
      title: '',
      type: 'command',
      width: '1%',
      sorting: false,
      edit: false,
      create: false,
      footer: true,
      display: function (sourcedata) {
         var retobj = $('<button />')
            .addClass('btn btn-outline-primary btn-sm')
            .text(translateString('Show history'))
            .prepend($('<span>history</span>')
               .addClass('material-symbols-rounded'));
               
         retobj.click(function () {
            if(retobj.closest('.accordion-body').find('#child-history').length)
            {
               parentContainer.jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
               return;
            }
            
             parentContainer.jtable('openChildTable',
               retobj.closest('.accordion-body').find('.accordion-footer'),
               {
                  title: translateString('History'),
                  actions: {
                     listAction: '/api/v1/items/ActivityLog?object_type='+modeltype+'&object_id='+sourcedata.record.id,
                  },
                  tableId: 'child-history',
                  paging: true,
                  fields: {
                     created_at_pretty: {
                        title: translateString('Date / time'),
                        width: '20%',
                        list: true,
                     },
                     created_by: {
                        title: translateString('User'),
                        width: '20%',
                        list: true,
                     },
                     text: {
                        title: translateString('Description'),
                        width: 'auto',
                        list: true,
                     },
                  },
               }, function (data) {
                      data.childTable.jtable('load');
               }
             );
         });
            
         return retobj;
      }
   };
}

window.showMessagesField = function(modeltype, parentContainer)
{
   return  {
   title: '',
   type: 'command',
   width: '1%',
   sorting: false,
   edit: false,
   create: false,
   footer: true,
   display: function (sourcedata) {
      var retobj = $('<button />')
         .addClass('btn btn-outline-primary btn-sm')
         .text(translateString('Show comments'))
         .prepend($('<span>chat</span>')
            .addClass('material-symbols-rounded'));
            
      if(sourcedata.record.messagecount)
         retobj.find('.material-symbols-rounded').addClass('has-messages');
            
      retobj.click(function (clickevent) {
         if(retobj.closest('.accordion-body').find('#child-messages').length)
         {
            parentContainer.jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
            return;
         }
         
          parentContainer.jtable('openChildTable',
            retobj.closest('.accordion-body').find('.accordion-footer'),
            {
               title: translateString('Comments'),
               actions: {
                  listAction: '/api/v1/items/ObjectMessage?object_type='+modeltype+'&object_id='+sourcedata.record.id,
                  deleteAction: '/api/v1/items/ObjectMessage',
               },
               tableId: 'child-messages',
               paging: true,
               fields: {
                  id: {
                     key: true,
                     list: false,
                  },
                  created_at_pretty: {
                     title: translateString('Date / time'),
                     width: '20%',
                     list: true,
                  },
                  created_by: {
                     title: translateString('User'),
                     width: '20%',
                     list: true,
                  },
                  comment: {
                     title: translateString('Comment'),
                     width: 'auto',
                     list: true,
                  },
               },
               recordDeleted: function(event, data) {
                  if(1 == $(event.target).find('.jtable-data-row').length)
                     $(event.target).closest('.jtable-data-row').find('[data-jtable-fieldname="showMessages"] .has-messages').removeClass('has-messages');
               }
            }, function (data) {
               data.childTable.jtable('load');
               $(clickevent.target).closest('.jtable-data-row').find('.jtable-main-container')
                  .append($('<div></div>')
                     .addClass('add-message-row')
                     .append($('<textarea></textarea>')
                        .addClass('form-control')
                        .attr('placeholder', translateString('Write your comment here'))
                     )
                     .append($('<button></button>')
                        .addClass('btn btn-primary btn-sm text-light')
                        .text(translateString('Add comment'))
                        .on('click', function(event){
                           ajaxPost('/api/v1/items/ObjectMessage', { 'object_type':modeltype, 'object_id': sourcedata.record.id, comment: $(event.target).siblings('textarea').val() }, function(){
                              $(event.target).siblings('textarea').val('')
                              data.childTable.jtable('reload');
                              $(event.target).closest('.jtable-data-row').find('[data-jtable-fieldname="showMessages"] > .jtable-cell-content > .btn > .material-symbols-rounded:not(.has-messages)').addClass('has-messages');
                           });
                        })                                     
                     )
                  );
            }
          );
      });
         
         return retobj;
      }
   };
}

window.showTags = function(modeltype, parentContainer){
   return {
      title: '',
      create: false,
      edit: false,
      list: true,
      listClass: 'd-inline-block col-12 col-md-4',
      display: function(data) {
         var retobj=$('<div />');
         retobj.append(
            $('<a />')
               .css({cursor: 'pointer', 'font-size': '1.5em', 'margin-right': '5px'})
               .attr('title', translateString('Edit tags'))
               .append($('<span />')
                  .addClass('material-symbols-rounded')
                  .text('sell'))
               .on('click', function(event){
                  editTags(modeltype, data.record.id, $(this).closest('tr').find('span.tagcontainer'), function(){
                     $('#tableContainer').jtable('reloadRow', $(event.target).closest('.jtable-data-row'));
                  });
               })
         );
         
         var tagContainer = $('<span />')
            .addClass('tagcontainer');
            
         if(data.record.tags)
         {
            data.record.tags.forEach((tag) => 
               tagContainer.append(
                  $('<span />')
                     .addClass('badge')
                     .addClass('rounded-pill')
                     .text(tag.name)
               )
            );
         }
         
         retobj.append(tagContainer);
         
         return retobj;
      },
   };
}

window.showFindings = function(modeltype, parentContainer)
{
   return  {
   title: '',
   type: 'command',
   width: '1%',
   sorting: false,
   edit: false,
   create: false,
   footer: true,
   display: function (sourcedata) {
      var retobj = $('<button />')
         .addClass('btn btn-outline-primary btn-sm')
         .text(translateString('Show associated findings'))
         .prepend($('<span>report</span>')
            .addClass('material-symbols-rounded'));
            
      if(sourcedata.record.findingcount)
         retobj.find('.material-symbols-rounded').addClass('text-danger');
            
      retobj.click(function (clickevent) {
         if(retobj.closest('.accordion-body').find('#child-findings').length)
         {
            parentContainer.jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
            return;
         }
         
          parentContainer.jtable('openChildTable',
            retobj.closest('.accordion-body').find('.accordion-footer'),
            {
               title: translateString('Findings'),
               actions: {
                  listAction: '/api/v1/items/Finding?context_type='+modeltype+'&context_id='+sourcedata.record.id,
               },
               tableId: 'child-findings',
               paging: true,
               fields: {
                  id: {
                     title: 'ID',
                     key: true,
                     list: true,
                     width: '15%',
                     display: function(data){
                        return 'FINDING-'+data.record.id;
                     },
                  },
                  created_at_pretty: {
                     title: translateString('Created'),
                     list: true,
                     width: '15%',
                  },
                  isnc: {
                     title: translateString('Type'),
                     list: true,
                     width: '15%',
                     display: function(data){
                        return data.record.isnc ? 'Non-conformity' : 'Observation';
                     }
                  },
                  finished: {
                     title: translateString('Status'),
                     list: true,
                     width: '15%',
                     display: function(data)
                     {
                        return data.record.finished_at ? 'Finished' : 'Pending';
                     }
                  },
                  name: {
                     title: translateString('Name'),
                     list: true,
                     width: '40%',
                  },
               },
               recordsLoaded: function(event, data){
                  $(event.target).find('.jtable-data-row')
                     .css({'cursor': 'pointer'})
                     .on('click', function(){
                        window.open('/assessment/findings?jtId[findingstable]='+$(this).data('record-key'), '_blank');
                     });
               }
            }, function (data) {
               data.childTable.jtable('load');
            }
          );
      });
         
         return retobj;
      }
   };
}

window.showRisks = function(modeltype, parentContainer, ai=false, allowdelete=false)
{
   return  {
   title: '',
   type: 'command',
   width: '1%',
   sorting: false,
   edit: false,
   create: false,
   footer: true,
   display: function (sourcedata) {
      var retobj = $('<button />')
         .addClass('btn btn-outline-primary btn-sm')
         .text(translateString('Risks'))
         .prepend($('<span>warning</span>')
            .addClass('material-symbols-rounded'));
            
      if(sourcedata.record.riskcount)
         retobj.find('.material-symbols-rounded').addClass('text-danger');
            
      retobj.click(function (clickevent) {
         if(retobj.closest('.accordion-body').find('#child-risks').length)
         {
            parentContainer.jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
            return;
         }
         
          parentContainer.jtable('openChildTable',
            retobj.closest('.accordion-body').find('.accordion-footer'),
            {
               title: translateString('Risks'),
               actions: {
                  listAction: '/api/v1/items/Risk?context_type='+modeltype+'&context_id='+sourcedata.record.id,
                  createAction: '/api/v1/items/Risk?context='+modeltype.split('\\').slice(-1)[0]+'_'+sourcedata.record.id,
                  deleteAction: (allowdelete ? '/api/v1/items/Risk' : null),
               },
               messages: {
                  addNewRecord: translateString('Add new risk'),
               },
               toolbar: (ai) ? {
                     items: [
                        {
                           icon: 'support_agent',
                           cssClass: 'btn btn-primary btn-sm mx-2 text-white',
                           text: translateString('AI Risk identification'),
                           click: function (buttonContainer) {
                              var jtableContainer = $(buttonContainer).closest('.jtable-child-table-container');
                              ajaxPost('/api/v1/ai/riskidentification', { context_type: 'App\\Models\\'+modeltype.split('\\').slice(-1)[0], context_id: sourcedata.record.id }, function(aidata, textStatus, jqXHR){
                                 if(null == aidata.riskcount)
                                    showDialog(translateString("AI Risk identification"), translateString("No response was received from AI agent"));
                                 else {
                                    jtableContainer.jtable('reload');
                                 }
                              });

                              return false;
                           }
                        }]
                  } : {},
               tableId: 'child-risks',
               paging: true,
               fields: {
                  id: {
                     title: 'ID',
                     type: 'hidden',
                     key: true,
                     list: true,
                     width: '15%',
                     display: function(data){
                        return 'RISK-'+data.record.id;
                     },
                  },
                  department_id: {
                     title: translateString('Department'),
                     create: true,
                     list: false,
                     required: true,
                     listClass: 'd-inline-block col-12 col-md-4',
                     options: '/api/v1/listsources/departments',
                  },
                  riskowner_id: {
                     title: translateString('Risk owner'),
                     create: true,
                     list: false,
                     required: false,
                     listClass: 'd-inline-block col-12 col-md-4',
                     options: '/api/v1/listsources/riskowners',
                  },
                  created_at_pretty: {
                     title: translateString('Created'),
                     list: true,
                     create: false,
                     width: '15%',
                  },
                  status: {
                     title: translateString('Status'),
                     list: true,
                     create: false,
                     width: '15%',
                     display: function(data)
                     {
                        return data.record.assessed_at ? translateString('Finished') : translateString('Pending');
                     }
                  },
                  name_pretty: {
                     title: translateString('Name'),
                     list: true,
                     create: false,
                     width: '55%',
                  },
                  name: {
                     title: translateString('Name'),
                     create: true,
                     list: false,
                     required: true,
                     maxlength: 255,
                  },
                  scenariodescription: {
                     title: translateString('Scenario description'),
                     type: 'textarea',
                     create: true,
                     list: false,
                     required: true,
                  },
                  consequencedescription: {
                     title: translateString('Consequence description'),
                     type: 'textarea',
                     create: true,
                     list: false,
                     required: false,
                  },
               },
               recordsLoaded: function(event, data){
                  $(event.target).find('.jtable-data-row')
                     .css({'cursor': 'pointer'})
                     .on('click', function(){
                        window.open('/assessment/riskregister?jtId[riskregister]='+$(this).data('record-key'), '_blank');
                     });
               }
            }, function (data) {
               data.childTable.jtable('load');
            }
          );
      });
         
         return retobj;
      }
   };
}

window.showFiles = function(modeltype, parentContainer, uploadToken)
{
   return  {
   title: '',
   type: 'command',
   width: '1%',
   sorting: false,
   edit: false,
   create: false,
   footer: true,
   display: function (sourcedata) {
      var retobj = $('<button />')
         .addClass('btn btn-outline-primary btn-sm')
         .text(translateString('Files'))
         .prepend($('<span>folder_open</span>')
            .addClass('material-symbols-rounded'));
            
      if(sourcedata.record && sourcedata.record.files && (0 < sourcedata.record.files.length))
         retobj.find('.material-symbols-rounded').addClass('text-danger');
            
      retobj.click(function (clickevent) {
         if(retobj.closest('.accordion-body').find('#child-files').length)
         {
            parentContainer.jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
            return;
         }
         
          parentContainer.jtable('openChildTable',
            retobj.closest('.accordion-body').find('.accordion-footer'),
            {
               title: translateString('Files'),
               actions: {
                  listAction: '/api/v1/items/File?object_type='+modeltype+'&object_id='+sourcedata.record.id,
                  updateAction: '/api/v1/items/File',
                  deleteAction: '/api/v1/items/File',
               },
               tableId: 'child-files',
               fields: {
                  id: {
                     key: true,
                     list: false,
                     edit: false,
                  },
                  icon: {
                     title: translateString('Download'),
                     list: true,
                     edit: false,
                     width: '15%',
                     display: function(data) {
                        return $('<a href="/api/v1/items/File/'+data.record.id+'/download" />')
                           .append($('<span />')
                              .addClass('material-symbols-rounded')
                              .text('description'))
                           .append(translateString('Download'));
                     }
                  },
                  created_at_pretty: {
                     title: translateString('Created'),
                     list: true,
                     edit: false,
                     width: '15%',
                  },
                  name: {
                     title: translateString('Name'),
                     list: true,
                     edit: true,
                     width: '15%',
                  },
                  description: {
                     title: translateString('Description'),
                     type: 'textarea',
                     list: true,
                     edit: true,
                  },
               },
               recordsLoaded: function(event, data) {
                  // Check if there is already a dropzone
                  if(!$(this).find('.file-drop-area').length && uploadToken)
                  {
                     var fileArea = $('<div />')
                        .addClass('file-drop-area dropzone')
                        .append($('<div data-dz-message />')
                           .addClass('dz-message')
                           .append($('<span />')
                              .text(translateString('Drop files here to upload'))
                           )
                        )
                        .dropzone({
                           url: '/api/v1/items/File?_token='+uploadToken+'&object_type='+modeltype+'&object_id='+sourcedata.record.id,
                           error: function(file, retval) {
                              if (file.previewElement) {
                                 file.previewElement.classList.add("dz-error");
                                 for (let node of file.previewElement.querySelectorAll(
                                   "[data-dz-errormessage]"
                                 )) {
                                   node.textContent = retval.message;
                                 }
                              }
                           },
                           complete: function() {
                              if (this.getUploadingFiles().length === 0 && this.getQueuedFiles().length === 0) {
                                 $(this.element).closest('.jtable-child-table-container').jtable('reload');
                              }
                           }
                        })
                        .appendTo($(this).find('.jtable-title'));
                  }
               },
            }, function (data) {
               data.childTable.jtable('load');
            }
          );
      });
         
         return retobj;
      }
   };
}


window.showForms = function(modeltype, parentContainer)
{
   return  {
      title: '',
      type: 'command',
      width: '1%',
      sorting: false,
      edit: false,
      create: false,
      footer: true,
      display: function (sourcedata) {
         var retobj = $('<button />')
            .addClass('btn btn-outline-primary btn-sm')
            .text(translateString('Forms'))
            .prepend($('<span>description</span>')
               .addClass('material-symbols-rounded'));

         retobj.click(function (clickevent) {
            if(retobj.closest('.accordion-body').find('#child-forms').length)
            {
               parentContainer.jtable('closeChildTable', retobj.closest('.accordion-body').find('.accordion-footer'));
               return;
            }

            parentContainer.jtable('openChildTable',
               retobj.closest('.accordion-body').find('.accordion-footer'),
               {
                  title: translateString('Forms'),
                  actions: {
                     listAction: '/api/v1/items/Form?hidearchived=1&context_type='+modeltype+'&context_id='+sourcedata.record.id,
                  },
                  tableId: 'child-forms',
                  paging: true,
                  fields: {
                     id: {
                        title: 'ID',
                        key: true,
                        list: false,
                     },
                     open: {
                        type: 'command',
                        title: translateString('Open'),
                        width: '1%',
                        display: function(data){
                           switch(data.record.state)
                           {
                              case 'draft':
                              case 'answered':
                              case 'archived':
                                 break;
                              default:
                                 return '';
                           }
                           return $('<span />')
                              .addClass('material-symbols-rounded')
                              .css({'cursor': 'pointer'})
                              .text('open_in_new')
                              .click(function(){
                                 displayUserForm(data.record.id);
                              });
                        }
                     },
                     state_pretty: {
                        title: translateString('Status'),
                        list: true,
                        width: '10%',
                     },
                     name: {
                        title: translateString('Name'),
                        list: true,
                        width: '40%',
                     },
                  },
               }, function (data) {
                  data.childTable.jtable('load');
               }
            );
         });

         return retobj;
      }
   };
}
/* User report */
window.userReportObservation = function(departments, observations=true){
   var outerContainer = $('<form />')
      .addClass('container')
      .addClass('userreportform');
      
   outerContainer.append($('<div />')
      .addClass('formpartlabel')
      .text(translateString('Report type')));
      
if(observations)
{
   outerContainer.append($('<div />')
      .addClass('form-check')
      .append($('<input />')
         .addClass('form-check-input')
         .prop('type', 'radio')
         .prop('name', 'category')
         .prop('value', 'nonconformity')
         .prop('checked', true)
         .prop('id', 'categoryValue1'))
      .append($('<label />')
         .addClass('form-check-label')
         .prop('for', 'categoryValue1')
         .text(translateString('Non-conformity'))));
   
   outerContainer.append($('<div />')
      .addClass('form-check')
      .append($('<input />')
         .addClass('form-check-input')
         .prop('type', 'radio')
         .prop('name', 'category')
         .prop('value', 'observation')
         .prop('id', 'categoryValue2'))
      .append($('<label />')
         .addClass('form-check-label')
         .prop('for', 'categoryValue2')
         .text(translateString('Observation'))));
}

   outerContainer.append($('<div />')
      .addClass('form-check')
      .append($('<input />')
         .addClass('form-check-input')
         .prop('type', 'radio')
         .prop('name', 'category')
         .prop('value', 'risk')
         .prop('checked', !observations)
         .prop('id', 'categoryValue3'))
      .append($('<label />')
         .addClass('form-check-label')
         .prop('for', 'categoryValue3')
         .text(translateString('Risk'))));
         
   outerContainer.append($('<div />')
      .addClass('formpartlabel')
      .text(translateString('Department')));
      
   var departmentSelect = $('<select />')
      .addClass('form-control form-select')
      .prop('name', 'department_id')
      .appendTo(outerContainer);
      
   Object.values(departments).forEach((dept) => {
      departmentSelect.append($('<option />')
         .prop('value', dept.id)
         .text(dept.name));
   });
         
   outerContainer.append($('<div />')
      .addClass('formpartlabel')
      .text(translateString('Header')));
         
   outerContainer.append($('<input />')
         .addClass('form-control')
         .prop('type', 'text')
         .prop('name', 'name')
         .attr('maxlength', 100)
         .attr('required', true));
         
   outerContainer.append($('<div />')
      .addClass('formpartlabel')
      .text(translateString('Observation')));
         
   outerContainer.append($('<textarea />')
         .addClass('form-control')
         .prop('name', 'description')
         .attr('required', true)
         .prop('rows', 3)
         .prop('placeholder', translateString('Give a brief introduction to what you have seen')));
         
   outerContainer.append($('<div />')
      .addClass('formpartlabel')
      .text(translateString('Consequence')));
         
   outerContainer.append($('<textarea />')
         .addClass('form-control')
         .prop('name', 'consequence')
         .attr('required', false)
         .prop('rows', 3)
         .prop('placeholder', translateString('If you have an idea, please consider describing what you see as a potential or real consequence')));
         
   confirmDialog(translateString('Report'), outerContainer, function(formData, dialogDiv){
      
      // Validate form
      if(!$(dialogDiv).find('form')[0].reportValidity())
         return true;
         
      var postData = {};
      var callsuffix = '';
      
      Object.values(formData).forEach((item) => {
         switch(item.name)
         {
            case 'category':
               if(item.value == 'nonconformity')
               {
                  postData.nonconformity = 1;
                  callsuffix = 'Finding';
               }
               else if(item.value == 'observation')
               {
                  postData.nonconformity = 0;
                  callsuffix = 'Finding';
               }
               else if(item.value == 'risk')
               {
                  callsuffix = 'Risk';
               }
               break;
            case 'name':
               postData.name = item.value;
               break;
            case 'department_id':
            {
               postData.department_id = item.value;
               postData.department = item.value;
               break;
            }
            case 'description':
               postData.description = item.value;
               break;
            case 'consequence':
               postData.consequence = item.value;
               break;
         }
      });
      
      if('' == callsuffix)
      {
         console.error('Invalid report category');
         return;
      }
      
      if('Risk' == callsuffix)
      {
         postData.scenariodescription = postData.description;
         postData.consequencedescription = postData.consequence;
      }
      
      // Perform ajax call to create item
      ajaxPost('/api/v1/items/'+callsuffix, postData, function(data){
         showDialog(translateString('Report sent'), translateString('Your report was submitted. Thank you for contributing!'));
      });         
   });
}

/* User mark activity as completed */
window.userFinishActivity = function(id, callback=null){
   confirmDialog(translateString("Completed"), translateString("By marking this activity as completed, you state that the activity has been performed"), function(){
         ajaxPost('/api/v1/items/Activity/'+id+'/completed', {}, function(){
               if(callback)
                  callback();
            });                        
   }, {warning: true});
   
}

window.displayUserForm = function(id, onClose=null) {
   ajaxGet('/api/v1/items/Form/' + id+'?include_formdata=1', function(data) {
      var container = $('<div />')[0];
      var unmount = null;
      var formdata = data.formdata;
      if (typeof formdata === 'string') {
         try { formdata = JSON.parse(formdata); } catch(e) { formdata = {}; }
      }

      showDialog(data.name || translateString('Form'), container, {
         formCreated: function(dialogDiv) {
            dialogDiv.find('.modal-dialog').addClass('modal-xl');
            unmount = window.mountFormViewer(container, {
               formName: data.name || '',
               formdata: formdata || {},
               formId: id,
            });
         },
         afterClose: function() {
            if (unmount) unmount();
            if (onClose) onClose();
         },
      });
   });
}

/* Draw kpi */
window.showKpi = function(canvas, dataset, unit, showaskpi = false) {
  new Chart(canvas,
    {
      type: 'line',
      data: {
         labels: dataset.map(row => row.date),
         datasets: [
            {
               label: '',
               data: dataset.map(row => row.value),
               borderColor: showaskpi ? '#779a9e30': '#779a9eff',
               fill: false,
               tension: 0.3,
            }
        ],
      },
      options: {
         plugins: {
            legend: {
                display: false,
            }
         },
         scales: {
            x:{
               display: !showaskpi,
               grid: {
                  display:!showaskpi,
               }
            },
            y:{
               display: !showaskpi,
               grid: {
                  display:!showaskpi,
               }
            },
         }
      },

    }
  );
}

/* Set cookie */
window.setCookieValue = function(name, value) {
   if('undefined' != typeof document.cookie)
      document.cookie = encodeURIComponent(name)+'='+encodeURIComponent(JSON.stringify(value))+'; path=/; SameSite=strict';
}

/* Get cookie */
window.getCookieValue = function(name) {
   var cookies = document.cookie ? document.cookie.split(';') : [];
   for (var i = 0; i < cookies.length; i++) {
      var parts = cookies[i].trim().split('=');
      var cookiename = decodeURIComponent(parts.shift());

      if (cookiename && cookiename === name) {
         return JSON.parse(decodeURIComponent(parts.shift()));
      }
   }   
   
   return null;
}
