<?php
/*	The AACalc form. */

/**
 *	name: form.php
 *
 *	PHP version: 5.3.1
 *
 *	description: Sets up server side variables for form input and echos the AACalc form to the browser. 
 *		Users will be able to input Axis & Allies attacking and defending units along with battle
 *		options on the form. Submitting the form sends these values to other functions on the PHP side.
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

//set the $tech variable to later enable or disable tech units on the form
$tech=((isset($_REQUEST['toggletechs'])
	&&($_REQUEST['toggletechs']=="Enable techs"))
	or (isset($_REQUEST['techs'])
	&& (!isset($_REQUEST['toggletechs'])
	or $_REQUEST['toggletechs']!=='Disable techs'))
);

$chk=' checked';

//MAY NOT NEED
/*$gameid='';if (isset($_REQUEST['gameid'])) $gameid=$_REQUEST['gameid'];
$password='';if (isset($_REQUEST['password'])) $password=$_REQUEST['password'];
$turnid='';if (isset($_REQUEST['turnid'])) $turnid=$_REQUEST['turnid'];*/

$luckselect="";
$luckmodes=array(
	'pure'=>'Pure Luck',
	'low'=>'Low Luck',
	'none'=>'No Luck',
);
//sets $luckselect string to HTML radio buttons to be put on the form later on...
foreach ($luckmodes as $mode=>$label) {
	$luckselect .= '<input name="luck" type="radio" value="'.$mode.'"';
	if ((isset($_REQUEST['luck']) && $_REQUEST['luck']==$mode) or (!isset($_REQUEST['luck']) && $mode=='pure')) $luckselect .= ' checked';
	$luckselect .= '/>'.$label.'<br />';
}

$repmodes=array(
	'1'=>'Once',
	'1000'=>'1,000x',
	'5000'=>'5,000x',
	'10000'=>'10,000x'
);
//sets $repselect string to HTML radio buttons to be put on the form later on...
$repselect="";
foreach ($repmodes as $mode=>$label) {
	$repselect .= '<input name="reps" type="radio" value="'.$mode.'"';
	if ((isset($_REQUEST['reps']) && $_REQUEST['reps']==$mode) or (!isset($_REQUEST['reps']) && $mode=='1') ) $repselect .= ' checked';
	$repselect .= ' />'.$label.'<br />';
}
$roundsmodes=array(
	'1'=>'one',
	'2'=>'two',
	'3'=>'three',
	''=>'all',
);
//sets $roundselect string to HTML radio buttons to be put on the form later on...
$roundsselect="";
foreach ($roundsmodes as $mode=>$label) {
	$roundsselect .= '<input name="rounds" type="radio" value="'.$mode.'"';
	if ((isset($_REQUEST['rounds']) && $_REQUEST['rounds']==$mode) or (!isset($_REQUEST['rounds']) && $mode=='1') ) $roundsselect .= ' checked';
	$roundsselect .= ' />'.$label.'<br />';
}

//set defaults for other battle options like land units to die last/strafe/punch ratio...
$mustland=0;
if (isset($_REQUEST['mustland'])  && $_REQUEST['mustland']!=='' && !isset($_REQUEST['Clear'])) {
	$_REQUEST['mustland']=intval($_REQUEST['mustland']);
	$mustland=$_REQUEST['mustland'];
}
$abortratio=0;
if (isset($_REQUEST['abortratio'])  && $_REQUEST['abortratio']!=='' && !isset($_REQUEST['Clear'])) {
	$_REQUEST['abortratio']=intval($_REQUEST['abortratio']);
	$abortratio=$_REQUEST['abortratio'];
}
$saveunits=0;
if (isset($_REQUEST['saveunits'])  && $_REQUEST['saveunits']!=='' && !isset($_REQUEST['Clear'])) {
	$_REQUEST['saveunits']=intval($_REQUEST['saveunits']);
	$saveunits=$_REQUEST['saveunits'];
}
$strafeunits=0;
if (isset($_REQUEST['strafeunits'])  && $_REQUEST['strafeunits']!=='' && !isset($_REQUEST['Clear'])) {
	$_REQUEST['strafeunits']=intval($_REQUEST['strafeunits']);
	$strafeunits=$_REQUEST['strafeunits'];
}


$aachecked=''; $aarchecked='';
$asubschickenchecked=''; $dsubschickenchecked='';
$pbem='';
$territory='';
if (isset($_REQUEST['pbem'])) {
	if ( is_array ($_REQUEST['pbem'])) {$pbem=implode (' ', $_REQUEST['pbem']); }
	else { $pbem=$_REQUEST['pbem'];}
}
if (isset($_REQUEST['territory']) && !isset ($_REQUEST['Clear'])) $territory=$_REQUEST['territory'];
if (!isset($_REQUEST['round']) or isset($_REQUEST['Clear'])) $_REQUEST['round']=1;

global $unitspecs, $rounds, $round;
global $sides, $ool, $savedool;

//call the next two functions to set the unit label tips/units/OOLs by ruleset.
updateunits();
updateools();

if (isset($savedool)) $ool=$savedool;

if (isset($_REQUEST['AA']) && !isset($_REQUEST['Clear']) && ($_REQUEST['reps']!==1 or $rounds==20)) $aachecked=$chk;
if (isset($_REQUEST['AAr']) && !isset($_REQUEST['Clear']) && ($_REQUEST['reps']!==1 or $rounds==20)) $aarchecked=$chk;

if (isset($_REQUEST['asubschicken'])  && !isset($_REQUEST['Clear'])) $asubschickenchecked=$chk;
if (isset($_REQUEST['dsubschicken'])  && !isset($_REQUEST['Clear'])) $dsubschickenchecked=$chk;

if (isset($_REQUEST['rounds']) && $_REQUEST['rounds']!=='' && !isset($_REQUEST['Clear'])) {
	if (isset($options['average']) and !$options['average']) $aachecked=''; 
	$rounds=intval($_REQUEST['rounds']);
	}
if ($rounds==20) $rounds='';
global $resetrounds, $fixrounds;
if ($resetrounds) {
	$_REQUEST['round']=1;
	$territory='';
} else if (isset($_REQUEST['round']) && isset ($_REQUEST['battle']) && $_REQUEST['battle']=='Run' && !isset($_REQUEST['Clear']) && $rounds !== 20 && isset ($_REQUEST['reps']) && $_REQUEST['reps']==1 && !isset($fixrounds))  {
	$_REQUEST['round']+=$rounds;
}

global $pageurl;

//start of the form
echo '
<form  method="GET" action="'.$pageurl.'" style="margin-bottom: 0;"> 
	<table id="battle" border="0" cellspacing="0" cellpadding="0" style=" background: white;" >
		<tr>
			<td><a href="'.$pageurl.'">total reset</a>
			</td>
			<th colspan="16" style="text-align: center; font-size: 60%">tip: hover over unit labels to see the full name and stats for each unit
			</th>
			<td rowspan="8" valign="top" style="background: white; color: black; font-size: 60%">
				<input type="text" size="1" name="mustland" value='.$mustland.' style="width: 2ex; text-align: center;"/> Atk. Tra / land units die last<br />
				<input type="text" size="1" name="abortratio" maxlength=3 value='.$abortratio.' style="width: 2ex; text-align: center;" title="Attack will abort once punch falls below this percentage of the defender\'s count or punch"/>% min. atk. punch ratio<br />
				<input type="text" size="1" name="saveunits" value='.$saveunits.' style="width: 2ex; text-align: center;"/> A. units must survive<br />
				<input type="text" size="1" name="strafeunits" value='.$strafeunits.' style="width: 2ex; text-align: center;"/> D. units to be left alive (strafe)<br />
				
				<input type="checkbox" name="asubschicken" '.$asubschickenchecked.' />Att. subs chicken<br />
				<input type="checkbox" name="dsubschicken" '.$dsubschickenchecked.' />Def. subs chicken<br />
				<input type="checkbox" id="AA" name="AA" '.$aachecked.' onclick="uncheck(\'AAr\')" title="aa guns fire at a 1"/>AA gun 
				<input type="checkbox" id="AAr" name="AAr" '.$aarchecked.' onclick="uncheck(\'AA\')" title="aa guns fire at a 2"';
//get class set for AAradar checkbox to show or hide based on ruleset
if (isset($_REQUEST['ruleset'])) {
	if 	((($_REQUEST['ruleset'] == 'AA50') or ($_REQUEST['ruleset'] == 'Revised')) && ($tech)) {
		echo ' class=""/><span id="txtAAr" class="">AA radar</span><br /><br />';
	} else {
		echo ' class="noshow"/><span id="txtAAr" class="noshow">AA radar</span><br /><br />';
	}
} else {
	echo ' class="noshow"/><span id="txtAAr" class="noshow">AA radar</span><br /><br />';
}
echo '			<input type="submit" name="battle" value="Evaluate units" /><br />
				<input type="submit" name="battle" value="Swap units" /> <br />
				<input type="submit" name="Clear" value="Clear units/OOLs" /><br />
';

$units=allunits();
if ($tech) {
	echo '<input type="submit" id="toggletechs" name="toggletechs" value="Disable techs" />
	<input type="hidden" name="techs" value="on" />';
} else {
	echo '<input type="submit" id="toggletechs" name="toggletechs" value="Enable techs" />';
	$units=notechunits();
}

#debugarray ($units);

echo '		</td>
		</tr>
		<tr style="font-size: 55%; text-align: center;"> 
			<td>&nbsp;</td>
';

//set class suffix to change colors and units for rulesets on PHP-side generated form
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

$labels='';
$afields='';
$dfields='';
foreach ($units as $type) {	
	//html title text to list unit name, offense, defense, and IPC cost
	$labels .='
			<td id="'.$type.'"';
	//set class to hide if the unit is not in a ruleset for the unit labels
	if (!isset($_REQUEST['ruleset'])) $_REQUEST['ruleset']='AA1942';
	if 	($_REQUEST['ruleset'] == 'AA1942') {
		if ($type=='AArt'||$type=='JFig'||$type=='HBom'||$type=='SSub')
		{	$labels .=' class="noshow"';
		}
	}
	if 	($_REQUEST['ruleset'] == 'Classic') {
		if ($type=='Art'||$type=='AArt'||$type=='Des'||$type=='Cru'||$type=='dBat')
		{	$labels .=' class="noshow"';
		}
	}
	if 	($_REQUEST['ruleset'] == 'Revised') {
		if ($type=='AArt'||$type=='Cru')
		{	$labels .=' class="noshow"';
		}
	}		

	$labels .=' title="'.$unitspecs[$type]['name'];
	
	//indicate for HBom additional power on attack in the html tip
	if ($type=='HBom')
	{	$labels .=' A:'.$unitspecs[$type]['attack'].'x'.$unitspecs[$type]['attackdice'];
	} else {
		$labels .=' A:'.$unitspecs[$type]['attack'];
	}
	
	$labels .=' D:'.$unitspecs[$type]['defend'].' cost:'.$unitspecs[$type]['cost'].'">'.$type.'
			</td> ';
	
	$count='';
	if (isset($aforce[$type])) $count= $aforce[$type];
	$afields .='
			<td id="a_'.$type.'"';
	
	//again set class to hide if the unit is not in a ruleset for the attacker fields		
	if 	($_REQUEST['ruleset'] == 'AA1942') {
		if ($type=='AArt'||$type=='JFig'||$type=='HBom'||$type=='SSub')
		{	$afields .=' class="noshow"';
		}
	}	
	if 	($_REQUEST['ruleset'] == 'Classic') {
		if ($type=='Art'||$type=='AArt'||$type=='Des'||$type=='Cru'||$type=='dBat')
		{	$afields .=' class="noshow"';
		}
	}
	if 	($_REQUEST['ruleset'] == 'Revised') {
		if ($type=='AArt'||$type=='Cru')
		{	$afields .=' class="noshow"';
		}
	}		
	$afields .=' style="text-align:center;">
				<input type="text" id="txta_'.$type.'" name="a'.$type.'" style="width: 3ex;"  maxlength="3" class="units" value="'.$count.'" /> 
			</td>'; 
	$count='';
	if (isset($dforce[$type])) $count= $dforce[$type];
	$dfields .='
			<td id="d_'.$type.'"';
			
	//again set class to hide if the unit is not in a ruleset for the defender fields		
	if 	($_REQUEST['ruleset'] == 'AA1942') {
		if ($type=='AArt'||$type=='JFig'||$type=='HBom'||$type=='SSub')
		{	$dfields .=' class="noshow"';
		}
	}	
	if 	($_REQUEST['ruleset'] == 'Classic') {
		if ($type=='Art'||$type=='AArt'||$type=='Des'||$type=='Cru'||$type=='dBat')
		{	$dfields .=' class="noshow"';
		}
	}
	if 	($_REQUEST['ruleset'] == 'Revised') {
		if ($type=='AArt'||$type=='Cru')
		{	$dfields .=' class="noshow"';
		}
	}
	$dfields .=' style="text-align:center;">
				<input type="text" id="txtd_'.$type.'" name="d'.$type.'" style="width: 3ex;" maxlength="3" class="units" value="'.$count.'" /> 
			</td>'; 
}

echo $labels.'
		</tr>
		<tr id="Attacker" class="att'.$className.'">
			<th style="text-align: right">Attack:</th>'.$afields.'
		</tr>
';

echo '
		<tr id="Defender" class="def'.$className.'">
			<th  style="text-align: right">Defend: </th>'.$dfields.'
		</tr>
';


// Show OOL edit fields
foreach ($sides as $side => $name)
{
	echo '
		<tr id="'.$side.'OOL" class="'.$side.$className.'">
			<th title="Order of Loss" style="text-align: right">'.substr($name,0,1).'.&nbsp;OOL:</th>
			<td colspan=16 style ="padding-left: 4px;" >
				<input type="text" id="ool_'.$side.'" name="ool_'.$side.'" style="width: 36.5em; text-align: center; margin: 3px; font-size: 60%" value="'.(implode('-',$ool[$side])).'" />
			</td>
		</tr>';
}

echo '	
		<tr>
			<td colspan=16 style="text-align: center; font-size: 80%">
			<table style="text-align: left">
		<tr>
			<td>
				<input type="submit" name="battle" value="Run">
			</td>
			<td>
				'.$roundsselect.'
			</td>

			<td><em>rounds<br />of<br />combat</em>
			</td>

			<td>
				'.$repselect.' 
			</td>
			
			<td><em>using </em>
			</td>

			<td>
				'.$luckselect.' 
			</td>

			<td><em>for </em>
			</td>

			<td>
';

$rulesets=array ('AA1942', 'AA50', 'Revised', 'Classic', );
foreach ($rulesets as $ruleset)
{
	echo '<input type="radio" name="ruleset" ';
	if ((isset($_REQUEST['ruleset']) && $ruleset==$_REQUEST['ruleset']) or (!isset($_REQUEST['ruleset']) && $ruleset=='AA1942')) echo ' checked';
	//onclick value calls javascript to change colors client side
	echo " value=\"$ruleset\" onclick=\"ChangeColors('$ruleset')\" />$ruleset<br />";
}
echo '		</td>
		</tr>
	</table>
	
	</td></tr>
	<tr>
	<!--<td><b>PBEM<br />options:</b></td>-->
		<td colspan=16 style="text-align: center">
			Battle territory: <input type="text" name="territory"
			title="Enter comments such as the name of the territory being attacked."
			value="'.$territory.'" style="width: 18ex;" />
			Battle round #: <input type="text" name="round"
			title="Enter the round of combat to start at" value="'.$_REQUEST['round'].'" style="width: 3ex; text-align: center;"/>
			<br />E-mail result to: <input type="text" name="pbem"
			title="Enter one to five e-mail addresses separated by spaces" value="'.$pbem.'" style="width: 37ex;"/>
		</td>
	</tr>
	</table>
</form>';

?>



