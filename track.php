<?php

date_default_timezone_set('Europe/Prague');

array_shift($argv);

if (empty($argv)) {
  var_dump('No arguments passed. Fetching users from file "track.list"');
  if (file_exists('track.list'))
    $argv = array_map('trim', file('track.list'));
}
foreach ($argv as $arg) {
  $user = strtolower($arg);

  $followersIds = $users = [];

  $cursor = '-1';
  do {
    var_dump(0);
    $url = "http://api.twitter.com/1/followers/ids.json?screen_name=$user&cursor=$cursor";
    $data = json_decode(file_get_contents($url));
    $cursor = $data->next_cursor_str;
    $followersIds = array_merge($followersIds, $data->ids);
  } while ($cursor);

  $data = date('Y-m-d H-i-s').':'.implode(',', $followersIds)."\n";
  file_put_contents("followers_$user", $data, FILE_APPEND);

  // get known users
  $u = @file_get_contents('users');
  if ($u) {
    foreach (explode(',', trim($u)) as $pair) {
      list($id, $user) = explode(':', $pair);
      $users[$id] = $user;
    }
  }

  // fetch names of unknown users
  $unknownFollowersIds = array_diff($followersIds, array_keys($users));
  var_dump("total: ".count($followersIds)." unknown: ".count($unknownFollowersIds));
  $idChunks = array_chunk($unknownFollowersIds, 100);
  $idChunks = array_slice($idChunks, 0, 10);
  foreach ($idChunks as $chunk) {
    $url = "http://api.twitter.com/1/users/lookup.json?user_id=".implode(',',$chunk);
    //var_dump($url);
    var_dump('fetching users');
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

}
