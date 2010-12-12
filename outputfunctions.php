<?php
/*	Functions for AACalc that deal with the results of battles. */

/**
 *	name: outputfunctions.php
 *
 *	PHP version: 5.3.1
 *
 *	description: Contains functions dealing with the reporting of battle statistics, the display of dice, and
 *		the display of other output in the browser for the AACalc form.	Also deals with outputting error messages
 *		when form validation indicates incorrect setups for battles.
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

//determines modifications (such as artillery and advanced artillery) to a force's count, cost, and punch to be reported later.
function assess ($force,$mode) {
	global $unitspecs;
	$punch=0;	$count=0; $cost=0; 
	if ($mode=='attack') {	
		if (isset($force['Inf']) && ((isset ($force['Art']))||(isset ($force['AArt'])))){
			if (isset ($force['Art'])) {
				$punch=$force['Art'];
				if ($punch>$force['Inf']) $punch = $force['Inf'];
			} else { //corrects the punch for Advanced artillery with up to 2x the number of infantry
				$punch=(2*($force['AArt']));
				if ($punch>$force['Inf']) $punch = $force['Inf'];
			}
		}
#		if (isset($force['HBom']) && $_REQUEST['ruleset']!=='LHTR') {
#			$factor=1;
#			if ($_REQUEST['ruleset']=='Classic') $factor=2;
#			$punch += $force['HBom']*$factor*$unitspecs['HBom']['attack'];
#		}
	}
	foreach ($force as $type => $typecount) {
		if ($typecount > 0) {
			#debug ("$type $mode"."dice: ".$unitspecs[$type][$mode.'dice']);
			$punch += $typecount * $unitspecs[$type][$mode] * $unitspecs[$type][$mode.'dice'];
			$cost += $typecount * $unitspecs[$type]['cost'];
			#if ($type=='Bat' or $type=='dBat') $typecount *=2;
			$typecount *= $unitspecs[$type]['hp'];
			$count += $typecount;
		}
	}
	return array (
		'count' => $count,
		'cost' => $cost,
		'punch'=>$punch,
	);
}

//displays hits in red and misses in black within a standard graphic representing a single die
function showdice ($dice) {
	$output='';
	$hits=0;
	foreach ($dice as $set => $rolls) {
		if ($set !== 'rolled') {
			#shuffle($rolls);
			sort ($rolls);
			$output .= '<div style="padding: 0 4px;  line-height: 21px; margin: 0;" title="Hitting on '.$set.' or less:"> @'.$set.': ';
			foreach ($rolls as $roll) {
				$r='miss';
				$color='black';
				$weight='bold';
				if ($roll <= $set) {
					$r='hit';
					$color='red';
					$weight='bold';
					$hits++;
				}
				$output.='
					<span style="font-size: 12px; border: 1px outset black; font-family: monospace; background: #ddd; padding: 0px 5px; font-weight:'.$weight.'; color: '.$color.'; text-shadow: 0px 0px 0px #000">'.$roll.'</span> ';
#					<img src="'.$pageurl.'dice/'.$r.$roll.'.png" width="12" height="12" alt="'.$roll.': '.$r.' target of '.$set.'" />'.$roll.$char.'';
			}
			$output.= '</div>   '; //extra spaces get deleted by something, leave them
		}
	}
	#$output.=scenariolink('mail');
	return $output;
}

//generates a unit list for a given force and if it's empty, sets the list to no units
function showforce ($force) {
	global $unitspecs;
	$list='';
	foreach ($force as $type => $units) if ($units>0) $list.= "$units ".$type.", ";
	if ($list=='') $list = 'no units  ';
	return substr($list,0,-2).'.';
}

//sets a standard width for the results that show unit count, IPC value, and punch.
function width ($value, $mx) {
	if ($mx==0) {	
		$w=0;
	} else {
		$w=round(175* $value/$mx)-1;
	}	
	if ($w <0) $w=0;
	return $w;
	}

//determines count, cost, and punch for the attacker and the defender
function calibrate ($forces) {
	$data=assess($forces['att'],'attack');
	$count=$data['count'];
	$cost=$data['cost'];
	$punch=$data['punch'];
	$data=assess($forces['def'],'defend');
	if ($count<$data['count'])$count=$data['count'];
	if ($cost<$data['cost'])$cost=$data['cost'];
	if ($punch<$data['punch'])$punch=$data['punch'];
	return array (
		'count' => $count,
		'cost' => $cost,
		'punch' => $punch,
	);
}

//displays the evaluation of units and/or battle results in the browser window at the bottom of the AACalc form
function showresults ($forces, $history, $t) {
	//set colors and units in the next if-then for rulesets on PHP side...uses the CSS
	$className='';
	if (isset($_REQUEST['ruleset'])) {
		if 	($_REQUEST['ruleset'] == 'Classic') {
			$className='cla';
		}
		if 	($_REQUEST['ruleset'] == 'Revised' ) {
			$className='rev';
		}
		if 	($_REQUEST['ruleset'] == 'AA50' ) {
			$className='aa50';
		}
	}
	
	$graphstyles=' display: block; float: left; font-size: 60%; text-align: center; padding: 0; margin: 0; height:15px; border: 1px solid grey; ';

	$benchmarks=calibrate($forces);
	$aalllost=array();
	$dalllost=array();
	global $options, $round, $rounds;
	if (intval($round)<1) $round=1;
	
	$result= '<h3>';
	global $heading;
		$heading = 'Result';
	if (isset($_REQUEST['territory']) && strlen($_REQUEST['territory'])>0 ) $heading .=' in '.$_REQUEST['territory'];
	if ($rounds !== '') $heading .=', round '.($t+$round-2);

	$result .=$heading.'</h3>
	<span class="att'.$className.'" style="width: 15px; display: block; float: left; border: 1px solid black; margin: 3px 3px 0 0; font-size: 60%;">&nbsp;</span> Attacker starts with: '.(showforce($forces['att'])).'<br />
	<span class="def'.$className.'" style="width: 15px; display: block; float: left; border: 1px solid black; margin: 3px 3px 0 0; font-size: 60%;">&nbsp;</span> Defender starts with: '.(showforce($forces['def'])).'<br />';
	if (!isset($confirm)) $result .= scenariolink('web');
	$result .='<table border=1><tr><th colspan=2>Results</th><th>Unit count <!--<div style="font-size: 60%">scale: '.($benchmarks['count']).'</div>--></th><th>IPC value<!-- <div style="font-size: 60%">scale: '.($benchmarks['cost']).'</div>--></th>
	<th>Punch <!--<div style="font-size: 60%">scale: '.($benchmarks['punch']).'</div>--></th>
	</tr>';
	
	$sides=array('att' => 'Attacker', 'def'=>'Defender');
	
	$x=0;
	$steps=array('opening fire' =>'ofs', 'normal combat' =>'norm');
	while ($x <$t) {
		$data=$history[$x];
		$aforce=$data['att']['force'];
		$astats=assess($aforce, 'attack');
#		$astats['punch']=$astats['opunch'];
		$alost=$data['att']['lost'];
		$aalllost=ftool($alost, $aalllost);
		$aloststats=assess($alost, 'attack');
#		$aloststats['punch']=$aloststats['opunch'];
		$dforce=$data['def']['force'];
		$dstats=assess($dforce, 'defend');
#		$dstats['punch']=$dstats['dpunch'];
#		$dstats['punch']=$dstats['dpunch'];
		$dlost=$data['def']['lost'];
		$dalllost=ftool($dlost, $dalllost);
		$dloststats=assess($dlost, 'defend');
	#	$dloststats['punch']=$dloststats['dpunch'];
		if ($x==0) {$label='Initial'; $rows=1;} 
		else {$label='Round '.($x+$round-1); $rows=3;}
		$result.='<tr><th  rowspan="'.$rows.'">'.$label.':</th>';
		if ($x!==0) {
			$int=0;
			foreach ($sides as $side=>$name) {
				if ($int > 0 ) echo '<tr>';
				$result.= '<th class="'.$side.$className.'" style="font-weight: normal; text-align: right; ">'.(substr($name,0,1)).'.&nbsp;hits: </th>
				<td colspan="3" class="'.$side.$className.'" style="padding: 0; color: white;font-size: 60%;">&nbsp;';
				$i=0; //counter for attack steps present
				$totalhits=0;
				$output='';
				foreach($steps as $label => $step) {
					if (isset($data[$side]['vals'][$step]) && $data[$side]['vals'][$step]['punch']>0) {
						$src=$data[$side]['vals'][$step];
						$totalhits+=$src['hits'];
						$i++;
						$output.='<span style="font-weight: bold !important; font-size: 125% !important;">'.$src['hits']."</span> hits in $label: ";
						if ($_REQUEST['luck']!=='pure') {
							if ($_REQUEST['luck']=='none') {
								$hits=$src['hits'];
								$punch=$src['punch'];
								$output.="$punch/6  rounded = $hits ";
							} else { //must be lowluck
								$output.=$src['punch']."/6 rounded = ".intval($src['punch']/6)." ";	
								if ($src['dice']['rolled']>0) $output.=' + roll ';
							}
						}
						if (substr($output, -2)==': ' && $src['dice']['rolled']==0) $output=substr($output,0,-2);
						if ($src['dice']['rolled']>0) $output.= showdice ($src['dice']);
						$output.=' and ';
					}
				}
				if ($i >1) $output="<span style=\"font-weight: bold !important; font-size: 125% !important;\">$totalhits</span> total: ".$output;
				if (substr($output, -5)==' and ') $output=substr($output,0,-6);
				
				$result.= $output.'</td></tr>';
				$int++;
			}
		}
		if (isset($int) and $int > 0 ) $result.='<tr>';
		$result.= '<th style="padding: 0px;"><div class="att'.$className.'" style="padding: 0 3px; margin: 0px;">A.&nbsp;stats:</div>
		<div class="def'.$className.'" style="padding: 0 3px;">D.&nbsp;stats:</div></th>';
		foreach ($benchmarks as $stat => $bmark) {
				$afw=width($astats[$stat],$bmark);
				$alw=width($aloststats[$stat],$bmark);
				$dfw=width($dstats[$stat],$bmark);
				$dlw=width($dloststats[$stat],$bmark);
				$result.= '<td class="graph" valign=top style="padding: 3px;">';
				if ($astats[$stat] >0) $result.= '<span class="att'.$className.'" style="'.$graphstyles.' width:'.$afw.'px;" title="Left: '.(showforce($aforce)).'" >'.$astats[$stat].' </span>';
				if ($aloststats[$stat] >0) $result.='<span class="lost" style="border: none !important; padding: 1px 0px !important; '.$graphstyles.'  width:'.$alw.'px;" title="Lost: '.(showforce($alost)).'" >'.$aloststats[$stat].' </span>';
				$result.= '<br />';
				
				if ($dstats[$stat] >0) $result.='<span class="def'.$className.'" style="'.$graphstyles.' width:'.$dfw.'px;" title="Left: '.(showforce($dforce)).'">'.$dstats[$stat].' </span>';
				if ($dloststats[$stat] >0) $result.= '<span class="lost" style="border: none !important; padding: 1px 0px !important; '.$graphstyles.' width:'.$dlw.'px;" title="Lost: '.(showforce($dlost)).'" >'.$dloststats[$stat].' </span>';
				$result .='</td>';
		}
		$x++;
		$result.= '</tr>';
	}
	if (array_sum($aalllost) + array_sum($dalllost)>0) { // show kills if there were any
		$aallstats=assess($aalllost, 'attack');
		$dallstats=assess($dalllost, 'defend');
		$result.='<tr><th colspan="2">Total lost:</th>';
		foreach  ($benchmarks as $stat => $bmark) {
			$aallfw=width($aallstats[$stat],$bmark);
			$dallfw=width($dallstats[$stat],$bmark);
			$result.='<td class="graph"  valign=top style="padding: 3px;">';
			if ($aallstats[$stat] >0) $result.='<span class="att'.$className.'" style="'.$graphstyles.'  width:'.$aallfw.'px;" title="Killed by defender: '.(showforce($aalllost)).'" >'.$aallstats[$stat].'</span>';
			$result.='<br />';
			if ($dallstats[$stat] >0) $result.='<span class="def'.$className.'" style="'.$graphstyles.'  width:'.$dallfw.'px;" title="Killed by attacker: '.(showforce($dalllost)).'" >'.$dallstats[$stat].'</span>';
			$result.= ' </td>';
		}
		$result.='</tr></table>
		<span class="lost" style="width: 15px; display: block; float: left; border: 1px solid black; margin: 3px 3px 0 0; font-size: 60%;">&nbsp;</span>Casualties <br />
		<span class="att'.$className.'" style="width: 15px; display: block; float: left; border: 1px solid black; margin: 3px 3px 0 0; font-size: 60%;">&nbsp;</span> Attacker ends with: '.(showforce($aforce)).' (losses: '.(showforce($aalllost)).')<br />';
		$result.= '<span class="def'.$className.'" style="width: 15px; display: block; float: left; border: 1px solid black; margin: 3px 3px 0 0; font-size: 60%;">&nbsp;</span>Defender ends with: '.(showforce($dforce)).' (losses: '.(showforce($dalllost)).')';
		$result.= '<p>';
	} else { $result.= '</table>'; }
	return $result;
}

//controls the output graphics for the results of multiple runs (probability bar, avg and median, and standard deviations)
function probgraph ($num_times, $force, $units, $lost, $cost, $median, $stdev1, $stdev2) {
	$width=width($num_times, 60);
	$med_style='';
	if ($stdev2) $med_style='font-weight: bold !important; color: dodgerblue;';
	if ($stdev1) $med_style='font-weight: bold !important; color: blue;';
	if ($median) $med_style='font-weight: bold !important; color: red;';
	$result='<!--'.$units.'-->
	<td style="text-align: right;"><span style="background: #999; width: '.$width.'px; display: block; float: right; border: 1px solid black; margin:  0; font-size: 50%; '.$med_style.'" title="'.$num_times.'%">&nbsp;</span></td>
	<td style="text-align: left;'.$med_style.'">'.$num_times.'%</td>'.
	'<td  style="text-align: right;'.$med_style.'"> '.$units.':</td>
	<td style="'.$med_style.'">'.$force.'</td>
	<td style="text-align: right;'.$med_style.'"><em>'.$lost.' :</em></td>
	<td style="'.$med_style.'">'.$cost.' IPCs</td>';
	return $result;
}

//creates the URL link for running the same battle with units and options set
function scenariolink ($type) {
	global $pageurl, $forces;
	$url=$pageurl.'?';
	foreach ($_REQUEST as $key => $value) { if ($key !== 'pbem') $url.="$key=$value&";
	};
	$url.='pbem=';
	$link='<a href="'.$url.'">Link to or bookmark this scenario</a>.';
#	if ($type='mail') $link .= ' <span class="noshow">Full address: '.$url.'</span>';
	return $link;
}

//keeps track of battle result(s) and displays them...this is the main function called by index.php file to display results
function showaverages ($results) {
	global $forces, $options;
	//set colors and units in the next if-then for rulesets on PHP side...uses the CSS
	$className='';
	if (isset($_REQUEST['ruleset'])) {
		if 	($_REQUEST['ruleset'] == 'Classic') {
		$className='cla';
		}
		if 	($_REQUEST['ruleset'] == 'Revised' ) {
		$className='rev';
		}
		if 	($_REQUEST['ruleset'] == 'AA50' ) {
		$className='aa50';
		}
	}
	$output ='';
	$sides=array ('att' => 'Attacker', 'def'=> 'Defender');
	$statkeys=array('count', 'cost', 'punch');
	$output .= '<h2>Average outcome of '.(number_format($_REQUEST['reps'])).' battles</h2><b>Attacker: </b>'.showforce($forces['att']). '<b> v. Defender: </b>'.showforce($forces['def']).' '.scenariolink('web');
	$output .= '<p>Average battle duration: <b>'.$results['duration'].'</b> rounds of combat</p>';
	$breaks=array ('att'=> array(), 'def'=>array());
	if (!has_sea($forces['def']) && can_bombard($forces['att'])) { //Amphibious assault
		unset ($forces['att']['Des']);
		unset ($forces['att']['Cru']);
		unset ($forces['att']['Bat']);
		$output.="<b>Note:</b> Bombarding units are included in the statistics, although not reported in count/cost/punch/units.<br />";
	}
	$benchmarks=calibrate($forces);
	
	$graphstyles=' display: block; float: left; font-size: 60%; text-align: center; padding: 0; margin: 0; height:15px; border: 1px solid grey; ';
	$output .= '<table border=0><tr><th>&nbsp;</th>
	<th>avg. # units left<!--<div style="font-size: 60%">scale: '.($benchmarks['count']).'</div>--></th>
	<th>IPC value <!--<div style="font-size: 60%">scale: '.($benchmarks['cost']).'</div>--></th>
	<th>Punch <!--<div style="font-size: 60%">scale: '.($benchmarks['punch']).'</div>--></th>
	</tr>';
	$gross=array();	
	#debugarray($results);
	$stats=array();
	foreach ($sides as $side => $name) { // show both sides
		arsort ($results[$side]);
		$totals=array ('count'=>0, 'cost'=>0, 'punch'=>0, 'wins' => 0,);
		#debugarray ($results[$side]);
		$percentpoint=0;
		foreach ($results[$side] as $index => $data) {
			$wins=round($data['total']/$_REQUEST['reps'] * 100, 2);
			$lastpercent=$percentpoint;
			$percentpoint+=$wins;
			$units=$data['stats']['count'];
			$lossdata=assess ($data['lost'], substr(strtolower($name),0, -2));
			#debug ("$wins, total $percentpoint, $index");
			#debug($percentpoint);
			
			//the 1st SD includes 68% of the results centered about the mean or median
			$stdev1=($percentpoint >= 84 && $lastpercent < 84)||($percentpoint >= 16 && $lastpercent < 16);
			//the 2nd SD includes 94% of the results centered about the mean or median
			$stdev2=($percentpoint >= 97 && $lastpercent < 97)||($percentpoint >= 3 && $lastpercent < 3);
			$median=$percentpoint >= 50 && $lastpercent < 50;
			if ($wins >0) $breaks[$side][$index]=probgraph ($wins, showforce($data['force']), $units, showforce($data['lost']), $lossdata['cost'], $median, $stdev1, $stdev2);
			foreach ($statkeys as $key)$totals[$key] += $data['stats'][$key] * $wins;
			$totals['wins']+=$wins;
		}
		#debugarray ($breaks[$side]);
		$astats = array ();
		foreach ($statkeys as $key) {
			if ($totals['wins']!==0) {$astats[$key] = round($totals[$key]/
			100
			#$totals['wins']
			, 1);
#			if ($side=='att'){$astats['punch']=$astats['opunch'];}else{$astats['punch']=$astats['dpunch'];}
			}	else {$astats[$key]=0;}
		}
		$stats[$side]=$astats; //save for later
		$mode=strtolower(substr($sides[$side],0,-2)); //gets 'attack' or 'defend' from "Attacker" or "Defender"
		$astartstats=assess($forces[$side], $mode);
		
		#if ($side=='att'){$astartstats['punch']=$astartstats['opunch'];}else{$astartstats['punch']=$astartstats['dpunch'];}
		$aloststats = array ();
		foreach ($statkeys as $key) $aloststats[$key] = $astartstats[$key] - $astats[$key];
		if ($totals['wins']>100)$totals['wins']=100;
		$gross[$side]=round($totals['wins'],1);
		$survive='<th class="'.$side.$className.'">'.$name.':</th>';
		
		if ($totals['wins'] < 99) {
			$losses=100-$totals['wins'];
			$stdev2=$losses >=3; //2nd standard deviation contains about 95% of the results. thus, 50-(95/2) = 3% when rounded up
			$stdev1=$losses >=16; //1st standard deviation contains about 68% of the results. thus, 50-(68/2) = 16%
			$median=$losses >=50;
			$width=width($losses, 50);
			$lossdata=assess($forces[$side],substr(strtolower($name),0, -2));
			$breaks[$side][0]=probgraph ($losses, 'no units.', 0, showforce($forces[$side]), $lossdata['cost'], $median, $stdev1, $stdev2);
			#'<!--0-->
			#<span style="background: grey; width: '.$width.'px; display: block; float: left; border: 1px solid black; margin: 3px 3px 0 0; font-size: 60%;">&nbsp;</span>
			#0 units: '.$losses.'%: left with <b>nothing</b>.';
		}
		foreach ($benchmarks as $stat => $bmark) {
			$afw=width($astats[$stat],$bmark);
			$alw=width($aloststats[$stat],$bmark);
			$alossw=width($astartstats[$stat],$bmark);
			
			$survive .='<td class="graph">';
			if ($astats[$stat] >0) $survive .='<span class="'.$side.$className.'"style="width:'.$afw.'px; '.$graphstyles.'">'.$astats[$stat].'</span>'; 
			#<span class="noshow">Left:</span> ';
			if ($aloststats[$stat] >0) $survive .='<span class="lost" style="'.$graphstyles.' border: none !important; padding: 1px 0px !important; width:'.($alw-1).'px;">'.$aloststats[$stat].'</span>' ; 
			
			$survive .='</td>';
		}
		#if ($totals['wins'] > 0) 
#		$ics=$astartstats['count']*$astartstats['punch'];
#		$survive.= "<td>count: ".$astartstats['count']." punch: ".$astartstats['punch']. "ICS: $ics</td>";
		$output .= '<tr>'.$survive.'</tr>';
	}
	
	$output .= '
	<tr><td>&nbsp;</td><td><span class="att'.$className.'" style="display: block; float: left; border: 1px solid black; padding: 0 3px; margin: 3px 3px 0 0; font-size: 60%;">#</span> Surviving Attackers <br />
	 </td>
	<td valign="top"><span class="def'.$className.'" style="display: block; float: left; border: 1px solid black; padding: 0 3px; margin: 3px 3px 0 0; font-size: 60%;">#</span> Surviving Defenders</td><td><span class="lost" style="display: block; float: left; border: 1px solid  grey; padding: 0 3px; margin: 3px 3px 0 0; font-size: 60%; ">#</span>Casualties</td></tr>
	<tr><td colspan="4"><hr /></td></tr>
	<tr style="font-weight: bold;">
	<th>Overall %*:</th><td class="att'.$className.'" style="font-weight: bold; padding: 0px 4px;">A. survives: '.$gross['att'].'%</td>
	<td class="def'.$className.'" style="font-weight: bold; padding: 0px 4px;">D. survives: '.$gross['def'].'%</td>
	<td class="lost" style="font-weight: bold; padding: 0px 4px;">No one survives: '.(round($results['draw']/$_REQUEST['reps']*100, 1)).'%</td></tr>
	</table>
	<em>* percentages may not total 100 due to rounding. The average results from above are<span style="background: yellow;"> highlighted </span>in charts below, while the median result (equal odds of getting a worse or better result) is written in <span style="color: red">red</span>. If shown, the 1st and 2nd standard deviations about the mean are represented in <span style="color: blue">blue</span> and <span style="color: dodgerblue">light blue</span>.</em>
	<table>';
	#debugarray ($results);
	foreach ($sides as $side => $name) {
		$output .= '<tr><th colspan="4">'.$name.' results:<hr /></td></tr>
		<tr><th style="text-align: right;">Probability</th><th style="text-align: left;">%</th><th style="text-align: right;">#</th><th style="text-align: left">units</th><th>/ losses</th></tr>
		';
#		<tr><td>';
#		foreach ($breaks[$side] as $line) $output .= "<tr>$line</tr>";
		#$output .= '</ul></td><td><h3>By remaining count</h3><ul>';
		natcasesort($breaks[$side]);
		#debug ('Stats:');
		#debugarray ($stats[$side]);
		#debug ('Avg. Count for '.$side.': '.$stats[$side]['count']);	
		$breaks[$side]=array_reverse($breaks[$side]);
		#debugarray ($r);
		#debug (count($breaks[$side]));
		foreach ($breaks[$side] as $index => $line) {
			$color='background: #eef;';
			$avgcount=round($stats[$side]['count']);
			$count=0;
			if ($index !== 0) $count=$results[$side][$index]['stats']['count'];
			if ($count > $avgcount-1.0 and $count < $avgcount+1.0) $color= 'background: yellow;';
			if ($count !==0 && $color=='') {
				$countratio= $avgcount/ $count;
				if ($countratio >= 0.9 and $countratio <= 1.1) $color= 'background: yellow;';
			}
			$output .= "<tr style=\"font-size: 60%; $color\">$line</tr>
			";
		}
		$output .= '<tr><td colspan=4><hr /></td></tr>';
	}
	$output .= '</table>';
	global $rounds;
	#if ($rounds == '') 
	$output .= showipcdiffs ($results['ipcdiff']);
	#debug ($rounds);
	$output .= scenariolink('mail');
	return $output;
}

//displays the differences in IPC losses between defender and attacker 
function showipcdiffs ($ipcdiff) {
	krsort ($ipcdiff);
	
	$output='<table border=0 >
	<tr><th colspan="3">Defender IPC losses in excess of Attacker IPC losses:</th></tr>
	<tr><th style="text-align: right">Probability</th><th style="text-align: left">%</th><th style="text-align: left">IPC loss differential</th></tr>
	';
	$times=$_REQUEST['reps'];
	foreach ($ipcdiff as $diff => $num) {
		$percent= round ($num/$times * 100,1);
		if ($percent > 0) {
			$width=width($percent, 60);
			$output .='<tr style="font-size: 60%; background: #eef;"><td><span style="background: #999; width: '.$width.'px; display: block; float: right; border: 1px solid black; margin:  0; font-size: 50%;" title="'.$percent.'%">&nbsp;</span></td><td>'.$percent.'%</td><td>'.$diff.' IPCs</td></tr> ';
		}
	}
	$output.='</table>';
	return $output;
	debugarray($ipcdiff);
		
}

//error message to indicate AACalc was not run due to certain conditions
function nobattle () {
	echo '<p>Sorry, cannot run battle. Possible reasons:</p>
	<ul>
	<li>Attacker and/or Defender has no units.</li>
	<li>Both land and sea units requested.</li>
	<li>Attacker has combination of artillery and advanced artillery, subs and super subs, fighters and jet fighters, or bombers and heavy bombers.</li>
	</ul>';
}
?>
