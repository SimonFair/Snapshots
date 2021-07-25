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
global $btrfs_path, $btrfs_line ;
switch ($_POST['table']) {
// sv = BTRFS Volumes Tab Tables  
// it = Initiator Tab Tables
// st = Status Tab Tables
// ft = Fileio Tab Tables
// lt = LUN Tab Tables 
// xt = Diag Tables
case 'sv1':
        #exec(' cat /mnt/cache/appdata/snapcmd/dflist ',$targetcli);
        exec(' df -t btrfs --output="target" ',$targetcli);
            $list=build_list($targetcli) ;
                # echo "<tr><td>" ;var_dump( $btrfs_volumes) ;echo "</td></tr>" ;

                foreach ($list as $key=>$vline) {
                  echo "<tr><td> Volume:".preg_replace('/\]  +/',']</td><td>Test</td><td>',$key)."</td></td><td><td>".make_button("Create Subvolume")."</td></td><td></tr>";
             
                  foreach ($vline as $snap=>$snapdetail) {
                  # echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$vline)."</td></tr>";
               if ($snapdetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
               if ($snapdetail["snap"] == false) {
                  echo "<tr><td>       Subvolume: ".$snap.'</td><td>  Read Only:<input type="checkbox" '.$checked.' value="">'."</td><td>".make_button("Delete Subvolume")." ".make_button("Create Snapshot").'</td></tr>';
               } else {
                  echo "<tr><td>          Snapshot: ".$snap.'</td><td>  Read Only:<input type="checkbox"'.$checked.' value="">'."</td><td>".make_button("Delete Snapshot").'</td></tr>'; 
               }
     
              }}
       #echo "<tr><td>" ;
       #var_dump($btrfs_paths) ;
       #echo "</td></tr>" ;
       break;

       case 'db1':
        
       # exec(' cat /mnt/cache/appdata/snapcmd/dflist ',$targetcli);
        exec(' df -t btrfs --output="target" ',$targetcli);
            $list=build_list($targetcli) ;
      
           echo "<tr><td>" ;
           var_dump($list) ;
           echo "</td></tr>" ;
           break;
    
}
