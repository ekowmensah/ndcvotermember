<?php
declare(strict_types=1);
namespace App;
final class PhotoService
{
 public static function ready(): bool {
  static $ready=null;
  return $ready??=(bool)Database::scalar("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='voter_photos'");
 }
 public static function prepare(?array $file): ?string {
  if($file===null||($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE){return null;}
  if(!self::ready()){throw new \InvalidArgumentException('Import database/06_voter_photos_mysql8.sql in phpMyAdmin to enable voter photos.');}
  $error=$file['error']??UPLOAD_ERR_NO_FILE;
  if(in_array($error,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true)){throw new \InvalidArgumentException('Choose a voter photo no larger than 2 MB.');}
  if($error!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])){throw new \InvalidArgumentException('The photo upload did not complete. Select the file again.');}
  $path=$file['tmp_name'];$size=filesize($path);
  if($size===false||$size<1||$size>2*1024*1024){throw new \InvalidArgumentException('Choose a voter photo no larger than 2 MB.');}
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);$info=@getimagesize($path);
  if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)||!$info||($info['mime']??'')!==$mime){throw new \InvalidArgumentException('Choose a valid JPEG, PNG or WebP photo.');}
  if($info[0]<1||$info[1]<1||$info[0]>6000||$info[1]>6000||$info[0]*$info[1]>12000000){throw new \InvalidArgumentException('Photo dimensions must be at most 6000 pixels per side and 12 megapixels.');}
  if(!function_exists('imagecreatefromstring')){throw new \InvalidArgumentException('Enable the PHP GD extension in Laragon to upload photos.');}
  $image=@imagecreatefromstring(file_get_contents($path));
  if(!$image){throw new \InvalidArgumentException('This image could not be read. Choose another photo.');}
  $target=null;
  try {
   if($mime==='image/jpeg'&&function_exists('exif_read_data')){
    $exif=@exif_read_data($path);$orientation=(int)($exif['Orientation']??1);
    if(in_array($orientation,[2,4,5,7],true)){imageflip($image,IMG_FLIP_HORIZONTAL);}
    $angle=match($orientation){3,4=>180,6,7=>-90,5,8=>90,default=>0};
    if($angle){$rotated=imagerotate($image,$angle,0);if(!$rotated){throw new \InvalidArgumentException('This photo could not be rotated. Choose another photo.');}imagedestroy($image);$image=$rotated;}
   }
   $width=imagesx($image);$height=imagesy($image);$scale=min(1,1024/max($width,$height));
   $outWidth=max(1,(int)round($width*$scale));$outHeight=max(1,(int)round($height*$scale));
   $target=imagecreatetruecolor($outWidth,$outHeight);
   imagefill($target,0,0,imagecolorallocate($target,255,255,255));
   imagecopyresampled($target,$image,0,0,0,0,$outWidth,$outHeight,$width,$height);
   // Re-encode pixels only: discard EXIF/GPS metadata and the original file contents.
   ob_start();try{$success=imagejpeg($target,null,85);$bytes=ob_get_contents();}finally{ob_end_clean();}
   if(!$success||!is_string($bytes)||$bytes===''){throw new \InvalidArgumentException('The photo could not be processed. Choose another photo.');}
   return $bytes;
  }finally{imagedestroy($image);if($target){imagedestroy($target);}}
 }
 // Called inside the voter-save transaction so failed edits never change the photo.
 public static function save(int $voter,?string $bytes,bool $remove): void {
  if($bytes===null&&!$remove){return;}
  if(!self::ready()){throw new \InvalidArgumentException('Import database/06_voter_photos_mysql8.sql in phpMyAdmin first.');}
  if($remove){
   if(Database::execute('DELETE FROM voter_photos WHERE voter_record_id=?',[$voter])){Auth::audit('voter.photo_removed','voter',$voter);}
   return;
  }
  Database::execute('INSERT INTO voter_photos(voter_record_id,image_data,uploaded_by_user_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE image_data=?,uploaded_by_user_id=?,updated_at=CURRENT_TIMESTAMP(6)',[$voter,$bytes,Auth::user()['id'],$bytes,Auth::user()['id']]);
  Auth::audit('voter.photo_saved','voter',$voter);
 }
 public static function output(int $voter): never {
  Auth::requireLogin();
  $owner=Database::one('SELECT * FROM voters WHERE id=?',[$voter]);if(!$owner||(!empty($owner['deleted_at'])&&!Auth::admin())){throw new HttpException(404,'Photo unavailable.');}
  $photo=self::ready()?Database::one('SELECT image_data FROM voter_photos WHERE voter_record_id=?',[$voter]):null;
  if(!$photo){throw new HttpException(404,'No photo has been added for this voter.');}
  header('Content-Type: image/jpeg');header('Content-Disposition: inline; filename="voter-photo.jpg"');
  header('Content-Length: '.strlen($photo['image_data']));
  echo $photo['image_data'];exit;
 }
}
