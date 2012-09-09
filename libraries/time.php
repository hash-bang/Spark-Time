<?
/**
* Various time related functions
* @author Matt Carter <m@ttcarter.com>
*/
class Time {
	/**
	* Returns the number of seconds from a human readable string such as '1h30m' => (60*60)+(30*60)
	* @param string $haystack The string to search usually in the example form '1h30m'
	* @return int The number of seconds that the input string is 'worth'
	*/
	function Shorthand($haystack) {
		$out = 0;
		if (is_int($haystack))
			return $out;
		foreach (re('/([0-9]+)([hmsdwy]?)/i',$haystack,RE_ALWAYSARRAY) as $timebit) {
			switch (strtolower($timebit[1])) {
				case 'h':
					$out += 60*60*$timebit[0];
					break;
				case 'm':
					$out += 60*$timebit[0];
					break;
				case 'd':
					$out += 24*60*60*$timebit[0];
					break;
				case 'w':
					$out += 7*24*60*60*$timebit[0];
					break;
				case 'y':
					$out += 356*24*60*60*$timebit[0];
					break;
				default:
					$out += $timebit[0];
					break;
			}
		}
		return $out;
	}

	/**
	* Returns a sequence of seconds in human format
	* e.g. 97560 = '1 day, 3 hours and 6 minutes'
	* @param int $seconds The number of elapsed seconds to convert
	* @param string $suffix The suffix of the string (if any)
	* @param int $interest How many 'tuples' of information we are inteersted in e.g. if 2 = '2 hours and 3 minutes', if 3 = '2 hours, 3 minutes and 4 seconds'
	* @param mixed $zerotime What to return if the distance is zero or less
	* @return string A human readable string expressing time
	*/
	// interest is how many time levels we should dig before getting board 
	function Humanize($seconds, $suffix = 'ago', $interest = 2, $zerotime = 'now') {
		$no = $seconds;
		$out = array();
		$boredom = 0;
		if ($seconds < 10)
			return $zerotime;
		foreach (array(
			'year' => 326*24*60*60,
			'month' => 30*24*60*60,
			'week' => 7*24*60*60,
			'day' => 24*60*60,
			'hour' => 60*60,
			'minute' => 60,
			'second' => 1,
		) as $term => $value) { 
			if ($no > $value) {
				if (($ammount = (int) ($no / $value)) == 1)
					$out[] =  "$ammount $term";
				else
					$out[] =  "$ammount $term" . 's';
				$no -= $ammount * $value;
				if (++$boredom >= $interest)
					break;
			}
		}
		if (count($out) == 1) {
			return "{$out[0]} $suffix";
		} else {
			$last = array_pop($out);
			return implode(', ', $out) . " and $last $suffix";
		}
	}

	/**
	* Shorthand functionality for Humanize that calculates the age of something from another point (today is assumed)
	* @param int $epoc The creation date of the item
	* @param int $today Todays epoc to calculate from (if null NOW is assumed)
	* @param int $interest The number of date tuples to return (See Humanize for examples)
	* @param mixed $zerotime What to return if the age of the item is the same or younger than today
	* @return string Human readable string calculating the items age
	* @see Humanize()
	*/
	function Age($epoc, $today, $interest = 2, $zerotime = FALSE) {
		$age = $today - $epoc;
		if ($age < 0)
			return $zerotime;
		return $this->Humanize($age, '', $interest, $zerotime);
	}

	/**
	* Takes a DATE() format as input and a user provided string an converts it to an epoc
	* e.g. getstamp('d/m/Y H:M','02/07/1983 1035') will return the unix timestamp 425952900
	* @param string $format The input format (see PHP's DATE() function for syntax)
	* @param string $string The input string to use against the format
	* @param int $impossible A lower value to check against, if the output epoc is lower than this value FALSE is returned instead
	* @return int|bool Either the epoc value of the formatted date or boolean FALSE
	*/
	function GetStamp($format, $string, $impossible = 0) {
		if ($impossible === 0)
			$impossible = mktime(1,1,1,1,1,1980);
		$translate = array( // Anything thats ===0 in the list below is currently unsupported
			'S' => '(st|nd|rd|th)',
			'L' => '(0|1)',
			'a' => '(am|pm)',
			'A' => '(am|pm)',
			'M' => '([a-z]{3})',
			'F' => '([a-z]{3})',
			// The below date() functions are not supported by McTime yet
			'D' => 0,
			'l' => 0,
			'N' => 0,
			'w' => 0,
			'z' => 0,
			'W' => 0,
			'B' => 0,
			'u' => 0,
			'e' => 0,
			'I' => 0,
			'O' => 0,
			'P' => 0,
			'T' => 0,
			'Z' => 0,
			'c' => 0,
			'r' => 0,
			'U' => 0,
			'y' => '([0-9]{4}|[0-9]{3}|[0-9]{2})', // Years are oftain mistaken as either Y or y
			'Y' => '([0-9]{4}|[0-9]{3}|[0-9]{2})', // Years are oftain mistaken as either Y or y
		);
		foreach (array('d','j','m','n','t','g','G','h','H','i','s') as $tone2) // Standard 2 number translators
			$translate[$tone2] = '([0-9]{1,2})';
		foreach (array('o') as $tone4) // Standard 4 number translators
			$translate[$tone4] = '([0-9]{4})';
		$matchorder = array();
		$matchexp = '';
		$skipnext = 0;
		for ($i = 0; $i < strlen($format); $i++) { // Figure out the format (and load it into the $matchexp with $matchorder as the lookup table)
			if ($skipnext) { // Escape this char?
				$skipnext = 0;
			} elseif ( ($char = substr($format,$i,1)) == '\\') {
				$skipnext = 1;
			} else { // Actually process this char
				if (isset($translate[$char])) {
					if ($translate[$char] === 0)
						die("McTime->getstamp('$format','$string') - I understand what '$char' means but it is currently not supported by this version of McTime. Poke MC for future inclusion\n");
					$matchexp .= $translate[$char];
					$matchorder[] = $char;
				} else
					$matchexp .= preg_quote($char,'/');
			}
		}
		if (!preg_match("/$matchexp/i",$string,$matches))
			return false;

		$months_short = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
		$months_long = array('January', 'Febuary', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');

		$mktime = array('h' => 0, 'i' => 0, 's' => 0, 'm' => 0, 'd' => 0, 'Y' => 0, 'ante' => 0);
		for ($i = 1; $i < count($matches); $i++) {
			switch ($matchorder[$i-1]) {
				case 'd':
				case 'j':
					$mktime['d'] = $matches[$i];
					break;
				case 'M':
					$mktime['m'] = array_search($matches[$i], $months_short) + 1;
					break;
				case 'F':
					$mktime['m'] = array_search($matches[$i], $months_long) + 1;
					break;
				case 'm':
				case 'n':
					$mktime['m'] = $matches[$i];
					break;
				case 'o':
				case 'y':
				case 'Y':
					if ($matches[$i] >= 100)
						$mktime['Y'] = $matches[$i];
					else
						$mktime['Y'] = (int) $matches[$i] + ($matches[$i] < 50 ? 2000 : 1900);
					break;
				case 'A':
				case 'a':
					$mktime['ante'] = (strtolower(substr($matches[$i],0,1)) == 'p');
					break;
				case 'g':
				case 'h':
					$mktime['h'] = $matches[$i] + ($mktime['ante'] ? 12 : 0);
					break;
				case 'H':
				case 'G':
					$mktime['h'] = $matches[$i];
					break;
				case 'i':
					$mktime['i'] = $matches[$i];
					break;
				case 's':
					$mktime['s'] = $matches[$i];
					break;
			}
		}
		$computed = mktime($mktime['h'],$mktime['i'],$mktime['s'],$mktime['m'],$mktime['d'],$mktime['Y']);
		if ($computed < $impossible)
			return false;

		// If you want to do some DEBUGGING comment out the following line so we drop though into the debug area
		return $computed;

		// Debug area - program counter shouldnt get here (unless above line is uncommented)
		if ( ($humancomputed = date($format,$computed)) != $string ) // Double check the result
			die("Time->GetStamp('$format','$string') - I think i'm going crazy. I computed the result $humancomputed but it SHOULD be $string\n");
		return $computed;
	}

	/**
	* Returns the epoc time rounded to that days beginning at midnight.
	* @param int $epoc The input epoc to convert into the pure day start. If unprovided today is used
	* @param int $hour The hour of the day to use instead of midnight
	* @param int $minute The minute of the day to use instead of '0'
	* @param int $second The second of the day to use instead of '0'
	* @return int The epoc representing midnight (or an offset of) for the given epoc
	* @see EpocDateEnd()
	*/
	function EpocDate($epoc = 0, $hour = 0, $minute = 0, $second = 0) {
		$day = getdate( ($epoc == 0) ? time() : $epoc);
		return mktime($hour,$minute,$second,$day['mon'],$day['mday'],$day['year']);
	}

	/**
	* Simular to EpocDate but returns the very LAST second of the day instead
	* @param int $epoc The epoc of the day to return the end-of-day epoc for
	* @return int The epoc representing the last second of the day
	* @see EpocDate()
	*/
	function EpocDateEnd($epoc = 0) {
		$day = getdate( ($epoc == 0) ? time() : $epoc);
		return mktime(23,59,59,$day['mon'],$day['mday'],$day['year']);
	}
}
?>
