<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PoolCron extends Command
{
	protected $signature = 'pool:cron';
	protected $description = 'Run required cron jobs in succession.';

	public function handle()
	{
		$this->call('pool:download-data');
		$this->call('stats:miners');
		$this->call('stats:pool');
		$this->call('alerts:miners');
		$this->info('PoolCron completed successfully.');
	}
}
