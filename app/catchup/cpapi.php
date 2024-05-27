<?php

// * Copyright 2021-2024 SnehTV, Inc.
// * Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// * Created By : TechieSneh

error_reporting(0);
include "cpfunctions.php";

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$local_ip = getHostByName(php_uname('n'));
$ip_port = $_SERVER['SERVER_PORT'];
if ($_SERVER['SERVER_ADDR'] !== "127.0.0.1" || 'localhost') {
    $host_jio = $_SERVER['HTTP_HOST'];
} else {
    $host_jio = $local_ip;
}

$jio_path = $protocol . $host_jio . str_replace(" ", "%20", str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']));

header("Content-Type: application/vnd.apple.mpegurl");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Expose-Headers: Content-Length,Content-Range");
header("Access-Control-Allow-Headers: Range");
header("Accept-Ranges: bytes");

$cred = getCRED();
$jio_cred = json_decode($cred, true);
$ssoToken = $jio_cred['ssoToken'];
$access_token = $jio_cred['authToken'];
$crm = $jio_cred['sessionAttributes']['user']['subscriberId'];
$uniqueId = $jio_cred['sessionAttributes']['user']['unique'];
$device_id = $jio_cred['deviceId'];

$st = $_REQUEST['st'];
$pid = $_REQUEST['pid'];
$id = $_REQUEST['id'];
$result = substr($pid, 0, 6);
$begin = $_REQUEST['begin'];
$end = $_REQUEST['end'];

$data = [
    'stream_type' => 'Catchup',
    'channel_id' => $id,
    'programId' => $pid,
    'showtime' => $st,
    'srno' => "20$result",
    'begin' => $begin,
    'end' => $end,
];

$post_data = http_build_query($data);

$headers_api = array(
    "Host: jiotvapi.media.jio.com",
    "Content-Type: application/x-www-form-urlencoded",
    "appkey: NzNiMDhlYzQyNjJm",
    "channel_id: " . $id,
    "userid: " . $crm,
    "crmid: " . $crm,
    "deviceId: " . $device_id,
    "devicetype: phone",
    "isott: true",
    "languageId: 6",
    "lbcookie: 1",
    "os: android",
    "dm: Xiaomi 22101316UP",
    "osversion: 14",
    "srno: 240303144000",
    "accesstoken: " . $access_token,
    "subscriberid: " . $crm,
    "uniqueId: " . $uniqueId,
    "content-length: " . strlen($post_data),
    "usergroup: tvYR7NSNn7rymo3F",
    "User-Agent: okhttp/4.9.3",
    "versionCode: 331",
);

$haystacks = cUrlGetData("https://jiotvapi.media.jio.com/playback/apis/v1/geturl?langId=6", $headers_api, $post_data);
$haystack = @json_decode($haystacks);

if ($haystack->code !== 200) {
    refresh_token();
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit();
} else {
    $cookie = explode('?', $haystack->result);
    $chs = explode('/', $cookie[0]);

    if (strpos($cookie[1], "bpk-tv") !== false) {
        $cookie = explode("&_", $cookie[1]);
        $cookie = '_' . $cookie[1];
    } elseif (strpos($cookie[1], "/HLS/") !== false) {
        $cookie = explode("&_", $cookie[1])[1];
        $cookie = '_' . $cookie;
    } else {
        $cookie = $cookie[1];
    }

    $cook = bin2hex($cookie);
    $headers = jio_headers($cook, $crm, $device_id, $ssoToken, $uniqueId);

    if (strpos($cookie, "bpk-tv") !== false) {
        $hs = cUrlGetData($haystack->result, $headers);
        $hs = str_replace(['URI="', $chs[4] . '-video', $chs[4] . '-audio', '?vbegin=', '&vend=', 'cpstream.php?cid=keyframes/'], ['URI="cpstream.php?cid=', 'cpstream.php?cid=' . $chs[4] . '-video', 'cpstream.php?cid=' . $chs[4] . '-audio', '?vb=', '=', 'cpstream.php?cid=keyframes/'], $hs);
        $hs = str_replace(['cpstream.php?cid=cpstream.php?cid=', 'cpstream.php?cid='], 'cpstream.php?ck=' . $cook . '&cid=', $hs);
        print_r($hs);
    } elseif (strpos($cookie, "/HLS/") !== false) {
        $link = $chs[0] . '/' . $chs[1] . '/' . $chs[2] . '/' . $chs[3] . '/' . $chs[4];
        $data = explode("_", $chs[5]);
        $hs = cUrlGetData($haystack->result, $headers);
        $hs = str_replace($data[0], 'cpSonyAuth.php?id=' . $id . '&ck=' . $cook . '&link=' . $link . '&data=' . $data[0], $hs);
        print $hs;
    } elseif (strpos($cookie, "acl=/" . $chs[3] . "/") !== false) {
        $hs = cUrlGetData($haystack->result, $headers);
        $hs = str_replace('https://jiotvcod.cdn.jio.com/', 'cpstream.php?ck=' . $cook . '&sid=', $hs);
        print $hs;
    } else {
        http_response_code(404);
        die();
    }
}
