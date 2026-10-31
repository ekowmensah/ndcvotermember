<?php use App\Auth; ?>
<div class="member-workspace">
<header class="page-heading member-heading"><div><a class="back-link" href="<?= e(url('members')) ?>"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i>Back to members</a><span class="section-kicker d-block mt-2">MEMBER PROFILE</span><h1><?= e($voter['full_name']) ?></h1><p>Membership, personal information and location in one place.</p></div><div class="d-flex flex-wrap gap-2 no-print"><button type="button" class="btn btn-outline-secondary" data-member-print><i class="bi bi-printer me-2" aria-hidden="true"></i>Print profile</button><?php if($canManage): ?><a class="btn btn-primary" href="<?= e($editUrl) ?>"><i class="bi bi-pencil-square me-2" aria-hidden="true"></i>Edit member</a><?php endif; ?></div></header>
<?php require \App\View::path('shared/people/partials/life_status_notice'); ?>
<div class="member-layout">
<aside class="member-sidebar" aria-label="Member summary"><?php require \App\View::path('members/partials/identity'); ?></aside>
<div class="member-main">
<nav class="member-section-nav no-print" aria-label="Member profile sections"><a href="#member-overview">Overview</a><a href="#member-residence">Residence</a><a href="#member-electoral">Electoral location</a><a href="#member-background">Background</a><a href="#member-wings">Wings</a><?php if(\App\Executives\AppointmentService::ready()): ?><a href="#member-executive">Executive roles</a><a href="#member-executive-history">Service timeline</a><?php endif; ?><?php if($canManage): ?><a href="<?= e($editUrl) ?>">Edit member</a><?php endif; ?></nav>
<?php require \App\View::path('members/partials/overview'); ?>
<?php require \App\View::path('members/partials/background'); ?>
<?php require \App\View::path('membership/wings/partials/member'); ?>
<?php require \App\View::path('members/partials/executive_appointments'); ?>
<?php require \App\View::path('members/partials/executive_history'); ?>
<?php if($canManage): require \App\View::path('members/partials/management'); endif; ?>
</div>
</div>
</div>
