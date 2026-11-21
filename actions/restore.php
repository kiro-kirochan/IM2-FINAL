<?php
// Restores an archived record (is_deleted = 0).
require_once __DIR__ . '/../includes/helpers.php';
require_post();

$entity = post('entity');
$id = post('id');
if ($entity !== '' && $id !== '' && set_deleted($entity, $id, 0)) {
    flash('ok', 'Restored.');
}
$r = post('return', 'trash/');
redirect(($r !== '' && preg_match('#^[A-Za-z0-9_\-./?=&]+$#', $r) && !str_contains($r, '..') && !str_starts_with($r, '/') && !str_contains($r, '//')) ? $r : 'trash/');
