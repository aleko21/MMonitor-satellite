<?php
$string['pluginname'] = 'MMonitor Satellite';

// Dashboard Strings
$string['dashboard_title'] = 'MMonitor Dashboard';
$string['dashboard_heading'] = 'Moodle Satellite Status';
$string['go_to_dashboard'] = 'Go to Visual Dashboard';
$string['task_name'] = 'MMonitor: Generate Status Report';

// General Settings
$string['general_settings'] = 'General Configuration';
$string['vps_ip'] = 'Monitoring IP Whitelist';
$string['vps_ip_desc'] = 'For security reasons, enter the IP address of the monitoring server here. Requests from other IPs will be blocked. <br>Use <code>0.0.0.0</code> to allow access from any IP (e.g., dynamic IPs or Docker internal networks).';
$string['secret_key'] = 'Secret Key (Token)';
$string['secret_key_desc'] = 'Copy this key and paste it into your monitoring panel (Telegraf/Grafana). Without this key, data cannot be read.';
$string['log_retention'] = 'Log Retention';
$string['log_retention_desc'] = 'How many days to keep the JSON history in the moodledata folder.';

// Select Options
$string['days_1'] = '1 Day';
$string['days_3'] = '3 Days';
$string['days_7'] = '7 Days';
$string['days_14'] = '14 Days';
$string['days_30'] = '30 Days';

// Advanced Settings (Resource Calibration)
$string['advanced_settings'] = 'Resource Calibration';
$string['advanced_info'] = '
<div style="background-color: #e7f1ff; border-left: 5px solid #0d6efd; padding: 15px; margin-bottom: 20px;">
    <h4 style="margin-top:0;">🛑 Read before configuring!</h4>
    <p>MMonitor attempts to automatically detect server resources. However, depending on your hosting type, it might "see too much".</p>
    
    <strong>CASE A: Dedicated Server or VPS (e.g., AWS EC2, DigitalOcean, On-Premise)</strong><br>
    The OS has exclusive access to the hardware.<br>
    ✅ <b>Leave the fields below at 0 (or empty).</b> Automatic detection will work perfectly.
    <hr style="margin: 10px 0; border-color: #b6d4fe;">
    
    <strong>CASE B: Shared Hosting (e.g., cPanel, Plesk, Managed Hosting)</strong><br>
    Your site shares the server with hundreds of others. The OS sees 64GB RAM/10TB Disk, but your plan only allows 2GB RAM/50GB Disk.<br>
    ⚠️ <b>Action Required:</b> Enter your exact plan limits below, otherwise charts will show wrong data (e.g., "1% used" when you are actually full).
    <hr style="margin: 10px 0; border-color: #b6d4fe;">

    <strong>CASE C: Docker / Kubernetes Containers</strong><br>
    Usually, MMonitor detects container limits (Cgroups). If charts show Host RAM instead of Container RAM:<br>
    ⚠️ <b>Action Required:</b> Manually enter the container limits below.
</div>';

$string['manual_ram_mb'] = 'Override RAM Limit (MB)';
$string['manual_ram_mb_desc'] = 'Set this <b>ONLY</b> for "CASE B" or "CASE C".<br>Enter the RAM amount in <b>Megabytes</b> assigned to your account.<br><i>Examples: 2GB = <code>2048</code>, 4GB = <code>4096</code>, 8GB = <code>8192</code>.</i><br>Leave <b>0</b> for automatic detection.';

$string['manual_disk_gb'] = 'Override Disk Limit (GB)';
$string['manual_disk_gb_desc'] = 'Set this <b>ONLY</b> if you have a specific disk quota different from the physical disk size.<br>Enter the amount in <b>Gigabytes</b>.<br><i>Example: If your plan is 250GB, type <code>250</code>.</i><br>Leave <b>0</b> for automatic detection.';