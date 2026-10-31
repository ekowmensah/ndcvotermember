<?php
use App\LocationService;
$titles=['region'=>'Regions','constituency'=>'Constituencies','electoral_area'=>'Electoral areas (branches)','polling_station'=>'Polling stations'];
$singular=['region'=>'region','constituency'=>'constituency','electoral_area'=>'branch','polling_station'=>'polling station'];
?>
<div class="directory-workspace"><div class="page-heading"><div><span class="section-kicker">LOCATION DIRECTORY</span><h1>Electoral locations</h1><p>Organize your regions, constituencies, branches and polling stations.</p></div><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="<?= e(url('station_imports')) ?>"><i class="bi bi-upload me-2" aria-hidden="true"></i>Upload Polling Stations</a><a class="btn btn-outline-secondary" href="<?= e(url('voters')) ?>"><i class="bi bi-people me-2" aria-hidden="true"></i>View voters</a></div></div>
<?php if(!$catalog || !LocationService::enhanced()): ?>
<section class="card"><div class="card-body"><h2 class="h5">Update the location directory</h2><p>In phpMyAdmin, select your application database and import <strong><?= !$catalog?'database/02_locations_mysql8.sql, then ':'' ?>database/03_location_shortnames_mysql8.sql</strong> once. Existing records and IDs are preserved. Refresh this page afterwards.</p><p class="mb-0 text-secondary">Regions and constituencies use shortnames. Branches use their names; polling stations use names and official polling station IDs. Apply migrations 04 and 05 after 03.</p></div></section>
<?php else:
$regions=[];$constituencies=[];$branches=[];
foreach($catalog['regions'] as $item){$regions[$item['id']]=$item['shortname'].' · '.$item['name'];}
foreach($catalog['constituencies'] as $item){$constituencies[$item['id']]=$item['shortname'].' · '.$item['name'].' / '.($regions[$item['region_id']]??'');}
foreach($catalog['electoral_areas'] as $item){$branches[$item['id']]=$item;}
$record=$editing??[];
if(!empty($_SESSION['location_form'])){$saved=$_SESSION['location_form'];unset($_SESSION['location_form']);if(($saved['kind']??'')===$kind&&(int)($saved['location_id']??0)===(int)($record['id']??0)){$record=array_replace($record,$saved);}}
$parentOptions=$kind==='constituency'?$regions:$constituencies;
$parentKey=$kind==='constituency'?'region_id':'constituency_id';
$parentValue=$record['parent_id']??$record[$parentKey]??($kind==='constituency'?($filters['region_filter']??''):($filters['constituency_filter']??''));
$formUrl=url('locations',$filters);
$stationCodesReady=LocationService::stationCodesReady();
$sharedStationNamesReady=LocationService::sharedStationNamesReady();
$hasShortname=LocationService::usesShortname($kind);
$needsShortnameUpdate=!$hasShortname&&!LocationService::withoutShortnamesReady();
?>
<?php if($kind==='polling_station'&&$stationCodesReady&&!$sharedStationNamesReady): ?><div class="alert alert-info">Import <strong>database/22_polling_station_shared_names_mysql8.sql</strong> in phpMyAdmin to allow stations to share a name. Official Station IDs remain unique; existing stations, branches and voter links are preserved.</div><?php endif; ?>
<?php if($kind==='polling_station'&&!$stationCodesReady): ?><div class="alert alert-info">Import <strong>database/04_polling_station_codes_mysql8.sql</strong> once in phpMyAdmin to add official polling station IDs. Existing records and branch assignments are preserved.</div><?php endif; ?><?php if($needsShortnameUpdate): ?><div class="alert alert-info">Import <strong>database/05_branch_station_without_shortnames_mysql8.sql</strong> once in phpMyAdmin to add branches and polling stations without initials. Existing records and links are preserved.</div><?php endif; ?><p class="small text-secondary mb-2">Complete electoral directory · counts below include all locations</p><div class="location-metrics mb-4">
<?php foreach($titles as $type=>$title): ?><a class="location-metric <?= $kind===$type?'is-current':'' ?>" href="<?= e(url('locations',['type'=>$type])) ?>"><span><?= e($title) ?></span><strong><?= number($counts[$type]) ?></strong><small><?= $type==='region'?'Top-level directory':($type==='constituency'?'Linked to regions':($type==='electoral_area'?'Linked to constituencies':'Branch assignment optional')) ?></small></a><?php endforeach; ?>
</div>
<div class="location-notice mb-4"><i class="bi bi-diagram-3" aria-hidden="true"></i><p class="mb-0"><strong>A station starts with its constituency.</strong> Add its branch whenever you are ready. Linked voters and members pick up that assignment automatically.</p><a href="<?= e(url('locations',['type'=>'polling_station','assignment'=>'unassigned'])) ?>" class="btn btn-sm btn-outline-primary"><?= number($counts['unassigned']) ?> without branch</a></div>
<?php if($stationCodesReady&&$counts['missing_codes']>0): ?><div class="directory-info-line"><i class="bi bi-card-heading" aria-hidden="true"></i><a href="<?= e(url('locations',['type'=>'polling_station','code_status'=>'missing'])) ?>"><?= number($counts['missing_codes']) ?> polling stations still need their official polling station ID.</a></div><?php endif; ?><nav class="location-tabs mb-4" aria-label="Location levels"><?php foreach($titles as $type=>$title): ?><a class="<?= $kind===$type?'active':'' ?>" <?= $kind===$type?'aria-current="page"':'' ?> href="<?= e(url('locations',['type'=>$type])) ?>"><?= e($title) ?><span><?= number($counts[$type]) ?></span></a><?php endforeach; ?></nav>
<div class="row g-4 align-items-start"><div class="col-xl-8">
<section class="card location-directory directory-panel"><div class="card-header"><div><h2 class="card-title"><?= e($titles[$kind]) ?></h2><p class="small text-secondary mb-0"><?= number($total) ?> matching records · IDs remain stable when names change</p></div><a class="btn btn-sm btn-primary" href="<?= e(url('locations',$filters).'#location-editor') ?>">Add <?= e($singular[$kind]) ?></a></div>
<?php require \App\View::path('locations/electoral/partials/filters'); ?>
<?php require \App\View::path('locations/electoral/partials/table'); ?>
<?php $pagination=['route'=>'locations','filters'=>$filters,'total'=>$total,'page'=>$page,'per_page'=>$perPage,'last'=>$last];require \App\View::path('shared/components/directory/pagination'); ?></section></div>
<?php require \App\View::path('locations/electoral/partials/editor'); ?></div>
<?php endif; ?>
</div>
