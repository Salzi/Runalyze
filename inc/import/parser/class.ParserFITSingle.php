<?php
/**
 * This file contains class::ParserFITSingle
 * @package Runalyze\Import\Parser
 */

use Runalyze\Configuration;

/**
 * Parser for FIT files from ANT
 *
 * @author Hannes Christiansen
 * @package Runalyze\Import\Parser
 */
class ParserFITSingle extends ParserAbstractSingle {
	/**
	 * Current line
	 * @var string
	 */
	protected $Line = '';

	/**
	 * Current header
	 * @var array
	 */
	protected $Header = array();

	/**
	 * Current values
	 * @var array
	 */
	protected $Values = array();

	/**
	 * Total pause time
	 * @var int
	 */
	protected $PauseInSeconds = 0;

	/**
	 * Is this a swimming activity?
	 * @var bool
	 */
	protected $isSwimming = false;

	/**
	 * Is paused?
	 * @var boolean
	 */
	protected $isPaused = false;

	/**
	 * Was paused?
	 * @var bool
	 */
	protected $wasPaused = false;

	/**
	 * Timestamp of last stop
	 * @var int
	 */
	protected $lastStopTimestamp = false;

	/**
	 * Pauses to apply
	 * @var array
	 */
	protected $pausesToApply = array();
	
	const SPORT_SWIMMING_INSIDE = 1;
	const SPORT_SWIMMING_OUTSIDE = 2;

	/**
	 * Parse
	 */
	public function parse() {
		// Uses another interface to not hold the complete file
	}

	/**
	 * Start a new activity at current point
	 */
	public function startNewActivity() {
		$creator = $this->TrainingObject->getCreator();

		$this->TrainingObject = new TrainingObject(DataObject::$DEFAULT_ID);
		$this->TrainingObject->setTimestamp(PHP_INT_MAX);
		$this->TrainingObject->setCreator($creator);

		$this->isPaused = false;
		$this->isSwimming = false;

		foreach (array_keys($this->gps) as $key) {
			$this->gps[$key] = array();
		}
	}

	/**
	 * Finish parsing
	 */
	public function finishParsing() {
		$this->applyPauses();
		$this->switchTimeArraysForSwimming();
		$this->setGPSarrays();
	}
	
	protected function switchTimeArraysForSwimming() {
	    if($this->isSwimming == self::SPORT_SWIMMING_INSIDE) {
		$tmpTime = $this->gps['time_in_s'];
		$this->gps['time_in_s'] = $this->gps['swimtime'];
		$this->gps['swimtime'] = $tmpTime;
	    }
	}

	/**
	 * Parse line
	 */
	public function parseLine($line) {
		if ($line[0] == '=') {
			if (empty($this->Header)) {
				$this->setHeaderFrom($line);
			} else {
				$this->interpretCurrentValues();
				$this->Header = array();
				$this->Values = array();
			}
		} elseif ($line[0] == '-') {
			$this->addValueFrom($line);
		} elseif ($line[0] == '#') {
			// Unimportant
		}
	}

	/**
	 * Set header
	 * @param string $line
	 */
	protected function setHeaderFrom($line) {
		$MessageInfo = explode(' ', substr($line, 2));

		foreach ($MessageInfo as $Info) {
			$Info = explode('=', $Info);
			$this->Header[$Info[0]] = $Info[1];
		}
	}

	/**
	 * Add value
	 * @param string $line
	 */
	protected function addValueFrom($line) {
		$line = substr($line, 4);
		$values = explode('=', $line);

		if (count($values) == 3)
			$this->Values[$values[0]] = array($values[1], $values[2]);
	}

	/**
	 * Interpret values
	 */
	protected function interpretCurrentValues() {
		if (isset($this->Header['NAME'])) {
			switch ($this->Header['NAME']) {
				case 'file_id':
					$this->readFileId();
					break;

				case 'file_creator':
					break;
				case 'device_info':
					$this->readDeviceInfo();
					break;

				case 'event':
					$this->readEvent();
					break;

				case 'record':
					$this->readRecord();
					break;

				case 'hrv':
					$this->readHRV();
					break;
				    
				case 'hr':
					$this->readHR();
					break;

				case 'lap':
					$this->readLap();
					break;

				case 'session':
					$this->readSession();
					break;

				case 'length':
					$this->readLength();
					break;

				case 'activity':
					break;
			}
		}
	}

	/**
	 * Read file ID
	 */
	protected function readFileId() {
		if (isset($this->Values['type']) && $this->Values['type'][1] != 'activity')
			$this->addError( __('FIT file is not specified as activity.') );

		if (isset($this->Values['time_created']))
			$this->TrainingObject->setTimestamp( strtotime((string)$this->Values['time_created'][1]) );

		$this->TrainingObject->setSportid( Configuration::General()->mainSport() );

		if (isset($this->Values['manufacturer']))
			$this->TrainingObject->setCreator($this->Values['manufacturer'][1]);
	}

	/**
	 * Read device info
	 */
	protected function readDeviceInfo() {
		if (isset($this->Values['garmin_product']) && isset($this->Values['device_index']) && $this->Values['device_index'][0] == 0)
			$this->TrainingObject->setCreator($this->Values['garmin_product'][1]);
	}

	/**
	 * Read session
	 */
	protected function readSession() {
		if (isset($this->Values['total_timer_time']))
			$this->TrainingObject->setTimeInSeconds( round($this->Values['total_timer_time'][0] / 1e3) + $this->TrainingObject->getTimeInSeconds() );

		if (isset($this->Values['total_elapsed_time']))
			$this->TrainingObject->setElapsedTime( round($this->Values['total_elapsed_time'][0] / 1e3) + $this->TrainingObject->getElapsedTime() );

		if (isset($this->Values['total_distance']))
			$this->TrainingObject->setDistance( round($this->Values['total_distance'][0] / 1e5, 3) + $this->TrainingObject->getDistance() );

		if (isset($this->Values['total_calories']))
			$this->TrainingObject->setCalories( $this->Values['total_calories'][0] + $this->TrainingObject->getCalories() );

		if (isset($this->Values['total_strokes'])) {
			$this->TrainingObject->setTotalStrokes($this->Values['total_strokes'][0]);
			$this->isSwimming = self::SPORT_SWIMMING_INSIDE;
		}

		if (isset($this->Values['avg_swimming_cadence']))
			$this->TrainingObject->setCadence($this->Values['avg_swimming_cadence'][0]);

		if (isset($this->Values['pool_length']))
			$this->TrainingObject->setPoolLength($this->Values['pool_length'][0]);
		
		if(!isset($this->Values['pool_length']) && isset($this->Values['total_strokes'])){
		    $this->isSwimming = self::SPORT_SWIMMING_OUTSIDE;
		}
		if($this->isSwimming == self::SPORT_SWIMMING_OUTSIDE) {
		    $this->gps['stroke'] = $this->gps['rpm'];
		    //TODO each item stroke / 60 * time
		    $this->gps['rpm'] = '';
		    $this->gps['swimtime'] = $this->gps['time_in_s'];
		    $this->gps['swimdistance'] = $this->gps['km'];
		}
	}

	/**
	 * Read event
	 */
	protected function readEvent() {
		if (isset($this->Values['event']) && isset($this->Values['data'])) {
			switch ((int)$this->Values['event'][1]) {
				case 37:
					$this->TrainingObject->setFitVdotEstimate((int)$this->Values['data'][1]);
					return;

				case 38:
					$this->TrainingObject->setFitRecoveryTime((int)$this->Values['data'][1]);
					return;

				case 39:
					$this->TrainingObject->setFitHRVscore((int)$this->Values['data'][1]);
					return;

			}
		}

		if (!isset($this->Values['event']) || $this->Values['event'][1] != 'timer' || !isset($this->Values['event_type']))
			return;

		$thisTimestamp = strtotime((string)$this->Values['timestamp'][1]);

		if ($this->Values['event_type'][1] == 'stop_all' || $this->Values['event_type'][1] == 'stop') {
			$this->isPaused = true;
			$this->lastStopTimestamp = $thisTimestamp;
		} elseif ($this->Values['event_type'][1] == 'start') {
			if ($this->isPaused && ($thisTimestamp - $this->TrainingObject->getTimestamp()) < end($this->gps['time_in_s'])) {
				$this->pausesToApply[] = array(
					'time' => $this->lastStopTimestamp - $this->TrainingObject->getTimestamp(),
					'duration' => ($thisTimestamp - $this->lastStopTimestamp)
				);
			} elseif ($this->isPaused) {
				$this->wasPaused = true;
			}

			$this->isPaused = false;

			if ($this->lastStopTimestamp === false)
				$this->TrainingObject->setTimestamp( $thisTimestamp );
			elseif ($thisTimestamp > $this->lastStopTimestamp)
				$this->PauseInSeconds += $thisTimestamp - $this->lastStopTimestamp;
		}
	}

	/**
	 * Read record
	 */
	protected function readRecord() {
		if ($this->isPaused) // Should not happen?
			return;
		
		if(!isset($this->Values['position_lat']) && $this->isSwimming == self::SPORT_SWIMMING_INSIDE)
		    return;


		if (empty($this->gps['time_in_s'])) {
			$startTime = strtotime((string)$this->Values['timestamp'][1]);

			if ($startTime < $this->TrainingObject->getTimestamp()) {
				$this->TrainingObject->setTimestamp($startTime);
			}
		}
		$time = strtotime((string)$this->Values['timestamp'][1]) - $this->TrainingObject->getTimestamp() - $this->PauseInSeconds;
		$last = end($this->gps['time_in_s']);
		
		if ($this->wasPaused) {
			$this->TrainingObject->Pauses()->add(
				new \Runalyze\Model\Trackdata\Pause(
					$last,
					strtotime((string)$this->Values['timestamp'][1]) - $this->lastStopTimestamp,
					end($this->gps['heartrate']),
					isset($this->Values['heart_rate']) ? (int)$this->Values['heart_rate'][0] : 0
				)
			);

			$this->wasPaused = false;
		}

		$this->gps['latitude'][]  = isset($this->Values['position_lat']) ? substr($this->Values['position_lat'][1], 0, -3) : 0;
		$this->gps['longitude'][] = isset($this->Values['position_long']) ? substr($this->Values['position_long'][1], 0, -3) : 0;

		$this->gps['altitude'][]  = isset($this->Values['altitude']) ? substr($this->Values['altitude'][1], 0, -3) : 0;

		$this->gps['km'][]        = isset($this->Values['distance']) ? round($this->Values['distance'][0] / 1e5, ParserAbstract::DISTANCE_PRECISION) : end($this->gps['km']);
		$this->gps['heartrate'][] = isset($this->Values['heart_rate']) ? (int)$this->Values['heart_rate'][0] : 0;
		$this->gps['time_in_s'][] = $time;
		$this->gps['rpm'][]       = isset($this->Values['cadence']) ? (int)$this->Values['cadence'][0] : 0;

		$this->gps['power'][]     = isset($this->Values['power']) ? (int)$this->Values['power'][0] : 0;
		//$this->gps['left_right'][]     = isset($this->Values['left_right_balance']) ? (int)$this->Values['left_right_balance'][0] : 0;

		$this->gps['temp'][]      = isset($this->Values['temperature']) ? (int)$this->Values['temperature'][0] : 0;


		$this->gps['groundcontact'][] = isset($this->Values['stance_time']) ? round($this->Values['stance_time'][0]/10) : 0;
		$this->gps['oscillation'][]   = isset($this->Values['vertical_oscillation']) ? round($this->Values['vertical_oscillation'][0]/10) : 0;

		if ($time === $last && !$this->isSwimming) {
			$this->mergeRecord();
		}

	}

	/**
	 * Merge current record
	 */
	protected function mergeRecord() {
		end($this->gps['time_in_s']);
		$i = key($this->gps['time_in_s']);

		foreach (array_keys($this->gps) as $key) {
			if (isset($this->gps[$key][$i])) {
				$last = $this->gps[$key][$i - 1];
				$current = array_pop($this->gps[$key]);

				if ($current != 0 && $last == 0) {
					$this->gps[$key][$i - 1] = $current;
				}
			}
		}

	}

	/**
	 * Read lap
	 */
	protected function readLap() {
	    if(!$this->isSwimming && isset($this->Values['total_strokes'])) {
		$this->isSwimming = self::SPORT_SWIMMING_INSIDE;
	    }
		if (isset($this->Values['total_timer_time']) && isset($this->Values['total_distance']))
			$this->TrainingObject->Splits()->addSplit(
				$this->Values['total_distance'][0] / 1e5,
				$this->Values['total_timer_time'][0] / 1e3
			);
	}
        
	/**
	 * Read length
	 */
	protected function readLength() {
		if (!$this->isSwimming && isset($this->Values['total_strokes'])) {
			foreach (array_keys($this->gps) as $key) {
				$this->gps[$key] = array();
			}

			$this->isSwimming = self::SPORT_SWIMMING_INSIDE;
		}
                if(isset($this->Values['total_strokes']) && $this->Values['total_strokes'][0] == 0) {
                    $this->TrainingObject->Pauses()->add(
				new \Runalyze\Model\Trackdata\Pause(
					end($this->gps['time_in_s']),
					round(((int)$this->Values['total_timer_time'][0])/1000)
				)
			);
                }
		$this->gps['stroke'][] = isset($this->Values['total_strokes']) ? (int)$this->Values['total_strokes'][0] : 0;
		$this->gps['stroketype'][] = isset($this->Values['swim_stroke']) ? (int)$this->Values['swim_stroke'][0] : 0;
		//$this->gps['rpm'][] = isset($this->Values['avg_swimming_cadence']) ? (int)$this->Values['avg_swimming_cadence'][0] : 0;

		if (empty($this->gps['time_in_s'])) {
			$this->TrainingObject->setTimestamp( strtotime((string)$this->Values['start_time'][1]) );
			$this->gps['time_in_s'][] = round(((int)$this->Values['total_timer_time'][0])/1000);
		} else {
			$this->gps['time_in_s'][] = end($this->gps['time_in_s']) + round(((int)$this->Values['total_timer_time'][0])/1000);
		}
		
	}

	/**
	 * Read hrv
	 */
	protected function readHRV() {
		if (!$this->isPaused) {
			$this->gps['hrv'][] = $this->Values['time'][0];
		}
	}
	
	/**
	 * Read hr
	 */
	protected function readHR() {
	    if(isset($this->Values['event_timestamp_12'])) {
		$bpm = explode(',', $this->Values['filtered_bpm'][1]);

		$Time = $this->readEventTimestamp12($this->Values['event_timestamp_12'][1]);
		if(count($Time) == 8) {
		    $this->FitArrayToGps($bpm, 'heartrate');
		    $this->FitArrayToGps($Time, 'swimtime', true);
		}
	    }
	}
	
	/*
	 * Write (FIT) Arrays to activity arrays
	 * @param array $Data
	 * @param string $key
	 */
	public function FitArrayToGps(array $Data, $key, $addup = false) {
	    if(is_array($Data)) {
		foreach($Data as $value) {
                    if($addup == true && isset($this->gps[$key])) {
                        $value = end($this->gps[$key]) + $value;
                    }
		    $this->gps[$key][] = $value;
		}
	    }
	}
	
	/**
	 * Get timestamps from hr event timestamp 12
	 * @param string $EventTimestamp12
	 * @return array
	 */
	public function readEventTimestamp12($EventTimestamp12) {
	    $EventTimeStamp12 = explode(',', $EventTimestamp12);
	    $lastbin = '';
	   // $LastTimestamp = end($this->gps['time_in_s']);
	    $LastTimestamp = 0;
	    if(is_array($EventTimeStamp12) && count($EventTimeStamp12) == 12) {
		$BinTimestamp = array_map(function($n) { return sprintf('%08b',  $n); }, $EventTimeStamp12);
		
		$Time = array();
		for($i = 0; $i <= count($BinTimestamp); $i++) {
		    if($lastbin != '') {
			$val=  $lastbin.$BinTimestamp[$i+1];
			$Time[] = $LastTimestamp + end($Time) + bindec($val) / 1024;
			$lastbin = '';
			$i += 1;
		    } else {
			if(isset($BinTimestamp[$i+1])) {
			    $val = $BinTimestamp[$i].substr($BinTimestamp[$i+1], 0,4);
			    $Time[] = $LastTimestamp + end($Time) + bindec($val) / 1024;
			    $lastbin = substr($BinTimestamp[$i+1],4,8);
			}
		    }
		}
		return $Time;
	    }
	}
	/**
	 * Apply pauses
	 */
	protected function applyPauses() {
		if (!empty($this->pausesToApply)) {
			$num = count($this->gps['time_in_s']);
			$keys = array_keys($this->gps);
			$pauseInSeconds = 0;
			$pauseIndex = 0;
			$pauseTime = $this->pausesToApply[$pauseIndex]['time'];
			$pauseUntil = 0;
			$isPause = false;
			$hrStart = 0;

			for ($i = 0; $i < $num; $i++) {
				if (!$isPause && $this->gps['time_in_s'][$i] > $pauseTime) {
					if ($pauseIndex < count($this->pausesToApply)) {
						$isPause = true;
						$hrStart = $this->gps['heartrate'][$i];
						$pauseInSeconds += $this->pausesToApply[$pauseIndex]['duration'];
						$pauseTime = $this->pausesToApply[$pauseIndex]['time'];
						$pauseUntil = $this->pausesToApply[$pauseIndex]['duration'] + $pauseTime;
						$pauseIndex++;
						$pauseTime = ($pauseIndex < count($this->pausesToApply)) ? $this->pausesToApply[$pauseIndex]['time'] : PHP_INT_MAX;
					}
				}

				if ($isPause && $this->gps['time_in_s'][$i] >= $pauseUntil) {
					$isPause = false;
					$this->TrainingObject->Pauses()->add(
						new \Runalyze\Model\Trackdata\Pause(
							$this->pausesToApply[$pauseIndex-1]['time'],
							$this->pausesToApply[$pauseIndex-1]['duration'],
							$hrStart,
							end($this->gps['heartrate'])
						)
					);
				}

				if ($isPause) {
					foreach ($keys as $key) {
						if (isset($this->gps[$key][$i])) {
							unset($this->gps[$key][$i]);
						}
					}
				} else {
					$this->gps['time_in_s'][$i] -= $pauseInSeconds;
				}
			}
		}
	}
}
