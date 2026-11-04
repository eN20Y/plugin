# BILLSYS GenieACS ONU Management

Standalone BILLSYS plugin based on the HotspotBilling OLT plugin structure.

## Files
- `plugin/genieacs.php` → install as `system/plugin/genieacs.php`
- `plugin/ui/genieacs.tpl` → install as `system/plugin/ui/genieacs.tpl`

## Setup
1. Install both files into the paths above (or use the BILLSYS Plugin Manager package).
2. Sign in as Admin or SuperAdmin.
3. Open **GenieACS ONU Management → Settings**.
4. Set the NBI URL, normally `http://GENIEACS_HOST:7557`. Do not use the GUI URL on port 3000.
5. Optionally configure HTTP Basic Auth if enabled on NBI.
6. Return to the plugin and refresh device discovery.

## Features in this first release
- Read-only discovery of registered GenieACS devices.
- Display GenieACS device ID, model/manufacturer, PPPoE username, WiFi SSIDs and last Inform.
- Bind/unbind a GenieACS device to a BILLSYS customer ID.
- Submit WiFi SSID/password changes for Huawei-style TR-069 parameter paths.
- Submit ONU reboot tasks.

## Safety
- NBI communication is server-side; credentials are never passed to JavaScript.
- Restrict NBI network access to the BILLSYS host or trusted private network.
- Use HTTPS or a private network. Do not expose NBI to the public Internet.
- This initial version stores device/customer mappings in `tbl_appconfig` JSON to avoid a schema migration.
- The assignment form records a numeric customer ID but does not yet validate the ID against the exact live `tbl_customers` schema. Confirm/extend that validation before production use.
- The plugin does not read or display stored WiFi passwords.
- Task submission is not proof of device execution. Verify task completion through GenieACS and the ONU.

## Notes
The 2.4 GHz mapping uses the verified HG8145V5 path:
`InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.KeyPassphrase`.

The 5 GHz mapping assumes WLANConfiguration.5, based on the supplied GenieACS preset; verify on each model before use.
