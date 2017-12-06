<?php
namespace App\Http\Controllers;

use Guzzle\Service\Exception\CommandTransferException;
use Illuminate\Http\Request;
use App\Http\Requests;
use AWS;
use Prophecy\Exception\Exception;

class DynamoDBController extends Controller
{
    private $dynamo;
    private $s3;
    private $organisation = "utgIPo9zU9XTELIkWLnc";//"demolab";
    private $enviromentPrefix = "mailsphere-live-internal-";//"mailsphere-test-default-";
    private $tableNameBase = "index-";
    private $tableNameAU = "";
    private $tableNameA = "";
    private $tableNameD = "";
    private $attributesToGetAU = array( "user", "archive");
    private $attributesToGetA = array( "data", "fingerprint", "instance");
    private $attributesToGetD = array( "id", "length");
    private $lastEvaluatedKeyAU = null;
    private $lastEvaluatedKeyA = null;
    private $lastEvaluatedKeyD = null;
    private $scanFilterAU = array();
    private $scanFilterA = array();
    private $scanFilterD = array();
    private $allUsers;
    private $users = array();


    public function index() {

        exec('ps aux | grep "inspire" | grep -v grep', $pids);
        if (count($pids) > 2) {
          exit();
        }

        $this->tableNameBase = $this->enviromentPrefix.$this->tableNameBase;
        $this->tableNameAU = $this->tableNameBase."archives-Users";
        $this->tableNameA = $this->tableNameBase."archives";
        $this->tableNameD = $this->tableNameBase."datas";

        $this->allUsers[$this->organisation] = array();
        $lastEvaluatedKey = null;
        //DynamoDB
        $this->dynamo = AWS::createClient('DynamoDb');


        $this->scanFilterAU["organisation"]["AttributeValueList"] = array(array('S'=>$this->organisation));
        $this->scanFilterAU["organisation"]["ComparisonOperator"] = "EQ";

        /*$this->lastEvaluatedKeyAU = array('user' => array('S' => 'AMdOEreoLDrHQobTp24t'),'archive' => array('S' => '|XfFikTSzcUbokwI+VAWSPg=='));
        $this->changeEnv(['lastEvaluatedKeyAU'   => json_encode($this->lastEvaluatedKeyAU)]);
        $this->changeEnv(['allUsers'   => json_encode($this->allUsers)]);*/

        $this->allUsers = json_decode(env('allUsers'),true);
        $this->lastEvaluatedKeyAU = json_decode(env('lastEvaluatedKeyAU'),true);
        //print "<pre>";print_r($this->lastEvaluatedKeyAU);print_r($this->allUsers);exit();
        do {
          $result = $this->scanWithLast($this->tableNameAU, $this->scanFilterAU, $this->attributesToGetAU, $this->lastEvaluatedKeyAU);
          $this->lastEvaluatedKeyAU = $result->get('LastEvaluatedKey');
          $this->changeEnv(['lastEvaluatedKeyAU'   => \GuzzleHttp\json_encode($this->lastEvaluatedKeyAU)]);
          $itemsAU = $result->get('Items');
          $resultSizeAU = $result->get('Count');
          //sleep(2);
          echo "<pre>$resultSizeAU";print_r($this->lastEvaluatedKeyAU); echo "</pre>";
        } while($this->lastEvaluatedKeyAU != null && $resultSizeAU==0);

        for ($i = 0; $i < $resultSizeAU && $resultSizeAU; $i++) {

          foreach ($itemsAU as $item) {
            $archive = explode("|", $item['archive']['S']);
            if (strlen($archive[0]) == 0) {
              continue;
            }
            if(!$this->in_array_r($archive[0],$this->scanFilterA)) {
              $this->scanFilterA[] = [
                "fingerprint" => ['S' => $archive[0]],
                "instance" => ['S' => $archive[1]]
              ];
              $this->users[$item['archive']['S']]['user'] = $item['user']['S'];
            }
          }

          $result = $this->batchGetItem($this->tableNameA, $this->scanFilterA, $this->attributesToGetA);
          $item = $result->get('Responses');
          $itemsA = $item[$this->tableNameA];

          foreach ($itemsA as $item) {
            if(!$this->in_array_r($item['data']['S'],$this->scanFilterD)) {
              $this->scanFilterD[] = ["id" => ['S' => $item['data']['S']]];
              $this->users[$item['fingerprint']['S'] . "|" . $item['instance']['S']][$item['data']['S']] = 1;
            }
          }
          $result = $this->batchGetItem($this->tableNameD, $this->scanFilterD, $this->attributesToGetD);
          $item = $result->get('Responses');
          $itemsD = $item[$this->tableNameD];

          foreach ($itemsD as $item) {
            foreach ($this->users as $archive=>$sUser){
              foreach ($sUser as $dataId=>$val){
                if($dataId=="user") {
                  $user = $val;
                  continue;
                }
                if($dataId == $item['id']['S']){
                  if (isset($this->allUsers[$this->organisation][$user]['length'])) {
                    $this->allUsers[$this->organisation][$user]['length'] += $item['length']['N'];
                  }
                  else {
                    $this->allUsers[$this->organisation][$user]['length'] = $item['length']['N'];
                  }
                  $this->changeEnv(['allUsers'   => \GuzzleHttp\json_encode($this->allUsers)]);
                }
              }
            }
          }

          if (count($this->lastEvaluatedKeyAU) > 0) {
            $this->scanFilterA = array();
            $this->scanFilterD = array();
            try {
              $result = $this->scanWithLast($this->tableNameAU, $this->scanFilterAU, $this->attributesToGetAU, $this->lastEvaluatedKeyAU);
              $itemsAU = $result->get('Items');
              $resultSizeAU = $result->get('Count');
              $this->lastEvaluatedKeyAU = $result->get('LastEvaluatedKey');
              $this->changeEnv(['lastEvaluatedKeyAU'   => \GuzzleHttp\json_encode($this->lastEvaluatedKeyAU)]);
            } catch (Exception $e) {
              sleep(5);
              $result = $this->scanWithLast($this->tableNameAU, $this->scanFilterAU, $this->attributesToGetAU, $this->lastEvaluatedKeyAU);
              $itemsAU = $result->get('Items');
              $resultSizeAU = $result->get('Count');
              $this->lastEvaluatedKeyAU = $result->get('LastEvaluatedKey');
              $this->changeEnv(['lastEvaluatedKeyAU'   => \GuzzleHttp\json_encode($this->lastEvaluatedKeyAU)]);
            }
            $i = 0;
            $this->users = array();
          }

        }

        echo "<pre>$resultSizeAU";print_r($this->allUsers[$this->organisation]);echo "</pre>";exit();
    }

    function scanWithLast($tableName, $scanFilter, $attributesToGet, $lastEvaluatedKey){

      //querying table
      $request["AttributesToGet"] = $attributesToGet;
      $request['ConsistentRead'] = true;
      $request['Limit'] = 100;
      //$request["ConditionalOperator"] = "AND";
      $request['TableName'] = $tableName;
      if($lastEvaluatedKey){
        $request['ExclusiveStartKey'] = $lastEvaluatedKey;
      }
      $request['ScanFilter'] = $scanFilter;
      //sleep(5);
      //echo "<pre>";print_r($scanFilter);echo "</pre><hr/>";
      return $this->dynamo->Scan($request);//getItem($request);

    }

    function queryWithLast($tableName, $queryFilter, $attributesToGet, $lastEvaluatedKey) {

      //querying table
      $request["AttributesToGet"] = $attributesToGet;
      $request['ConsistentRead'] = TRUE;
      $request['Limit'] = 100;
      //$request["ConditionalOperator"] = "AND";
      $request['TableName'] = $tableName;
      if ($lastEvaluatedKey) {
        $request['ExclusiveStartKey'] = $lastEvaluatedKey;
      }
        $request['KeyConditions'] = $queryFilter;
        //sleep(1);
        //echo "<pre>"; print_r($queryFilter);echo "</pre><hr/>";
        return $this->dynamo->Query($request);//getItem($request);
    }

    function batchGetItem($tableName, $queryFilter, $attributesToGet) {

      //querying table
      $request["AttributesToGet"] = $attributesToGet;
      $request['ConsistentRead'] = TRUE;
      $request['Keys'] = $queryFilter;
      $req['RequestItems'][$tableName] = $request;

        //sleep(1);
        //echo "<pre>"; print_r($request);echo "</pre><hr/>";exit();
        return $this->dynamo->BatchGetItem($req);//getItem($request);
    }

    protected function changeEnv($data = array()){
      if(count($data) > 0){

        // Read .env-file
        $env = file_get_contents(base_path() . '/.env');

        // Split string on every " " and write into array
        $env = preg_split('/\s+/', $env);;

        // Loop through given data
        foreach((array)$data as $key => $value){

          // Loop through .env-data
          foreach($env as $env_key => $env_value){

            // Turn the value into an array and stop after the first split
            // So it's not possible to split e.g. the App-Key by accident
            $entry = explode("=", $env_value, 2);

            // Check, if new key fits the actual .env-key
            if($entry[0] == $key){
              // If yes, overwrite it with the new one
              $env[$env_key] = $key . "=" . $value;
            } else {
              // If not, keep the old one
              $env[$env_key] = $env_value;
            }
          }
        }

        // Turn the array back to an String
        $env = implode("\n", $env);

        // And overwrite the .env with the new data
        file_put_contents(base_path() . '/.env', $env);

        return true;
      } else {
        return false;
      }
    }

    function in_array_r($needle, $haystack, $strict = false) {
      foreach ($haystack as $item) {
        if (($strict ? $item === $needle : $item == $needle) || (is_array($item) && $this->in_array_r($needle, $item, $strict))) {
          return true;
        }
      }

      return false;
    }

    function takeBackUp($orgID = null){
        if($orgID === null){
          $orgID = $this->organisation;
        } else {
          $this->organisation = $orgID;
        }

        exec('ps aux | grep "backup" | grep -v grep', $pids);
        if (count($pids) > 2) {
          exit();
        }

        $this->tableNameBase = $this->enviromentPrefix.$this->tableNameBase;
        $this->tableNameAU = $this->tableNameBase."archives-Users";
        $this->tableNameA = $this->tableNameBase."archives";
        $this->tableNameD = $this->tableNameBase."datas";

        $this->allUsers[$this->organisation] = array();
        $lastEvaluatedKey = null;

        //DynamoDB
        $this->dynamo = AWS::createClient('DynamoDb');

        //S3
        $this->s3 = AWS::createClient('s3');

        //Create Bucket
        $bucket = $this->enviromentPrefix.'backup';
        if(!$this->s3->doesBucketExist($bucket)) {
          $this->s3->createBucket(array(
            'Bucket' => $bucket
          ));

          $this->s3->waitUntil('BucketExists', array('Bucket' => $bucket));
        }

        $this->scanFilterAU["organisation"]["AttributeValueList"] = array(array('S'=>$this->organisation));
        $this->scanFilterAU["organisation"]["ComparisonOperator"] = "EQ";

        $this->allUsers = json_decode(env('allUsers'),true);
        $this->lastEvaluatedKeyAU = json_decode(env('lastEvaluatedKeyAU'),true);

        do {
          print_r($this->lastEvaluatedKeyAU);
          $result = $this->scanWithLast($this->tableNameAU, $this->scanFilterAU, $this->attributesToGetAU, $this->lastEvaluatedKeyAU);
          $this->lastEvaluatedKeyAU = $result->get('LastEvaluatedKey');
          $this->changeEnv(['lastEvaluatedKeyAU'   => \GuzzleHttp\json_encode($this->lastEvaluatedKeyAU)]);
          $itemsAU = $result->get('Items');
          $resultSizeAU = $result->get('Count');
          sleep(2);
          echo "<pre>$resultSizeAU";print_r($this->lastEvaluatedKeyAU); echo "</pre>";
        } while($this->lastEvaluatedKeyAU != null && $resultSizeAU==0);

        for ($i = 0; $i < $resultSizeAU && $resultSizeAU; $i++) {

          foreach ($itemsAU as $item) {
            $archive = explode("|", $item['archive']['S']);
            if (strlen($archive[0]) == 0) {
              continue;
            }
            if(!$this->in_array_r($archive[0],$this->scanFilterA)) {
              $this->scanFilterA[] = [
                "fingerprint" => ['S' => $archive[0]],
                "instance" => ['S' => $archive[1]]
              ];
              $this->users[$item['archive']['S']]['user'] = $item['user']['S'];
            }
          }

          $result = $this->batchGetItem($this->tableNameA, $this->scanFilterA, $this->attributesToGetA);
          $item = $result->get('Responses');
          $itemsA = $item[$this->tableNameA];

          foreach ($itemsA as $item) {
            if(!$this->in_array_r($item['data']['S'],$this->scanFilterD)) {
              $this->scanFilterD[] = ["id" => ['S' => $item['data']['S']]];
              $this->users[$item['fingerprint']['S'] . "|" . $item['instance']['S']][$item['data']['S']] = 1;
            }
          }
          $result = $this->batchGetItem($this->tableNameD, $this->scanFilterD, $this->attributesToGetD);
          $item = $result->get('Responses');
          $itemsD = $item[$this->tableNameD];

          foreach ($itemsD as $item) {
            foreach ($this->users as $archive=>$sUser){
              foreach ($sUser as $dataId=>$val){
                if($dataId=="user") {
                  $user = $val;
                  continue;
                }
                if($dataId == $item['id']['S']){
                  $emlFileName = last(explode("/",$item['hotStoreLocation']['S']));
                  if (isset($this->allUsers[$this->organisation][$user]['emails'])) {
                    $this->allUsers[$this->organisation][$user]['emails']++;
                  }
                  else {
                    $this->allUsers[$this->organisation][$user]['emails'] = 1;
                  }

                  $this->s3->copyObject(array(
                    'Bucket'     => $bucket,
                    'Key'        => $orgID.'/'.$user.'/'.$emlFileName.'.eml',
                    'CopySource' => $this->enviromentPrefix.'hotstore/default/'.$emlFileName,
                  ));
                  $this->changeEnv(['allUsers'   => \GuzzleHttp\json_encode($this->allUsers)]);
                }
              }
            }
          }

          if (count($this->lastEvaluatedKeyAU) > 0) {
            $this->scanFilterA = array();
            $this->scanFilterD = array();
            try {
              $result = $this->scanWithLast($this->tableNameAU, $this->scanFilterAU, $this->attributesToGetAU, $this->lastEvaluatedKeyAU);
              $itemsAU = $result->get('Items');
              $resultSizeAU = $result->get('Count');
              $this->lastEvaluatedKeyAU = $result->get('LastEvaluatedKey');
              $this->changeEnv(['lastEvaluatedKeyAU'   => \GuzzleHttp\json_encode($this->lastEvaluatedKeyAU)]);
            } catch (Exception $e) {
              sleep(5);
              $result = $this->scanWithLast($this->tableNameAU, $this->scanFilterAU, $this->attributesToGetAU, $this->lastEvaluatedKeyAU);
              $itemsAU = $result->get('Items');
              $resultSizeAU = $result->get('Count');
              $this->lastEvaluatedKeyAU = $result->get('LastEvaluatedKey');
              $this->changeEnv(['lastEvaluatedKeyAU'   => \GuzzleHttp\json_encode($this->lastEvaluatedKeyAU)]);
            }
            $i = 0;
            $this->users = array();
          }

        }

        echo "<pre>$resultSizeAU";print_r($this->allUsers[$this->organisation]);echo "</pre>";exit();

    }
}
