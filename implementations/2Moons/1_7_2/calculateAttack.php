<?php

/**
 *  OPBE
 *  Copyright (C) 2013  Jstar
 *
 * This file is part of OPBE.
 * 
 * OPBE is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * OPBE is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with OPBE.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OPBE
 * @author Jstar <frascafresca@gmail.com>
 * @copyright 2013 Jstar <frascafresca@gmail.com>
 * @license http://www.gnu.org/licenses/ GNU AGPLv3 License
 * @version alpha(2013-2-4)
 * @link https://github.com/jstar88/opbe
 */
define('PATH', ROOT_PATH . 'includes/libs/opbe/');
include ('PATH' . 'utils/includer.php');

define('ID_MIN_SHIPS', 100);
define('ID_MAX_SHIPS', 300);
define('HOME_FLEET', 0);
define('DEFENDERS_WON', 'r');
define('ATTACKERS_WON', 'a');
define('DRAW', 'w');
define('METAL_ID', 901);
define('CRYSTAL_ID', 902);


/**
 * calculateAttack()
 * Calculate the battle using OPBE
 * 
 * @param array &$attackers
 * @param array &$defenders
 * @param mixed $FleetTF
 * @param mixed $DefTF
 * @return array
 */
function calculateAttack(&$attackers, &$defenders, $FleetTF, $DefTF)
{
    $CombatCaps = $GLOBALS['CombatCaps'];
    $pricelist = $GLOBALS['pricelist'];

    /********** BUILDINGS MODELS **********/
    //attackers
    $attackerGroupObj = new PlayerGroup();
    foreach ($attackers as $fleetID => $attacker)
    {
        $player = $attacker['player'];
        $attackerPlayerObj = $attackerGroupObj->createPlayerIfNotExist($player['id'], array(), $player['military_tech'], $player['shield_tech'], $player['defence_tech']);
        $attackerFleetObj = new Fleet($fleetID);
        foreach ($attacker['unit'] as $element => $amount)
        {
            $fighters = getFighters($element, $amount);
            $attackerFleetObj->add($fighters);
        }
        $attackerPlayerObj->addFleet($attackerFleetObj);
    }
    //defenders
    $defenderGroupObj = new PlayerGroup();
    foreach ($defenders as $fleetID => $defender)
    {
        $player = $attacker['player'];
        $defenderPlayerObj = $defenderGroupObj->createPlayerIfNotExist($player['id'], array(), $player['military_tech'], $player['shield_tech'], $player['defence_tech']);
        $defenderFleetObj = getFleet($fleetID);
        foreach ($defender['unit'] as $element => $amount)
        {
            $fighters = getFighters($element, $amount);
            $defenderFleetObj->add($fighters);
        }
        $defenderPlayerObj->addFleet($defenderFleetObj);
    }

    /********** BATTLE ELABORATION **********/
    $opbe = new Battle($attackerGroupObj, $defenderGroupObj);
    $opbe->startBattle();
    $report = $opbe->getReport();

    /********** WHO WON **********/
    if ($report->defenderHasWin())
    {
        $won = DEFENDERS_WON;
    }
    elseif ($report->attackerHasWin())
    {
        $won = ATTACKERS_WON;
    }
    else
    {
        $won = DRAW;
    }

    /********** ROUNDS INFOS **********/

    $ROUND = array();
    $i = 1;
    for (; $i <= $report->getLastRoundNumber(); $i++)
    {
        $attackerGroupObj = $report->getPresentationAttackersFleetOnRound($i);
        $defenderGroupObj = $report->getPresentationDefendersFleetOnRound($i);
        $attackAmount = $attackerGroupObj->getTotalCount();
        $defenseAmount = $defenderGroupObj->getTotalCount();
        $attArray = updatePlayers($attackerGroupObj, $attackers, 'detail');
        $defArray = updatePlayers($defenderGroupObj, $defenders, 'def');
        $ROUND[$i - 1] = roundInfo($report, $attackers, $defenders, $attackerGroupObj, $defenderGroupObj, $i, $attArray, $defArray);

    }
    //after battle
    $attackerGroupObj = $report->getAfterBattleAttackers();
    $defenderGroupObj = $report->getAfterBattleDefenders();
    $attArray = updatePlayers($attackerGroupObj, $attackers, 'detail');
    $defArray = updatePlayers($defenderGroupObj, $defenders, 'def');
    $ROUND[$i - 1] = roundInfo($report, $attackers, $defenders, $attackerGroupObj, $defenderGroupObj, 'END', $attArray, $defArray);

    /********** DEBRIS **********/
    //attackers
    $debAtt = $report->getAttackerDebris();
    $debAttMet = $debAtt[0];
    $debAttCry = $debAtt[1];
    //defenders
    $debDef = $report->getDefenderDebris();
    $debDefMet = $debDef[0];
    $debDefCry = $debDef[1];
    //total
    $debris = array('attacker' => array(METAL_ID => $debAttMet, CRYSTAL_ID => $debAttCry), 'defender' => array(METAL_ID => $debDefMet, CRYSTAL_ID => $debDefCry));

    /********** LOST UNITS **********/
    $totalLost = array('attacker' => $report->getTotalAttackersLostUnits(), 'defender' => $report->getTotalDefendersLostUnits());


    /********** RETURNS **********/
    return array(
        'won' => $won,
        'debris' => $debris,
        'rw' => $ROUND,
        'unitLost' => $totalLost);
}


/**
 * roundInfo()
 * Return the info required to fill $ROUND
 * @param BattleReport $report
 * @param array $attackers
 * @param array $defenders
 * @param PlayerGroup $attackerGroupObj
 * @param PlayerGroup $defenderGroupObj
 * @param int $i
 * @return array
 */
function roundInfo(BattleReport $report, $attackers, $defenders, PlayerGroup $attackerGroupObj, PlayerGroup $defenderGroupObj, $i, $attArray, $defArray)
{
    return array(
        'attack' => ($i == 'END') ? 0 : $report->getAttackersFirePower($i),
        'defense' => ($i == 'END') ? 0 : $report->getDefendersFirePower($i),
        'defShield' => ($i == 'END') ? 0 : $report->getDefendersAssorbedDamage($i),
        'attackShield' => ($i == 'END') ? 0 : $report->getAttachersAssorbedDamage($i),
        'attackers' => $attackers,
        'defenders' => $defenders,
        'attackA' => $attackerGroupObj->getTotalCount(),
        'defenseA' => $defenderGroupObj->getTotalCount(),
        'infoA' => $attArray,
        'infoD' => $defArray);
}


/**
 * updatePlayers()
 * Update players array as default 2moons require
 * 
 * @param PlayerGroup $playerGroup
 * @param array &$players
 * @return null
 */
function updatePlayers(PlayerGroup $playerGroup, &$players, $indexShipsName)
{
    $plyArray = array();

    foreach ($players as $idFleet => $info)
    {
        $shipInfo = $info[$indexShipsName];
        $player = $playerGroup->getPlayer($info['player']['id']);
        $fleet = $player->getFleet($idFleet);

        foreach ($shipInfo as $idFighters => $amount)
        {
            $fighters = $fleet->getFighters($idFighters);
            $players[$idFleet][$indexShipsName][$idFighters] = ($fleet !== false) ? $fighters->getCount() : 0;
            $plyArray[$idFleet][$idFighters] = array(
                'def' => $fighters->getHull() * $fighters->getCount(),
                'shield' => $fighters->getShield() * $fighters->getCount(),
                'att' => $fighters->getPower() * $fighters->getCount());
        }
        $players[$idFleet]['techs'] = array(
            $player->getWeaponsTech(),
            $player->getArmourTech(),
            $player->getShieldsTech());
    }
    return $plyArray;
}


/**
 * getFighters()
 * Choose the correct class type by ID
 * 
 * @param int $id
 * @param int $count
 * @return a Ship or Defense instance
 */
function getFighters($id, $count)
{
    $CombatCaps = $GLOBALS['CombatCaps'];
    $pricelist = $GLOBALS['pricelist'];
    $rf = $CombatCaps[$id]['sd'];
    $shield = $CombatCaps[$id]['shield'];
    $cost = array($pricelist[$id]['cost'][METAL_ID], $pricelist[$id]['cost'][CRYSTAL_ID]);
    $power = $CombatCaps[$id]['attack'];
    if ($id > ID_MIN_SHIPS && $id < ID_MAX_SHIPS)
    {
        return new Ship($id, $count, $rf, $shield, $cost, $power);
    }
    return new Defense($id, $count, $rf, $shield, $cost, $power);
}


/**
 * getFleet()
 * Choose the correct class type by ID
 * 
 * @param int $id
 * @return a Fleet or HomeFleet instance
 */
function getFleet($id)
{
    if ($id == HOME_FLEET)
    {
        return new HomeFleet(HOME_FLEET);
    }
    return new Fleet($id);
}

?>