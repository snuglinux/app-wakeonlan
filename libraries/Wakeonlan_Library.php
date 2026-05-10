<?php

namespace clearos\apps\wakeonlan;

///////////////////////////////////////////////////////////////////////////////
// B O O T S T R A P
///////////////////////////////////////////////////////////////////////////////

$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';

///////////////////////////////////////////////////////////////////////////////
// N A M E S P A C E S
///////////////////////////////////////////////////////////////////////////////

use \clearos\apps\base\Daemon;
clearos_load_library('base/Daemon');
use \Exception as Exception;
use \clearos\apps\base\File as File;
clearos_load_library('base/File');

class Wakeonlan_Library extends Daemon {
    const FILE_CONFIG = '/etc/clearos/wakeonlan.conf';

    public function __construct() {
        parent::__construct('wakeonlan');
    }

    public function get_devices() {
	clearstatcache(true, self::FILE_CONFIG);

	if (!is_file(self::FILE_CONFIG))
	    return array();

	$config = parse_ini_file(self::FILE_CONFIG, true);

	if (!$config)
	    return array();

	$devices = array();

	foreach ($config as $section => $data) {

    	    if (strpos($section, 'device_') !== 0)
        	continue;

    	    $name = isset($data['name']) ? trim($data['name']) : '';
    	    $mac = isset($data['mac']) ? strtoupper(trim($data['mac'])) : '';
    	    $ip = isset($data['ip']) ? trim($data['ip']) : '';

    	    if ($name === '' || !$this->_is_valid_mac($mac))
        	continue;

    	    $devices[] = array(
        	'name' => $name,
        	'mac' => $mac,
        	'ip' => $ip
    	    );
	}

	return $devices;
    }

    public function set_devices($devices, $settings = NULL) {
	if ($settings === NULL)
	    $settings = $this->get_settings();

	$content = "[settings]\n";

	foreach ($settings as $key => $value) {
    	    $content .= $key . "=" . $value . "\n";
	}

	$content .= "\n";

	$i = 1;

	foreach ($devices as $device) {

    	    $name = trim($device['name']);
	    $mac = strtoupper($device['mac']);

	    if ($name === '' || !$this->_is_valid_mac($mac))
        	continue;

    	    $ip = isset($device['ip']) ? $device['ip'] : '';

    	    $content .= "[device_" . $i . "]\n";
    	    $content .= "name=" . $name . "\n";
    	    $content .= "mac=" . $mac . "\n";
    	    $content .= "ip=" . $ip . "\n\n";

	    $i++;
        }

        $file = new File(self::FILE_CONFIG . '.new');

        if ($file->exists())
            $file->delete();

        $file->create('root', 'root', '0644');
        $file->add_lines($content);

        $file->move_to(self::FILE_CONFIG);
    }

    public function get_settings() {
	clearstatcache(true, self::FILE_CONFIG);

	if (!is_file(self::FILE_CONFIG))
    	    return array();

	$config = parse_ini_file(self::FILE_CONFIG, true);

	if (!$config || !isset($config['settings']))
    	    return array();

	return $config['settings'];
    }

    public function set_settings($settings) {
	$devices = $this->get_devices();

	$this->set_devices($devices, $settings);
    }


    public function get_local_ipv4_addresses() {
	$commands = array(
	    '/sbin/ip -o -4 addr show up 2>/dev/null',
	    '/usr/sbin/ip -o -4 addr show up 2>/dev/null',
	    'ip -o -4 addr show up 2>/dev/null'
	);

	$lines = array();

	foreach ($commands as $command) {
	    $output = array();
	    exec($command, $output, $code);

	    if ($code === 0 && !empty($output)) {
		$lines = $output;
		break;
	    }
	}

	$addresses = array();
	$seen = array();

	foreach ($lines as $line) {
	    if (!preg_match('/^\d+:\s+([^\s]+).*\sinet\s+([0-9\.]+)\/\d+/', $line, $matches))
		continue;

	    $interface = $matches[1];
	    $ip = $matches[2];
	    $broadcast = '';

	    if (preg_match('/\sbrd\s+([0-9\.]+)/', $line, $broadcast_matches))
		$broadcast = $broadcast_matches[1];

	    if ($ip === '127.0.0.1')
		continue;

	    if (isset($seen[$ip]))
		continue;

	    $seen[$ip] = TRUE;
	    $addresses[] = array(
		'ip' => $ip,
		'interface' => $interface,
		'broadcast' => $broadcast
	    );
	}

	return $addresses;
    }

    public function is_local_bind_ip($ip) {
	$ip = trim((string)$ip);

	if ($ip === '')
	    return TRUE;

	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	    return FALSE;

	foreach ($this->get_local_ipv4_addresses() as $address) {
	    if (isset($address['ip']) && $address['ip'] === $ip)
		return TRUE;
	}

	return FALSE;
    }

    private function _is_valid_mac($mac) {
        return (bool)preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac);
    }
}
