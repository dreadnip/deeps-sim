<?php
function load_abilities(){

	//open up the json file and read it's content
	$db = file_get_contents("abilities.json");
	$json = json_decode($db);

	//prep the abilities; loop over them and calculate average effective dmg & dmg per tick
	//set a property on-cooldown to 0 for later
	foreach($json->abilities as $style){

		foreach($style as &$ability){
			if($ability->min_damage  == false){
				$ability->min_damage = ($ability->max_damage * 0.20);
			}

			$ability->on_cd = 0;
			$ability->average_damage = (($ability->max_damage - $ability->min_damage)/2)+$ability->min_damage;
			$ability->tick_damage = $ability->average_damage / $ability->cast_duration;
		}
	}

	return $json->abilities;
}

//basic dps algho
//this is basically a PHP implementation of: http://runescape.wikia.com/wiki/Calculator:Revolution/Details
//with a few minor changes and thresholds thrown in

function sim_dps($style, $ticks, $walking = false, $stun_immune = false){
	global $abilities;
	$time = 0;
	$incr = 0;
	$adrenaline = 0;
	$result = [];
	$rotation = [];
	$damage = 0;
	$stunned = 0;
	$broke = false;
	$next = null;

	//update walking damage values
	foreach($abilities->{$style} as &$ability){
		if($walking == true && $ability->walk_bonus > 0){
			$ability->tick_damage = ($ability->tick_damage * $ability->walk_bonus);
			$ability->average_damage = ($ability->average_damage * $ability->walk_bonus);
		}
	}

  	while($time < $ticks){ //number of ticks to run for

  		//reduce cooldown on all abilities by incr (but not below 0)
  		foreach($abilities->{$style} as &$ability){
  			if($ability->on_cd - $incr <= 0){
  				$ability->on_cd = 0;
  			}else{
  				$ability->on_cd -= $incr;
  			}
  		}

  		//reduce stunned by incr (but not below 0)
  		if($stunned - $incr <= 0){
  			$stunned  = 0;
  		}else{
  			$stunned -= $incr;
  		}

  		//reset next
  		$next = new stdClass();

  		//find the strongest available ability
  		foreach($abilities->{$style} as $abil_index => &$ability){

  			//hackerino hackerino save my scripterino
  			(isset($next->tick_damage)) ? '' : $next->tick_damage = 0;

  			//determine thresh or basic based on adren
  			if($adrenaline >= 50){
  				if($ability->type == 'threshold' && $ability->on_cd == 0 && $ability->tick_damage > $next->tick_damage){
  					$next = $ability;
  				}
  			}else{
  				if($ability->type == 'basic' && $ability->on_cd == 0 && $ability->tick_damage > $next->tick_damage){
  					$next = $ability;
  				}
  			}
  		}
  		
  		//activate the selected ability ($next)
  		$next->on_cd = $next->cooldown;
  		$incr = $next->cast_duration;
  		$time += $incr;
  		($next->type == 'basic') ? $adrenaline += 8 : $adrenaline -= 15;
  		array_push($rotation, $next->slug);
  		
  		if($stun_immune == false && $stunned > 0 && $next->stun_damage != false){
  			$damage += $next->stun_damage;
  		}elseif($walking == true && $next->walk_bonus != false){
  			$damage += ($next->average_damage * $next->walk_bonus);
  		}else{
  			$damage += $next->tick_damage;
  		}
  		
  		if($next->stun_duration != false){
  			$stunned = $next->stun_duration;
  		}
  	}

  	if($broke == false){
  		$result['rotation'] = $rotation;
  		$result['total_ability_damage'] = round($damage*100, 2);
  		$result['ticks'] = $time;
  		$result['average_damage_per_tick'] = round(($damage / $time)*100, 2);
  		$result['dps'] = round((($damage / $time) * 2013)/0.6, 2);
  		return $result;
  	}else{
  		return 'not working';
  	}
}

$abilities = load_abilities();
$result = sim_dps('ranged', 500, true, false); //style, duration(in ticks) | optional: walking(true/false), stun immune(true/false);
echo json_encode($result);
