<?php if(\App\People\LifeStatusService::ready()): ?>
<?php if(!empty($voter['is_deceased'])): ?><div class="alert alert-secondary" role="status"><strong>Deceased</strong><?= !empty($voter['date_of_death'])?' · '.e(date_display($voter['date_of_death'])):'' ?>. Record and membership details are retained. <?php if(\App\Auth::canManage()): ?><a href="<?= e(url('life_status',['id'=>$voter['id']])) ?>">Review / correct status</a><?php endif; ?></div>
<?php elseif(\App\Auth::canManage()): ?><p class="small text-end no-print"><a href="<?= e(url('life_status',['id'=>$voter['id']])) ?>">Update life status / mark deceased</a></p><?php endif; ?>
<?php endif; ?>
