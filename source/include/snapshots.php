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

function make_button($text, $function, $entry) {
#	global $paths, $Preclear , $loaded_vhci_hcd, $usbip_cmds_exist ;

	$button = "<span><button  onclick='%s(\"%s\")' class='mount' context='%s' role='%s' %s><i class='%s'></i>%s</button></span>";

#	if ($loaded_vhci_hcd == "0")
#		{
		#	$disabled = "disabled <a href=\"#\" title='"._("vhci_hcd module not loaded")."'" ;
	#	} else {
	#		$disabled = "enabled"; 
	#	}

	$context = "disk";
   $texts = _($text) ;
	$button = sprintf($button, $function , $entry, "", 'attach', $disabled, 'fa fa-import', $texts);
   #"<button onclick='add_remote_host()'>"._('Add Remote System')."</button>";
	
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

        echo "<thead><tr><td>"._("Volume/Sub Volume/Snapshot")."<td>"._('Read only')."</td><td>"._('Remove')."</td><td>"._('Create')."</td>" ;

         echo "</tr></thead>";
         echo "<tbody><tr>";
        exec(' df -t btrfs --output="target" ',$targetcli);
            $list=build_list($targetcli) ;
                # echo "<tr><td>" ;var_dump( $btrfs_volumes) ;echo "</td></tr>" ;
                
                
                $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='Create Subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";

                foreach ($list as $key=>$vline) {
                  echo "<tr><td>".preg_replace('/\]  +/',']',$key)."</td><td></td><td></td><td>".make_button("Create Subvolume", "create_subvolume" ,$vline)."</td>tr>";
                  $ct = "<td title='"._("Remove Device configuration")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$key}\")'><i class='fa fa-remove hdd'></a>";

                  foreach ($vline as $snap=>$snapdetail) {
                  # echo "<tr><td>".preg_replace('/\]  +/',']</td><td>',$vline)."</td></tr>";
               if ($snapdetail["property"]["ro"] == "true" ) $checked = "checked" ; else $checked = "" ;
               if ($snapdetail["snap"] == false) {
                  echo "<tr><td>\t".$snap.'</td><td><input type="checkbox" '.$checked.' value="">'."</td>" ;
                  $remove = $snapdetail["vol"]."/".$snap ;
                  echo "<td title='"._("Delete Subvolume")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_subvolume(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
                  
                  echo "</td><td> ".make_button("Create Snapshot", "create_snapshot", " ").'</tr>';
               } else {
                  echo "<tr><td>\t\t".$snap.'</td><td><input type="checkbox"'.$checked.' value="">'."</td>" ;
                  $remove = $snapdetail["vol"]."/".$snap ;
                  echo "<td title='"._("Delete Snapshot")."'><a style='color:#CC0000;font-weight:bold;cursor:pointer;'  onclick='delete_snapshot(\"{$remove}\")'><i class='fa fa-remove hdd'></a>" ;
                  echo '</td><td></td></tr>'; 
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

      case 'delete_subvolume':
         $subvol = urldecode(($_POST['subvol']));
         exec('btrfs subvolume delete '.$subvol, $result, $error) ;
         #if
         snap_manager_log('btrfs subvolume delete '.$subvol.' '.$error.' '.$result[0]) ;
         echo json_encode(TRUE);
         break;

         case 'create_subvolume':
            $subvol = urldecode(($_POST['subvol']));
            exec('btrfs subvolume create '.$subvol, $result, $error) ;
            #if
            snap_manager_log('btrfs subvolume create '.$subvol.' '.$error.' '.$result[0]) ;
            echo json_encode(TRUE);
            break;

    
}

/*
function remove_vm_mapping($source) {
	$config_file = $GLOBALS["paths"]["vm_mappings"];;
	$config = @parse_ini_file($config_file, true);
	if ( isset($config[$source]) ) {
		usb_manager_log("Removing configuration '$source'.");
	}	
	unset($config[$source]);
	save_ini_file($config_file, $config);
	return (! isset($config[$source])) ? TRUE : FALSE;
	}
   */
