<?php
$bootstrap = getenv('CLEAROS_BOOTSTRAP') ? getenv('CLEAROS_BOOTSTRAP') : '/usr/clearos/framework/shared';
require_once $bootstrap . '/bootstrap.php';
header('Content-Type: application/x-javascript');
?>

function normalizeMac(mac) {
    var hex = (mac || '').replace(/[^0-9a-f]/gi, '').toUpperCase();

    if (hex.length !== 12)
        return '';

    return hex.match(/.{1,2}/g).join(':');
}

function isValidMac(mac) {
    return /^([0-9A-F]{2}:){5}[0-9A-F]{2}$/.test(mac);
}

function showMessage(type, text) {
    var css = 'alert alert-' + type;
    $('#wol-message')
        .removeClass('theme-hidden alert-success alert-danger alert-info')
        .addClass(css)
        .text(text);
}

function hideMessage() {
    $('#wol-message')
        .addClass('theme-hidden')
        .removeClass('alert alert-success alert-danger alert-info')
        .text('');
}

function openEditor(device) {
    hideMessage();

    if (device) {
        $('#wol-original-mac').val(device.mac);
        $('#wol-name').val(device.name);
        $('#wol-mac').val(device.mac);
        $('#wol-ip').val(device.ip || '');
    } else {
        $('#wol-original-mac').val('');
        $('#wol-name').val('');
        $('#wol-mac').val('');
        $('#wol-ip').val('');
    }

    $('#wol-editor').removeClass('theme-hidden');
    $('#wol-name').focus();
}

function closeEditor() {
    $('#wol-original-mac').val('');
    $('#wol-name').val('');
    $('#wol-mac').val('');
    $('#wol-editor').addClass('theme-hidden');
}

function ensureBindIpSelect() {
    if ($('#bind_select').length)
	return;

    $('<select id="bind_select" class="form-control hidden"></select>').insertAfter($('#bind'));
}

function populateBindIpSelect(bindIps, selectedIp) {
    ensureBindIpSelect();

    var $select = $('#bind_select');
    $select.empty();

    $('<option>')
	.attr('value', '')
	.text(lang_wakeonlan_dont_use_bind)
	.appendTo($select);

    $.each(bindIps || [], function (index, item) {
	var ip = item.ip || '';
	var iface = item.interface || '';
	var label = ip;

	if (iface !== '')
	    label += ' — ' + iface;

	if (ip === '')
	    return;

	$('<option>')
	    .attr('value', ip)
	    .text(label)
	    .appendTo($select);
    });

    if (selectedIp && !$select.find('option[value="' + selectedIp.replace(/"/g, '\\"') + '"]').length) {
	$('<option>')
	    .attr('value', selectedIp)
	    .text(selectedIp)
	    .appendTo($select);
    }

    $select.val(selectedIp || '');
}

function populateBroadcastList(bindIps) {
    var $list = $('#wakeonlan-broadcast-ip-list');
    var seen = {};

    if (!$list.length)
	return;

    $list.empty();

    $.each(bindIps || [], function (index, item) {
	var broadcast = item.broadcast || '';
	var iface = item.interface || '';
	var label = iface;

	if (broadcast === '' || seen[broadcast])
	    return;

	seen[broadcast] = true;

	$('<option>')
	    .attr('value', broadcast)
	    .attr('label', label)
	    .appendTo($list);
    });
}

function settingsEditor(show) {
    ensureBindIpSelect();

    if (show) {
	$('#wakeonlan-settings-edit-btn').hide();
	$('#wakeonlan-settings-cancel-btn').show();
	$('#wakeonlan-settings-save-btn').show();

	$('#bind').attr('class', 'hidden');
	$('#bind_select').attr('class', 'form-control');
	$('#bind_text').attr('class', 'hidden');

        $('#broadcast')
	    .attr('class', 'form-control')
	    .attr('list', 'wakeonlan-broadcast-ip-list')
	    .attr('placeholder', '192.168.x.255');
        $('#broadcast_text').attr('class', 'hidden');
    } else {
        $('#wakeonlan-settings-edit-btn').show();
        $('#wakeonlan-settings-cancel-btn').hide();
        $('#wakeonlan-settings-save-btn').hide();

	$('#bind').attr('class', 'hidden');
	$('#bind_select').attr('class', 'hidden');
	$('#bind_text').attr('class', 'form-control');

	$('#broadcast').attr('class', 'hidden');
	$('#broadcast_text').attr('class', 'form-control');
    }

    fetch('/app/wakeonlan/get_settings')
    .then(r => r.json())
    .then(data => {
	var bindIp = '';
	var broadcastIp = '';

	if (data.settings && data.settings.bind_ip)
	    bindIp = data.settings.bind_ip;

	if (data.settings && data.settings.broadcast_ip)
	    broadcastIp = data.settings.broadcast_ip;

	populateBindIpSelect(data.bind_ips || [], bindIp);
	populateBroadcastList(data.bind_ips || []);

	if (show) {
	    $('#bind_select').val(bindIp || '');
	    $('#broadcast').val(broadcastIp || '');
	} else {
	    $('#bind_text').text(bindIp || lang_wakeonlan_dont_use_bind);
	    $('#broadcast_text').text(broadcastIp || lang_wakeonlan_dont_use_broadcast);
	}
    });
}

$(document).ready(function () {
    var $root = $('#wol-root');

    if (!$root.length)
        return;

    var devices = [];
    var csrfName = $root.attr('data-csrf-name') || '';
    var csrfHash = $root.attr('data-csrf-hash') || '';

    var saveUri = $root.attr('data-save-uri');
    var deleteUri = $root.attr('data-delete-uri');
    var wakeUri = $root.attr('data-wake-uri');
    var statusUri = $root.attr('data-status-uri');

    try {
        devices = JSON.parse($('#wol-devices-json').text() || '[]');
    } catch (e) {
        devices = [];
    }

    function postAction(url, payload, successText, deviceName) {
	if (csrfName !== '')
    	    payload[csrfName] = csrfHash;

	$.ajax({
    	    url: url,
    	    type: 'POST',
    	    dataType: 'json',
    	    data: payload
	}).done(function (response) {
    	    if (response.csrf_hash)
        	csrfHash = response.csrf_hash;

    	    if (response.devices)
        	devices = response.devices;

    	    renderTable();
	    refreshStatus();

    	    if (successText)
        	showMessage('success', successText + ": " + deviceName);
	}).fail(function (xhr) {
    	    var message = lang_wakeonlan_request_error;

    	    if (xhr.responseJSON && xhr.responseJSON.message)
        	message = xhr.responseJSON.message;

    	    showMessage('danger', message);
	});
    }

    function refreshStatus() {
	$.ajax({
    	    url: statusUri,
    	    type: 'GET',
    	    dataType: 'json'
	}).done(function (response) {
    	    if (response.devices) {
        	devices = response.devices;
        	renderTable();
    	    }
	});
    }

    function renderTable() {
	var $tbody = $('#wol-table tbody');
	$tbody.empty();

	if (!devices.length) {
    	    $tbody.append(
        	$('<tr>').append(
            	    $('<td>')
                	.attr('colspan', 3)
                	.text(lang_wakeonlan_no_devices_added)
        	)
    	    );
    	    return;
	}

	$.each(devices, function (index, device) {
    	    var $tr = $('<tr>');

    	    $tr.append($('<td>').text(device.name));
    	    $tr.append($('<td>').addClass('text-nowrap').text(device.mac));
    	    var statusIcon = '';

    	    if (device.online === true)
        	statusIcon = ' 🔵'
    	    else if (device.online === false)
        	statusIcon = ' 🔴';
    	    else
        	statusIcon = ''

    	    $tr.append(
        	$('<td>').addClass('text-nowrap').text((device.ip || '') + statusIcon)
    	    );

    	    var $actions = $('<td>').addClass('actions text-nowrap');

    	    $actions.append(
        	$('<button type="button" class="btn btn-success btn-xs">' + lang_wakeonlan_wake + '</button>')
            	    .on('click', function () {
                	hideMessage();
                	postAction(wakeUri, { mac: device.mac }, lang_wakeonlan_request_sent, device.name);
            	    })
    	    );

    	    $actions.append(
        	$('<button type="button" style="color: #7a7474" class="btn btn-default btn-xs">' + lang_base_edit + '</button>')
            	    .on('click', function () {
                	openEditor(device);
            	    })
    	    );

    	    $actions.append(
        	$('<button type="button" class="btn btn-danger btn-xs">' + lang_base_delete + '</button>')
            	    .on('click', function () {
                	window.location.href = "/app/wakeonlan/delete/" + encodeURIComponent(device.mac) + "?name=" + encodeURIComponent(device.name);
            	    })
    	    );

    	    $tr.append($actions);
    	    $tbody.append($tr);
	});
    }

    setInterval(refreshStatus, 10000);

    $('#wol-add').on('click', function () {
        openEditor(null);
    });

    $('#wol-cancel').on('click', function () {
        closeEditor();
    });

    $('#wol-save').on('click', function () {
        hideMessage();

        var name = $.trim($('#wol-name').val());
        var mac = normalizeMac($('#wol-mac').val());
        var originalMac = $('#wol-original-mac').val() || '';
	originalMac = originalMac ? normalizeMac(originalMac) : '';
	var ip = $.trim($('#wol-ip').val());

        if (name === '') {
            showMessage('danger', lang_wakeonlan_device_name_is_required);
            return;
        }

        if (!isValidMac(mac)) {
            showMessage('danger', lang_wakeonlan_mac_format_should_be);
            return;
        }

        postAction(saveUri, {
            original_mac: originalMac,
            name: name,
            mac: mac,
	    ip: ip
        }, lang_wakeonlan_device_saved, name);

        closeEditor();
    });

    renderTable();
    settingsEditor(false);

    $('#wakeonlan-settings-edit-btn').on('click', function (e) {
	settingsEditor(true);
    });
    $('#wakeonlan-settings-save-btn').on('click', function (e) {
	const form = new URLSearchParams();

	form.append(
	    'bind_ip',
	    $('#bind_select').val() || ''
	);

	form.append(
	    'broadcast_ip',
	    $('#broadcast').val() || ''
	);

	if (csrfName !== '')
	    form.append(csrfName, csrfHash);

	fetch('/app/wakeonlan/save_settings', {
	    method: 'POST',
	    headers: {
    		'Content-Type': 'application/x-www-form-urlencoded'
	    },
	    body: form
	})
	.then(r => r.json())
	.then(data => {
	    if (data.csrf_hash)
    		csrfHash = data.csrf_hash;

	    populateBroadcastList(data.bind_ips || []);
	    settingsEditor(false);

	    if (data.success) {
		showMessage('success', lang_wakeonlan_settings_saved);
	    } else {
		showMessage('danger', data.message || lang_wakeonlan_settings_not_saved);
	    }
	});
    });
    $('#wakeonlan-settings-cancel-btn').on('click', function (e) {
        settingsEditor(false);
    });
});

// vim: syntax=javascript ts=4
