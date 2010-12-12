<?php
/*	AACalc - a battle simulator for the Axis and Allies computer and/or board game. */

/**
 *	name: index.php
 *
 *	PHP version: 5.3.1
 *	Javascript
 *	JQuery
 *
 *	description: PHP Code containing the home/front page for AACalc. Includes and calls functions from
 *		associated AACalc php files to generate a form, process the form and output statistical results
 *		that apply to Axis and Allies gameplay scenarios.
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

include('init.php');
include('combatfunctions.php');
include('outputfunctions.php');
include('functions.php');
include('emogrifier.php'); //3rd party open source php code to deal with email and CSS under MIT license

updateunits(); //call function to update unitspecs if a ruleset other than AA50 and AA1942 is set.
updateools(); //call function to update OOLs if a rulset other than AA50 and AA1942 is set.

if (isset($_REQUEST['battle'])) {	
	#$start=microtime(true);
#debug ('running');
	//saving processing time: do not let the simulator run more than 20 rounds for any battle
	if (isset($_REQUEST['rounds'])) $rounds=($_REQUEST['rounds']);
	if ($rounds == '') {
		$rounds=20;
	} else {
		$rounds=intval($rounds);
		if ($rounds > 20) $rounds=20;
	}

	#$abortratio=100;
	$forces=getformdata($_REQUEST); //call function to deal with the form

	//if the request is to swap attacking and defending units, then swap
	if ($_REQUEST['battle']=='Swap units') {
#		debug ('Swapping');
		$swap=$forces['att'];
		$forces['att']=$forces['def'];
		$forces['def']=$swap;
	}

	$savedool=$ool;
	$goahead=true;
	if (isset($options['gameidok'])) $goahead=$options['gameidok'];
	
	$results=array(
		'att' => array(),
		'def' => array(),
		'draw' => 0,
	);
	$saveunits=intval($_REQUEST['saveunits']);
	$strafeunits=intval($_REQUEST['strafeunits']);
	if ((!$options['nounits'] && $options['legal'] ) or $_REQUEST['battle']!=='Run') {
		$history[0]=array(
			'att' => array('force' => $forces['att'], 'lost' => array(), 'hits' => array(), 'dice' => array(),),
			'def' => array('force' => $forces['def'], 'lost' => array(), 'hits' => array(), 'dice' => array(),),
		);
		$duration=0;
		$ipcdiff=array();
		$startforces=$forces;
		if (can_bombard($startforces['att']) && has_land($startforces['def'])) {
			if (isset($startforces['att']['Bat'])) unset ($startforces['att']['Bat']);
			if (isset($startforces['att']['Cru'])) unset ($startforces['att']['Cru']);
			if (isset($startforces['att']['Des'])) unset ($startforces['att']['Des']);
		}
		$astartstats=assess($startforces['att'], 'attack'); //call function to assess attacker stats
		$dstartstats=assess($startforces['def'], 'defend'); //call function to assess defender stats
			
		while ($goahead) {
			$t=1;
			$abort=($_REQUEST['battle']!=='Run');
			$options['AA']=(isset($_REQUEST['AA']) or isset($_REQUEST['AAr']));
			$att[$t]['force']=$forces['att'];
			$def[$t]['force']=$forces['def'];
			$astats=assess($forces['att'],'attack');$dstats=assess($forces['def'],'defend');
			$submerged= array();
			$draw=false;
			
			while (array_sum($att[$t]['force']) > 0
				&& (array_sum($def[$t]['force'])>0
				or (
				# isset($_REQUEST['AA']) # checking for SBR
				#has_air ($att[$t]['force'])
				#&&  
				$t==1)) 
				#&& (!isset($_REQUEST['AirStop']) or (isset($_REQUEST['AirStop']) && array_sum($att[$t]['force']) > aircount($att[$t]['force'])))
				&& $t <= $rounds
				&& !$draw
				&& !$stalemate 
				&& !$abort
			) {  # do battle

				/*check if a force is only transports under aa50 and AA1942 rulesets. if it is and the other side has punch left,
					then destroy remaining transports.*/
				if (($_REQUEST['ruleset']=='AA50') || ($_REQUEST['ruleset']=='AA1942')) {
					if ((has_seapunch($att[$t]['force'])) && (has_tra($def[$t]['force'])) && (!has_seapunch($def[$t]['force']))){
						$outcome=array(
							'att' => array('force' => ($att[$t]['force']), 'lost' => array(), 'vals'=> array(),),
							'def' => array('force' => array(), 'lost' => ($def[$t]['force']), 'vals' => array(),),
						);
					} elseif ((has_seapunch($def[$t]['force'])) && (has_tra($att[$t]['force'])) && (!has_seapunch($att[$t]['force']))){
						$outcome=array(
							'att' => array('force' => array(), 'lost' => ($att[$t]['force']), 'vals'=> array(),),
							'def' => array('force' => ($def[$t]['force']), 'lost' => array(), 'vals' => array(),),
						);					
					//DRAW condition where both Attacker and Defender only have transports left.
					} elseif (((!has_seapunch($att[$t]['force'])) && (has_tra($att[$t]['force'])))
						&& ((!has_seapunch($def[$t]['force'])) && (has_tra($def[$t]['force'])))
					) {
						$draw=true;
						$outcome=skirmish($att[$t]['force'], $def[$t]['force']);
					//DRAW condition where only subs vs only air are left.
					} elseif (((has_sub($att[$t]['force'])) && (!nonsubs($att[$t]['force']))
						&& (has_air($def[$t]['force'])) && (!nonair($def[$t]['force']))) or ((has_sub($def[$t]['force']))
						&& (!nonsubs($def[$t]['force'])) && (has_air($att[$t]['force'])) && (!nonair($att[$t]['force'])))
					) {	
						$draw=true;
						$outcome=array(
							'att' => array('force' => ($att[$t]['force']), 'lost' => array(), 'vals'=> array(),),
							'def' => array('force' => ($def[$t]['force']), 'lost' => array(), 'vals'=> array(),),
						);
					} else {
						$outcome=skirmish($att[$t]['force'], $def[$t]['force']);
					}
				} else {
					$outcome=skirmish($att[$t]['force'], $def[$t]['force']);
				}
				
				//condition to void subs dice rolls for an only sub attack force vs an only air defense force.
				if ((has_sub($att[$t]['force'])) && (!nonsubs($att[$t]['force']))
					&& (has_air($def[$t]['force'])) && (!nonair($def[$t]['force']))
				) {
					$outcome['att']['vals']=array();
				}
				//condition to void subs dice rolls for an only air attack force vs an only sub defense force.
				if ((has_sub($def[$t]['force'])) && (!nonsubs($def[$t]['force']))
					&& (has_air($att[$t]['force'])) && (!nonair($att[$t]['force']))
				) {
					$outcome['def']['vals']=array();
				}

				$history[$t]=$outcome;
				$stalemate=($_REQUEST['luck']=='none' && $t > 0 && $outcome['att']['vals']['totalhits'] + $outcome['def']['vals']['totalhits'] == 0);
				$t++;
				$att[$t]['force']=$outcome['att']['force'];
				$def[$t]['force']=$outcome['def']['force'];
				$astats=assess($att[$t]['force'], 'attack');
				$dstats=assess($def[$t]['force'], 'defend');

				$abort=(#($dstats['count']>0 and $astats['count']/$dstats['count']*100 < $abortratio)
					($astats['count'] - round(($dstats['punch']+3)/6) < $saveunits && $saveunits > 0) or
					($dstats['count'] - round(($astats['punch']+3)/6) < $strafeunits && $strafeunits > 0) or 
					($dstats['punch'] > 0 and $astats['punch']/$dstats['punch']*100 < $abortratio)
				);

				if (!$stalemate) {
					if ((!has_sea($def[$t]['force']) && (!nonsubs($att[$t]['force'])
						or isset($_REQUEST['asubschicken'])) && has_sub($att[$t]['force']))
					) {
						$submerged['att']=array();
						if (isset($att[$t]['force']['Sub'])) $submerged['att']['Sub']=$att[$t]['force']['Sub'];
						if (isset($att[$t]['force']['SSub'])) $submerged['att']['SSub']=$att[$t]['force']['SSub'];
						$att[$t]['force'] = submerge($att[$t]['force']);
					} else if (!has_sea($att[$t]['force']) && (!nonsubs($def[$t]['force'])
						or isset($_REQUEST['dsubschicken'])) && has_sub($def[$t]['force'])
					) {
						$submerged['def']=array();
						if (isset($def[$t]['force']['Sub'])) $submerged['def']['Sub']=$def[$t]['force']['Sub'];
						if (isset($def[$t]['force']['SSub'])) $submerged['def']['SSub']=$def[$t]['force']['SSub'];
						$def[$t]['force']=submerge($def[$t]['force']);
					}
				}
			} // Battle has been resolved

			//Note that bombarding Battleships, Cruisers, and Destroyers are also tracked in $submerged.
			if (isset($submerged['att']['Sub']) or isset($submerged['att']['SSub'])) { 
				foreach ($submerged['att'] as $type => $survivors) {
				/*not adding BBs, Crus, or DDs back in for bombardment, because they always survive and this skews the survival 
					rate to always show 100%*/
					if (isset($att[$t]['force'][$type])) {$att[$t]['force'][$type] += $survivors;}
					else {$att[$t]['force'][$type]=$survivors;}
				}
			}
			if (isset($submerged['def'])) {
				foreach ($submerged['def'] as $type => $survivors) {
					if (isset($def[$t]['force'][$type])) {$def[$t]['force'][$type] += $survivors;}
					else {$def[$t]['force'][$type]=$survivors;}
				}
			}

			$diff=0;
#			if (isset($att[1]['force']['Des']))  $diff-=$att[1]['force']['Des']*$unitspecs['Des']['cost']; # For some reason Destroyers are messing up Diff - perhaps due to $submerged??? figure out later...
			
			//restore damaged battleships 
			if ($_REQUEST['reps'] !== 1 && (isset($def[$t]['force']['dBat']) or isset($att[$t]['force']['dBat'])) ) {
				if (isset($def[$t]['force']['dBat'])) {
					if (isset($def[$t]['force']['Bat'])) { $def[$t]['force']['Bat'] += $def[$t]['force']['dBat'];}
					else {$def[$t]['force']['Bat'] = $def[$t]['force']['dBat'];}
					unset ($def[$t]['force']['dBat']);
				}
				if (isset($att[$t]['force']['dBat'])) {
					if (isset($att[$t]['force']['Bat'])) { $att[$t]['force']['Bat'] += $att[$t]['force']['dBat'];}
					else {$att[$t]['force']['Bat'] = $att[$t]['force']['dBat'];}
					unset ($att[$t]['force']['dBat']);
					
				}
			}

			if (isset($att[$t]['force']['Bat'])) $diff -= $att[$t]['force']['Bat'] * $unitspecs['Bat']['cost'];
			$diff+=$dstartstats['cost']-$dstats['cost']-($astartstats['cost']-$astats['cost']);
			if ($ool !== $savedool) $ool=$savedool;
			$goahead=false;

			if ($_REQUEST['reps']==1) {
				//running battle only once - see if round should be reset
				$resetrounds=$astats['count'] * $dstats['count'] == 0;
#				debug ($round);
			} else {
				$duration +=$t;
				$b++; $goahead = ($b<$_REQUEST['reps']); //count number of battles
				$acount=array_sum($att[$t]['force']);
				$dcount=array_sum($def[$t]['force']); //have stats for each sides outcome
				
				if (isset($ipcdiff[$diff])) {
					$ipcdiff[$diff]++;
				} else {
					$ipcdiff[$diff]=1;
				}
				#debug ($diff.' IPC Gain for attacker');
				$aindex=(makeindex($att[$t]['force']));
				$dindex=(makeindex($def[$t]['force']));
				if ($dcount==0&& $acount==0) {
					$results['draw']++; 
				} else {
					if ($acount > 0) { //attacker had surviving units
						if (isset($results['att'][$aindex])) {
							$results['att'][$aindex]['total']++;
						} else {
							$results['att'][$aindex] = array(
								'stats'=>$astats, #assess($att[$t]['force'],'attack'),
								'total' =>1,

								'force'=>$att[$t]['force'],
								'lost'=> whatdied($att[$t]['force'], $forces['att']),
							);
						}
					}
					if ($dcount > 0) { //defender had surviving units
						if (isset($results['def'][$dindex])) {
							$results['def'][$dindex]['total']++;
						} else {
							$results['def'][$dindex] = array(
								'stats'=>$dstats,#assess($def[$t]['force'],'defend'),
								'total'=>1,
								'force'=>$def[$t]['force'],
								'lost'=> whatdied($def[$t]['force'], $forces['def']),
								
							);
						}
					}
				}
			} 
			//end  of samples collection mode
		}

		//battle was fought to the death, 1000x or just pre-combat, or insufficient data for PBEM log. . restore original units
		if ($rounds==20 or $rounds== 0 or $_REQUEST['reps']>1 or (isset($options['gameidok']) && $options['gameidok']==false)) { 
			showform($forces['att'], $forces['def']);
		} else { //battle was limited to $t rounds. load remaining units.
			if ($resetrounds) {
				showform (array(),array());
			} else {
				showform ($att[$t]['force'], $def[$t]['force']);
			}
		}
	} else {
		$fixrounds=true;
		showform($forces['att'], $forces['def']);
		nobattle();
		
	}
} else {
	showform (array(), array());
	include ('intro.php');
}

if (!$options ['nounits'] && $options['legal'] && (!isset($options['gameidok']) or $options['gameidok']==true)) { # $display results or averages
	if ($_REQUEST['reps']>1) { 
		$results['duration']=round ($duration / $_REQUEST['reps'], 1)-1;
		$results['ipcdiff']=$ipcdiff;
		$out= showaverages ($results);
	} else {
		$out= showresults ($forces, $history, $t);
	}

	if (count($_REQUEST['pbem'])>0) {
		global $host;
		if ($host == 'localhost') {
			echo '<h1>E-mail not available on localhost</h1>';
		} else if ( $_REQUEST['reps'] > 1 or $_REQUEST ['battle']!== 'Run') {
			echo "<em>E-mail not available when swapping or evaluating units or running battle more than once.</em><br />";
		} else {
			//use emogrifier to convert CSS styles in results($out) to in-line styles so colors are preserved in the email
			$cssstr = file_get_contents("aa.css"); //gets the CSS and puts it's contents into a string
			$converttoinline = new emogrifier($out,$cssstr); //instantiates the emogrifier class
			
			//need to use @ operator to suppress PHP warnings when loading HTML into DOM object
			@$body=$converttoinline->emogrify(); //converted results with in line styles instead of CSS classes

			$addresses=implode (', ', $_REQUEST['pbem']);
			//NO NEED FOR GAMEID ANYMORE?
			$subject=/*$_REQUEST['gameid']. */" Battle results";
			echo "<em>Results e-mailed to $addresses.</em><br /> ";
			$headers  = 'From: aacalc@frood.net' . "\n" .'MIME-Version: 1.0' . "\n".'Content-type: text/html; charset=iso-8859-1' . "\n";
			mail($addresses, $subject, $body, $headers);
			
			//release class object
			unset($converttoinline);
		}
			
		/*NO LONGER NEEDED?
		//The magic: add results to Game Record if requested
		if ($_REQUEST['gameid']!=='' && file_exists('makegame/games/'.$_REQUEST['gameid'].'.php')) { //Game ID already validated in getformdata
			if ($_REQUEST ['battle']== 'Run') {
				include ('makegame/games/'.$_REQUEST['gameid'].'.php');
				$turnid=$_REQUEST['turnid'];
				$territory=$_REQUEST['territory'];
				if (!isset($data['results'])) $data['results']=array();
				if (!isset($data['results'][$turnid])) $data['results'][$turnid]=array();
				if (!isset($data['results'][$turnid][$territory])) $data['results'][$turnid][$territory]=array();
				global $round, $rounds;
				$data['results'][$turnid][$territory][]=array('forces'=>$forces, 'history'=>$history, 't'=>$t, 'round'=>$round, 'rounds'=>$rounds, 'time'=>time());
				if (count($data['results']) > 5) $data['results']=array_slice($data['results'],1);
				savegame ($data);
				echo '<em>Results saved under record for Game ID  '.$_REQUEST['gameid'].'</em><br />';
			} else { 
				echo '<em>Evaluate results do not get logged.</em><br/>'; }
		}*/
	}
	echo $out;
}
?>

<html>
<head>

<script language="javascript"></script>
<script src="http://code.jquery.com/jquery-latest.min.js"></script>
<script>
//javascript and jQuery library used to update the form client side instead of server side. 
// noConflict method needed since original Javascript is used for the majority of the following functions.
jQuery.noConflict();

//update the form with appropriate colors/tips/units/options according to ruleset select. 
function ChangeColors(Rules)
{
	var className1="att"; var className2="def";
	var doubleBat=true; var Art=true; var Des=true; var Cru=true;
	var AArt=false; var NormTech=false; var AAradar=false;
	if (Rules=="AA50") {
		className1 = className1 + "aa50";
		className2 = className2 + "aa50";
		AArt=true; NormTech=true; AAradar=true;
	} else if (Rules=="Revised") {
		className1 = className1 + "rev";
		className2 = className2 + "rev";
		Cru=false;
		NormTech=true; AAradar=true;
	} else if (Rules=="Classic") {
		className1 = className1 + "cla";
		className2 = className2 + "cla";
		doubleBat=false; Art=false; Des=false; Cru=false;
		NormTech=true;
	}

//original Javascript here that only changes the form and not the results colors.
	//document.getElementById("Attacker").className=className1;
	//document.getElementById("attOOL").className=className1;
	//document.getElementById("Defender").className=className2;
	//document.getElementById("defOOL").className=className2;
//jQuery here to get all elements with base class "att" and "def" and change to classname1 and classname2 this will
	//	change the results colors too. Maybe we do not want this since the results are for a previously selected ruleset.
	jQuery('[class^="att"]').removeClass().addClass(className1);
	jQuery('[class^="def"]').removeClass().addClass(className2);

	if(doubleBat){
		document.getElementById("dBat").className="";
		document.getElementById("a_dBat").className="";
		document.getElementById("d_dBat").className="";
	}else{
		document.getElementById("dBat").className="noshow";
		document.getElementById("a_dBat").className="noshow";
		document.getElementById("d_dBat").className="noshow";
		document.getElementById("txta_dBat").value="";
		document.getElementById("txtd_dBat").value="";
	}

	if(Art){
		document.getElementById("Art").className="";
		document.getElementById("a_Art").className="";
		document.getElementById("d_Art").className="";
	}else{
		document.getElementById("Art").className="noshow";
		document.getElementById("a_Art").className="noshow";
		document.getElementById("d_Art").className="noshow";
		document.getElementById("txta_Art").value="";
		document.getElementById("txtd_Art").value="";		
	}

	if(Des){
		document.getElementById("Des").className="";
		document.getElementById("a_Des").className="";
		document.getElementById("d_Des").className="";
	}else{
		document.getElementById("Des").className="noshow";
		document.getElementById("a_Des").className="noshow";
		document.getElementById("d_Des").className="noshow";
		document.getElementById("txta_Des").value="";
		document.getElementById("txtd_Des").value="";
	}
	if(Cru){
		document.getElementById("Cru").className="";
		document.getElementById("a_Cru").className="";
		document.getElementById("d_Cru").className="";
	}else{
		document.getElementById("Cru").className="noshow";
		document.getElementById("a_Cru").className="noshow";
		document.getElementById("d_Cru").className="noshow";
		document.getElementById("txta_Cru").value="";
		document.getElementById("txtd_Cru").value="";
	}	

	if(document.getElementById("JFig")){
		if (AAradar){
			document.getElementById("AAr").className="";
			document.getElementById("txtAAr").className="";
		}else{
			document.getElementById("AAr").className="noshow";
			document.getElementById("txtAAr").className="noshow";
			document.getElementById("AAr").checked=false;
		}
		if (AArt){
			document.getElementById("AArt").className="";
			document.getElementById("a_AArt").className="";
			document.getElementById("d_AArt").className="";
		}else{
			document.getElementById("AArt").className="noshow";
			document.getElementById("a_AArt").className="noshow";
			document.getElementById("d_AArt").className="noshow";
			document.getElementById("txta_AArt").value="";
			document.getElementById("txtd_AArt").value="";
		}	
		if (NormTech){
			document.getElementById("JFig").className="";
			document.getElementById("a_JFig").className="";
			document.getElementById("d_JFig").className="";
			document.getElementById("HBom").className="";
			document.getElementById("a_HBom").className="";
			document.getElementById("d_HBom").className="";
			document.getElementById("SSub").className="";
			document.getElementById("a_SSub").className="";
			document.getElementById("d_SSub").className="";
		}else{
			document.getElementById("JFig").className="noshow";
			document.getElementById("a_JFig").className="noshow";
			document.getElementById("d_JFig").className="noshow";
			document.getElementById("HBom").className="noshow";
			document.getElementById("a_HBom").className="noshow";
			document.getElementById("d_HBom").className="noshow";
			document.getElementById("SSub").className="noshow";
			document.getElementById("a_SSub").className="noshow";
			document.getElementById("d_SSub").className="noshow";
			document.getElementById("txta_JFig").value="";
			document.getElementById("txtd_JFig").value="";
			document.getElementById("txta_HBom").value="";
			document.getElementById("txtd_HBom").value="";
			document.getElementById("txta_SSub").value="";
			document.getElementById("txtd_SSub").value="";
		}	
	}
	
	switch(Rules){
	case "AA50": case "AA1942":
		document.getElementById("Arm").title="Armor A:3 D:3 cost:5";
		document.getElementById("Fig").title="Fighters A:3 D:4 cost:10";
		document.getElementById("Bom").title="Bombers A:4 D:1 cost:12";
		document.getElementById("Tra").title="Transports A:0 D:0 cost:7";
		document.getElementById("Sub").title="Submarines A:2 D:1 cost:6";
		document.getElementById("Des").title="Destroyers A:2 D:2 cost:8";
		document.getElementById("Car").title="Carriers A:1 D:2 cost:14";
		document.getElementById("Bat").title="Battleships A:4 D:4 cost:20";
		document.getElementById("dBat").title="Damaged battleships A:4 D:4 cost:20";
		document.getElementById("ool_att").value="Bat-Inf-Art-AArt-Arm-Sub-SSub-Des-Fig-JFig-Cru-Bom-HBom-Car-dBat-Tra";
		document.getElementById("ool_def").value="Bat-Inf-Art-AArt-Arm-Bom-HBom-Sub-SSub-Des-Car-Cru-Fig-JFig-dBat-Tra";
		if(document.getElementById("JFig")){
			document.getElementById("JFig").title="Jet fighters A:4 D:4 cost:10";
			document.getElementById("HBom").title="Heavy bombers A:4x2 D:1 cost:12";
			document.getElementById("SSub").title="Super submarines A:3 D:1 cost:6";
		}
		break;
	case "Revised":
		document.getElementById("Arm").title="Armor A:3 D:3 cost:5";
		document.getElementById("Fig").title="Fighters A:3 D:4 cost:10";
		document.getElementById("Bom").title="Bombers A:4 D:1 cost:15";
		document.getElementById("Tra").title="Transports A:0 D:1 cost:8";
		document.getElementById("Sub").title="Submarines A:2 D:2 cost:8";
		document.getElementById("Des").title="Destroyers A:3 D:3 cost:12";
		document.getElementById("Car").title="Carriers A:1 D:3 cost:16";
		document.getElementById("Bat").title="Battleships A:4 D:4 cost:24";
		document.getElementById("dBat").title="Damaged battleships A:4 D:4 cost:24";
		document.getElementById("ool_att").value="Bat-Inf-Art-AArt-Arm-Tra-Sub-SSub-Fig-JFig-Des-Cru-Bom-HBom-Car-dBat";
		document.getElementById("ool_def").value="Bat-Inf-Art-AArt-Arm-Bom-HBom-Tra-Sub-SSub-Des-Cru-Fig-JFig-Car-dBat";
		if(document.getElementById("JFig")){
			document.getElementById("JFig").title="Jet fighters A:3 D:5 cost:10";
			document.getElementById("HBom").title="Heavy bombers A:4x2 D:1 cost:15";
			document.getElementById("SSub").title="Super submarines A:3 D:2 cost:8";
		}
		break;
	case "Classic":
		document.getElementById("Arm").title="Armor A:3 D:2 cost:5";
		document.getElementById("Fig").title="Fighters A:3 D:4 cost:12";
		document.getElementById("Bom").title="Bombers A:4 D:1 cost:15";
		document.getElementById("Tra").title="Transports A:0 D:1 cost:8";
		document.getElementById("Sub").title="Submarines A:2 D:2 cost:8";
		document.getElementById("Car").title="Carriers A:1 D:3 cost:18";
		document.getElementById("Bat").title="Battleships A:4 D:4 cost:24";
		document.getElementById("ool_att").value="Inf-Art-AArt-Arm-Tra-Sub-SSub-Fig-JFig-Des-Cru-Bom-HBom-Car-dBat-Bat";
		document.getElementById("ool_def").value="Inf-Art-AArt-Arm-Bom-HBom-Tra-Sub-SSub-Des-Cru-Fig-JFig-Car-dBat-Bat";
		if(document.getElementById("JFig")){
			document.getElementById("JFig").title="Jet fighters A:3 D:5 cost:12";
			document.getElementById("HBom").title="Heavy bombers A:4x3 D:1 cost:15";
			document.getElementById("SSub").title="Super submarines A:3 D:2 cost:8";
		}
		break;
	default:
	}
}

function uncheck(CheckboxID)
{
    document.getElementById(CheckboxID).checked=false
}
</script>

</body>
</html>

