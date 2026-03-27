<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

$basePath = dirname(__DIR__);
$lockFile = $basePath.'/storage/framework/installed.lock';
$tokenHashFile = $basePath.'/storage/framework/install_token.hash';
$attemptsFile = $basePath.'/storage/framework/install_attempts.json';
$templateFile = $basePath.'/deploy/.env.installer.example';
$envFile = $basePath.'/.env';
$maxAttempts = 10;
$lockSeconds = 15 * 60;
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

function readAttempts(string $file): array
{
    if (! file_exists($file)) {
        return [];
    }

    $json = file_get_contents($file);
    if ($json === false) {
        return [];
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : [];
}

function writeAttempts(string $file, array $payload): void
{
    file_put_contents($file, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function denyInstaller(string $message, int $status = 403): never
{
    http_response_code($status);
    echo '<h1>Instalador bloqueado</h1><p>'.htmlspecialchars($message, ENT_QUOTES, 'UTF-8').'</p>';
    exit;
}

if (! is_dir($basePath.'/storage/framework')) {
    @mkdir($basePath.'/storage/framework', 0775, true);
}

if (file_exists($lockFile)) {
    denyInstaller('La app ya fue instalada. Elimina manualmente storage/framework/installed.lock solo si quieres reinstalar.');
}

$tokenHash = file_exists($tokenHashFile) ? trim((string) file_get_contents($tokenHashFile)) : '';
if ($tokenHash === '') {
    denyInstaller('No existe token de instalacion. Genera un nuevo ZIP de release para habilitar el instalador.');
}

$attempts = readAttempts($attemptsFile);
$record = $attempts[$clientIp] ?? ['count' => 0, 'locked_until' => 0];
$now = time();

if (($record['locked_until'] ?? 0) > $now) {
    $waitSeconds = ((int) $record['locked_until']) - $now;
    denyInstaller('Demasiados intentos fallidos. Vuelve a intentar en '.max(1, $waitSeconds).' segundos.', 429);
}

$providedToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
if ($providedToken === '') {
    denyInstaller('Falta el token de instalacion. Abre install.php?token=TU_TOKEN');
}

if (! hash_equals($tokenHash, hash('sha256', $providedToken))) {
    $record['count'] = ((int) ($record['count'] ?? 0)) + 1;
    if ($record['count'] >= $maxAttempts) {
        $record['locked_until'] = $now + $lockSeconds;
        $record['count'] = 0;
    }
    $attempts[$clientIp] = $record;
    writeAttempts($attemptsFile, $attempts);
    denyInstaller('Token invalido.');
}

unset($attempts[$clientIp]);
writeAttempts($attemptsFile, $attempts);

session_start();

if (empty($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}

$defaults = [
    'app_name' => 'SquadControl',
    'app_url' => ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://').($_SERVER['HTTP_HOST'] ?? 'localhost'),
    'db_host' => '127.0.0.1',
    'db_port' => '3306',
    'db_name' => 'squadcontrol',
    'db_user' => 'root',
    'db_pass' => '',
    'admin_name' => 'Administrador',
    'admin_email' => 'admin@example.com',
    'admin_password' => '',
];

if (file_exists($envFile)) {
    $env = file_get_contents($envFile) ?: '';
    foreach ([
        'APP_NAME' => 'app_name',
        'APP_URL' => 'app_url',
        'DB_HOST' => 'db_host',
        'DB_PORT' => 'db_port',
        'DB_DATABASE' => 'db_name',
        'DB_USERNAME' => 'db_user',
    ] as $envKey => $formKey) {
        if (preg_match('/^'.preg_quote($envKey, '/').'=(.*)$/m', $env, $match) === 1) {
            $defaults[$formKey] = trim($match[1], "\"' ");
        }
    }
}

$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    if (! hash_equals($_SESSION['installer_csrf'], (string) $csrf)) {
        $errors[] = 'Token CSRF invalido. Recarga la pagina.';
    }

    $data = [
        'app_name' => trim((string) ($_POST['app_name'] ?? '')),
        'app_url' => trim((string) ($_POST['app_url'] ?? '')),
        'db_host' => trim((string) ($_POST['db_host'] ?? '')),
        'db_port' => trim((string) ($_POST['db_port'] ?? '3306')),
        'db_name' => trim((string) ($_POST['db_name'] ?? '')),
        'db_user' => trim((string) ($_POST['db_user'] ?? '')),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'admin_name' => trim((string) ($_POST['admin_name'] ?? '')),
        'admin_email' => trim((string) ($_POST['admin_email'] ?? '')),
        'admin_password' => (string) ($_POST['admin_password'] ?? ''),
    ];

    foreach (['app_name', 'app_url', 'db_host', 'db_port', 'db_name', 'db_user', 'admin_name', 'admin_email', 'admin_password'] as $key) {
        if ($data[$key] === '') {
            $errors[] = "El campo {$key} es obligatorio.";
        }
    }

    if (! filter_var($data['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Correo de administrador invalido.';
    }

    if (! filter_var($data['app_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'URL de la app invalida. Ejemplo: https://tu-dominio.com';
    }

    if (! ctype_digit($data['db_port']) || (int) $data['db_port'] < 1 || (int) $data['db_port'] > 65535) {
        $errors[] = 'Puerto de base de datos invalido.';
    }

    $password = $data['admin_password'];
    $strongPassword = strlen($password) >= 12
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/\d/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);

    if (! $strongPassword) {
        $errors[] = 'La clave admin debe tener minimo 12 caracteres e incluir mayuscula, minuscula, numero y simbolo.';
    }

    if (! file_exists($basePath.'/vendor/autoload.php')) {
        $errors[] = 'Falta la carpeta vendor. Debes subir una version empaquetada con dependencias de Composer.';
    }

    if (! file_exists($basePath.'/public/build/manifest.json')) {
        $errors[] = 'Falta el build frontend (public/build). Debes subir el ZIP con assets compilados.';
    }

    if (! file_exists($templateFile)) {
        $errors[] = 'No existe la plantilla deploy/.env.installer.example';
    }

    if (empty($errors)) {
        $template = file_get_contents($templateFile);
        if ($template === false) {
            $errors[] = 'No se pudo leer la plantilla de entorno.';
        } else {
            $appKey = 'base64:'.base64_encode(random_bytes(32));
            $replacements = [
                'APP_NAME' => $data['app_name'],
                'APP_URL' => $data['app_url'],
                'APP_KEY' => $appKey,
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => $data['db_port'],
                'DB_DATABASE' => $data['db_name'],
                'DB_USERNAME' => $data['db_user'],
                'DB_PASSWORD' => $data['db_pass'],
                'MAIL_FROM_NAME' => '${APP_NAME}',
                'VITE_APP_NAME' => '${APP_NAME}',
            ];

            foreach ($replacements as $envKey => $value) {
                $escaped = str_contains($value, ' ') ? '"'.$value.'"' : $value;
                $template = preg_replace('/^'.preg_quote($envKey, '/').'=.*$/m', $envKey.'='.$escaped, (string) $template) ?? $template;
            }

            if (file_put_contents($envFile, $template) === false) {
                $errors[] = 'No se pudo escribir el archivo .env. Revisa permisos.';
            } else {
                require_once $basePath.'/vendor/autoload.php';

                /** @var \Illuminate\Foundation\Application $app */
                $app = require $basePath.'/bootstrap/app.php';
                $app->make(ConsoleKernel::class)->bootstrap();

                try {
                    Artisan::call('config:clear');
                    Artisan::call('cache:clear');
                    Artisan::call('migrate', ['--force' => true]);
                    Artisan::call('storage:link');

                    $user = User::query()->firstOrNew(['email' => $data['admin_email']]);
                    $user->name = $data['admin_name'];
                    $user->password = Hash::make($data['admin_password']);
                    $user->role = 'Admin';
                    $user->status = 'Activo';
                    $user->email_verified_at = now();
                    $user->save();

                    Artisan::call('optimize');

                    file_put_contents($lockFile, 'installed_at='.date('c').PHP_EOL);
                    file_put_contents($lockFile, 'admin_email='.$data['admin_email'].PHP_EOL, FILE_APPEND);
                    file_put_contents($lockFile, 'installed_ip='.$clientIp.PHP_EOL, FILE_APPEND);

                    @unlink($tokenHashFile);
                    @unlink($attemptsFile);

                    $deletedInstaller = @unlink(__FILE__);

                    $messages[] = 'Instalacion completada correctamente.';
                    $messages[] = 'Usuario administrador creado/actualizado: '.$data['admin_email'];
                    if (! $deletedInstaller) {
                        $messages[] = 'Aviso: no se pudo eliminar public/install.php automaticamente. Eliminalo manualmente.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Error durante la instalacion: '.$e->getMessage();
                }
            }
        }

        $defaults = array_merge($defaults, $data);
    }
}

function field(string $name, array $defaults): string
{
    return htmlspecialchars((string) ($defaults[$name] ?? ''), ENT_QUOTES, 'UTF-8');
}

?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instalador SquadControl</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f2f5f9; margin: 0; padding: 24px; color: #13213a; }
        .container { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,.08); padding: 24px; }
        h1 { margin-top: 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        label { font-size: 13px; font-weight: 700; display: block; margin-bottom: 6px; }
        input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #c9d4e5; border-radius: 8px; }
        .full { grid-column: 1 / -1; }
        .error { background: #ffe5e5; border: 1px solid #ffb8b8; color: #8a1f1f; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        .ok { background: #e7f8ec; border: 1px solid #b6ecc4; color: #14532d; padding: 10px; border-radius: 8px; margin-bottom: 12px; }
        button { margin-top: 16px; border: 0; background: #0f766e; color: #fff; padding: 12px 18px; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .muted { color: #4a5d7a; font-size: 13px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalador SquadControl</h1>
        <p class="muted">Este asistente prepara la app sin usar terminal: genera .env, migra base de datos y crea usuario administrador.</p>

        <?php foreach ($errors as $error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>

        <?php foreach ($messages as $message): ?>
            <div class="ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endforeach; ?>

        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($providedToken, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="grid">
                <div class="full"><label>Nombre App</label><input name="app_name" value="<?php echo field('app_name', $defaults); ?>"></div>
                <div class="full"><label>URL App</label><input name="app_url" value="<?php echo field('app_url', $defaults); ?>"></div>

                <div><label>DB Host</label><input name="db_host" value="<?php echo field('db_host', $defaults); ?>"></div>
                <div><label>DB Port</label><input name="db_port" value="<?php echo field('db_port', $defaults); ?>"></div>
                <div><label>DB Name</label><input name="db_name" value="<?php echo field('db_name', $defaults); ?>"></div>
                <div><label>DB User</label><input name="db_user" value="<?php echo field('db_user', $defaults); ?>"></div>
                <div class="full"><label>DB Password</label><input name="db_pass" type="password" value="<?php echo field('db_pass', $defaults); ?>"></div>

                <div><label>Admin Name</label><input name="admin_name" value="<?php echo field('admin_name', $defaults); ?>"></div>
                <div><label>Admin Email</label><input name="admin_email" type="email" value="<?php echo field('admin_email', $defaults); ?>"></div>
                <div class="full"><label>Admin Password</label><input name="admin_password" type="password"></div>
            </div>

            <button type="submit">Instalar ahora</button>
        </form>
    </div>
</body>
</html>
