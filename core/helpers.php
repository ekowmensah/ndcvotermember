<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function base_url(string $path = ''): string
{
    return App\Config::baseUrl() . ($path === '' ? '' : '/' . ltrim($path, '/'));
}
function url(string $page = 'dashboard', array $params = []): string
{
    return base_url('index.php') . '?' . http_build_query(['page' => $page] + $params);
}
function asset(string $path): string { return base_url('assets/' . ltrim($path, '/')); }
function redirect(string $page, array $params = []): never { header('Location: ' . url($page, $params), true, 303); exit; }
function csrf(): string { return '<input type="hidden" name="_token" value="' . e(App\Auth::csrf()) . '">'; }
function flash(string $type, string $message): void { $_SESSION['flashes'][] = ['type' => $type, 'message' => $message]; }
function number(mixed $value): string { return number_format((float)($value ?? 0)); }
function label(string $value): string { return ucwords(str_replace('_', ' ', $value)); }
function badge(string $value): string
{
    $style = match ($value) {
        'active','approved','completed','published','valid','registered','succeeded','substantiated' => 'success',
        'failed','rejected','terminated','revoked','invalid' => 'danger',
        'pending','submitted','under_review','open','draft','uploaded','suspended','in_progress' => 'warning',
        default => 'secondary',
    };
    return '<span class="badge badge-soft-' . $style . '">' . e(label($value)) . '</span>';
}
function date_display(?string $value, bool $time = false): string
{
    return $value ? date($time ? 'd M Y, H:i' : 'd M Y', strtotime($value)) : '—';
}
function input(string $name, string $label, mixed $value = '', string $type = 'text', bool $required = false, string $extra = ''): string
{
    static $counter=0; $id='field-'.(++$counter).'-'.$name;
    return '<div class="mb-3"><label class="form-label" for="' . e($id) . '">' . e($label) . ($required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '') . '</label><input class="form-control" id="' . e($id) . '" name="' . e($name) . '" type="' . e($type) . '" value="' . e($value) . '" ' . ($required ? 'required ' : '') . $extra . '></div>';
}
function select_field(string $name, string $title, array $options, mixed $selected = '', bool $required = false): string
{
    static $counter=0; $id='select-'.(++$counter).'-'.$name;
    $out = '<div class="mb-3"><label class="form-label" for="' . e($id) . '">' . e($title) . '</label><select class="form-select" id="' . e($id) . '" name="' . e($name) . '" ' . ($required ? 'required' : '') . '>';
    foreach ($options as $key => $text) { $out .= '<option value="' . e($key) . '" ' . ((string)$key === (string)$selected ? 'selected' : '') . '>' . e($text) . '</option>'; }
    return $out . '</select></div>';
}
function status_options(array $values): array { return array_combine($values, array_map('label', $values)); }
function pagination(int $total, int $perPage = 25): void
{
    $current = max(1, (int)($_GET['p'] ?? 1)); $last = max(1, (int)ceil($total / $perPage));
    echo '<div class="table-pagination"><span>' . number($total) . ' records · Page ' . $current . ' of ' . $last . '</span><div class="btn-group">';
    foreach (['Previous' => $current - 1, 'Next' => $current + 1] as $text => $p) {
        $query = $_GET; unset($query['page']); $query['p'] = $p;
        echo '<a class="btn btn-sm btn-light ' . ($p < 1 || $p > $last ? 'disabled' : '') . '" href="' . e(url($_GET['page'] ?? 'dashboard', $query)) . '">' . $text . '</a>';
    }
    echo '</div></div>';
}


function member_identifier(array $person): string
{
    $official = trim((string)($person['official_membership_id'] ?? ''));
    return $official !== '' ? $official : (string)($person['member_number'] ?? '');
}
function member_identifier_label(array $person): string
{
    return trim((string)($person['official_membership_id'] ?? '')) !== '' ? 'Party ID' : 'Sys No.';
}