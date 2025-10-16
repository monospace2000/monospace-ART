<?php
// ----------------------------
// CONFIG
// ----------------------------
$logFile = __DIR__ . '/stats.log'; // where visits are stored
$ignore_ips = ['69.121.111.123'];  // your IP
$ignore_cookie = 'ignoreStats';
$ignore_param  = 'ignoreStats';

// ----------------------------
// GET VISITOR INFO
// ----------------------------
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
$referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
$page     = $_SERVER['REQUEST_URI'] ?? 'UNKNOWN';
$timestamp = date('Y-m-d H:i:s');

// ----------------------------
// CHECK IF SHOULD IGNORE
// ----------------------------
if (
    in_array($ip, $ignore_ips) || 
    (isset($_COOKIE[$ignore_cookie]) && $_COOKIE[$ignore_cookie] === '1') || 
    (isset($_GET[$ignore_param]) && $_GET[$ignore_param] === '1')
) {
    exit; // skip logging
}

// ----------------------------
// LOG VISIT
// ----------------------------
$line = json_encode([
    'ip' => $ip,
    'referrer' => $referrer,
    'page' => $page,
    'time' => $timestamp
]) . PHP_EOL;

file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

// Optional: send 1x1 transparent GIF so it can be included as an <img>
header('Content-Type: image/gif');
echo base64_decode(
    'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='
);
exit;
