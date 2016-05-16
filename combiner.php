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

$sorted       = array();
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

    $period = intval( floor( $time / $seconds_period ) * $seconds_period );

    if( isset( $sorted[ $period ][ $probe ] ) === false ) {
      // Initialize to prevent index notices
      $sorted[ $period ][ $probe ] = array( 'sum' => 0, 'count' => 0 );
    }

    $sorted[ $period ][ $probe ][ 'sum' ] += $temp;
    $sorted[ $period ][ $probe ][ 'count' ]++;
  }
}

foreach( $sorted as $period => $probes ) {
  foreach( $probes as $probe => $data ) {
    $sorted[ $period ][ $probe ]['avg'] = $data['sum'] / $data['count'];
  }
}

ksort( $sorted );
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
