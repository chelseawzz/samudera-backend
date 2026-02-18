<?php
// api/dashboard_totals.php — realtime totals + DEBUG
declare(strict_types=1);
require __DIR__ . '/db.php';

header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET,OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
header('Content-Type: application/json; charset=utf-8');

$year  = (isset($_GET['tahun']) && ctype_digit($_GET['tahun'])) ? (int)$_GET['tahun'] : (int)date('Y');
$DEBUG = isset($_GET['debug']) && $_GET['debug'] === '1';

$pdo = pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== helpers ===== */
function q(PDO $pdo, string $sql, array $p = []) { $st=$pdo->prepare($sql); $st->execute($p); return $st; }
function scalar(PDO $pdo, string $sql, array $p = []) { $v=q($pdo,$sql,$p)->fetchColumn(); return $v===false?null:(float)$v; }
function tableExists(PDO $pdo,string $t):bool {
  return (bool)scalar($pdo,"SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? LIMIT 1",[$t]);
}
function columnExists(PDO $pdo,string $t,string $c):bool {
  return (bool)scalar($pdo,"SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? LIMIT 1",[$t,$c]);
}
function numFlex($raw):float{
  if($raw===null||$raw==='') return 0.0;
  $s=trim((string)$raw); $s=preg_replace('/[^\d,.\-]/','',$s);
  if(preg_match('/\d+\.\d{3},\d+$/',$s)){ $s=str_replace('.','',$s); $s=str_replace(',','.',$s); }
  elseif(preg_match('/\d+,\d{3}\.\d+$/',$s)){ $s=str_replace(',','',$s); }
  elseif(preg_match('/\d+\.\d{3}$/',$s)){ $s=str_replace('.','',$s); }
  elseif(preg_match('/\d+,\d+$/',$s)){ $s=str_replace(',','.',$s); }
  return (float)$s;
}
function sumCol(PDO $pdo,string $table,string $col,int $year,array &$trace=null):float{
  if(!tableExists($pdo,$table) || !columnExists($pdo,$table,$col)){
    if(is_array($trace)) $trace[]=['table'=>$table,'col'=>$col,'exists'=>false,'value'=>0];
    return 0.0;
  }
  $ycol=null; foreach(['tahun','thn','year','tahun_data'] as $c){ if(columnExists($pdo,$table,$c)){ $ycol=$c; break; } }
  $rows=q($pdo,"SELECT `$col` FROM `$table`".($ycol?" WHERE `$ycol`=?":""), $ycol?[$year]:[])->fetchAll(PDO::FETCH_COLUMN);
  $sum=0.0; foreach($rows as $r){ $sum+=numFlex($r); }
  if(is_array($trace)) $trace[]=['table'=>$table,'col'=>$col,'ycol'=>$ycol,'value'=>$sum];
  return $sum;
}

/* ===== calc ===== */
$tot = [
  'tangkap_ton'=>0.0,'budidaya_ton'=>0.0,'kpp_garam_ton'=>0.0,
  'pengolahan_unit'=>0.0,'ekspor_ton'=>0.0,'investasi_juta'=>0.0
];
$trace=[];

/* TANGKAP (Ton) */
foreach ([['tangkap_volume_bulanan','jumlah'],
          ['tangkap_produksi_matrix','volume_ton'],
          ['tangkap_ringkasan','volume_ton']] as [$t,$c]){
  $v=sumCol($pdo,$t,$c,$year,$trace['tangkap']);
  if($v>0){ $tot['tangkap_ton']=$v; break; }
}

/* BUDIDAYA (Ton) */
foreach ([['budidaya_produksi_kabkota','volume_ton'],
          ['budidaya_produksi','volume_ton'],
          ['budidaya_ringkasan','volume_ton']] as [$t,$c]){
  $v=sumCol($pdo,$t,$c,$year,$trace['budidaya']);
  if($v>0){ $tot['budidaya_ton']=$v; break; }
}

/* KPP GARAM (Ton) — banyak kandidat kolom */
$kppCols = [
  'Σ Prod (Ton)','Σ Prod (TON)','Σ Prod',
  'sigma_prod_ton','sigma_prod','prod_ton',
  'volume_produksi_ton','volume_ton','ton','jumlah_ton','total_ton'
];
$kppT   = ['kpp_garam','garam_ringkasan'];
$got=0.0;
foreach ($kppT as $tbl){
  foreach ($kppCols as $col){
    $v = sumCol($pdo,$tbl,$col,$year,$trace['kpp']);
    if($v>0){ $got=$v; break 2; }
  }
}
$tot['kpp_garam_ton']=$got;

/* PENGOLAHAN (Unit) */
foreach ([['pengolahan_pemasaran_olahjenis','jumlah_upi'],
          ['pengolahan_pemasaran_olahankab','jumlah_unit'],
          ['pengolahan_ringkasan','unit'],
          ['pengolahan_produk','jumlah_unit'],
          ['pengolahan_produksi','jumlah_unit']] as [$t,$c]){
  $v=sumCol($pdo,$t,$c,$year,$trace['pengolahan']);
  if($v>0){ $tot['pengolahan_unit']=$v; break; }
}

/* EKSPOR (Ton) */
foreach ([['ekspor_perikanan_total','volume_ton'],
          ['ekspor_perikanan_ringkasan','jumlah_ton'],
          ['ekspor_perikanan','volume_ton'],
          ['ekspor','ton']] as [$t,$c]){
  $v=sumCol($pdo,$t,$c,$year,$trace['ekspor']);
  if($v>0){ $tot['ekspor_ton']=$v; break; }
}

/* INVESTASI (Juta) */
foreach ([['investasi_sektor_total','nilai_rp_juta'],
          ['investasi_detail','nilai_investasi_rp_juta'],
          ['investasi_ringkasan','nilai_juta'],
          ['investasi_kp','nilai_juta']] as [$t,$c]){
  $v=sumCol($pdo,$t,$c,$year,$trace['investasi']);
  if($v>0){ $tot['investasi_juta']=$v; break; }
}

/* ===== output ===== */
$out = [
  'ok'=>true,
  'tahun'=>$year,
  'totals'=>array_map(fn($v)=>round($v,2),$tot),
  'units'=>[
    'tangkap_ton'=>'Ton','budidaya_ton'=>'Ton','kpp_garam_ton'=>'Ton',
    'pengolahan_unit'=>'Unit','ekspor_ton'=>'Ton','investasi_juta'=>'Juta'
  ]
];
if ($DEBUG) $out['debug']=$trace;
echo json_encode($out, JSON_UNESCAPED_UNICODE);
