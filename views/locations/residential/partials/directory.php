<?php
use App\DirectoryUi;
$route=$isDistrict?'districts':'residential_regions';$title=$isDistrict?'Districts':'Residential regions';$singular=$isDistrict?'district':'residential region';
?>
<div class="directory-workspace">
<header class="page-heading"><div><span class="section-kicker">RESIDENTIAL DIRECTORY</span><h1><?= e($title) ?></h1><p><?= $isDistrict?'Manage home-address districts and see the voters, members and towns linked to each one.':'Manage residential regions independently from electoral geography.' ?></p></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-primary" href="<?= e(url($isDistrict?'residential_regions':'districts')) ?>"><?= $isDistrict?'Residential regions':'Manage districts' ?></a><?php if($available): ?><a class="btn btn-primary" href="<?= e(url($route,$filters).'#residential-editor') ?>"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add <?= e($singular) ?></a><?php endif; ?></div></header>
<?php if(!$available): ?><section class="card directory-panel"><div class="card-body"><h2 class="h5">Enable residential locations</h2><p class="mb-0">Apply migrations 09 and 10 in phpMyAdmin to enable the separate residential directory. Existing addresses are preserved.</p></div></section><?php else:
$regionOptions=array_column($regions,'name','id');$record=$editing??[];
$sessionKey=$isDistrict?'district_form':'residential_region_form';$idField=$isDistrict?'district_id':'residential_region_id';
if(isset($_SESSION[$sessionKey])){$saved=$_SESSION[$sessionKey];unset($_SESSION[$sessionKey]);if((int)($saved[$idField]??0)===(int)($record['id']??0)&&(!$editing||($saved['version']??'')===$editing['updated_at'])){$record=array_replace($record,$saved);}}
$record['region_id']??=$filters['region_filter']??'';
$formUrl=url($route,$filters);
$filterChips=DirectoryUi::chips($route,$filters,['q'=>'Search','region_filter'=>'Residential region','coverage'=>'Linked records'],['region_filter'=>$regionOptions,'coverage'=>['with_voters'=>'With voters','without_voters'=>'No voters yet']]);
$directoryMetrics=[
 ['label'=>'Matching '.$title,'count'=>$total,'href'=>$formUrl,'icon'=>$isDistrict?'bi-buildings':'bi-map','note'=>'Across all result pages'],
 ['label'=>'Linked voters','count'=>$summary['voters'],'icon'=>'bi-person-lines-fill','note'=>'Within matching locations'],
 ['label'=>'Linked members','count'=>$summary['members'],'icon'=>'bi-people','note'=>'Included in voter total'],
 ['label'=>'Without linked voters','count'=>$summary['unused'],'href'=>url($route,array_replace($filters,['coverage'=>'without_voters','p'=>1])),'icon'=>'bi-pin-map','note'=>'Ready for future addresses'],
];
require \App\View::path('shared/components/directory/metrics');
?>
<nav class="directory-section-tabs" aria-label="Residential directories"><a class="<?= !$isDistrict?'active':'' ?>" href="<?= e(url('residential_regions')) ?>">Residential regions</a><a class="<?= $isDistrict?'active':'' ?>" href="<?= e(url('districts')) ?>">Districts</a><a href="<?= e(url('residential_towns')) ?>">Saved towns</a></nav>
<div class="directory-info-line"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Residential region → district → town. These locations describe home addresses; electoral constituencies and polling stations are separate.</span></div>
<div class="row g-4 align-items-start"><div class="col-xl-8"><section class="card directory-panel">
<div class="directory-search-area"><form method="get" action="<?= e(base_url('index.php')) ?>" data-directory-searchable><input type="hidden" name="page" value="<?= e($route) ?>">
<div class="directory-search-row"><div class="flex-grow-1"><?= input('q','Search '.$title,$filters['q']??'','search',false,'maxlength="100" placeholder="Name or system ID"') ?></div><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button><a class="btn btn-outline-secondary" href="<?= e(url($route)) ?>">Reset</a></div>
<details class="directory-filter-panel"><summary><span><i class="bi bi-sliders me-2" aria-hidden="true"></i>Filters &amp; display</span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="directory-filter-content"><div class="row g-3">
<?php if($isDistrict): ?><div class="col-md-6"><?= select_field('region_filter','Residential region',[''=>'All regions']+$regionOptions,$filters['region_filter']??'') ?></div><?php endif; ?>
<div class="col-md-6"><?= select_field('coverage','Linked voters',[''=>'All locations','with_voters'=>'With linked voters','without_voters'=>'No linked voters'],$filters['coverage']??'') ?></div>
<div class="col-md-6"><?= select_field('sort','Sort by',['name'=>'Name A–Z','name_desc'=>'Name Z–A','newest'=>'Newest first'],$filters['sort']) ?></div>
<div class="col-md-6"><?= select_field('per_page','Rows per page',['25'=>'25','50'=>'50','100'=>'100'],$perPage) ?></div>
</div><button class="btn btn-primary mt-3" type="submit">Apply filters</button></div></details>
</form><?php require \App\View::path('shared/components/directory/filter_chips'); ?></div>
<div class="directory-result-toolbar"><div><h2 class="h6 mb-1"><?= e($title) ?></h2><span class="small text-secondary">Click a record count to open the matching people.</span></div><span class="directory-count"><?= number($total) ?> records</span></div>
<div class="table-responsive directory-table-scroll"><table class="table table-hover align-middle mb-0"><thead><tr><th scope="col">Location</th><?php if($isDistrict): ?><th scope="col">Residential region</th><?php else: ?><th scope="col">Districts</th><?php endif; ?><th scope="col">Towns</th><th scope="col">Voters / members</th><th scope="col">Updated</th><th scope="col" class="directory-actions-cell">Action</th></tr></thead><tbody>
<?php foreach($rows as $item): $personFilter=[$isDistrict?'residential_district_filter':'residential_region_filter'=>$item['id']]; ?>
<tr class="<?= $editing&&(int)$editing['id']===(int)$item['id']?'directory-row-editing':'' ?>"><th scope="row"><strong><?= e($item['name']) ?></strong><span class="table-detail">System ID #<?= e($item['id']) ?></span></th>
<?php if($isDistrict): ?><td><a href="<?= e(url('residential_regions',['edit'=>$item['region_id']]).'#residential-editor') ?>"><?= e($item['region_name']) ?></a></td><?php else: ?><td><a class="directory-record-count" href="<?= e(url('districts',['region_filter'=>$item['id']])) ?>"><?= number($item['district_count']) ?><span>districts</span></a></td><?php endif; ?>
<td><?php if($item['town_count']!==null): ?><a class="directory-record-count" href="<?= e(url('residential_towns',$personFilter)) ?>"><?= number($item['town_count']) ?><span>towns</span></a><?php else: ?><span class="text-secondary">Not available</span><?php endif; ?></td>
<td><div class="directory-count-links"><a href="<?= e(url('voters',$personFilter)) ?>"><strong><?= number($item['voter_count']) ?></strong> voters</a><a href="<?= e(url('members',$personFilter)) ?>"><strong><?= number($item['member_count']) ?></strong> members</a></div></td>
<td><span class="small text-secondary"><?= e(date_display($item['updated_at'])) ?></span></td>
<td class="directory-actions-cell"><a class="btn btn-sm btn-light" href="<?= e(url($route,array_replace($filters,['edit'=>$item['id']])).'#residential-editor') ?>" aria-label="Edit <?= e($item['name']) ?>"><i class="bi bi-pencil me-1" aria-hidden="true"></i>Edit</a></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="6"><div class="directory-empty"><i class="bi bi-geo-alt" aria-hidden="true"></i><h3>No <?= e(strtolower($title)) ?> found</h3><p>Try a different search or add a <?= e($singular) ?>.</p><a class="btn btn-outline-primary" href="<?= e(url($route)) ?>">Clear filters</a></div></td></tr><?php endif; ?>
</tbody></table></div>
<?php $pagination=['route'=>$route,'filters'=>$filters,'total'=>$total,'page'=>$listingPage,'per_page'=>$perPage,'last'=>$last];require \App\View::path('shared/components/directory/pagination'); ?>
</section></div>
<div class="col-xl-4"><section class="card directory-panel directory-editor" id="residential-editor"><div class="card-header"><div><span class="member-section-kicker"><?= $editing?'UPDATE RECORD':'NEW LOCATION' ?></span><h2 class="card-title"><?= $editing?'Edit':'Add' ?> <?= e($singular) ?></h2></div><?php if($editing): ?><a href="<?= e($formUrl) ?>">Cancel edit</a><?php endif; ?></div><div class="card-body">
<?php if($editing): ?><div class="directory-editor-meta"><span>System ID #<?= e($editing['id']) ?></span><span>Updated <?= e(date_display($editing['updated_at'],true)) ?> UTC</span></div><?php else: ?><p class="small text-secondary">A permanent system ID will be assigned when saved.</p><?php endif; ?>
<form method="post" action="<?= e($formUrl) ?>" data-directory-searchable><?= csrf() ?><input type="hidden" name="_action" value="<?= $isDistrict?'district_save':'residential_region_save' ?>">
<?php if($editing): ?><input type="hidden" name="<?= e($idField) ?>" value="<?= e($editing['id']) ?>"><input type="hidden" name="version" value="<?= e($editing['updated_at']) ?>"><?php endif; ?>
<?php if($isDistrict): ?><?php if($editing): ?><input type="hidden" name="region_id" value="<?= e($editing['region_id']) ?>"><label class="form-label">Residential region</label><p class="location-parent"><?= e($regionOptions[$editing['region_id']]??'Not recorded') ?></p><?php else: ?><?= select_field('region_id','Residential region',[''=>'Choose a region']+$regionOptions,$record['region_id'],true) ?><?php endif; ?><?php endif; ?>
<?= input('name',ucfirst($singular).' name',$record['name']??'','text',true,'maxlength="'.($isDistrict?180:160).'" autocomplete="off"') ?>
<button class="btn btn-primary w-100" type="submit"><?= $editing?'Save changes':'Create '.$singular ?></button>
</form><p class="small text-secondary mt-3 mb-0"><?= $isDistrict?'Names are unique within each residential region. The parent region stays fixed when editing.':'Residential region names are unique. Renaming updates the label wherever it is used for home addresses.' ?></p>
<?php if($editing): ?><hr><h3 class="h6">Recent activity</h3><?php if($history): ?><ul class="location-history"><?php foreach($history as $event): ?><li><strong><?= str_ends_with($event['action_code'],'.created')?'Location created':'Location updated' ?></strong><span><?= e($event['actor']??'System') ?> · <?= e(date_display($event['occurred_at'],true)) ?> UTC</span></li><?php endforeach; ?></ul><?php else: ?><p class="small text-secondary mb-0">No recorded changes yet.</p><?php endif; ?><?php endif; ?>
</div></section></div></div>
<?php endif; ?></div>
