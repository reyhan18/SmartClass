<?php
include('api/routeros_api.class.php');
include('config.php');
date_default_timezone_set('Asia/Makassar');

$API = new RouterosAPI();
$voucherBaru = null;
$error = null;

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    if ($API->connect($ip, $username, $password, $port)) {
        $profile_name = "voucher";
        $username_voucher = 'Informatika' . rand(1000, 9999);
        $password_voucher = rand(1000, 9999);
        $uptime_limit = "12h";

        // Buat profil jika belum ada
        $existingProfiles = $API->comm("/ip/hotspot/user/profile/print", ["?name" => $profile_name]);
        if (count($existingProfiles) === 0) {
            $API->comm("/ip/hotspot/user/profile/add", [
                "name" => $profile_name,
                "shared-users" => 1,
                "session-timeout" => $uptime_limit
            ]);
        }

        // Tambah user voucher
        $API->comm("/ip/hotspot/user/add", [
            "name" => $username_voucher,
            "password" => $password_voucher,
            "profile" => $profile_name,
            "limit-uptime" => $uptime_limit,
            "comment" => "auto-voucher"
        ]);

        $voucherBaru = [
            'username' => $username_voucher,
            'password' => $password_voucher,
            'profile' => $profile_name,
            'durasi' => $uptime_limit
        ];

        $API->disconnect();
    } else {
        $error = "Gagal konek ke MikroTik.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buat Voucher</title>
  <style>
    body {
        font-family: Arial, sans-serif;
        background-color: #f0f9ff;
        padding: 30px;
        color: #333;
    }
    .success {
        background-color: #e0ffe0;
        padding: 15px;
        border: 1px solid #090;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    .error {
        background-color: #ffe0e0;
        padding: 15px;
        border: 1px solid #900;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    form button {
        background-color: #007acc;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 16px;
        cursor: pointer;
    }
    form button:hover {
        background-color: #005f99;
    }
    a.button {
        display: inline-block;
        margin-top: 20px;
        padding: 10px 15px;
        background: #555;
        color: #fff;
        text-decoration: none;
        border-radius: 5px;
    }
    a.button:hover {
        background: #333;
    }
  </style>
</head>
<body>

<h2>🎫 Buat Voucher Hotspot</h2>

<form method="POST">
    <button type="submit" name="generate">🔄 Generate Voucher</button>
</form>

<?php if ($voucherBaru): ?>
  <div class="success">
    <strong>Voucher berhasil dibuat:</strong><br>
    Username: <?= $voucherBaru['username'] ?><br>
    Password: <?= $voucherBaru['password'] ?><br>
    Profile: <?= $voucherBaru['profile'] ?><br>
    Durasi: <?= $voucherBaru['durasi'] ?>
  </div>
<?php elseif ($error): ?>
  <div class="error"><?= $error ?></div>
<?php endif; ?>

</body>
</html>
