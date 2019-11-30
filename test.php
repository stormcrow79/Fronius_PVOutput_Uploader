<?php

  // Define Date & Time
  date_default_timezone_set("Australia/Perth");
  $system_time= time();
  $year = date('Y', time());
  $date = date('Ymd', time());
  $time = date('H:i', time());

  //$slug = expand_tilde("~/projects/solar/data/") . date("Ymd-Hi");
  $dataFolder = "~/projects/solar";
  $slug = expand_tilde($dataFolder."/data/".$year."/inverter-").date("Ymd-Hi");

  echo $slug;

  return;

  $json = file_get_contents("https://randomuser.mex/api");
  if ($json === FALSE) {
    $err = error_get_last();
    file_put_contents($slug . ".err", $err["message"]);
  } else {
    file_put_contents($slug . ".json", $json);
  }

  function expand_tilde($path)
  {
      if (function_exists('posix_getuid') && strpos($path, '~') !== false) {
          $info = posix_getpwuid(posix_getuid());
          $path = str_replace('~', $info['dir'], $path);
      }
  
      return $path;
  }

?>