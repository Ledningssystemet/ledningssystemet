<?php

namespace Ledningssystemet\Ledningssystemet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PurgeApiTokens extends Command
{
   /**
    * The name and signature of the console command.
    *
    * @var string
    */
   protected $signature = 'ledningssystemet:purgeapitokens';

   /**
    * The console command description.
    *
    * @var string
    */
   protected $description = 'Purge expired API tokens';

   /**
    * Execute the console command.
    *
    * @return int
    */
   public function handle()
   {
      $this->info('Starting purge of expired API tokens');

      $deleted = DB::table('personal_access_tokens')
         ->where('expires_at', '<', now())
         ->delete();

      $this->info("Deleted {$deleted} expired API tokens");

      return Command::SUCCESS;
   }
}
