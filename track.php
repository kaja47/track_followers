<?php

date_default_timezone_set('Europe/Prague');

array_shift($argv);

if (empty($argv)) {
  echo "No arguments passed. Fetching users from file 'track.list'\n";
  if (file_exists('track.list'))
    $argv = array_map('trim', file('track.list'));
}

$rateLimit = json_decode(file_get_contents('https://api.twitter.com/1/account/rate_limit_status.json'))->remaining_hits;

$allFollowersIds = [];
foreach ($argv as $arg) {
  $user = strtolower($arg);
  echo $user, "\n";

  $followersIds = $users = [];

  $cursor = '-1';
  do {
    echo "fetching followers\n";
    $url = "http://api.twitter.com/1/followers/ids.json?screen_name=$user&cursor=$cursor";
    $data = file_get_contents($url);
    $rateLimit--;

    if ($data === false) // response from server is fucked up, aborting this user
      continue 2;

    $data = json_decode($data);
    if (!$data)
      continue 2;

    $cursor = $data->next_cursor_str;
    $followersIds = array_merge($followersIds, $data->ids);
    $allFollowersIds = array_merge($allFollowersIds, $followersIds);
  } while ($cursor);

  $data = date('Y-m-d H-i-s').':'.implode(',', $followersIds)."\n";
  file_put_contents("followers_$user", $data, FILE_APPEND);
}


// load known users
$u = @file_get_contents('users');
if ($u) {
  foreach (explode(',', trim($u)) as $pair) {
    list($id, $user) = explode(':', $pair);
    $users[$id] = $user;
  }
}

echo "rate limit: $rateLimit\n";

// fetch names of unknown users
$unknownFollowersIds = array_diff($allFollowersIds, array_keys($users));
$idChunks = array_chunk($unknownFollowersIds, 100);
$idChunks = array_slice($idChunks, 0, $rateLimit);

echo "total: ".count($allFollowersIds)." unknown: ".count($unknownFollowersIds)."\n";

foreach ($idChunks as $chunk) {
  $url = "http://api.twitter.com/1/users/lookup.json?user_id=".implode(',',$chunk);
  echo "fetching users\n";
  $newUsersData = json_decode(file_get_contents($url));
  if ($newUsersData) {
    foreach ($newUsersData as $u) 
      $users[$u->id_str] = $u->screen_name;
  }
}

// save users back
$usersData = [];
foreach ($users as $id => $name) {
  $usersData[] = ($id . ":" . $name);
}
file_put_contents('users', implode(',', $usersData));
