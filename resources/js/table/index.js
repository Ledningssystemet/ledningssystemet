/* 

jTable 2.4.0
http://www.jtable.org

---------------------------------------------------------------------------

Copyright (C) 2011-2014 by Halil İbrahim Kalkan (http://www.halilibrahimkalkan.com)

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.


-----
Extended by Rickard Svenningsson and adopted to Bootstrap 5

*/

import 'jquery-ui';
import 'jquery-ui-sortable';

/************************************************************************
 * CORE jTable module                                                    *
 *************************************************************************/
(function ($) {

   var unloadingPage;
   var jtableSerializePatched = false;
   var jtableOriginalSerializeFn = $.fn.serialize;

   $(window).on('beforeunload', function () {
      unloadingPage = true;
   });
   $(window).on('unload', function () {
      unloadingPage = false;
   });

   $.widget("hik.jtable", {

      /************************************************************************
       * DEFAULT OPTIONS / EVENTS                                              *
       *************************************************************************/
      options: {

         //Options
         actions: {},
         fields: {},
         fieldtabs: [],
         animationsEnabled: true,
         dialogShowEffect: 'fade',
         dialogHideEffect: 'fade',
         showCloseButton: false,
         loadingAnimationDelay: 500,
         saveUserPreferences: true,
         unAuthorizedRequestRedirectUrl: null,
         searchfield: false,
         bootstrap: false,
         accordion: false,
         openfirstchild: false,

         ajaxSettings: {
            type: 'POST',
            dataType: 'json',
         },

         toolbar: {
            hoverAnimation: true,
            hoverAnimationDuration: 60,
            hoverAnimationEasing: undefined,
            items: []
         },

         //Events
         closeRequested: function (event, data) {
         },
         formCreated: function (event, data) {
         },
         formSubmitting: function (event, data) {
         },
         formClosed: function (event, data) {
         },
         loadingRecords: function (event, data) {
         },
         recordsLoaded: function (event, data) {
         },
         rowLoaded: function (event, data) {
         },
         rowInserted: function (event, data) {
         },
         rowsRemoved: function (event, data) {
         },

         //Localization
         messages: {
            serverCommunicationError: 'An error occured while communicating to the server.',
            loadingMessage: 'Loading records...',
            noDataAvailable: 'No data available!',
            areYouSure: 'Are you sure?',
            yesDelete: 'Yes, delete',
            save: 'Save',
            saving: 'Saving',
            cancel: 'Cancel',
            error: 'Error',
            close: 'Close',
            cannotLoadOptionsFor: 'Can not load options for field {0}',
            ascending: 'Ascending',
            descending: 'Descending',
            search: 'Search',
            selectperiod: 'Select period...',
            deleteRecord: 'Delete record',
            editRecord: 'Edit record',
         }
      },

      /************************************************************************
       * PRIVATE FIELDS                                                        *
       *************************************************************************/

      _$mainContainer: null, //Reference to the main container of all elements that are created by this plug-in (jQuery object)

      _$titleDiv: null, //Reference to the title div (jQuery object)
      _$toolbarDiv: null, //Reference to the toolbar div (jQuery object)

      _$table: null, //Reference to the main <table> (jQuery object)
      _$tableBody: null, //Reference to <body> in the table (jQuery object)
      _$tableRows: null, //Array of all <tr> in the table (except "no data" row) (jQuery object array)

      _$busyDiv: null, //Reference to the div that is used to block UI while busy (jQuery object)
      _$busyMessageDiv: null, //Reference to the div that is used to show some message when UI is blocked (jQuery object)

      _columnList: null, //Name of all data columns in the table (select column and command columns are not included) (string array)
      _fieldList: null, //Name of all fields of a record (defined in fields option) (string array)
      _keyField: null, //Name of the key field of a record (that is defined as 'key: true' in the fields option) (string)

      _firstDataColumnOffset: 0, //Start index of first record field in table columns (some columns can be placed before first data column, such as select checkbox column) (integer)
      _lastPostData: null, //Last posted data on load method (object)

      _cache: null, //General purpose cache dictionary (object)

      /************************************************************************
       * CONSTRUCTOR AND INITIALIZATION METHODS                                *
       *************************************************************************/

      /* Contructor.
        *************************************************************************/
      _create: function () {

         // Perform translation
         if (('undefined' != typeof window) &&
            ('undefined' != typeof window.translations)) {
            Object.keys(this.options.messages).forEach((key) => {
               Object.values(window.translations).every((obj) => {
                  if (obj.in == this.options.messages[key]) {
                     this.options.messages[key] = obj.out;
                     return false;
                  }
                  return true;
               });
            });
         }

         //Initialization
         this._appendCustomOptions();
         this._normalizeFieldsOptions();
         this._initializeFields();
         this._createFieldAndColumnList();

         //Creating DOM elements
         this._createMainContainer();
         this._createTableTitle();
         this._createToolBar();
         this._createTable();
         this._createBusyPanel();
         this._addNoDataRow();


         // Add bootstrap settings
         this._bootstrapAdjustments();

         // Override the default serialization for our dialog forms in order to handle file uploads.
         // Must be done once globally, otherwise each new table instance wraps serialize again and leaks closures.
         if (!jtableSerializePatched) {
            $.fn.serialize = function () {
               var self = this;

               if ($(self).hasClass('jtable-dialog-form') && window.FormData) {
                  var form = $(this);
                  var formData = new FormData(form[0]);

                  // Append empty multiple selects
                  form.find('[name][multiple]').each(function () {
                     var obj = $(this);
                     var name = $(this).prop('name');
                     name = name.substring(0, name.length - 2);


                     if (!formData.has(obj.prop('name')))
                        formData.append(name, []);
                  });

                  return formData;
               } else
                  return jtableOriginalSerializeFn.apply(this);
            };

            jtableSerializePatched = true;
         }
      },

      /* Use Bootstrap
        *************************************************************************/

      // This function will adjust the classes in order to utilize bootstrap functionality
      _bootstrapAdjustments: function () {
         var self = this;

         if (!self.options.bootstrap)
            return;

         if (self.options.accordion) {
            self._$tableBody.addClass('accordion');
            self._$tableBody.attr('id', 'jtable-body-' + self.options.tableId);
         }

         self._$titleDiv.find('.jtable-filter').addClass('d-inline-block col col-12 col-lg-8 col-md-6 align-top');
         self._$titleDiv.find('h1').addClass('d-inline-block col col-12 col-md-8');
         self._$titleDiv.find('.jtable-search-field').addClass('d-inline-block col col-12 col-md-4').removeClass('input-group');
         self._$toolbarDiv.addClass('d-inline-block col col-12 col-lg-4 col-md-6 text-end');
      },

      /* Append custom options
        *************************************************************************/
      _appendCustomOptions: function () {
         var self = this;

         self.options.loadid = null;
         self.options.searchInit = null;

         if ('undefined' != typeof self.options.tableId) {
            const searchParams = new URLSearchParams(window.location.search);

            /* Derive if search query is provided */
            if (searchParams.has('jtSearch[' + self.options.tableId + ']'))
               self.options.searchInit = searchParams.get('jtSearch[' + self.options.tableId + ']');
         } else {
            const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            self.options.tableId = 'jtable-table-';
            for (var i = 0; i < 20; i++)
               self.options.tableId += characters.charAt(Math.floor(Math.random() * characters.length));
         }
      },

      /* Normalizes some options for all fields (sets default values).
        *************************************************************************/
      _normalizeFieldsOptions: function () {
         var self = this;
         $.each(self.options.fields, function (fieldName, props) {
            self._normalizeFieldOptions(fieldName, props);
         });
      },

      /* Normalizes some options for a field (sets default values).
        *************************************************************************/
      _normalizeFieldOptions: function (fieldName, props) {
         if (props.listClass == undefined) {
            props.listClass = '';
         }
         if (props.inputClass == undefined) {
            props.inputClass = '';
         }

         //Convert dependsOn to array if it's a comma seperated lists
         if (props.dependsOn && $.type(props.dependsOn) === 'string') {
            var dependsOnArray = props.dependsOn.split(',');
            props.dependsOn = [];
            for (var i = 0; i < dependsOnArray.length; i++) {
               props.dependsOn.push($.trim(dependsOnArray[i]));
            }
         }

         if ('undefined' == typeof props.header)
            props.header = false;

         if ('undefined' == typeof props.footer)
            props.footer = false;

         if ('undefined' == typeof props.body)
            props.body = false;


         if (!props.header && !props.footer)
            props.body = true;
      },

      /* Intializes some private variables.
        *************************************************************************/
      _initializeFields: function () {
         this._lastPostData = {};
         this._$tableRows = [];
         this._columnList = [];
         this._fieldList = [];
         this._cache = [];
      },

      /* Fills _fieldList, _columnList arrays and sets _keyField variable.
        *************************************************************************/
      _createFieldAndColumnList: function () {
         var self = this;

         $.each(self.options.fields, function (name, props) {

            //Add field to the field list
            self._fieldList.push(name);

            //Check if this field is the key field
            if (props.key == true) {
               self._keyField = name;
            }

            //Add field to column list if it is shown in the table
            if (props.list != false && props.type != 'hidden') {
               self._columnList.push(name);
            }
         });
      },

      /* Creates the main container div.
        *************************************************************************/
      _createMainContainer: function () {
         this._$mainContainer = $('<div />')
            .addClass('jtable-main-container' + (this.options.bootstrap ? ' jtable-bootstrap' : ' jtable-native') + (this.options.accordion ? ' jtable-accordion' : ''))
            .appendTo(this.element);
      },

      /* Creates title part
        *************************************************************************/
      _createTableTitle: function () {
         var self = this;

         var $titleDiv = $('<div />')
            .addClass('jtable-title')
            .appendTo(self._$mainContainer);

         if (self.options.title) {
            $('<h1 />')
               .addClass('jtable-title-text')
               .appendTo($titleDiv)
               .append(self.options.title);

            if (self.options.showCloseButton) {

               var $textSpan = $('<span />')
                  .html(self.options.messages.close);

               $('<button></button>')
                  .addClass('jtable-command-button jtable-close-button btn-close')
                  .attr('title', self.options.messages.close)
                  .append($textSpan)
                  .appendTo($titleDiv)
                  .click(function (e) {
                     e.preventDefault();
                     e.stopPropagation();
                     self._onCloseRequested();
                  });
            }
         }

         // Searchfield
         if (self.options.searchfield) {
            // Append with search field
            var newelem = $('<div></div>')
               .addClass('jtable-search-field input-group')
               .append($('<span></span>')
                  .addClass('input-group-text')
                  .append($('<input type="text"></input>')
                     .addClass('form-control form-control-sm')
                     .prop('placeholder', self.options.messages.search)
                     .prop('maxlength', 200)
                     .prop('aria-describedby', 'jtSearchField'))
                  .append($('<span></span>')
                     .addClass('material-symbols-rounded')
                     .css({'font-size': '1.3em'})
                     .text('search')));

            // Initialize search field
            if (null != self.options.searchInit)
               newelem.find('input').val(self.options.searchInit);

            let debounceTimer;
            newelem.find('input').on('input', function () {
               clearTimeout(debounceTimer);
               debounceTimer = setTimeout(() => {
                  self._currentPageNo = 1;
                  self.reload();
               }, 250);
            });
            newelem.appendTo($titleDiv);
         }
         // Filter functionality
         var disableCookies = false;

         /* Derive if single item load */
         const searchParams = new URLSearchParams(window.location.search);
         if (searchParams.has('jtId[' + self.options.tableId + ']')) {
            disableCookies = true;

            if(!self.options.filter)
               self.options.filter = {};
            self.options.filter.id = {
               value: searchParams.get('jtId[' + self.options.tableId + ']'),
               default: 0,
               type: 'hidden',
               text: 'ID'
            };
         }


         var filtersContainer = $('<div></div>').addClass('jtable-filter').appendTo($titleDiv);
         if(self.options.filter && self.options.filter.id) {
            filtersContainer.addClass('jtable-filter-disabled');
            $titleDiv.find('.jtable-search-field').addClass('d-none');
         }

         if (self.options.filter && (0 < Object.keys(self.options.filter).length)) {
            // Create filter content
            var filterDiv = $('<div></div>');

            filterDiv.append($('<h6 />')
               .addClass('text-muted text-uppercase small mb-3')
               .text(translateString('Filter'))
            );

            $.each(self.options.filter, function (key, filter) {
               // Create filter item container
               let filterContainer = $('<div></div>').addClass('mb-3').addClass('form-group').appendTo(filterDiv);

               // Calculate default value
               var defaultValue = self._getCookie('filter-' + key + '-value');

               if ("true" === defaultValue)
                  defaultValue = true;
               if ("false" === defaultValue)
                  defaultValue = false;
               if ("null" === defaultValue)
                  defaultValue = null;
               if (null == defaultValue)
                  defaultValue = filter.default;

               /* Derive if filter presets are provided by search parameter, if so set/override default but don't mess with cookies*/
               const filterSearchString = 'jtFilter[' + self.options.tableId + '][' + key + ']';
               for (const [key, value] of searchParams.entries()) {
                  if (filterSearchString == key) {
                     disableCookies = true;
                     if ('checkbox' == filter.type)
                        defaultValue = (("true" == value) || (1 == value));
                     else
                        defaultValue = value;
                  }
               }

               switch (filter.type) {
                  case 'hidden':
                     $('<input  />')
                        .addClass('jtable-filter-value')
                        .prop('type', 'hidden')
                        .prop('name', key)
                        .prop('id', 'jtable-filter-' + key)
                        .val(filter.value)
                        .appendTo(filterContainer);
                     break;
                  case 'checkbox':
                     filterContainer.addClass('form-check');

                     $('<input  />')
                        .addClass('form-check-input jtable-filter-value')
                        .prop('type', 'checkbox')
                        .prop('checked', (null != defaultValue) ? (!(!defaultValue)) : filter.checked)
                        .prop('name', key)
                        .prop('id', 'jtable-filter-' + key)
                        .val(filter.value)
                        .appendTo(filterContainer);

                     $('<label />')
                        .prop('for', 'jtable-filter-' + key)
                        .addClass('form-check-label')
                        .text(filter.text ? filter.text : '')
                        .appendTo(filterContainer);

                     break;
                  case 'select':
                     $('<label />')
                        .prop('for', 'jtable-filter-' + key)
                        .addClass('form-check-label')
                        .text(filter.text ? filter.text : '')
                        .appendTo(filterContainer);

                     var selectObj = $('<select />')
                        .prop('id', 'jtable-filter-' + key)
                        .prop('name', key)
                        .prop('multiple', filter.multiple)
                        .addClass('form-control jtable-filter-value form-select')
                        .appendTo(filterContainer);

                     $.each(filter.options, function (optkey, optvalue) {
                        $('<option />')
                           .prop('value', optvalue.value)
                           .prop('selected', defaultValue && (defaultValue == optvalue.value))
                           .text(optvalue.text)
                           .appendTo(selectObj);
                     });

                     if (filter.search)
                        selectObj.select2({});

                     break;
               }
            });

            var updateCookies = function() {
               if(disableCookies)
                  return;
               
               // Delete all filter cookies
               self._getPrefixedCookies('filter').forEach(cookie => {
                  self._deleteCookie(cookie.Key);
               });

               $titleDiv.find('.jtable-filter-pills > .badge').each(function(){
                  var key = $(this).attr('data-filter-key');
                  var defaultValue = $(this).attr('data-filter-default');
                  var value = $(this).attr('data-filter-value');

                  if(value != defaultValue) {
                     self._setCookie('filter-' + key + '-value', value);
                  }
               });
            }

            var updateFilterPills = function () {
               // Delete filter pills
               filtersContainer.find('.jtable-filter-pills').remove();

               // Create filter pills
               var pillsContainer = $('<div>').addClass('jtable-filter-pills').appendTo(filtersContainer);

               $.each(self.options.filter, function (key, filter) {

                  // Derive filter value
                  var filterValue = filter.default;
                  var pillText = '';
                  var showBadge = false;
                  switch (filter.type) {
                     case 'checkbox':
                        if (filterDiv.find('#jtable-filter-' + key).is(':checked')) {
                           filterValue = filter.value;
                           pillText = filter.text;
                           showBadge = true;
                        }
                        else
                           filterValue = filter.default;
                        break;
                     case 'hidden':
                        filterValue = filterDiv.find('#jtable-filter-' + key).val();
                        pillText = filter.text + ': ' + filterValue;
                        showBadge = (filterValue != filter.default);
                        break;
                     case 'select':
                        filterValue = filterDiv.find('#jtable-filter-' + key).val();
                        filter.options.forEach(option => {
                           if (option.value == filterValue)
                              pillText = filter.text + ': ' + option.text
                        });
                        showBadge = (filterValue != filter.default);
                        break;
                  }
                  if (showBadge) {
                     $('<span />')
                        .addClass('badge rounded-pill')
                        .attr('data-filter-key', key)
                        .attr('data-filter-value', filterValue)
                        .attr('data-filter-type', filter.type)
                        .attr('data-filter-default', filter.default)
                        .append($('<span />')
                           .addClass('material-symbols-rounded')
                           .text('cancel')
                           .on('click', function () {
                              switch ($(this).parent().attr('data-filter-type')) {
                                 case 'checkbox':
                                    filterDiv.find('#jtable-filter-' + $(this).parent().attr('data-filter-key')).prop('checked', false);
                                    self.options.filter[$(this).parent().attr('data-filter-key')].checked = false;
                                    break;
                                 default:
                                    filterDiv.find('#jtable-filter-' + $(this).parent().attr('data-filter-key')).val($(this).parent().attr('data-filter-default'));
                                    self.options.filter[$(this).parent().attr('data-filter-key')].value = $(this).parent().attr('data-filter-default');
                                    break;
                              }
                              if($(this).parent().attr('data-filter-key') == "id") {
                                 filtersContainer.removeClass('jtable-filter-disabled');
                                 $titleDiv.find('.jtable-search-field').removeClass('d-none');
                              }
                              updateFilterPills();
                              updateCookies();
                              self.reload();
                           })
                        )
                        .append($('<span />')
                           .addClass('filter-pill-text')
                           .text(pillText)
                        )
                        .appendTo(pillsContainer);
                  }
               });
            }

            // Create reload button
            filtersContainer.append($('<button />')
               .addClass('btn btn-sm btn-outline-primary jtable-reload-button')
               .text(translateString('Reload'))
               .append($('<span />')
                  .addClass('material-symbols-rounded')
                  .text('refresh')
                  .prop('title', translateString('Reload'))
               )
               .click(function () {
                  self.reload();
               })
            );

            // Create button
            filtersContainer.append($('<button />')
               .addClass('btn btn-sm btn-outline-primary jtable-filter-button')
               .text(translateString('Filter'))
               .append($('<span />')
                  .addClass('material-symbols-rounded')
                  .text('filter_alt')
                  .prop('title', translateString('Filter'))
               )
               .click(function () {
                  // Check if id is set
                  if (!self.options.filter.id || (self.options.filter.id.value == self.options.filter.id.default)) {
                     confirmDialog(translateString('Filter'), filterDiv, function (data) {
                           updateFilterPills();
                           updateCookies();
                           self.reload();
                        },
                        {
                           actionLabel: translateString('Apply'),
                           dismissLabel: translateString('Cancel'),
                        });
                  }
               })
            );

            updateFilterPills();

         }


         self._$titleDiv = $titleDiv;
      },

      /* Creates the table.
        *************************************************************************/
      _createTable: function () {
         this._$table = $(this.options.bootstrap ? '<div></div>' : '<table></table>')
            .addClass('jtable')
            .appendTo(this._$mainContainer);

         if (this.options.tableId) {
            this._$table.attr('id', this.options.tableId);
         }

         if (!this.options.bootstrap)
            this._createTableHead();

         this._createTableBody();
      },

      /* Creates header (all column headers) of the table.
        *************************************************************************/
      _createTableHead: function () {
         var $thead = $('<thead></thead>')
            .appendTo(this._$table);

         this._addRowToTableHead($thead);
      },

      /* Adds tr element to given thead element
        *************************************************************************/
      _addRowToTableHead: function ($thead) {
         var $tr = $('<tr></tr>')
            .appendTo($thead);

         this._addColumnsToHeaderRow($tr);
      },

      /* Adds column header cells to given tr element.
        *************************************************************************/
      _addColumnsToHeaderRow: function ($tr) {
         for (var i = 0; i < this._columnList.length; i++) {
            var fieldName = this._columnList[i];
            var $headerCell = this._createHeaderCellForField(fieldName, this.options.fields[fieldName]);
            $headerCell.appendTo($tr);
         }
      },

      /* Creates a header cell for given field.
        *  Returns th jQuery object.
        *************************************************************************/
      _createHeaderCellForField: function (fieldName, field) {
         field.width = field.width || '10%'; //default column width: 10%.

         var $headerTextSpan = $('<span />')
            .addClass('jtable-column-header-text')
            .html(field.title);

         var $headerContainerDiv = $('<div />')
            .addClass('jtable-column-header-container')
            .append($headerTextSpan);

         var $th = null;

         if ('command' == field.type) {
            $th = $('<th scope="col"></th>')
               .addClass('jtable-command-column-header')
               .addClass(field.listClass)
               .css('width', '1%')
               .data('fieldName', fieldName);
         } else {
            $th = $('<th scope="col"></th>')
               .addClass('jtable-column-header')
               .addClass(field.listClass)
               .css('width', field.width)
               .data('fieldName', fieldName)
               .append($headerContainerDiv);
         }

         return $th;
      },

      /* Creates an empty header cell that can be used as command column headers.
        *************************************************************************/
      _createEmptyCommandHeader: function () {
         var $th = $('<th></th>')
            .addClass('jtable-command-column-header')
            .css('width', '1%');

         return $th;
      },

      /* Creates tbody tag and adds to the table.
        *************************************************************************/
      _createTableBody: function () {
         if (this.options.bootstrap)
            this._$tableBody = $('<div></div>')
               .addClass('jtable-body')
               .appendTo(this._$table);
         else
            this._$tableBody = $('<tbody></tbody>').appendTo(this._$table);
      },

      /* Creates a div to block UI while jTable is busy.
        *************************************************************************/
      _createBusyPanel: function () {
         this._$busyMessageDiv = $('<div />').addClass('jtable-busy-message').prependTo(this._$mainContainer);
         this._$busyDiv = $('<div />').addClass('jtable-busy-panel-background').prependTo(this._$mainContainer);
         this._hideBusy();
      },

      /************************************************************************
       * PUBLIC METHODS                                                        *
       *************************************************************************/

      /* Loads data using AJAX call, clears table and fills with new data.
        *************************************************************************/
      load: function (postData, completeCallback) {
         this._lastPostData = postData;
         this._reloadTable(completeCallback);
      },

      /* Refreshes (re-loads) table data with last postData.
        *************************************************************************/
      reload: function (completeCallback) {
         this._reloadTable(completeCallback);
      },

      /* Reloads single row with new status.
        *************************************************************************/
      reloadRowStatus: function (row, completeCallback) {
         this._reloadRowStatus(row, completeCallback);
      },

      /* Reloads single row with last postData.
        *************************************************************************/
      reloadRow: function (row, completeCallback) {
         this._reloadRow(row, completeCallback);
      },

      /* Reloads property with last postData.
        *************************************************************************/
      reloadProperty: function (row, property, completeCallback) {
         this._reloadProperty(row, property, completeCallback);
      },

      /* Gets a jQuery row object according to given record key
        *************************************************************************/
      getRowByKey: function (key) {
         for (var i = 0; i < this._$tableRows.length; i++) {
            if (key == this._getKeyValueOfRecord(this._$tableRows[i].data('record'))) {
               return this._$tableRows[i];
            }
         }

         return null;
      },

      /* Completely removes the table from it's container.
        *************************************************************************/
      destroy: function () {
         var self = this;

         if (this._$mainContainer) {
            this._$mainContainer.find('.jtable-child-table-container').each(function () {
               if ($(this).data('hik-jtable')) {
                  $(this).jtable('destroy');
               }
            });

            this._$mainContainer.find('.jtable-data-row').each(function () {
               self._disposeTooltips(this);
               self._disposeCollapses(this);
            });
         }

         this.element.empty();
         $.Widget.prototype.destroy.call(this);
      },

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* Used to change options dynamically after initialization.
        *************************************************************************/
      _setOption: function (key, value) {

      },

      /* LOADING RECORDS  *****************************************************/



      /* Performs status update
        *************************************************************************/
      _reloadRowStatus: function (row, completeCallback) {
         var self = this;

         var completeReload = function (data) {

            //Re-generate table row
            var $newRow = self._createRowFromRecord(data);

            // Perform bootstrap reload
            self._rowBootstrapAdjustments($newRow);

            // Replace status properties
            row.find('> .accordion-collapse > .accordion-body > .status-alert-container').replaceWith($newRow.find('> .accordion-collapse > .accordion-body > .status-alert-container'));
            row.find('> .accordion-button > .accordion-header > .status-indicator').replaceWith($newRow.find('> .accordion-button > .accordion-header > .status-indicator'));

            // Update record
            row.data('record', data);

            self._hideBusy();

            self._onStatusReloaded(row);

            //Call complete callback
            if (completeCallback) {
               completeCallback();
            }
         };

         self._showBusy(self.options.messages.loadingMessage, self.options.loadingAnimationDelay); //Disable table since it's busy
         self._onLoadingRecords();

         //listAction may be a function, check if it is
         if ($.isFunction(self.options.actions.listAction)) {

            //Execute the function
            var funcResult = self.options.actions.listAction(self._lastPostData, $.extend({id: row.data('record-key')}, self._createJtParamsForLoading()));

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               funcResult.done(function (data) {
                  completeReload(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
               }).always(function () {
                  self._hideBusy();
               });
            } else { //assume it's the data we're loading
               completeReload(funcResult);
            }

         } else { //assume listAction as URL string.

            //Generate URL (with query string parameters) to load records
            var loadUrl = self._createRecordLoadUrl(row.data('record-key'));

            //Load data from server using AJAX
            self._ajax({
               url: loadUrl,
               data: self._lastPostData,
               method: 'GET',
               success: function (data) {
                  completeReload(data);
               },
               error: function (errdata) {
                  self._hideBusy();
                  self._showError((errdata && errdata[0] && errdata[0].responseJSON && errdata[0].responseJSON.message) ? errdata[0].responseJSON.message : self.options.messages.serverCommunicationError);
               }
            });

         }
      },
      /* Performs load of single row
        *************************************************************************/
      _reloadProperty: function (row, property, completeCallback) {
         var self = this;

         var completeReload = function (data) {

            //Re-generate table row
            var $newRow = self._createRowFromRecord(data);

            // Perform bootstrap reload
            self._rowBootstrapAdjustments($newRow);

            // Replace property/properties
            if (!Array.isArray(property))
               property = [property];

            property.forEach((elem) => {
               row.find('[data-jtable-fieldname="' + elem + '"]').replaceWith($newRow.find('[data-jtable-fieldname="' + elem + '"]'));
            });

            // Update record
            row.data('record', data);

            self._hideBusy();

            self._onPropertyLoaded(row, property, data);

            //Call complete callback
            if (completeCallback) {
               completeCallback();
            }
         };

         self._showBusy(self.options.messages.loadingMessage, self.options.loadingAnimationDelay); //Disable table since it's busy
         self._onLoadingRecords();

         //listAction may be a function, check if it is
         if ($.isFunction(self.options.actions.listAction)) {

            //Execute the function
            var funcResult = self.options.actions.listAction(self._lastPostData, $.extend({id: row.data('record-key')}, self._createJtParamsForLoading()));

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               funcResult.done(function (data) {
                  completeReload(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
               }).always(function () {
                  self._hideBusy();
               });
            } else { //assume it's the data we're loading
               completeReload(funcResult);
            }

         } else { //assume listAction as URL string.

            //Generate URL (with query string parameters) to load records
            var loadUrl = self._createRecordLoadUrl(row.data('record-key'));

            //Load data from server using AJAX
            self._ajax({
               url: loadUrl,
               data: self._lastPostData,
               method: 'GET',
               success: function (data) {
                  completeReload(data);
               },
               error: function (errdata) {
                  self._hideBusy();
                  self._showError((errdata && errdata[0] && errdata[0].responseJSON && errdata[0].responseJSON.message) ? errdata[0].responseJSON.message : self.options.messages.serverCommunicationError);
               }
            });

         }
      },

      /* Performs load of single row
        *************************************************************************/
      _reloadRow: function (row, completeCallback) {
         var self = this;

         var completeReload = function (data) {
            //Re-generate table rows
            var $newRow = self._createRowFromRecord(data);
            self._disposeTooltips((row && row[0]) ? row[0] : null);
            self._disposeCollapses((row && row[0]) ? row[0] : null);
            row.replaceWith($newRow);

            // Perform bootstrap reload
            self._rowBootstrapAdjustments($newRow);

            // Perform any markup
            self._refreshRowStyles();

            // Check if new row was opened
            if (row.children('button.accordion-button:not(.collapsed)').length) {
               $newRow.children('.accordion-button').removeClass('collapsed');
               $newRow.children('.accordion-collapse').removeClass('collapse').addClass('show');
            }

            self._hideBusy();

            self._onRowLoaded($newRow, data);

            //Call complete callback
            if (completeCallback) {
               completeCallback();
            }
         };

         self._showBusy(self.options.messages.loadingMessage, self.options.loadingAnimationDelay); //Disable table since it's busy
         self._onLoadingRecords();

         //listAction may be a function, check if it is
         if ($.isFunction(self.options.actions.listAction)) {

            //Execute the function
            var funcResult = self.options.actions.listAction(self._lastPostData, $.extend({id: row.data('record-key')}, self._createJtParamsForLoading()));

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               funcResult.done(function (data) {
                  completeReload(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
               }).always(function () {
                  self._hideBusy();
               });
            } else { //assume it's the data we're loading
               completeReload(funcResult);
            }

         } else { //assume listAction as URL string.

            //Generate URL (with query string parameters) to load records
            var loadUrl = self._createRecordLoadUrl(row.data('record-key'));

            //Load data from server using AJAX
            self._ajax({
               url: loadUrl,
               data: self._lastPostData,
               method: 'GET',
               global: false,
               success: function (data) {
                  completeReload(data);
               },
               error: function (errdata) {
                  self._hideBusy();
                  if (errdata && errdata[0] && errdata[0].status && ((403 == errdata[0].status) || (404 == errdata[0].status))) {
                     self.reload();
                  } else
                     self._showError((errdata && errdata[0] && errdata[0].responseJSON && errdata[0].responseJSON.message) ? errdata[0].responseJSON.message : self.options.messages.serverCommunicationError);
               },
            });

         }
      },


      /* Performs an AJAX call to reload data of the table.
        *************************************************************************/
      _reloadTable: function (completeCallback) {
         var self = this;

         var completeReload = function (data) {

            if (self.options.loadid && !Array.isArray(data))
               data = [data];

            self._hideBusy();

            //Re-generate table rows
            self._removeAllRows('reloading');
            self._addRecordsToTable(data.data ? data.data : data);

            self._onRecordsLoaded(data);

            //Call complete callback
            if (completeCallback) {
               completeCallback();
            }
         };

         self._showBusy(self.options.messages.loadingMessage, self.options.loadingAnimationDelay); //Disable table since it's busy
         self._onLoadingRecords();

         //listAction may be a function, check if it is
         if ($.isFunction(self.options.actions.listAction)) {

            //Execute the function
            var funcResult = self.options.actions.listAction(self._lastPostData, self._createJtParamsForLoading());

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               funcResult.done(function (data) {
                  completeReload(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
               }).always(function () {
                  self._hideBusy();
               });
            } else { //assume it's the data we're loading
               completeReload(funcResult);
            }

         } else { //assume listAction as URL string.

            //Generate URL (with query string parameters) to load records
            var loadUrl = self._createRecordLoadUrl();

            //Load data from server using AJAX
            self._ajax({
               url: loadUrl,
               data: self._lastPostData,
               method: 'GET',
               success: function (data) {
                  completeReload(data);
               },
               error: function (errdata) {
                  self._hideBusy();
                  self._showError((errdata && errdata[0] && errdata[0].responseJSON && errdata[0].responseJSON.message) ? errdata[0].responseJSON.message : self.options.messages.serverCommunicationError);
               }
            });

         }
      },

      /* Creates URL to load records.
        *************************************************************************/
      _createRecordLoadUrl: function (singleid = null) {
         var self = this;
         var loadUrl = this.options.actions.listAction;

         if (self.options.loadid || singleid) {
            var singleLoadId = self.options.loadid ? self.options.loadid : singleid;

            if (loadUrl.indexOf('?') > -1) {
               var urlPart = loadUrl.substring(0, loadUrl.indexOf('?'));
               var paramPart = loadUrl.substring(loadUrl.indexOf('?'));
               loadUrl = urlPart + '/' + singleLoadId + paramPart;
            } else
               loadUrl += '/' + singleLoadId;

            return loadUrl;
         }

         if (self.options.filter) {
            // Special case when explicit id is set
            if(self._$titleDiv.find('.jtable-filter-pills .badge[data-filter-key="id"]').length)
               loadUrl = loadUrl + (loadUrl.indexOf('?') < 0 ? '?' : '&') + 'id=' + self._$titleDiv.find('.jtable-filter-pills .badge[data-filter-key="id"]').attr('data-filter-value');
            else {
               $.each(self.options.filter, function (key, filter) {
                  var filterBadge = self._$titleDiv.find('.jtable-filter-pills .badge[data-filter-key=\'' + key + '\']');
                  if (0 != filterBadge.length) {
                     switch (filter.type) {
                        case 'checkbox':
                        case 'hidden':
                        case 'select':
                        case 'date':
                           loadUrl = loadUrl + (loadUrl.indexOf('?') < 0 ? '?' : '&') + key + '=' + self._$titleDiv.find('.jtable-filter-pills .badge[data-filter-key=\'' + key + '\']').attr('data-filter-value');
                           break;
                     }
                  }
               });
            }
         }

         if (self.options.searchfield) {
            var searchString = self._$titleDiv.find('div.jtable-search-field input').val().trim();

            if ("" != searchString)
               loadUrl = loadUrl + (loadUrl.indexOf('?') < 0 ? '?' : '&') + 'search=' + searchString;
         }

         return loadUrl;
      },

      _createJtParamsForLoading: function () {
         return {
            //Empty as default, paging, sorting or other extensions can override this method to add additional params to load request
         };
      },

      /* TABLE MANIPULATION METHODS *******************************************/

      /* Creates a row from given record
        *************************************************************************/
      _createRowFromRecord: function (record) {
         var $tr = $(this.options.bootstrap ? '<div></div>' : '<tr></tr>')
            .addClass('jtable-data-row')
            .attr('data-record-key', this._getKeyValueOfRecord(record))
            .data('record', record);
         this._addCellsToRowUsingRecord($tr, record);
         return $tr;
      },

      /* Adds all cells to given row.
        *************************************************************************/
      _addCellsToRowUsingRecord: function ($row, record) {
         var record = $row.data('record');

         for (var i = 0; i < this._columnList.length; i++) {
            this._createCellForRecordField(record, this._columnList[i])
               .appendTo($row);
         }
      },

      /* Create a cell for given field.
        *************************************************************************/
      _createCellForRecordField: function (record, fieldName) {
         var retobj = null;
         if ('command' == this.options.fields[fieldName].type) {
            retobj = $(this.options.bootstrap ? '<div></div>' : '<td></td>')
               .addClass('jtable-command-column')
               .addClass(this.options.fields[fieldName].listClass)
               .attr('data-jtable-fieldname', fieldName)
               .append((this._getDisplayTextForRecordField(record, fieldName)));
         } else {
            retobj = $(this.options.bootstrap ? '<div></div>' : '<td></td>')
               .addClass(this.options.fields[fieldName].listClass)
               .addClass('jtable-field')
               .addClass(('undefined' == typeof this.options.fields[fieldName].type) ? 'jtable-field-text' : 'jtable-field-' + this.options.fields[fieldName].type)
               .attr('data-jtable-fieldname', fieldName)
               .append((this._getDisplayTextForRecordField(record, fieldName)));
         }

         if (this.options.fields[fieldName].title)
            retobj.prepend($('<span />').addClass('jtable-field-label').text(this.options.fields[fieldName].title));

         return retobj;
      },

      /* Adds a list of records to the table.
        *************************************************************************/
      _addRecordsToTable: function (records) {
         var self = this;

         $.each(records, function (index, record) {
            var row = self._createRowFromRecord(record);
            self._addRow(row);
            self._onRowLoaded(row, record);
         });

         if (self.options.bootstrap && self.options.accordion && (self.options.openfirstchild || self.options.loadid || (self._$tableRows.length == 1))) {
            if (self._$tableRows.length) {
               $(self._$tableRows[0]).find('.accordion-button').removeClass('collapsed');
               $(self._$tableRows[0]).find('.accordion-collapse').addClass('show');
            }
         }

         self._refreshRowStyles();

         // Re-order functionality
         if (self.options.actions.reorderAction) {
            // Make first column the handle column
            $(self._$table).find('tbody > tr.jtable-data-row > td:first-child').each(function () {
               $(this).prepend($('<span />')
                  .addClass('jtable-sortable-handle')
               );
            });

            $(self._$table).find('tbody').sortable({
               update: function (event, ui) {
                  // Get position of moved item and its predecessor
                  var itemkey = $(ui.item[0]).attr('data-record-key');
                  var prevkey = $(ui.item[0]).prev();
                  if (prevkey.length == 0)
                     prevkey = null;
                  else
                     prevkey = prevkey.attr('data-record-key');

                  //reorderAction may be a function, check if it is
                  if ($.isFunction(self.options.actions.reorderAction)) {

                     //Execute the function
                     var funcResult = self.options.actions.reorderAction($.param({id: itemkey, after: prevkey}));

                     //Check if result is a jQuery Deferred object
                     if (self._isDeferredObject(funcResult)) {
                        //Wait promise
                        funcResult.done(function (data) {
                           self.reload();
                        }).fail(function () {
                           self._hideBusy();
                           self._showError(self.options.messages.serverCommunicationError);
                        });
                     }
                  } else { //Assume it's a URL string and perform an ajax call
                     // Set id on url
                     var updateUrl = self.options.actions.reorderAction;

                     if (null != itemkey) {
                        if (updateUrl.indexOf('?') > -1) {
                           var urlPart = updateUrl.substring(0, updateUrl.indexOf('?'));
                           var paramPart = updateUrl.substring(updateUrl.indexOf('?'));
                           updateUrl = urlPart + '/' + itemkey + '/reorder' + paramPart;
                        } else
                           updateUrl += '/' + itemkey + '/reorder';
                     }

                     self._ajax({
                        url: updateUrl,
                        method: 'POST',
                        data: {
                           after: prevkey
                        },
                        success: function (data) {
                           self.reload();
                        },
                        error: function () {
                           self._hideBusy();
                           self._showError(self.options.messages.serverCommunicationError);
                        }
                     });
                  }
               },
               helper: function (e, ui) {
                  ui.children().each(function () {
                     $(this).width($(this).width());
                  });
                  return ui;
               },
               handle: ".jtable-sortable-handle",
            });
         }
      },

      /* Adds a single row to the table.
        * NOTE: THIS METHOD IS DEPRECATED AND WILL BE REMOVED FROM FEATURE RELEASES.
        * USE _addRow METHOD.
        *************************************************************************/
      _addRowToTable: function ($tableRow, index, isNewRow, animationsEnabled) {
         var options = {
            index: this._normalizeNumber(index, 0, this._$tableRows.length, this._$tableRows.length)
         };

         if (isNewRow == true) {
            options.isNewRow = true;
         }

         if (animationsEnabled == false) {
            options.animationsEnabled = false;
         }

         this._addRow($tableRow, options);

      },

      /* Adds a single row to the table.
        *************************************************************************/
      _addRow: function ($row, options) {
         //Set defaults
         options = $.extend({
            index: this._$tableRows.length,
            isNewRow: false,
            animationsEnabled: true
         }, options);

         //Remove 'no data' row if this is first row
         if (this._$tableRows.length <= 0) {
            this._removeNoDataRow();
         }

         //Add new row to the table according to it's index
         options.index = this._normalizeNumber(options.index, 0, this._$tableRows.length, this._$tableRows.length);
         if (options.index == this._$tableRows.length) {
            //add as last row
            this._$tableBody.append($row);
            this._$tableRows.push($row);
         } else if (options.index == 0) {
            //add as first row
            this._$tableBody.prepend($row);
            this._$tableRows.unshift($row);
         } else {
            //insert to specified index
            this._$tableRows[options.index - 1].after($row);
            this._$tableRows.splice(options.index, 0, $row);
         }

         this._onRowInserted($row, options.isNewRow);

         //Show animation if needed
         if (options.isNewRow) {
            this._refreshRowStyles();
            if (this.options.animationsEnabled && options.animationsEnabled) {
               this._showNewRowAnimation($row);
            }
         }

         this._rowBootstrapAdjustments($row);
      },

      /* Perform necessary adjustments on row for bootstrap
        *************************************************************************/
      _rowBootstrapAdjustments: function ($row) {
         var self = this;

         if (!self.options.bootstrap)
            return;


         var headerDiv = (self.options.accordion ? $('<h2></h2>').addClass('accordion-header') : $('<div></div>').addClass('card-header'));
         var bodyDiv = (self.options.accordion ? $('<div></div>').addClass('accordion-body') : $('<div></div>').addClass('card-body'));
         var footerDiv = (self.options.accordion ? $('<div></div>').addClass('accordion-footer d-inline-block') : $('<div></div>').addClass('card-footer d-inline-block'));

         // If no header is defined, then simply use first visible field as header field
         var hasHeader = false;
         Object.values(self.options.fields).forEach((element) => {
            if (('undefined' != typeof element.header) && element.header) {
               hasHeader = true;
            }
         });

         if (!hasHeader)
            self.options.fields[$row.find('div.jtable-field').first().data('jtable-fieldname')].header = true;

         Object.keys(self.options.fields).forEach((key) => {
            var fieldOptions = self.options.fields[key];

            // Re-arrange fields
            if (fieldOptions.header)
               $row.find('div[data-jtable-fieldname="' + key + '"]').appendTo(headerDiv);
            else if (fieldOptions.body)
               $row.find('div[data-jtable-fieldname="' + key + '"]').appendTo(bodyDiv);
            else if (fieldOptions.footer)
               $row.find('div[data-jtable-fieldname="' + key + '"]').appendTo(footerDiv);
         });

         // Re-arrange edit/delete commands
         $row.find('div.jtable-edit-command, div.jtable-delete-command').appendTo(footerDiv);

         $row
            .addClass(self.options.accordion ? 'accordion-item' : 'card');

         if (self.options.accordion) {
            $row.append($('<button type="button" data-bs-toggle="collapse" data-bs-target="#jtable-row-' + self.options.tableId + '-' + $row.data('record-key') + '"></button>')
               .addClass('accordion-button collapsed')
               .append(headerDiv));

            footerDiv.appendTo(bodyDiv);

            $row.append($('<div></div>')
               .addClass('accordion-collapse collapse')
               .attr('id', 'jtable-row-' + self.options.tableId + '-' + $row.data('record-key'))
               .append(bodyDiv));
         } else {
            $row.append(headerDiv);
            $row.append(bodyDiv);
            $row.append(footerDiv);
         }

         var rowRecord = $row.data('record');
         if (('undefined' != typeof rowRecord) &&
            ('undefined' != typeof rowRecord.status) &&
            ('undefined' != typeof rowRecord.status.icon) &&
            ('undefined' != typeof rowRecord.status.level) &&
            ('' != rowRecord.status.icon) &&
            ('' != rowRecord.status.level)) {
            headerDiv.append($('<span />')
               .addClass('status-indicator')
               .addClass('status-level-' + rowRecord.status.level)
               .append($('<span />')
                  .addClass('material-symbols-rounded')
                  .addClass('justify-middle')
                  .text(rowRecord.status.icon)));
         }

         if (rowRecord.status && rowRecord.status.text && rowRecord.status.text.length) {
            if (Array.isArray(rowRecord.status.text)) {
               var container = $('<div />')
                  .addClass('status-alert-container')
                  .prependTo(bodyDiv);

               Object.values(rowRecord.status.text).forEach((text) => {
                  container.append($('<span />')
                     .addClass('status-text' + ((rowRecord.status.text.length > 1) ? ' status-text-array-item' : ''))
                     .text(text));
               });
            } else {
               bodyDiv.prepend($('<div />')
                  .addClass('status-alert-container')
                  .append($('<span />')
                     .addClass('status-text')
                     .text(rowRecord.status.text)));
            }
         }
      },

      /* Shows created animation for a table row
        *************************************************************************/
      _showNewRowAnimation: function ($tableRow) {
         var className = 'jtable-row-created';

         $tableRow.addClass(className, 'slow', '', function () {
            $tableRow.removeClass(className, 5000);
         });
      },

      /* Removes a row or rows (jQuery selection) from table.
        *************************************************************************/
      _removeRowsFromTable: function ($rows, reason) {
         var self = this;

         //Check if any row specified
         if ($rows.length <= 0) {
            return;
         }

         //remove from DOM
         $rows.each(function () {
            self._disposeTooltips(this);
            self._disposeCollapses(this);
         });
         $rows.addClass('jtable-row-removed').remove();

         //remove from _$tableRows array
         $rows.each(function () {
            var index = self._findRowIndex($(this));
            if (index >= 0) {
               self._$tableRows.splice(index, 1);
            }
         });

         self._onRowsRemoved($rows, reason);

         //Add 'no data' row if all rows removed from table
         if (self._$tableRows.length == 0) {
            self._addNoDataRow();
         }

         self._refreshRowStyles();
      },

      /* Finds index of a row in table.
        *************************************************************************/
      _findRowIndex: function ($row) {
         return this._findIndexInArray($row, this._$tableRows, function ($row1, $row2) {
            return $row1.data('record') == $row2.data('record');
         });
      },

      /* Removes all rows in the table and adds 'no data' row.
        *************************************************************************/
      _removeAllRows: function (reason) {
         //If no rows does exists, do nothing
         if (this._$tableRows.length <= 0) {
            return;
         }

         this._$tableBody.find('.jtable-child-table-container').each(function () {
            if ($(this).data('hik-jtable')) {
               $(this).jtable('destroy');
            }
         });

         this._$tableBody.find('.jtable-data-row').each((index, row) => {
            this._disposeTooltips(row);
            this._disposeCollapses(row);
         });

         //Select all rows (to pass it on raising _onRowsRemoved event)
         var $rows = this._$tableBody.find('tr.jtable-data-row');

         //Remove all rows from DOM and the _$tableRows array
         this._$tableBody.empty();
         this._$tableRows = [];

         this._onRowsRemoved($rows, reason);

         //Add 'no data' row since we removed all rows
         this._addNoDataRow();
      },

      /* Adds "no data available" row to the table.
        *************************************************************************/
      _addNoDataRow: function () {

         if (this.options.bootstrap) {
            $('<div></div>')
               .addClass('jtable-no-data-row')
               .html(this.options.messages.noDataAvailable)
               .appendTo(this._$tableBody);
         } else {
            if (this._$tableBody.find('>.jtable-no-data-row').length > 0) {
               return;
            }

            var $tr = $('<tr></tr>')
               .addClass('jtable-no-data-row')
               .appendTo(this._$tableBody);

            var totalColumnCount = this._$table.find('thead th').length;
            $('<td></td>')
               .attr('colspan', totalColumnCount)
               .html(this.options.messages.noDataAvailable)
               .appendTo($tr);
         }
      },

      /* Removes "no data available" row from the table.
        *************************************************************************/
      _removeNoDataRow: function () {
         this._$tableBody.find('.jtable-no-data-row').remove();
      },

      /* Refreshes styles of all rows in the table
        *************************************************************************/
      _refreshRowStyles: function () {
         for (var i = 0; i < this._$tableRows.length; i++) {
            if (i % 2 == 0) {
               this._$tableRows[i].addClass('jtable-row-even');
            } else {
               this._$tableRows[i].removeClass('jtable-row-even');
            }
         }
      },

      /* RENDERING FIELD VALUES ***********************************************/

      /* Gets text for a field of a record according to it's type.
        *************************************************************************/
      _getDisplayTextForRecordField: function (record, fieldName) {
         var field = this.options.fields[fieldName];
         var fieldValue = record[fieldName];

         //if this is a custom field, call display function
         if (field.display) {
            return ($('<div />').addClass('jtable-cell-content').append(field.display({record: record})));
         }

         var displaytext = null;

         if (field.type == 'date') {
            displaytext = this._getDisplayTextForDateRecordField(field, fieldValue);
         } else if (field.type == 'checkbox') {
            displaytext = this._getCheckBoxTextForFieldByValue(fieldName, fieldValue);
         } else if (field.options) { // Select
            var options = this._getOptionsForField(fieldName, {
               record: record,
               value: fieldValue,
               source: 'list',
               dependedValues: this._createDependedValuesUsingRecord(record, field.dependsOn)
            });

            if (!Array.isArray(fieldValue)) {
               var option = $('<span />')
                  .addClass('jtable-display-select-option')
                  .text(this._findOptionByValue(options, fieldValue).DisplayText);

               var tooltip = (this._findOptionByValue(options, fieldValue).Tooltip);
               if (('undefined' !== typeof tooltip) && tooltip) {
                  option
                     .addClass('hasdescription')
                     .attr('data-bs-toggle', 'tooltip')
                     .attr('data-bs-placement', 'left')
                     .attr('data-bs-title', tooltip);
               }

               displaytext = $('<div />')
                  .addClass('jtable-display-select-options')
                  .append(option);
            } else // Multiple selections
            {
               var displaytext = $('<div />').addClass('jtable-display-select-options');
               fieldValue.forEach((obj) => {
                  // Transform field value if it is an arrray with id parameter
                  if ('undefined' !== typeof obj['id'])
                     obj = obj['id'];

                  var option = $('<span />')
                     .addClass('jtable-display-select-option')
                     .text(this._findOptionByValue(options, obj).DisplayText);

                  var tooltip = (this._findOptionByValue(options, obj).Tooltip);
                  if (('undefined' !== typeof tooltip) && tooltip) {
                     option
                        .addClass('hasdescription')
                        .attr('data-bs-toggle', 'tooltip')
                        .attr('data-bs-placement', 'left')
                        .attr('data-bs-title', tooltip);
                  }

                  displaytext.append(option);
               });
            }
         } else { //other types

            const map = {
               '&': '&amp;',
               '<': '&lt;',
               '>': '&gt;',
               '"': '&quot;',
               "'": '&#039;'
            };

            displaytext = ('string' == typeof fieldValue) ? fieldValue.replace(/[&<>"']/g, function (m) {
               return map[m];
            }) : fieldValue;
         }

         var retobj = $('<span />')
            .addClass('jtable-cell-content')
            .append(displaytext);

         return retobj;
      },

      /* Creates and returns an object that's properties are depended values of a record.
        *************************************************************************/
      _createDependedValuesUsingRecord: function (record, dependsOn) {
         if (!dependsOn) {
            return {};
         }

         var dependedValues = {};
         for (var i = 0; i < dependsOn.length; i++) {
            dependedValues[dependsOn[i]] = record[dependsOn[i]];
         }

         return dependedValues;
      },

      /* Finds an option object by given value.
        *************************************************************************/
      _findOptionByValue: function (options, value) {
         // Check if optgroup
         if (options.length && options[0].Label) {
            for (var i = 0; i < options.length; i++) {
               if (!options[i].Children)
                  continue;

               for (var j = 0; j < options[i].Children.length; j++) {
                  if (options[i].Children[j].Value == value)
                     return options[i].Children[j];
               }
            }
         } else {
            for (var i = 0; i < options.length; i++) {
               if (options[i].Value == value) {
                  return options[i];
               }
            }
         }
         return {}; //no option found
      },

      /* Gets text for a date field.
        *************************************************************************/
      _getDisplayTextForDateRecordField: function (field, fieldValue) {
         if (!fieldValue) {
            return '';
         }

         var date = this._parseDate(fieldValue);

         var year = '' + date.getFullYear();
         var month = '' + (date.getMonth() + 1);
         var day = '' + date.getDate();

         if (month.length == 1)
            month = '0' + month;

         if (day.length == 1)
            day = '0' + day;

         return year + '-' + month + '-' + day;
      },

      /* Gets options for a field according to user preferences.
        *************************************************************************/
      _getOptionsForField: function (fieldName, funcParams) {
         var field = this.options.fields[fieldName];
         var optionsSource = field.options;

         if ($.isFunction(optionsSource)) {
            //prepare parameter to the function
            funcParams = $.extend(true, {
               _cacheCleared: false,
               dependedValues: {},
               clearCache: function () {
                  this._cacheCleared = true;
               }
            }, funcParams);

            //call function and get actual options source
            optionsSource = optionsSource(funcParams);
         }

         var options;

         //Build options according to it's source type
         if (typeof optionsSource == 'string') { //It is an Url to download options
            var cacheKey = 'options_' + fieldName + '_' + optionsSource; //create a unique cache key
            if (funcParams._cacheCleared || (!this._cache[cacheKey])) {
               //if user calls clearCache() or options are not found in the cache, download options
               this._cache[cacheKey] = this._buildOptionsFromArray(this._downloadOptions(fieldName, optionsSource));
               this._sortFieldOptions(this._cache[cacheKey], field.optionsSorting);
            } else {
               //found on cache..
               //if this method (_getOptionsForField) is called to get option for a specific value (on funcParams.source == 'list')
               //and this value is not in cached options, we need to re-download options to get the unfound (probably new) option.
               if (funcParams.value != undefined) {
                  var optionForValue = this._findOptionByValue(this._cache[cacheKey], funcParams.value);
                  if (optionForValue.DisplayText == undefined) { //this value is not in cached options...
                     this._cache[cacheKey] = this._buildOptionsFromArray(this._downloadOptions(fieldName, optionsSource));
                     this._sortFieldOptions(this._cache[cacheKey], field.optionsSorting);
                  }
               }
            }

            options = this._cache[cacheKey];
         } else if (jQuery.isArray(optionsSource)) { //It is an array of options
            options = this._buildOptionsFromArray(optionsSource);
            this._sortFieldOptions(options, field.optionsSorting);
         } else { //It is an object that it's properties are options
            options = this._buildOptionsArrayFromObject(optionsSource);
            this._sortFieldOptions(options, field.optionsSorting);
         }

         return options;
      },

      /* Download options for a field from server.
        *************************************************************************/
      _downloadOptions: function (fieldName, url) {
         var self = this;
         var options = [];

         self._ajax({
            url: url,
            method: 'GET',
            async: false,
            success: function (data) {
               Object.values(data).forEach((obj) => {
                  options.push({Value: obj.id, DisplayText: obj.name});
               });
            },
            error: function () {
               var errMessage = self._formatString(self.options.messages.cannotLoadOptionsFor, fieldName);
               self._showError(errMessage);
            }
         });

         return options;
      },

      /* Sorts given options according to sorting parameter.
        *  sorting can be: 'value', 'value-desc', 'text' or 'text-desc'.
        *************************************************************************/
      _sortFieldOptions: function (options, sorting) {

         if ((!options) || (!options.length) || (!sorting)) {
            return;
         }

         //Determine using value of text
         var dataSelector;
         if (sorting.indexOf('value') == 0) {
            dataSelector = function (option) {
               return option.Value;
            };
         } else { //assume as text
            dataSelector = function (option) {
               return option.DisplayText;
            };
         }

         var compareFunc;
         if ($.type(dataSelector(options[0])) == 'string') {
            compareFunc = function (option1, option2) {
               return dataSelector(option1).localeCompare(dataSelector(option2));
            };
         } else { //asuume as numeric
            compareFunc = function (option1, option2) {
               return dataSelector(option1) - dataSelector(option2);
            };
         }

         if (sorting.indexOf('desc') > 0) {
            options.sort(function (a, b) {
               return compareFunc(b, a);
            });
         } else { //assume as asc
            options.sort(function (a, b) {
               return compareFunc(a, b);
            });
         }
      },

      /* Creates an array of options from given object.
        *************************************************************************/
      _buildOptionsArrayFromObject: function (options) {
         var list = [];

         $.each(options, function (propName, propValue) {
            list.push({
               Value: propName,
               DisplayText: propValue
            });
         });

         return list;
      },

      /* Creates array of options from giving options array.
        *************************************************************************/
      _buildOptionsFromArray: function (optionsArray) {
         var list = [];

         for (var i = 0; i < optionsArray.length; i++) {
            if ($.isPlainObject(optionsArray[i])) {
               list.push(optionsArray[i]);
            } else { //assumed as primitive type (int, string...)
               list.push({
                  Value: optionsArray[i],
                  DisplayText: optionsArray[i]
               });
            }
         }

         return list;
      },

      /* Parses given date string to a javascript Date object.
        *  Given string must be formatted one of the samples shown below:
        *  /Date(1320259705710)/
        *  2011-01-01 20:32:42 (YYYY-MM-DD HH:MM:SS)
        *  2011-01-01 (YYYY-MM-DD)
        *************************************************************************/
      _parseDate: function (dateString) {
         if (dateString.indexOf('Date') >= 0) { //Format: /Date(1320259705710)/
            return new Date(
               parseInt(dateString.substr(6), 10)
            );
         } else if (dateString.length == 10) { //Format: 2011-01-01
            return new Date(
               parseInt(dateString.substr(0, 4), 10),
               parseInt(dateString.substr(5, 2), 10) - 1,
               parseInt(dateString.substr(8, 2), 10)
            );
         } else if (dateString.length == 19) { //Format: 2011-01-01 20:32:42
            return new Date(
               parseInt(dateString.substr(0, 4), 10),
               parseInt(dateString.substr(5, 2), 10) - 1,
               parseInt(dateString.substr(8, 2, 10)),
               parseInt(dateString.substr(11, 2), 10),
               parseInt(dateString.substr(14, 2), 10),
               parseInt(dateString.substr(17, 2), 10)
            );
         } else {
            this._logWarn('Given date is not properly formatted: ' + dateString);
            return 'format error!';
         }
      },

      /* TOOL BAR *************************************************************/

      /* Creates the toolbar.
        *************************************************************************/
      _createToolBar: function () {
         this._$toolbarDiv = $('<div />')
            .addClass('jtable-toolbar')
            .appendTo(this._$titleDiv);

         for (var i = 0; i < this.options.toolbar.items.length; i++) {
            this._addToolBarItem(this.options.toolbar.items[i]);
         }
      },

      /* Adds a new item to the toolbar.
        *************************************************************************/
      _addToolBarItem: function (item) {

         //Check if item is valid
         if ((item == undefined) || (item.text == undefined && item.icon == undefined)) {
            this._logWarn('Can not add tool bar item since it is not valid!');
            this._logWarn(item);
            return null;
         }

         var $toolBarItem = $('<button></button>')
            .addClass('jtable-toolbar-item')
            .appendTo(this._$toolbarDiv);

         //text property
         if (item.text) {
            $toolBarItem.text(item.text);
         }

         //icon property
         if (item.icon) {
            $('<span class="material-symbols-rounded"></span>')
               .html(item.icon)
               .prependTo($toolBarItem);
         }


         //cssClass property
         if (item.cssClass) {
            $toolBarItem
               .addClass(item.cssClass);
         }

         //tooltip property
         if (item.tooltip) {
            $toolBarItem
               .attr('title', item.tooltip);
         }


         //click event
         if (item.click) {
            $toolBarItem.click(function () {
               item.click($toolBarItem);
            });
         }

         //set hover animation parameters
         var hoverAnimationDuration = undefined;
         var hoverAnimationEasing = undefined;
         if (this.options.toolbar.hoverAnimation) {
            hoverAnimationDuration = this.options.toolbar.hoverAnimationDuration;
            hoverAnimationEasing = this.options.toolbar.hoverAnimationEasing;
         }

         //change class on hover
         $toolBarItem.hover(function () {
            $toolBarItem.addClass('jtable-toolbar-item-hover', hoverAnimationDuration, hoverAnimationEasing);
         }, function () {
            $toolBarItem.removeClass('jtable-toolbar-item-hover', hoverAnimationDuration, hoverAnimationEasing);
         });

         return $toolBarItem;
      },

      /* ERROR DIALOG *********************************************************/

      /* Shows error message dialog with given message.
        *************************************************************************/
      _showError: function (message) {
         //window.showDialog(this.options.messages.error, message);
      },

      /* BUSY PANEL ***********************************************************/

      /* Shows busy indicator and blocks table UI.
        * TODO: Make this cofigurable and changable
        *************************************************************************/
      _setBusyTimer: null,
      _showBusy: function (message, delay) {
         var self = this;  //

         //Show a transparent overlay to prevent clicking to the table
         self._$busyDiv
            .width(self._$mainContainer.width())
            .height(self._$mainContainer.height())
            .addClass('jtable-busy-panel-background-invisible')
            .show();

         var makeVisible = function () {
            self._$busyDiv.removeClass('jtable-busy-panel-background-invisible');
            self._$busyMessageDiv.html(message).show();
         };

         if (delay) {
            if (self._setBusyTimer) {
               return;
            }

            self._setBusyTimer = setTimeout(makeVisible, delay);
         } else {
            makeVisible();
         }
      },

      /* Hides busy indicator and unblocks table UI.
        *************************************************************************/
      _hideBusy: function () {
         clearTimeout(this._setBusyTimer);
         this._setBusyTimer = null;
         this._$busyDiv.hide();
         this._$busyMessageDiv.html('').hide();
      },

      /* Returns true if jTable is busy.
        *************************************************************************/
      _isBusy: function () {
         return this._$busyMessageDiv.is(':visible');
      },


      /* COMMON METHODS *******************************************************/

      /* Performs an AJAX call to specified URL.
        * THIS METHOD IS DEPRECATED AND WILL BE REMOVED FROM FEATURE RELEASES.
        * USE _ajax METHOD.
        *************************************************************************/
      _performAjaxCall: function (url, postData, async, success, error) {
         this._ajax({
            url: url,
            data: postData,
            async: async,
            success: success,
            error: error
         });
      },

      _unAuthorizedRequestHandler: function () {
         if (this.options.unAuthorizedRequestRedirectUrl) {
            location.href = this.options.unAuthorizedRequestRedirectUrl;
         } else {
            location.reload(true);
         }
      },

      /* This method is used to perform AJAX calls in jTable instead of direct
        * usage of jQuery.ajax method.
        *************************************************************************/
      _ajax: function (options) {
         var self = this;

         //Handlers for HTTP status codes
         var opts = {
            statusCode: {
               401: function () { //Unauthorized
                  self._unAuthorizedRequestHandler();
               }
            }
         };

         opts = $.extend(opts, this.options.ajaxSettings, options);

         //Override success
         opts.success = function (data) {
            //Checking for Authorization error
            if (data && data.UnAuthorizedRequest == true) {
               self._unAuthorizedRequestHandler();
            }

            if (options.success) {
               options.success(data);
            }
         };

         //Override error
         opts.error = function (jqXHR, textStatus, errorThrown) {
            if (unloadingPage) {
               jqXHR.abort();
               return;
            }

            if (options.error) {
               options.error(arguments);
            }
         };

         //Override complete
         opts.complete = function () {
            if (options.complete) {
               options.complete();
            }
         };

         ajaxCall(opts);
      },

      /* Gets value of key field of a record.
        *************************************************************************/
      _getKeyValueOfRecord: function (record) {
         return record[this._keyField];
      },

      /************************************************************************
       * COOKIE                                                                *
       *************************************************************************/

      /* Sets a cookie with given key.
        *************************************************************************/
      _setCookie: function (key, value) {
         var expireDate = new Date();
         key = this.options.tableId + '-' + key;
         expireDate.setDate(expireDate.getDate() + 30);
         document.cookie = encodeURIComponent(key) + '=' + encodeURIComponent(value) + "; expires=" + expireDate.toUTCString() + "; SameSite=Strict";
      },

      /* Gets a cookie with given key.
        *************************************************************************/
      _getCookie: function (key) {
         key = this.options.tableId + '-' + key;
         var equalities = document.cookie.split('; ');
         for (var i = 0; i < equalities.length; i++) {
            if (!equalities[i]) {
               continue;
            }

            var splitted = equalities[i].split('=');
            if (splitted.length != 2) {
               continue;
            }

            if (decodeURIComponent(splitted[0]) === key) {
               return decodeURIComponent(splitted[1] || '');
            }
         }

         return null;
      },

      /* Gets all cookies with the given prefix.
        *************************************************************************/
      _getPrefixedCookies: function (key) {
         key = this.options.tableId + '-' + key;
         var cookies = [];
         var equalities = document.cookie.split('; ');
         for (var i = 0; i < equalities.length; i++) {
            if (!equalities[i]) {
               continue;
            }

            var splitted = equalities[i].split('=');
            if (splitted.length != 2) {
               continue;
            }

            if (decodeURIComponent(splitted[0]).startsWith(key)) {
               cookies.push({
                  Key: decodeURIComponent(splitted[0]),
                  Value: decodeURIComponent(splitted[1] || '')
               });
            }
         }

         return cookies;
      },

      /* Delete a cookie with given key.
        *************************************************************************/
      _deleteCookie: function (key) {
         var expireDate = new Date(1970, 1, 1, 0, 0);
         document.cookie = encodeURIComponent(key) + '=0; expires="' + expireDate.toUTCString() + "; SameSite=Strict";
      },

      /************************************************************************
       * EVENT RAISING METHODS                                                 *
       *************************************************************************/

      _onLoadingRecords: function () {
         this._trigger("loadingRecords", null, {});
      },

      _onRecordsLoaded: function (data) {
         this._trigger("recordsLoaded", null, {records: data.Records, serverResponse: data});
      },

      _initializeTooltips: function (scopeElement) {
         if (('undefined' === typeof bootstrap) ||
            !bootstrap.Tooltip ||
            !bootstrap.Tooltip.getOrCreateInstance) {
            return;
         }

         var tooltipTriggerList = (scopeElement || document).querySelectorAll('[data-bs-toggle="tooltip"]');
         tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            bootstrap.Tooltip.getOrCreateInstance(tooltipTriggerEl);
         });
      },

      _disposeTooltips: function (scopeElement) {
         if (!scopeElement ||
            ('undefined' === typeof bootstrap) ||
            !bootstrap.Tooltip ||
            !bootstrap.Tooltip.getInstance) {
            return;
         }

         var tooltipTriggerList = scopeElement.querySelectorAll('[data-bs-toggle="tooltip"]');
         tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            var instance = bootstrap.Tooltip.getInstance(tooltipTriggerEl);
            if (instance && instance.dispose) {
               instance.dispose();
            }
         });
      },

      _disposeCollapses: function (scopeElement) {
         if (!scopeElement ||
            ('undefined' === typeof bootstrap) ||
            !bootstrap.Collapse ||
            !bootstrap.Collapse.getInstance) {
            return;
         }

         var collapseElements = scopeElement.querySelectorAll('.accordion-collapse');
         collapseElements.forEach(function (collapseElement) {
            var instance = bootstrap.Collapse.getInstance(collapseElement);
            if (instance && instance.dispose) {
               instance.dispose();
            }
         });
      },

      _onRowLoaded: function (row, data) {
         this._initializeTooltips((row && row[0]) ? row[0] : document);
         this._trigger("rowLoaded", null, {record: data});
      },

      _onPropertyLoaded: function (row, property, data) {
         this._initializeTooltips((row && row[0]) ? row[0] : document);
         this._trigger("propertyLoaded", null, {row: row, record: data, property: property});
      },

      _onStatusReloaded: function (row) {
         this._trigger("_onStatusReloaded", null, {row: row});
      },

      _onRowInserted: function ($row, isNewRow) {
         this._trigger("rowInserted", null, {row: $row, record: $row.data('record'), isNewRow: isNewRow});
      },

      _onRowsRemoved: function ($rows, reason) {
         this._trigger("rowsRemoved", null, {rows: $rows, reason: reason});
      },

      _onCloseRequested: function () {
         this._trigger("closeRequested", null, {});
      }

   });

}(jQuery));


/************************************************************************
 * Some UTULITY methods used by jTable                                   *
 *************************************************************************/
(function ($) {

   $.extend(true, $.hik.jtable.prototype, {

      /* Gets property value of an object recursively.
        *************************************************************************/
      _getPropertyOfObject: function (obj, propName) {
         if (propName.indexOf('.') < 0) {
            return obj[propName];
         } else {
            var preDot = propName.substring(0, propName.indexOf('.'));
            var postDot = propName.substring(propName.indexOf('.') + 1);
            return this._getPropertyOfObject(obj[preDot], postDot);
         }
      },

      /* Sets property value of an object recursively.
        *************************************************************************/
      _setPropertyOfObject: function (obj, propName, value) {
         if (propName.indexOf('.') < 0) {
            obj[propName] = value;
         } else {
            var preDot = propName.substring(0, propName.indexOf('.'));
            var postDot = propName.substring(propName.indexOf('.') + 1);
            this._setPropertyOfObject(obj[preDot], postDot, value);
         }
      },

      /* Inserts a value to an array if it does not exists in the array.
        *************************************************************************/
      _insertToArrayIfDoesNotExists: function (array, value) {
         if ($.inArray(value, array) < 0) {
            array.push(value);
         }
      },

      /* Finds index of an element in an array according to given comparision function
        *************************************************************************/
      _findIndexInArray: function (value, array, compareFunc) {

         //If not defined, use default comparision
         if (!compareFunc) {
            compareFunc = function (a, b) {
               return a == b;
            };
         }

         for (var i = 0; i < array.length; i++) {
            if (compareFunc(value, array[i])) {
               return i;
            }
         }

         return -1;
      },

      /* Normalizes a number between given bounds or sets to a defaultValue
        *  if it is undefined
        *************************************************************************/
      _normalizeNumber: function (number, min, max, defaultValue) {
         if (number == undefined || number == null || isNaN(number)) {
            return defaultValue;
         }

         if (number < min) {
            return min;
         }

         if (number > max) {
            return max;
         }

         return number;
      },

      /* Formats a string just like string.format in c#.
        *  Example:
        *  _formatString('Hello {0}','Halil') = 'Hello Halil'
        *************************************************************************/
      _formatString: function () {
         if (arguments.length == 0) {
            return null;
         }

         var str = arguments[0];
         for (var i = 1; i < arguments.length; i++) {
            var placeHolder = '{' + (i - 1) + '}';
            str = str.replace(placeHolder, arguments[i]);
         }

         return str;
      },

      /* Checks if given object is a jQuery Deferred object.
         */
      _isDeferredObject: function (obj) {
         return obj.then && obj.done && obj.fail;
      },

      //Logging methods ////////////////////////////////////////////////////////

      _logDebug: function (text) {
         if (!window.console) {
            return;
         }

         console.log('jTable DEBUG: ' + text);
      },

      _logInfo: function (text) {
         if (!window.console) {
            return;
         }

         console.log('jTable INFO: ' + text);
      },

      _logWarn: function (text) {
         if (!window.console) {
            return;
         }

         console.log('jTable WARNING: ' + text);
      },

      _logError: function (text) {
         if (!window.console) {
            return;
         }

         console.log('jTable ERROR: ' + text);
      }

   });

   /* Fix for array.indexOf method in IE7.
     * This code is taken from http://www.tutorialspoint.com/javascript/array_indexof.htm */
   if (!Array.prototype.indexOf) {
      Array.prototype.indexOf = function (elt) {
         var len = this.length;
         var from = Number(arguments[1]) || 0;
         from = (from < 0)
            ? Math.ceil(from)
            : Math.floor(from);
         if (from < 0)
            from += len;
         for (; from < len; from++) {
            if (from in this &&
               this[from] === elt)
               return from;
         }
         return -1;
      };
   }

})(jQuery);


/************************************************************************
 * FORMS extension for jTable (base for edit/create forms)               *
 *************************************************************************/
(function ($) {

   $.extend(true, $.hik.jtable.prototype, {

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* Submits a form asynchronously using AJAX.
        *  This method is needed, since form submitting logic can be overrided
        *  by extensions.
        *************************************************************************/
      _submitFormUsingAjax: function (url, formData, success) {
         var method = 'POST';

         // Set laravel hidden method to PATCH if this is an update
         if (null != formData.get('id'))
            formData.append('_method', 'PATCH');

         this._ajax({
            url: url,
            method: method,
            data: formData,
            success: success,
            cache: false,
            contentType: false,
            processData: false,
         });
      },

      /* Creates label for an input element.
        *************************************************************************/
      _createInputLabelForRecordField: function (fieldName) {
         var retval = $('<div />')
            .addClass('jtable-input-label')
            .html(this.options.fields[fieldName].inputTitle || this.options.fields[fieldName].title);

         if (this.options.fields[fieldName].tooltip) {
            retval.append($('<div />')
               .addClass('jtable-input-label-description')
               .html(this.options.fields[fieldName].tooltip));
         }

         return retval;
      },

      /* Appends the given field containers to the form. If self.options.fieldtabs is
       * configured, the containers are grouped into bootstrap nav-tabs in the order
       * they appear in each tab's "fields" array. Editable/creatable fields that are
       * not listed in any tab end up in the first tab. If no fieldtabs are configured,
       * containers are appended directly to the form (default behavior).
        *************************************************************************/
      _appendFieldContainersToForm: function ($form, fieldContainers) {
         var self = this;
         var fieldtabs = self.options.fieldtabs;

         if (!fieldtabs || !fieldtabs.length) {
            for (var i = 0; i < fieldContainers.length; i++) {
               $form.append(fieldContainers[i].$container);
            }
            return;
         }

         var containerByField = {};
         for (var i = 0; i < fieldContainers.length; i++) {
            containerByField[fieldContainers[i].fieldName] = fieldContainers[i].$container;
         }

         var assignedFields = {};
         var tabIdPrefix = 'jtable-fieldtab-' + Math.floor(Math.random() * 1000000);

         var $tabNav = $('<ul></ul>').addClass('nav nav-tabs jtable-field-tabs').attr('role', 'tablist');
         var $tabContent = $('<div></div>').addClass('tab-content jtable-field-tabs-content');
         var $panes = [];

         for (var t = 0; t < fieldtabs.length; t++) {
            var tabDef = fieldtabs[t];
            var tabId = tabIdPrefix + '-' + t;
            var isActive = (t === 0);

            var $navItem = $('<li></li>').addClass('nav-item').attr('role', 'presentation');
            var $navLink = $('<button></button>')
               .attr('type', 'button')
               .attr('id', tabId + '-tab')
               .attr('data-bs-toggle', 'tab')
               .attr('data-bs-target', '#' + tabId)
               .attr('role', 'tab')
               .attr('aria-controls', tabId)
               .attr('aria-selected', isActive ? 'true' : 'false')
               .addClass('nav-link' + (isActive ? ' active' : ''));
            $navLink.append($('<span></span>').addClass('jtable-tab-label').text(tabDef.name));
            $navLink.prepend($('<span></span>')
               .addClass('jtable-tab-invalid-indicator')
               .attr('title', self.options.messages.tabHasInvalidFields || '')
               .text('')
               .hide());
            $navItem.append($navLink);
            $tabNav.append($navItem);

            var $pane = $('<div></div>')
               .attr('id', tabId)
               .attr('role', 'tabpanel')
               .attr('aria-labelledby', tabId + '-tab')
               .addClass('tab-pane fade jtable-field-tab-pane' + (isActive ? ' show active' : ''));

            var tabFields = tabDef.fields || [];
            for (var f = 0; f < tabFields.length; f++) {
               var fieldName = tabFields[f];
               var $container = containerByField[fieldName];
               if ($container) {
                  $pane.append($container);
                  assignedFields[fieldName] = true;
               }
            }

            $panes.push($pane);
            $tabContent.append($pane);
         }

         //Fields that are not listed in any tab's "fields" array go to the first tab
         for (var i = 0; i < fieldContainers.length; i++) {
            var fieldContainer = fieldContainers[i];
            if (!assignedFields[fieldContainer.fieldName]) {
               $panes[0].append(fieldContainer.$container);
            }
         }

         $form.append($tabNav).append($tabContent);

         //Keep tab indicators in sync with field validity as the user edits the form
         $form.on('input change', function () {
            self._updateFieldTabValidityIndicators($form);
         });
         self._updateFieldTabValidityIndicators($form);
      },

      /* Toggles a visual indicator on each tab's nav-link when its pane contains
       * one or more invalid (e.g. empty required) fields, so the user can see
       * which tabs need attention without having to open each one.
        *************************************************************************/
      _updateFieldTabValidityIndicators: function ($form) {
         $form.find('.jtable-field-tab-pane').each(function () {
            var $pane = $(this);
            var hasInvalid = $pane.find(':invalid').length > 0;
            var $navLink = $form.find('button.nav-link[data-bs-target="#' + $pane.attr('id') + '"]');

            $navLink.toggleClass('jtable-tab-has-invalid', hasInvalid);
            $navLink.find('.jtable-tab-invalid-indicator').toggle(hasInvalid);
         });
      },

      /* When fields are grouped into tabs, invalid controls in a non-active tab pane
       * are hidden and cannot be focused/reported by the browser's native validation
       * (reportValidity/checkValidity). This activates the tab that contains the
       * first invalid control, if any, so it can be reported and focused normally.
        *************************************************************************/
      _activateTabForFirstInvalidField: function ($form) {
         var invalidField = $form[0].querySelector(':invalid');
         if (!invalidField) {
            return;
         }

         var $pane = $(invalidField).closest('.jtable-field-tab-pane');
         if (!$pane.length || $pane.hasClass('active')) {
            return;
         }

         var paneId = $pane.attr('id');
         var $navLink = $form.find('button.nav-link[data-bs-target="#' + paneId + '"]');
         if (!$navLink.length) {
            return;
         }

         if (typeof bootstrap !== 'undefined' && bootstrap.Tab) {
            bootstrap.Tab.getOrCreateInstance($navLink[0]).show();
         } else {
            $form.find('.jtable-field-tab-pane.active').removeClass('show active');
            $form.find('button.nav-link.active').removeClass('active').attr('aria-selected', 'false');
            $pane.addClass('show active');
            $navLink.addClass('active').attr('aria-selected', 'true');
         }
      },

      /* Creates an input element according to field type.
        *************************************************************************/
      _createInputForRecordField: function (funcParams) {
         var fieldName = funcParams.fieldName,
            value = funcParams.value,
            record = funcParams.record,
            formType = funcParams.formType,
            form = funcParams.form;

         //Get the field
         var field = this.options.fields[fieldName];

         //If value if not supplied, use defaultValue of the field
         if (value == undefined || value == null) {
            value = field.defaultValue;
         }

         //Use custom function if supplied
         if (field.input) {
            var $input = $(field.input({
               value: value,
               record: record,
               formType: formType,
               form: form
            }));

            //Add id attribute if does not exists
            if (!$input.attr('id')) {
               $input.attr('id', 'Edit-' + fieldName);
            }

            //Wrap input element with div
            return $('<div />')
               .addClass('jtable-input jtable-custom-input')
               .append($input);
         }

         //Create input according to field type
         if (field.type == 'date') {
            return this._createDateInputForField(field, fieldName, value);
         } else if (field.type == 'datetime') {
            return this._createDateTimeInputForField(field, fieldName, value);
         } else if (field.type == 'textarea') {
            return this._createTextAreaForField(field, fieldName, value);
         } else if (field.type == 'password') {
            return this._createPasswordInputForField(field, fieldName, value);
         } else if (field.type == 'checkbox') {
            return this._createCheckboxForField(field, fieldName, value);
         } else if (field.type == 'number') {
            return this._createNumberInputForField(field, fieldName, value);
         } else if (field.type == 'file') {
            return this._createFileInputForField(field, fieldName, value);
         } else if (field.options) {
            if (field.type == 'radiobutton') {
               return this._createRadioButtonListForField(field, fieldName, value, record, formType);
            } else {
               return this._createDropDownListForField(field, fieldName, value, record, formType, form);
            }
         } else {
            return this._createTextInputForField(field, fieldName, value);
         }
      },

      //Creates a hidden input element with given name and value.
      _createInputForHidden: function (fieldName, value) {
         if (value == undefined) {
            value = "";
         }

         return $('<input type="hidden" name="' + fieldName + '" id="Edit-' + fieldName + '"></input>')
            .val(value);
      },

      /* Creates a date input for a field.
        *************************************************************************/
      _createDateInputForField: function (field, fieldName, value) {
         var $input = $('<input type="date" class="form-control ' + field.inputClass + '" id="Edit-' + fieldName + '" type="text" name="' + fieldName + '"' + (field.required ? ' required' : '') + '></input>');
         if (value != undefined) {
            $input.val(value);
         }

         return $('<div />')
            .addClass('jtable-input jtable-date-input')
            .append($input);
      },

      /* Creates a datetime input for a field.
        *************************************************************************/
      _createDateTimeInputForField: function (field, fieldName, value) {
         var $input = $('<input class="form-control ' + field.inputClass + '" id="Edit-' + fieldName + '" type="datetime-local" name="' + fieldName + '"' + (field.required ? ' required' : '') + '></input>');
         if (value != undefined) {
            $input.val(value);
         }

         return $('<div />')
            .addClass('jtable-input jtable-date-input')
            .append($input);
      },

      /* Creates a textarea element for a field.
        *************************************************************************/
      _createTextAreaForField: function (field, fieldName, value) {
         var $textArea = $('<textarea class="form-control ' + field.inputClass + '" id="Edit-' + fieldName + '" name="' + fieldName + '"' + (field.required ? ' required' : '') + '></textarea>');
         if (value != undefined) {
            $textArea.val(value);
         }

         if (undefined != field.placeholder)
            $textArea.prop('placeholder', field.placeholder);

         return $('<div />')
            .addClass('jtable-input jtable-textarea-input')
            .append($textArea);
      },

      /* Creates a standard textbox for a field.
        *************************************************************************/
      _createTextInputForField: function (field, fieldName, value) {
         var $input = $('<input class="form-control ' + field.inputClass + '" id="Edit-' + fieldName + '" type="text" name="' + fieldName + '"' + (field.maxlength ? ' maxLength="' + field.maxlength + '"' : '') + (field.pattern ? ' pattern="' + field.pattern + '"' : '') + (field.required ? ' required' : '') + '></input>');
         if (value != undefined) {
            $input.val(value);
         }

         if (undefined != field.placeholder)
            $textArea.prop('placeholder', field.placeholder);

         return $('<div />')
            .addClass('jtable-input jtable-text-input')
            .append($input);
      },

      /* Creates a number input for a field.
        *************************************************************************/
      _createNumberInputForField: function (field, fieldName, value) {
         var $input = $('<input class="form-control ' + field.inputClass + '" id="Edit-' + fieldName + '" type="number" name="' + fieldName + '"' + ('undefined' != typeof field.min ? ' min="' + field.min + '"' : '') + ('undefined' != typeof field.max ? ' max="' + field.max + '"' : '') + (field.step ? ' step="' + field.step + '"' : '') + (field.required ? ' required' : '') + '></input>');
         if (value != undefined) {
            $input.val(value);
         }

         return $('<div />')
            .addClass('jtable-input jtable-text-input')
            .append($input);
      },

      /* Creates a file input for a field.
        *************************************************************************/
      _createFileInputForField: function (field, fieldName, value) {
         var $input = $('<input type="file" class="form-control-file" name="' + fieldName + '" id="Edit-' + fieldName + '" ' + (field.accept ? 'accept="' + field.accept + '"' : '') + ' ' + (field.required ? ' required' : '') + '/>');
         if (value != undefined) {
            $input.val(value);
         }

         return $('<div />')
            .addClass('jtable-input jtable-file-input')
            .append($input);
      },

      /* Creates a password input for a field.
        *************************************************************************/
      _createPasswordInputForField: function (field, fieldName, value) {
         var $input = $('<input class="form-control ' + field.inputClass + '" id="Edit-' + fieldName + '" type="password" name="' + fieldName + '"' + (field.required ? ' required' : '') + '></input>');
         if (value != undefined) {
            $input.val(value);
         }

         return $('<div />')
            .addClass('jtable-input jtable-password-input')
            .append($input);
      },

      /* Creates a checkboxfor a field.
        *************************************************************************/
      _createCheckboxForField: function (field, fieldName, value) {
         var self = this;

         //If value is undefined, get unchecked state's value
         if (value == undefined) {
            value = self._getCheckBoxPropertiesForFieldByState(fieldName, false).Value;
         }

         //Create a container div
         var $containerDiv = $('<div />')
            .addClass('jtable-input jtable-checkbox-input');

         //Create checkbox and check if needed
         var $checkBox = $('<input class="' + field.inputClass + '" id="Edit-' + fieldName + '" type="checkbox" name="' + fieldName + '" />')
            .appendTo($containerDiv);
         if (value != undefined) {
            $checkBox.val(value);
         }

         //Create display text of checkbox for current state
         var $textSpan = $('<span>' + (field.formText || self._getCheckBoxTextForFieldByValue(fieldName, value)) + '</span>')
            .appendTo($containerDiv);

         //Check the checkbox if it's value is checked-value
         if (self._getIsCheckBoxSelectedForFieldByValue(fieldName, value)) {
            $checkBox.attr('checked', 'checked');
         }

         //This method sets checkbox's value and text according to state of the checkbox
         var refreshCheckBoxValueAndText = function () {
            var checkboxProps = self._getCheckBoxPropertiesForFieldByState(fieldName, $checkBox.is(':checked'));
            $checkBox.attr('value', checkboxProps.Value);
            $textSpan.html(field.formText || checkboxProps.DisplayText);
         };

         //Register to click event to change display text when state of checkbox is changed.
         $checkBox.click(function () {
            refreshCheckBoxValueAndText();
         });

         //Change checkbox state when clicked to text
         if (field.setOnTextClick != false) {
            $textSpan
               .addClass('jtable-option-text-clickable')
               .click(function () {
                  if ($checkBox.is(':checked')) {
                     $checkBox.attr('checked', false);
                  } else {
                     $checkBox.attr('checked', true);
                  }

                  refreshCheckBoxValueAndText();
               });
         }

         return $containerDiv;
      },

      /* Creates a drop down list (combobox) input element for a field.
        *************************************************************************/
      _createDropDownListForField: function (field, fieldName, value, record, source, form) {

         //Create a container div
         var $containerDiv = $('<div />')
            .addClass('jtable-input jtable-dropdown-input');

         //Create select element
         var $select = $('<select class="form-control form-select' + field.inputClass + '" id="Edit-' + fieldName + '" name="' + fieldName + (field.multiple ? '[]' : '') + '" ' + (field.required ? ' required' : '') + '></select>')
            .prop('multiple', field.multiple)
            .appendTo($containerDiv);

         //add options
         var options = this._getOptionsForField(fieldName, {
            record: record,
            source: source,
            form: form,
            dependedValues: this._createDependedValuesUsingForm(form, field.dependsOn)
         });

         this._fillDropDownListWithOptions($select, options, value);

         if (field.search)
            $select.select2({dropdownParent: $containerDiv});

         return $containerDiv;
      },

      /* Fills a dropdown list with given options.
        *************************************************************************/
      _fillDropDownListWithOptions: function ($select, options, value) {
         $select.empty();
         // Check if optgroup
         if (options.length && options[0].Label) {
            for (var i = 0; i < options.length; i++) {
               if (!options[i].Children)
                  continue;

               var optgroup = $('<optgroup />')
                  .attr('label', options[i].Label)
                  .appendTo($select);

               for (var j = 0; j < options[i].Children.length; j++) {
                  var isSelected = false;
                  if (!Array.isArray(value))
                     isSelected = (options[i].Children[j].Value == value);
                  else {
                     value.forEach((aval) => {
                        // Option 1: Value is an object with a parameter named id
                        if (('undefined' !== typeof aval.id) &&
                           (aval.id == options[i].Children[j].Value)) {
                           isSelected = true;
                           return;
                        }

                        // Option 2: Value is a plain integer
                        if (aval == options[i].Children[j].Value) {
                           isSelected = true;
                           return;
                        }
                     });
                  }
                  $('<option' + (isSelected ? ' selected="selected"' : '') + ' />')
                     .prop('title', (('undefined' != typeof options[i].Children[j].Tooltip) && options[i].Children[j].Tooltip) ? options[i].Children[j].Tooltip : '')
                     .text(options[i].Children[j].DisplayText)
                     .val(options[i].Children[j].Value)
                     .prop('disabled', options[i].Children[j].Disabled ?? false)
                     .appendTo(optgroup);
               }
            }
         } else // No optgroup
         {
            for (var i = 0; i < options.length; i++) {
               var isSelected = false;
               if (!Array.isArray(value))
                  isSelected = (options[i].Value == value);
               else {
                  value.forEach((aval) => {
                     // Option 1: Value is an object with a parameter named id
                     if (('undefined' !== typeof aval.id) &&
                        (aval.id == options[i].Value)) {
                        isSelected = true;
                        return;
                     }

                     // Option 2: Value is a plain integer
                     if (aval == options[i].Value) {
                        isSelected = true;
                        return;
                     }
                  });
               }

               $('<option' + (isSelected ? ' selected="selected"' : '') + ' />')
                  .prop('title', (('undefined' != typeof options[i].Tooltip) && options[i].Tooltip) ? options[i].Tooltip : '')
                  .text(options[i].DisplayText)
                  .val(options[i].Value)
                  .prop('disabled', options[i].Disabled ?? false)
                  .appendTo($select);
            }
         }
      },

      /* Creates depended values object from given form.
        *************************************************************************/
      _createDependedValuesUsingForm: function ($form, dependsOn) {
         if (!dependsOn) {
            return {};
         }

         var dependedValues = {};

         for (var i = 0; i < dependsOn.length; i++) {
            var dependedField = dependsOn[i];

            var $dependsOn = $form.find('select[name=' + dependedField + ']');
            if ($dependsOn.length <= 0) {
               continue;
            }

            dependedValues[dependedField] = $dependsOn.val();
         }


         return dependedValues;
      },

      /* Creates a radio button list for a field.
        *************************************************************************/
      _createRadioButtonListForField: function (field, fieldName, value, record, source) {
         var $containerDiv = $('<div />')
            .addClass('jtable-input jtable-radiobuttonlist-input');

         var options = this._getOptionsForField(fieldName, {
            record: record,
            source: source
         });

         $.each(options, function (i, option) {
            var $radioButtonDiv = $('<div class=""></div>')
               .addClass('jtable-radio-input')
               .appendTo($containerDiv);

            var $radioButton = $('<input type="radio" id="Edit-' + fieldName + '-' + i + '" class="' + field.inputClass + '" name="' + fieldName + '"' + ((option.Value == (value + '')) ? ' checked="true"' : '') + ' />')
               .val(option.Value)
               .appendTo($radioButtonDiv);

            var $textSpan = $('<span></span>')
               .html(option.DisplayText)
               .appendTo($radioButtonDiv);

            if (field.setOnTextClick != false) {
               $textSpan
                  .addClass('jtable-option-text-clickable')
                  .click(function () {
                     if (!$radioButton.is(':checked')) {
                        $radioButton.attr('checked', true);
                     }
                  });
            }
         });

         return $containerDiv;
      },

      /* Gets display text for a checkbox field.
        *************************************************************************/
      _getCheckBoxTextForFieldByValue: function (fieldName, value) {
         return this.options.fields[fieldName].values[value];
      },

      /* Returns true if given field's value must be checked state.
        *************************************************************************/
      _getIsCheckBoxSelectedForFieldByValue: function (fieldName, value) {
         return (this._createCheckBoxStateArrayForFieldWithCaching(fieldName)[1].Value.toString() == value.toString());
      },

      /* Gets an object for a checkbox field that has Value and DisplayText
        *  properties.
        *************************************************************************/
      _getCheckBoxPropertiesForFieldByState: function (fieldName, checked) {
         return this._createCheckBoxStateArrayForFieldWithCaching(fieldName)[(checked ? 1 : 0)];
      },

      /* Calls _createCheckBoxStateArrayForField with caching.
        *************************************************************************/
      _createCheckBoxStateArrayForFieldWithCaching: function (fieldName) {
         var cacheKey = 'checkbox_' + fieldName;
         if (!this._cache[cacheKey]) {

            this._cache[cacheKey] = this._createCheckBoxStateArrayForField(fieldName);
         }

         return this._cache[cacheKey];
      },

      /* Creates a two element array of objects for states of a checkbox field.
        *  First element for unchecked state, second for checked state.
        *  Each object has two properties: Value and DisplayText
        *************************************************************************/
      _createCheckBoxStateArrayForField: function (fieldName) {
         var stateArray = [];
         var currentIndex = 0;
         $.each(this.options.fields[fieldName].values, function (propName, propValue) {
            if (currentIndex++ < 2) {
               stateArray.push({'Value': propName, 'DisplayText': propValue});
            }
         });

         return stateArray;
      },

      /* Searches a form for dependend dropdowns and makes them cascaded.
        */
      _makeCascadeDropDowns: function ($form, record, source) {
         var self = this;

         $form.find('select') //for each combobox
            .each(function () {
               var $thisDropdown = $(this);

               //get field name
               var fieldName = $thisDropdown.attr('name');
               if (!fieldName) {
                  return;
               }

               if (fieldName.endsWith('[]'))
                  fieldName = fieldName.substring(0, fieldName.length - 2);

               var field = self.options.fields[fieldName];

               //check if this combobox depends on others
               if (!field.dependsOn) {
                  return;
               }

               //for each dependency
               $.each(field.dependsOn, function (index, dependsOnField) {
                  //find the depended combobox
                  var $dependsOnDropdown = $form.find('select[name=' + dependsOnField + ']');
                  //when depended combobox changes
                  $dependsOnDropdown.change(function () {

                     //Refresh options
                     var funcParams = {
                        record: record,
                        source: source,
                        form: $form,
                        dependedValues: {}
                     };
                     funcParams.dependedValues = self._createDependedValuesUsingForm($form, field.dependsOn);
                     var options = self._getOptionsForField(fieldName, funcParams);

                     //Fill combobox with new options
                     self._fillDropDownListWithOptions($thisDropdown, options, undefined);

                     //Thigger change event to refresh multi cascade dropdowns.
                     $thisDropdown.change();
                  });
               });
            });
      },

      /* Updates values of a record from given form
        *************************************************************************/
      _updateRecordValuesFromForm: function (record, $form) {
         for (var i = 0; i < this._fieldList.length; i++) {
            var fieldName = this._fieldList[i];
            var field = this.options.fields[fieldName];

            //Do not update non-editable fields
            if (field.edit == false) {
               continue;
            }

            //Get field name and the input element of this field in the form
            var $inputElement = $form.find('[name="' + fieldName + '"],[name="' + fieldName + '[]"]');
            if ($inputElement.length <= 0) {
               continue;
            }

            //Update field in record according to it's type
            if (field.options && field.type == 'radiobutton') {
               var $checkedElement = $inputElement.filter(':checked');
               if ($checkedElement.length) {
                  record[fieldName] = $checkedElement.val();
               } else {
                  record[fieldName] = undefined;
               }
            } else {
               record[fieldName] = $inputElement.val();
            }
         }
      },
   });

})(jQuery);


/************************************************************************
 * CREATE RECORD extension for jTable                                    *
 *************************************************************************/
(function ($) {

   //Reference to base object members
   var base = {
      _create: $.hik.jtable.prototype._create
   };

   //extension members
   $.extend(true, $.hik.jtable.prototype, {

      /************************************************************************
       * DEFAULT OPTIONS / EVENTS                                              *
       *************************************************************************/
      options: {

         //Events
         recordAdded: function (event, data) {
         },

         //Localization
         messages: {
            addNewRecord: 'Add new record'
         }
      },

      /************************************************************************
       * PRIVATE FIELDS                                                        *
       *************************************************************************/


      /************************************************************************
       * CONSTRUCTOR                                                           *
       *************************************************************************/

      /* Overrides base method to do create-specific constructions.
        *************************************************************************/
      _create: function () {
         base._create.apply(this, arguments);

         if (!this.options.actions.createAction) {
            return;
         }

         this._createAddRecordDialogDiv();
      },

      /* Creates and prepares add new record dialog div
        *************************************************************************/
      _createAddRecordDialogDiv: function () {
         var self = this;

         if (self.options.addRecordButton) {
            //If user supplied a button, bind the click event to show dialog form
            self.options.addRecordButton.click(function (e) {
               e.preventDefault();
               self._showAddRecordForm();
            });
         } else {
            //If user did not supplied a button, create a 'add record button' toolbar item.
            self._addToolBarItem({
               cssClass: 'jtable-toolbar-item-add-record btn btn-sm btn-primary text-light',
               icon: 'add_circle',
               text: self.options.messages.addNewRecord,
               click: function () {
                  self._showAddRecordForm();
               }
            });
         }
      },

      _onSaveClickedOnCreateForm: function (dialog) {
         var self = this;
         var $addRecordForm = $(dialog).find('form.jtable-dialog-form');

         // Perform form validation
         if ($addRecordForm[0].checkValidity && !$addRecordForm[0].checkValidity()) {
            self._updateFieldTabValidityIndicators($addRecordForm);
            self._activateTabForFirstInvalidField($addRecordForm);

            if ($addRecordForm[0].reportValidity)
               $addRecordForm[0].reportValidity();

            return true;
         }

         if (self._trigger("formSubmitting", null, {form: $addRecordForm, formType: 'create'}) != false) {
            self._saveAddRecordForm($addRecordForm);
            return true;
         }
      },

      /************************************************************************
       * PUBLIC METHODS                                                        *
       *************************************************************************/

      /* Shows add new record dialog form.
        *************************************************************************/
      showCreateForm: function () {
         this._showAddRecordForm();
      },

      /* Adds a new record to the table (optionally to the server also)
        *************************************************************************/
      addRecord: function (options) {
         var self = this;
         options = $.extend({
            clientOnly: false,
            animationsEnabled: self.options.animationsEnabled,
            success: function () {
            },
            error: function () {
            }
         }, options);

         if (!options.record) {
            self._logWarn('options parameter in addRecord method must contain a record property.');
            return;
         }

         if (options.clientOnly) {
            self._addRow(
               self._createRowFromRecord(options.record), {
                  isNewRow: true,
                  animationsEnabled: options.animationsEnabled
               });

            options.success();
            return;
         }

         var completeAddRecord = function (data) {
            if (!data) {
               self._logError('Server must return the created object.');
               options.error(data);
               return;
            }

            self._onRecordAdded(data);
            self._addRow(
               self._createRowFromRecord(data), {
                  isNewRow: true,
                  animationsEnabled: options.animationsEnabled
               });


            options.success(data);
         };

         //createAction may be a function, check if it is
         if (!options.url && $.isFunction(self.options.actions.createAction)) {

            //Execute the function
            var funcResult = self.options.actions.createAction($.param(options.record));

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               //Wait promise
               funcResult.done(function (data) {
                  completeAddRecord(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
                  options.error();
               });
            } else { //assume it returned the creation result
               completeAddRecord(funcResult);
            }

         } else { //Assume it's a URL string

            //Make an Ajax call to create record
            self._submitFormUsingAjax(
               options.url || self.options.actions.createAction,
               $.param(options.record),
               function (data) {
                  completeAddRecord(data);
               });

         }
      },

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* Shows add new record dialog form.
        *************************************************************************/
      _showAddRecordForm: function () {
         var self = this;

         //Create add new record form
         var $addRecordForm = $('<form id="jtable-create-form" class="jtable-dialog-form jtable-create-form"></form>');

         $addRecordForm.on('submit', function (event) {
            event.preventDefault();
            $(event.target).closest('.modal').find('.action-button').trigger('click');
            return false;
         });

         //Create input elements
         var fieldContainers = [];
         for (var i = 0; i < self._fieldList.length; i++) {

            var fieldName = self._fieldList[i];
            var field = self.options.fields[fieldName];

            //Do not create input for fields that is key and not specially marked as creatable
            if (field.key == true && field.create != true) {
               continue;
            }

            //Do not create input for fields that are not creatable
            if (field.create == false) {
               continue;
            }

            if (field.type == 'hidden') {
               $addRecordForm.append(self._createInputForHidden(fieldName, field.defaultValue));
               continue;
            }

            //Create a container div for this input field
            var $fieldContainer = $('<div />')
               .addClass('jtable-input-field-container');

            //Create a label for input
            $fieldContainer.append(self._createInputLabelForRecordField(fieldName));

            //Create input element
            $fieldContainer.append(
               self._createInputForRecordField({
                  fieldName: fieldName,
                  formType: 'create',
                  form: $addRecordForm
               }));

            fieldContainers.push({fieldName: fieldName, $container: $fieldContainer});
         }

         //Append field containers to the form, grouped into tabs if fieldtabs is configured
         self._appendFieldContainersToForm($addRecordForm, fieldContainers);

         self._makeCascadeDropDowns($addRecordForm, undefined, 'create');

         //Open the form
         confirmDialog($('<div></div>')
               .addClass('jtable-form-title')
               .append($('<span></span>')
                  .addClass('material-symbols-rounded jtable-form-title-icon')
                  .text('edit_square'))
               .append($('<span></span>')
                  .addClass(' jtable-form-title-label')
                  .text(self.options.messages.addNewRecord))
            , $addRecordForm, function (data, dialog) {
               return self._onSaveClickedOnCreateForm(dialog);
            }, {
               dismissLabel: self.options.messages.cancel,
               actionLabel: self.options.messages.save,
               modalClass: 'modal-xl'
            });
         self._trigger("formCreated", null, {form: $addRecordForm, formType: 'create'});
      },

      /* Saves new added record to the server and updates table.
        *************************************************************************/
      _saveAddRecordForm: function ($addRecordForm) {
         var self = this;

         var completeAddRecord = function (data) {
            if (!data) {
               self._logError('Server must return the created Record object.');
               return;
            }

            self._onRecordAdded(data);
            self._addRow(
               self._createRowFromRecord(data), {
                  isNewRow: true
               });

            var dialog = bootstrap.Modal.getInstance($('.jtable-dialog-form').closest('.modal')[0]);
            var modalContainer = $('.jtable-dialog-form').closest('.modal');
            dialog.hide();
            dialog.dispose();
            modalContainer.remove();
         };

         $addRecordForm.data('submitting', true); //TODO: Why it's used, can remove? Check it.

         //createAction may be a function, check if it is
         if ($.isFunction(self.options.actions.createAction)) {

            //Execute the function
            var funcResult = self.options.actions.createAction($addRecordForm.serialize());

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               //Wait promise
               funcResult.done(function (data) {
                  completeAddRecord(data);
               }).fail(function (errdata) {
                  self._showError((errdata && errdata[0] && errdata[0].responseJSON && errdata[0].responseJSON.message) ? errdata[0].responseJSON.message : self.options.messages.serverCommunicationError);
               });
            } else { //assume it returned the creation result
               completeAddRecord(funcResult);
            }

         } else { //Assume it's a URL string

            //Make an Ajax call to create record
            self._submitFormUsingAjax(
               self.options.actions.createAction,
               $addRecordForm.serialize(),
               function (data) {
                  completeAddRecord(data);
               });
         }
      },

      _onRecordAdded: function (data) {
         this._trigger("recordAdded", null, {record: data});
      }

   });

})(jQuery);


/************************************************************************
 * EDIT RECORD extension for jTable                                      *
 *************************************************************************/
(function ($) {

   //Reference to base object members
   var base = {
      _create: $.hik.jtable.prototype._create,
      _addColumnsToHeaderRow: $.hik.jtable.prototype._addColumnsToHeaderRow,
      _addCellsToRowUsingRecord: $.hik.jtable.prototype._addCellsToRowUsingRecord
   };

   //extension members
   $.extend(true, $.hik.jtable.prototype, {

      /************************************************************************
       * DEFAULT OPTIONS / EVENTS                                              *
       *************************************************************************/
      options: {

         //Events
         recordUpdated: function (event, data) {
         },
         rowUpdated: function (event, data) {
         },

         //Localization
         messages: {
            editRecord: 'Edit Record'
         }
      },

      /************************************************************************
       * PRIVATE FIELDS                                                        *
       *************************************************************************/

      _$editingRow: null, //Reference to currently editing row (jQuery object)

      /************************************************************************
       * CONSTRUCTOR AND INITIALIZATION METHODS                                *
       *************************************************************************/

      /* Overrides base method to do editing-specific constructions.
        *************************************************************************/
      _create: function () {
         base._create.apply(this, arguments);

         if (!this.options.actions.updateAction) {
            return;
         }
      },

      /* Saves editing form to server.
        *************************************************************************/
      _onSaveClickedOnEditForm: function (dialog) {
         var self = this;

         //row maybe removed by another source, if so, do nothing
         if (self._$editingRow.hasClass('jtable-row-removed')) {
            return;
         }

         var $editForm = $(dialog).find('form.jtable-dialog-form');

         // Perform form validation
         if ($editForm[0].checkValidity && !$editForm[0].checkValidity()) {
            self._updateFieldTabValidityIndicators($editForm);
            self._activateTabForFirstInvalidField($editForm);

            if ($editForm[0].reportValidity)
               $editForm[0].reportValidity();

            return true;
         }

         if (self._trigger("formSubmitting", null, {
            form: $editForm,
            formType: 'edit',
            row: self._$editingRow
         }) != false) {
            self._saveEditForm($editForm);
            return true;
         }
      },

      /************************************************************************
       * PUBLIC METHODS                                                        *
       *************************************************************************/

      /* Updates a record on the table (optionally on the server also)
        *************************************************************************/
      updateRecord: function (options) {
         var self = this;
         options = $.extend({
            clientOnly: false,
            animationsEnabled: self.options.animationsEnabled,
            success: function () {
            },
            error: function () {
            }
         }, options);

         if (!options.record) {
            self._logWarn('options parameter in updateRecord method must contain a record property.');
            return;
         }

         var key = self._getKeyValueOfRecord(options.record);
         if (key == undefined || key == null) {
            self._logWarn('options parameter in updateRecord method must contain a record that contains the key field property.');
            return;
         }

         var $updatingRow = self.getRowByKey(key);
         if ($updatingRow == null) {
            self._logWarn('Can not found any row by key "' + key + '" on the table. Updating row must be visible on the table.');
            return;
         }

         if (options.clientOnly) {
            $.extend($updatingRow.data('record'), options.record);
            self._updateRowTexts($updatingRow);
            self._onRecordUpdated($updatingRow, null);
            if (options.animationsEnabled) {
               self._showUpdateAnimationForRow($updatingRow);
            }

            options.success();
            return;
         }

         var completeEdit = function (data) {
            $.extend($updatingRow.data('record'), options.record);
            self._updateRecordValuesFromServerResponse($updatingRow.data('record'), data);

            self._updateRowTexts($updatingRow);
            self._onRecordUpdated($updatingRow, data);
            if (options.animationsEnabled) {
               self._showUpdateAnimationForRow($updatingRow);
            }

            options.success(data);
         };

         //updateAction may be a function, check if it is
         if (!options.url && $.isFunction(self.options.actions.updateAction)) {

            //Execute the function
            var funcResult = self.options.actions.updateAction($.param(options.record));

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               //Wait promise
               funcResult.done(function (data) {
                  completeEdit(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
                  options.error();
               });
            } else { //assume it returned the creation result
               completeEdit(funcResult);
            }

         } else { //Assume it's a URL string

            //Make an Ajax call to create record
            self._submitFormUsingAjax(
               options.url || self.options.actions.updateAction,
               $.param(options.record),
               function (data) {
                  completeEdit(data);
               });

         }
      },

      /************************************************************************
       * OVERRIDED METHODS                                                     *
       *************************************************************************/

      /* Overrides base method to add a 'editing column cell' to header row.
        *************************************************************************/
      _addColumnsToHeaderRow: function ($tr) {
         base._addColumnsToHeaderRow.apply(this, arguments);
         if (this.options.actions.updateAction != undefined) {
            $tr.append(this._createEmptyCommandHeader());
         }
      },

      /* Overrides base method to add a 'edit command cell' to a row.
        *************************************************************************/
      _addCellsToRowUsingRecord: function ($row, record) {
         var self = this;
         base._addCellsToRowUsingRecord.apply(this, arguments);

         if (self.options.actions.updateAction != undefined) {
            if (('undefined' != typeof record) && ('undefined' != typeof record.access) && ('undefined' != typeof record.access.update) && !record.access.update)
               return;


            var $button = $('<span title="' + self.options.messages.editRecord + '"></span>')
               .addClass('jtable-command-button jtable-edit-command-button')
               .append($('<span title="' + self.options.messages.editRecord + '">edit</span>')
                  .addClass('material-symbols-rounded'))
               .click(function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  self._showEditForm($row);
               });

            if (self.options.bootstrap)
               $button
                  .addClass('btn btn-sm btn-outline-primary')
                  .append(self.options.messages.editRecord);


            $(this.options.bootstrap ? '<div></div>' : '<td></td>')
               .addClass('jtable-command-column jtable-edit-command')
               .append($button)
               .appendTo($row);
         }
      },

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* Shows edit form for a row.
        *************************************************************************/
      _showEditForm: function ($tableRow) {
         var self = this;
         var record = $tableRow.data('record');
         var nameFieldValue = null;

         //Create edit form
         var $editForm = $('<form id="jtable-edit-form" class="jtable-dialog-form jtable-edit-form"></form>');

         $editForm.on('submit', function (event) {
            event.preventDefault();
            $(event.target).closest('.modal').find('.action-button').trigger('click');
            return false;
         });

         //Create input fields
         var fieldContainers = [];
         for (var i = 0; i < self._fieldList.length; i++) {

            var fieldName = self._fieldList[i];
            var field = self.options.fields[fieldName];
            var fieldValue = record[fieldName];

            if (field.key == true) {
               if (field.edit != true) {
                  //Create hidden field for key
                  $editForm.append(self._createInputForHidden(fieldName, fieldValue));
                  continue;
               } else {
                  //Create a special hidden field for key (since key is be editable)
                  $editForm.append(self._createInputForHidden('jtRecordKey', fieldValue));
               }
            }

            if ('name_pretty' == fieldName)
               nameFieldValue = fieldValue;

            if (!nameFieldValue && ('name' == fieldName))
               nameFieldValue = fieldValue;

            //Do not create element for non-editable fields
            if (field.edit == false) {
               continue;
            }

            //Hidden field
            if (field.type == 'hidden') {
               $editForm.append(self._createInputForHidden(fieldName, fieldValue));
               continue;
            }

            //Create a container div for this input field
            var $fieldContainer = $('<div class="jtable-input-field-container"></div>');

            //Create a label for input
            $fieldContainer.append(self._createInputLabelForRecordField(fieldName));

            //Create input element with it's current value
            var currentValue = self._getValueForRecordField(record, fieldName);
            $fieldContainer.append(
               self._createInputForRecordField({
                  fieldName: fieldName,
                  value: currentValue,
                  record: record,
                  formType: 'edit',
                  form: $editForm
               }));

            fieldContainers.push({fieldName: fieldName, $container: $fieldContainer});
         }

         //Append field containers to the form, grouped into tabs if fieldtabs is configured
         self._appendFieldContainersToForm($editForm, fieldContainers);

         self._makeCascadeDropDowns($editForm, record, 'edit');

         //Open the form
         confirmDialog($('<div></div>')
               .addClass('jtable-form-title')
               .append($('<span></span>')
                  .addClass('material-symbols-rounded jtable-form-title-icon')
                  .text('edit_square'))
               .append($('<span></span>')
                  .addClass(' jtable-form-title-label')
                  .text(self.options.messages.editRecord + (nameFieldValue ? ' - ' + nameFieldValue : '')))
            , $editForm, function (data, dialog) {
               return self._onSaveClickedOnEditForm(dialog);
            }, {
               dismissLabel: self.options.messages.cancel,
               actionLabel: self.options.messages.save,
               modalClass: 'modal-xl'
            });

         //Open dialog
         self._$editingRow = $tableRow;
         self._trigger("formCreated", null, {form: $editForm, formType: 'edit', record: record, row: $tableRow});
      },

      /* Saves editing form to the server and updates the record on the table.
        *************************************************************************/
      _saveEditForm: function ($editForm) {
         var self = this;

         var completeEdit = function (data) {

            var record = self._$editingRow.data('record');

            self._updateRecordValuesFromForm(record, $editForm);
            self._updateRecordValuesFromServerResponse(record, data);
            self._updateRowTexts(self._$editingRow);

            self._$editingRow.attr('data-record-key', self._getKeyValueOfRecord(record));

            self._onRecordUpdated(self._$editingRow, data);

            if (self.options.animationsEnabled) {
               self._showUpdateAnimationForRow(self._$editingRow);
            }

            var dialog = bootstrap.Modal.getInstance($('.jtable-dialog-form').closest('.modal')[0]);
            var modalContainer = $('.jtable-dialog-form').closest('.modal');
            dialog.hide();
            dialog.dispose();
            modalContainer.remove();

         };


         //updateAction may be a function, check if it is
         if ($.isFunction(self.options.actions.updateAction)) {

            //Execute the function
            var funcResult = self.options.actions.updateAction($editForm.serialize());

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               //Wait promise
               funcResult.done(function (data) {
                  completeEdit(data);
               }).fail(function () {
                  self._showError(self.options.messages.serverCommunicationError);
               });
            } else { //assume it returned the creation result
               completeEdit(funcResult);
            }

         } else { //Assume it's a URL string

            // Set id on url
            var updateUrl = self.options.actions.updateAction;
            var id = $editForm.find('#Edit-id').val();

            if (null != id) {
               if (updateUrl.indexOf('?') > -1) {
                  var urlPart = updateUrl.substring(0, updateUrl.indexOf('?'));
                  var paramPart = updateUrl.substring(updateUrl.indexOf('?'));
                  updateUrl = urlPart + '/' + id + paramPart;
               } else
                  updateUrl += '/' + id;
            }

            //Make an Ajax call to update record
            self._submitFormUsingAjax(
               updateUrl,
               $editForm.serialize(),
               function (data) {
                  completeEdit(data);
               });
         }

      },

      /* This method ensures updating of current record with server response,
        * if server sends a Record object as response to updateAction.
        *************************************************************************/
      _updateRecordValuesFromServerResponse: function (record, serverResponse) {
         if (!serverResponse || !serverResponse.Record) {
            return;
         }

         $.extend(true, record, serverResponse.Record);
      },

      /* Gets text for a field of a record according to it's type.
        *************************************************************************/
      _getValueForRecordField: function (record, fieldName) {
         var field = this.options.fields[fieldName];
         var fieldValue = record[fieldName];
         if (field.type == 'date') {
            return this._getDisplayTextForDateRecordField(field, fieldValue);
         } else {
            return fieldValue;
         }
      },

      /* Updates cells of a table row's text values from row's record values.
        *************************************************************************/
      _updateRowTexts: function ($tableRow) {
         var self = this;
         if (this.options.bootstrap) {
            self._reloadRow($tableRow);
            return;
         }

         var record = $tableRow.data('record');
         var $columns = $tableRow.find('td');
         for (var i = 0; i < this._columnList.length; i++) {
            var displayItem = this._getDisplayTextForRecordField(record, this._columnList[i]);
            if ((displayItem != "") && (displayItem == 0)) displayItem = "0";

            var container = $columns.eq(this._firstDataColumnOffset + i);
            // Check if container has a cell content container
            if (container.find('span.jtable-cell-content').length > 0)
               container.find('>span.jtable-cell-content').html(displayItem || '');
            else
               container.html(displayItem || '');
         }

         this._onRowUpdated($tableRow);
      },

      /* Shows 'updated' animation for a table row.
        *************************************************************************/
      _showUpdateAnimationForRow: function ($tableRow) {
         var className = 'jtable-row-updated';
         $tableRow.stop(true, true).addClass(className, 'slow', '', function () {
            $tableRow.removeClass(className, 5000);
         });
      },

      /************************************************************************
       * EVENT RAISING METHODS                                                 *
       *************************************************************************/

      _onRowUpdated: function ($row) {
         this._trigger("rowUpdated", null, {row: $row, record: $row.data('record')});
      },

      _onRecordUpdated: function ($row, data) {
         this._trigger("recordUpdated", null, {record: $row.data('record'), row: $row, serverResponse: data});
      }

   });

})(jQuery);


/************************************************************************
 * DELETION extension for jTable                                         *
 *************************************************************************/
(function ($) {

   //Reference to base object members
   var base = {
      _create: $.hik.jtable.prototype._create,
      _addColumnsToHeaderRow: $.hik.jtable.prototype._addColumnsToHeaderRow,
      _addCellsToRowUsingRecord: $.hik.jtable.prototype._addCellsToRowUsingRecord
   };

   //extension members
   $.extend(true, $.hik.jtable.prototype, {

      /************************************************************************
       * DEFAULT OPTIONS / EVENTS                                              *
       *************************************************************************/
      options: {

         //Options
         deleteConfirmation: true,

         //Events
         recordDeleted: function (event, data) {
         },

         //Localization
         messages: {
            deleteConfirmation: 'This record will be permanently removed',
            deleteConfirmationWarning: 'Are you sure that you want to delete this record?',
            deleteText: 'Delete',
            deleting: 'Deleting',
            canNotDeletedRecords: 'Can not delete {0} of {1} records!',
            deleteProggress: 'Deleting {0} of {1} records, processing...'
         }
      },

      /************************************************************************
       * PRIVATE FIELDS                                                        *
       *************************************************************************/

      _$deletingRow: null, //Reference to currently deleting row (jQuery object)

      /************************************************************************
       * CONSTRUCTOR                                                           *
       *************************************************************************/

      /* Overrides base method to do deletion-specific constructions.
        *************************************************************************/
      _create: function () {
         base._create.apply(this, arguments);
      },

      /************************************************************************
       * PUBLIC METHODS                                                        *
       *************************************************************************/

      /* This method is used to delete one or more rows from server and the table.
        *************************************************************************/
      deleteRows: function ($rows) {
         var self = this;

         if ($rows.length <= 0) {
            self._logWarn('No rows specified to jTable deleteRows method.');
            return;
         }

         if (self._isBusy()) {
            self._logWarn('Can not delete rows since jTable is busy!');
            return;
         }

         //Deleting just one row
         if ($rows.length == 1) {
            self._deleteRecordFromServer(
               $rows,
               function () { //success
                  self._removeRowsFromTableWithAnimation($rows);
               },
               function (message) { //error
                  self._showError(message);
               }
            );

            return;
         }

         //Deleting multiple rows
         self._showBusy(self._formatString(self.options.messages.deleteProggress, 0, $rows.length));

         //This method checks if deleting of all records is completed
         var completedCount = 0;
         var isCompleted = function () {
            return (completedCount >= $rows.length);
         };

         //This method is called when deleting of all records completed
         var completed = function () {
            var $deletedRows = $rows.filter('.jtable-row-ready-to-remove');
            if ($deletedRows.length < $rows.length) {
               self._showError(self._formatString(self.options.messages.canNotDeletedRecords, $rows.length - $deletedRows.length, $rows.length));
            }

            if ($deletedRows.length > 0) {
               self._removeRowsFromTableWithAnimation($deletedRows);
            }

            self._hideBusy();
         };

         //Delete all rows
         var deletedCount = 0;
         $rows.each(function () {
            var $row = $(this);
            self._deleteRecordFromServer(
               $row,
               function () { //success
                  ++deletedCount;
                  ++completedCount;
                  $row.addClass('jtable-row-ready-to-remove');
                  self._showBusy(self._formatString(self.options.messages.deleteProggress, deletedCount, $rows.length));
                  if (isCompleted()) {
                     completed();
                  }
               },
               function () { //error
                  ++completedCount;
                  if (isCompleted()) {
                     completed();
                  }
               }
            );
         });
      },

      /* Deletes a record from the table (optionally from the server also).
        *************************************************************************/
      deleteRecord: function (options) {
         var self = this;
         options = $.extend({
            clientOnly: false,
            animationsEnabled: self.options.animationsEnabled,
            url: self.options.actions.deleteAction,
            success: function () {
            },
            error: function () {
            }
         }, options);

         if (options.key == undefined) {
            self._logWarn('options parameter in deleteRecord method must contain a key property.');
            return;
         }

         var $deletingRow = self.getRowByKey(options.key);
         if ($deletingRow == null) {
            self._logWarn('Can not found any row by key: ' + options.key);
            return;
         }

         if (options.clientOnly) {
            self._removeRowsFromTableWithAnimation($deletingRow, options.animationsEnabled);
            options.success();
            return;
         }

         self._deleteRecordFromServer(
            $deletingRow,
            function (data) { //success
               self._removeRowsFromTableWithAnimation($deletingRow, options.animationsEnabled);
               options.success(data);
            },
            function (message) { //error
               self._showError(message);
               options.error(message);
            },
            options.url
         );
      },

      /************************************************************************
       * OVERRIDED METHODS                                                     *
       *************************************************************************/

      /* Overrides base method to add a 'deletion column cell' to header row.
        *************************************************************************/
      _addColumnsToHeaderRow: function ($tr) {
         base._addColumnsToHeaderRow.apply(this, arguments);
         if ((this.options.actions.deleteAction != undefined) && (this.options.actions.deleteAction)) {
            $tr.append(this._createEmptyCommandHeader());
         }
      },

      /* Overrides base method to add a 'delete command cell' to a row.
        *************************************************************************/
      _addCellsToRowUsingRecord: function ($row, record) {
         base._addCellsToRowUsingRecord.apply(this, arguments);

         var self = this;
         if ((self.options.actions.deleteAction != undefined) && (self.options.actions.deleteAction)) {

            if (('undefined' != typeof record) && ('undefined' != typeof record.access) && ('undefined' != typeof record.access.delete) && !record.access.delete)
               return;

            var $button = $(self.options.bootstrap ? '<button></button>' : '<span></span>')
               .prop('title', self.options.messages.deleteRecord)
               .addClass('jtable-command-button jtable-delete-command-button')
               .append($('<span title="' + self.options.messages.deleteRecord + '">delete</span>')
                  .addClass('material-symbols-rounded'))
               .click(function (e) {
                  e.preventDefault();
                  e.stopPropagation();
                  self._deleteButtonClickedForRow($row);
               });


            if (self.options.bootstrap)
               $button
                  .addClass('btn btn-sm btn-outline-primary')
                  .append(self.options.messages.deleteRecord);


            $(self.options.bootstrap ? '<div></div>' : '<td></td>')
               .addClass('jtable-command-column jtable-delete-command')
               .append($button)
               .appendTo($row);
         }
      },

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* This method is called when user clicks delete button on a row.
        *************************************************************************/
      _deleteButtonClickedForRow: function ($row) {
         var self = this;

         var deleteConfirm;
         var deleteConfirmMessage = self.options.messages.deleteConfirmation;

         //If options.deleteConfirmation is function then call it
         if ($.isFunction(self.options.deleteConfirmation)) {
            var data = {
               row: $row,
               record: $row.data('record'),
               deleteConfirm: true,
               deleteConfirmMessage: deleteConfirmMessage,
               cancel: false,
               cancelMessage: null
            };
            self.options.deleteConfirmation(data);

            //If delete progress is cancelled
            if (data.cancel) {

               //If a canlellation reason is specified
               if (data.cancelMessage) {
                  self._showError(data.cancelMessage);
               }

               return;
            }

            deleteConfirmMessage = data.deleteConfirmMessage;
            deleteConfirm = data.deleteConfirm;
         } else {
            deleteConfirm = self.options.deleteConfirmation;
         }

         if (deleteConfirm != false) {
            //Confirmation
            confirmDialog(self.options.messages.deleteConfirmationWarning, deleteConfirmMessage, function () {
               self._deleteRecordFromServer(
                  $row,
                  function () { //success
                     self._removeRowsFromTableWithAnimation($row);
                  },
                  function (message) { //error
                     self._showError(message);
                  }
               );
            }, {
               warning: true,
               dismissLabel: self.options.messages.cancel,
               actionLabel: self.options.messages.yesDelete
            });
         } else {
            //No confirmation
            self._deleteRecordFromServer(
               $row,
               function () { //success
                  self._removeRowsFromTableWithAnimation($row);
               },
               function (message) { //error
                  self._showError(message);
               }
            );
         }
      },


      /* Performs an ajax call to server to delete record
        *  and removes row of the record from table if ajax call success.
        *************************************************************************/
      _deleteRecordFromServer: function ($row, success, error, url) {
         var self = this;

         var completeDelete = function (data) {
            self._trigger("recordDeleted", null, {record: $row.data('record'), row: $row, serverResponse: data});

            if (success) {
               success(data);
            }
         };

         //Check if it is already being deleted right now
         if ($row.data('deleting') == true) {
            return;
         }

         $row.data('deleting', true);

         var postData = {};
         postData[self._keyField] = self._getKeyValueOfRecord($row.data('record'));

         //deleteAction may be a function, check if it is
         if (!url && $.isFunction(self.options.actions.deleteAction)) {

            //Execute the function
            var funcResult = self.options.actions.deleteAction(postData);

            //Check if result is a jQuery Deferred object
            if (self._isDeferredObject(funcResult)) {
               //Wait promise
               funcResult.done(function (data) {
                  completeDelete(data);
               }).fail(function () {
                  $row.data('deleting', false);
                  if (error) {
                     error(self.options.messages.serverCommunicationError);
                  }
               });
            } else { //assume it returned the deletion result
               completeDelete(funcResult);
            }

         } else { //Assume it's a URL string
            // Set id on url
            var deleteUrl = self.options.actions.deleteAction;
            var id = $row.data('record-key');

            if (null != id) {
               if (deleteUrl.indexOf('?') > -1) {
                  var urlPart = deleteUrl.substring(0, deleteUrl.indexOf('?'));
                  var paramPart = deleteUrl.substring(deleteUrl.indexOf('?'));
                  deleteUrl = urlPart + '/' + id + paramPart;
               } else
                  deleteUrl += '/' + id;
            }

            //Make ajax call to delete the record from server
            this._ajax({
               url: (url || deleteUrl),
               data: postData,
               method: 'DELETE',
               success: function (data) {
                  completeDelete(data);
               },
               error: function (errdata) {
                  $row.data('deleting', false);
                  self._showError((errdata && errdata[0] && errdata[0].responseJSON && errdata[0].responseJSON.message) ? errdata[0].responseJSON.message : self.options.messages.serverCommunicationError);
               }
            });

         }
      },

      /* Removes a row from table after a 'deleting' animation.
        *************************************************************************/
      _removeRowsFromTableWithAnimation: function ($rows, animationsEnabled) {
         var self = this;

         if (animationsEnabled == undefined) {
            animationsEnabled = self.options.animationsEnabled;
         }

         if (animationsEnabled) {
            var className = 'jtable-row-deleting';

            //Stop current animation (if does exists) and begin 'deleting' animation.
            $rows.stop(true, true).addClass(className, 'slow', '').promise().done(function () {
               self._removeRowsFromTable($rows, 'deleted');
            });
         } else {
            self._removeRowsFromTable($rows, 'deleted');
         }
      }

   });

})(jQuery);


/************************************************************************
 * PAGING extension for jTable                                           *
 *************************************************************************/
(function ($) {

   //Reference to base object members
   var base = {
      load: $.hik.jtable.prototype.load,
      _create: $.hik.jtable.prototype._create,
      _setOption: $.hik.jtable.prototype._setOption,
      _createRecordLoadUrl: $.hik.jtable.prototype._createRecordLoadUrl,
      _createJtParamsForLoading: $.hik.jtable.prototype._createJtParamsForLoading,
      _addRowToTable: $.hik.jtable.prototype._addRowToTable,
      _addRow: $.hik.jtable.prototype._addRow,
      _removeRowsFromTable: $.hik.jtable.prototype._removeRowsFromTable,
      _onRecordsLoaded: $.hik.jtable.prototype._onRecordsLoaded
   };

   //extension members
   $.extend(true, $.hik.jtable.prototype, {

      /************************************************************************
       * DEFAULT OPTIONS / EVENTS                                              *
       *************************************************************************/
      options: {
         paging: false,
         pageList: 'normal', //possible values: 'minimal', 'normal'
         pageSize: 10,
         pageSizes: [10, 25, 50, 100, 250, 500],
         pageSizeChangeArea: true,
         gotoPageArea: 'combobox', //possible values: 'textbox', 'combobox', 'none'

         messages: {
            pagingInfo: 'Showing {0}-{1} of {2}',
            pageSizeChangeLabel: 'Row count',
            gotoPageLabel: 'Go to page'
         }
      },

      /************************************************************************
       * PRIVATE FIELDS                                                        *
       *************************************************************************/

      _$bottomPanel: null, //Reference to the panel at the bottom of the table (jQuery object)
      _$pagingListArea: null, //Reference to the page list area in to bottom panel (jQuery object)
      _$pageSizeChangeArea: null, //Reference to the page size change area in to bottom panel (jQuery object)
      _$pageInfoSpan: null, //Reference to the paging info area in to bottom panel (jQuery object)
      _$gotoPageArea: null, //Reference to 'Go to page' input area in to bottom panel (jQuery object)
      _$gotoPageInput: null, //Reference to 'Go to page' input in to bottom panel (jQuery object)
      _totalRecordCount: 0, //Total count of records on all pages
      _currentPageNo: 1, //Current page number

      /************************************************************************
       * CONSTRUCTOR AND INITIALIZING METHODS                                  *
       *************************************************************************/

      /* Overrides base method to do paging-specific constructions.
        *************************************************************************/
      _create: function () {
         base._create.apply(this, arguments);

         if (this.options.paging) {
            this._loadPagingSettings();
            this._createBottomPanel();
            this._createPageListArea();
            this._createGotoPageInput();
            this._createPageSizeSelection();
         }
      },

      /* Loads user preferences for paging.
        *************************************************************************/
      _loadPagingSettings: function () {
         if (!this.options.saveUserPreferences) {
            return;
         }

         var pageSize = this._getCookie('page-size');
         if (pageSize) {
            this.options.pageSize = this._normalizeNumber(pageSize, 1, 1000000, this.options.pageSize);
         }
      },

      /* Creates bottom panel and adds to the page.
        *************************************************************************/
      _createBottomPanel: function () {
         this._$bottomPanel = $('<div />')
            .addClass('jtable-bottom-panel')
            .insertAfter(this._$table);

         $('<div />').addClass('jtable-left-area').appendTo(this._$bottomPanel);
         $('<div />').addClass('jtable-right-area').appendTo(this._$bottomPanel);
      },

      /* Creates page list area.
        *************************************************************************/
      _createPageListArea: function () {
         this._$pagingListArea = $('<span></span>')
            .addClass('jtable-page-list')
            .appendTo(this._$bottomPanel.find('.jtable-left-area'));

         this._$pageInfoSpan = $('<span></span>')
            .addClass('jtable-page-info')
            .appendTo(this._$bottomPanel.find('.jtable-right-area'));
      },

      /* Creates page list change area.
        *************************************************************************/
      _createPageSizeSelection: function () {
         var self = this;

         if (!self.options.pageSizeChangeArea) {
            return;
         }

         //Add current page size to page sizes list if not contains it
         if (self._findIndexInArray(self.options.pageSize, self.options.pageSizes) < 0) {
            self.options.pageSizes.push(parseInt(self.options.pageSize));
            self.options.pageSizes.sort(function (a, b) {
               return a - b;
            });
         }

         //Add a span to contain page size change items
         self._$pageSizeChangeArea = $('<span></span>')
            .addClass('jtable-page-size-change')
            .appendTo(self._$bottomPanel.find('.jtable-left-area'));

         //Page size label
         self._$pageSizeChangeArea.append('<span>' + self.options.messages.pageSizeChangeLabel + ': </span>');

         //Page size change combobox
         var $pageSizeChangeCombobox = $('<select></select>').addClass('form-control form-select form-control-sm').appendTo(self._$pageSizeChangeArea);

         //Add page sizes to the combobox
         for (var i = 0; i < self.options.pageSizes.length; i++) {
            $pageSizeChangeCombobox.append('<option value="' + self.options.pageSizes[i] + '">' + self.options.pageSizes[i] + '</option>');
         }

         //Select current page size
         $pageSizeChangeCombobox.val(self.options.pageSize);

         //Change page size on combobox change
         $pageSizeChangeCombobox.change(function () {
            self._changePageSize(parseInt($(this).val()));
         });
      },

      /* Creates go to page area.
        *************************************************************************/
      _createGotoPageInput: function () {
         var self = this;

         if (!self.options.gotoPageArea || self.options.gotoPageArea == 'none') {
            return;
         }

         //Add a span to contain goto page items
         this._$gotoPageArea = $('<span></span>')
            .addClass('jtable-goto-page')
            .appendTo(self._$bottomPanel.find('.jtable-left-area'));

         //Goto page label
         this._$gotoPageArea.append('<span>' + self.options.messages.gotoPageLabel + ': </span>');

         //Goto page input
         if (self.options.gotoPageArea == 'combobox') {

            self._$gotoPageInput = $('<select></select>')
               .addClass('form-control form-select form-control-sm')
               .appendTo(this._$gotoPageArea)
               .data('pageCount', 1)
               .change(function () {
                  self._changePage(parseInt($(this).val()));
               });
            self._$gotoPageInput.append('<option value="1">1</option>');

         } else { //textbox

            self._$gotoPageInput = $('<input type="text" maxlength="10" value="' + self._currentPageNo + '" />')
               .appendTo(this._$gotoPageArea)
               .keypress(function (event) {
                  if (event.which == 13) { //enter
                     event.preventDefault();
                     self._changePage(parseInt(self._$gotoPageInput.val()));
                  } else if (event.which == 43) { // +
                     event.preventDefault();
                     self._changePage(parseInt(self._$gotoPageInput.val()) + 1);
                  } else if (event.which == 45) { // -
                     event.preventDefault();
                     self._changePage(parseInt(self._$gotoPageInput.val()) - 1);
                  } else {
                     //Allow only digits
                     var isValid = (
                        (47 < event.keyCode && event.keyCode < 58 && event.shiftKey == false && event.altKey == false)
                        || (event.keyCode == 8)
                        || (event.keyCode == 9)
                     );

                     if (!isValid) {
                        event.preventDefault();
                     }
                  }
               });

         }
      },

      /* Refreshes the 'go to page' input.
        *************************************************************************/
      _refreshGotoPageInput: function () {
         if (!this.options.gotoPageArea || this.options.gotoPageArea == 'none') {
            return;
         }

         if (this._totalRecordCount <= 0) {
            this._$gotoPageArea.hide();
         } else {
            this._$gotoPageArea.show();
         }

         if (this.options.gotoPageArea == 'combobox') {
            var oldPageCount = this._$gotoPageInput.data('pageCount');
            var currentPageCount = this._calculatePageCount();
            if (oldPageCount != currentPageCount) {
               this._$gotoPageInput.empty();

               //Skip some pages is there are too many pages
               var pageStep = 1;
               if (currentPageCount > 10000) {
                  pageStep = 100;
               } else if (currentPageCount > 5000) {
                  pageStep = 10;
               } else if (currentPageCount > 2000) {
                  pageStep = 5;
               } else if (currentPageCount > 1000) {
                  pageStep = 2;
               }

               for (var i = pageStep; i <= currentPageCount; i += pageStep) {
                  this._$gotoPageInput.append('<option value="' + i + '">' + i + '</option>');
               }

               this._$gotoPageInput.data('pageCount', currentPageCount);
            }
         }

         //same for 'textbox' and 'combobox'
         this._$gotoPageInput.val(this._currentPageNo);
      },

      /************************************************************************
       * OVERRIDED METHODS                                                     *
       *************************************************************************/

      /* Overrides load method to set current page to 1.
        *************************************************************************/
      load: function () {
         this._currentPageNo = 1;

         base.load.apply(this, arguments);
      },

      /* Used to change options dynamically after initialization.
        *************************************************************************/
      _setOption: function (key, value) {
         base._setOption.apply(this, arguments);

         if (key == 'pageSize') {
            this._changePageSize(parseInt(value));
         }
      },

      /* Changes current page size with given value.
        *************************************************************************/
      _changePageSize: function (pageSize) {
         if (pageSize == this.options.pageSize) {
            return;
         }

         this.options.pageSize = pageSize;

         //Normalize current page
         var pageCount = this._calculatePageCount();
         if (this._currentPageNo > pageCount) {
            this._currentPageNo = pageCount;
         }
         if (this._currentPageNo <= 0) {
            this._currentPageNo = 1;
         }

         //if user sets one of the options on the combobox, then select it.
         var $pageSizeChangeCombobox = this._$bottomPanel.find('.jtable-page-size-change select');
         if ($pageSizeChangeCombobox.length > 0) {
            if (parseInt($pageSizeChangeCombobox.val()) != pageSize) {
               var selectedOption = $pageSizeChangeCombobox.find('option[value=' + pageSize + ']');
               if (selectedOption.length > 0) {
                  $pageSizeChangeCombobox.val(pageSize);
               }
            }
         }

         this._savePagingSettings();
         this._reloadTable();
      },

      /* Saves user preferences for paging
        *************************************************************************/
      _savePagingSettings: function () {
         if (!this.options.saveUserPreferences) {
            return;
         }

         this._setCookie('page-size', this.options.pageSize);
      },

      /* Overrides _createRecordLoadUrl method to add paging info to URL.
        *************************************************************************/
      _createRecordLoadUrl: function () {
         var loadUrl = base._createRecordLoadUrl.apply(this, arguments);
         loadUrl = this._addPagingInfoToUrl(loadUrl, this._currentPageNo);
         return loadUrl;
      },

      /* Overrides _createJtParamsForLoading method to add paging parameters to jtParams object.
        *************************************************************************/
      _createJtParamsForLoading: function () {
         var jtParams = base._createJtParamsForLoading.apply(this, arguments);

         if (this.options.paging) {
            jtParams.page = this._currentPageNo;
            jtParams.pageSize = this.options.pageSize;
         }

         return jtParams;
      },

      /* Overrides _addRowToTable method to re-load table when a new row is created.
        * NOTE: THIS METHOD IS DEPRECATED AND WILL BE REMOVED FROM FEATURE RELEASES.
        * USE _addRow METHOD.
        *************************************************************************/
      _addRowToTable: function ($tableRow, index, isNewRow) {
         if (isNewRow && this.options.paging) {
            this._reloadTable();
            return;
         }

         base._addRowToTable.apply(this, arguments);
      },

      /* Overrides _addRow method to re-load table when a new row is created.
        *************************************************************************/
      _addRow: function ($row, options) {
         if (options && options.isNewRow && this.options.paging) {
            this._reloadTable();
            return;
         }

         base._addRow.apply(this, arguments);
      },

      /* Overrides _removeRowsFromTable method to re-load table when a row is removed from table.
        *************************************************************************/
      _removeRowsFromTable: function ($rows, reason) {
         base._removeRowsFromTable.apply(this, arguments);

         if (this.options.paging) {
            if (this._$tableRows.length <= 0 && this._currentPageNo > 1) {
               --this._currentPageNo;
            }

            this._reloadTable();
         }
      },

      /* Overrides _onRecordsLoaded method to to do paging specific tasks.
        *************************************************************************/
      _onRecordsLoaded: function (data) {
         if (this.options.paging) {
            this._totalRecordCount = ('undefined' == typeof data.total) ? -1 : data.total;
            this._createPagingList();
            this._createPagingInfo();
            this._refreshGotoPageInput();

            if ('undefined' == typeof data.total)
               this._$bottomPanel.hide();
            else
               this._$bottomPanel.show();
         }

         base._onRecordsLoaded.apply(this, arguments);
      },

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* Adds jtStartIndex and jtPageSize parameters to a URL as query string.
        *************************************************************************/
      _addPagingInfoToUrl: function (url, pageNumber) {
         if (!this.options.paging) {
            return url;
         }


         return (url + (url.indexOf('?') < 0 ? '?' : '&') + 'page=' + pageNumber + '&pageSize=' + this.options.pageSize);
      },

      /* Creates and shows the page list.
        *************************************************************************/
      _createPagingList: function () {
         if (this.options.pageSize <= 0) {
            return;
         }

         this._$pagingListArea.empty();
         if (this._totalRecordCount <= 0) {
            return;
         }

         var pageCount = this._calculatePageCount();

         this._createFirstAndPreviousPageButtons();
         if (this.options.pageList == 'normal') {
            this._createPageNumberButtons(this._calculatePageNumbers(pageCount));
         }
         this._createLastAndNextPageButtons(pageCount);
         this._bindClickEventsToPageNumberButtons();
      },

      /* Creates and shows previous and first page links.
        *************************************************************************/
      _createFirstAndPreviousPageButtons: function () {
         var $first = $('<span></span>')
            .addClass('jtable-page-number-first')
            .html('&lt&lt')
            .data('pageNumber', 1)
            .appendTo(this._$pagingListArea);

         var $previous = $('<span></span>')
            .addClass('jtable-page-number-previous')
            .html('&lt')
            .data('pageNumber', this._currentPageNo - 1)
            .appendTo(this._$pagingListArea);
         if (this._currentPageNo <= 1) {
            $first.addClass('jtable-page-number-disabled');
            $previous.addClass('jtable-page-number-disabled');
         }
      },

      /* Creates and shows next and last page links.
        *************************************************************************/
      _createLastAndNextPageButtons: function (pageCount) {
         var $next = $('<span></span>')
            .addClass('jtable-page-number-next')
            .html('&gt')
            .data('pageNumber', this._currentPageNo + 1)
            .appendTo(this._$pagingListArea);
         var $last = $('<span></span>')
            .addClass('jtable-page-number-last')
            .html('&gt&gt')
            .data('pageNumber', pageCount)
            .appendTo(this._$pagingListArea);


         if (this._currentPageNo >= pageCount) {
            $next.addClass('jtable-page-number-disabled');
            $last.addClass('jtable-page-number-disabled');
         }
      },

      /* Creates and shows page number links for given number array.
        *************************************************************************/
      _createPageNumberButtons: function (pageNumbers) {
         var previousNumber = 0;
         for (var i = 0; i < pageNumbers.length; i++) {
            //Create "..." between page numbers if needed
            if ((pageNumbers[i] - previousNumber) > 1) {
               $('<span></span>')
                  .addClass('jtable-page-number-space')
                  .html('...')
                  .appendTo(this._$pagingListArea);
            }

            this._createPageNumberButton(pageNumbers[i]);
            previousNumber = pageNumbers[i];
         }
      },

      /* Creates a page number link and adds to paging area.
        *************************************************************************/
      _createPageNumberButton: function (pageNumber) {
         var $pageNumber = $('<span></span>')
            .addClass('jtable-page-number')
            .html(pageNumber)
            .data('pageNumber', pageNumber)
            .appendTo(this._$pagingListArea);

         if (this._currentPageNo == pageNumber) {
            $pageNumber.addClass('jtable-page-number-active jtable-page-number-disabled');
         }
      },

      /* Calculates total page count according to page size and total record count.
        *************************************************************************/
      _calculatePageCount: function () {
         var pageCount = Math.floor(this._totalRecordCount / this.options.pageSize);
         if (this._totalRecordCount % this.options.pageSize != 0) {
            ++pageCount;
         }

         return pageCount;
      },

      /* Calculates page numbers and returns an array of these numbers.
        *************************************************************************/
      _calculatePageNumbers: function (pageCount) {
         if (pageCount <= 4) {
            //Show all pages
            var pageNumbers = [];
            for (var i = 1; i <= pageCount; ++i) {
               pageNumbers.push(i);
            }

            return pageNumbers;
         } else {
            //show first three, last three, current, previous and next page numbers
            var shownPageNumbers = [1, 2, pageCount - 1, pageCount];
            var previousPageNo = this._normalizeNumber(this._currentPageNo - 1, 1, pageCount, 1);
            var nextPageNo = this._normalizeNumber(this._currentPageNo + 1, 1, pageCount, 1);

            this._insertToArrayIfDoesNotExists(shownPageNumbers, previousPageNo);
            this._insertToArrayIfDoesNotExists(shownPageNumbers, this._currentPageNo);
            this._insertToArrayIfDoesNotExists(shownPageNumbers, nextPageNo);

            shownPageNumbers.sort(function (a, b) {
               return a - b;
            });
            return shownPageNumbers;
         }
      },

      /* Creates and shows paging informations.
        *************************************************************************/
      _createPagingInfo: function () {
         if (this._totalRecordCount <= 0) {
            this._$pageInfoSpan.empty();
            return;
         }

         var startNo = (this._currentPageNo - 1) * this.options.pageSize + 1;
         var endNo = this._currentPageNo * this.options.pageSize;
         endNo = this._normalizeNumber(endNo, startNo, this._totalRecordCount, 0);

         if (endNo >= startNo) {
            var pagingInfoMessage = this._formatString(this.options.messages.pagingInfo, startNo, endNo, this._totalRecordCount);
            this._$pageInfoSpan.html(pagingInfoMessage);
         }
      },

      /* Binds click events of all page links to change the page.
        *************************************************************************/
      _bindClickEventsToPageNumberButtons: function () {
         var self = this;
         self._$pagingListArea
            .find('.jtable-page-number,.jtable-page-number-previous,.jtable-page-number-next,.jtable-page-number-first,.jtable-page-number-last')
            .not('.jtable-page-number-disabled')
            .click(function (e) {
               e.preventDefault();
               self._changePage($(this).data('pageNumber'));
            });
      },

      /* Changes current page to given value.
        *************************************************************************/
      _changePage: function (pageNo) {
         pageNo = this._normalizeNumber(pageNo, 1, this._calculatePageCount(), 1);
         if (pageNo == this._currentPageNo) {
            this._refreshGotoPageInput();
            return;
         }

         this._currentPageNo = pageNo;
         this._reloadTable();
      }

   });

})(jQuery);


/************************************************************************
 * MASTER/CHILD tables extension for jTable                              *
 *************************************************************************/
(function ($) {

   //Reference to base object members
   var base = {
      _removeRowsFromTable: $.hik.jtable.prototype._removeRowsFromTable
   };

   //extension members
   $.extend(true, $.hik.jtable.prototype, {

      /************************************************************************
       * DEFAULT OPTIONS / EVENTS                                              *
       *************************************************************************/
      options: {},

      /************************************************************************
       * PUBLIC METHODS                                                        *
       *************************************************************************/

      /* Creates and opens a new child table for given row.
        *************************************************************************/
      openChildTable: function ($row, tableOptions, opened) {
         var self = this;

         //Show close button as default
         tableOptions.showCloseButton = (tableOptions.showCloseButton != false);

         //Close child table when close button is clicked (default behavior)
         if (tableOptions.showCloseButton && !tableOptions.closeRequested) {
            tableOptions.closeRequested = function () {
               self.closeChildTable($row);
            };
         }

         //Close child table for this row and open new one for child table
         self.closeChildTable($row, function () {
            var $childRowColumn = self.getChildRow($row).empty();
            var $childTableContainer = $('<div />')
               .addClass('jtable-child-table-container')
               .appendTo($childRowColumn);
            $childTableContainer.jtable(tableOptions);
            self.openChildRow($row);
            $childTableContainer.hide().slideDown('fast', function () {
               if (opened) {
                  opened({
                     childTable: $childTableContainer
                  });
               }
            });
         });
      },

      /* Closes child table for given row.
        *************************************************************************/
      closeChildTable: function ($row, closed) {
         var self = this;

         var $childRowColumn = this.getChildRow($row);
         var $childTable = $childRowColumn.find('.jtable');
         if (!$childTable.length) {
            if (closed) {
               closed();
            }

            return;
         }

         if ($childTable.data('hik-jtable')) {
            $childTable.jtable('destroy');
         }

         $childTable.slideUp('fast', function () {
            $childRowColumn.remove();
            if (closed) {
               closed();
            }
         });
      },

      /* Returns a boolean value indicates that if a child row is open for given row.
        *************************************************************************/
      isChildRowOpen: function ($row) {
         return (this.getChildRow($row).is(':visible'));
      },

      /* Gets child row for given row, opens it if it's closed (Creates if needed).
        *************************************************************************/
      getChildRow: function ($row) {
         var $childRow = $row.siblings('.jtable-child-row');
         if ($childRow.length) {
            return $childRow;
         } else
            return this._createChildRow($row);
      },

      /* Creates and opens child row for given row.
        *************************************************************************/
      openChildRow: function ($row) {
         var $childRow = this.getChildRow($row);
         if (!$childRow.is(':visible')) {
            $childRow.show();
         }

         return $childRow;
      },

      /* Closes child row if it's open.
        *************************************************************************/
      closeChildRow: function ($row) {
         var $childRow = this.getChildRow($row);
         if ($childRow.is(':visible')) {
            $childRow.hide();
         }
      },

      /************************************************************************
       * OVERRIDED METHODS                                                     *
       *************************************************************************/

      /* Overrides _removeRowsFromTable method to remove child rows of deleted rows.
        *************************************************************************/
      _removeRowsFromTable: function ($rows, reason) {
         //var self = this;

         if (reason == 'deleted') {
            $rows.each(function () {
               var $row = $(this);
               var $childRow = $row.data('childRow');
               if ($childRow) {
                  $childRow.remove();
               }
            });
         }

         base._removeRowsFromTable.apply(this, arguments);
      },

      /************************************************************************
       * PRIVATE METHODS                                                       *
       *************************************************************************/

      /* Creates a child row for a row, hides and returns it.
        *************************************************************************/
      _createChildRow: function ($row) {
         var totalColumnCount = this._$table.find('thead th').length;
         var $childRow = null;
         if (this.options.bootstrap) {
            $childRow = $('<div></div>')
               .addClass('jtable-child-row');
         } else {
            $childRow = $('<tr></tr>')
               .addClass('jtable-child-row')
               .append('<td colspan="' + totalColumnCount + '"></td>');
         }
         $row.after($childRow);
         $childRow.hide();
         return $childRow;
      }

   });

})(jQuery);
