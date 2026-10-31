<?php
use App\Auth;
use App\VoterService;
$values=VoterService::values($voter);
$backUrl=$voter?url('voter',['id'=>$voter['id']]):url('voters');
if(!empty($_SESSION['voter_form'])){
 $saved=json_decode(\App\Crypto::decrypt($_SESSION['voter_form']),true);unset($_SESSION['voter_form']);
 if(is_array($saved)&&(int)($saved['id']??0)===(int)($voter['id']??0)&&(!$voter||($saved['voter_version']??null)===$voter['updated_at'])){$values=array_replace($values,$saved);}
}
?>
<div class="voter-editor" data-voter-editor>
<header class="voter-editor-heading"><div><a class="back-link" href="<?= e($backUrl) ?>"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i><?= $voter?'Back to voter profile':'Back to voters' ?></a><span class="voter-editor-kicker"><?= $voter?'UPDATE RECORD':'NEW VOTER' ?></span><h1><?= $voter?'Edit voter':'Add voter' ?></h1><p><?= $voter?e($voter['full_name']):'Create a voter record with the details you have available.' ?></p></div><div class="d-flex gap-2"><a class="btn btn-light" href="<?= e($backUrl) ?>">Cancel</a><button class="btn btn-primary" type="submit" form="voter-editor-form"><i class="bi bi-check2 me-2" aria-hidden="true"></i><?= $voter?'Save changes':'Save voter' ?></button></div></header>
<?php if(!VoterService::namesReady()): ?><div class="alert alert-info">Import <strong>database/07_voter_names_mysql8.sql</strong> once in phpMyAdmin to save first and last names. Existing full names are preserved.</div><?php endif; ?>
<div class="voter-editor-layout">
<aside class="voter-editor-sidebar" aria-label="Editor navigation">
<div class="voter-editor-context"><span><?= $voter?'EDITING VOTER':'GETTING STARTED' ?></span><strong><?= e($voter['voter_id']??'New voter record') ?></strong><p>Voter ID, first name, last name and polling station are required. Contact and residential details are optional.</p></div>
<nav class="voter-editor-nav" aria-label="Voter editing sections">
<?php foreach(['personal'=>['01','Personal details','Name, identity and photo'],'contact'=>['02','Contact details','Phone and email'],'residence'=>['03','Residential address','Home, town and district'],'electoral'=>['04','Polling location','Station and derived branch']] as $section=>[$step,$label,$hint]): ?>
<a href="#voter-<?= e($section) ?>"><span><?= e($step) ?></span><div><strong><?= e($label) ?></strong><small><?= e($hint) ?></small></div><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
<?php endforeach; ?>
</nav>
<div class="voter-editor-help"><i class="bi bi-info-circle" aria-hidden="true"></i><p>Save the voter first. You can review membership enrollment from the voter profile afterwards.</p></div>
</aside>
<form id="voter-editor-form" method="post" enctype="multipart/form-data" action="<?= e(url('voter',$voter?['id'=>$voter['id'],'edit'=>1]:[])) ?>" class="voter-record-form voter-editor-main">
<?= csrf() ?><input type="hidden" name="_action" value="voter_save"><?php if($voter): ?><input type="hidden" name="id" value="<?= e($voter['id']) ?>"><input type="hidden" name="voter_version" value="<?= e($voter['updated_at']) ?>"><?php endif; ?>
<fieldset <?= !Auth::canManage()?'disabled':'' ?>><legend class="visually-hidden">Voter details</legend>
<?php require \App\View::path('shared/forms/person'); ?>
<div class="voter-editor-save"><div><strong data-voter-editor-status role="status" aria-live="polite">Ready to edit</strong><p>Changes apply when you save this record.</p></div><div class="d-flex gap-2"><a class="btn btn-light" href="<?= e($backUrl) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $voter?'Save voter changes':'Save voter' ?></button></div></div>
</fieldset></form></div></div>