<?php
declare(strict_types=1);
namespace App;
final class Controller
{
 public function get(): void {
  $page=(string)($_GET['page']??'dashboard');
  if($page==='activate_account'){View::render('auth/activate',[],'Activate account');return;}
  if($page==='login'){if(Auth::user()){redirect('dashboard');}View::render('auth/login',[],'Sign in');return;}
  Auth::requireLogin();
  if(\App\Security\ExecutiveAccess::dispatch($page)){return;}
  switch($page){
   case 'reports': \App\Reporting\ReportsController::get();break;
   case 'reports_print': \App\Reporting\ReportsController::get(true);break;
   case 'staff_member_details': Auth::requireRole(['admin']);$details=\App\Security\StaffIdentity::details(Validation::id($_GET,'id'));header('Content-Type: application/json; charset=utf-8');echo json_encode($details,JSON_THROW_ON_ERROR);exit;
   case 'executive_account': \App\Security\AppointmentAccounts::page(Validation::id($_GET,'id'));break;
   case 'branch_voter_edit': case 'membership_nominations': case 'membership_nomination': \App\Membership\BranchWorkflowController::get($page);break;
   case 'security_confirm': View::render('security/auth/confirm',[],'Confirm password');break;
   case 'security_activity': \App\Security\SessionSecurity::activity($_GET);break;
   case 'system_health': \App\Security\SystemHealth::show();break;
   case 'record_reviews': \App\Security\ExecutiveAccessAdmin::reviews();break;
   case 'staff': \App\Security\StaffController::get();break;
   case 'staff_edit': \App\Security\StaffController::get(true);break;
   case 'wing_report_print': \App\Membership\WingController::report($_GET);break;
   case 'wing_candidates': View::render('membership/wings/candidates',\App\Membership\WingEligibilityService::candidates($_GET),'Wing eligibility suggestions');break;
   case 'wing_history': View::render('membership/wings/history',\App\Membership\WingHistoryService::page($_GET),'Wing participation history');break;
   case 'wings': \App\Membership\WingController::get();break;
   case 'member_wings': \App\Membership\WingController::get(true);break;
   case 'executive_terms': case 'executive_term_edit': case 'executive_recognition': case 'executive_recognition_edit': case 'executive_reports': case 'executive_report_print': \App\Executives\ToolsController::get($page);break;
   case 'executive_history': View::render('executives/history/index',\App\Executives\HistoryService::listing($_GET),'Hall of service');break;
   case 'executive_appointment_manage': \App\Executives\LifecycleController::get();break;
   case 'executive_appointment': View::render('executives/appointments/show',\App\Executives\AppointmentService::detail(Validation::id($_GET,'id')),'Executive appointment');break;
   case 'executive_appointments': \App\Executives\AppointmentController::get(false);break;
   case 'executive_appointment_add': \App\Executives\AppointmentController::get(true);break;
   case 'executive_positions': \App\Executives\PositionController::get(false);break;
   case 'executive_position_edit': \App\Executives\PositionController::get(true);break;
   case 'life_status': View::render('shared/people/life_status',\App\People\LifeStatusService::page(Validation::id($_GET,'id')),'Life status');break;
   case 'deleted_voters': View::render('voters/deleted/index',\App\Voters\TrashService::listing($_GET),'Deleted voters');break;
   case 'voter_delete': View::render('voters/deleted/review',\App\Voters\TrashService::review(Validation::id($_GET,'id'),true),'Delete voter');break;
   case 'voter_restore': View::render('voters/deleted/review',\App\Voters\TrashService::review(Validation::id($_GET,'id')),'Review deleted voter');break;
   case 'residential_town_edit': VoterService::requireManage();View::render('locations/residential/towns/edit',TownService::editor(Validation::id($_GET,'id')),'Edit residential town');break;
   case 'residential_towns': VoterService::requireManage();View::render('locations/residential/towns/index',TownService::directory($_GET),'Residential towns');break;
   case 'residential_regions': VoterService::requireManage();View::render('locations/residential/regions/index',AddressService::regionDirectory($_GET),'Residential regions');break;
   case 'districts': VoterService::requireManage();View::render('locations/residential/districts/index',AddressService::directory($_GET),'Districts');break;
   case 'voter_photo': PhotoService::output(Validation::id($_GET,'id'));
   case 'csv_preview': VoterService::requireManage();View::render('uploads/review',CsvPreviewService::page($_GET),'Review CSV upload');break;
   case 'station_imports': VoterService::requireManage();View::render('uploads/polling_stations/index',PollingStationImportService::page($_GET),'Upload polling stations');break;
   case 'station_template': VoterService::requireManage();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="polling-stations-template.csv"');$out=fopen('php://output','wb');fputcsv($out,PollingStationImportService::HEADERS,',','"','');fclose($out);break;
   case 'locations': VoterService::requireManage();View::render('locations/electoral/index',['catalog'=>LocationService::catalog(false)]+LocationService::directory($_GET),'Locations');break;
   case 'dashboard': View::render('dashboard/index',DashboardService::overview($_GET),'Overview');break;
   case 'voters': View::render('voters/index',Repository::voters()+['membersOnly'=>false],'Voters');break;
   case 'members': View::render('members/index',Repository::voters(true)+['membersOnly'=>true],'Members');break;
   case 'voter':
    $voter=isset($_GET['id'])?Repository::voter(Validation::id($_GET,'id')):null;
    if(!empty($voter['membership_id'])){redirect('member',['id'=>$voter['membership_id']]);}
    if(!$voter||($_GET['edit']??'')==='1'){
     VoterService::requireManage();View::render('voters/form',['voter'=>$voter],$voter?'Edit voter':'Add voter');
    }else{View::render('voters/show',\App\Voters\ProfileService::present($voter),'Voter profile');}
    break;
   case 'member': if(($_GET['edit']??null)==='1'){redirect('member_edit',['id'=>Validation::id($_GET,'id')]);}View::render('members/show',MemberViewService::profile(Validation::id($_GET,'id')),'Member profile');break;
   case 'member_edit': VoterService::requireManage();View::render('members/edit',MemberViewService::profile(Validation::id($_GET,'id'),true),'Edit member');break;
   case 'member_enroll': VoterService::requireManage();$voter=Repository::voter(Validation::id($_GET,'id'));if($voter['membership_id']){redirect('member',['id'=>$voter['membership_id']]);}View::render('members/enroll',['voter'=>$voter],'Mark as member');break;
   case 'imports': VoterService::requireManage();View::render('uploads/voters/index',['imports'=>Database::all('SELECT i.*,u.name AS imported_by FROM voter_imports i JOIN app_users u ON u.id=i.imported_by_user_id ORDER BY i.id DESC LIMIT 20')],'Upload voters');break;
   case 'template': VoterService::requireManage();header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="voters-template.csv"');$out=fopen('php://output','wb');fputcsv($out,VoterService::HEADERS,',','"','');fclose($out);break;
   case 'account': View::render('account/index',[],'My account');break;
   default: throw new HttpException(404,'This page is not part of Phase 1. Use Voters or Members.');
  }
 }
 public function post(): never {
  $action=Validation::text($_POST,'_action',60);
  if($action==='activate_account'){
   try{\App\Security\AppointmentAccounts::activate($_POST);flash('success','Account activated. Sign in with your email and password.');redirect('login');}
   catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());redirect('activate_account');}
  }
  if($action==='login'){
   try {if(!Auth::login(Validation::email($_POST,'email'),(string)($_POST['password']??''))){throw new \InvalidArgumentException('Email or password is incorrect.');}redirect('dashboard');}
   catch(\InvalidArgumentException $e){flash('danger',$e->getMessage());redirect('login');}
  }
  Auth::requireLogin();if($action==='logout'){Auth::logout();redirect('login');}
  \App\Security\ExecutiveAccess::guardPost($action);
  if($action==='people_report_csv'){\App\Reporting\ReportsController::csv();}
  if($action==='security_confirm'){\App\Security\SessionSecurity::confirm();}
  \App\Security\SessionSecurity::guard($action,$_POST);
  if(in_array($action,['appointment_activation_issue','appointment_grant_save'],true)){
   Auth::requireRole(['admin']);
   try{if($action==='appointment_activation_issue'){\App\Security\AppointmentAccounts::issue(Validation::id($_POST,'id'));}else{\App\Security\AppointmentAccounts::saveGrant($_POST);}flash('success','Appointment account settings saved.');}
   catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());}
   catch(\PDOException $error){error_log((string)$error);flash('danger','No changes were saved. Refresh and try again.');}
   redirect('executive_account',['id'=>Validation::id($_POST,'id')]);
  }
  if(in_array($action,['branch_contact_save','membership_nominate','membership_decide'],true)){\App\Membership\BranchWorkflowController::post($action);}
  if($action==='executive_data_csv'){\App\Security\ExecutiveAccess::export();}
  if(in_array($action,['executive_access_save','executive_flag','review_resolve','staff_member_link'],true)){
   try{
    if($action==='staff_member_link'){\App\Security\StaffIdentity::link($_POST);}
    elseif($action==='executive_access_save'){\App\Security\ExecutiveAccessAdmin::save($_POST);}
    elseif($action==='executive_flag'){\App\Security\ExecutiveAccess::flag($_POST);}
    else{\App\Security\ExecutiveAccessAdmin::resolve($_POST);}
    flash('success','Saved successfully.');
   }catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());}
   catch(\PDOException $error){error_log((string)$error);flash('danger','Unable to save. No changes were made.');}
   if($action==='executive_flag'){redirect('voter',['id'=>Validation::id($_POST,'voter_id')]);}
   redirect(in_array($action,['executive_access_save','staff_member_link'],true)?'staff':($action==='review_resolve'?'record_reviews':'voters'));
  }
  if($action==='staff_save'){\App\Security\StaffController::save();}
  if($action==='wing_report_csv'){\App\Membership\WingController::report($_POST,true);exit;}
  if($action==='wing_save'){\App\Membership\WingController::save();}
  if(in_array($action,['executive_term_save','executive_term_assign','executive_recognition_save','executive_report_csv'],true)){\App\Executives\ToolsController::post($action);}
  if($action==='executive_service_save'){\App\Executives\LifecycleController::save();}
  if(in_array($action,['executive_appointment_create','executive_appointment_update'],true)){\App\Executives\AppointmentController::save();}
  if($action==='executive_position_save'){\App\Executives\PositionController::save();}
  if($action==='life_status_save'){
   $id=Validation::id($_POST,'id');VoterService::requireManage();
   try{\App\People\LifeStatusService::save($_POST);flash('success','Life status saved.');}
   catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());redirect('life_status',['id'=>$id]);}
   catch(\PDOException $error){error_log((string)$error);flash('danger','Status could not be saved. No changes were made.');redirect('life_status',['id'=>$id]);}
   $member=Database::scalar('SELECT id FROM members WHERE voter_record_id=?',[$id]);redirect($member?'member':'voter',['id'=>$member?:$id]);
  }
  if($action==='voter_trash'){
   $return=($_POST['mode']??'')==='delete'?'voters':'deleted_voters';
   try{flash('success',\App\Voters\TrashService::act($_POST));}
   catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());}
   catch(\PDOException $error){error_log((string)$error);flash('danger','No changes were saved. Review the latest records and try again.');}
   redirect($return);
  }
  if($action==='town_save'){$this->townAction();}
  if(in_array($action,['voters_upload','stations_upload','csv_confirm','csv_refresh','csv_cancel'],true)){$this->csvAction($action);}
  $destination=match($action){'residential_region_save'=>'residential_regions','district_save'=>'districts','location_save'=>'locations','voter_save'=>'voter','member_mark','member_create'=>'member_enroll','member_remove'=>'voter','member_update'=>'member',default=>'account'};
  $params=[];
  if(in_array($action,['residential_region_save','district_save'],true)){$params=AddressService::listingFilters($_GET,$action==='district_save');}
  if($action==='residential_region_save'&&!empty($_POST['residential_region_id'])){$params['edit']=Validation::id($_POST,'residential_region_id');}
  if($action==='district_save'&&!empty($_POST['district_id'])){$params['edit']=Validation::id($_POST,'district_id');}
  if($action==='location_save'){$params=LocationService::context($_GET);if(!empty($_POST['location_id'])){$params['edit']=Validation::id($_POST,'location_id');}}
  if(in_array($action,['voter_save','member_mark','member_create'],true)&&!empty($_POST['id'])){$params['id']=Validation::id($_POST,'id');}
  if($action==='member_update'){$params['id']=Validation::id($_POST,'membership_id');$destination='member_edit';}
  if($action==='member_remove'){$params['id']=Validation::id($_POST,'voter_record_id');}
  try {
   switch($action){
    case 'residential_region_save': AddressService::saveRegion($_POST);unset($params['edit'],$_SESSION['residential_region_form']);flash('success','Residential region saved.');break;
    case 'district_save': AddressService::saveDistrict($_POST);unset($params['edit'],$_SESSION['district_form']);flash('success','District saved.');break;
    case 'location_save': if(!LocationService::ready()){throw new \InvalidArgumentException('Import database/02_locations_mysql8.sql in phpMyAdmin first.');}LocationService::save($_POST);unset($params['edit'],$_SESSION['location_form']);flash('success','Location saved.');break;
    case 'voter_save': $params=['id'=>VoterService::save($_POST,$_FILES['photo']??null)];unset($_SESSION['voter_form']);flash('success','Voter saved.');break;
    case 'member_mark': VoterService::requireManage();redirect('member_enroll',['id'=>Validation::id($_POST,'id')]);
    case 'member_create': $member=VoterService::markMember(Validation::id($_POST,'id'),$_POST);unset($_SESSION['member_enroll_form']);$destination='member';$params=['id'=>$member];flash('success','Membership saved.');break;
    case 'member_update': VoterService::updateMember($_POST,$_FILES['photo']??null);unset($_SESSION['member_update_form'],$_SESSION['member_person_form']);flash('success','Member and shared voter details saved.');$destination='member';break;
    case 'member_remove': $removed=VoterService::removeMember($params['id'],Validation::id($_POST,'membership_id'));flash('success',$removed?'Membership removed. The voter record has been kept.':'This membership was already removed. The voter record has been kept.');break;
    case 'password_change': $this->password();flash('success','Password updated.');break;
    default: throw new HttpException(400,'Unknown action.');
   }
  }catch(\InvalidArgumentException $e){$this->remember($action);flash('danger',$e->getMessage());}
  catch(\PDOException $e){$this->remember($action);error_log((string)$e);flash('danger',($e->errorInfo[1]??0)===1062?($action==='location_save'?(($_POST['kind']??'')==='polling_station'?'This official Station ID is already in use. Open the existing station to update it.':'This name or shortname already exists under this parent. Check the directory before saving.'):(in_array($action,['member_create','member_update'],true)?'The voter ID or official party membership ID is already in use. Check both identifiers before saving.':($action==='district_save'?'This district name already exists in the selected region.':($action==='residential_region_save'?'This residential region name already exists.':'This voter ID already exists. Search the voter list and edit that record.')))):'The change could not be saved. Please check the application log.');}
  if($action==='voter_save'&&!empty($params['id'])){
   if(isset($_SESSION['voter_form'])){$params['edit']=1;}
   $member=Database::scalar('SELECT id FROM members WHERE voter_record_id=?',[$params['id']]);
   if($member){unset($_SESSION['voter_form']);$destination='member_edit';$params=['id'=>(int)$member];}
  }
  if($action==='member_update'&&!Database::scalar('SELECT id FROM members WHERE id=?',[$params['id']])){
   unset($_SESSION['member_update_form'],$_SESSION['member_person_form']);
   $person=!empty($_POST['voter_record_id'])?Database::scalar('SELECT id FROM voters WHERE id=?',[Validation::id($_POST,'voter_record_id')]):null;
   $destination=$person?'voter':'members';$params=$person?['id'=>(int)$person]:[];
  }
  redirect($destination,$params);
 }
 private function townAction(): never {
  VoterService::requireManage();$id=Validation::id($_POST,'id');
  try{
   $merged=TownService::save($_POST);unset($_SESSION['town_form'],$_SESSION['town_merge']);
   flash('success',$merged?'Duplicate towns merged. All linked voters and members now use the existing town.':'Town saved. Linked voter and member residential details are synchronized.');
   redirect('residential_towns');
  }catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());}
  catch(\PDOException $error){
   error_log((string)$error);
   flash('danger',($error->errorInfo[1]??0)===1062?'The town directory changed while saving. No records were changed. Review and save again to merge with the current matching town.':'The town could not be saved. No records were changed. Review the current details and try again.');
  }
  if(!Database::scalar('SELECT id FROM residential_towns WHERE id=?',[$id])){unset($_SESSION['town_form']);redirect('residential_towns');}
  $values=[];foreach(['id','name','version','residential_region_filter','residential_district_filter'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,180);}}
  $_SESSION['town_form']=$values;redirect('residential_town_edit',['id'=>$id]);
 }
 private function csvAction(string $action): never {
  VoterService::requireManage();$token=null;$return='imports';$params=[];
  try{
   if(in_array($action,['voters_upload','stations_upload'],true)){
    $station=$action==='stations_upload';$return=$station?'station_imports':'imports';
    $options=$station?['parent_id'=>Validation::id($_POST,'parent_id')]:['update'=>isset($_POST['update_existing'])];
    if($station){$params=['parent_id'=>$options['parent_id']];}
    $token=CsvPreviewService::stage($station?'stations':'voters',$_FILES['file']??[],$options);
    redirect('csv_preview',['token'=>$token]);
   }
   $token=Validation::text($_POST,'token',64);$draft=CsvPreviewService::load($token);
   $return=$draft['kind']==='stations'?'station_imports':'imports';$params=$draft['kind']==='stations'?['parent_id'=>$draft['options']['parent_id']]:[];
   if($action==='csv_cancel'){CsvPreviewService::discard($token);flash('info','Upload cancelled. No rows were imported.');redirect($return,$params);}
   if($action==='csv_refresh'){CsvPreviewService::refresh($token);flash('info','Preview refreshed against the latest database records. Nothing has been imported.');redirect('csv_preview',['token'=>$token]);}
   $result=CsvPreviewService::confirm($token,Validation::text($_POST,'revision',32),isset($_POST['exclude_issues']));
   $counts=$result['counts'];
   flash('success',$counts['added'].' added, '.($counts['updated']??0).' updated, '.$counts['skipped'].' skipped. Reviewed import completed.');
   redirect($return,$params);
  }catch(\InvalidArgumentException $error){flash('danger',$error->getMessage());}
  catch(\PDOException $error){error_log((string)$error);flash('danger','The import could not be completed. No rows were imported. Refresh the preview and review any conflicts.');}
  if($token){
   try{CsvPreviewService::load($token);redirect('csv_preview',['token'=>$token]);}
   catch(\InvalidArgumentException $error){/* Expired/completed previews return to the upload form. */}
  }
  redirect($return,$params);
 }
 private function remember(string $action): void {
  if($action==='residential_region_save'){$values=[];foreach(['residential_region_id','name','version'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,160);}}$_SESSION['residential_region_form']=$values;return;}
  if($action==='district_save'){$values=[];foreach(['district_id','region_id','name','version'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,180);}}$_SESSION['district_form']=$values;return;}
  if($action==='member_create'){
   $values=[];foreach(array_merge(['id','joined_on','status','official_membership_id'],MemberProfileService::fields()) as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,500);}}$_SESSION['member_enroll_form']=$values;return;
  }
  if($action==='member_update'){
   $values=[];foreach(array_merge(['membership_id','joined_on','status','official_membership_id','membership_version','voter_version','member_person_present','voter_record_id'],MemberProfileService::fields()) as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,500);}}$_SESSION['member_update_form']=$values;
   if(isset($_POST['member_person_present'])){
    $person=[];foreach(array_merge(VoterService::HEADERS,['town_choice','full_name','voter_version','membership_version','membership_id','voter_record_id','region_id','constituency_id','polling_station_id']) as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$person[$field]=mb_substr((string)$_POST[$field],0,1000);}}
    $_SESSION['member_person_form']=Crypto::encrypt(json_encode($person,JSON_THROW_ON_ERROR));
    if(isset($_POST['remove_photo'])||($_FILES['photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){flash('info','Select any photo replacement or removal again before retrying. Your other entered details have been kept.');}
   }
   return;
  }
  if($action==='location_save'){
   $values=[];foreach(['kind','location_id','name','shortname','station_code','parent_id','version'] as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,250);}}
   if(($values['kind']??'')!=='assignment'){$_SESSION['location_form']=$values;}return;
  }
  if($action!=='voter_save'){return;}$values=[];
  if(isset($_POST['remove_photo'])||($_FILES['photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){flash('info','Select any photo replacement or removal again before retrying. Your other entered details have been kept.');}
  foreach(array_merge(VoterService::HEADERS,['town_choice','full_name','id','voter_version','region_id','constituency_id','electoral_area_id','polling_station_id']) as $field){if(isset($_POST[$field])&&is_scalar($_POST[$field])){$values[$field]=mb_substr((string)$_POST[$field],0,1000);}}
  $_SESSION['voter_form']=Crypto::encrypt(json_encode($values,JSON_THROW_ON_ERROR));
 }
 private function password(): void {
  \App\Security\SessionSecurity::changePassword($_POST);
 }
}
