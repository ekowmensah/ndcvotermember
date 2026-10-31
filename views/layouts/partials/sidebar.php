<?php
use App\Auth;
$stationReview=$currentPage==='csv_preview'&&($draft['kind']??'')==='stations';
$voterReview=$currentPage==='csv_preview'&&!$stationReview;
$electoralType=$_GET['type']??'region';
$item=static fn(string $page,string $icon,string $label,bool $active,array $params=[]): array=>compact('page','icon','label','active','params');
$groups=[
 ['label'=>'Dashboard','items'=>[$item('dashboard','bi-grid-1x2','Overview',$currentPage==='dashboard')]],
 ['label'=>'People','items'=>[
  $item('voters','bi-person-lines-fill','Voters',in_array($currentPage,['voters','voter'],true)),
  $item('members','bi-people','Members',in_array($currentPage,['members','member','member_edit','member_enroll','member_wings','wing_history'],true))
 ]]
];
if(Auth::admin()){$groups[1]['items'][]=$item('deleted_voters','bi-trash','Deleted voters',in_array($currentPage,['deleted_voters','voter_restore'],true));}
$groups[]=['label'=>'Membership wings','items'=>[
 $item('wings','bi-people','Youth Wing',in_array($currentPage,['wings','wing_candidates'],true)&&($_GET['wing']??'')==='youth',['wing'=>'youth']),
 $item('wings','bi-people','Women\'s Wing',in_array($currentPage,['wings','wing_candidates'],true)&&($_GET['wing']??'')==='women',['wing'=>'women'])
]];
$groups[]=['label'=>'Executives','items'=>[$item('executive_appointments','bi-person-badge','Executive appointments',in_array($currentPage,['executive_appointments','executive_appointment_add','executive_appointment','executive_appointment_manage'],true)),$item('executive_history','bi-award','Hall of service',$currentPage==='executive_history'),$item('executive_terms','bi-calendar-range','Leadership terms',in_array($currentPage,['executive_terms','executive_term_edit'],true)),$item('executive_recognition','bi-trophy','Hall of Fame',in_array($currentPage,['executive_recognition','executive_recognition_edit'],true)),$item('executive_reports','bi-file-earmark-bar-graph','Reports',in_array($currentPage,['executive_reports','executive_report_print'],true)),$item('executive_positions','bi-briefcase','Positions',in_array($currentPage,['executive_positions','executive_position_edit'],true))]];
if(Auth::canManage()){
 $groups[]=['label'=>'Data management','items'=>[
  ['label'=>'CSV uploads','icon'=>'bi-cloud-arrow-up','children'=>[
   $item('imports','bi-person-lines-fill','Voter uploads',$currentPage==='imports'||$voterReview),
   $item('station_imports','bi-geo-alt','Polling station uploads',$currentPage==='station_imports'||$stationReview)
  ]],
  ['label'=>'Electoral locations','icon'=>'bi-diagram-3','children'=>[
   $item('locations','bi-map','Regions',$currentPage==='locations'&&$electoralType==='region',['type'=>'region']),
   $item('locations','bi-bounding-box','Constituencies',$currentPage==='locations'&&$electoralType==='constituency',['type'=>'constituency']),
   $item('locations','bi-signpost-split','Electoral areas (branches)',$currentPage==='locations'&&$electoralType==='electoral_area',['type'=>'electoral_area']),
   $item('locations','bi-geo-alt','Polling stations',$currentPage==='locations'&&$electoralType==='polling_station',['type'=>'polling_station'])
  ]],
  ['label'=>'Residential locations','icon'=>'bi-house-gear','children'=>[
   $item('residential_regions','bi-map','Regions',$currentPage==='residential_regions'),
   $item('districts','bi-buildings','Districts',$currentPage==='districts'),
   $item('residential_towns','bi-houses','Towns',in_array($currentPage,['residential_towns','residential_town_edit'],true))
  ]]
 ]];
}
if(Auth::admin()){$groups[]=['label'=>'Administration','items'=>[$item('staff','bi-shield-lock','Staff accounts',in_array($currentPage,['staff','staff_edit'],true)),$item('security_activity','bi-shield-check','Security activity',$currentPage==='security_activity'),$item('system_health','bi-heart-pulse','System health',$currentPage==='system_health')]];}
$groups[]=['label'=>'Personal','items'=>[$item('account','bi-person-gear','My account',$currentPage==='account')]];
if(Auth::canManage()&&\App\Security\ExecutiveAccess::ready()){$groups[]=['label'=>'Reviews','items'=>[$item('record_reviews','bi-clipboard-check','Record reviews',$currentPage==='record_reviews')]];}
if(\App\Security\ExecutiveAccess::restricted()){
 $groups=[['label'=>'Executive workspace','items'=>[$item('dashboard','bi-grid-1x2','My jurisdiction',$currentPage==='dashboard'),$item('voters','bi-person-lines-fill','Voters',in_array($currentPage,['voters','voter'],true)),$item('members','bi-people','Members',in_array($currentPage,['members','member'],true))]],['label'=>'Personal','items'=>[$item('account','bi-person-gear','My account',$currentPage==='account')]]];
}
$groups[]=['label'=>'Reports','items'=>[$item('reports','bi-bar-chart-line','Reports Centre',in_array($currentPage,['reports','reports_print'],true))]];
if(\App\Membership\BranchWorkflow::ready()&&(Auth::admin()||\App\Membership\BranchWorkflow::permitted('can_nominate')||\App\Membership\BranchWorkflow::permitted('can_approve_membership'))){$groups[]=['label'=>'Membership review','items'=>[$item('membership_nominations','bi-person-check','Nominations',in_array($currentPage,['membership_nominations','membership_nomination'],true))]];}
?>
<aside class="app-sidebar bg-sidebar shadow-sm">
<div class="sidebar-brand"><a class="brand-link" href="<?= e(url()) ?>"><img class="brand-image" src="<?= e(asset('img/ndc-ghana-logo.svg')) ?>" alt=""><span class="ndc-wordmark"><strong>NDC Workspace</strong><small>Voters &amp; Membership</small></span></a></div>
<div class="sidebar-wrapper"><nav class="workspace-navigation" aria-label="Main navigation">
<?php foreach($groups as $group): $groupActive=(bool)array_filter($group['items'],static fn($node)=>isset($node['children'])?(bool)array_filter($node['children'],static fn($child)=>$child['active']):$node['active']); ?>
<details class="workspace-nav-section" name="sidebar-sections" <?= $groupActive?'open':'' ?>><summary class="workspace-nav-label"><span><?= e($group['label']) ?></span><i class="bi bi-chevron-right workspace-nav-chevron" aria-hidden="true"></i></summary><ul class="nav sidebar-menu flex-column">
<?php foreach($group['items'] as $node): ?>
<?php if(isset($node['children'])): $expanded=(bool)array_filter($node['children'],static fn($child)=>$child['active']); ?>
<li class="nav-item"><details class="workspace-nav-group" name="sidebar-submenus" <?= $expanded?'open':'' ?>><summary><i class="nav-icon bi <?= e($node['icon']) ?>" aria-hidden="true"></i><span><?= e($node['label']) ?></span><i class="bi bi-chevron-right workspace-nav-chevron" aria-hidden="true"></i></summary><ul class="nav flex-column workspace-nav-children">
<?php foreach($node['children'] as $child): ?><li class="nav-item"><a class="nav-link <?= $child['active']?'active':'' ?>" <?= $child['active']?'aria-current="page"':'' ?> href="<?= e(url($child['page'],$child['params'])) ?>"><i class="nav-icon bi <?= e($child['icon']) ?>" aria-hidden="true"></i><span><?= e($child['label']) ?></span></a></li><?php endforeach; ?>
</ul></details></li>
<?php else: ?>
<li class="nav-item"><a class="nav-link <?= $node['active']?'active':'' ?>" <?= $node['active']?'aria-current="page"':'' ?> href="<?= e(url($node['page'],$node['params'])) ?>"><i class="nav-icon bi <?= e($node['icon']) ?>" aria-hidden="true"></i><p><?= e($node['label']) ?></p></a></li>
<?php endif; ?>
<?php endforeach; ?></ul></details>
<?php endforeach; ?>
</nav><div class="workspace-status"><i class="bi bi-shield-check" aria-hidden="true"></i><div><strong>Staff workspace</strong><small>National Democratic Congress &middot; Ghana</small></div></div></div></aside>
