<?php

date_default_timezone_set('Europe/Prague');

if (!isset($argv[1])) exit;

$user = strtolower($argv[1]);

$users = [];
$usersData = @file_get_contents('users');
if ($usersData) {
  foreach (explode(',', trim($usersData)) as $pair) {
    list($id, $u) = explode(':', $pair);
    $users[$id] = $u;
  }
}

$data = file("followers_$user");

$followers = [];
foreach ($data as $d) {
  list($date, $ids) = explode(':', trim($d));
  $followers[$date] = explode(',', $ids);
}

$lastIds = null;
$result = [];
foreach ($followers as $date => $ids) {
  if ($lastIds !== null) {
    $result[$date] = [
      '-' => array_diff($lastIds, $ids),
      '+' => array_diff($ids, $lastIds),
    ];
  }
  $lastIds = $ids;
}

$messages = [];
foreach ($result as $date => $d) {
  foreach ($d['-'] as $u) { $messages[] = "$date unfollowed by ${users[$u]}"; }
  foreach ($d['+'] as $u) { $messages[] = "$date followed by ${users[$u]}"; }
}

foreach ($messages as $m) echo $m, "\n";
