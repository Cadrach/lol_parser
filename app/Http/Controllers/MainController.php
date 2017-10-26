<?php

namespace App\Http\Controllers;

class MainController extends Controller
{
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
        $metadata = json_decode(fread($h, $lengths['metadataLength']), true);
        $metadata['statsJson'] = json_decode($metadata['statsJson'], true);

        echo '<pre>';
        print_r($lengths);
        print_r($metadata);
    }
}
