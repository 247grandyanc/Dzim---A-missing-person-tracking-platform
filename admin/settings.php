<?php
require_once __DIR__ . '/includes/admin-auth.php';
$admin = admin_authenticate();

// Only super admins can change settings
if ($admin['role'] !== 'SUPER_ADMIN') {
    header('Location: dashboard.php');
    exit();
}

// Get current settings
$settings = $gh->query("SELECT * FROM system_settings")->fetch_all(MYSQLI_ASSOC);
$settings_map = [];
foreach ($settings as $setting) {
    $settings_map[$setting['setting_key']] = $setting['setting_value'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST['settings'] as $key => $value) {
        $value = clean_input($value);
        
        // Validate boolean settings
        if (in_array($key, ['REQUIRE_GH_IP', 'BIOMETRICS_ENABLED'])) {
            $value = $value ? '1' : '0';
        }
        
        // Update setting
        $stmt = $gh->prepare("
            INSERT INTO system_settings (setting_key, setting_value) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->bind_param("sss", $key, $value, $value);
        $stmt->execute();
    }
    
    log_admin_action($admin['admin_id'], "UPDATE_SETTINGS", '');
    header("Location: settings.php?success=Settings+updated");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/admin-nav.php'; ?>
    <title>System Settings | SEACH-GH</title>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex h-screen">
        <?php include __DIR__ . '/includes/admin-sidebar.php'; ?>
        
        <div class="flex-1 overflow-y-auto">
            <div class="container mx-auto px-6 py-8">
                <h1 class="text-3xl font-bold mb-8">System Settings</h1>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-900/30 border border-green-700 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <?= htmlspecialchars($_GET['success']) ?>
                    </div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
                        <h2 class="text-xl font-bold mb-4">General Settings</h2>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-700 rounded-lg">
                                <div>
                                    <h3 class="font-medium">Require Ghana IP</h3>
                                    <p class="text-sm text-gray-400">Restrict access to Ghanaian IP addresses only</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[REQUIRE_GH_IP]" value="1" 
                                           class="sr-only peer" <?= $settings_map['REQUIRE_GH_IP'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between p-4 bg-gray-700 rounded-lg">
                                <div>
                                    <h3 class="font-medium">Enable Biometrics</h3>
                                    <p class="text-sm text-gray-400">Allow facial recognition searches</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="settings[BIOMETRICS_ENABLED]" value="1" 
                                           class="sr-only peer" <?= $settings_map['BIOMETRICS_ENABLED'] ? 'checked' : '' ?>>
                                    <div class="w-11 h-6 bg-gray-600 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                </label>
                            </div>
                            
                            <div class="p-4 bg-gray-700 rounded-lg">
                                <h3 class="font-medium mb-2">Max Free Searches</h3>
                                <p class="text-sm text-gray-400 mb-2">Number of free searches allowed per user</p>
                                <input type="number" name="settings[MAX_FREE_SEARCHES]" min="0" max="100" 
                                       value="<?= htmlspecialchars($settings_map['MAX_FREE_SEARCHES']) ?>" 
                                       class="w-full bg-gray-600 border border-gray-500 rounded-lg px-4 py-2">
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 mb-6">
                        <h2 class="text-xl font-bold mb-4">Allowed Countries</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <?php 
                            $countries = [
                                'GH' => 'Ghana',
                                'US' => 'United States',
                                'UK' => 'United Kingdom',
                                'NG' => 'Nigeria',
                                'ZA' => 'South Africa',
                                'KE' => 'Kenya'
                            ];
                            
                            $allowed = $gh->query("SELECT country_code FROM allowed_countries WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
                            $allowed_codes = array_column($allowed, 'country_code');
                            
                            foreach ($countries as $code => $name): ?>
                                <div class="flex items-center p-3 bg-gray-700 rounded-lg">
                                    <input type="checkbox" id="country_<?= $code ?>" name="countries[]" 
                                           value="<?= $code ?>" <?= in_array($code, $allowed_codes) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-600 rounded">
                                    <label for="country_<?= $code ?>" class="ml-2 text-sm text-gray-300">
                                        <?= htmlspecialchars($name) ?> (<?= $code ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-6 py-3 rounded-lg font-medium">
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
</body>
</html>