<?php

namespace Ledningssystemet\Ledningssystemet\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Ledningssystemet\Ledningssystemet\Models\Process;
use Ledningssystemet\Ledningssystemet\Support\ProcessChartBpmn;
use Illuminate\Support\Facades\Log;

#[Signature('ledningssystemet:convert-process-charts')]
#[Description('Convert process charts to the new format')]
class ConvertProcessCharts extends Command
{
   /**
    * Execute the console command.
    */
   public function handle()
   {
      $chartFailures = [];
      $chartCount = 0;
      $failedChartCount = 0;
      foreach (Process::whereNull('process_chart_draft')->whereNotNull('bpmn')->cursor() as $process) {
         $this->info("Converting draft process chart for process: {$process->name}");
         $chartCount++;
         try {
            // Get process chart array from the BPMN XML
            $processChartArray = ProcessChartBpmn::fromXml($process->bpmn);

            // Ensure this is a valid array
            if(!is_array($processChartArray)) {
                throw new \Exception("Invalid draft process chart array: ".print_r($processChartArray, true));
            }

            // Create BPMN XML from the process chart array
            $newBpmnXml = ProcessChartBpmn::toXml($processChartArray);

            // Convert the newly created BPMN XML back to a process chart array to ensure it's valid
            $newProcessChartArray = ProcessChartBpmn::fromXml($newBpmnXml);

            // Compare the original and new process chart arrays to ensure they contain the same data even though the order of elements may differ
            self::compareProcessChartArrays($processChartArray, $newProcessChartArray);

            // Store the process chart array in the process_chart_draft field quietly without triggering any model events or observers
            $process->process_chart_draft = json_encode($processChartArray);
            $process->saveQuietly();
         } catch (\Exception $e) {
            $failedChartCount++;
            $chartFailures[] = "Failed to convert draft process chart for process: {$process->name}. Error: {$e->getMessage()}";
            $this->error("Failed to convert draft process chart for process: {$process->name}. Error: {$e->getMessage()}");
         }
      }

      foreach (Process::whereNull('process_chart_published')->whereNotNull('publishedbpmn')->cursor() as $process) {
         $this->info("Converting published process chart for process: {$process->name}");
         $chartCount++;
         try {
            // Get process chart array from the BPMN XML
            $processChartArray = ProcessChartBpmn::fromXml($process->publishedbpmn);

            // Ensure this is a valid array
            if(!is_array($processChartArray)) {
               throw new \Exception("Invalid published process chart array: ".print_r($processChartArray, true));
            }

            // Create BPMN XML from the process chart array
            $newBpmnXml = ProcessChartBpmn::toXml($processChartArray);

            // Convert the newly created BPMN XML back to a process chart array to ensure it's valid
            $newProcessChartArray = ProcessChartBpmn::fromXml($newBpmnXml);

            // Compare the original and new process chart arrays to ensure they contain the same data even though the order of elements may differ
            self::compareProcessChartArrays($processChartArray, $newProcessChartArray);

            // Store the process chart array in the process_chart_published field quietly without triggering any model events or observers
            $process->process_chart_published = json_encode($processChartArray);
            $process->saveQuietly();
         } catch (\Exception $e) {
            $failedChartCount++;
            $chartFailures[] = "Failed to convert published process chart for process: {$process->name}. Error: {$e->getMessage()}";
            $this->error("Failed to convert published process chart for process: {$process->name}. Error: {$e->getMessage()}");
         }
      }

      $this->info("Conversion completed. Total charts processed: {$chartCount}");
      $this->info("Total charts failed: {$failedChartCount}");
      if($failedChartCount > 0) {
         $this->warn("Failed charts details:");
         foreach ($chartFailures as $failure) {
            $this->warn($failure);
         }
      }

      Log::info("Conversion completed. Total charts processed: {$chartCount}, Total charts failed: {$failedChartCount}");
   }
   public static function compareProcessChartArrays(array &$array1, array &$array2): void
   {
      // Sort both arrays by keys to ensure order doesn't matter
      ksort($array1);
      ksort($array2);

      // Iterate through the first array
      foreach ($array1 as $key => &$value) {
         // Check if the key exists in the second array
         if (!array_key_exists($key, $array2)) {
             throw new \Exception("Key '{$key}' not found in the second array.");
         }

         // If the value is an array, recursively compare
         if (is_array($value)) {
             if (!is_array($array2[$key])) {
                 throw new \Exception("Value for key '{$key}' is not an array in the second array.");
             }
             self::compareProcessChartArrays($value, $array2[$key]);
         } else {
             // If the value is not an array, compare values
             if ($value !== $array2[$key]) {
                 throw new \Exception("Value mismatch for key '{$key}': '{$value}' !== '{$array2[$key]}'.");
             }
         }
      }

      // Check for any extra keys in the second array
      foreach ($array2 as $key => &$value) {
         if (!array_key_exists($key, $array1)) {
             throw new \Exception("Extra key '{$key}' found in the second array.");
         }
      }
   }
}
