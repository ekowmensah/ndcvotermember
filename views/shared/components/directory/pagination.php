<div class="directory-pagination"><span><?= $pagination['total']?number(($pagination['page']-1)*$pagination['per_page']+1):'0' ?>–<?= number(min($pagination['total'],$pagination['page']*$pagination['per_page'])) ?> of <?= number($pagination['total']) ?> · Page <?= number($pagination['page']) ?> of <?= number($pagination['last']) ?></span><nav aria-label="Directory pages"><div class="btn-group">
<?php
$paginationTargets=['First'=>1,'Previous'=>$pagination['page']-1,'Next'=>$pagination['page']+1,'Last'=>$pagination['last']];
foreach($paginationTargets as $paginationText=>$paginationTarget):
$paginationDisabled=in_array($paginationText,['First','Previous'],true)?$pagination['page']<=1:$pagination['page']>=$pagination['last'];
?>
<?php if($paginationDisabled): ?><span class="btn btn-sm btn-light disabled" aria-disabled="true"><?= e($paginationText) ?></span><?php else: ?><a class="btn btn-sm btn-light" href="<?= e(url($pagination['route'],array_replace($pagination['filters'],['p'=>$paginationTarget]))) ?>"><?= e($paginationText) ?></a><?php endif; ?>
<?php endforeach; ?></div></nav></div>
