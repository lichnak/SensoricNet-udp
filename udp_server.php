<?php
$conf ['version'] = "v0.1"; // verze
$conf ['bind_address'] = '0.0.0.0';
$conf ['bind_port'] = 9999;
$conf ['base_dir'] = dirname ( __FILE__ );
$conf ['log_filename'] = $conf ['base_dir'] . "/log/nbiot_udp.log";
$conf ['api_url'] = 'http://example.com/api/ttn_update';

$conf ['log_severities'] = array (
		'emergency' => 0,
		'alert' => 1,
		'critical' => 2,
		'error' => 3,
		'warning' => 4,
		'notice' => 5,
		'info' => 6,
		'debug' => 7 
);
$conf ['log_severity'] = 'debug';

/**
 * **********************************************
 * constants
 * **********************************************
 */

// #define LPP_DIGITAL_INPUT 0 // 1 byte
$lpp_definition [0] ['name'] = 'digital_in';
$lpp_definition [0] ['size'] = 1;
// #define LPP_DIGITAL_OUTPUT 1 // 1 byte
$lpp_definition [1] ['name'] = 'digital_out';
$lpp_definition [1] ['size'] = 1;
// #define LPP_ANALOG_INPUT 2 // 2 bytes, 0.01 signed
$lpp_definition [2] ['name'] = 'analog_in';
$lpp_definition [2] ['size'] = 2;
// #define LPP_ANALOG_OUTPUT 3 // 2 bytes, 0.01 signed
$lpp_definition [3] ['name'] = 'analog_out';
$lpp_definition [3] ['size'] = 2;
// #define LPP_LUMINOSITY 101 // 2 bytes, 1 lux unsigned
$lpp_definition [101] ['name'] = 'luminosity';
$lpp_definition [101] ['size'] = 2;
// #define LPP_PRESENCE 102 // 1 byte, 1
$lpp_definition [102] ['name'] = 'presence';
$lpp_definition [102] ['size'] = 1;
// #define LPP_TEMPERATURE 103 // 2 bytes, 0.1°C signed
$lpp_definition [103] ['name'] = 'temperature';
$lpp_definition [103] ['size'] = 2;
// #define LPP_RELATIVE_HUMIDITY 104 // 1 byte, 0.5% unsigned
$lpp_definition [104] ['name'] = 'relative_humidity';
$lpp_definition [104] ['size'] = 1;
// #define LPP_ACCELEROMETER 113 // 2 bytes per axis, 0.001G
$lpp_definition [113] ['name'] = 'accelerometer';
$lpp_definition [113] ['size'] = 2;
// #define LPP_BAROMETRIC_PRESSURE 115 // 2 bytes 0.1 hPa Unsigned
$lpp_definition [115] ['name'] = 'barometric_pressure';
$lpp_definition [115] ['size'] = 2;
// #define LPP_GYROMETER 134 // 2 bytes per axis, 0.01 °/s
$lpp_definition [134] ['name'] = 'gyrometer';
$lpp_definition [134] ['size'] = 6;
// #define LPP_GPS 136 // 3 byte lon/lat 0.0001 °, 3 bytes alt 0.01 meter
$lpp_definition [136] ['name'] = 'gps';
$lpp_definition [136] ['size'] = 9;

/**
 * logovani podle nastavene severity na obrazovku a do souboru
 *
 * @param string $severity
 * @param string $text
 */
function logit($severity, $text) {
	global $conf, $log;
	
	if ($conf ['log_severities'] [$conf ['log_severity']] >= $conf ['log_severities'] [$severity]) {
		$text_array = explode ( "\n", $text );
		foreach ( $text_array as $line ) {
			fputs ( $log, date ( "Ymd H:i:s " ) . strtoupper ( $severity ) . ": " . $line . "\n" );
		}
	}
}

/**
 * Dekoduje data z cayenne lpp do pole
 *
 * @param string $data
 */
function cayenne_lpp_decode($data) {
	global $lpp_definition;
	
	$byte_array = unpack ( 'C*', $data );
	
	$output_object = new stdClass ();
	
	while ( count ( $byte_array ) > 0 ) {
		$sensor_number = array_shift ( $byte_array );
		$field_code = array_shift ( $byte_array );
		
		// existuje tento lpp key?
		if (array_key_exists($field_code, $lpp_definition)) {
			$field_name = $lpp_definition [$field_code] ['name'];
			$field_size = $lpp_definition [$field_code] ['size'];
			
			switch ($field_code) {
				case 0 :
				case 1 :
				case 102 :
					$field_value = array_shift ( $byte_array );
					$output_object->{$field_name . "_" . $sensor_number} = $field_value;
					break;
				case 2 :
				case 3 :
					$field_value = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 100;
					$output_object->{$field_name . "_" . $sensor_number} = $field_value;
					break;
				case 103 :
				case 115 :
					$field_value = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 10;
					$output_object->{$field_name . "_" . $sensor_number} = $field_value;
					break;
				case 101 :
					$field_value = 256 * array_shift ( $byte_array ) + array_shift ( $byte_array );
					$output_object->{$field_name . "_" . $sensor_number} = $field_value;
					break;
				case 104 :
					$field_value = array_shift ( $byte_array ) / 2;
					$output_object->{$field_name . "_" . $sensor_number} = $field_value;
					break;
				case 113 :
					$accelerometer_object = new stdClass ();
					// correct object names TODO
					$accelerometer_object->vx = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 1000;
					$accelerometer_object->vy = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 1000;
					$accelerometer_object->vz = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 1000;
					$output_object->{$field_name . "_" . $sensor_number} = $accelerometer_object;
					break;
				case 134 :
					$gyrometer_object = new stdClass ();
					$gyrometer_object->vx = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 100;
					$gyrometer_object->vy = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 100;
					$gyrometer_object->vz = (256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 100;
					$output_object->{$field_name . "_" . $sensor_number} = $gyrometer_object;
					break;
				case 136 :
					$gps_object = new stdClass ();
					$gps_object->longitude = (65536 * array_shift ( $byte_array ) + 256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 10000;
					$gps_object->latitude = (65536 * array_shift ( $byte_array ) + 256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 10000;
					$gps_object->altitude = (65536 * array_shift ( $byte_array ) + 256 * array_shift ( $byte_array ) + array_shift ( $byte_array )) / 100;
					$output_object->{$field_name . "_" . $sensor_number} = $gps_object;
					break;
				default:
					// invalid lpp, TODO
			}
		} else {
			logit ('warning', "lpp key $field_code does not exist");
			// co ted? prohledavat dal, nebo failnout?
		}
	}

	logit('debug', print_r($output_object, true));
	return $output_object;
}


/************************************************************
 * main
 ************************************************************/

// Reduce errors
error_reporting ( ~ E_WARNING );

// logovaci soubor
$log = fopen ( $conf ['log_filename'], "a" );
if (! $log) {
	die ( "Cannot open log file " . $conf ['log_filename'] );
}

logit ( 'info', "SensoricNet UDP reciever version " . $conf ['version'] );

// Create a UDP socket
if (! ($sock = socket_create ( AF_INET, SOCK_DGRAM, 0 ))) {
	$errorcode = socket_last_error ();
	$errormsg = socket_strerror ( $errorcode );
	
	logit ( 'emergency', "Couldn't create socket: [$errorcode] $errormsg" );
	die ();
}

// Bind the source address
if (! socket_bind ( $sock, $conf ['bind_address'], $conf ['bind_port'] )) {
	$errorcode = socket_last_error ();
	$errormsg = socket_strerror ( $errorcode );
	
	logit ( 'emergency', "Couldn't bind socket: [$errorcode] $errormsg" );
	die ();
}

logit ( 'info', "Listening on " . $conf ['bind_address'] . ":" . $conf ['bind_port'] );

// Do some communication, this loop can handle multiple clients
while ( 1 ) {
	
	// Receive some data
	$r = socket_recvfrom ($sock, $buf, 512, 0, $remote_ip, $remote_port );
	logit ( 'info', "UDP packet recieved. remote_ip: $remote_ip remote_port: $remote_port" );
	logit ( 'debug', "UDP packet data dump: " . print_r(unpack ( 'H*', $buf ), true) );
	
	// do the magic here...
	
	// test validity of packet
	
	// decode cayenne lpp,
	$payload_fields = cayenne_lpp_decode ($buf);
	
	// create ttn json structure and call SensoricNet api
	$gateway_object = new stdClass();
	$gateway_object->gtw_id = 'eui-****************'; //TODO
	$gateway_object->timestamp = '74464988'; //TODO
	$gateway_object->time = '2018-03-08T22:20:35.944604Z'; //TODO
	$gateway_object->channel = '3'; //TODO
	$gateway_object->rssi = '-33'; //TODO
	$gateway_object->snr = '8'; //TODO
	$gateway_object->rf_chain = '0'; //TODO
	$gateway_object->latitude = '49.93467'; //TODO
	$gateway_object->longitude = '17.89626'; //TODO
	$gateway_object->altitude = '275'; //TODO
	
	$metadata_object = new stdClass();
	$metadata_object->time = '2018-03-08T22:20:35.97081415Z'; //TODO
	$metadata_object->frequency = '867.1'; //TODO
	$metadata_object->modulation = 'NBIoT';
	$metadata_object->data_rate = 'SF11BW125'; //TODO
	$metadata_object->coding_rate = '4/5'; //TODO
	$metadata_object->gateways = array(0=>$gateway_object);
	
	$ttn_object = new stdClass();
	$ttn_object->app_id = 'test_dron01'; //TODO
	$ttn_object->dev_id = 'nbiot-snpm001'; //TODO
	$ttn_object->hardware_serial = '0004A30B0021E4CC'; // TODO
	$ttn_object->port = '1'; //TODO
	$ttn_object->counter = '27'; //TODO
	$ttn_object->payload_raw = 'AWcBEAJzJhADaAAEAgHIBQICAwYCAk8HiAf92AC+4QAAyA=='; //TODO
	$ttn_object->payload_fields = $payload_fields;
	$ttn_object->metadata = $metadata_object;
	$ttn_object->downlink_url = 'https://integrations.thethingsnetwork.org/ttn-eu/api/v2/down/test_dron01/processid_dron?key=....'; //TODO

	logit('debug', print_r($ttn_object, true));
	
	// profit
	$ch = curl_init($conf ['api_url']);
	
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ttn_object));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	
	$result = curl_exec($ch);
	if ($result === false) {
		logit ( 'error', "Curl call failed. Error was " . curl_error($ch) );
	} else {
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		logit ( 'info', "Curl call was successful, return code is $http_code" );
	}
	curl_close($ch);
}

socket_close($sock);
