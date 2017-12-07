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
        $users = array("caUDPpecoPVHUsjyTZDL","gqWmyjt5jkHcXgI0fNa4","61MefvE6VtYaPoYxXK5Q","ANuEPO31czkkm25A1vYE","NxECCLCu33NSNVXti2gD","kRiHdme6Z0FxiK6mPWD5","aPGRrgjVC7gAQjhcKOXe","5v4BTyhnmMvuP4SNZLf5","q6xXSPH8N6TqWKFOw1m5","Y1VRpwVVglxyq33XQRDy","hdKkZTiqc5wYFqdqza6R","F8I4WnSWG7tNRo1Dz84l","6roTa5IgJOxFTLeCB8p1","OkPIwLAhTTQU3xW9m7hN","oJGWrTQcieqBUQH6kAOQ","itYfCBCpr6lwAurxmrGe","zQSi1Brw7PcKvjVrOKcD","5mwlK1AaIvlaW6nrbZ6s","wvPpRW7tEBZBGMII6aZO","dlXB9pOKjfinZTPDiCPB","hHgTHs2ChQhSU1QqWTfx","SCDEaejpYFFALxZmFupA","00H5Xa4uREhsxYwpWSIl","RmD6oaAgZ1xJYrW9RzkT","zVdENFg3jvDeI9cDHzjJ","JqfhCrxT6T7Wfdd5gHre","oxYlpb5jX6ywqVL1g4id","B0qixyZwjFETRyaqBcuL","V9804pKBNtnsBYY1mJHJ","YmWVFstzk6FzBuN0M8KQ","CIq2D17zz2s3wCnhmYb5","mGUvA9Iz53nu1neprEKC","d9uOFnYrOas5hX8EQjlp","J2KimfsAeOS0Prz0uymP","MfgJ2xJsTxW400mipYK7","ZdYIWMV1vfxubwcbuzEu","r2TRPnAUFXINlR6LVziP","jHj7s32BDTVwkydg5EIy","Wgq7kVJr3rs6iYVlbzxC","QOBAAoYzYqsH8lWrFH52","1HwCRAbiSx6QvDsCz3eA","4Ohcvcn3P9mXng4lfg5b","Eei3NsCJOX5HWWGx2JSr","Nulg2RMFVPaVjIl6jTaE");
        $dynamoDBController = new DynamoDBController;
        $dynamoDBController->takeBackUp(null, $users);
    }
}
