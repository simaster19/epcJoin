<?php
error_reporting(0);
session_start();
require 'config.php';

if (isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$error = '';

$pastebinUrl = PASTEBIN_URL; // dari config.php


function getUsers($url) {

    $cacheFile = 'cache_users.json';
    $cacheTime = 10;

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        $data = file_get_contents($cacheFile);
    } else {
        $data = @file_get_contents($url);
        if ($data) file_put_contents($cacheFile, $data);
    }

    if (!$data) return [];

    $json = json_decode($data, true);
    if (!$json || !isset($json['users'])) return [];

    return $json['users'];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $users = getUsers($pastebinUrl);
  

    $loginSuccess = false;

    foreach ($users as $user) {

        if (
            $user['username'] === $username &&
            password_verify($password, $user['password'])
        ) {
            $loginSuccess = true;
            break;
        }
    }

    if ($loginSuccess) {

        session_regenerate_id(true);

        $_SESSION['login'] = true;
        $_SESSION['user'] = $username;
        $_SESSION['fullName'] = $user["fullName"];
        $_SESSION['versiApp'] = $user["versiApp"];

        header("Location: index.php");
        exit;

    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login</title>

<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

<style>
body{
background: radial-gradient(circle at top,#1f2937,#020617);
}
</style>

</head>

<body class="min-h-screen text-white flex items-center justify-center">

<div class="w-full max-w-6xl grid md:grid-cols-2 bg-white/10 backdrop-blur-xl border border-white/10 rounded-3xl shadow-2xl overflow-hidden">

<!-- LEFT -->
<div class="hidden md:flex flex-col justify-center p-12 bg-white/5">

<h1 class="text-4xl font-bold mb-4">
Selamat Datang
</h1>

<p class="text-gray-300 mb-6">
Login untuk menggunakan tool ini.
</p>

<div class="space-y-3 text-gray-400 text-sm">
<p>✔ Hanya Ambil data EPC Jika Convert Ke File .CSV</p>
<p>✔ Dapat Ambil Semua data Jika Convert Ke File .XLSX</p>
<p>✔ Dapat Memfilter EPC(Error-Duplicate-Benar)</p>
<p>✔ Dapat Memfilter Encode-Decode(Bernilai TRUE) -> Khusus Hasil Save dari Mesin Encode.</p>
<p>✔ Prosses Export Yang Diambil Hanya EPC yang bukan EMPTY</p>
</div>

</div>

<!-- RIGHT -->
<div class="p-8 md:p-12 w-full">

<div class="max-w-md mx-auto">

<h2 class="text-3xl font-bold mb-2 text-center md:text-center">
Login
</h2>

<?php if ($error): ?>
<div class="bg-red-500 text-white p-3 rounded-lg mb-4 text-center text-sm">
<?= $error ?>
</div>
<?php endif; ?>
<form class="space-y-5" method="POST" action="">

<div>

<label class="text-sm text-gray-300">
Username
</label>

<input
type="text"
name="username"
required
placeholder="Username"
class="w-full mt-1 px-4 py-3 rounded-xl bg-white/5 border border-white/10 focus:border-blue-500 focus:outline-none"
/>

</div>

<div>

<label class="text-sm text-gray-300">
Password
</label>

<input
type="password"
name="password"
required
placeholder="••••••••"
class="w-full mt-1 px-4 py-3 rounded-xl bg-white/5 border border-white/10 focus:border-blue-500 focus:outline-none"
/>

</div>

<div class="flex justify-between text-sm text-gray-400">

</div>

<button
type="submit"
name="btnLogin"
class="w-full py-3 rounded-xl bg-blue-600 hover:bg-blue-700 transition font-semibold"
>
Login
</button>

</form>


</div>
<footer class="text-center text-sm text-gray-400 mt-10">
© 2026 Miftakhul Kirom. All rights reserved.
</footer>
</div>

</div>

</body>
</html>