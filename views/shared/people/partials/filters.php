<div class="directory-search-area"><form method="get" action="<?= e(base_url('index.php')) ?>" data-directory-filters data-directory-searchable data-people-constituencies="<?= e(json_encode($locationCatalog['constituencies']??[],JSON_THROW_ON_ERROR)) ?>" data-residential-filters="<?= e(json_encode($addressCatalog['districts']??[],JSON_THROW_ON_ERROR)) ?>" data-residential-towns="<?= e(json_encode($addressCatalog['towns']??[],JSON_THROW_ON_ERROR)) ?>" data-branches="<?= e(json_encode($locationCatalog['electoral_areas']??[],JSON_THROW_ON_ERROR)) ?>">
<input type="hidden" name="page" value="<?= e($route) ?>">
<?php if(!empty($filters['polling_station_filter'])): ?><input type="hidden" name="polling_station_filter" value="<?= e($filters['polling_station_filter']) ?>"><?php endif; ?>
<div class="directory-search-row"><div class="flex-grow-1"><?= input('q',$membersOnly?'Search members':'Search voters',$filters['q']??'','search',false,'maxlength="100" placeholder="Name, voter ID, system number or official party ID"') ?></div><button class="btn btn-primary" type="submit"><i class="bi bi-search me-1" aria-hidden="true"></i>Search</button><a class="btn btn-outline-secondary" href="<?= e(url($route)) ?>">Reset</a></div>
<details class="directory-filter-panel"><summary><span><i class="bi bi-sliders me-2" aria-hidden="true"></i>Filters <span class="directory-count"><?= number(count($filterChips)-(isset($filters['q'])?1:0)) ?> applied</span></span><i class="bi bi-chevron-down" aria-hidden="true"></i></summary><div class="directory-filter-content">
<fieldset class="directory-filter-group"><legend>Record criteria</legend><div class="row g-3">
<?php if($membersOnly&&\App\Membership\WingService::ready()): ?><div class="col-md-4"><?= select_field('wing','Membership wing',[''=>'All members']+\App\Membership\WingService::WINGS+['none'=>'Not enrolled in any wing'],$filters['wing']??'') ?></div><?php endif; ?>
<div class="col-md-4"><?php if($membersOnly): ?><?= select_field('status','Membership status',[''=>'All statuses','active'=>'Active','inactive'=>'Inactive'],$filters['status']??'') ?><?php else: ?><?= select_field('membership','Membership',[''=>'All voters','member'=>'Members','not_member'=>'Non-members'],$filters['membership']??'') ?><?php endif; ?></div>
<div class="col-md-4"><?= select_field('gender','Gender',[''=>'Any gender','female'=>'Female','male'=>'Male','other'=>'Other','unknown'=>'Not recorded'],$filters['gender']??'') ?></div>
<div class="col-md-4"><?= select_field('photo','Photo',[''=>'All records','with'=>'With photo','without'=>'Without photo'],$filters['photo']??'') ?></div>
<div class="col-md-4"><?= input('created_month',$membersOnly?'Membership saved in month':'Voter added in month',$filters['created_month']??'','month',false,'min="1000-01" max="9999-12"') ?></div>
<div class="col-md-4"><?= select_field('missing','Missing information',$missingOptions,$filters['missing']??'') ?></div>
<?php if(\App\People\LifeStatusService::ready()): ?><div class="col-md-4"><?= select_field('life_status','Life status',[''=>'All records','not_deceased'=>'Not marked deceased','deceased'=>'Deceased'],$filters['life_status']??'') ?></div><?php endif; ?>
<?php if($membersOnly&&$officialReady): ?><div class="col-md-4"><?= select_field('official_id','Party ID',[''=>'Any ID status','recorded'=>'Recorded','missing'=>'Not recorded'],$filters['official_id']??'') ?></div><?php endif; ?>
</div></fieldset>
<?php if($locationCatalog): ?><fieldset class="directory-filter-group"><legend>Electoral location</legend><div class="row g-3">
<div class="col-md-4"><?= select_field('electoral_region_filter','Electoral region',[''=>'All electoral regions']+$regions,$filters['electoral_region_filter']??'') ?></div>
<div class="col-md-4"><?= select_field('constituency_filter','Constituency',[''=>'All constituencies']+$constituencies,$filters['constituency_filter']??'') ?></div>
<div class="col-md-4"><?= select_field('branch_filter','Electoral area / branch',[''=>'All branches']+$branches,$filters['branch_filter']??'') ?></div>
</div></fieldset><?php endif; ?>
<?php if($addressCatalog): ?><fieldset class="directory-filter-group"><legend>Residential address</legend><div class="row g-3">
<div class="col-md-4"><?= select_field('residential_region_filter','Residential region',[''=>'All residential regions']+$resRegionOptions,$filters['residential_region_filter']??'') ?></div>
<div class="col-md-4"><?= select_field('residential_district_filter','District',[''=>'All districts']+$resDistrictOptions,$filters['residential_district_filter']??'') ?></div>
<?php if(\App\TownService::ready()): ?><div class="col-md-4"><?= select_field('residential_town_filter','Saved town',[''=>'All saved towns']+$townOptions,$filters['residential_town_filter']??'') ?></div><?php endif; ?>
<div class="col-md-4"><?= input('town','Town name contains',$filters['town']??'','search',false,'maxlength="180"') ?></div>
</div></fieldset><?php endif; ?>
<div class="row"><?php require \App\View::path('members/partials/filters'); ?></div>
<div class="d-flex gap-2 mt-3"><button class="btn btn-primary" type="submit">Apply filters</button><a class="btn btn-light" href="<?= e(url($route)) ?>">Clear all</a></div>
</div></details>
<div class="directory-display-controls"><div><?= select_field('sort','Sort by',['name'=>'Full name A–Z','name_desc'=>'Full name Z–A','newest'=>'Newest first']+(\App\VoterService::namesReady()?['last_name'=>'Last name A–Z','last_name_desc'=>'Last name Z–A','first_name'=>'First name A–Z','first_name_desc'=>'First name Z–A']:[])+($membersOnly&&$profileReady?['profession'=>'Profession A–Z','education'=>'Education level']:[])+($addressCatalog?['town'=>'Town A–Z','district'=>'District A–Z','residential_region'=>'Residential region A–Z']:[]),$filters['sort']) ?></div><div><?= select_field('per_page','Rows per page',['25'=>'25','50'=>'50','100'=>'100'],$perPage) ?></div><button class="btn btn-sm btn-outline-secondary" type="submit">Update view</button><span class="small text-secondary">Filters combine to narrow results.</span></div>
</form><?php require \App\View::path('shared/components/directory/filter_chips'); ?></div>
