<?php
/*	Initializes the AACalc form. */

/**
 *	name: init.php
 *
 *	PHP version: 5.3.1
 *
 *	description: Initializes the AACalc form using a referenced stylesheet and a list of unit stats.
 *		Unit statistics include the name of each unit for Axis and Allies and it's respective
 *		attack, defense, cost and other unit specifics. This also addresses casualties with what units
 *		can hit other units and a detailed Order of Loss (OOL) list.
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

if (isset($headextra)) {
	$headextra.='<link rel="stylesheet" type="text/css" media="screen" title="Normal" href="aa.css" />';
} else {
	echo '<html><head><title>AACalc</title><link rel="stylesheet" type="text/css" media="screen" title="Normal" href="aa.css" /></head><body>';
}

$t=0; $b=0; $rounds=20; $stalemate=false;$resetrounds=false;
$round=1;
$att=array(); $def=array();	$history=array();
$sides=array('att' => 'Attacker', 'def' => 'Defender');

//array of each unit's stats for the default Axis and Allies ruleset in AACalc
$unitspecs=array(
	'Inf' => array(
		'cost' => 3,
		'attack' => 1,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 2,
		'speed' => 1,
		'hp' => 1,
		'name' => 'Infantry',
	),
	'Art' => array(
		'cost' => 4,
		'attack' => 2,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 2,
		'speed' => 1,
		'hp' => 1,
		'name' => 'Artillery',
	),
	'AArt' => array(
		'cost' => 4,
		'attack' => 2,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 2,
		'speed' => 1,
		'hp' => 1,
		'name' => 'Advanced artillery',
	),
	'Arm' => array(
		'cost' => 5,
		'attack' => 3,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 3,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Armor',
	),
	'Fig' => array(
		'cost' => 10,
		'attack' => 3,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 4,
		'speed' => 4,
		'hp' => 1,
		'name' => 'Fighters',
	),
	'JFig' => array(
		'cost' => 10,
		'attack' => 4,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 4,
		'speed' => 4,
		'hp' => 1,
		'name' => 'Jet fighters',
	),
	'Bom' => array(
		'cost' => 12,
		'defenddice' => 1, 'attackdice' => 1,
		'attack' => 4,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 1,
		'speed' => 6,
		'hp' => 1,
		'name' => 'Bombers',
	),
	'HBom' => array(
		'cost' => 12,
		'attack' => 4,
		'defenddice' => 1, 'attackdice' => 2,
		'defend' => 1,
		'speed' => 6,
		'hp' => 1,
		'name' => 'Heavy bombers',
	),
	'Tra' => array(
		'cost' => 7,
		'attack' => 0,
		'defenddice' => 0, 'attackdice' => 0,
		'defend' => 0,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Transports',
	),
	'Car' => array(
		'cost' => 14,
		'attack' => 1,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 2,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Carriers',
	),
	'Sub' => array(
		'cost' => 6,
		'attack' => 2,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 1,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Submarines',
	),
	'SSub' => array(
		'cost' => 6,
		'attack' => 3,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 1,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Super submarines',
	),
	'Des' => array(
		'cost' => 8,
		'attack' => 2,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 2,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Destroyers',
	),
	'Cru' => array(
		'cost' => 12,
		'attack' => 3,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 3,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Cruisers',
	),
	'Bat' => array(
		'cost' => 20,
		'attack' => 4,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 4,
		'speed' => 2,
		'hp' => 2,
		'name' => 'Battleships',
	),
	'dBat' => array( //this is an extra unit for every battleship
		'cost' => 20,
		'attack' => 4,
		'defenddice' => 1, 'attackdice' => 1,
		'defend' => 4,
		'speed' => 2,
		'hp' => 1,
		'name' => 'Damaged battleships',
	),
);

//array of the types of units that can be selected as casualties under certain gameplay situations
$canhit=array(
	'sub'  => array('Bat', 'Sub', 'Tra', 'SSub', 'Des', 'Cru', 'Car', 'dBat'),
	'air' => array('Bom', 'Fig', 'JFig', 'HBom'),
	'land' => array('Inf', 'Art', 'AArt', 'Arm', 'Bom', 'Fig', 'JFig', 'HBom'),
	'sea' => array('Bom', 'Fig', 'JFig', 'HBom','Bat', 'Sub', 'Tra', 'SSub', 'Des', 'Cru', 'Car', 'dBat'),
	'seanosub' => array('Bom', 'Fig', 'JFig', 'HBom','Bat', 'Tra', 'Des', 'Cru', 'Car', 'dBat'),
	'ground' => array('Inf', 'Art', 'AArt', 'Arm'),
);

//arrays that list, in order, the units to be selected as casualties first. this is the Order of Loss (OOL)
$baseool=array('Bat', 'Inf', 'Art', 'AArt', 'Arm', 'Sub', 'SSub', 'Bom', 'HBom', 'Des', 'Cru', 'Fig', 'JFig',  'Car', 'dBat', 'Tra');
$ool=array(
	'att' => array('Bat', 'Inf', 'Art', 'AArt', 'Arm', 'Sub', 'SSub', 'Des', 'Fig', 'JFig', 'Cru', 'Bom', 'HBom', 'Car', 'dBat', 'Tra'),
	'def' => array('Bat', 'Inf', 'Art', 'AArt', 'Arm', 'Bom', 'HBom', 'Sub', 'SSub', 'Des', 'Car', 'Cru', 'Fig', 'JFig', 'dBat', 'Tra'),
);

?>
