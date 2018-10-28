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
  private $tableNameA = "";
  private $tableNameD = "";
  private $attributesToGetA = array( "data", "fingerprint", "instance");
  private $attributesToGetD = array( "id", "length", "hotStoreLocation");
  private $scanFilterA = array();
  private $scanFilterD = array();


  public function __construct() {
    $this->elasticBaseUrl = strtolower(env('ELASTIC_NODE'));
    $this->organisation = strtolower(env('ORGANISATION')); //"demolab";
    $this->environmentPrefix = env('ENVIRONMENT_PREFIX'); //"mailsphere-test-default-";
    $this->currentStartingPoint = env('CURRENT_STARTING_POINT');
    $this->size = env('BATCH_SIZE');
    $this->allUsers = json_decode(env('allUsers'),true);

    $this->tableNameBase = $this->environmentPrefix.$this->tableNameBase;
    $this->tableNameA = $this->tableNameBase."archives";
    $this->tableNameD = $this->tableNameBase."datas";
    $this->dynamoDBObj = new DynamoDBController();
    $this->s3 = AWS::createClient('s3');

    //Create Bucket
    $bucket = $this->environmentPrefix.'backup';
    if(!$this->s3->doesBucketExist($bucket)) {
      $this->s3->createBucket(array(
        'Bucket' => $bucket
      ));

      $this->s3->waitUntil('BucketExists', array('Bucket' => $bucket));
    }
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
    exec('ps aux | grep "backup-elastic" | grep -v grep', $pids);
    if (count($pids) > 2 || env('scanningDone') == 1) {
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


    $this->scanFilterD = array();
    $foundEmails = array();
    $this->scanFilterA = array();

    if($response != null && isset($response->hits->hits) && count($response->hits->hits)) {
      $this->currentStartingPoint += $this->size;

      foreach ($response->hits->hits as $eResult){

        $parts = explode('|', $eResult->_id);
        $last = array_pop($parts);
        $archive = array(implode('|', $parts), $last);
        if (strlen($archive[0]) == 0) {
          continue;
        }

        $this->scanFilterA[] = [
          'fingerprint' => ['S' => $archive[0]],
          'instance' => ['S' => $archive[1]]
        ];


        foreach ($this->scanFilterA as $k => $filter) {
          if($k < count($this->scanFilterA) - 1) {
            if ($filter['fingerprint']['S'] == $archive[0] && $filter['instance']['S'] == $archive[1]) {
              unset($this->scanFilterA[$k]);
              break;
            }
          }
        }


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

      $this->scanFilterA = array_map('array_values', $this->scanFilterA);
      $result = $this->dynamoDBObj->batchGetItem($this->tableNameA, $this->scanFilterA, $this->attributesToGetA);
      $item = $result->get('Responses');
      $itemsA = $item[$this->tableNameA];

      foreach ($itemsA as $item) {

        $this->scanFilterD[] = ["id" => ['S' => $item['data']['S']]];
        $foundEmails[$item['data']['S']] = $foundEmails[$item['fingerprint']['S']."|".$item['instance']['S']];
        unset($foundEmails[$item['fingerprint']['S']."|".$item['instance']['S']]);

        foreach ($this->scanFilterD as $k => $filter) {
          if($k < count($this->scanFilterD) - 1) {
            if ($filter['id']['S'] == $item['data']['S']) {
              unset($foundEmails[$filter['id']['S']]);
              unset($this->scanFilterD[$k]);
              break;
            }
          }
        }
      }

      $this->scanFilterD = array_map('array_values', $this->scanFilterD);
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

      $this->dynamoDBObj->changeEnv(['CURRENT_STARTING_POINT'   => $this->currentStartingPoint]);
      $this->dynamoDBObj->changeEnv(['allUsers'   => \GuzzleHttp\json_encode($this->allUsers)]);
      $this->index();
    }

    $this->dynamoDBObj->changeEnv(['scanningDone'   => 1]);
    exit();
  }
}