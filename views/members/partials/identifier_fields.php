<?php
$editingMembership=!empty($voter['membership_id']);
$identifierValues=$editingMembership?$memberForm:$membership;
?>
<?php if($editingMembership): ?><div class="location-record-meta mb-3"><span>System ID <strong>#<?= e($voter['membership_id']) ?></strong></span><span>Sys No. <strong><?= e($voter['member_number']) ?></strong></span></div><?php endif; ?>
<?php if(\App\VoterService::officialIdsReady()): ?>
<?php if($editingMembership): ?><input type="hidden" name="membership_version" value="<?= e($voter['membership_version']) ?>"><?php endif; ?>
<?= input('official_membership_id','Party ID',$identifierValues['official_membership_id']??'','text',false,'maxlength="64" autocomplete="off" aria-describedby="party-id-help"') ?>
<p id="party-id-help" class="form-text">Optional. Enter the real party ID from the card or party records, including leading zeros. It must be unique. When recorded, this becomes the primary membership ID across profiles, lists and dashboard summaries. The system member number stays unchanged as a secondary reference. Clearing this field restores the system number as the primary identifier.</p>
<?php else: ?><p class="small text-secondary">Import database/08_official_membership_ids_mysql8.sql once in phpMyAdmin to record official party IDs. Membership can still be saved without one.</p><?php endif; ?>
