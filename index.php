<?php
include('api/routeros_api.class.php');
include('config.php');
date_default_timezone_set('Asia/Makassar');

$API = new RouterosAPI();
$message = "";
$daftarUser = [];

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

if ($API->connect($ip, $username, $password, $port)) {
    if (isset($_GET['hapus'])) {
        $target = $_GET['hapus'];
        $user = $API->comm("/ip/hotspot/user/print", ["?name" => $target]);
        if (count($user) > 0) {
            $API->comm("/ip/hotspot/user/remove", [".id" => $user[0][".id"]]);
            $message = "Voucher <b>$target</b> berhasil dihapus.";
        }
    }

    if (isset($_GET['autodelete'])) {
        $deleted = 0;
        $users = $API->comm("/ip/hotspot/user/print");
        foreach ($users as $user) {
            if (isset($user["limit-uptime"], $user["last-logged-out"]) && ($user["comment"] ?? '') === 'auto-voucher') {
                $limitUptime = strtotime("+" . $user["limit-uptime"], strtotime($user["last-logged-out"]));
                if (time() > $limitUptime) {
                    $API->comm("/ip/hotspot/user/remove", [".id" => $user[".id"]]);
                    $deleted++;
                }
            }
        }
        $message = "$deleted voucher expired berhasil dihapus.";
    }

    $daftarUser = $API->comm("/ip/hotspot/user/print");
    $API->disconnect();
} else {
    die("❌ Gagal konek ke MikroTik");
}

// Statistik penggunaan
$totalUpload = 0;
$totalDownload = 0;
$totalUser = 0;
foreach ($daftarUser as $user) {
    if (($user['comment'] ?? '') === 'auto-voucher') {
        $totalUser++;
        $totalUpload += $user['bytes-out'] ?? 0;
        $totalDownload += $user['bytes-in'] ?? 0;
    }
}
$totalTraffic = $totalUpload + $totalDownload;
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Hotspot Voucher Manager</title>
  <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background-color: #f4f9ff;
        color: #333;
        padding: 20px;
        margin: 0;
    }
    h2, h3 {
        color: #006699;
    }
    a.button {
        display: inline-block;
        background-color: #007acc;
        color: #fff;
        padding: 10px 20px;
        margin: 10px 0;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
    }
    a.button:hover {
        background-color: #005f99;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        background-color: #fff;
        margin-top: 20px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    th {
        background-color: #007acc;
        color: white;
    }
    tr:nth-child(even) {
        background-color: #f2f9ff;
    }
    th, td {
        padding: 10px;
        text-align: center;
        border: 1px solid #ddd;
    }
    .success {
        background-color: #e0ffe0;
        padding: 15px;
        border: 1px solid #0c0;
        border-radius: 5px;
        margin-top: 10px;
    }
    .btn-delete {
        color: #d9534f;
        font-weight: bold;
        text-decoration: none;
    }
    .btn-delete:hover {
        text-decoration: underline;
    }
    input[type="text"] {
        padding: 8px;
        width: 250px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    ul { padding-left: 20px; }
    li { margin-bottom: 5px; }

    @media screen and (max-width: 768px) {
        table, thead, tbody, th, td, tr {
            display: block;
        }
        tr {
            margin-bottom: 10px;
            border: 1px solid #ccc;
        }
        td {
            text-align: right;
            padding-left: 50%;
            position: relative;
        }
        td::before {
            content: attr(data-label);
            position: absolute;
            left: 10px;
            width: 45%;
            text-align: left;
            font-weight: bold;
        }
    }
  </style>
</head>
<body>

<h2>📡 Manajemen Voucher Hotspot</h2>

<a href="generate.php" class="button">➕ Buat Voucher Baru</a>

<?php if ($message): ?>
  <div class="success"><?= $message ?></div>
<?php endif; ?>

<h3>🔎 Cari Voucher</h3>
<form method="GET">
  <input type="text" name="search" placeholder="Cari username..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
  <button type="submit">Cari</button>
</form>

<h3>📋 Daftar Voucher Aktif</h3>
<a href="?autodelete=1" class="button" style="background-color:#d9534f">🗑️ Hapus Voucher Expired</a>

<table>
  <tr>
    <th>No</th>
    <th>Username</th>
    <th>Password</th>
    <th>Profile</th>
    <th>Durasi</th>
    <th>Upload</th>
    <th>Download</th>
    <th>Logout Terakhir</th>
    <th>Aksi</th>
  </tr>
  <?php
  $no = 1;
  $search = isset($_GET['search']) ? strtolower(trim($_GET['search'])) : '';
  foreach ($daftarUser as $user):
    if (($user['comment'] ?? '') === 'auto-voucher'):
      if ($search && stripos($user['name'], $search) === false) continue;
      $upload = isset($user['bytes-out']) ? formatBytes($user['bytes-out']) : '-';
      $download = isset($user['bytes-in']) ? formatBytes($user['bytes-in']) : '-';
  ?>
    <tr>
      <td data-label="No"><?= $no++ ?></td>
      <td data-label="Username"><?= $user['name'] ?></td>
      <td data-label="Password"><?= $user['password'] ?? '-' ?></td>
      <td data-label="Profile"><?= $user['profile'] ?></td>
      <td data-label="Durasi"><?= $user['limit-uptime'] ?? '-' ?></td>
      <td data-label="Upload"><?= $upload ?></td>
      <td data-label="Download"><?= $download ?></td>
      <td data-label="Logout Terakhir"><?= $user['last-logged-out'] ?? '-' ?></td>
      <td data-label="Aksi">
        <a class="btn-delete" href="?hapus=<?= $user['name'] ?>" onclick="return confirm('Hapus voucher ini?')">Hapus</a>
      </td>
    </tr>
  <?php endif; endforeach; ?>
</table>

<h3>📊 Statistik Penggunaan</h3>
<ul>
  <li><strong>Total Voucher Aktif:</strong> <?= $totalUser ?></li>
  <li><strong>Total Upload:</strong> <?= formatBytes($totalUpload) ?></li>
  <li><strong>Total Download:</strong> <?= formatBytes($totalDownload) ?></li>
  <li><strong>Total Penggunaan:</strong> <?= formatBytes($totalTraffic) ?></li>
</ul>

</body>
</html>
