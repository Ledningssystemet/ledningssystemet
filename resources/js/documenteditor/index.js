import EditorJS from '@editorjs/editorjs';
import Header from '@editorjs/header';
import Paragraph from '@editorjs/paragraph';
import List from '@editorjs/list';
import Nestedlist from '@editorjs/nested-list';
import Checklist from '@editorjs/checklist';
import Link from '@editorjs/link';
import Table from '@editorjs/table';
import {isFunction} from "min-dash";


var editor = null;

// Extend with functions
jQuery.fn.extend({
   documentEditor: function (params, args) {
      if('string' === typeof params) {
         switch (params) {
            case 'load':
               var doc = args && args.doc ? args.doc : [];
               var change = args && args.change && isFunction(args.change) ? args.change : null;

               $('#editorJscontainer').empty();

               editor = new EditorJS({
                  /**
                   * Id of Element that should contain the Editor
                   */
                  holder: 'editorJscontainer',

                  data: doc,
                  tools: {
                     header: {
                        class: Header,
                        inlineToolbar: true,
                     },
                     list: {
                        class: List,
                        inlineToolbar: true,
                        config: {
                           defaultStyle: 'unordered'
                        },
                     },
                     paragraph: {
                        class: Paragraph,
                        inlineToolbar: true
                     },
                  },
                  onChange: change,
                  readOnly: args && args.readonly ? args.readonly : false,
               });
               break;
            case 'save':
               if(null == editor) {
                  showDialog(translateString("Action failed"), 'Editor not initialized');
               }
               else {
                  editor.save().then((outputData) => {
                     if(args && args.success && 'function' === typeof args.success)
                        args.success(outputData);
                  }).catch((error) => {
                     showDialog(translateString("Action failed"), error);
                  });
               }
            break;
         }
      }
   }
});

