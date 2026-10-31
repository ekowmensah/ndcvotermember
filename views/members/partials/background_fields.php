<?php
$profileReady=\App\MemberProfileService::ready();
$profileValues=!empty($voter['membership_id'])?$memberForm:$membership;
?>
<hr><h3 class="h5">Member background <span class="small text-secondary fw-normal">Optional</span></h3><p class="small text-secondary">Record the member’s profession, education and skills to find people with relevant experience. Leave anything not known blank.</p>
<?php if(!$profileReady): ?><p class="small text-secondary">Apply migration 08, then import <strong>database/11_member_profiles_mysql8.sql</strong> in phpMyAdmin to enable these fields. Existing memberships can still be saved without background details.</p>
<?php else: ?>
<input type="hidden" name="member_profile_present" value="1"><div class="row">
<div class="col-md-6"><?= input('profession','Profession',$profileValues['profession']??'','text',false,'maxlength="150" autocomplete="off"') ?></div>
<div class="col-md-6"><?= select_field('education_level','Highest education level',[''=>'Not recorded']+\App\MemberProfileService::EDUCATION,$profileValues['education_level']??'') ?></div>
<div class="col-md-6"><?= select_field('employment_status','Employment status',[''=>'Not recorded']+\App\MemberProfileService::EMPLOYMENT,$profileValues['employment_status']??'') ?></div>
<div class="col-md-6"><?= input('job_title','Job title',$profileValues['job_title']??'','text',false,'maxlength="150"') ?></div>
<div class="col-12"><?= input('employer','Employer / organization',$profileValues['employer']??'','text',false,'maxlength="180"') ?></div>
<div class="col-12"><?= input('skills','Skills / areas of expertise',$profileValues['skills']??'','text',false,'maxlength="500" aria-describedby="member-skills-help"') ?><p class="form-text" id="member-skills-help">Separate multiple skills with commas. Use consistent names so they are easier to find.</p></div>
<div class="col-12"><?= input('languages','Languages spoken',$profileValues['languages']??'','text',false,'maxlength="250"') ?></div>
<div class="col-12"><?= input('volunteer_interests','Volunteer interests',$profileValues['volunteer_interests']??'','text',false,'maxlength="500"') ?></div>
<div class="col-12"><?= select_field('availability','Volunteer availability',[''=>'Not recorded']+\App\MemberProfileService::AVAILABILITY,$profileValues['availability']??'') ?></div>
</div><?php endif; ?>
