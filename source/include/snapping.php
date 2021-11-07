#!/usr/bin/php
<?PHP
/* Copyright 2020-2020, Simon Fairweather
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
$plugin = "snapshots";
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
$translations = file_exists("$docroot/webGui/include/Translations.php");

if ($translations) {
	/* add translations */
	$_SERVER['REQUEST_URI'] = 'snapshots';
	require_once "$docroot/webGui/include/Translations.php";
} else {
	/* legacy support (without javascript) */
	$noscript = true;
	require_once "$docroot/plugins/$plugin/include/Legacy.php";
}

require_once "plugins/snapshots/include/lib.php";
require_once("webGui/include/Helpers.php");
$arg1 = $argv[1];
$arg2 = $argv[2];
$dummyrun = false ;
$dummyrundel = true ;

/*

Start processing Snapshots read config.

Options

[/mnt/cache/snaps]
snapscheduleenabled = "yes"
snapSchedule = 1
hour1 = 13
min = 0
hour2 = "/1"
snaplogging = "yes"
snapsend = "local"
remotehost = "Ip"
snapincremental = "yes"
mastersnap = "Previous"
vmselection = "Windows 10 test 6"
hostoption = "shutdown"
shutdowntimeout = 60
Removal = "yes"
days = 10
occurences = 5
snapsendopt = "none"
cron = "0 13 * * *"

*/


$subvol = $arg1 ;
$snapshot=get_subvol_config($subvol, "default") ;
$sendshot=get_subvol_config($subvol, "sendto") ;
$readonly = true ;
$slot= $arg2 ;

#$schedule = get_subvol_schedule($subvol) ;
$schedule = get_subvol_schedule_json($subvol,$slot) ;
$logging = $schedule['snaplogging'] ;

/* is logging set 
if yes log steps, otherwise just log start/stop.
*/

# Is snapping already running.
$pidfile= '/var/run/snap'.urlencode($subvol).'.pid' ;
$pid = file_exists($pidfile) ;
if ($pid) {
	snap_manager_log('Snapping process already running for'.$arg1." Slot:".$slot ) ;
	return(-1) ;
}

snap_manager_log('Start snapping process '.$arg1." Slot:".$slot ) ;

# Write pid file
file_put_contents($pidfile, "Pid") ;

/* Process VM Options if VMs defined 
Shutdown, Suspend, Hibernate.

*/
$vms=explode(",", $schedule["vmselection"]) ;
$vm_state=array() ;
foreach($vms as $vm) {
	$vm_output=NULL ;
	exec ('virsh domstate "'.$vm.'"', $vm_output) ;
	if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_output[0]);
	$vm_state[$vm] = $vm_output[0] ;
}
var_dump($vm_state) ;
$hostoption=$schedule["hostoption"] ;
/*
foreach($vms as $vm) {
	switch($hostoption) {
		case ""	
	
	exec ('virsh domstate "'.$vm.'"', $vm_output) ;
	if ($logging == "yes") snap_manager_log("VM ".$vm.' State is :'.$vm_output[0]);
	$vm_state[$vm] = $vm_output[0] ;
}

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh suspend "Windows 11"
#Domain 'Windows 11' suspended

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh resume "Windows 11"
#Domain 'Windows 11' resumed

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh shutdown "Windows 11"
#Domain 'Windows 11' is being shutdown

#root@computenode:/usr/local/emhttp/plugins/snapshots/include# virsh start "Windows 11"
#Domain 'Windows 11' started

*/


if ($dummyrun == true)
  {
	# Process with no Actions but write logging.
	#if ($logging == "yes") snap_manager_log('Action') ;
  } else {
	# Perform Action.  
	
  }

/* Create Snapshot Readonly */

if ($readonly == "true")  $readonly = "-r" ; else $readonly="" ;
$ymd = date('YmdHi', time());
$snapshoty = str_replace("{YMD}", $ymd, $snapshot);

if ($dummyrun == true)
  {
	/* Process with no Actions but write logging.*/
	if ($logging == "yes")	snap_manager_log('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty)) ;
  } else {
	exec('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty), $result, $error) ;
	if ($logging == "yes") snap_manager_log('btrfs subvolume snapshot '.$readonly.' '.escapeshellarg($subvol).' '.escapeshellarg($snapshoty)) ;
  }

 /* Restart VMs */ 

/* Send Snapshot */

/* Send Local */
var_dump($sendshot) ;
if ($schedule["snapsend"] == "local") 
  {
		exec('btrfs send '.$snapshoty.' | btrfs receive '.$sendshot , $result, $error) ;
		snap_manager_log('btrfs snapshot send '.$snapshoty.' To '.$sendshot.' '.$error.' '.$result[0]) ;
  }

/* Send Remote */

/* Delete old snaps */

if ($schedule["Removal"]!="no") {
$parents=subvol_parents() ;
$parent=$parents[$subvol]["vol"].'/' ;
$lines[]=$parent ;
exec(' df -t btrfs --output="target" ',$df);

$list = build_list3($df) ;
#var_dump($df) ;
$list=$list[$parents[$subvol]["vol"]][$subvol]["subvolume"] ;
#var_dump($list) ;
$snaps=array_reverse($list) ;
#var_dump($snaps) ;
if ($logging == "yes") snap_manager_log('Count: '.count($snaps).' Occurences: '.$schedule["occurences"].' Days: '.$schedule["days"]) ;




if ($schedule["days"] > 0) {
	$prevdatelog = date('l jS F (Y-m-d-H-i-s)', strtotime('-'.$schedule["days"].' days'));
	$prevdate = date('Y-m-d-H-i-s', strtotime('-'.$schedule["days"].' days'));
	if ($logging == "yes") snap_manager_log('Date to remove to:'.$prevdatelog) ;
	foreach($snaps as $path=>$snap) {
		if ($snap['odate'].'-'.$snap['otime'] > $prevdate )  continue ;
        $path = $parent.$path ;
		if ($schedule["Removal"] == "dry")
  		{
			/* Process with no Actions but write logging.*/
			if ($logging == "yes") snap_manager_log('Dry Run Delete by date '.$path) ;
  		} else {
			exec('btrfs subvolume delete '.escapeshellarg($path), $result, $error) ;
			if ($logging == "yes") snap_manager_log('Deleted Snapshot by date: '.$path) ;
  		}
	}
}


$count = 0 ;


if ($schedule["occurences"] > 0)
	{
  	foreach($snaps as $path=>$snap) {
		if ($count < $schedule["occurences"] ) { $count++ ; continue ;}
        $path = $parent.$path ;
		if ($schedule["Removal"] == "dry")
  		{
			/* Process with no Actions but write logging.*/
			if ($logging == "yes") snap_manager_log('Dry Run Delete by occurence'.$path) ;
  		} else {
			exec('btrfs subvolume delete '.escapeshellarg($path), $result, $error) ;
			if ($logging == "yes") snap_manager_log('Deleted Snapshot by occurence: '.$path) ;
  		}
		  $count++ ;
	}
}
}
sleep(10) ;
unlink($pidfile) ;
snap_manager_log('End snapping process '.$arg1 ) ;
?>