<?php
declare(strict_types=1);
// Offline file encryption only. Never connects to the database or loads .env.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
umask(0077);
$destination=null;$written=false;$input=null;$output=null;$key=null;
try{
 if(!extension_loaded('sodium')){throw new RuntimeException('Enable the sodium extension.');}
 $options=getopt('',['mode:','key-file:','input:','output:']);$mode=$options['mode']??'';
 if(!in_array($mode,['keygen','encrypt','decrypt'],true)){throw new RuntimeException('Use --mode=keygen|encrypt|decrypt --key-file=ABSOLUTE_PATH [--input=ABSOLUTE_PATH --output=ABSOLUTE_PATH].');}
 $outside=static function(mixed $value):string{
  if(!is_string($value)||$value===''||!preg_match('~^(?:[A-Za-z]:[\\/]|/)~',$value)){throw new RuntimeException('Use absolute local file paths.');}
  $parent=realpath(dirname($value));$root=realpath(dirname(__DIR__,2));
  if(!$parent||!$root||is_link($value)){throw new RuntimeException('Use an existing directory and a regular file path.');}
  $candidate=$parent.DIRECTORY_SEPARATOR.basename($value);$compare=strtolower(str_replace('\\','/',$candidate));$web=strtolower(str_replace('\\','/',$root));
  if($compare===$web||str_starts_with($compare,$web.'/')){throw new RuntimeException('Keep keys, SQL and backup files outside the project/web root.');}
  return $candidate;
 };
 $keyPath=$outside($options['key-file']??null);
 if($mode==='keygen'){
  $destination=$keyPath;$output=fopen($destination,'xb');if(!$output){throw new RuntimeException('Key file already exists or cannot be created.');}$written=true;
  $key=random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);$bytes=base64_encode($key)."\n";
  if(fwrite($output,$bytes)!==strlen($bytes)){throw new RuntimeException('Could not write key.');}
  fclose($output);$output=null;sodium_memzero($key);fwrite(STDOUT,"Backup key created. Restrict its NTFS permissions and store it separately.\n");exit;
 }
 if(!is_file($keyPath)||filesize($keyPath)>128){throw new RuntimeException('Use a valid backup key file.');}
 $key=base64_decode(trim((string)file_get_contents($keyPath)),true);
 if($key===false||strlen($key)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES){throw new RuntimeException('Invalid backup key.');}
 $source=$outside($options['input']??null);$destination=$outside($options['output']??null);
 if(!is_file($source)||$source===$destination||$keyPath===$source||$keyPath===$destination||strtolower(dirname($keyPath))===strtolower(dirname($destination))){throw new RuntimeException('Use a regular input, a new output and a separate directory for the backup key.');}
 $input=fopen($source,'rb');if(!$input){throw new RuntimeException('Cannot open input.');}
 $output=fopen($destination,'xb');if(!$output){throw new RuntimeException('Cannot create exclusive output.');}$written=true;
 $write=static function($stream,string $bytes):void{if(fwrite($stream,$bytes)!==strlen($bytes)){throw new RuntimeException('File write failed.');}};
 $read=static function($stream,int $length):string{$bytes='';while(strlen($bytes)<$length&&!feof($stream)){$part=fread($stream,$length-strlen($bytes));if($part===false){throw new RuntimeException('File read failed.');}$bytes.=$part;}return $bytes;};
 if($mode==='encrypt'){
  [$state,$header]=sodium_crypto_secretstream_xchacha20poly1305_init_push($key);$write($output,'NDCBK001'.$header);
  while(true){$plain=$read($input,65536);$final=feof($input);$cipher=sodium_crypto_secretstream_xchacha20poly1305_push($state,$plain,'',$final?SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL:0);$write($output,pack('N',strlen($cipher)).$cipher);if($final){break;}}
 }else{
  if($read($input,8)!=='NDCBK001'){throw new RuntimeException('Unknown backup format.');}
  $header=$read($input,SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
  if(strlen($header)!==SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES){throw new RuntimeException('Truncated backup.');}
  $state=sodium_crypto_secretstream_xchacha20poly1305_init_pull($header,$key);$final=false;
  while(!$final){$size=$read($input,4);if(strlen($size)!==4){throw new RuntimeException('Missing authenticated end of backup.');}$length=unpack('Nlength',$size)['length'];if($length<17||$length>65553){throw new RuntimeException('Invalid backup chunk.');}$cipher=$read($input,$length);if(strlen($cipher)!==$length){throw new RuntimeException('Truncated backup.');}$result=sodium_crypto_secretstream_xchacha20poly1305_pull($state,$cipher);if($result===false){throw new RuntimeException('Authentication failed: wrong key or damaged backup.');}[$plain,$tag]=$result;$write($output,$plain);$final=$tag===SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;}
  if($read($input,1)!==''){throw new RuntimeException('Unexpected data after authenticated end.');}
 }
 fclose($input);$input=null;fclose($output);$output=null;sodium_memzero($key);
 fwrite(STDOUT,"File completed. Verify recovery before deleting any original. No database operation was performed.\n");
}catch(Throwable $error){
 if(is_resource($input)){fclose($input);}if(is_resource($output)){fclose($output);}
 if($written&&$destination!==null){@unlink($destination);}if(is_string($key)){sodium_memzero($key);}
 fwrite(STDERR,$error->getMessage()."\n");exit(1);
}
