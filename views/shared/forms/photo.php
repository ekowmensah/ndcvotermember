<?php $photoReady=\App\PhotoService::ready();$hasPhoto=!empty($voter['has_photo']); ?>
<div class="col-12"><section class="voter-photo-panel mb-4" data-photo-editor>
<div class="voter-photo-frame"><img class="voter-photo" data-photo-preview <?php if($hasPhoto): ?>src="<?= e(url('voter_photo',['id'=>$voter['id']])) ?>"<?php else: ?>hidden<?php endif; ?> alt="Profile photo"><span class="voter-photo-placeholder" data-photo-placeholder <?= $hasPhoto?'hidden':'' ?>><?= e(mb_strtoupper(mb_substr($voter['full_name']??'?',0,1))) ?></span></div>
<div class="flex-grow-1"><h2 class="h5">Profile photo <span class="small text-secondary fw-normal">Optional</span></h2>
<?php if(\App\Auth::canManage()): ?>
<?php if($photoReady): ?><label class="form-label" for="voter-photo-file"><?= $hasPhoto?'Replace photo':'Add photo' ?></label><input id="voter-photo-file" class="form-control" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input aria-describedby="voter-photo-help"><p id="voter-photo-help" class="form-text">JPEG, PNG or WebP, up to 2 MB and 12 megapixels. Photos are resized for profiles. Save the record to apply changes.</p>
<?php if($hasPhoto): ?><label class="form-check"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" data-photo-remove><span class="form-check-label">Remove current photo when saving</span></label><?php endif; ?>
<p class="small text-secondary mb-0" data-photo-status role="status" aria-live="polite"></p>
<?php else: ?><p class="small text-secondary mb-0">To enable optional photos, import <strong>database/06_voter_photos_mysql8.sql</strong> once in phpMyAdmin. You can save the record without a photo.</p><?php endif; ?>
<?php else: ?><p class="small text-secondary mb-0"><?= $hasPhoto?'Photo on file.':'No photo added.' ?></p><?php endif; ?></div></section></div>
