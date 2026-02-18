<?php
// ===== Helper Auth (JSON storage) =====
if (session_status() === PHP_SESSION_NONE) session_start();

function users_path(){ return __DIR__ . '/storage/users.json'; }

function ensure_storage(){
  $p = users_path();
  $dir = dirname($p);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (!file_exists($p)) file_put_contents($p, json_encode((object)[]));
}

function load_users(){
  ensure_storage();
  $raw = @file_get_contents(users_path());
  $json = json_decode($raw, true);
  return is_array($json) ? $json : [];
}

function save_users(array $arr){
  ensure_storage();
  file_put_contents(users_path(), json_encode($arr, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
}

function seed_defaults(){
  $users = load_users();
  $changed = false;

  if (!isset($users['admin@samudera.com'])) {
    $users['admin@samudera.com'] = [
      'name' => 'Administrator',
      'role' => 'admin',
      'password_hash' => password_hash('dkpjatim2024', PASSWORD_DEFAULT),
    ];
    $changed = true;
  }
  if (!isset($users['user@samudera.com'])) {
    $users['user@samudera.com'] = [
      'name' => 'User',
      'role' => 'user',
      'password_hash' => password_hash('user12345', PASSWORD_DEFAULT),
    ];
    $changed = true;
  }
  if ($changed) save_users($users);
}

function find_user($email){
  $users = load_users();
  $email = strtolower(trim($email));
  return $users[$email] ?? null;
}

function add_user($name, $email, $role, $password){
  $users = load_users();
  $emailKey = strtolower(trim($email));
  if (isset($users[$emailKey])) return ['ok'=>false, 'msg'=>'Email sudah terdaftar.'];
  $users[$emailKey] = [
    'name' => $name,
    'role' => $role,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
  ];
  save_users($users);
  return ['ok'=>true];
}

function csrf_token(){
  if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf_token'];
}
function csrf_check($token){ return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? ''); }
