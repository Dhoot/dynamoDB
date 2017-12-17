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
        $users = array("NxECCLCu33NSNVXti2gD","kRiHdme6Z0FxiK6mPWD5","aPGRrgjVC7gAQjhcKOXe","q6xXSPH8N6TqWKFOw1m5","oJGWrTQcieqBUQH6kAOQ","itYfCBCpr6lwAurxmrGe","wvPpRW7tEBZBGMII6aZO","dlXB9pOKjfinZTPDiCPB","00H5Xa4uREhsxYwpWSIl","RmD6oaAgZ1xJYrW9RzkT","JqfhCrxT6T7Wfdd5gHre","YmWVFstzk6FzBuN0M8KQ","CIq2D17zz2s3wCnhmYb5","mGUvA9Iz53nu1neprEKC","d9uOFnYrOas5hX8EQjlp","J2KimfsAeOS0Prz0uymP","MfgJ2xJsTxW400mipYK7","r2TRPnAUFXINlR6LVziP","Wgq7kVJr3rs6iYVlbzxC","Eei3NsCJOX5HWWGx2JSr");
        $dynamoDBController = new DynamoDBController;
        $dynamoDBController->takeBackUp(null, $users);
    }
}
