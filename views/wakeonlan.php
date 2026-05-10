<?php

$this->lang->load('wakeonlan');

$csrf_name = $this->security->get_csrf_token_name();
$csrf_hash = $this->security->get_csrf_hash();

?>

<style>
#wol-table .actions .btn {
    margin-right: 4px;
}
@media (max-width: 767px) {
    #wol-table .actions {
        white-space: normal;
    }
    #wol-table .actions .btn {
        margin-bottom: 4px;
    }
}
#wakeonlan-settings-save-btn, #wakeonlan-settings-cancel-btn {
    display: none;
}
</style>

<script>
const lang_wakeonlan_wake = "<?php echo lang('wakeonlan_wake'); ?>";
const lang_base_edit = "<?php echo lang('base_edit'); ?>";
const lang_base_delete = "<?php echo lang('base_delete'); ?>";
const lang_wakeonlan_device_name_is_required = "<?php echo lang('wakeonlan_device_name_is_required'); ?>";
const lang_wakeonlan_mac_format_should_be = "<?php echo lang('wakeonlan_mac_format_should_be'); ?>";
const lang_wakeonlan_device_saved = "<?php echo lang('wakeonlan_device_saved'); ?>";
const lang_wakeonlan_request_error = "<?php echo lang('wakeonlan_request_error'); ?>";
const lang_wakeonlan_no_devices_added = "<?php echo lang('wakeonlan_no_devices_added'); ?>";
const lang_wakeonlan_request_sent = "<?php echo lang('wakeonlan_request_sent'); ?>";
const lang_wakeonlan_settings_saved = "<?php echo lang('wakeonlan_settings_saved'); ?>";
const lang_wakeonlan_settings_not_saved = "<?php echo lang('wakeonlan_settings_not_saved'); ?>";
const lang_wakeonlan_dont_use_bind = "<?php echo lang('wakeonlan_dont_use_bind'); ?>";
const lang_wakeonlan_dont_use_broadcast = "<?php echo lang('wakeonlan_dont_use_broadcast'); ?>";
</script>

<script type="application/json" id="wol-devices-json"><?php echo $devices_json; ?></script>

<div id="wol-root"
     data-save-uri="/app/wakeonlan/save"
     data-delete-uri="/app/wakeonlan/delete"
     data-wake-uri="/app/wakeonlan/wake"
     data-csrf-name="<?php echo html_escape($csrf_name); ?>"
     data-csrf-hash="<?php echo html_escape($csrf_hash); ?>"
     data-status-uri="/app/wakeonlan/status">

    <div id="wol-message" class="theme-hidden"></div>

<?php
    $buttons = array(
    anchor_custom(
        'javascript:void(0)',
        lang('wakeonlan_save'),
        'high',
        array(
            'id' => 'wakeonlan-settings-save-btn'
        )
    ),
    anchor_custom(
        'javascript:void(0)',
        lang('base_cancel'),
        'low',
        array(
            'id' => 'wakeonlan-settings-cancel-btn'
        )
    ),
    anchor_custom(
        'javascript:void(0)',
        lang('base_edit'),
        'high',
        array(
            'id' => 'wakeonlan-settings-edit-btn'
        )
    ),
);

echo form_open('wakeonlan');
echo form_header(lang('base_settings'));

echo field_input('bind', '', lang('wakeonlan_bind_ip'), true);
echo '<div class="help-block">' . lang('wakeonlan_bind_ip_help') . '</div>';
echo field_input('broadcast', '', lang('wakeonlan_broadcast_ip'), true);
echo '<datalist id="wakeonlan-broadcast-ip-list"></datalist>';
echo '<div class="help-block">' . lang('wakeonlan_broadcast_ip_help') . '</div>';
echo field_button_set($buttons);

echo form_footer();
echo form_close();
?>

    <div class="pull-right" style="margin-bottom: 15px">
        <button type="button" id="wol-add" class="btn btn-primary"><?php echo lang('wakeonlan_btn_add_device'); ?></button>
    </div>

    <div class="clearfix"></div>

    <div id="wol-editor" class="panel panel-default theme-hidden">
	<div class="panel-heading">
            <strong style="color: #7a7474"><?php echo lang('wakeonlan_device'); ?></strong>
        </div>
        <div class="panel-body">
            <input type="hidden" id="wol-original-mac" value="" />

            <div class="row" style="position: relative">
                <div class="col-sm-5">
                    <label for="wol-name"><?php echo lang('wakeonlan_name'); ?></label>
                    <input type="text" id="wol-name" class="form-control" maxlength="64" />
                </div>
                <div class="col-sm-4">
                    <label for="wol-mac"><?php echo lang('wakeonlan_mac_address'); ?></label>
                    <input type="text" id="wol-mac" class="form-control" placeholder="AA:BB:CC:DD:EE:FF" />
                </div>
		<div class="col-sm-4">
		    <label><?php echo lang('wakeonlan_ip_address'); ?></label>
		    <input type="text" id="wol-ip" class="form-control" placeholder="192.168.x.x" />
		</div>
		<div class="col-sm-3" style="display: flex;flex-direction: column;gap: 5px;position: absolute;right: 0;top: 50%;transform: translateY(-50%);">
                    <button type="button" id="wol-save" class="btn btn-success"><?php echo lang('wakeonlan_save'); ?></button>
                    <button type="button" style="color: #7a7474" id="wol-cancel" class="btn btn-default"><?php echo lang('base_cancel'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table id="wol-table" class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?php echo lang('wakeonlan_name'); ?></th>
                    <th><?php echo lang('wakeonlan_mac_address'); ?></th>
		    <th><?php echo lang('wakeonlan_ip_address'); ?></th>
                    <th style="width: 260px;"><?php echo lang('wakeonlan_actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="3"><?php echo lang('base_loading...'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script src="/app/wakeonlan/wakeonlan.js.php"></script>