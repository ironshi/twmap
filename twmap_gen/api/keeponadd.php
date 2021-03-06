<?php


$id = $_REQUEST['id'];
$tm = $_REQUEST['tm'];
$url = $_REQUEST['url'];
$cp = $_REQUEST['cp'];
$title = $_REQUEST['title'];


require_once("../config.inc.php");
if (empty($id) || empty($tm) || empty($url) || empty($cp)  || empty($title)) {
	ajaxerr("1:not enough parameters");
}

// 這是地圖產生器跟 keepon 間的祕密, 這段就免看了
$check = intval($tm{0}) * $keepon_magic_1 + intval($tm{1}) + intval($tm{2}) * $keepon_magic_2 + intval($tm{3}) + intval($tm{4}) * $keepon_magic_3 + intval($tm{5}) + $keepon_magic_4 - strlen(urldecode($url));

if ($cp != $check) {
	ajaxerr("2:checksum error");
}
$uid = 1;
if ($url=='delete') {
	$result = keepon_map_exists($uid, $id);
	if ($result === false) {
			ajaxerr("5:map not exists");
	}
	if (map_del($result['mid'])) {
		  ajaxok("刪除完成");
	} else {
		  ajaxerr("6:map delete fail,please report");
	}
}

// 1. 先抓取 gpx 檔案
$tmp_gpx = tempnam("/tmp","GPX") . ".gpx";
try {
	$data = request_curl($url);
	$TODO = $_REQUEST;
	$TODO['gpx'] = $tmp_gpx;
	$url_parts = parse_url($url);
	if (preg_match("/gdb$/i",$url_parts['path'])) {
		$tmp_gdb = tempnam("/tmp","GDB") . ".gdb";
		file_put_contents($tmp_gdb, $data);
		$cmd=sprintf("/usr/bin/gpsbabel -i gdb -o gpx -f %s -F %s",$tmp_gdb, $tmp_gpx);
		exec($cmd,$out,$ret);
		if ($ret != 0 ) {
			@unlink($tmp_gdb);
			ajaxerr("4:unsupported format?[gdb fail to convert to gpx]");
		}
	} else {
		file_put_contents($tmp_gpx, $data);
	}

} catch (Exception $e) {
	ajaxerr("3:unable to download gpx");
}
// 1.1 把參數抓出來
$svg = new gpxsvg(array("gpx"=>$tmp_gpx, "width"=>1024, "fit_a4" => 1,
	"show_label_trk" => 0, "show_label_wpt" => 2));
$ret = $svg->process();
// msglog("svg get_bound processed");
if ($ret === false ) {
	@unlink($tmp_gpx);
	ajaxerr("4:unsupported format?");
}

$TODO['startx'] = $svg->bound_twd67['tl'][0]/1000;
$TODO['starty'] = $svg->bound_twd67['tl'][1]/1000;
$TODO['shiftx'] = ($svg->bound_twd67['br'][0] - $svg->bound_twd67['tl'][0])/1000;
$TODO['shifty'] = ($svg->bound_twd67['tl'][1] - $svg->bound_twd67['br'][1])/1000;
$TODO['ph'] = $svg->bound_twd67['ph'];

// 2. 加入 queue
require_once("../lib/memq.inc.php");

$ret = MEMQ::enqueue("keepon", $TODO);
if ($ret === FALSE) 
	ajaxerr("system error");

$ret = array("time" => time(), "msg" => "accepted");
ajaxok($ret);

function formatreq($param) {
  global $keepon_magic_1, $keepon_magic_2, $keepon_magic_3, $keepon_magic_4;
	$tm =  $param['tm'];
	$url = $param['url'];
	$check = intval($tm{0}) * $keepon_magic_1 + intval($tm{1}) + intval($tm{2}) *$keepon_magic_2 + intval($tm{3}) + intval($tm{4}) * $keepon_magic_3 + intval($tm{5}) + $keepon_magic_4 - strlen($url);

	$param['cp'] = $check;

	return http_build_query($param);

}
