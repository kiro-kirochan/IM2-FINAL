<?php
// Soft delete: marks a record as archived (is_deleted = 1). Nothing is removed from MySQL.
require_once __DIR__ . '/../includes/helpers.php';
require_post();

$entity = post('entity');
$id = post('id');
if ($entity !== '' && $id !== '' && set_deleted($entity, $id, 1)) {
    flash('ok', 'Archived. It is hidden from the app but kept in the database — restore it any time from the Archive page.');
}
redirect(safe_return(post('return'), $entity . '/'));

function safe_return(string $r, string $fallback): string
{
    return ($r !== '' && preg_match('#^[A-Za-z0-9_\-./?=&]+$#', $r) && !str_contains($r, '..') && !str_starts_with($r, '/') && !str_contains($r, '//'))
        ? $r : $fallback;
}
