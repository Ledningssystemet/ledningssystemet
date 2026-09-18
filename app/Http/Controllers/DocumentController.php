<?php

namespace App\Http\Controllers;

use DOMDocument;
use XSLTProcessor;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
   public static function generateJTableDocumentFields($classname)
   {
      $idx = 0;
      foreach(glob(resource_path('templates/xslt/'.$classname.'/*.xslt')) as $filename)
      {
         $fileinfo = pathinfo($filename);

         // Get meta data from template file by fetching the first comment block in the file and parsing it as JSON
         $filecontent = file_get_contents($filename);
         preg_match('/<!--(.*?)-->/s', $filecontent, $matches);
         if(count($matches) < 2)
            continue;
         $meta = json_decode($matches[1]);
         if(null == $meta)
            continue;

         // Ensure there is a language property
         if(!property_exists($meta, 'language'))
            continue;

         // Ensure our locale matches the language property
         if(app()->getLocale() != $meta->language)
            continue;

         // Ensure the name property exists
         if(!property_exists($meta, 'name'))
            continue;

         // Create array
         echo('generatedocument_'.$idx++.": {\r\n");
         echo("   'type': 'command',\r\n");
         echo("   'create': false,\r\n");
         echo("   'edit': false,\r\n");
         echo("   'list': true,\r\n");
         echo("   'footer': true,\r\n");
         echo("   'display': function(data){\r\n");
         echo("      var retobj= $('<a />')\r\n");
         echo("         .prop('href', '/api/v1/documentcontroller/".$fileinfo['filename']."/".$classname."/'+data.record.id)\r\n");
         echo("         .addClass('btn btn-outline-primary btn-sm')\r\n");
         echo("         .css({cursor: 'pointer'})\r\n");
         echo("         .append($('<span />').addClass('material-symbols-rounded').text('print'))\r\n");
         echo("         .append('".addslashes($meta->name)."');\r\n");
         echo("      return retobj;\r\n");
         echo("   }\r\n");
         echo("},\r\n");
      }
   }

   private static function addModelData(&$xml, &$root, &$foreignkeys, &$models, &$model, &$addedNodes = []) : void
   {
      $tagname = strtolower(class_basename($model));

      // Check if model has already been added, if so skip
      if(in_array(['model' => $model::class, 'id' => $model->id], $addedNodes))
         return;

      // Create root node
      $rootnode = $root->appendChild($xml->createElement($tagname));
      $addedNodes[] = ['model' => $model::class, 'id' => $model->id];

      // Set attributes
      foreach($model->getAttributes() as $key => $value) {
         $rootnode->setAttribute($key, $value);
      }

      // Set relations
      $fks = array_filter($foreignkeys, function($fk) use ($model, $models) {
         return $fk->srctable == $models[$model::class];
      });
      foreach($fks as $fk)
      {
         dd($model, $fks);
         // Get key for matching array value
         $srckey = array_search($fk->srctable, $models);

         // Ensure it is a valid model
         if(!class_exists($srckey))
            continue;

         // Find model with matching id
         $refobject = $srckey::where($fk->srccolumn, $model->{$fk->dstcolumn})->first();

         if(null == $refobject)
            continue;

         // Check if child model has already been added, if so skip
         if(in_array(['model' => $srckey, 'id' => $refobject->id], $addedNodes))
            continue;

         // Check if the xml document already contains a container tag, otherwise we will crate a new one with the pluralized name of the child model
         $childtagname = strtolower(class_basename($refobject));
         $containername = $childtagname.'s';
         $container = $xml->getElementsByTagName($containername)->item(0);
         if(null == $container)
            $container = $root->appendChild($xml->createElement($containername));

         // Create child node
         DocumentController::addModelData($xml, $container, $foreignkeys, $models, $refobject, $addedNodes);
      }



      // Create incoming referencing objects
      $fks = array_filter($foreignkeys, function($fk) use ($model, $models) {
         return $fk->dsttable == $models[$model::class];
      });

      // Recursively add child nodes
      foreach($fks as $fk)
      {
         // Find model name of the src table
         $classname = array_search($fk->srctable, $models);

         if(!class_exists($classname))
            continue;

         // Fetch all elements that reference to this object
         foreach(($classname)::where($fk->srccolumn, $model->id)->get() as $childmodel)
         {
            // Check if child model has already been added, if so skip
            if(in_array(['model' => $childmodel, 'id' => $childmodel->id], $addedNodes))
               continue;

            // Check if the xml document already contains a container tag, otherwise we will crate a new one with the pluralized name of the child model
            $childtagname = strtolower(class_basename($childmodel));
            $containername = $childtagname.'s';
            $container = $root->getElementsByTagName($containername)->item(0);
            if(null == $container)
               $container = $root->appendChild($xml->createElement($containername));

            // Create child node
            DocumentController::addModelData($xml, $container, $foreignkeys, $models, $childmodel, $addedNodes);
         }
      }
   }

   public static function generateXml($model, $xslt) : string
   {
      // Generate a list of all models and their corresponding database table by traversing all model files and then call getTable() on each model
      $models = [];
      foreach(glob(app_path('Models').'/*.php') as $filename) {
         // Get only the class name from the file path and remove the .php extension
         $fileinfo = pathinfo($filename);
         $classname = 'App\\Models\\'.$fileinfo['filename'];
         $tablename = (new $classname)->getTable();

         $models[$classname] = $tablename;
      }

      // Generate a list of all foreign keys in the database
      $foreignkeys = DB::select('SELECT TABLE_NAME AS srctable,COLUMN_NAME AS srccolumn, CONSTRAINT_NAME AS fkname, REFERENCED_TABLE_NAME AS dsttable,REFERENCED_COLUMN_NAME AS dstcolumn FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE (REFERENCED_TABLE_SCHEMA="'.DB::getDatabaseName().'")');

      // Create whitelist of attributes to include in XML
      $whitelistmodels = [];
      $whitelistattributes = [];

      // Find all attributes and nodes in the xslt and add them to the whitelist
      foreach($xslt->xpath('//*[@match]') as $node) {
         $path = $node['match']->__toString();
         foreach(explode('/', $path) as $part) {
            if(isset($whitelistmodels[$part]))
               continue;

            if("" == $part)
               continue;

            $whitelistmodels[$part] = $part;
         }
      }

      // Create XML document
      $xml = new DOMDocument('1.0', 'UTF-8');

      // Create root element
      $root = $xml->createElement('document');
      $xml->appendChild($root);
      $xml->formatOutput = true;

      // Add static data to root element
      $root->appendChild($xml->createElement('created_at', date('Y-m-d H:i:s')));
      $root->appendChild($xml->createElement('created_by', auth()->user()->name));

      // Perform recursive conversion of model to XML
      DocumentController::addModelData($xml, $root, $foreignkeys, $models, $model);

      return $xml->saveXML();
   }

   public static function generatePdf($doc, $template, $draft)
   {
      $xmldoc = new DOMDocument();
      if($xmldoc->loadXML($doc) === false)
         throw new \Exception("Failed to load XML document");


      $xsldoc = new DOMDocument();
      if($xsldoc->loadXML($template) === false)
         throw new \Exception("Failed to load XSL template");

      $xslproc = new XSLTProcessor();


      libxml_use_internal_errors(true);

      $result = $xslproc->importStyleSheet($xsldoc);
      $errors = libxml_get_errors();

      libxml_use_internal_errors(false);

      if($errors) {
         throw new \Exception("Failed to import XSL stylesheet");
      }

      $xml = $xslproc->transformToXML($xmldoc);

      // Get document options
      $papersize = 'A4';
      $orientation = 'portrait';

      preg_match('/<!--(.*?)-->/s', $template, $matches);
      if(count($matches) == 2)
      {
         $meta = json_decode($matches[1]);
         if(null != $meta)
         {
            if(property_exists($meta, 'papersize'))
               $papersize = $meta->papersize;

            if(property_exists($meta, 'orientation'))
               $orientation = ($meta->orientation == 'landscape') ? 'landscape' : 'portrait';
         }
      }


      // Create new DOMPDF instance
      $dompdf = new Dompdf();

      $options = $dompdf->getOptions();
      $options->setFontDir(resource_path('fonts'));
      $options->setFontCache(resource_path('fonts'));
      $options->set('isRemoteEnabled', true);
      $options->setIsPhpEnabled(true);

      // Load HTML content
      $dompdf->loadHtml($xml);

      // Set options
      $dompdf->setPaper($papersize, $orientation);

      // Render the pdf
      $dompdf->render();

      // Return generated PDF
      return $dompdf->output();
   }
}
