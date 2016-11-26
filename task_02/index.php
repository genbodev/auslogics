<?php

class numbers {

  public $numbers = array();

  public function get_mt_rand($min = 1, $max = 1000000) {
    return mt_rand($min, $max);
  }

  public function generate_numbers($max_count = 1000, $show = false) {
    $start = 0;
    if ($show) $start = microtime(true);
    $number = 0;
    $numbers = array();
    $this->numbers = $this->array_fill($numbers, $max_count);
    while (true) {
      if (count($this->numbers) !== $max_count - 1) {
        $this->numbers = $this->array_fill($this->numbers, $max_count);
      }
      else {
        $this->numbers = array_unique($this->numbers, SORT_NUMERIC);
        $number = $this->numbers[count($this->numbers) - 1];
        $this->numbers[] = $number;
        shuffle($this->numbers);
        break;
      }
    }
    if ($show) $this->show_result(__FUNCTION__, microtime(true) - $start, $number, count($this->numbers));
  }

  public function generate_numbers_range($max_count = 1000, $show = false) {
    $start = 0;
    if ($show) $start = microtime(true);
    $this->numbers = range(1, $max_count - 1);
    $number = $this->numbers[$this->get_mt_rand(0, $max_count - 1)];
    $this->numbers[] = $number;
    shuffle($this->numbers);
    if ($show) $this->show_result(__FUNCTION__, microtime(true) - $start, $number, count($this->numbers));
  }

  function array_fill($array, $count) {
    return array_map(
      function ($v) {
        if ($v === 0) {
          return $this->get_mt_rand();
        }
        return $v;
      },
      array_pad($array, $count - 1, 0)
    );
  }

  public function array_count_values_func() {
    $start = microtime(true);
    $number = 0;
    $numbers = array_count_values($this->numbers);
    foreach ($numbers as $number => $val) {
      if ($val === 2) {
        break;
      }
    }
    $this->show_result(__FUNCTION__, microtime(true) - $start, $number, count($this->numbers));
  }

  public function in_array_func() {
    $start = microtime(true);
    $number = 0;
    $numbers = array();
    foreach ($this->numbers as $number) {
      if (!in_array($number, $numbers)) {
        $numbers[] = $number;
      }
      else {
        break;
      }
    }
    $this->show_result(__FUNCTION__, microtime(true) - $start, $number, count($this->numbers));
  }

  public function double_foreach_func() {
    $start = microtime(true);
    $number = 0;
    $array = array();
    foreach ($this->numbers as $key => $val) {
      $array[$val][] = $key;
    }
    foreach ($array as $number => $keys) {
      if (count($keys) === 2) {
        break;
      }
    }
    $this->show_result(__FUNCTION__, microtime(true) - $start, $number, count($this->numbers));
  }

  public function built_in_array_func() {
    $start = microtime(true);
    $numbers = $this->numbers;
    $number = array_keys(array_filter(array_count_values($numbers), function ($v) {
      return $v === 2;
    }))[0];
    $this->show_result(__FUNCTION__, microtime(true) - $start, $number, count($this->numbers));
  }

  public function show_result($function_name, $time, $number, $count) {
    echo '
    Function: ' . $function_name . '<br /> 
    - Time: ' . $time . '<br /> 
    - Number: ' . $number . '<br /> 
    - Count: ' . $count . '<br /><br />';
  }


}

$num = new numbers();
//$num->generate_numbers(10000, true); // create array
$num->generate_numbers_range(10000, true); // fast create
$num->array_count_values_func(); // leader
$num->in_array_func();
$num->built_in_array_func();
$num->double_foreach_func();
