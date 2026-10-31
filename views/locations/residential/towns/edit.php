<?php
$regionOptions=array_column($catalog['regions'],'name','id');$districtOptions=[];
foreach($catalog['districts'] as $item){if(empty($values['residential_region_filter'])||(int)$item['region_id']===(int)$values['residential_region_filter']){$districtOptions[$item['id']]=$item['name'].' / '.$item['region_name'];}}
?>
<div class="page-heading"><div><h1>Edit town location</h1><p><?= e($town['name']) ?> · Town #<?= e($town['id']) ?></p></div><a class="btn btn-light" href="<?= e(url('residential_towns')) ?>">Back to towns</a></div>
<section class="card"><div class="card-header"><h2 class="card-title">Town details</h2></div><div class="card-body">
<div class="alert alert-info">Changes apply to all <?= number($counts['voters']) ?> linked people, including <?= number($counts['members']) ?> members. Their town name, residential region and district update together.</div>
<form method="post" action="<?= e(url('residential_town_edit',['id'=>$town['id']])) ?>" data-directory-searchable data-residential-filters="<?= e(json_encode($catalog['districts'],JSON_THROW_ON_ERROR)) ?>">
<?= csrf() ?><input type="hidden" name="_action" value="town_save"><input type="hidden" name="id" value="<?= e($town['id']) ?>"><input type="hidden" name="version" value="<?= e($version) ?>">
<?= input('name','Town name',$values['name']??$town['name'],'text',true,'maxlength="180"') ?>
<div class="row"><div class="col-md-6"><?= select_field('residential_region_filter','Residential region',[''=>'Not assigned']+$regionOptions,$values['residential_region_filter']??'') ?></div><div class="col-md-6"><?= select_field('residential_district_filter','Residential district',[''=>'Not assigned']+$districtOptions,$values['residential_district_filter']??'') ?></div></div>
<p class="text-secondary">Choose the region first, then its district. You can assign a region now and add the district later. A district requires its matching residential region.</p>
<p class="text-secondary">If a matching town exists in the selected region and district, you will be asked to confirm a merge before anything is saved. Towns in different locations remain separate.</p>
<?php if($merge): ?>
<section class="alert alert-warning" role="alert" aria-labelledby="merge-heading">
<h3 class="h5" id="merge-heading">Merge these towns?</h3>
<p>Merge <strong><?= e($town['name']) ?> (#<?= e($town['id']) ?>)</strong> into <strong><?= e($merge['target']['name']) ?> (#<?= e($merge['target']['id']) ?>)</strong>.</p>
<p>Destination: <?= e($regionOptions[$merge['target']['residential_region_id']]??'Region not assigned') ?> / <?= e(array_column($catalog['districts'],'name','id')[$merge['target']['district_id']]??'District not assigned') ?>.</p>
<p>All <?= number($merge['people']) ?> linked people, including any members, will move to the existing town. Its name and ID will be kept, and this duplicate town will be removed. Personal and membership records will be preserved.</p>
<button class="btn btn-warning" type="submit" name="merge_token" value="<?= e($merge['token']) ?>">Confirm merge</button> <a class="btn btn-light" href="<?= e(url('residential_towns')) ?>">Cancel merge</a>
</section>
<?php endif; ?>
<button class="btn btn-primary" type="submit">Save town</button> <a class="btn btn-light" href="<?= e(url('residential_towns')) ?>">Cancel</a>
</form></div></section>
