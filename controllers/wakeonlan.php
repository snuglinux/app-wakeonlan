<?php

use \Exception as Exception;

class Wakeonlan extends ClearOS_Controller {
    const DEFAULT_PORT = 9;

    public function index() {
        $this->lang->load('wakeonlan');
        $this->load->library('wakeonlan/Wakeonlan_Library');

        try {
            $devices = $this->wakeonlan_library->get_devices();
	    foreach ($devices as &$device) {
		if (!empty($device['ip'])) {
    		    $device['online'] = $this->_is_online($device['ip']);
		} else {
    		    $device['online'] = null;
		}
	    }
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        $data['devices_json'] = json_encode(
            $devices,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        $this->page->view_form('wakeonlan', $data, lang('wakeonlan_app_name'));
    }

    public function save()  {
        if (!$this->_require_post())
            return;

        if (!$this->_check_rate_limit('save', 20, 60))
            return;

	$this->lang->load('wakeonlan');
        $this->load->library('wakeonlan/Wakeonlan_Library');

        $name = $this->_sanitize_name($this->input->post('name', TRUE));
        $mac = $this->_normalize_mac($this->input->post('mac', TRUE));
        $original_mac = $this->_normalize_mac($this->input->post('original_mac', TRUE));
	$ip = trim((string)$this->input->post('ip', TRUE));

        if ($name === '')
            return $this->_json_error(lang('wakeonlan_device_name_is_required'), 422);

        if (!$this->_is_valid_mac($mac))
            return $this->_json_error(lang('wakeonlan_mac_format_should_be'), 422);

        try {
            $devices = $this->wakeonlan_library->get_devices();
            $updated = array();
            $replaced = FALSE;

	    if ($original_mac === '') {
		foreach ($devices as $d) {
    		    if ($d['mac'] === $mac) {
        		return $this->_json_error(lang('wakeonlan_mac_is_added'), 409);
    		    }
		}
	    }

	    foreach ($devices as $device) {

		if ($original_mac !== '' && $device['mac'] === $original_mac) {

    		    foreach ($devices as $d) {
        		if ($d['mac'] === $mac && $d['mac'] !== $original_mac) {
            		    return $this->_json_error(lang('wakeonlan_mac_is_added'), 409);
        		}
    		    }

    		    $updated[] = [
        		'name' => $name,
        		'mac' => $mac,
			'ip' => $ip
    		    ];

    		    $replaced = TRUE;
    		    continue;
		}

		$updated[] = $device;
	    }

            if (!$replaced) {
                $updated[] = array(
                    'name' => $name,
                    'mac' => $mac,
		    'ip' => $ip
                );
            }

            $this->wakeonlan_library->set_devices($updated);

	    $devices = $this->wakeonlan_library->get_devices();

            return $this->_json_ok(array(
                'message' => lang('wakeonlan_device_saved'),
                'devices' => $devices,
                'csrf_hash' => $this->security->get_csrf_hash()
            ));
        } catch (Exception $e) {
            return $this->_json_error($e->getMessage(), 500);
        }
    }

    public function delete($mac = NULL) {
	$mac = urldecode($mac);
	$name = $this->input->get('name');

	$confirm_uri = '/app/wakeonlan/destroy/' . $mac;
	$cancel_uri = '/app/wakeonlan';

	$items = array(
	    "Name: " . $name . "<br></li><li>MAC: " . $mac
	);

	$this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
    }

    public function destroy($mac = NULL) {
	$this->load->library('wakeonlan/Wakeonlan_Library');

	try {
    	    $devices = $this->wakeonlan_library->get_devices();
    	    $updated = array();

    	    foreach ($devices as $device) {
        	if ($device['mac'] !== $mac)
            	    $updated[] = $device;
    	    }

    	    $this->wakeonlan_library->set_devices($updated);

    	    $this->page->set_status_deleted();
    	    redirect('/wakeonlan');

	} catch (Exception $e) {
    	    $this->page->view_exception($e);
	}
    }

    public function wake() {
	$this->lang->load('wakeonlan');
	$this->load->library('wakeonlan/Wakeonlan_Library');

        if (!$this->_require_post())
            return;

        if (!$this->_check_rate_limit('wake', 5, 60))
            return;

	$settings = $this->wakeonlan_library->get_settings();
	$bind_ip = isset($settings['bind_ip']) ? trim((string)$settings['bind_ip']) : '';
	$broadcast_ip = isset($settings['broadcast_ip']) ? trim((string)$settings['broadcast_ip']) : '';
        $mac = $this->_normalize_mac($this->input->post('mac', TRUE));

        if (!$this->_is_valid_mac($mac))
            return $this->_json_error(lang('wakeonlan_mac_format_should_be'), 422);

        try {
	    if ($bind_ip !== '' && !$this->wakeonlan_library->is_local_bind_ip($bind_ip))
		return $this->_json_error(sprintf(lang('wakeonlan_bind_ip_not_local'), $bind_ip), 422);

	    if ($broadcast_ip !== '' && !filter_var($broadcast_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
		return $this->_json_error(lang('wakeonlan_broadcast_ip_invalid_format'), 422);

	    $ok = $this->_send_magic_packet($mac, $bind_ip, $broadcast_ip, self::DEFAULT_PORT);

            if (!$ok)
                return $this->_json_error(lang('wakeonlan_request_not_sent'), 500);

            log_message('info', 'Wake-on-LAN packet sent to ' . $mac);

            return $this->_json_ok(array(
                'message' => lang('wakeonlan_request_sent'),
                'mac' => $mac,
                'csrf_hash' => $this->security->get_csrf_hash()
            ));
        } catch (Exception $e) {
            return $this->_json_error($e->getMessage(), 500);
        }
    }

    public function status() {
	$this->load->library('wakeonlan/Wakeonlan_Library');

	$devices = $this->wakeonlan_library->get_devices();

        foreach ($devices as &$device) {
    	    if (!empty($device['ip'])) {
        	$device['online'] = $this->_is_online($device['ip']);
    	    } else {
        	$device['online'] = null;
    	    }
	}

	return $this->_json_ok([
    	    'devices' => $devices
	]);
    }

    public function get_settings() {
	$this->load->library('wakeonlan/Wakeonlan_Library');

	try {
	    $settings = $this->wakeonlan_library->get_settings();

	    return $this->_json_ok(array(
		'settings' => $settings,
		'bind_ips' => $this->wakeonlan_library->get_local_ipv4_addresses()
	    ));
	} catch (Exception $e) {
	    return $this->_json_error($e->getMessage(), 500);
	}
    }

    public function save_settings() {
	$this->lang->load('wakeonlan');

	if (!$this->_require_post())
	    return;

	$this->load->library('wakeonlan/Wakeonlan_Library');

	$bind_ip = trim((string)$this->input->post('bind_ip', TRUE));
	$broadcast_ip = trim((string)$this->input->post('broadcast_ip', TRUE));

	if ($bind_ip !== '' && !filter_var($bind_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	    return $this->_json_error(lang('wakeonlan_bind_ip_invalid_format'), 422);

	if ($broadcast_ip !== '' && !filter_var($broadcast_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
	    return $this->_json_error(lang('wakeonlan_broadcast_ip_invalid_format'), 422);

	try {
	    if ($bind_ip !== '' && !$this->wakeonlan_library->is_local_bind_ip($bind_ip))
		return $this->_json_error(sprintf(lang('wakeonlan_bind_ip_not_local'), $bind_ip), 422);

	    $settings = array(
		'bind_ip' => $bind_ip,
		'broadcast_ip' => $broadcast_ip
	    );

	    $this->wakeonlan_library->set_settings($settings);

	    return $this->_json_ok(array(
		'message' => lang('wakeonlan_settings_saved'),
		'csrf_hash' => $this->security->get_csrf_hash(),
		'bind_ips' => $this->wakeonlan_library->get_local_ipv4_addresses()
	    ));
	} catch (Exception $e) {
	    return $this->_json_error($e->getMessage(), 500);
	}
    }

    private function _require_post() {
	if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
    	    $this->output->set_status_header(405);
    	    $this->output->set_output(json_encode([
        	'success' => false,
        	'message' => 'Method not allowed'
    	    ]));
    	    return FALSE;
	}

	return TRUE;
    }

    private function _sanitize_name($name) {
        $name = trim((string)$name);
        $name = preg_replace('/[[:cntrl:]\|]+/u', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return substr($name, 0, 64);
    }

    private function _normalize_mac($mac) {
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string)$mac));

        if (strlen($hex) !== 12)
            return '';

        return implode(':', str_split($hex, 2));
    }

    private function _is_valid_mac($mac) {
        return (bool)preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac);
    }

    private function _send_magic_packet($mac, $bind_ip, $broadcast_ip = '', $port = 9) {
	$mac = escapeshellarg(strtoupper(trim($mac)));
	$port = (int)$port;
	$cmd = 'wol';

	if ($bind_ip !== '')
	    $cmd .= ' -b ' . escapeshellarg($bind_ip);

	if ($broadcast_ip !== '')
	    $cmd .= ' -i ' . escapeshellarg($broadcast_ip);

	$cmd .= " -p $port $mac 2>&1";

	exec($cmd, $output, $code);

	return $code === 0;
    }

    private function _is_online($ip) {
	$ip = escapeshellarg($ip);

	exec("ping -c 1 -W 1 $ip 2>&1", $out, $code);

	return $code === 0;
    }

    private function _check_rate_limit($action, $limit, $window_seconds) {
	$this->lang->load('wakeonlan');

        $dir = '/var/clearos/framework/tmp';

        if (!is_dir($dir))
            $dir = sys_get_temp_dir();

        $key = md5($action . '|' . session_id() . '|' . $_SERVER['REMOTE_ADDR']);
        $path = $dir . '/wakeonlan-rate-' . $key . '.json';
        $now = time();
        $hits = array();

        if (is_file($path)) {
            $raw = @file_get_contents($path);
            $decoded = json_decode($raw, TRUE);

            if (is_array($decoded))
                $hits = $decoded;
        }

        $fresh = array();

        foreach ($hits as $ts) {
            if (($now - (int)$ts) < $window_seconds)
                $fresh[] = (int)$ts;
        }

        if (count($fresh) >= $limit) {
            $this->_json_error(lang('wakeonlan_too_many_requests'), 429);
            return FALSE;
        }

        $fresh[] = $now;
        @file_put_contents($path, json_encode($fresh), LOCK_EX);

        return TRUE;
    }

    private function _json_ok($payload = array()) {
        $payload['success'] = TRUE;
        return $this->_json_response($payload, 200);
    }

    private function _json_error($message, $status = 400) {
        return $this->_json_response(array(
            'success' => FALSE,
            'message' => $message
        ), $status);
    }

    private function _json_response($payload, $status) {
        $this->output->set_status_header($status);
        $this->output->set_content_type('application/json');
        $this->output->set_output(json_encode($payload));
    }
}