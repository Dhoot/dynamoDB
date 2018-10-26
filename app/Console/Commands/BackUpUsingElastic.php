<?php

namespace App\Console\Commands;

use App\Http\Controllers\ElasticController;
use Illuminate\Console\Command;

class BackUpUsingElastic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup-elastic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Doing Backup Using Elastic';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $users = array();
        $elasticController = new ElasticController();
        $elasticController->index($users);
    }
}
