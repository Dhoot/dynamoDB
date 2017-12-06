<?php

namespace App\Console\Commands;

use App\Http\Controllers\DynamoDBController;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;

class BackUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display an inspiring quote';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dynamoDBController = new DynamoDBController;
        $dynamoDBController->takeBackUp();
    }
}
