<?php
/*	Battle functions for AACalc. */

/**
 *	name: combatfunctions.php
 *
 *	PHP version: 5.3.1
 *
 *	description: Contains functions dealing with combat for AACalc. Skirmish is the battle board - it receives
 *		the results of calculations and ultimately removes casualties from each side, returning the outcome of
 *		each round of combat and the processing of units. Triage takes the number and type of hits (air, sub, etc.)
 *		and determines what casualties will eventually be taken. Other functions deal with counting hits and
 *		applying them to the battle.
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

//takes num & type of dice and finds number of actual successful hits, using pure, low or no luck mode as requested. Returns dice rolled.
function counthits ($shots, $dice) { #returns number of hits rolled on X dice for one value, and also the set of dice rolled.
	global $options, $unitspecs;
	#$dice=array();
	$punch=0;
	if (!isset($dice['rolled'])) $dice['rolled']=0;
	if ($_REQUEST['luck']!=='pure') { //Sim is in low luck mode
		foreach ($shots as $hitval => $count) $punch += ($hitval * $count);
		$hits = intval($punch/6); //total hits scored by punch
		$die= $punch % 6; //get remainder
		if ($die > 0) {
			if ($_REQUEST['luck']=='none') {
				$hits += round($die/6);
			} else {
				$dice['rolled']++;
				$roll=mt_rand(1, 6);
				$dice[$die][]=$roll;
				if ($roll <= $die) $hits++;
			}
		}
	} else { // do it old school - by rolling dice for each attacking unit
		$rolls=0;
		$hits=0;
		if (isset($dice['LHTR_HBoms'])) {
			$shots[4]-=$dice['LHTR_HBoms'];
			$punch += 5.33*$dice['LHTR_HBoms'];
			$x=0;
			#debug ('Rolling HBoms twice...');
			while ($x++ < $dice['LHTR_HBoms']) {
				#debug ('HBom #: '.$x);
				$dice['rolled']++;
				$roll= mt_rand(1, 6); 
				$roll2= mt_rand(1, 6);
				#debug ('1st roll: '.$roll);
				if ($roll2 < $roll) $roll=$roll2; 
				#debug ('2nd roll: '.$roll);
				
				if ($roll <= 4) $hits++;
				$dice[4][]=$roll;
			}
		
			unset ($dice['LHTR_HBoms']);
		}
		foreach ($shots as $target => $count) {
			if ($count > 0) {
				$i=0;
				$punch +=$target*$count;
				#$dice[$target]=array();
				while ($i < $count) {
					$i++;
					$roll= mt_rand(1, 6); 
					$dice['rolled']++;
					$dice[$target][] = $roll;
					if ($roll <= $target) $hits++;
				}
			}
		}
	}
	return array ('hits' => $hits, 'dice' => $dice, 'punch' => $punch);
}

//takeshots determine the number and type of dice to be rolled for a given force
function takeshots ($force, $side) {
	global $unitspecs, $options, $t;
	$hits=0;
	$special=array();
	
	//possible unit Att and Def values...as of yet, nothing hits on a  6
	$shots=array (1=>0, 2=>0, 3=>0, 4=>0, 5=>0);
	foreach ($force as $type => $units) {
		if ($units * $unitspecs[$type][$side]>0 && $type !== 'Sub' && $type!== 'SSub' &&
			$type!== 'Fig' && $type!== 'JFig' && $type!== 'Bom' && $type!== 'HBom') {
			$shots[$unitspecs[$type][$side]] += $force[$type];
		}
	}
	
	// correct for improved attack strength of infantry with artillery and advanced artillery
	if ($options['landbattle'] && $side=='attack' && isset($force['Inf']) && (isset($force ['Art'])||(isset($force ['AArt']))))  { 
		if (isset($force ['Art'])) {
			$bonus=$force['Art']; if ($bonus>$force['Inf']) $bonus = $force['Inf'];
			$shots[2] += $bonus; $shots[1] -= $bonus;
		} else {
			$bonus=(2*($force['AArt'])); if ($bonus>$force['Inf']) $bonus = $force['Inf'];
			$shots[2] += $bonus; $shots[1] -= $bonus;
		}
	}
	
	/* this is now in the airattack function
	if (isset($force['HBom']) && $side=='attack') {
		if ($_REQUEST['ruleset']!=='LHTR')  {
			$shots[$unitspecs['HBom'][$side]] += $force['HBom']; #add shots again for attacking HBoms
			if ($_REQUEST['ruleset']=='Classic') $shots[$unitspecs['HBom'][$side]] += $force['HBom'];
		} else if ($_REQUEST['luck']=='pure') { #LHTR ruleset applies, and combat being rolled by dice
			
			$special['LHTR_HBoms']=$force['HBom'];
			#debug('Setting LHTR HBoms to roll twice');
		}
	}*/
	
	$result=counthits ($shots, $special); //determine how many hits scored
	#$special is usually a dice array, added to accommodate a second AA gun (which has been taken off the form)
	$result ['hit_type']='land';
	if ($options['seabattle']) $result['hit_type'] = 'sea';
	return $result;
}

//Determine $shots to be fired for coastal bombardment
function bombard ($force) {
	$shots=array ();
	
	//if AA50 or AA1942, then only 1 bombard per unit transported and no more.
	if (($_REQUEST['ruleset']=='AA50')||($_REQUEST['ruleset']=='AA1942')) {	
		//get number of land units in the amphibious assault
		$types=array('Inf', 'Art', 'AArt', 'Arm');
		$numland=matchbombard($force,$types);
		
		//get number of Battleships in the amphibious assault
		$types=array('Bat');
		$numBat=matchbombard($force,$types);
		
		//get number of Cruisers in the amphibious assault
		$types=array('Cru');
		$numCru=matchbombard($force,$types);
		
		if ($numland>=($numBat+$numCru)) //if there are >= ground units than bombard ships, proceed
		{
			if (isset($force['Cru'])) $shots[3]=$force['Cru'];
			if (isset($force['Bat'])) $shots[4]=$force['Bat'];
		} else { //otherwise determine the bombard ships that can participate on a 1 for 1 basis
			if ($numland<$numBat)
			{
				if (isset($force['Bat'])) $shots[4]=$numland;
			} else {
				if ($numCru>0)
				{
					$numtemp=$numland-$numBat;
					if ($numtemp<$numCru)
					{
						if (isset($force['Cru'])) $shots[3]=$numtemp;
					} else {
						if (isset($force['Cru'])) $shots[3]=$force['Cru'];
					}
				}
				if (isset($force['Bat'])) $shots[4]=$force['Bat'];
			}
		}
	} else { //for Revised and Classic Rules, no limit on number of bombard ships
		if (isset($force['Des'])) {
			if ($_REQUEST['ruleset']=='Revised') {$shots[3]=$force['Des'];}
		}
		if (isset($force['Bat'])) {$shots[4]=$force['Bat'];}
	}

	$result=counthits($shots, array());
	if (($_REQUEST['ruleset']=='AA50')||($_REQUEST['ruleset']=='AA1942')) {
		$result['hit_type']='land';
	} else {
		$result['hit_type']='ground';
	}
	return $result;
}


//for AA50 and AA1942, bombarding ships cannot outnumber # of units transported in an amphib assault
function matchbombard ($force,$types) {
	$count=0;
	foreach ($types as $type) if (isset($force[$type])) $count += $force[$type];
	return $count;
}

//Determine $shots to be fired for sub attacks
function subattack ($force, $side) {
	global $unitspecs;
	
	//possible sub unit Att and Def values
	$shots=array(1=>0, 2 => 0, 3 => 0); 
	
	if (isset($force['Sub'])) $shots[$unitspecs['Sub'][$side]]+=$force['Sub'];
	if (isset($force['SSub'])) $shots[$unitspecs['SSub'][$side]]+=$force['SSub'];
	
	$result=counthits($shots, array());
	$result['hit_type']='sub';
	return $result;
}

//need this seperate function to assign air unit hits since in some rulesets, air units can not hit subs unless a DD is present.
function airattack ($force, $side) {
	global $unitspecs, $options, $t;
	
	//possible air unit Att and Def values
	$shots=array(1=>0, 3=>0, 4=>0, 5=>0); 
	
	if (isset($force['Fig'])) $shots[$unitspecs['Fig'][$side]]+=$force['Fig']; 
	if (isset($force['JFig'])) $shots[$unitspecs['JFig'][$side]]+=$force['JFig'];
	if (isset($force['Bom'])) $shots[$unitspecs['Bom'][$side]]+=$force['Bom']; 
	if (isset($force['HBom'])) $shots[$unitspecs['HBom'][$side]]+=$force['HBom']; 
	
	if (isset($force['HBom']) && $side=='attack') {
		if ($_REQUEST['ruleset']!=='LHTR')  {
			$shots[$unitspecs['HBom'][$side]] += $force['HBom']; #add shots again for attacking HBoms
			if ($_REQUEST['ruleset']=='Classic') $shots[$unitspecs['HBom'][$side]] += $force['HBom'];
		} else if ($_REQUEST['luck']=='pure') { #LHTR ruleset applies, and combat being rolled by dice
			
			$special['LHTR_HBoms']=$force['HBom'];
			#debug('Setting LHTR HBoms to roll twice');
		}
	}
	
	$result=counthits($shots, array());
	$result ['hit_type']='land';
	if ($options['seabattle']) {
		if ((($_REQUEST['ruleset']=='AA50') or ($_REQUEST['ruleset']=='AA1942')) && (!isset($force['Des']))) {
			$result['hit_type'] = 'seanosub';
		} else {
			$result['hit_type'] = 'sea';
		}
	}
	return $result;
}

//determine shots for aa gun fire
function aa_fire ($force) {
	if (isset($_REQUEST['AA'])) {$shots=array(1=> aircount($force));} 
	elseif (isset($_REQUEST['AAr'])) {$shots=array(2=> aircount($force));} #aaradar fires at 2
	
	$result = counthits ($shots, array());
	if (isset($_REQUEST['AA2'])) {
		$shots[1]=$shots[1]-$result['hits']; #take a second shot at surviving planes
		$result2=counthits($shots, $result['dice']);
		$result['hits']+= $result2['hits'];
		$result['dice']=$result2['dice'];
		$result['punch']+=$result2['punch'];
	}
	$result['hit_type']='air';
	return $result;
}

/*The grand central function - Skirmish is the battle board - it receives the results of calculations and ultimately
	removes casualties from each side, returning the outcome of each round of combat and the processing of units.*/
function skirmish ($aforce, $dforce) { 
	global $t, $options, $submerged;

	//conduct opening fire step
	$ofsaresolved=false; $ofsdresolved=false; $killsfireback=false; $air_a=false; $air_d=false;
	$avals=array('totalhits'=>0);
	$dvals=array('totalhits'=>0);
	$alost=array();
	$dlost=array();
	if ($options['seabattle']) {	//if sea battle:
		if (has_sub($aforce)) $avals['ofs']=subattack($aforce, 'attack'); 	//handle attack subs
		if (has_sub($dforce)) $dvals['ofs']=subattack($dforce, 'defend'); 
	} 
	
	else 
	if (($t==1) && (($_REQUEST['ruleset']=='Classic')||($_REQUEST['ruleset']=='Revised'))) { //check for other opening fire
		
		//the amphibious assault casualties for AA50 and AA1942 are moved into the regular take casualities section
		
		if (has_land($aforce) && can_bombard($aforce)) { //Amphibious assault
			#debug ('Bombarding');
			$avals['ofs'] = bombard ($aforce);
			if (isset($aforce['Bat'])) {
				$submerged['att']['Bat']=$aforce['Bat'];
				unset($aforce['Bat']);
			}
			if (isset($aforce['Des'])) {
				$submerged['att']['Des']=$aforce['Des'];
				unset($aforce['Des']);
			}
		}
	}
	
	if ($t==1) {if (aa_present() && has_air($aforce)) $dvals['ofs'] = aa_fire ($aforce);}
	
	if (isset($avals['ofs'])) {
		$ofsd_triaged=triage ($dforce, $avals['ofs'], $dlost, 'def'); //determine what the casualties will be from OFS for each side:
		$dlost=$ofsd_triaged['lost'];
		$avals['totalhits']+=$avals['ofs']['hits'];
		if (!$options['seabattle'] or ($options['seabattle'] && !isset($dforce['Des']))) { //resolve casualties if OFS victims are not saved by destroyers
			$dforce=$ofsd_triaged['force']; 
			$ofsdresolved=true;
		}
	}
		
	//lather, rinse, repeat for defender.
	if (isset($dvals['ofs'])) { //triage attacking casualties from OFS
		$ofsa_triaged=triage ($aforce, $dvals['ofs'], $alost, 'att');
		$alost=$ofsa_triaged['lost'];
		$dvals['totalhits']+=$dvals['ofs']['hits'];
		if (!$options['seabattle'] or ($options['seabattle'] && (!isset($aforce['Des']) && $_REQUEST['ruleset']!=='Europe'))) { #take hits right away
			$aforce= $ofsa_triaged['force'];
			$ofsaresolved=true;
			
		}
	}
	
	// for all but sub sneak attacks vs a force with DDs and AA50/AA1942 bombardment, OFS casualties already taken.

	//for AA50 and AA1942, taking casualties from bombardment - if it is round 1
	if (($t==1) && (($_REQUEST['ruleset']=='AA50')||($_REQUEST['ruleset']=='AA1942'))) { 
		if (has_land($aforce) && can_bombard($aforce)) { //Amphibious assault attacker has to have land units
		//bombards can hit air units now in the AA50 and AA1942 editions.
			#debug ('Bombarding');
			$killsfireback=true;
			$avals['ofs'] = bombard ($aforce);
			#print_r ($avals['ofs']);
			$avals['totalhits']+=$avals['ofs']['hits'];
			if (isset($aforce['Bat'])) {
				$submerged['att']['Bat']=$aforce['Bat'];
				unset($aforce['Bat']);
			}
			if (isset($aforce['Cru'])) {
				$submerged['att']['Cru']=$aforce['Cru'];
				unset($aforce['Cru']);
			}
			
		}
	}
	
	//find hits scored by air (so their hits can be applied to subs or not with DD interaction)
	if (has_air($aforce)) {
		$avals['norm']=airattack($aforce, 'attack'); 
		$avals['totalhits']+=$avals['norm']['hits'];
		$air_a=true;
		//store result from airattack function in a temp array to be merged with the takeshots results later
		$airavals=$avals;
	}
	if (has_air($dforce)) {
		$dvals['norm']=airattack($dforce, 'defend');
		$dvals['totalhits']+=$dvals['norm']['hits'];
		$air_d=true;
		//store result from airattack function in a temp array to be merged with the takeshots results later
		$airdvals=$dvals;
	}

	//find number of hits scored by all regular remaining units
	if (nonsubs($aforce)) {
		$avals['norm']=takeshots($aforce, 'attack'); 
		$avals['totalhits']+=$avals['norm']['hits'];
	}
	if (nonsubs($dforce) or $options['AA']) {
		$dvals['norm']=takeshots($dforce, 'defend');
		$dvals['totalhits']+=$dvals['norm']['hits'];
	}
	
	//Having allowed the counterattack for sub victims saved by destroyers, now take them off before other casualties are taken
	if (isset($ofsa_triaged) && !$ofsaresolved) { $aforce = $ofsa_triaged['force']; }
	if (isset($ofsd_triaged) && !$ofsdresolved) { $dforce = $ofsd_triaged['force']; }
	
	//triage casualties from bombardment for AA50 and AA1942, so use all other OFS hits besides subs
	if (($_REQUEST['ruleset']=='AA50')||($_REQUEST['ruleset']=='AA1942')) {
		if ((nonsubs($aforce))&&(nonsubs($dforce))&&($killsfireback)) { 
			if (isset($avals['ofs'])) {
				$d_triaged=triage($dforce, $avals['ofs'], $dlost, 'def');
				$dforce=$d_triaged['force'];
				$dlost=$d_triaged['lost'];
			}
		}
	}
	
	//Now triagecasualties from air attack
	
	//determine air casualties for defender
	if ($air_a && (isset($airavals['norm']))) {
		$aird_triaged=triage ($dforce, $airavals['norm'], $dlost, 'def'); //determine defender's casualties from air attack
		$dforce=$aird_triaged['force'];
		$dlost=$aird_triaged['lost'];
	}
	
	//determine air casualties for attacker
	if ($air_d && (isset($airdvals['norm']))) {
		$aira_triaged=triage ($aforce, $airdvals['norm'], $alost, 'att'); //determine attacker's casualties from air attack
		$aforce=$aira_triaged['force'];
		$alost=$aira_triaged['lost'];
	}

	//Now, triage all other casualties
	
	if (isset($avals['norm'])) {
		$d_triaged=triage($dforce, $avals['norm'], $dlost, 'def');
		$dforce=$d_triaged['force'];
		$dlost=$d_triaged['lost'];
	}
	if (isset($dvals['norm']))  {
		$a_triaged=triage($aforce, $dvals['norm'], $alost, 'att');
		$aforce=$a_triaged['force'];
		$alost=$a_triaged['lost'];
	}		
	
	//merge the attacker airvals and vals arrays into one so dice get displayed correctly.
	if ($air_a) {
	$avals["totalhits"]+= $airavals["totalhits"];
	$avals["norm"]["hits"]+= $airavals["norm"]["hits"];
	$avals["norm"]["punch"]+= $airavals["norm"]["punch"];
	$avals["norm"]["dice"]["rolled"]+= $airavals["norm"]["dice"]["rolled"];
	
	//ADD dice rolls at 1 3 4 5 (possible dice rolls for air units)
	//may not need dice rolls at 2 or 6 until air is listed to hit at 2 or 6.
	$index=array(1 /*,2*/,3,4,5 /*,6*/);
	foreach ($index as $v) {
		if ((array_key_exists($v,$airavals["norm"]["dice"])) && (array_key_exists($v,$avals["norm"]["dice"]))) {
			$avals["norm"]["dice"][$v] = array_merge($airavals["norm"]["dice"][$v], $avals["norm"]["dice"][$v]);
		} elseif (array_key_exists($v,$airavals["norm"]["dice"])) {
			$avals["norm"]["dice"][$v] = $airavals["norm"]["dice"][$v];
		}
	}
	ksort ($avals["norm"]["dice"]);
	}
	
	//merge the defender airvals and vals arrays into one so dice get displayed correctly.
	if ($air_d) {
	$dvals["totalhits"]+= $airdvals["totalhits"];
	$dvals["norm"]["hits"]+= $airdvals["norm"]["hits"];
	$dvals["norm"]["punch"]+= $airdvals["norm"]["punch"];
	$dvals["norm"]["dice"]["rolled"]+= $airdvals["norm"]["dice"]["rolled"];
	
	//ADD dice rolls at 1 3 4 5 (possible dice rolls for air units)
	//may not need dice rolls at 2 or 6 until air is listed to hit at 2 or 6.
	$index=array(1 /*,2*/,3,4,5 /*,6*/);
	foreach ($index as $v) {
		if ((array_key_exists($v,$airdvals["norm"]["dice"])) && (array_key_exists($v,$dvals["norm"]["dice"]))) {
			$dvals["norm"]["dice"][$v] = array_merge($airdvals["norm"]["dice"][$v], $dvals["norm"]["dice"][$v]);
		} elseif (array_key_exists($v,$airdvals["norm"]["dice"])) {
			$dvals["norm"]["dice"][$v] = $airdvals["norm"]["dice"][$v];
		}
	}
	ksort ($dvals["norm"]["dice"]);
	}
	
	return array (
		'att' => array ('force' => $aforce,'lost' => $alost,'vals'=> $avals,),
		'def' => array ('force' => $dforce,'lost' => $dlost,'vals' => $dvals,),
	);
}

//takes the number and type of hits (air, sub, etc.) and determines what casualties will eventually be taken
function triage ($force, $result, $casualties, $side) { # This function determines which units get killed.
	global $unitspecs, $canhit, $ool, $t, $options, $mustland;

	$done=false;
	$hit_type=$result['hit_type'];
	$kills=$result['hits'];
	if ($kills > 0) {
		$victims=$canhit[$hit_type];
		if ($hit_type=='air' && !isset($_REQUEST['AA_OOL'])) { #for AA hits, take hits randomly from all aircraft
			$planes=array();
			foreach($victims as $type) {
				if (isset($force[$type])) {
					$typecount=$force[$type];
					$x=0;
					while ($x < $typecount) {
						$planes[]=$type;
						$x++;
					}
				}
			}
			shuffle ($planes);
			$y=0;
			while ($y<$kills) {
				$type=$planes[$y];
				$force[$type]--;
				if ($force[$type]==0) unset ($force[$type]);
				if (isset($casualties[$type])) {$casualties[$type]++;}
				else {$casualties[$type]=1;}
				$y++;
			}
		} else { //hit type is not air (AA), so take casualties according to OOL
		
		// THIS SHOULD BE DONE OUTSIDE OF THE REPEATED FUNCTIONS! but modified here with each turn, and then restored at end of each battle?
			$dBatpresent=false;
			$ko=array();
			//this creates the $ko array, ordered according to OOL, containing only unit types that are in victims and the force taking losses :
			if ($hit_type=='air') $side='att';
			foreach ($ool[$side] as $type) {
				//Add dBats to killing array so they can take hits in same round they are damaged. Prevents Bats from always surviving first round.
				if ($type=='Bat' && isset ($force[$type])) $dBatpresent=true;
				if (($type=='dBat') && ($dBatpresent)) $ko[]=$type;
				
				if (in_array($type, $victims) && isset ($force[$type])) $ko[]=$type;
			}
			// work through the kill order (ko) that has been created from OOL, victims and force
			foreach ($ko as $type) {
				if (!$done && isset($force[$type]) && $force[$type] > 0	) { //test whether attack is done
					 if ($type=='Inf' or $type =='Arm' or $type=='Art' or $type=='AArt' or $type=='Tra') {
						if ($mustland > 0 && $side=='att') { #test whether to modify triage to accommodate "take land at all costs"
							$forcecount=array_sum($force);
							$nonair= #$forcecount-$aircount;
							#$vitalcount=
							typecount ($force, array('Inf','Art','AArt','Arm','Tra'));
							#debug ("nonair: $nonair");
							$aircount=$forcecount-$nonair;
							#debug ("air: $aircount");
							#debug ("mustland: $mustland");
							if ($nonair<$mustland) $mustland=$nonair;
							#debug ("mustland: $mustland");
						#	debug ("kills: $kills");
							
							if ($kills > $nonair-$mustland && $kills < $forcecount && $aircount>0) {
								$spared=$kills-$nonair+$mustland;
						#		debug ("spared: $spared");
							
								$kills-=$spared;
						#	debug ("kills: $kills");
								
								foreach (array('Inf', 'Art', 'AArt', 'Arm', 'Tra') as $landunit) {
									$key=array_search($landunit, $ool[$side]);
									unset ($ool[$side][$key]);
									$ool[$side][]=$landunit;
								}
							}
						}
					} else if (isset($spared)) { #$type is not land unit, check if any hits were delayed because of "mustland"
						$kills=$spared;
						#debug ("kills 2: $kills");
							
						unset($spared);
					}

					if ($kills <= $force[$type]) { // equal or more units than hits - no more to take after this
						$force[$type] -= $kills; //take off the last set of hits
						if  ($type!=='Bat' or $_REQUEST['ruleset']=='Classic') {
							if ($type=='dBat' && $_REQUEST['ruleset']!=='Classic') {
								if (!isset($casualties['Bat'])) { $casualties['Bat']=$kills;}
								else {$casualties['Bat']+=$kills;}
							} else { 
								if (!isset($casualties[$type])) { $casualties[$type]=$kills;}
								else {$casualties[$type]+=$kills;}
							}
						} else { //take hits on battleships, move them over to used state
							if (isset($force['dBat'])) {$force['dBat'] += $kills;}
							else {$force['dBat']=$kills;}
						}
						if (!isset($spared)) $done=true;
						if ($force[$type]==0) unset ($force[$type]);
					} else { //need to keep hitting more stuff
						$kills -= $force[$type]; //take all the hits in this category
						if  ($type!=='Bat' or $_REQUEST['ruleset']=='Classic') { 
							if ($type=='dBat' && $_REQUEST['ruleset']!=='Classic') {
								if (isset($casualties['Bat'])) {$casualties['Bat']+=$force['dBat'];}
								else {$casualties['Bat']=$force['dBat'];}
							} else { 
								if (isset($casualties[$type])) {$casualties[$type] +=$force[$type];}
								else {$casualties[$type]=$force[$type];}
							}
						} else { //take hits on battleships, move them over to used state
							if (isset($force['dBat'])) {$force['dBat'] += $force['Bat'];}
							else {$force['dBat'] = $force['Bat'];}
						}
						unset ($force[$type]); //none of this type left
						if (array_sum($force) <=0) $done=true; //nothing left to hit. Stop hitting.
					}
				}
			}
		}
	}
	return array('force'=>$force, 'lost' =>$casualties);
}
?>
