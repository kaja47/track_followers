<?php

date_default_timezone_set('Europe/Prague');

if (!isset($argv[1])) exit;

$user = strtolower($argv[1]);

$userLog = @file("followers_$user"); 
if ($userLog === false) {
  echo "No record for user `$user'\n";
  return;
}

$followers = $result = array();
foreach ($userLog as $l) {
  list($date, $ids) = explode(':', trim($l));
  $ids = explode(',', $ids);

  if (reset($ids) === 'diff') {
    array_shift($ids);
    foreach ($ids as $signId) {
      $id = (int) substr($signId, 1);
      if ($signId[0] === '+') {
        $result[$date]['+'][] = $id;
        $followers[] = $id;

      } else if ($signId[0] === '-') {
        $result[$date]['-'][] = $id;
        $key = array_search($id, $followers, true);
        unset($followers[$key]);

      } else throw new Exception("Invalid diff format");
    }
  } else {
    // snapshot format
    // compute diff with last set of ids
    $result[$date] = array(
      '-' => array_diff($followers, $ids),
      '+' => array_diff($ids, $followers),
    );
    $followers = $ids;
  }
}

array_shift($result);

$users = array();
$usersData = @file_get_contents('users');
if ($usersData) {
  foreach (explode(',', trim($usersData)) as $pair) {
    list($id, $u) = explode(':', $pair);
    $users[$id] = $u;
  }
}

function getUser($id, $users) {
  if (isset($users[$id])) return $users[$id];
  else return "<unknown> id: $id";
}

foreach ($result as $date => $d) {
  if (isset($d['-']))
    foreach ($d['-'] as $u) { echo "$date unfollowed by ", getUser($u, $users), "\n"; }

  if (isset($d['+']))
    foreach ($d['+'] as $u) { echo "$date followed by ",   getUser($u, $users), "\n"; }
}
