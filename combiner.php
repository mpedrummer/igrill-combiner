<?php

date_default_timezone_set('America/New_York');

// The data is combined into an average per probe per period.  This defines the length
// of the period.
if( isset( $argv[1] ) && intval( $argv[1] ) > 0 ) {
  $seconds_period = intval( $argv[1] );
} else {
  $seconds_period = 120;
}

if( isset( $argv[2] ) ) {
  $date_format = $argv[2];
} else {
  $date_format = 'H:i';
}

$unsorted     = array();
$found_probes = array();

$lowest  = 99999999999999;
$highest = 0;

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

    $period = intval( floor( $time / $seconds_period ) * $seconds_period );

    if( $period < $lowest ) {
      $lowest = $period;
    } else if( $period > $highest ) {
      $highest = $period;
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

echo date( 'Y-m-d H:i', $lowest ) . "\n";
echo date( 'Y-m-d H:i', $highest ) . "\n";

echo "lowest $lowest; highest $highest; $seconds_period\n\n";


for( $period = $lowest; $period <= $highest; $period += $seconds_period ) {
  echo date( "Y-m-d H:i", $period ) . "\n";

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

foreach( $sorted as $period => $probes ) {
  $data = [];
  $data[] = date( $date_format, $period );

  foreach( $found_probes as $probe ) {
    if( isset( $probes[ $probe ] ) ) {
      $data[] = number_format( $probes[ $probe ]['avg'], 1 );
    } else {
      $data[] = '';
    }
  }

  echo implode( ',', $data ) . "\n";
}
