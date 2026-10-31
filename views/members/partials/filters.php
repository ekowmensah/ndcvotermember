<?php if($membersOnly&&\App\MemberProfileService::ready()): ?>
<div class="col-12"><section class="directory-filter-group"><h3 class="h6">Member background criteria</h3><p class="small text-secondary mt-2">Combine these criteria with membership status and location. All selected criteria must match.</p><div class="row g-3">
<div class="col-md-4"><?= input('profession','Profession contains',$filters['profession']??'','search',false,'maxlength="150"') ?></div>
<div class="col-md-4"><?= select_field('education_level','Education level',[''=>'Any education level','missing'=>'Education not recorded','degree'=>'Any degree (bachelor’s / master’s / doctorate)']+\App\MemberProfileService::EDUCATION,$filters['education_level']??'') ?></div>
<div class="col-md-4"><?= select_field('employment_status','Employment status',[''=>'Any employment status','missing'=>'Employment not recorded']+\App\MemberProfileService::EMPLOYMENT,$filters['employment_status']??'') ?></div>
<div class="col-md-4"><?= input('skills','Skills contain',$filters['skills']??'','search',false,'maxlength="150"') ?></div>
<div class="col-md-4"><?= input('language','Language contains',$filters['language']??'','search',false,'maxlength="150"') ?></div>
<div class="col-md-4"><?= input('employer','Employer contains',$filters['employer']??'','search',false,'maxlength="150"') ?></div>
<div class="col-md-4"><?= input('volunteer_interest','Volunteer interest contains',$filters['volunteer_interest']??'','search',false,'maxlength="150"') ?></div>
<div class="col-md-4"><?= select_field('availability','Volunteer availability',[''=>'Any availability','missing'=>'Availability not recorded']+\App\MemberProfileService::AVAILABILITY,$filters['availability']??'') ?></div>

<div class="col-md-4"><?= select_field('profile_status','Background details',[''=>'All members','with'=>'With background details','without'=>'No background details'],$filters['profile_status']??'') ?></div>
</div></section></div><?php endif; ?>
