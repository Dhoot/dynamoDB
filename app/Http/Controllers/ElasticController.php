<?php
/**
 * User: ronakdhoot
 * Date: 25/10/18
 * Time: 1:05 PM
 */

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use AWS;


class ElasticController {

  private $dynamoDBObj;
  private $s3;
  private $currentStartingPoint = 0;
  private $size = 100;
  private $elasticBaseUrl = "";
  private $organisation = "";
  private $environmentPrefix = "";
  private $tableNameBase = "index-";
  /*org Users */
  private $users = array();
  private $allUsers = array();
  private $tableNameD = "";
  private $attributesToGetD = array( "id", "length", "hotStoreLocation");
  private $scanFilterD = array();


  public function __construct() {
    $this->elasticBaseUrl = strtolower(env('ELASTIC_NODE'));
    $this->organisation = strtolower(env('ORGANISATION')); //"demolab";
    $this->environmentPrefix = env('ENVIRONMENT_PREFIX'); //"mailsphere-test-default-";
    $this->currentStartingPoint = env('CURRENT_STARTING_POINT');
    $this->currentStartingPoint = env('BATCH_SIZE');

    $this->tableNameBase = $this->environmentPrefix.$this->tableNameBase;
    $this->tableNameD = $this->tableNameBase."datas";
    $this->dynamoDBObj = new DynamoDBController();
    $this->s3 = AWS::createClient('s3');
  }

  private function callApi($url, $options){
    $client = new Client();
    $res = $client->request('GET', $url, $options);
    if($res->getStatusCode()) {
      return $res->getBody();
    }
    return null;
  }

  public function index($givenUsers = array()) {

    if(count($givenUsers) > 0) {
      $this->users = $givenUsers;
    }
    exec('ps aux | grep "inspire" | grep -v grep', $pids);
    if (count($pids) > 2) {
      exit();
    }
    $url = 'http://'.$this->elasticBaseUrl.'/'.$this->organisation.'-v0/EMAIL/_search';
    $options['headers'] = array('Content-Type' => 'application/json');
    $bodyObj = new \stdClass();
    $bodyObj->fields =  array('ownerIds', 'spam', 'from');
    $bodyObj->size =  $this->size;
    $bodyObj->from =  $this->currentStartingPoint;
    /*$bodyObj->aggs =  new \stdClass();
    $bodyObj->aggs->by_userId = new \stdClass();
    $bodyObj->aggs->by_userId->terms = new \stdClass();
    $bodyObj->aggs->by_userId->terms->field = 'ownerIds';
    $bodyObj->aggs->by_userId->terms->size = 100000;
    $bodyObj->aggs->by_userId->aggs = new \stdClass();
    $bodyObj->aggs->by_userId->aggs->total_size = new \stdClass();
    $bodyObj->aggs->by_userId->aggs->total_size->sum = new \stdClass();
    $bodyObj->aggs->by_userId->aggs->total_size->sum->field = 'messageSize';*/

    $options['body'] = json_encode($bodyObj);

    $response = json_decode($this->callApi($url, $options));

    if($response != null && isset($response->hits->hits) && count($response->hits->hits)) {
      $this->currentStartingPoint += $this->size;
      $this->dynamoDBObj->changeEnv(['CURRENT_STARTING_POINT'   => $this->currentStartingPoint]);
      $this->scanFilterD = array();
      $foundEmails = array();

      foreach ($response->hits->hits as $eResult){
        $eResult->fields->tos;
        $this->scanFilterD[] = ["id" => ['S' => $eResult->_id]];
        if (isset( $this->allUsers[$this->organisation][$eResult->fields->ownerIds[0]]['emails'])) {
          $this->allUsers[$this->organisation][$eResult->fields->ownerIds[0]]['emails'] += 1;
        }
        else {
          $this->allUsers[$this->organisation][$eResult->fields->ownerIds[0]]['emails'] = 1;
        }

        $foundEmails[$eResult->_id]['state'] = 'INBOX';
        $foundEmails[$eResult->_id]['user'] = isset($eResult->fields->ownerIds[0]) ? $eResult->fields->ownerIds[0] : 'Unknown';
        if(isset($eResult->fields->spam) && isset($eResult->fields->spam[0]) && $eResult->fields->spam[0] == true) {
          $foundEmails[$eResult->_id]['state'] = 'SPAM_INBOX';
        }
        else if(isset($eResult->fields->from) && isset($eResult->fields->from[0]) && $this->dynamoDBObj->in_array_r($eResult->fields->from[0], $this->users)) {
          $foundEmails[$eResult->_id]['state'] = 'SENT';
        }

      }

      $result = $this->dynamoDBObj->batchGetItem($this->tableNameD, $this->scanFilterD, $this->attributesToGetD);
      $item = $result->get('Responses');
      $itemsD = $item[$this->tableNameD];
      $bucket = $this->environmentPrefix.'backup';

      foreach ($itemsD as $item) {
        $user = $foundEmails[$item['id']['S']]['user'];
        $state = $foundEmails[$item['id']['S']]['state'];
        $emlFileName = last(explode("/",$item['hotStoreLocation']['S']));
        if(!$this->s3->doesObjectExist($bucket, $this->organisation . '/' . $user . '/' . $state . '/' . $emlFileName . '.eml')) {
          $this->s3->copyObject([
            'Bucket' => $bucket,
            'Key' => $this->organisation . '/' . $user . '/' . $state . '/' . $emlFileName . '.eml',
            'CopySource' => $this->environmentPrefix . 'hotstore/default/' . $emlFileName,
          ]);
        }
      }

      $this->dynamoDBObj->changeEnv(['allUsers'   => \GuzzleHttp\json_encode($this->allUsers)]);
    }

    $this->dynamoDBObj->changeEnv(['scanningDone'   => 1]);
    exit();
  }
}