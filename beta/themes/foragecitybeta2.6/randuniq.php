<?php

class RandomUnique100kArray {
	private $array;
	private $current;

	/**
	 * Construct an array of all integers in the range [0,99999]
	 * in a random order. Each element in the array will be an array
	 * with the random integer value and a boolean value to be used
	 * for marking/checking whether that integer value is being used.
	 * All boolean values are initially set to false.
	 *
	 * @return array of int/boolean pairs
	 */
	public function __construct($seed = '', $cur = 0){
		if(!empty($seed))
			mt_srand($seed);
		$n = 100000;
		$min = 0;
		$max = 99999;
		$nary = array();
		$i = 0;
		while($n--){
			$ind = mt_rand(0,$max);
			while(isset($nary[$ind]))
				$ind = mt_rand(0,$max);
			$nary[$ind] = $i;	
			$i++;
		}
		ksort($nary);
		$this->array = $nary;
		$this->current = $cur;
	}

	public function claim(){
		$result = false;
		$cur = $this->current;
		if($cur >= 0) {
			$val = $this->array[$cur];
			$cur++;
			if($cur > 99999)
				$this->current = -1;
			else
				$this->current = $cur;
			$result = array($val, $this->current);
		}
		return $result;
	}

	public function print_array($note = ""){
		if(!empty($note)) echo $note.PHP_EOL;
		foreach(array_keys($this->array) as $key){
			$intval = $this->array[$key];
			if($intval<10)
				$intval = "0000".$intval;
			elseif($intval<100)
				$intval = "000".$intval;
			elseif($intval<1000)
				$intval = "00".$intval;
			elseif($intval<10000)
				$intval = "0".$intval;
			echo "[$intval:".(($key<$this->current)?'used':'free')."]".PHP_EOL;
		}
	}
}

?>
