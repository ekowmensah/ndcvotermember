<?php
use App\Auth;
use App\DirectoryUi;
$route=$membersOnly?'members':'voters';$officialReady=\App\VoterService::officialIdsReady();$profileReady=\App\MemberProfileService::ready();
$regions=array_column($locationCatalog['regions']??[],'name','id');$constituencies=[];$branches=[];
foreach($locationCatalog['constituencies']??[] as $item){$constituencies[$item['id']]=$item['name'].' / '.($regions[$item['region_id']]??'');}
foreach($locationCatalog['electoral_areas']??[] as $item){if(empty($filters['constituency_filter'])||(int)$item['constituency_id']===(int)$filters['constituency_filter']){$branches[$item['id']]=$item['name'].' / '.($constituencies[$item['constituency_id']]??'');}}
$resRegionOptions=array_column($addressCatalog['regions']??[],'name','id');$resDistrictOptions=[];$townOptions=[];
foreach($addressCatalog['districts']??[] as $item){if(empty($filters['residential_region_filter'])||(int)$item['region_id']===(int)$filters['residential_region_filter']){$resDistrictOptions[$item['id']]=$item['name'].' / '.$item['region_name'];}}
foreach($addressCatalog['towns']??[] as $item){
 if(!empty($filters['residential_region_filter'])&&(int)$item['residential_region_id']!==(int)$filters['residential_region_filter']){continue;}
 if(!empty($filters['residential_district_filter'])&&(int)$item['district_id']!==(int)$filters['residential_district_filter']){continue;}
 $townOptions[$item['id']]=$item['name'].' / '.(implode(' / ',array_filter([$item['district_name'],$item['region_name']]))?:'Location not recorded');
}
$missingOptions=[''=>'Any record completeness','phone'=>'Phone not recorded','dob'=>'Date of birth not recorded'];
if(\App\AddressService::ready()){$missingOptions['district']='Residential district not assigned';}
if(\App\TownService::ready()){$missingOptions['town']='Saved residential town not assigned';}
if(\App\LocationService::ready()){$missingOptions['station']='Polling station not assigned';$missingOptions['branch']='Derived branch not assigned';}
$filterLabels=['life_status'=>'Life status','q'=>'Search','membership'=>'Membership','status'=>'Status','photo'=>'Photo','gender'=>'Gender','created_month'=>'Created month','missing'=>'Missing','official_id'=>'Party ID','electoral_region_filter'=>'Electoral region','constituency_filter'=>'Constituency','branch_filter'=>'Branch','polling_station_filter'=>'Polling station','residential_region_filter'=>'Residential region','residential_district_filter'=>'District','residential_town_filter'=>'Town','town'=>'Town contains','profile_status'=>'Background','profession'=>'Profession','education_level'=>'Education','employment_status'=>'Employment','skills'=>'Skills','language'=>'Language','employer'=>'Employer','volunteer_interest'=>'Volunteer interest','availability'=>'Availability'];
$filterChoices=['life_status'=>['deceased'=>'Deceased','not_deceased'=>'Not marked deceased'],'electoral_region_filter'=>$regions,'constituency_filter'=>$constituencies,'branch_filter'=>$branches,'residential_region_filter'=>$resRegionOptions,'residential_district_filter'=>$resDistrictOptions,'residential_town_filter'=>$townOptions,'missing'=>$missingOptions,'gender'=>['female'=>'Female','male'=>'Male','other'=>'Other','unknown'=>'Not recorded'],'membership'=>['member'=>'Members','not_member'=>'Non-members'],'status'=>['active'=>'Active','inactive'=>'Inactive'],'photo'=>['with'=>'With photo','without'=>'Without photo'],'official_id'=>['missing'=>'Not recorded','recorded'=>'Recorded'],'profile_status'=>['with'=>'Recorded','without'=>'Not recorded']];
foreach(\App\MemberProfileService::choices() as $field=>$options){$filterChoices[$field]=$options+['missing'=>'Not recorded','degree'=>'Any degree'];}
if(!empty($filters['polling_station_filter'])){$filterChoices['polling_station_filter']=[(string)$filters['polling_station_filter']=>(\App\Database::scalar('SELECT name FROM polling_stations WHERE id=?',[$filters['polling_station_filter']])??'Station #'.$filters['polling_station_filter'])];}
$filterLabels['wing']='Wing';$filterChoices['wing']=\App\Membership\WingService::WINGS+['none'=>'Not enrolled in any wing'];
$filterChips=DirectoryUi::chips($route,$filters,$filterLabels,$filterChoices);
$directoryMetrics=[
 ['label'=>$membersOnly?'Matching members':'Matching voters','count'=>$total,'href'=>url($route,$filters),'icon'=>'bi-people','note'=>'Across all result pages'],
 ['label'=>$membersOnly?'Active members':'Members in results','count'=>$summary[$membersOnly?'active':'members'],'href'=>url($route,array_replace($filters,$membersOnly?['status'=>'active','p'=>1]:['membership'=>'member','p'=>1])),'icon'=>'bi-person-check','note'=>$membersOnly?'Current membership status':'Included in voter total'],
 ['label'=>$membersOnly?'Inactive members':'Non-members in results','count'=>$summary[$membersOnly?'inactive':'nonmembers'],'href'=>url($route,array_replace($filters,$membersOnly?['status'=>'inactive','p'=>1]:['membership'=>'not_member','p'=>1])),'icon'=>'bi-person-dash','note'=>$membersOnly?'Membership retained':'Available for enrollment review'],
 ['label'=>'With profile photo','count'=>$summary['with_photo'],'href'=>url($route,array_replace($filters,['photo'=>'with','p'=>1])),'icon'=>'bi-person-bounding-box','note'=>'In current results'],
];
?>
<div class="directory-workspace" data-directory-workspace="<?= e($route) ?>">
<header class="page-heading"><div><span class="section-kicker">PEOPLE DIRECTORY</span><h1><?= $membersOnly?'Members':'Voters' ?></h1><p><?= $membersOnly?'A clear view of membership, background and local representation.':'Search your voter records, check locations and review membership.' ?></p></div><div class="d-flex flex-wrap gap-2"><?php if(Auth::canManage()&&!$membersOnly): ?><a class="btn btn-outline-primary" href="<?= e(url('imports')) ?>"><i class="bi bi-upload me-2" aria-hidden="true"></i>Upload CSV</a><a class="btn btn-primary" href="<?= e(url('voter')) ?>"><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Add voter</a><?php elseif(Auth::canManage()): ?><a class="btn btn-primary" href="<?= e(url('voters',['membership'=>'not_member'])) ?>">Find voters to enroll</a><?php endif; ?></div></header>
<?php require \App\View::path('shared/components/directory/metrics'); ?>
<div class="directory-info-line"><i class="bi bi-info-circle" aria-hidden="true"></i><span><?= $membersOnly?'Update every detail through the member profile. Inactive members keep their membership.':'Voters include members. Enrolled people open in their member profile for all updates.' ?></span></div>
<section class="card directory-panel people-directory">
<?php require \App\View::path('shared/people/partials/filters'); ?>
<div class="directory-result-toolbar"><div><h2 class="h6 mb-1"><?= $membersOnly?'Member directory':'Voter directory' ?></h2><span class="small text-secondary"><?= number($total) ?> matching records · Branch follows polling station</span></div><?php if($rows): ?><details class="directory-columns" data-directory-columns><summary><i class="bi bi-layout-three-columns me-1" aria-hidden="true"></i>Columns</summary><div data-column-options></div></details><?php endif; ?></div>
<?php require \App\View::path('shared/people/partials/table'); ?>
<?php $pagination=['route'=>$route,'filters'=>$filters,'total'=>$total,'page'=>$listingPage,'per_page'=>$perPage,'last'=>$lastPage];require \App\View::path('shared/components/directory/pagination'); ?>
</section></div>
