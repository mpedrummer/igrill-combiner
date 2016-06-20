<?php

date_default_timezone_set('America/New_York');

$Registry = new Registry();

// The data is combined into an average per probe per period.  This defines the length
// of the period.
if( isset( $argv[1] ) && intval( $argv[1] ) > 0 ) {
  $Registry->seconds_period = intval( $argv[1] );
}

if( isset( $argv[2] ) ) {
  $Registry->date_format = $argv[2];
}

$unsorted     = array();
$found_probes = array();

foreach( glob( './iGrill*' ) as $file ) {
  $fh = fopen( $file, 'r' );

  $line = 0;

  while( $parts = fgetcsv( $fh, 1024 ) ) {
    ++$line;

    if( $line === 1 ) {
      // First line contains headers, skip it.
      continue;
    }

    if( count( $parts ) !== 3 ) {
      // Bad line
      continue;
    }

    $probe = $parts[0];
    $time  = strtotime( $parts[1] );
    $temp  = intval( $parts[2] );

    $found_probes[ $probe ] = 1;

    $period = intval( floor( $time / $Registry->seconds_period ) * $Registry->seconds_period );

    if( $period < $Registry->lowest ) {
      $Registry->lowest = $period;
    } else if( $period > $Registry->highest ) {
      $Registry->highest = $period;
    }

    if( isset( $unsorted[ $period ][ $probe ] ) === false ) {
      // Initialize to prevent index notices
      $unsorted[ $period ][ $probe ] = array( 'sum' => 0, 'count' => 0 );
    }

    $unsorted[ $period ][ $probe ][ 'sum' ] += $temp;
    $unsorted[ $period ][ $probe ][ 'count' ]++;
  }
}

foreach( $unsorted as $period => $probes ) {
  foreach( $probes as $probe => $data ) {
    $unsorted[ $period ][ $probe ]['avg'] = $data['sum'] / $data['count'];
  }
}

ksort( $unsorted );

// It's sorted now.
$sorted = $unsorted;


for( $period = $Registry->lowest; $period <= $Registry->highest; $period += $Registry->seconds_period ) {
  if( isset( $sorted[ $period ] ) === false ) {
    $sorted[ $period ] = array();
  }
}

ksort( $sorted ); // Sort it again
ksort( $found_probes );

$found_probes = array_keys( $found_probes );

$header = array( 'Time' );
$header = array_merge( $header, $found_probes );

echo implode( ',', $header ) . "\n";

$probe_count = count( $found_probes );

foreach( $sorted as $period => $probes ) {
  $i = 0;

  $data     = array();
  $data[$i] = date( $Registry->date_format, $period );

  foreach( $found_probes as $probe ) {
    ++$i;
    if( isset( $probes[ $probe ] ) ) {
      $period_avg            = number_format( $probes[ $probe ]['avg'], 1 );
      $Registry->last_known[ $probe ]  = $period_avg;
      $data[$i]              = $period_avg;
      $data[$i+$probe_count] = $period_avg;
    } else {
      $data[$i] = '';
      $data[$i + $probe_count] = getInterpolatedData( $sorted,
                                                      $period,
                                                      $probe,
                                                      $Registry );
    }
  }
  ksort( $data );

  echo implode( ',', $data ) . "\n";
}

/**
 * Calculates missing data points.
 *
 * @param array    $array
 * @param integer  $period
 * @param string   $probe
 * @param Registry $Registry
 * @return string
 */
function getInterpolatedData( &$array,
                              $period,
                              $probe,
                              Registry $Registry ) {
  $blanks         = 2;
  $last_known     = floatval( $Registry->last_known[ $probe ] );
  $seconds_period = $Registry->seconds_period;

  while( $period <= $Registry->highest
    && isset( $array[ $period + $seconds_period ][$probe]['avg'] ) === false ) {
    $period += $seconds_period;
    ++$blanks;
  }

  if( isset( $array[ $period + $seconds_period ] ) &&
    isset( $array[ $period + $seconds_period ][ $probe ] ) ) {
    $next_valid = $array[ $period + $seconds_period ][ $probe ]['avg'];
    $gap        = $next_valid - $last_known;
  } else {
    $gap = 0;
  }

  $spacer = $gap / $blanks;

  $new_avg = $last_known + $spacer;
  $Registry->last_known[ $probe ] = $new_avg;

  return number_format( $new_avg, 1 );
}

class Registry {
  public $lowest         = 9999999999999;
  public $highest        = 0;
  public $last_known     = array();
  public $seconds_period = 120;
  public $date_format    = 'H:i';
}
