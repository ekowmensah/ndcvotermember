<section class="mb-4" aria-labelledby="dashboard-wings-title">
<div class="d-flex align-items-start justify-content-between gap-3 mb-3"><div><h2 class="h5 mb-1" id="dashboard-wings-title">Membership wings</h2><p class="small text-secondary mb-0">Selected residential scope &middot; Deleted voter records excluded</p></div></div>
<?php if(!$wingStats['available']): ?><div class="card"><div class="card-body"><p class="text-secondary mb-0">Import database/20_membership_wings_mysql8.sql in phpMyAdmin to enable wing statistics. No members are enrolled automatically.</p></div></div><?php else: $wingScope=$wingStats['scope']; ?>
<div class="row g-4"><?php foreach($wingStats['wings'] as $wingCode=>$wingData): $wingCounts=$wingData['counts'];$wingDirectoryScope=$wingScope+['wing'=>$wingCode,'active_people'=>'1'];$wingExecutiveScope=$wingScope+['organization_scope'=>$wingCode]; ?>
<div class="col-xl-6"><article class="card dashboard-panel h-100"><div class="card-header"><div><h3 class="card-title"><?= e($wingData['label']) ?></h3><p class="card-subtitle"><?= $wingCode==='youth'?'Ages 18–49 inclusive':'Female members' ?> &middot; Explicit enrollment</p></div><a href="<?= e(url('wings',$wingDirectoryScope)) ?>">View directory <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div><div class="card-body">
<div class="row g-3">
<?php foreach([
 ['Enrolled members',$wingCounts['enrolled'],'wings',$wingDirectoryScope,'Current enrollment records, including those needing review'],
 ['Eligible, not enrolled',$wingData['eligible'],'wing_candidates',$wingScope+['wing'=>$wingCode],'Meets recorded criteria; confirmation still required'],
 ['Needs review',$wingCounts['needs_review'],'wings',$wingDirectoryScope+['review'=>'needs_review'],'Open enrollments with eligibility, life or membership issues'],
 ['Ended enrollments',$wingCounts['ended'],'wings',$wingDirectoryScope+['state'=>'ended'],'Historical records, including previous enrollment periods']
 ] as [$text,$count,$route,$query,$note]): ?>
<div class="col-sm-6"><a class="d-block border rounded p-3 h-100 text-body" href="<?= e(url($route,$query)) ?>"><span class="small text-secondary d-block"><?= e($text) ?></span><strong class="fs-2 d-block my-1"><?= number($count) ?></strong><span class="small text-secondary d-block"><?= e($note) ?></span></a></div>
<?php endforeach; ?></div>
<?php if($wingCode==='youth'): ?><a class="dashboard-summary-row mt-3" href="<?= e(url('wings',$wingDirectoryScope+['review'=>'aged_out'])) ?>"><span>Youth enrollments aged 50+ &middot; review required</span><strong><?= number($wingCounts['aged_out']) ?></strong></a><?php endif; ?>
<div class="border-top mt-3 pt-3"><h4 class="h6 mb-3">Executive service</h4>
<?php if(!$wingData['executives']['available']): ?><p class="small text-secondary mb-0">Import migration 21 in phpMyAdmin to enable separate wing executive statistics.</p><?php else: ?>
<?php foreach([['current','Current appointments','executive_appointments',['state'=>'current']],['scheduled','Scheduled appointments','executive_appointments',['state'=>'scheduled']],['completed','Completed terms','executive_history',['active_people'=>'1']]] as [$metric,$text,$route,$extra]): ?><a class="dashboard-summary-row" href="<?= e(url($route,array_merge($wingExecutiveScope,$extra))) ?>"><span><?= e($text) ?></span><strong><?= number($wingData['executives']['counts'][$metric]) ?></strong></a><?php endforeach; ?>
<?php endif; ?></div></div></article></div>
<?php endforeach; ?></div>
<p class="small text-secondary mt-3 mb-0">A person can belong to both wings; do not add wing totals to count unique people. Ended enrollments and executive terms are historical records. Age-out and other review flags never automatically end enrollment or service.</p>
<?php endif; ?></section>
