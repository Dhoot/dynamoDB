<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests;
use AWS;
use Prophecy\Exception\Exception;

class DynamoDBController extends Controller
{
    private $dynamo;
    private $organisation = "utgIPo9zU9XTELIkWLnc";//"demolab";
    private $tableNameBase = "mailsphere-live-internal-index-";//"mailsphere-test-default-index-";
    private $tableNameAU = "";
    private $tableNameA = "";
    private $tableNameD = "";
    private $attributesToGetAU = array( "user", "archive");
    private $attributesToGetA = array( "data", "fingerprint", "instance");
    private $attributesToGetD = array( "id", "length");
    private $lastEvaluatedKeyAU = null;
    private $lastEvaluatedKeyA = null;
    private $lastEvaluatedKeyD = null;
    private $scanFilterAU = null;
    private $scanFilterA = null;
    private $scanFilterD = null;
    private $allUsers;


    public function index() {
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
          $item = $itemsAU[$i];
          $archive = explode("|", $item['archive']['S']);
          if (strlen($archive[0]) == 0 || strlen($archive[1]) == 0) {
            continue;
          }

          $this->scanFilterA["fingerprint"]["AttributeValueList"] = [['S' => $archive[0]]];
          $this->scanFilterA["fingerprint"]["ComparisonOperator"] = "EQ";
          $this->scanFilterA["instance"]["AttributeValueList"] = [['S' => $archive[1]]];
          $this->scanFilterA["instance"]["ComparisonOperator"] = "EQ";
          try {
            $result = $this->queryWithLast($this->tableNameA, $this->scanFilterA, $this->attributesToGetA, $this->lastEvaluatedKeyA);
          } catch (Exception $e) {
            sleep(5);
            $result = $this->queryWithLast($this->tableNameA, $this->scanFilterA, $this->attributesToGetA, $this->lastEvaluatedKeyA);
          }
          $this->lastEvaluatedKeyA = $result->get('LastEvaluatedKey');
          $itemsA = $result->get('Items');
          $resultSizeA = $result->get('Count');


          $this->scanFilterD["id"]["AttributeValueList"] = [$itemsA[0]['data']];
          $this->scanFilterD["id"]["ComparisonOperator"] = "EQ";
          try {
            $result = $this->queryWithLast($this->tableNameD, $this->scanFilterD, $this->attributesToGetD, $this->lastEvaluatedKeyD);
          } catch (Exception $e) {
            sleep(5);
            $result = $this->queryWithLast($this->tableNameA, $this->scanFilterA, $this->attributesToGetA, $this->lastEvaluatedKeyA);
          }
          $this->lastEvaluatedKeyD = $result->get('LastEvaluatedKey');
          $itemsD = $result->get('Items');
          $resultSizeD = $result->get('Count');

          if (isset($this->allUsers[$this->organisation][$item['user']['S']]['length'])) {
            $this->allUsers[$this->organisation][$item['user']['S']]['length'] += $itemsD[0]['length']['N'];
          }
          else {
            $this->allUsers[$this->organisation][$item['user']['S']]['length'] = $itemsD[0]['length']['N'];
          }
          $this->changeEnv(['allUsers'   => \GuzzleHttp\json_encode($this->allUsers)]);

          if ($i == $resultSizeAU - 1) {
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
          }
        }



        //$allUsers[$this->organisation][$itemsAU[$i]['user']['S']] = $itemsAU[$i];
        echo "<pre>$resultSizeAU";print_r($this->allUsers[$this->organisation]);echo "</pre>";exit();

    }


    function scanWithLast($tableName, $scanFilter, $attributesToGet, $lastEvaluatedKey){

      //querying table
      $request["AttributesToGet"] = $attributesToGet;
      $request['ConsistentRead'] = true;
      $request['Limit'] = 10000;
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
      $request['Limit'] = 10000;
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
}
