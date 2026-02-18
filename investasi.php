<?php
// investasi.php — Upload + Viewer
require_once __DIR__.'/auth_guard.php';  // 🔐 cek login & status admin
require_login();                         // wajib login dulu

// ⤵️ Izinkan mode readonly kalau dibuka lewat dashboard
$IS_DASH = isset($_GET['dashboard']) && $_GET['dashboard'] === '1';
if (!$IS_DASH) {
  require_admin(); // hanya admin kalau buka langsung
}

require_once __DIR__.'/protected_template.php';
start_protected_page('Investasi KP', 'investasi');
?>

<style>
:root{--toska:#0097a7;--head:#0b1b2b;--line:#e2e8f0}
.field-label{font-weight:700;color:#0f172a}.hint{font-size:.78rem;color:#64748b}
.nice-input{display:flex;align-items:center;gap:.5rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:.6rem .75rem;box-shadow:0 2px 6px rgba(2,8,20,.04)}
.nice-input input{width:100%;border:none;outline:none;background:transparent;font-size:.95rem;appearance:textfield;text-align:center}
.nice-input input::-webkit-outer-spin-button,.nice-input input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.filebox{display:flex;align-items:center;gap:.6rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:.45rem .5rem;box-shadow:0 2px 6px rgba(2,8,20,.04)}
.filebox input[type=file]{display:none}
.filebox .pick{display:inline-flex;align-items:center;gap:.45rem;background:var(--toska);color:#fff;font-weight:700;border-radius:10px;padding:.55rem .9rem;box-shadow:0 6px 18px rgba(0,151,167,.25)}
.filebox .pick:hover{background:#008a9d}.filebox .name{flex:1;color:#475569;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.btn-primary{background:var(--toska);color:#fff;font-weight:700;border-radius:12px;padding:.65rem 1rem;box-shadow:0 6px 18px rgba(0,151,167,.25)}
.btn-ghost{background:#fff;color:#0097a7;font-weight:700;border:1px solid var(--toska);border-radius:12px;padding:.65rem 1rem}
.btn-ghost:hover{background:#f1fefe}.btn-xs{padding:.32rem .5rem;border-radius:10px;font-size:.78rem}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--toska);color:#0b7285;border-radius:10px;background:#fff}
.icon-btn:hover{background:#f1fefe}.icon-btn[disabled],.btn-primary[disabled],.btn-ghost[disabled],.filebox .pick[disabled]{opacity:.5;cursor:not-allowed}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 2px 6px rgba(2,8,20,.04)}.card-h{padding:6px 10px;border-bottom:1px solid var(--line);font-weight:700}
.card-b{padding:8px 10px}
.panel-grid{display:grid;grid-template-columns:1fr;gap:20px}
@media (min-width:1024px){.panel-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #e2e8f0;padding:.5rem .75rem;font-size:.875rem!important;line-height:1.25rem!important;background:#fff}
th{font-weight:700}
thead.bg-samudera th{background:#0b1b2b!important;color:#fff!important;border-color:#fff!important;font-weight:700!important;font-size:.875rem!important;text-transform:none!important;letter-spacing:0!important;white-space:nowrap}
.thead-tw th{white-space:nowrap;color:#fff!important;border-color:#fff!important;font-weight:700!important;font-size:.875rem!important;letter-spacing:.01em!important;background:var(--head)!important}
tr.row-total td{font-weight:700;background:#f8fafc}
.small-actions{display:flex;gap:.4rem;align-items:center}
.sec-head{display:flex;align-items:center;justify-content:space-between}
.subnote{font-size:.78rem;color:#64748b;font-style:italic}
.note-err{color:#dc2626;font-size:.85rem}
.note-ok{color:#059669;font-size:.85rem}
.badge-ok{font-size:.75rem;margin-left:.25rem;color:#059669}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

<h1 class="text-2xl font-bold mb-2">Investasi KP</h1>
<p class="text-slate-600 mb-1"><b>Upload Excel (.xlsx). Sistem mendeteksi template &amp; menampilkan tabel.</b></p>
<p class="text-xs mb-4" id="globalInfo"></p>

<section id="panelUpload" class="bg-white rounded-2xl shadow p-5 mb-6">
  <div class="panel-grid">
    <div class="lg:col-span-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="field-label mb-2 block">Tahun</label>
          <div class="flex gap-2 items-center">
            <div class="nice-input">
              <input id="tahun" type="number" inputmode="numeric" min="2000" max="2100" placeholder="yyyy" autocomplete="off"/>
            </div>
            <button id="btnYearReset" type="button" class="btn-ghost btn-xs whitespace-nowrap">Reset Tahun</button>
            <span id="statusYear" class="text-xs text-slate-500 ml-2"></span>
          </div>
          <p class="hint mt-2">Pilih/ketik tahun. Tanpa tahun valid, upload dinonaktifkan.</p>
        </div>

        <div></div>

        <div class="md:col-span-2">
          <label class="field-label mb-2 block">Upload File Investasi (.xlsx)</label>
          <div class="filebox">
            <input id="fileAll" type="file" accept=".xlsx" multiple>
            <button id="pickAll" class="pick" type="button"><i class="fa-solid fa-file-arrow-up"></i> Pilih File</button>
            <input id="fileMore" type="file" accept=".xlsx" multiple style="display:none">
            <button id="addFile" class="icon-btn" type="button" title="Tambah"><i class="fa-solid fa-plus"></i></button>
            <button id="clearFiles" class="icon-btn" type="button" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            <span id="nameAll" class="name">Belum ada file dipilih (bisa multi)</span>
          </div>
          <p class="hint mt-2">
            <b>Nama yang diterima:</b><br>
            investasi_detail.xlsx • investasi_sektor_total.xlsx • investasi_rekap_sumber.xlsx •
            investasi_rekap_bidang.xlsx • investasi_rekap_kota.xlsx • investasi_rekap_pma_negara.xlsx
          </p>
          <div class="mt-4 flex items-center gap-2">
            <button id="btnUpload" class="btn-primary btn-xs" type="button"><i class="fa-solid fa-upload mr-1"></i> Upload &amp; Tampilkan</button>
            <button id="btnReset" class="btn-ghost btn-xs" type="button">Reset</button>
            <span id="statusSave" class="text-sm text-slate-500"></span>
          </div>
          <div id="notes" class="mt-2 note-upload"></div>
        </div>
      </div>
    </div>

    <aside class="card">
      <div class="card-h">Template Excel</div>
      <div class="card-b">
        <div class="flex flex-col gap-1">
          <button class="btn-ghost btn-xs w-full" data-tpl="investasi_detail" type="button">investasi_detail.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="investasi_sektor_total" type="button">investasi_sektor_total.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="investasi_rekap_sumber" type="button">investasi_rekap_sumber.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="investasi_rekap_bidang" type="button">investasi_rekap_bidang.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="investasi_rekap_kota" type="button">investasi_rekap_kota.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="investasi_rekap_pma_negara" type="button">investasi_rekap_pma_negara.xlsx</button>
          <button id="btnDownloadTemplates" class="btn-ghost btn-xs w-full" type="button"><i class="fa-solid fa-download mr-1"></i> Download Semua (6)</button>
        </div>
      </div>
    </aside>
  </div>
</section>

<!-- SEKTOR TOTAL -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Nilai Investasi per Sektor (Total)</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunSektor">-</span></p>
      <p class="subnote">Semua angka pada tabel ini berbasis <b>Rp Juta</b> dan ditampilkan apa adanya (tanpa ringkas M/T).</p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllSektor" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideSektor" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr><th>Sektor</th><th>Nilai (Rp Juta)</th></tr>
      </thead>
      <tbody id="tbodySektor"><tr><td colspan="2">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootSektor"></tfoot>
    </table>
  </div>
</section>

<!-- DETAIL -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Realisasi Investasi (Detail)</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunDetail">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllDetail" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideDetail" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center" id="theadDetail">
        <tr>
          <th>TAHUN</th><th>NAMA PERUSAHAAN</th><th>ALAMAT PERUSAHAAN</th>
          <th>KBLI</th><th>BIDANG USAHA</th><th>PROVINSI</th><th>KAB/KOTA</th>
          <th>NEGARA</th><th>STATUS</th><th>TRIWULAN</th>
          <th>NILAI INVESTASI Rp JUTA</th><th>NILAI INVESTASI US$ RIBU</th>
        </tr>
      </thead>
      <tbody id="tbodyDetail"><tr><td colspan="12">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootDetail"></tfoot>
    </table>
  </div>
</section>

<!-- REKAP -->
<div class="rekap-stack">
  <section class="bg-white rounded-2xl shadow">
    <div class="px-5 py-3 border-b sec-head">
      <div>
        <h3 class="font-bold">Rekap Triwulan menurut Sumber (PMA/PMDN)</h3>
        <p class="text-xs text-slate-500">Tahun <span id="tahunSumber">-</span></p>
        <p class="subnote">Satuan <b>Rp Juta</b>, angka ditampilkan apa adanya.</p>
      </div>
      <div class="small-actions">
        <button id="btnShowAllSumber" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
        <button id="btnHideSumber" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
      </div>
    </div>
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="text-center thead-tw">
          <tr><th>Sumber</th><th>1</th><th>2</th><th>3</th><th>4</th></tr>
        </thead>
        <tbody id="tbodySumber"><tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr></tbody>
        <tfoot id="tfootSumber"></tfoot>
      </table>
    </div>
  </section>

  <section class="bg-white rounded-2xl shadow">
    <div class="px-5 py-3 border-b sec-head">
      <div>
        <h3 class="font-bold">Rekap Triwulan menurut Bidang Usaha</h3>
        <p class="text-xs text-slate-500">Tahun <span id="tahunBidang">-</span></p>
        <p class="subnote">Satuan <b>Rp Juta</b>, angka ditampilkan apa adanya.</p>
      </div>
      <div class="small-actions">
        <button id="btnShowAllBidang" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
        <button id="btnHideBidang" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
      </div>
    </div>
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="text-center thead-tw">
          <tr><th>Bidang Usaha</th><th>1</th><th>2</th><th>3</th><th>4</th></tr>
        </thead>
        <tbody id="tbodyBidang"><tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr></tbody>
        <tfoot id="tfootBidang"></tfoot>
      </table>
    </div>
  </section>

  <section class="bg-white rounded-2xl shadow">
    <div class="px-5 py-3 border-b sec-head">
      <div>
        <h3 class="font-bold">Rekap Triwulan menurut Kab/Kota</h3>
        <p class="text-xs text-slate-500">Tahun <span id="tahunKota">-</span></p>
        <p class="subnote">Satuan <b>Rp Juta</b>, angka ditampilkan apa adanya.</p>
      </div>
      <div class="small-actions">
        <button id="btnShowAllKota" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
        <button id="btnHideKota" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
      </div>
    </div>
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="text-center thead-tw">
          <tr><th>Kab/Kota</th><th>1</th><th>2</th><th>3</th><th>4</th></tr>
        </thead>
        <tbody id="tbodyKota"><tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr></tbody>
        <tfoot id="tfootKota"></tfoot>
      </table>
    </div>
  </section>

  <section class="bg-white rounded-2xl shadow">
    <div class="px-5 py-3 border-b sec-head">
      <div>
        <h3 class="font-bold">Rekap Triwulan Negara (PMA)</h3>
        <p class="text-xs text-slate-500">Tahun <span id="tahunPMA">-</span></p>
        <p class="subnote">Satuan <b>Rp Juta</b>, angka ditampilkan apa adanya.</p>
      </div>
      <div class="small-actions">
        <button id="btnShowAllPMA" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
        <button id="btnHidePMA" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
      </div>
    </div>
    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead class="text-center thead-tw">
          <tr><th>Negara (PMA)</th><th>1</th><th>2</th><th>3</th><th>4</th></tr>
        </thead>
        <tbody id="tbodyPMA"><tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr></tbody>
        <tfoot id="tfootPMA"></tfoot>
      </table>
    </div>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
/* ====== State & helpers ====== */
const YEAR_MIN=2000, YEAR_MAX=2100;
let YEAR_LOAD_SEQ = 0;
const MAX_INITIAL_ROWS = 10;

let DETAIL_CACHE=[], DETAIL_SHOW_ALL=false;
let SEKTOR_CACHE=[], SEKTOR_SHOW_ALL=false;
let SUMBER_CACHE=[], SUMBER_SHOW_ALL=false;
let BIDANG_CACHE=[], BIDANG_SHOW_ALL=false;
let KOTA_CACHE=[],   KOTA_SHOW_ALL=false;
let PMA_CACHE=[],    PMA_SHOW_ALL=false;

const getYear = ()=> (document.getElementById('tahun')?.value || '').trim();
const isValidYear = y => /^\d{4}$/.test(String(y)) && +y>=YEAR_MIN && +y<=YEAR_MAX;

const notes = document.getElementById('notes');
function note(msg, ok=false){ notes.innerHTML = `<div class="${ok?'note-ok':'note-err'}">${msg}</div>`; }

let __yearTimer,__saveTimer;
function flashYear(msg,color="#0ea5e9",ms=3500){const el=document.getElementById('statusYear');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__yearTimer);__yearTimer=setTimeout(()=>{el.textContent='';},ms);}
function flashSave(msg,color="#059669",ms=4000){const el=document.getElementById('statusSave');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__saveTimer);__saveTimer=setTimeout(()=>{el.textContent='';},ms);}
function setStatusProgress(text,color='#64748b'){const el=document.getElementById('statusSave');if(!el)return;el.textContent=text;el.style.color=color;}

function setGlobal(ok, tahun){
  const el=document.getElementById('globalInfo');
  if(!el) return;
  if(!tahun || !/^\d{4}$/.test(tahun)){ el.innerHTML=''; return; }
  el.innerHTML = ok
    ? `<span class="badge-ok">Data tersedia untuk tahun ${tahun} ✓</span>`
    : `<span class="note-err">NOTED: Belum ada data untuk tahun ${tahun}.</span>`;
}

/* ====== Number helpers ====== */
function parseNumID(v){
  if(v===null||v===undefined) return null;
  if(typeof v==='number' && Number.isFinite(v)) return v;
  let s=String(v).trim(); if(s==='') return null;
  s=s.replace(/\s+/g,'');
  const hasComma=s.includes(','), hasDot=s.includes('.');
  if(hasComma && hasDot){
    const lastComma=s.lastIndexOf(','), lastDot=s.lastIndexOf('.');
    const decSep = (lastComma>lastDot) ? ',' : '.';
    const thouSep= decSep===',' ? '.' : ',';
    s = s.split(thouSep).join('');
    if(decSep===',') s = s.replace(',', '.');
  } else if(hasComma){
    const parts=s.split(',');
    if(parts[1] && parts[1].length<=2){ s = parts[0].split('.').join('') + '.' + parts[1]; }
    else { s = s.replace(/,/g,''); }
  } else if(hasDot){
    const parts=s.split('.');
    if(!(parts[1] && parts[1].length<=2)){ s = s.replace(/\./g,''); }
  }
  s = s.replace(/[^0-9.\-]/g,'');
  if(s==='' || s==='-' || s==='-.') return null;
  const n=parseFloat(s);
  return Number.isFinite(n) ? n : null;
}
const nf  = new Intl.NumberFormat('id-ID',{maximumFractionDigits:2,minimumFractionDigits:0});
const fmt  = v => (v===null||v===undefined||String(v).trim()==='') ? ''  : nf.format(+v||0);
const fmt0 = v => (v===null||v===undefined||String(v).trim()==='') ? '0' : nf.format(+v||0);

// Tampilan apa adanya untuk satuan "Rp Juta"
const formatRpJutaExact = n => nf.format(+n||0) + ' jt';

/* ====== File bag ====== */
const fAll=document.getElementById('fileAll'),fMore=document.getElementById('fileMore');
const pick=document.getElementById('pickAll'),addBtn=document.getElementById('addFile'),clrBtn=document.getElementById('clearFiles'),nameEl=document.getElementById('nameAll');
let bag=new DataTransfer();
function addFiles(list){for(const f of list) bag.items.add(f); fAll.files=bag.files; nameEl.textContent=[...bag.files].map(f=>f.name).join(', ')||'Belum ada file dipilih (bisa multi)';}
function clearFiles(){bag=new DataTransfer();fAll.value='';fAll.files=bag.files;nameEl.textContent='Belum ada file dipilih (bisa multi)';notes.innerHTML='';}
pick.onclick=()=>fAll.click();
fAll.onchange=()=>{if(fAll.files.length) addFiles(fAll.files);};
addBtn.onclick=()=>fMore.click();
fMore.onchange=()=>{if(fMore.files.length) addFiles(fMore.files); fMore.value='';};
clrBtn.onclick=clearFiles;

/* ====== Tahun state ====== */
function hardResetView(){
  ['tbodySektor','tfootSektor','tbodyDetail','tfootDetail','tbodySumber','tfootSumber','tbodyBidang','tfootBidang','tbodyKota','tfootKota','tbodyPMA','tfootPMA']
    .forEach(id=>{const el=document.getElementById(id); if(el) el.innerHTML='';});
  document.getElementById('tbodySektor').innerHTML='<tr><td colspan="2">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tbodyDetail').innerHTML='<tr><td colspan="12">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tbodySumber').innerHTML='<tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tbodyBidang').innerHTML ='<tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tbodyKota').innerHTML   ='<tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tbodyPMA').innerHTML    ='<tr><td colspan="5">Pilih tahun untuk melihat data.</td></tr>';

  DETAIL_CACHE=[]; DETAIL_SHOW_ALL=false;
  SEKTOR_CACHE=[]; SEKTOR_SHOW_ALL=false;
  SUMBER_CACHE=[]; SUMBER_SHOW_ALL=false;
  BIDANG_CACHE=[]; BIDANG_SHOW_ALL=false;
  KOTA_CACHE=[];   KOTA_SHOW_ALL=false;
  PMA_CACHE=[];    PMA_SHOW_ALL=false;

  ['Sumber','Bidang','Kota','PMA','Sektor','Detail'].forEach(x=>{
    const b1=document.getElementById('btnShowAll'+x), b2=document.getElementById('btnHide'+x);
    if(b1) b1.style.display='none'; if(b2) b2.style.display='none';
  });
  const st=document.getElementById('statusSave'); if(st) st.textContent='';
}
function setYearState(){
  const t=getYear(), ok=isValidYear(t);
  ['tahunDetail','tahunSektor','tahunSumber','tahunBidang','tahunKota','tahunPMA'].forEach(id=>{const el=document.getElementById(id);if(el)el.textContent=ok?t:'-';});
  pick.disabled=!ok; addBtn.disabled=!ok; clrBtn.disabled=false;
  const btnUpload=document.getElementById('btnUpload'); if(btnUpload) btnUpload.disabled=!ok;
  if(!ok) { hardResetView(); setGlobal(false,''); }
}
document.addEventListener('DOMContentLoaded',()=>{const y=document.getElementById('tahun'); if(y) y.value=''; setYearState();});

/* ====== XLSX utils & templates ====== */
function sheetToRows(ws){
  const aoa=XLSX.utils.sheet_to_json(ws,{header:1,raw:true,defval:''});
  if(!aoa.length) return {cols:[],data:[]};
  const cols=(aoa[0]||[]).map(h=>String(h||'').trim());
  const data=aoa.slice(1).map(r=>{const o={};cols.forEach((h,i)=>o[h]=r?.[i]??'');return o;});
  return {cols,data};
}
function wbDownload(name,sheets){const wb=XLSX.utils.book_new();sheets.forEach(s=>{const ws=XLSX.utils.aoa_to_sheet(s.aoa);if(s.widths){ws['!cols']=s.widths.map(w=>({wch:w}));} XLSX.utils.book_append_sheet(wb,ws,s.name);}); XLSX.writeFile(wb,name);}
function t_investasi_detail(){return{name:'DATA',widths:[8,28,36,12,24,16,18,16,12,10,22,22],aoa:[['TAHUN','NAMA PERUSAHAAN','ALAMAT PERUSAHAAN','KBLI','BIDANG USAHA','PROVINSI','KAB/KOTA','NEGARA','STATUS','TRIWULAN','NILAI INVESTASI Rp JUTA','NILAI INVESTASI US$ RIBU']]} }
function t_sektor(){return{name:'DATA',widths:[28,18],aoa:[['sektor','nilai_rp_juta']]} }
function t_tri(title,label){return{name:title,widths:[28,14,14,14,14],aoa:[[label,'q1','q2','q3','q4']]} }
function downloadTpl(key){
  const spec={
    'investasi_detail':{fname:'investasi_detail.xlsx',sheets:[t_investasi_detail()]},
    'investasi_sektor_total':{fname:'investasi_sektor_total.xlsx',sheets:[t_sektor()]},
    'investasi_rekap_sumber':{fname:'investasi_rekap_sumber.xlsx',sheets:[t_tri('DATA','sumber')]},
    'investasi_rekap_bidang':{fname:'investasi_rekap_bidang.xlsx',sheets:[t_tri('DATA','bidang_usaha')]},
    'investasi_rekap_kota':{fname:'investasi_rekap_kota.xlsx',sheets:[t_tri('DATA','kab_kota')]},
    'investasi_rekap_pma_negara':{fname:'investasi_rekap_pma_negara.xlsx',sheets:[t_tri('DATA','negara')]},
  }[key];
  if(!spec) return; wbDownload(spec.fname,spec.sheets);
}
document.querySelectorAll('[data-tpl]').forEach(b=>b.onclick=()=>downloadTpl(b.dataset.tpl));
document.getElementById('btnDownloadTemplates').onclick=()=>['investasi_detail','investasi_sektor_total','investasi_rekap_sumber','investasi_rekap_bidang','investasi_rekap_kota','investasi_rekap_pma_negara'].forEach((k,i)=>setTimeout(()=>downloadTpl(k),i*150));

/* ====== VALIDATOR ====== */
const TPLS = {
  'investasi_detail.xlsx': { table:'investasi_detail', headers:['TAHUN','NAMA PERUSAHAAN','ALAMAT PERUSAHAAN','KBLI','BIDANG USAHA','PROVINSI','KAB/KOTA','NEGARA','STATUS','TRIWULAN','NILAI INVESTASI Rp JUTA','NILAI INVESTASI US$ RIBU'] },
  'investasi_sektor_total.xlsx': { table:'investasi_sektor_total', headers:['sektor','nilai_rp_juta'] },
  'investasi_rekap_sumber.xlsx': { table:'investasi_rekap_sumber', headers:['sumber','q1','q2','q3','q4'] },
  'investasi_rekap_bidang.xlsx': { table:'investasi_rekap_bidang', headers:['bidang_usaha','q1','q2','q3','q4'] },
  'investasi_rekap_kota.xlsx': { table:'investasi_rekap_kota', headers:['kab_kota','q1','q2','q3','q4'] },
  'investasi_rekap_pma_negara.xlsx': { table:'investasi_rekap_pma_negara', headers:['negara','q1','q2','q3','q4'] },
};
const hnorm = (h)=>String(h||'').trim().replace(/\s+/g,' ').toLowerCase();
function validateByFilename(fileName, cols){
  const want = TPLS[fileName];
  if(!want) return {ok:false, msg:`File <b>${fileName}</b> tidak diizinkan. Hanya: ${Object.keys(TPLS).join(', ')}`};
  const eq = (a,b)=>a.length===b.length && a.every((v,i)=>hnorm(v)===hnorm(b[i]));
  if(!eq(cols, want.headers)) {
    return {ok:false, msg:`Header <b>${fileName}</b> tidak sesuai.<br>Diharapkan: <code>${want.headers.join(' | ')}</code><br>Ditemukan: <code>${cols.join(' | ')}</code>`};
  }
  return {ok:true, table: want.table};
}
function assertRowsYearDetail(rows, expectYear){
  const years = new Set();
  rows.forEach(r=>{ const val = String(r['TAHUN']??'').replace(/[^\d]/g,''); if(val) years.add(val); });
  if(years.size===0){ return {ok:false, msg:`Kolom <b>TAHUN</b> kosong/tidak berisi tahun yang valid.`}; }
  if(years.size>1 || !years.has(expectYear)){ return {ok:false, msg:`Tahun pada sheet (${[...years].join(', ')||'-'}) tidak sama dengan input (${expectYear}).`}; }
  return {ok:true};
}

/* ====== SAVE & WIPE ====== */
async function save(table, rows){
  if(!rows.length) return {ok:true,saved:0};
  const res = await fetch('api/save_rows.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({table,rows})});
  const out = await res.json();
  if(!out.ok) throw new Error(out.error||'Gagal simpan'); return out;
}
async function wipeYear(tahun){
  try{
    await fetch('api/wipe_year.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      tahun:+tahun,
      tables:['investasi_detail','investasi_sektor_total','investasi_rekap_sumber','investasi_rekap_bidang','investasi_rekap_kota','investasi_rekap_pma_negara']
    })});
  }catch(_){}
}

/* ====== Upload & parse ====== */
document.getElementById('btnUpload').onclick=async()=>{
  const tahun=getYear();
  if(!isValidYear(tahun)){ flashSave('Tahun wajib 4 digit (2000–2100).','#dc2626'); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return; }
  ['tahunDetail','tahunSektor','tahunSumber','tahunBidang','tahunKota','tahunPMA'].forEach(id=>document.getElementById(id).textContent=tahun);
  if((bag.files||[]).length===0){ flashSave('Pilih minimal satu file.','#dc2626'); return; }

  hardResetView(); setStatusProgress('Memproses file...'); notes.innerHTML='';

  const bucket = { investasi_detail:[], investasi_sektor_total:[], investasi_rekap_sumber:[], investasi_rekap_bidang:[], investasi_rekap_kota:[], investasi_rekap_pma_negara:[] };

  for(const f of bag.files){
    const fn=f.name.trim();
    if(!TPLS[fn]){ note(`NOTED: <b>${fn}</b> ditolak (nama tidak termasuk daftar template).`); continue; }
    try{
      const wb = await new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>{try{res(XLSX.read(e.target.result,{type:'array'}));}catch(err){rej(err)}};r.onerror=rej;r.readAsArrayBuffer(f);});
      wb.SheetNames.forEach(n=>{
        const ws=wb.Sheets[n]; if(!ws||!ws['!ref']) return;
        const {cols,data}=sheetToRows(ws);
        const v=validateByFilename(fn, cols);
        if(!v.ok){ note('NOTED: '+v.msg); return; }

        if(v.table==='investasi_detail'){
          const cek=assertRowsYearDetail(data, tahun); if(!cek.ok){ note('NOTED: '+cek.msg); return; }
          data.forEach(r=>{
            const tri = String(r['TRIWULAN']??'').replace(/[^\d]/g,'');
            bucket.investasi_detail.push({
              tahun:+tahun,
              nama_perusahaan:String(r['NAMA PERUSAHAAN']||''),
              alamat_perusahaan:String(r['ALAMAT PERUSAHAAN']||''),
              kbli:String(r['KBLI']||''),
              bidang_usaha:String(r['BIDANG USAHA']||''),
              provinsi:String(r['PROVINSI']||''),
              kab_kota:String(r['KAB/KOTA']||''),
              negara:String(r['NEGARA']||''),
              status_perusahaan:String(r['STATUS']||''),
              triwulan: tri?parseInt(tri,10):null,
              nilai_investasi_rp_juta: parseNumID(r['NILAI INVESTASI Rp JUTA']),
              nilai_investasi_usd_ribu: parseNumID(r['NILAI INVESTASI US$ RIBU'])
            });
          });
        } else if(v.table==='investasi_sektor_total'){
          data.forEach(r=>{
            const sektor=String(r['sektor']||'').trim();
            const nilai=parseNumID(r['nilai_rp_juta']);
            if(sektor==='' && nilai===null) return;
            bucket.investasi_sektor_total.push({ tahun:+tahun, sektor, nilai_rp_juta:nilai });
          });
        } else {
          const labelKey = v.table==='investasi_rekap_sumber' ? 'sumber'
                         : v.table==='investasi_rekap_bidang' ? 'bidang_usaha'
                         : v.table==='investasi_rekap_kota' ? 'kab_kota' : 'negara';
          data.forEach(r=>{
            const lbl=String(r[labelKey]||'').trim();
            const q1=parseNumID(r['q1']), q2=parseNumID(r['q2']), q3=parseNumID(r['q3']), q4=parseNumID(r['q4']);
            const sum=(q1||0)+(q2||0)+(q3||0)+(q4||0);
            if(/^grand\s*total$/i.test(lbl)) return;
            if(lbl==='' && sum===0) return;
            bucket[v.table].push({ tahun:+tahun, [labelKey]:lbl, q1, q2, q3, q4 });
          });
        }
      });
    }catch(e){ note(`NOTED: Gagal baca ${fn}: ${e.message||e}`); }
  }

  setStatusProgress('Menyelaraskan data tahun…'); await wipeYear(tahun);
  setStatusProgress('Menyimpan ke server…');
  try{
    await save('investasi_sektor_total', bucket.investasi_sektor_total);
    await save('investasi_detail', bucket.investasi_detail);
    await save('investasi_rekap_sumber', bucket.investasi_rekap_sumber);
    await save('investasi_rekap_bidang', bucket.investasi_rekap_bidang);
    await save('investasi_rekap_kota', bucket.investasi_rekap_kota);
    await save('investasi_rekap_pma_negara', bucket.investasi_rekap_pma_negara);

    flashSave(`Data tahun ${tahun} disimpan ✓`,'#059669');
    await loadFromDB(tahun, ++YEAR_LOAD_SEQ);
    note('Upload & simpan selesai ✓', true);
  }catch(e){
    flashSave('Gagal menyimpan data','#dc2626'); note('NOTED: '+(e.message||e), false);
  }finally{ setStatusProgress(''); }
};

/* ====== VIEWER ====== */
function normalizeDetailRows(apiRows){
  return (apiRows||[]).map(r=>({
    'TAHUN': r.tahun ?? r['TAHUN'] ?? '',
    'NAMA PERUSAHAAN': r.nama_perusahaan ?? r['NAMA PERUSAHAAN'] ?? '',
    'ALAMAT PERUSAHAAN': r.alamat_perusahaan ?? r['ALAMAT PERUSAHAAN'] ?? '',
    'KBLI': r.kbli ?? r['KBLI'] ?? '',
    'BIDANG USAHA': r.bidang_usaha ?? r['BIDANG USAHA'] ?? '',
    'PROVINSI': r.provinsi ?? r['PROVINSI'] ?? '',
    'KAB/KOTA': r.kab_kota ?? r['KAB/KOTA'] ?? '',
    'NEGARA': r.negara ?? r['NEGARA'] ?? '',
    'STATUS': r.status_perusahaan ?? r['STATUS'] ?? '',
    'TRIWULAN': (r.triwulan ?? r['TRIWULAN'] ?? ''),
    'NILAI INVESTASI Rp JUTA': (r.nilai_investasi_rp_juta ?? r['NILAI INVESTASI Rp JUTA'] ?? null),
    'NILAI INVESTASI US$ RIBU': (r.nilai_investasi_usd_ribu ?? r['NILAI INVESTASI US$ RIBU'] ?? null),
  }));
}
function withLimit(list, showAll){ return showAll ? list : list.slice(0, MAX_INITIAL_ROWS); }

function renderDetail(rows){
  const tbody=document.getElementById('tbodyDetail'), tfoot=document.getElementById('tfootDetail');
  DETAIL_CACHE = rows.slice();
  const showRows = withLimit(DETAIL_CACHE, DETAIL_SHOW_ALL);

  if(!DETAIL_CACHE.length){
    tbody.innerHTML='<tr><td colspan="12">Belum ada data.</td></tr>';
    tfoot.innerHTML='';
    document.getElementById('btnShowAllDetail').style.display='none';
    document.getElementById('btnHideDetail').style.display='none';
    return;
  }
  tbody.innerHTML = showRows.map(r=>{
    const cols=['TAHUN','NAMA PERUSAHAAN','ALAMAT PERUSAHAAN','KBLI','BIDANG USAHA','PROVINSI','KAB/KOTA','NEGARA','STATUS','TRIWULAN','NILAI INVESTASI Rp JUTA','NILAI INVESTASI US$ RIBU'];
    return `<tr>${cols.map(c=>{
      let v=r[c];
      let isNum=/TRIWULAN|NILAI/.test(c);
      if (c==='NILAI INVESTASI Rp JUTA') {
        return `<td class="px-3 py-2 text-right">${v==null||v===''?'':formatRpJutaExact(v)}</td>`;
      }
      if (c==='NILAI INVESTASI US$ RIBU') {
        return `<td class="px-3 py-2 text-right">${v==null||v===''?'':(fmt(v)+' ribu')}</td>`;
      }
      return `<td class="px-3 py-2 ${isNum?'text-right':''}">${isNum?fmt(v):String(v??'')}</td>`;
    }).join('')}</tr>`;
  }).join('');
  tfoot.innerHTML='';

  const showBtn=document.getElementById('btnShowAllDetail'), hideBtn=document.getElementById('btnHideDetail');
  if(DETAIL_CACHE.length>MAX_INITIAL_ROWS){
    showBtn.style.display = DETAIL_SHOW_ALL ? 'none' : 'inline-flex';
    hideBtn.style.display = DETAIL_SHOW_ALL ? 'inline-flex' : 'none';
  } else { showBtn.style.display='none'; hideBtn.style.display='none'; }
}
document.getElementById('btnShowAllDetail').onclick=()=>{ DETAIL_SHOW_ALL=true; renderDetail(DETAIL_CACHE); };
document.getElementById('btnHideDetail').onclick=()=>{ DETAIL_SHOW_ALL=false; renderDetail(DETAIL_CACHE); };

function renderSektor(list){
  const tbody=document.getElementById('tbodySektor'), tfoot=document.getElementById('tfootSektor');
  SEKTOR_CACHE = list.slice();
  if(!SEKTOR_CACHE.length){
    tbody.innerHTML='<tr><td colspan="2">Belum ada data.</td></tr>'; tfoot.innerHTML='';
    document.getElementById('btnShowAllSektor').style.display='none';
    document.getElementById('btnHideSektor').style.display='none';
    return;
  }
  const tot = SEKTOR_CACHE.reduce((a,r)=>a+(r.nilai||0),0);
  const rows = withLimit(SEKTOR_CACHE, SEKTOR_SHOW_ALL);
  tbody.innerHTML = rows.map(r=>`<tr>
      <td class="px-3 py-2">${r.sektor}</td>
      <td class="px-3 py-2 text-right">${formatRpJutaExact(r.nilai)}</td>
    </tr>`).join('');
  tfoot.innerHTML = `<tr class="row-total"><td class="text-center">Grand Total</td><td class="text-right">${formatRpJutaExact(tot)}</td></tr>`;

  const showBtn=document.getElementById('btnShowAllSektor'), hideBtn=document.getElementById('btnHideSektor');
  if(SEKTOR_CACHE.length>MAX_INITIAL_ROWS){
    showBtn.style.display = SEKTOR_SHOW_ALL ? 'none' : 'inline-flex';
    hideBtn.style.display = SEKTOR_SHOW_ALL ? 'inline-flex' : 'none';
  } else { showBtn.style.display='none'; hideBtn.style.display='none'; }
}
document.getElementById('btnShowAllSektor').onclick=()=>{ SEKTOR_SHOW_ALL=true; renderSektor(SEKTOR_CACHE); };
document.getElementById('btnHideSektor').onclick=()=>{ SEKTOR_SHOW_ALL=false; renderSektor(SEKTOR_CACHE); };

// Rekap triwulan (apa adanya, Rp Juta)
function renderTriGeneric(list, ids, which){
  if(which==='Sumber'){ SUMBER_CACHE=list.slice(); }
  if(which==='Bidang'){ BIDANG_CACHE=list.slice(); }
  if(which==='Kota'){   KOTA_CACHE=list.slice(); }
  if(which==='PMA'){    PMA_CACHE=list.slice(); }

  const cache = which==='Sumber'?SUMBER_CACHE:which==='Bidang'?BIDANG_CACHE:which==='Kota'?KOTA_CACHE:PMA_CACHE;
  const showAllFlag = which==='Sumber'?SUMBER_SHOW_ALL:which==='Bidang'?BIDANG_SHOW_ALL:which==='Kota'?KOTA_SHOW_ALL:PMA_SHOW_ALL;

  const tbody=document.getElementById(ids.tbody), tfoot=document.getElementById(ids.tfoot);
  if(!cache.length){
    tbody.innerHTML='<tr><td colspan="5">Belum ada data.</td></tr>'; tfoot.innerHTML='';
    document.getElementById('btnShowAll'+which).style.display='none';
    document.getElementById('btnHide'+which).style.display='none';
    return;
  }
  const sum=cache.reduce((a,r)=>({q1:a.q1+(r.q1||0),q2:a.q2+(r.q2||0),q3:a.q3+(r.q3||0),q4:a.q4+(r.q4||0)}),{q1:0,q2:0,q3:0,q4:0});
  const rows = withLimit(cache, showAllFlag);
  tbody.innerHTML = rows.map(r=>`<tr>
    <td class="px-3 py-2">${r.label}</td>
    <td class="px-3 py-2 text-right">${formatRpJutaExact(r.q1)}</td>
    <td class="px-3 py-2 text-right">${formatRpJutaExact(r.q2)}</td>
    <td class="px-3 py-2 text-right">${formatRpJutaExact(r.q3)}</td>
    <td class="px-3 py-2 text-right">${formatRpJutaExact(r.q4)}</td>
  </tr>`).join('');
  tfoot.innerHTML = `<tr class="row-total"><td class="text-center">Grand Total</td>
      <td class="text-right">${formatRpJutaExact(sum.q1)}</td>
      <td class="text-right">${formatRpJutaExact(sum.q2)}</td>
      <td class="text-right">${formatRpJutaExact(sum.q3)}</td>
      <td class="text-right">${formatRpJutaExact(sum.q4)}</td></tr>`;

  const showBtn=document.getElementById('btnShowAll'+which), hideBtn=document.getElementById('btnHide'+which);
  if(cache.length>MAX_INITIAL_ROWS){
    showBtn.style.display = showAllFlag ? 'none' : 'inline-flex';
    hideBtn.style.display = showAllFlag ? 'inline-flex' : 'none';
  } else { showBtn.style.display='none'; hideBtn.style.display='none'; }
}
document.getElementById('btnShowAllSumber').onclick=()=>{ SUMBER_SHOW_ALL=true; renderTriGeneric(SUMBER_CACHE,{tbody:'tbodySumber',tfoot:'tfootSumber'},'Sumber'); };
document.getElementById('btnHideSumber').onclick=()=>{ SUMBER_SHOW_ALL=false; renderTriGeneric(SUMBER_CACHE,{tbody:'tbodySumber',tfoot:'tfootSumber'},'Sumber'); };
document.getElementById('btnShowAllBidang').onclick=()=>{ BIDANG_SHOW_ALL=true; renderTriGeneric(BIDANG_CACHE,{tbody:'tbodyBidang',tfoot:'tfootBidang'},'Bidang'); };
document.getElementById('btnHideBidang').onclick=()=>{ BIDANG_SHOW_ALL=false; renderTriGeneric(BIDANG_CACHE,{tbody:'tbodyBidang',tfoot:'tfootBidang'},'Bidang'); };
document.getElementById('btnShowAllKota').onclick=()=>{ KOTA_SHOW_ALL=true; renderTriGeneric(KOTA_CACHE,{tbody:'tbodyKota',tfoot:'tfootKota'},'Kota'); };
document.getElementById('btnHideKota').onclick=()=>{ KOTA_SHOW_ALL=false; renderTriGeneric(KOTA_CACHE,{tbody:'tbodyKota',tfoot:'tfootKota'},'Kota'); };
document.getElementById('btnShowAllPMA').onclick=()=>{ PMA_SHOW_ALL=true; renderTriGeneric(PMA_CACHE,{tbody:'tbodyPMA',tfoot:'tfootPMA'},'PMA'); };
document.getElementById('btnHidePMA').onclick=()=>{ PMA_SHOW_ALL=false; renderTriGeneric(PMA_CACHE,{tbody:'tbodyPMA',tfoot:'tfootPMA'},'PMA'); };

async function loadFromDB(year, seq){
  if (typeof seq !== 'number') seq = ++YEAR_LOAD_SEQ;
  const mySeq = seq;
  try{
    if (mySeq !== YEAR_LOAD_SEQ) return;

    ['tahunDetail','tahunSektor','tahunSumber','tahunBidang','tahunKota','tahunPMA']
      .forEach(id=>{const el=document.getElementById(id); if(el) el.textContent=year; });

    const r=await fetch(`api/investasi_fetch.php?tahun=${encodeURIComponent(year)}&_=${Date.now()}`);
    const p=await r.json();
    if (mySeq !== YEAR_LOAD_SEQ) return;

    if(!p.ok){ setGlobal(false, year); hardResetView(); flashYear(`Gagal ambil data tahun ${year}.`,'#dc2626'); return; }

    const sektorList = (p.sektor_total||[])
      .map(row=>({ sektor:String(row.sektor||'').trim(), nilai:parseNumID(row.nilai_rp_juta) }))
      .filter(r => !(r.sektor==='' && r.nilai===null));
    renderSektor(sektorList);

    renderDetail(normalizeDetailRows(p.detail || p.detail_rows || []));

    const pickLabel = r => (r.label ?? r.sumber ?? r.bidang_usaha ?? r.kab_kota ?? r.negara ?? '');
    const mkTri = arr => (arr||[])
      .map(r=>({ label:String(pickLabel(r)??'').trim(), q1:parseNumID(r.q1), q2:parseNumID(r.q2), q3:parseNumID(r.q3), q4:parseNumID(r.q4) }))
      .filter(r=>!( /^grand\s*total$/i.test(r.label) || (r.label==='' && (r.q1||0)+(r.q2||0)+(r.q3||0)+(r.q4||0)===0) ));

    renderTriGeneric(mkTri(p.sumber), {tbody:'tbodySumber',tfoot:'tfootSumber'}, 'Sumber');
    renderTriGeneric(mkTri(p.bidang), {tbody:'tbodyBidang',tfoot:'tfootBidang'}, 'Bidang');
    renderTriGeneric(mkTri(p.kota),   {tbody:'tbodyKota',  tfoot:'tfootKota' }, 'Kota');
    renderTriGeneric(mkTri(p.pma),    {tbody:'tbodyPMA',   tfoot:'tfootPMA'  }, 'PMA');

    const hasAny = (sektorList.length>0) || (DETAIL_CACHE.length>0) ||
                   (SUMBER_CACHE.length>0) || (BIDANG_CACHE.length>0) ||
                   (KOTA_CACHE.length>0)   || (PMA_CACHE.length>0);
    setGlobal(hasAny, year);
    flashYear(hasAny ? 'Data tersedia ✓' : 'Tidak ada data.', hasAny?'#059669':'#dc2626', 2500);
  }catch(e){
    if (mySeq !== YEAR_LOAD_SEQ) return;
    console.error(e); setGlobal(false, year); flashYear(`Error mengambil data tahun ${year}`,'#dc2626');
  }
}

/* ====== Reset & Tahun ====== */
document.getElementById('btnReset').onclick=()=>{ clearFiles(); hardResetView(); setYearState(); const st=document.getElementById('statusSave'); if(st) st.textContent=''; notes.innerHTML=''; setGlobal(false,''); };
document.getElementById('btnYearReset').onclick=()=>{ YEAR_LOAD_SEQ++; const y=document.getElementById('tahun'); if(y) y.value=''; try{ clearTimeout(window.__yearDebounce); }catch(_){}
  setYearState(); const sy=document.getElementById('statusYear'); if(sy){ sy.textContent=''; sy.removeAttribute('style'); } setGlobal(false,''); };

const yearInput=document.getElementById('tahun');
function triggerLoad(){
  const y=getYear();
  if(!isValidYear(y)){ setYearState(); setGlobal(false,''); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return; }
  setYearState(); const seq=++YEAR_LOAD_SEQ; hardResetView(); loadFromDB(y, seq);
}
yearInput.addEventListener('input',()=>{clearTimeout(window.__yearDebounce);window.__yearDebounce=setTimeout(triggerLoad,250);});
yearInput.addEventListener('change',triggerLoad);
yearInput.addEventListener('blur',triggerLoad);
yearInput.addEventListener('keydown',(e)=>{if(e.key==='Enter'){e.preventDefault();triggerLoad();}});

// <!-- ===== Dashboard iframe helper (ringkas) ===== -->
(function(){
  const qs = new URLSearchParams(location.search);
  if (qs.get('dashboard') !== '1') return;

  // 1) Sembunyikan teks "Upload Excel..." + panel upload
  function hideUploadStuff(){
    // paragraf judul upload
    document.querySelectorAll('p').forEach(p=>{
      const t=(p.textContent||'').trim();
      if(/^Upload Excel\s*\(/i.test(t)) p.style.display='none';
    });
    // panel upload
    const up=document.getElementById('panelUpload');
    if(up) up.style.display='none';
  }
  hideUploadStuff();
  document.addEventListener('DOMContentLoaded', hideUploadStuff);

  // 2) Set tahun jika ada & load data
  const y = qs.get('tahun') || '';
  if (/^\d{4}$/.test(y)) {
    try {
      const inp=document.getElementById('tahun');
      if (inp) inp.value=y;
      if (typeof setYearState==='function') setYearState();
      if (typeof loadFromDB==='function') loadFromDB(y);
    } catch(_) {}
  }

  // 3) Scroll-X khusus tabel yang melebar (>=8 kolom)
  function addXScroll(){
    document.querySelectorAll('table').forEach(t=>{
      const cols=t.querySelectorAll('thead th').length || t.rows[0]?.cells.length || 0;
      if (cols<8) return;
      const wrap=t.parentElement;
      if(!wrap) return;
      wrap.style.overflowX='auto';
      wrap.style.overflowY='visible';
    });
  }
  addXScroll();
  setTimeout(addXScroll, 400);

  // 4) Auto-resize tinggi iframe
  const postH=()=> {
    const h=Math.max(document.body.scrollHeight, document.documentElement.scrollHeight);
    try{ parent.postMessage({type:'resizeFrame', height:h}, location.origin); }catch(_){}
  };
  window.addEventListener('load', postH);
  new ResizeObserver(postH).observe(document.body);
  setTimeout(postH,200); setTimeout(postH,800);
})();
</script>

<?php end_protected_page(); ?>
