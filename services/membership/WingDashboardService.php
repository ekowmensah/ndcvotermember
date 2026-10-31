<?php
declare(strict_types=1);
namespace App\Membership;
use App\{Auth,Database};
use App\Executives\{OrganizationService,HistoryService};

final class WingDashboardService
{
 public static function overview(array $scope): array {
  Auth::requireLogin();
  if(!WingService::ready()){return ['available'=>false];}
  $filters=[];$params=[];$where='v.id IS NOT NULL AND '.\App\Voters\TrashService::active();
  WingScopeService::apply($scope,$filters,$where,$params);
  $from=' FROM member_wing_enrollments w JOIN voters v ON v.id=w.voter_record_id LEFT JOIN members m ON m.id=w.member_id';
  $wings=[];
  foreach(WingService::WINGS as $wing=>$label){
   $review=WingEligibilityService::reviewSql($wing);
   $ageReview=$wing==='youth'?WingEligibilityService::agedOutSql():'0';
   $counts=Database::one('SELECT COALESCE(SUM(w.ended_on IS NULL),0) AS enrolled,COALESCE(SUM(w.ended_on IS NOT NULL),0) AS ended,COALESCE(SUM(w.ended_on IS NULL AND '.$review.'),0) AS needs_review,COALESCE(SUM(w.ended_on IS NULL AND '.$ageReview.'),0) AS aged_out'.$from.' WHERE '.$where.' AND w.wing_code=?',[...$params,$wing]);
   $candidateParams=[];$candidateWhere=WingEligibilityService::candidateCondition($wing,$candidateParams);
   $candidateWhere.=' AND '.$where;array_push($candidateParams,...$params);
   $eligible=(int)Database::scalar('SELECT COUNT(*) FROM members m JOIN voters v ON v.id=m.voter_record_id WHERE '.$candidateWhere,$candidateParams);
   $executives=OrganizationService::ready()?HistoryService::dashboard($filters+['organization_scope'=>$wing]):['available'=>false];
   $wings[$wing]=['label'=>$label,'counts'=>array_map('intval',$counts??[]),'eligible'=>$eligible,'executives'=>$executives];
  }
  return ['available'=>true,'wings'=>$wings,'scope'=>$filters];
 }
}
