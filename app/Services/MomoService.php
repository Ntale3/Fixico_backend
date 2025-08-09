<?php
namespace App\Services;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
class MomoService{
  protected $client;
  protected $baseUrl;
  protected $primaryKey;
  protected $apiUser;
  protected $apiKey;
  protected $targetEnv;
  protected $currency;

 public function __construct(){
  $this->client=new Client();
  $this->baseUrl=env('MOMO_BASE_URL');
  $this->primaryKey=env('MOMO_PRIMARY_KEY');
  $this->apiUser=env('MOMO_API_USER');
  $this->apiKey=env('MOMO_API_KEY');
  $this->targetEnv=env('MOMO_TARGET_ENVIRONMENT','sandbox');
  $this->currency=env('MOMO_CURRENCY','UGX');
 }

 protected function createUser(){
  $response=$this->client->post("{$this->baseUrl}/v1_0/apiuser",[
    'headers'=>[
      'X-Reference-Id'=> $this->apiUser,
      'Content-Type'=>   'application/json',
      'Ocp-Apim-Subscription-Key'=>$this->primaryKey
    ],
    'json'=>[
      "providerCallbackHost" => "string"

    ]
  ]);
  return $response;
 }

 protected function getApiKey(){
  $response=$this->client->post("{$this->baseUrl}/v1_0/apiuser/{$this->apiUser}/apikey",[
    'headers'=>[
      'Ocp-Apim-Subscription-Key'=>$this->primaryKey
    ]
  ]);
  return $response->getBody()->getContents();
 }

 public function getToken(){
  $response=$this->client->post("{$this->baseUrl}/collection/token/",[
    'headers'=>[
      'Authorization'=> 'Basic '. base64_encode($this->apiUser . ':' . $this->apiKey),
       'Ocp-Apim-Subscription-Key'=>$this->primaryKey

    ]
  ]);

  $data=json_decode($response->getBody(),true);
  return $data['access_token']??null;

 }

 public function requestToPay($paynumber,$amount){
  $uuid=(string)Str::uuid();
  $this->client->post("https://sandbox.momodeveloper.mtn.com/collection/v1_0/requesttopay",[
  'headers'=>[
    'Authorization'=>'Bearer'. $this->getToken(),
    'X-Reference-Id'=>$uuid,
    'X-Target-Environment'=>$this->targetEnv,
    'Ocp-Apim-Subscription-Key'=>$this->primaryKey
  ],
  'json'=>[

  "amount"=>number_format($amount, 2, '.', ''),
  "currency"=>$this->currency,
  "externalId"=>"00004335",
  "payer"=>[
    "partyIdType"=>"MSISDN",
    "partyId"=>$paynumber
    ],
  "payerMessage"=>"MoMo Market Payment",
  "payeeNote"=>"MoMo Market Payment"

  ]
  ]);
  return $uuid;
 }


}