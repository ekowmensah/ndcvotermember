<?php require \App\View::path('layouts/partials/head'); ?>

<body class="layout-fixed sidebar-expand-lg bg-body-tertiary"><a class="skip-link" href="#main-content">Skip to content</a><div class="app-wrapper">
<?php require \App\View::path('layouts/partials/header');require \App\View::path('layouts/partials/sidebar'); ?>
<main class="app-main" id="main-content"><div class="app-content pt-4"><div class="container-fluid content-container">
<?php require \App\View::path('layouts/partials/flash_messages');require $viewFile; ?>

</div></div></main><?php require \App\View::path('layouts/partials/footer'); ?></div></body></html>
