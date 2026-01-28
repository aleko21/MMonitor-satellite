# MMonitor - Moodle Server Satellite

**MMonitor** is a Moodle local plugin designed for real-time and historical server resource monitoring. It acts as a "Satellite" to gather critical data (CPU, RAM, Disk, Users, Cron status) and securely exposes it to an external central dashboard or displays it directly within the Moodle administration interface.

![Moodle Version](https://img.shields.io/badge/Moodle-4.x%20%2F%205.x-orange)
![License](https://img.shields.io/badge/License-GPLv3-blue)
![Status](https://img.shields.io/badge/Status-Stable-green)

## 🚀 Key Features

### 📊 Live Dashboard
* **Real Resources:** Calculates precise **CPU usage** (sum of container processes via `ps`) and **RAM usage** (via `free -m`) instead of generic Load Averages.
* **System Health:** Monitors **Cron status** (alerts if stalled for > 5 mins) and **Moodledata Disk usage**.
* **Traffic:** Tracks concurrent active users (sessions within the last 5 minutes).
* **Plugin Audit:** Lists local/custom plugins and checks for version updates.

### 📈 History & Charts
* Interactive charts powered by *Chart.js* (native Moodle integration).
* **Correlation:** Visual overlay of Users, CPU, and RAM to identify load patterns.
* Selectable Timeframes: 24 Hours, 3 Days, 7 Days, 30 Days.
* Raw data table for precise verification.

### 🔒 Secure API (JSON Endpoint)
* Exposes data via a restricted `fetch.php` endpoint.
* **Maximum Security:** JSON reports are stored in `$CFG->dataroot` (Moodledata), making them inaccessible via direct web URL.
* **Double Authentication:** Access is protected by a **Secret Key** and an **IP Whitelist** (configurable via Admin settings).

---

## 📋 Requirements

* Moodle 3.11 or higher.
* Linux Server environment (the plugin relies on `ps` and `free` shell commands).
* PHP `shell_exec` function enabled (must not be listed in `disable_functions` in `php.ini`).
* Moodle Cron configured and running.

---

## 🛠 Installation

1.  Download the plugin folder.
2.  Rename the folder to `mmonitor`.
3.  Upload the folder to your Moodle installation: `your_moodle_path/local/mmonitor`.
4.  Log in as Administrator and run the database upgrade.

---

## ⚙️ Configuration

Go to: **Site Administration > Plugins > Local plugins > MMonitor**.

1.  **Secret Key:** Set a strong alphanumeric string (used for external access).
2.  **VPS IP (Whitelist):** Enter the IP address of the external server authorized to fetch the JSON data. Use `0.0.0.0` to disable IP checking (not recommended).
3.  **Log Retention:** Days to keep historical JSON files (default: 7 days).

### Scheduled Task Setup
The plugin uses a Scheduled Task to generate reports. Ensure it is enabled:
* Go to *Server > Scheduled tasks*.
* Find **"MMonitor Report Generation"** (`\local_mmonitor\task\generate_report`).
* Set the frequency (Recommended: **Every 5 or 10 minutes**).

---

## 📡 External Integration (API)

To integrate MMonitor with a central dashboard (e.g., a Python script on a VPS), use the following secure endpoint:

**URL:**
`https://your-moodle-site.com/local/mmonitor/fetch.php?secret=YOUR_SECRET_KEY`

**HTTP Responses:**
* `200 OK`: Returns the JSON data.
* `403 Forbidden`: Requesting IP is not whitelisted.
* `401 Unauthorized`: Invalid Secret Key.
* `404 Not Found`: Report not generated yet (wait for the Cron to run).

### JSON Structure Example
```json
{
    "metadata": {
        "timestamp": 1706631234,
        "moodle_release": "4.3.2",
        "core_update_available": null
    },
    "server_status": {
        "cpu_local_percent": 15.2,
        "ram_usage": { "percent": 45.0, "used": 4096, "total": 8192 },
        "concurrent_users": 25,
        "cron_delay_sec": 120,
        "disk_usage": { "percent": 65.5, "free_gb": 120 }
    },
    "plugins_report": [ ... ]
}
