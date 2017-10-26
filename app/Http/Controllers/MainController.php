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
        'chunk' => [
            'a' => 'v', //float
            'b' => 'v', //float
            'c' => 'v', //float
            'd' => 'v', //float
            'e' => 'v', //float
            'f' => 'v', //float
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
        error_reporting(E_ERROR & ~E_DEPRECATED);

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

        $blockHeaders = [];
        $blockCounts = $payloadHeader['chunkCount'] + $payloadHeader['keyframeCount'];
        for($i=0;$i<$blockCounts;$i++){
            $blockHeaders[] = $this->unpack('chunkHeader', fread($h, 17));
        }

        $chunks = [];
        $keyFrames = [];
        foreach($blockHeaders as $blockHeader){
            $chunk = gzdecode($this->decrypt($encryptionKey, fread($h, $blockHeader['chunkLength'])));

            if($blockHeader['chunkID'] < $payloadHeader['startGameChunkID']){
                //ignore before games starts
                continue;
            }

            if($blockHeader['chunkType'] == 1){
                $chunks[] = substr($chunk, 0, 160);
            }
            else if($blockHeader['chunkType'] == 2){
                $keyFrames[] = substr($chunk, 0, 160);
            }
            else{
                throw new \Exception('Unknown BLOCK');
            }
        }

        $test = $this->unpack('chunk', $keyFrames[0]);

        print_r($test);


        file_put_contents($file . '.chunks', implode("\n\n****************************************************************************\n\n", $chunks));
        file_put_contents($file . '.keyframes', implode("\n\n****************************************************************************\n\n", $keyFrames));

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
        echo count($chunks) . " CHUNKS, " . count($keyFrames) . " KEYFRAMES\n\n";
        echo "LENGTHS\n";
        print_r($lengths);
        echo "\n\n\n\n\n\nPAYLOAD HEADER\n";
        print_r($payloadHeader);
        echo "\n\n\n\n\n\nBLOCKS HEADERS ".count($blockHeaders)."\n";
        print_r($blockHeaders);
        echo "\n\n\n\n\n\nMETADATA\n";
        print_r($metadata);



    }
}
