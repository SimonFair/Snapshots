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

function make_button($text) {
#	global $paths, $Preclear , $loaded_vhci_hcd, $usbip_cmds_exist ;

	$button = "<span><button  class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

#	if ($loaded_vhci_hcd == "0")
#		{
			$disabled = "disabled <a href=\"#\" title='"._("vhci_hcd module not loaded")."'" ;
	#	} else {
	#		$disabled = "enabled"; 
	#	}

	$context = "disk";
  $texts = _($text) ;
	$button = sprintf($button, "", 'attach', $disabled, 'fa fa-import', $texts);
	
	return $button;
}

$unraid = parse_plugin_cfg("dynamix",true);
$display = $unraid["display"];
#global $btrfs_path, $btrfs_uuid ;
switch ($_POST['table']) {
// sv = BTRFS Volumes Tab Tables  
// it = Initiator Tab Tables
// st = Status Tab Tables
// ft = Fileio Tab Tables
// lt = LUN Tab Tables 
// xt = Diag Tables
case 'sv1':
    exec(' df -t btrfs --output="target" ',$targetcli);
    foreach ($targetcli as $line) {
            if ($line == "/etc/libvirt" || $line == "/var/lib/docker" ||$line == "Mounted on") continue ;
             echo "<tr><td> Volume:".preg_replace('/\]  +/',']</td><td>Test</td><td>',$line)."</td></td><td><td>".make_button("Create Subvolume")."</td></td><td></tr>";
             
             
        
             build_volume($line) ;
            # echo "<tr><td>" ;var_dump( $btrfs_volumes) ;echo "</td></tr>" ;
             
             
             foreach ($btrfs_volumes[$line] as $key=>$vline) {
             # echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$vline)."</td></tr>";
              echo "<tr><td>       Subvolume: ".$key."</td><td></td><td>".make_button("Delete Subvolume")." ".make_button("Create Snapshot").' Read Only:<input type="checkbox" value=""></td></tr>';
              foreach ($btrfs_volumes[$line][$key]["snapshots"] as $snapshot) {
             
                   echo "<tr><td>          Snapshot: ".$snapshot."</td><td></td><td>".make_button("Delete Snapshot")."</td><td></td></tr>"; }
             }
            # echo "<tr><td>" ;
            # var_dump($volume) ;
            # echo "</td></tr>" ;
             #if ($volume == "" ) {echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',"No Volumes")."</td></tr>";}
       }
  break;

}
