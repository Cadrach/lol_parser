<?php

namespace App\Http\Controllers;

class MainController extends Controller
{
    /**
     * Reference page: https://github.com/robertabcd/lol-ob/wiki/ROFL-Container-Notes
     * @var array
     */
    protected $unpackers = [
        'lengths' => [
            'header' => 'v',
            'file' => 'I',
            'metadataOffset' => 'I',
            'metadataLength' => 'I',
            'payloadHeaderOffset' => 'I',
            'payloadHeaderLength' => 'I',
            'payloadOffset' => 'I',
        ],
        'payloadHeader' => [
            'gameId' => 'V2', //should be P
            'gameLength' => 'I',
            'keyframeCount' => 'I',
            'chunkCount' => 'I',
            'endStartupChunkId' => 'I',
            'startGameChunkId' => 'I',
            'keyframeInterval' => 'I',
            'encryptionKeyLength' => 'v',
            'encryptionKey' => 'a*', //(string, see encryption key length, base64 encoded)
        ]
    ];

    /**
     * Unpack using $unpackers information
     * @param $type
     * @param $data
     * @return array
     */
    protected function unpack($type, $data){
        $format = [];
        foreach($this->unpackers[$type] as $k=>$v){
            $format[] = $v.$k;
        }
        return unpack(implode('/', $format), $data);
    }

    public function getIndex(){

        //File read
        $file = storage_path() . '/replays/EUW1-3390001675.rofl';

        //Open file in binary
        $h = fopen ($file, "rb");

        //Magic identifier
        $magic = fread($h, 6);
        //Signature of the file (used to decrypt?)
        $signature = fread($h, 256);
        //Lengths
        $lengths = $this->unpack('lengths', fread($h, 26));
        //Meta data
        $metadata = json_decode(fread($h, $lengths['metadataLength']), true);
        $metadata['statsJson'] = json_decode($metadata['statsJson'], true);
        //Payload header
        $payloadHeader = $this->unpack('payloadHeader', fread($h, $lengths['payloadHeaderLength']));


        echo '<pre>';
        print_r($lengths);
        print_r($payloadHeader);
        print_r($metadata);

    }
}
