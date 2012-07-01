<?php

function replayFile($file) {
  $followers = array();
  $lines = @file($file);
  if ($lines === false)
    return array();

  foreach ($lines as $l) {
    list($date, $ids) = explode(':', trim($l));
    $ids = explode(',', $ids);
    if (reset($ids) === 'diff') {
      array_shift($ids);
      foreach ($ids as $signId) {
        $id = (int) substr($signId, 1);
        if ($signId[0] === '+')      $followers[$id] = $id;
        else if ($signId[0] === '-') unset($followers[$id]);
        else throw new Exception("Invalid diff format");
      }
    } else { // snapshot
      $followers = array_combine($ids, $ids);
    }
  }
  return array_values($followers);
}

date_default_timezone_set('Europe/Prague');

array_shift($argv);

if (empty($argv)) {
  echo "No arguments passed. Fetching users from file 'track.list'\n";
  if (file_exists('track.list'))
    $argv = array_filter(array_map('trim', file('track.list')), function ($u) { return $u[0] !== '#'; });
}

$rateLimit = json_decode(file_get_contents('https://api.twitter.com/1/account/rate_limit_status.json'))->remaining_hits;

$allFollowersIds = array();
foreach ($argv as $arg) {
  $user = strtolower($arg);
  echo $user, "\n";

  $followersIds = $users = array();

  $cursor = '-1';
  do {
    if ($rateLimit <= 0) {
      echo "Rate limit exceeded, there's not much to do. See you in one hour or behind proxy.\n";
      exit;
    }

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
    $allFollowersIds = array_merge($allFollowersIds, $data->ids);
  } while ($cursor); // if there isnt't any page, next_cursor_str is "0"

  // diff format
  $knownFollowers = replayFile("followers_$user");

  $unfollow = array_diff($knownFollowers, $followersIds);
  $follow   = array_diff($followersIds, $knownFollowers);

  $unfollow = array_map(function ($i) { return '-'.$i; }, $unfollow);
  $follow   = array_map(function ($i) { return '+'.$i; }, $follow);

  $data = date('Y-m-d H-i-s').':'.implode(',', array_merge(array('diff'), $follow, $unfollow))."\n";

  // snapshot format
  //$data = date('Y-m-d H-i-s').':'.implode(',', $followersIds)."\n";

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

// fetch names of yet unknown users
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
$usersData = array();
foreach ($users as $id => $name) {
  $usersData[] = ($id . ":" . $name);
}
file_put_contents('users', implode(',', $usersData));
