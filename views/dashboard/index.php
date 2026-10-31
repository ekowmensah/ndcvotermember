<?php
use App\Auth;
use App\MemberProfileService;
$percent=static fn(int $part,int $whole): float=>$whole>0?round(100*$part/$whole,1):0;
$rate=static fn(int $part,int $whole): string=>$whole>0?number_format(100*$part/$whole,1).'%':'—';
$link=static fn(string $page,array $extra=[]): string=>url($page,array_merge($filters,$extra));
$latest=end($trend);reset($trend);
$chartMax=max(1,...array_column($trend,'voters'),...array_column($trend,'members'));
$coverage=[
 ['Phone number',$stats['phone'],$stats['voters'],$link('voters',['missing'=>'phone'])],
 ['Date of birth',$stats['dob'],$stats['voters'],$link('voters',['missing'=>'dob'])],
];
if($photoReady){$coverage[]=['Voter photo',$stats['photos'],$stats['voters'],$link('voters',['photo'=>'without'])];}
if($addressReady){$coverage[]=['Residential district',$stats['district_recorded'],$stats['voters'],$link('voters',['missing'=>'district'])];}
if($townReady){$coverage[]=['Saved residential town',$stats['town_recorded'],$stats['voters'],$link('voters',['missing'=>'town'])];}
if($locationReady){
 $coverage[]=['Polling station',$stats['station_recorded'],$stats['voters'],$link('voters',['missing'=>'station'])];
 $coverage[]=['Derived branch',$stats['branch_recorded'],$stats['voters'],$link('voters',['missing'=>'branch'])];
}
?>
<div class="dashboard-workspace">
<header class="page-heading"><div><span class="section-kicker">VOTERS &amp; MEMBERSHIP</span><h1>Overview</h1><p>Understand your records, residential reach and membership coverage.</p></div><div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-secondary no-print" type="button" data-dashboard-print><i class="bi bi-printer me-2" aria-hidden="true"></i>Print overview</button><?php if(Auth::canManage()): ?><a class="btn btn-outline-primary" href="<?= e(url('imports')) ?>"><i class="bi bi-upload me-2" aria-hidden="true"></i>Upload voters</a><a class="btn btn-primary" href="<?= e(url('voter')) ?>"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Add voter</a><?php else: ?><a class="btn btn-primary" href="<?= e($link('voters')) ?>">Browse voters</a><?php endif; ?></div></header>

<section class="dashboard-scope mb-4" aria-label="Dashboard filters">
<form method="get" action="<?= e(base_url('index.php')) ?>" class="row g-3 align-items-end" data-dashboard-filters data-districts="<?= e(json_encode($districts,JSON_THROW_ON_ERROR)) ?>">
<input type="hidden" name="page" value="dashboard">
<div class="col-lg-3"><div class="dashboard-scope-label"><i class="bi bi-funnel" aria-hidden="true"></i> Residential scope</div><div class="small text-secondary">All statistics below follow this selection.</div></div>
<div class="col-md-5 col-lg-3"><label class="form-label" for="dashboard-region">Residential region</label><select id="dashboard-region" class="form-select" name="residential_region_filter" <?= !$addressReady?'disabled':'' ?>><option value="">All residential regions</option><?php foreach($regions as $item): ?><option value="<?= e($item['id']) ?>" <?= (string)($filters['residential_region_filter']??'')===(string)$item['id']?'selected':'' ?>><?= e($item['name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-5 col-lg-3"><label class="form-label" for="dashboard-district">Residential district</label><select id="dashboard-district" class="form-select" name="residential_district_filter" <?= !$addressReady?'disabled':'' ?>><option value="">All residential districts</option><?php foreach($districts as $item): if(isset($filters['residential_region_filter'])&&(int)$item['region_id']!==$filters['residential_region_filter']){continue;} ?><option value="<?= e($item['id']) ?>" <?= (string)($filters['residential_district_filter']??'')===(string)$item['id']?'selected':'' ?>><?= e($item['name'].' / '.$item['region_name']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-2 col-lg-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Apply</button><?php if($filters): ?><a class="btn btn-outline-secondary" href="<?= e(url('dashboard')) ?>">Reset</a><?php endif; ?></div>
</form>
<div class="dashboard-scope-meta"><span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($scopeName) ?></span><span>Snapshot <?= e(gmdate('d M Y, H:i')) ?> UTC · <a href="<?= e(url('dashboard',$filters)) ?>">Refresh</a></span></div>
</section>

<?php if(\App\People\LifeStatusService::ready()): ?><p class="small text-secondary">Totals include people marked deceased; deleted voters are excluded. Active/inactive refers to membership status, separately from life status.</p><?php endif; ?>
<div class="dashboard-kpis mb-4">
<?php
$cards=[
 ['Total voters',$stats['voters'],'People in the voter database','voters',[],'bi-person-lines-fill'],
 ['Total members',$stats['members'],$rate($stats['members'],$stats['voters']).' of voters are members','members',[],'bi-people'],
 ['Active members',$stats['active'],$stats['inactive'].' inactive members','members',['status'=>'active'],'bi-person-check'],
 ['Voters without membership',$stats['nonmembers'],'Available for membership review','voters',['membership'=>'not_member'],'bi-person-plus'],
 ['Voters added this month',$latest['voters'],'Records created in '.gmdate('F Y'),'voters',['sort'=>'newest','created_month'=>gmdate('Y-m')],'bi-person-vcard'],
 ['Memberships saved this month',$latest['members'],'Current memberships created in '.gmdate('F Y'),'members',['sort'=>'newest','created_month'=>gmdate('Y-m')],'bi-calendar-check'],
];
if(\App\People\LifeStatusService::ready()){
 $cards[]=['Deceased voters',$stats['deceased_voters'],'Includes deceased members; deleted records excluded','voters',['life_status'=>'deceased'],'bi-person-dash'];
 $cards[]=['Deceased members',$stats['deceased_members'],'Membership details retained','members',['life_status'=>'deceased'],'bi-person-dash'];
 $cards[]=['Voters not marked deceased',$stats['not_deceased_voters'],'Current records without a deceased flag','voters',['life_status'=>'not_deceased'],'bi-person-check'];
 $cards[]=['Members not marked deceased',$stats['not_deceased_members'],'Current members without a deceased flag','members',['life_status'=>'not_deceased'],'bi-person-check'];
}
foreach($cards as [$title,$value,$caption,$page,$extra,$icon]): ?>
<a class="dashboard-kpi" href="<?= e($link($page,$extra)) ?>"><div><span><?= e($title) ?></span><i class="bi <?= e($icon) ?>" aria-hidden="true"></i></div><strong><?= number($value) ?></strong><small><?= e($caption) ?></small><span class="dashboard-kpi-link">View records <i class="bi bi-arrow-up-right" aria-hidden="true"></i></span></a>
<?php endforeach; ?>
</div>
<?php require \App\View::path('dashboard/partials/executives'); ?>
<?php require \App\View::path('dashboard/partials/wings'); ?>
<p class="small text-secondary mb-4">Members are included in the voter total. Monthly cards open records created in the current month.</p>
<?php if(!$stats['voters']): ?><div class="dashboard-empty mb-4"><i class="bi bi-database" aria-hidden="true"></i><div><h2 class="h5"><?= $filters?'No voters in this residential scope':'Start building your voter database' ?></h2><p class="mb-0"><?= $filters?'Try a different region or district, or reset the filters.':'Add a voter or upload your own CSV to populate the dashboard.' ?></p></div></div><?php endif; ?>

<div class="row g-4 mb-4">
<div class="col-xl-8"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Registration activity</h2><p class="card-subtitle">Six calendar months · <?= e($scopeName) ?></p></div><div class="dashboard-chart-key"><span><i class="dashboard-dot" aria-hidden="true"></i>Voters</span><span><i class="dashboard-dot is-member" aria-hidden="true"></i>Members</span></div></div><div class="card-body">
<div class="dashboard-chart" role="img" aria-label="Voter records and current membership records created each month. Exact values follow in the activity table.">
<?php foreach($trend as $month): ?><div class="dashboard-chart-month"><div class="dashboard-chart-values"><span><?= number($month['voters']) ?></span><span><?= number($month['members']) ?></span></div><div class="dashboard-chart-track"><span class="dashboard-chart-bar" style="height:<?= e($percent($month['voters'],$chartMax)) ?>%"></span><span class="dashboard-chart-bar is-member" style="height:<?= e($percent($month['members'],$chartMax)) ?>%"></span></div><span class="dashboard-chart-label"><?= e($month['label']) ?></span></div><?php endforeach; ?>
</div>
<details class="mt-3 small"><summary>View activity figures</summary><div class="table-responsive"><table class="table table-sm"><caption class="visually-hidden">Monthly records created, not historical membership totals</caption><thead><tr><th scope="col">Month</th><th scope="col">Voters</th><th scope="col">Members</th></tr></thead><tbody><?php foreach($trend as $monthKey=>$month): ?><tr><th scope="row"><?= e($month['full_label']) ?></th><td><a href="<?= e($link('voters',['created_month'=>$monthKey,'sort'=>'newest'])) ?>"><?= number($month['voters']) ?></a></td><td><a href="<?= e($link('members',['created_month'=>$monthKey,'sort'=>'newest'])) ?>"><?= number($month['members']) ?></a></td></tr><?php endforeach; ?></tbody></table></div></details>
<p class="small text-secondary mb-0 mt-3">Members use the date saved in the system, rather than a backdated joining date. Removed memberships are excluded; these figures are not a historical audit.</p>
</div></section></div>
<div class="col-xl-4"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Membership coverage</h2><p class="card-subtitle">Of voters in this scope</p></div></div><div class="card-body">
<div class="dashboard-conversion"><strong><?= e($rate($stats['members'],$stats['voters'])) ?></strong><span>have a membership record</span></div>
<div class="dashboard-meter mb-3" role="progressbar" aria-label="Voters with membership" aria-valuenow="<?= e($percent($stats['members'],$stats['voters'])) ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= e($percent($stats['members'],$stats['voters'])) ?>%"></span></div>
<?php foreach([['Active',$stats['active'],'active'],['Inactive',$stats['inactive'],'inactive']] as [$text,$count,$status]): ?><a class="dashboard-summary-row" href="<?= e($link('members',['status'=>$status])) ?>"><span><?= e($text) ?></span><strong><?= number($count) ?></strong></a><?php endforeach; ?>
<?php if($officialReady): ?><a class="dashboard-summary-row" href="<?= e($link('members',['official_id'=>'missing'])) ?>"><span>Missing official party ID</span><strong><?= number($stats['members']-$stats['official_ids']) ?></strong></a><?php endif; ?>
<?php if($profileReady): ?><a class="dashboard-summary-row" href="<?= e($link('members',['profile_status'=>'without'])) ?>"><span>No member background recorded</span><strong><?= number($stats['members']-$stats['profiles']) ?></strong></a><?php endif; ?>
<p class="small text-secondary mt-3 mb-0">Missing IDs and background details are optional information to follow up.</p>
</div></section></div>
</div>

<section class="card dashboard-panel mb-4"><div class="card-header"><div><h2 class="card-title">Residential intelligence</h2><p class="card-subtitle">Where your voters and members live</p></div><?php if(Auth::canManage()): ?><a href="<?= e(url('residential_towns')) ?>">Manage towns</a><?php endif; ?></div><div class="card-body">
<?php if(!$addressReady): ?><p class="text-secondary mb-0">Residential statistics become available after residential migrations 09 and 10.</p><?php else: ?>
<div class="dashboard-residential-stats">
<?php foreach([['Regions represented',$stats['residential_regions']],['Districts represented',$stats['residential_districts']],['Towns represented',$townReady?$stats['towns']:null],['Members with a saved town',$townReady?$stats['member_town_recorded']:null]] as [$text,$count]): ?><div><strong><?= $count===null?'—':number($count) ?></strong><span><?= e($text) ?></span></div><?php endforeach; ?>
</div>
<?php if(!$townReady): ?><p class="small text-secondary mt-3 mb-0">Saved-town statistics require migration 12.</p><?php elseif(empty($filters['residential_district_filter'])): ?><div class="dashboard-town-prompt"><i class="bi bi-cursor" aria-hidden="true"></i><span>Select a residential district above and apply it to see clickable voter and member town tags.</span></div><?php else: ?>
<div class="row g-4 mt-1"><?php foreach($townTags as $kind=>$tags): ?><div class="col-lg-6"><h3 class="h6"><?= $kind==='members'?'Members by residential town':'Voters by residential town' ?></h3><p class="small text-secondary">Top 15 towns in <?= e($scopeName) ?>. Click a tag to view records.</p><div class="dashboard-town-cloud">
<?php $maximum=max(1,...array_column($tags,'total'));foreach($tags as $tag): $size=.85+.45*log(1+(int)$tag['total'])/log(1+(int)$maximum); ?>
<a class="town-tag dashboard-town-tag" style="font-size:<?= e(round($size,2)) ?>rem" href="<?= e($link($kind,['residential_town_filter'=>$tag['id']])) ?>" aria-label="<?= e($tag['name'].': '.$tag['total'].' '.$kind) ?>"><?= e($tag['name']) ?><span><?= number($tag['total']) ?></span></a>
<?php endforeach; if(!$tags): ?><p class="small text-secondary mb-0">No <?= e($kind) ?> with a saved town in this district yet.</p><?php endif; ?>
</div></div><?php endforeach; ?></div>
<p class="small text-secondary mt-3 mb-0">Tag size reflects actual record counts. Members are included in voter counts; towns without saved links are excluded.</p>
<?php endif; endif; ?>
</div></section>

<div class="row g-4 mb-4">
<div class="col-xl-7"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Residential district coverage</h2><p class="card-subtitle">Top 8 districts by voters · linked residential records only</p></div></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col">District / region</th><th scope="col" class="text-end">Voters</th><th scope="col" class="text-end">Members</th><th scope="col" class="text-end">Member share</th></tr></thead><tbody>
<?php foreach($residential as $row): ?><tr><th scope="row"><a href="<?= e($link('voters',['residential_district_filter'=>$row['id']])) ?>"><?= e($row['name']) ?></a><div class="small text-secondary fw-normal"><?= e($row['region_name']) ?></div></th><td class="text-end"><?= number($row['voters']) ?></td><td class="text-end"><a href="<?= e($link('members',['residential_district_filter'=>$row['id']])) ?>"><?= number($row['members']) ?></a></td><td class="text-end"><?= e($rate((int)$row['members'],(int)$row['voters'])) ?></td></tr><?php endforeach; if(!$residential): ?><tr><td colspan="4" class="dashboard-table-empty">No linked residential districts in this scope.</td></tr><?php endif; ?>
</tbody></table></div></section></div>
<div class="col-xl-5"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Record completeness</h2><p class="card-subtitle">Recorded values / voters in this scope</p></div></div><div class="card-body">
<?php foreach($coverage as [$text,$count,$total,$href]): ?><div class="dashboard-coverage-row"><div><span><?= e($text) ?></span><span><strong><?= number($count) ?></strong> / <?= number($total) ?> <small><?= e($rate($count,$total)) ?></small></span></div><div class="dashboard-meter"><span style="width:<?= e($percent($count,$total)) ?>%"></span></div><?php if($href&&$count<$total): ?><a class="small" href="<?= e($href) ?>">View missing records (<?= number($total-$count) ?>)</a><?php endif; ?></div><?php endforeach; ?>
<p class="small text-secondary mt-3 mb-0">These show recorded fields, not verified accuracy. A branch is derived from the voter's polling station.</p>
</div></section></div>
</div>

<div class="row g-4 mb-4">
<div class="col-lg-5"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Gender breakdown</h2><p class="card-subtitle">Voters and the members within them</p></div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th scope="col">Gender</th><th scope="col" class="text-end">Voters</th><th scope="col" class="text-end">Members</th></tr></thead><tbody><?php foreach($gender as $row): ?><tr><th scope="row"><?= e($row['gender']==='unknown'?'Not recorded':label($row['gender'])) ?></th><td class="text-end"><a href="<?= e($link('voters',['gender'=>$row['gender']])) ?>"><?= number($row['voters']) ?></a></td><td class="text-end"><a href="<?= e($link('members',['gender'=>$row['gender']])) ?>"><?= number($row['members']) ?></a></td></tr><?php endforeach; if(!$gender): ?><tr><td colspan="3" class="dashboard-table-empty">No voters to summarize yet.</td></tr><?php endif; ?></tbody></table></div></section></div>
<div class="col-lg-7"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Member expertise &amp; education</h2><p class="card-subtitle">Identify members using their recorded backgrounds</p></div></div><div class="card-body"><?php if(!$profileReady): ?><p class="text-secondary mb-0">Member background summaries become available after migration 11.</p><?php else: ?><div class="row g-4"><div class="col-sm-6"><h3 class="h6">Top professions</h3><?php foreach($professions as $row): ?><a class="dashboard-summary-row" href="<?= e($link('members',['profession'=>$row['name']])) ?>"><span><?= e($row['name']) ?></span><strong><?= number($row['total']) ?></strong></a><?php endforeach; if(!$professions): ?><p class="small text-secondary">No professions recorded.</p><?php endif; ?></div><div class="col-sm-6"><h3 class="h6">Education level</h3><?php foreach($education as $row): ?><a class="dashboard-summary-row" href="<?= e($link('members',['education_level'=>$row['name']??'missing'])) ?>"><span><?= e(MemberProfileService::EDUCATION[$row['name']??'']??'Not recorded') ?></span><strong><?= number($row['total']) ?></strong></a><?php endforeach; if(!$education): ?><p class="small text-secondary">No members in this scope.</p><?php endif; ?></div></div><?php endif; ?></div></section></div>
</div>

<div class="row g-4 mb-4">
<?php foreach([['Residential regions',$residentialRegions,'residential_region_filter'],['Electoral constituencies',$electoral,'constituency_filter']] as [$title,$rows,$key]): ?>
<div class="col-lg-6"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title"><?= e($title) ?></h2><p class="card-subtitle">Top 6 by voters in the current residential scope</p></div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th scope="col"><?= $key==='constituency_filter'?'Constituency':'Region' ?></th><th scope="col" class="text-end">Voters</th><th scope="col" class="text-end">Members</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><th scope="row"><a href="<?= e($link('voters',[$key=>$row['id']])) ?>"><?= e($row['name']) ?></a><?php if(isset($row['region_name'])): ?><div class="small text-secondary fw-normal"><?= e($row['region_name']) ?></div><?php endif; ?></th><td class="text-end"><?= number($row['voters']) ?></td><td class="text-end"><a href="<?= e($link('members',[$key=>$row['id']])) ?>"><?= number($row['members']) ?></a></td></tr><?php endforeach; if(!$rows): ?><tr><td colspan="3" class="dashboard-table-empty">No linked locations in this scope.</td></tr><?php endif; ?></tbody></table></div></section></div>
<?php endforeach; ?>
</div>
<?php if($locationReady): ?><div class="dashboard-footnote mb-4"><i class="bi bi-diagram-3" aria-hidden="true"></i><span>Electoral reach: <strong><?= number($stats['constituencies']) ?></strong> constituencies and <strong><?= number($stats['stations']) ?></strong> polling stations represented. Residential locations and electoral locations are separate directories.</span><?php if(Auth::canManage()): ?><a href="<?= e(url('locations')) ?>">Manage locations</a><?php endif; ?></div><?php endif; ?>

<div class="row g-4">
<div class="col-xl-7"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Recently added voters</h2><p class="card-subtitle">Latest 6 in this scope</p></div><a href="<?= e($link('voters',['sort'=>'newest'])) ?>">View all</a></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th scope="col">Voter</th><th scope="col">Residential town</th><th scope="col">Membership</th></tr></thead><tbody><?php foreach($recentVoters as $person): ?><tr><th scope="row"><a href="<?= e(url($person['membership_id']?'member':'voter',['id'=>$person['membership_id']?:$person['id']])) ?>"><?= e($person['full_name']) ?></a><div class="small text-secondary fw-normal"><?= e($person['voter_id']) ?> · <?= e(date_display($person['created_at'])) ?></div></th><td><?= e($person['town']??'Not recorded') ?></td><td><?php if($person['membership_id']): ?><a href="<?= e(url('member',['id'=>$person['membership_id']])) ?>"><?= e(member_identifier($person)) ?></a><?php else: ?>Not a member<?php endif; ?></td></tr><?php endforeach; if(!$recentVoters): ?><tr><td colspan="3" class="dashboard-table-empty">No voters in this scope.</td></tr><?php endif; ?></tbody></table></div></section></div>
<div class="col-xl-5"><section class="card h-100 dashboard-panel"><div class="card-header"><div><h2 class="card-title">Recent memberships</h2><p class="card-subtitle">Latest 5 saved in this scope</p></div><a href="<?= e($link('members',['sort'=>'newest'])) ?>">View all</a></div><div class="card-body"><?php foreach($recentMembers as $person): ?><a class="dashboard-recent-member" href="<?= e(url('member',['id'=>$person['id']])) ?>"><div><strong><?= e($person['full_name']) ?></strong><span><?= e(member_identifier($person)) ?> · <?= e($person['town']??'Town not recorded') ?></span><small>Joined <?= e(date_display($person['joined_on'])) ?> · Saved <?= e(date_display($person['created_at'])) ?></small></div><?= badge($person['status']) ?></a><?php endforeach; if(!$recentMembers): ?><p class="text-secondary mb-0">No memberships saved in this scope yet.</p><?php endif; ?></div></section></div>
</div>
</div>
