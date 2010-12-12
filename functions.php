<?php
/*	Pre & post-battle and form validation functions for AACalc. */

/**
 *	name: functions.php
 *
 *	PHP version: 5.3.1
 *
 *	description: Contains functions dealing with the setup of the AACalc form and the processing of units 
 *		before the pre battle. Also deals with validating the form and units included before running a battle.
 *
 *	primary author: Daniel Rempel
 *	additional authors: Aaron Kreider, Greg Massaro
 *  
 *	Copyright 2010, Daniel Rempel, Aaron Kreider, Greg Massaro
 *
 *	AACalc version:
 *	last major revision author: Greg Massaro
 *	date of last major revision: 11/19/2010
 *
 *	This file is part of AACalc.
 *
 *	AACalc is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	AACalc is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with Foobar.  If not, see <http://www.gnu.org/licenses/>.
 * 
 */

//add current round casualties by unit to the array of total casualties by unit 
function ftool ($force1, $force2) {
	foreach ($force2 as $type => $count) {
		if (isset($force1[$type])) {$force1[$type] += $force2[$type];}
		else {$force1[$type] = $force2[$type];}
	}
	return $force1;
}

//create array of casualties by unit for a given attacking or defending force
function whatdied ($remain, $start) {
	$lost= array();
	foreach ($start as $type => $count) {
		if (isset($remain[$type]) && $remain[$type]>0) {	
			if ($count > $remain[$type]) $lost[$type]=$count-$remain[$type];
		} else { $lost[$type]=$count;}
	}
	if (can_bombard ($start) && has_land($start)) {
		if (isset($lost['Bat'])) unset ($lost['Bat']);
		if (isset($lost['Cru'])) unset ($lost['Cru']);
		if (isset($lost['Des'])) unset ($lost['Des']);
	}
	return $lost;
}

//reads posted entries and also returns whether battle legal and what kind of opening fire needs to happen.
function getformdata ($posted) { 
	global $options, $canhit, $rounds, $sides, $ool, $baseool,$mustland, $round, $abortratio, $unitspecs;
	$customOOL=array (); $valid=true;
	
	$options['tech']=((isset($_REQUEST['toggletechs']) && ($_REQUEST['toggletechs']=="Enable techs"))
		or (isset($_REQUEST['techs']) && (!isset($_REQUEST['toggletechs']) or $_REQUEST['toggletechs']!=='Disable techs'))
	);
	#debugarray ($options);
	
	if ($options['tech']) {
		$units=allunits();
	} else {
		$units=notechunits();
	}
	
	/* DO NOT NEED IF NOT STORING GAMES ANYMORE?
	#validate gameid:
	#$gameid=$_REQUEST['gameid'];
	
	if ($gameid!=='') {
		#$gameid=intval($gameid);
		#if (strval($gameid)!==$_REQUEST['gameid']) {
		#	echo 'No non-numeric characters permitted in Game ID #<p>';
		#	$gameid='';
		#} else
		$options['gameidok']=false;
		if (!file_exists('makegame/games/'.$gameid.'.php')) {
			echo "No record exists for game ID # $gameid.<p>";
			$gameid='';
		} else if ($_REQUEST['territory']=='' or $_REQUEST['turnid']=='') {
			echo "<div style=\"color: red\"><b>Error:</b> You must specify a territory and a Turn ID for the game record.</div>";
			$_REQUEST['pbem']='';
		} else { #Game ID is valid and file exists
			include ('makegame/games/'.$gameid.'.php');
			if ($_REQUEST['password']==$data['password']) {
				$_REQUEST['ruleset']=$data['ruleset'];
				$_REQUEST['luck']=$data['luck'];
				if (isset ($_REQUEST['pbem']) && strlen($_REQUEST['pbem']) > 6 ) { # User has entered a game id but also entered e-mail addresses 
					$_REQUEST['pbem']=str_replace (array($data['player1'],$data['player2']), '', $_REQUEST['pbem']).' '.$data['player1'].' '.$data['player2'];
					#Previous line strips out the e-mail addresses from the pbem field that are already saved in game ID.
				} else {				
					$_REQUEST['pbem']=$data['player1'].' '.$data['player2']; # This line preloads the saved e-mail addresses.
				}
				$_REQUEST['reps']=1;
				$_REQUEST['gameid']=$gameid;
				$turnid=$_REQUEST['turnid'];
				$options['gameidok']=true;
			} else { 
				echo "The password provided did not match.<p>";
				$_REQUEST['gameid']='';
			}
		}
	}
	*/
	
	/* OLD OPTIONS FOR OTHER RULESETS...SHOULD BE PUT IN THE UPDATEUNITS FUNCTION IF WE WANT BACK IN
	if (isset($_REQUEST['ruleset'])) {
		if 	($_REQUEST['ruleset'] == 'LHTR' ) {
			#$unitspecs['HBom']['attack']=5.33;
			$unitspecs['SSub']['defend']=3;
			$unitspecs['HBom']['attackdice']=1;
			if ($_REQUEST['luck']!=='pure') $unitspecs['HBom']['attack']=5.33;
			#debug ($_REQUEST['luck']);
		}
		if ($_REQUEST['ruleset'] == 'Europe' ) {
			$unitspecs['Fig']['cost']=12;
			$unitspecs['Arm']['defend']=2;
			$unitspecs['Car']['cost']=18;
		}
	}*/

	if (isset($_REQUEST['round'])) $round=intval ($_REQUEST['round']);
	foreach ($sides as $side => $name) {

		$string=trim($_REQUEST['ool_'.$side]);
		$string=str_replace (' ', '-', $string);
		
		$replacechars=array ('  ',' ', ',', '.', '<', '>', '?', '/', '\\', '!');
		$string=str_replace($replacechars,'', $string) ;
		
		$array=explode ('-', $string);
		
		//find if any elements are missing or changed names
		foreach ($baseool as $type) {
			if (!in_array($type, $array)) $valid=false; 
		
		}
		
		//find if number of elements is the the same
		if (count($array)!==count($baseool)) $valid=false; 
		$customOOL[$side]=$array;
	}
	if ($valid) { 
		$ool=$customOOL;$warned = false;
		foreach ($sides as $side => $name) {
			//prevent battleships being killed first if Classic ruleset used and Bat's listed first in OOL
			if ($_REQUEST['ruleset']=='Classic' && $ool[$side][0]=='Bat') { 
				#debug ("Saving $side's battleships!");
				if (!$warned) echo "<b>Notice:</b> OOL adjusted to prevent battleships being killed first.";
				$warned=true;
			}
			$string=(implode('-',$ool[$side]));
			#setcookie ('ool_'.$side, $string, +31536000);
		}
	} else {
		echo "<p><strong>Error</strong> - elements in the custom Order of Loss should be separated only by dashes.
			The elements themselves should not be edited, deleted or added to.</p>";
	}
	$mustland=0;
	if (isset($_REQUEST['mustland']) && $_REQUEST['mustland']!=='' && !isset($_REQUEST['Clear'])) {
		$_REQUEST['mustland']=intval($_REQUEST['mustland']);
		$mustland=$_REQUEST['mustland'];
	}
	
	$abortratio=intval($_REQUEST['abortratio']);
	if ($abortratio > 1000 or $abortratio < 1) $abortratio=0;
	
	$options['legal']=false;
	$_REQUEST['reps']=intval($_REQUEST['reps']);

	$options['AA']=(isset($_REQUEST['AA']) or isset($_REQUEST['AAr']));

	$_REQUEST['pbem']=str_replace(array(',',';'),' ',$_REQUEST['pbem']);
	$_REQUEST['pbem']=explode(' ', $_REQUEST['pbem']);
	
	foreach ($_REQUEST['pbem'] as $key => $val) {
		if (!valid_email($val)) unset ($_REQUEST['pbem'][$key]);
	}
	
	if ($_REQUEST['reps']>1 && ($_REQUEST['luck']=='none' or $_REQUEST['battle']=='Evaluate units')) {
		echo '<p><strong>Note:</strong> in No Luck mode or Evaluate Units mode, every battle turns out the same
			(so not much point running it 1000x). Battle has only been run once.</p>';
		$_REQUEST['reps']=1;
	}
	
	foreach ($posted as $key => $value) {
		if ((!isset($value) or $value=='') && $key !== 'rounds'){ $posted[$key]=0;
		} else {$posted[$key]=intval($value);} 
	}
	
	$forces= array ('att' => array(), 'def' =>array());
	foreach ($units as $type) {
		if ($posted['a'.$type] >0) $forces['att'][$type]=$posted['a'.$type];
		if ($posted['d'.$type] >0) $forces['def'][$type]=$posted['d'.$type];
	}
	
	//check that both sides have units: 
	$options['nounits']= (count($forces['att']) == 0 && (count($forces['def']) == 0));
	if (!$options['nounits']) {
	
		//seabattle boolean conditions. check if seabattle or not.
		$options['seabattle']=(((has_sea($forces['def'])) or (has_sea($forces['att'])))
			&& (!has_land($forces['att'])) && (!has_land($forces['def']))
			&& (!isset($forces['def']['Bom'])) && (!isset($forces['def']['HBom'])));
													
		//landbattle boolean must also be set to true if a shore bombardment is possible
		$options['landbattle'] = ((has_land($forces['def']))
			or (has_land($forces['att']))
			or (has_air($forces['def']))
			or (has_air($forces['att']))
		);
		if (isset($forces['att']['Bat'])) {
			$options['landbattle'] = ($options['landbattle']) && (has_land($forces['att']));
		}

		//if AA50 or AA1942, add Des as invalid attacker and Cru as valid attacker into landbattle option
		if (($_REQUEST['ruleset']=='AA50')||($_REQUEST['ruleset']=='AA1942')) {
			if (isset($forces['att']['Cru'])) {
				$options['landbattle'] = ($options['landbattle']) && (has_land($forces['att']));
			}
			$options['landbattle']=(($options['landbattle'])
			&& (!isset($forces['att']['Des']))
		);
		}

		//if Revised, add Des as valid attacker into landbattle option -- No need to address Cru since it's not on Revised form
		if ($_REQUEST['ruleset']=='Revised'){
			if (isset($forces['att']['Des'])) {
				$options['landbattle'] = ($options['landbattle']) && (has_land($forces['att']));
			}
		}
		//no need to address Cru and Des in Classic either since they are not on the Classic form now
		

		//finish list of requirements for landbattle to not include any of the following			
		$options['landbattle']=(($options['landbattle'])
			&& (!has_sea($forces['def']))
			&& (!isset($forces['att']['Tra']))
			&& (!isset($forces['att']['Sub']))
			&& (!isset($forces['att']['SSub']))
			&& (!isset($forces['att']['Car']))
			&& (!isset($forces['att']['dBat']))
		);
		
		// check if attacker has combined standard and upgraded units of the same class:
		$illegalattackcomb = (
		(isset($forces['att']['Art']) && isset ($forces['att']['AArt'])) or
		(isset($forces['att']['Bom']) && isset ($forces['att']['HBom'])) or 
		(isset($forces['att']['Fig']) && isset($forces['att']['JFig'])) or 
		(isset($forces['att']['Sub']) && isset($forces['att']['SSub']))
		);
		$options['legal'] = ($options['landbattle'] + $options['seabattle'] !==2)
		&& ($options['landbattle'] + $options['seabattle'] !==0)
		&& $illegalattackcomb==false;
	}
	return $forces;
}

//runs form.php
function showform  ($aforce, $dforce) {
	global $options, $savedool, $ool;
	include ('form.php'); 
}

//sets a force to all empty for every possible tech and non-tech unit
function allunits () {
	return array ('Inf', 'Art', 'AArt', 'Arm', 'Fig', 'JFig', 'Bom', 'HBom', 'Tra', 'Sub', 'SSub', 'Des', 'Cru', 'Car', 'Bat', 'dBat',);
}

//sets a force to all empty for every possible non-tech unit
function notechunits () {
	return array ('Inf', 'Art', 'Arm', 'Fig', 'Bom', 'Tra', 'Sub', 'Des', 'Cru', 'Car', 'Bat', 'dBat',);
}

//determine if a force has any air units and returns a boolean
function has_air ($force) {
	return isset($force['Fig']) or isset($force['JFig']) or isset($force['Bom']) or isset($force['HBom']);
}

//determine if a force has any sub units and returns a boolean
function has_sub ($force) {
	return isset($force['Sub']) or isset($force['SSub']);
}

//determine if a force has any land units and returns a boolean
function has_land ($force) {
	return isset($force['Inf']) or isset($force['Art']) or isset($force['AArt']) or isset($force['Arm']);
}

//used only with AA50 and AA1942 rulesets to deal with defenseless transports -- returns a boolean if there are defenseless tra
function has_seapunch ($force) {
	return isset($force['Fig']) or isset($force['JFig']) or isset($force['Bom']) or isset($force['HBom']) 
	or isset($force['Sub']) or isset($force['SSub']) or isset($force['Car']) or isset($force['Des'])
	or isset($force['Bat']) or isset($force['dBat']) or isset($force['Cru']);
}

//used only with AA50 and AA1942 rulesets to deal with defenseless transports -- returns a boolean if a force has tra
function has_tra ($force) {
	return isset($force['Tra']);
}

//determine if a force has any sea units and returns a boolean
function has_sea ($force) {
	return isset($force['Sub']) or isset($force['SSub']) or isset($force['Tra']) or isset($force['Car'])
	or isset($force['Des']) or isset($force['Bat']) or isset($force['dBat']) or isset($force['Cru']);
}

//used to determine if a force has something besides only air units left so subs are assigned a dice roll or not.
function nonair ($force) {
	$count=0;
	if (isset($force['Fig'])) $count +=$force['Fig'];
	if (isset($force['JFig'])) $count += $force['JFig'];
	if (isset($force['Bom'])) $count +=$force['Bom'];
	if (isset($force['HBom'])) $count += $force['HBom'];
	#debug (array_sum ($force) ." ". $count);
	return array_sum ($force) > $count;
}

//determine if a force has any non-sub units and returns a boolean
function nonsubs ($force) {
	$count=0;
	if (isset($force['Sub'])) $count +=$force['Sub'];
	if (isset($force['SSub'])) $count += $force['SSub'];
	#debug (array_sum ($force) ." ". $count);
	return array_sum ($force) > $count;
}

//determine if a force has any sea units that can bombard in amphib assaults and returns a boolean
function can_bombard ($force) {
	return (isset ($force['Des']) or isset($force['Bat']) or isset($force['Cru']));
}

//determine if a force has aa or aa radar and returns a boolean
function aa_present() {
	return isset($_REQUEST['AA']) or isset($_REQUEST['AAr']);
}

//counts the number of a type of unit in a force
function typecount ($force, $types) {
	$count=0;
	foreach ($types as $type) if (isset($force[$type])) $count += $force[$type];
	return $count;
}

//counts the number of total air units in a force
function aircount ($force) {
	$types=array('Fig', 'JFig', 'Bom', 'HBom');
	$count=0;
	foreach ($types as $type) if (isset($force[$type])) $count += $force[$type];
	return $count;
}

//submerges subs so they can't take a hit from air units in certain rulesets
function submerge ($force) {
	if (isset($force['Sub'])) unset($force['Sub']);
	if (isset($force['SSub'])) unset($force['SSub']);
	return $force;
}

/* //NOT USED YET
function add_arrays ($array1, $array2) {
	$keys=array_merge(array_keys($array1), array_keys ($array2));
	$array3=array();
	foreach ($keys as $key) {
		$sum=0;
		if (isset($array1[$key])) $sum+= $array1[$key];
		if (isset($array2[$key])) $sum+= $array2[$key];
		$array3[$key]=$sum;
	}
	return $array3;
}*/

//create the index for a given force based on units
function makeindex($force) {
	$result='';
	foreach ($force as $type => $count) $result.= $count.'-'.$type.'_';
	return $result;
}

//checks entered email for valid format NOTE: PREG is PHP 5
function  valid_email($email) {
	if(!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", $email)) {
		return false;
	} else {
		return true;
	}
}
/* //MAY NOT NEED ANYMORE IF NO LONGER SAVING GAMES
function savegame ($data) {
	array_tofile ($data, '$data', 'makegame/games/'.$data['gameid'].'.php');
}

function array_tofile ($array, $arrayname, $file) {
	ob_start();
	arsort ($array);
	var_export ($array);
	$filedata = ob_get_contents();
	ob_end_clean();
	$fp=fopen($file, 'w');
	fputs ($fp, ("<?php $arrayname=$filedata; ?>"));
	fclose ($fp);
}*/

//Update function used to set the $unitspecs array according to ruleset.
function updateunits () {
	global $unitspecs;
	if (isset($_REQUEST['ruleset'])) {
		if 	($_REQUEST['ruleset'] == 'Classic') {
			$unitspecs['Arm']['defend']=2;
			$unitspecs['Fig']['cost']=12;
			$unitspecs['JFig']['defend']=5;
			$unitspecs['JFig']['attack']=3;
			$unitspecs['JFig']['cost']=12;
			$unitspecs['Bom']['cost']=15;
			$unitspecs['HBom']['attackdice']=3;
			$unitspecs['HBom']['cost']=15;
			$unitspecs['Tra']['defend']=1;
			$unitspecs['Tra']['cost']=8;
			$unitspecs['Tra']['attackdice']=1;
			$unitspecs['Tra']['defenddice']=1;
			$unitspecs['Sub']['defend']=2;
			$unitspecs['Sub']['cost']=8;
			$unitspecs['SSub']['defend']=2;
			$unitspecs['SSub']['cost']=8;
			$unitspecs['Car']['defend']=3;
			$unitspecs['Car']['cost']=18;
			$unitspecs['Bat']['hp']=1;
			$unitspecs['Bat']['cost']=24;
			
		}
		if 	($_REQUEST['ruleset'] == 'Revised' ) {
			$unitspecs['Bom']['cost']=15;
			$unitspecs['Tra']['defend']=1;
			$unitspecs['Tra']['cost']=8;
			$unitspecs['Tra']['attackdice']=1;
			$unitspecs['Tra']['defenddice']=1;
			$unitspecs['Sub']['defend']=2;
			$unitspecs['Sub']['cost']=8;
			$unitspecs['SSub']['defend']=2;
			$unitspecs['SSub']['cost']=8;
			$unitspecs['Des']['attack']=3;
			$unitspecs['Des']['defend']=3;
			$unitspecs['Des']['cost']=12;
			$unitspecs['Car']['defend']=3;
			$unitspecs['Car']['cost']=16;
			$unitspecs['Bat']['cost']=24;
			$unitspecs['dBat']['cost']=24;
		}
	}
}

//Update function used to update the OOL array if a ruleset is selected besides AA50-AA1942.
function updateools () {
	global $ool;
	if (isset($_REQUEST['ruleset'])) {
		if 	($_REQUEST['ruleset'] == 'Classic') {
			$ool=array (
				'att' =>array('Inf', 'Art', 'AArt', 'Arm', 'Tra', 'Sub', 'SSub', 'Fig', 'JFig', 'Des', 'Cru', 'Bom', 'HBom', 'Car', 'dBat', 'Bat'),
				'def' =>array('Inf', 'Art', 'AArt', 'Arm', 'Bom', 'HBom', 'Tra', 'Sub', 'SSub', 'Des', 'Cru', 'Fig', 'JFig', 'Car', 'dBat', 'Bat'),
			);
		}
		if 	($_REQUEST['ruleset'] == 'Revised') {
			$ool=array (
				'att' =>array('Bat', 'Inf', 'Art', 'AArt', 'Arm', 'Tra', 'Sub', 'SSub', 'Fig', 'JFig', 'Des', 'Cru', 'Bom', 'HBom', 'Car', 'dBat'),
				'def' =>array('Bat', 'Inf', 'Art', 'AArt', 'Arm', 'Bom', 'HBom', 'Tra', 'Sub', 'SSub', 'Des', 'Cru', 'Fig', 'JFig', 'Car', 'dBat'),
			);
		}
	}
}
?>
