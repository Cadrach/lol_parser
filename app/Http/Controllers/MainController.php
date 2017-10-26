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
            'gameID' => 'V2', //should be P, but requires PHP 64bits and PHP>=5.6.3, but the gamesIDs are still low, so it works by reading the first ulong
            'gameLength' => 'I',
            'keyframeCount' => 'I',
            'chunkCount' => 'I',
            'endStartupChunkID' => 'I',
            'startGameChunkID' => 'I',
            'keyframeInterval' => 'I',
            'encryptionKeyLength' => 'v',
            'encryptionKey' => 'a*', //(string, see encryption key length, base64 encoded)
        ],
        'chunkHeader' => [
            'chunkID' => 'I',
            'chunkType' => 'C', //(ubyte - 0 indicates keyframe, 1 indicates chunk)
            'chunkLength' => 'I',
            'nextChunkID' => 'I',
            'offset' => 'I',
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

    protected function getEncryptionKey($payloadHeader){
        //Game ID is our key
        $gameID = (string)($payloadHeader['gameID1'] + $payloadHeader['gameID2'] + 4294967296);
        $encryptedKey = base64_decode($payloadHeader['encryptionKey']);
        return $this->decrypt($gameID, $encryptedKey);
    }

    /**
     * Decrypt a string
     * @param $key
     * @param $string
     * @return string
     */
    protected function decrypt($key, $string){
        //Decrypt
        $paddedDecryptedString = mcrypt_decrypt(MCRYPT_BLOWFISH, $key, $string, MCRYPT_MODE_ECB);

        //Now we must remove the padding added by the PKCS5Padding
        $additionalChars = ord(substr($paddedDecryptedString, -1, 1));
        $decryptedString = substr($paddedDecryptedString, 0, -$additionalChars);

        //Return the final key
        return $decryptedString;
    }

    public function getIndex(){
        ini_set('memory_limit', -1);
        echo '<pre>';

        //File read
        $file = storage_path() . '/replays/EUW1-3390001675.rofl';

        //Open file in binary
        $h = fopen ($file, "rb");

        //Magic identifier
        $magic = fread($h, 6);

        //Signature of the file
        $signature = fread($h, 256);

        //Lengths
        $lengths = $this->unpack('lengths', fread($h, 26));

        //Meta data
        $metadata = json_decode(fread($h, $lengths['metadataLength']), true);
        $metadata['statsJson'] = json_decode($metadata['statsJson'], true);

        //Payload header
        $payloadHeader = $this->unpack('payloadHeader', fread($h, $lengths['payloadHeaderLength']));

        //Encryption Key
        $encryptionKey = $this->getEncryptionKey($payloadHeader);

        $chunkHeaders = [];
        $blockCounts = $payloadHeader['chunkCount'] + $payloadHeader['keyframeCount'];
        for($i=0;$i<$blockCounts;$i++){
            $chunkHeaders[] = $this->unpack('chunkHeader', fread($h, 17));
        }

        $chunks = [];
        foreach($chunkHeaders as $chunkHeader){
            $chunk = gzdecode($this->decrypt($encryptionKey, fread($h, $chunkHeader['chunkLength'])));
            if($chunkHeader['chunkID'] >= $payloadHeader['startGameChunkID']){
                $chunks[] = $chunk;
            }
        }

        file_put_contents($file . '.chunks', implode("\n\n***********************************************************************\n\n", $chunks));

//        $chunkContent = $this->unpack('chunkHeader', fread($h, 17));
//        print_r($chunkContent);
//        $chunkContent = $this->unpack('chunkHeader', fread($h, 17));
//        print_r($chunkContent);
//        $chunkContent = $this->unpack('chunkHeader', fread($h, 17));
//        print_r($chunkContent);
//        $chunkContent = $this->unpack('chunkHeader', fread($h, 17));
//        print_r($chunkContent);



//

//        die();
//        echo $encryptionKey . "\n";
        print_r($chunkHeaders[0]);
        print_r($lengths);
        print_r($payloadHeader);
        print_r($metadata);



    }
}
