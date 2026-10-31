<section class="card member-panel member-editor mb-4" id="member-edit">
<div class="card-body">
<p class="text-secondary small">Update personal details, photo, contacts, residence, polling location and membership here. One save updates the member and their shared voter record together.</p>
<?php if($recovered): ?><div class="alert alert-info">Your unsaved entries have been restored. Review the message above, make corrections and save again.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" class="voter-record-form" id="member-update-form" action="<?= e(url('member_edit',['id'=>$voter['membership_id']])) ?>" data-member-form>
<?= csrf() ?><input type="hidden" name="_action" value="member_update"><input type="hidden" name="membership_id" value="<?= e($voter['membership_id']) ?>"><input type="hidden" name="voter_record_id" value="<?= e($voter['id']) ?>"><input type="hidden" name="voter_version" value="<?= e($voter['updated_at']) ?>"><input type="hidden" name="member_person_present" value="1">
<nav class="voter-form-sections mb-4" aria-label="Edit member sections"><a href="#voter-personal">Personal &amp; photo</a><a href="#voter-contact">Contact</a><a href="#voter-residence">Residence</a><a href="#voter-electoral">Electoral location</a><a href="#member-edit-membership">Membership</a><a href="#member-edit-background">Background</a></nav>
<?php $storedPersonValues=$values;$values=$personForm;require \App\View::path('shared/forms/person');$values=$storedPersonValues; ?><fieldset id="member-edit-membership"><legend class="h6">Membership information</legend>
<?php require \App\View::path('members/partials/identifier_fields'); ?>
<div class="row"><div class="col-md-6"><?= input('joined_on','Date joined',$memberForm['joined_on'],'date',true,'max="'.date('Y-m-d').'"') ?></div><div class="col-md-6"><?= select_field('status','Membership status',['active'=>'Active','inactive'=>'Inactive'],$memberForm['status'],true) ?></div></div>
</fieldset>
<div id="member-edit-background"><?php require \App\View::path('members/partials/background_fields'); ?></div>
<div class="member-editor-actions"><div><strong>Save member changes</strong><span class="small text-secondary" data-member-save-status role="status" aria-live="polite">Personal and membership changes are applied together when you save.</span></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-secondary" href="<?= e(url('member',['id'=>$voter['membership_id']])) ?>">Cancel</a><button class="btn btn-primary" type="submit"><i class="bi bi-check2 me-1" aria-hidden="true"></i>Save member changes</button></div></div>
</form>
</div></section>
