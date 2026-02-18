<?php
// pengolahan-pemasaran.php — Upload + Viewer
require_once __DIR__.'/auth_guard.php';  // 🔐 cek login & status admin
require_login();                         // wajib login dulu

// ⤵️ Izinkan tampil readonly di dashboard
$IS_DASH = isset($_GET['dashboard']) && $_GET['dashboard'] === '1';
if (!$IS_DASH) {
  require_admin(); // tetap wajib admin kalau buka langsung
}

require_once __DIR__.'/protected_template.php';
start_protected_page('Pengolahan & Pemasaran', 'pengolahan');
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
thead.bg-samudera th{background:#0b1b2b!important;color:#fff!important;border-color:#fff!important;font-weight:700!important;font-size:.875rem!important;letter-spacing:0!important;white-space:nowrap}
.row-total td{font-weight:700;background:#f8fafc}
.sec-head{display:flex;align-items:center;justify-content:space-between}
.small-actions{display:flex;gap:.4rem;align-items:center}
.note-err{color:#dc2626;font-size:.85rem}.note-ok{color:#059669;font-size:.85rem}.badge-ok{font-size:.75rem;margin-left:.25rem;color:#059669}
.text-right{text-align:right}.text-center{text-align:center}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

<h1 class="text-2xl font-bold mb-2">Pengolahan &amp; Pemasaran</h1>
<p class="text-slate-600 mb-1"><b>Upload Excel (.xlsx/.xls). Sistem mendeteksi sheet &amp; menampilkan tabel.</b></p>
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
          <p class="hint mt-2">Tanpa tahun valid, upload dinonaktifkan.</p>
        </div>

        <div></div>

        <div class="md:col-span-2">
          <label class="field-label mb-2 block">Upload File (.xlsx/.xls)</label>
          <div class="filebox">
            <input id="fileAll" type="file" accept=".xlsx,.xls" multiple>
            <button id="pickAll" class="pick" type="button"><i class="fa-solid fa-file-arrow-up"></i> Pilih File</button>
            <input id="fileMore" type="file" accept=".xlsx,.xls" multiple style="display:none">
            <button id="addFile" class="icon-btn" type="button" title="Tambah"><i class="fa-solid fa-plus"></i></button>
            <button id="clearFiles" class="icon-btn" type="button" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            <span id="nameAll" class="name">Belum ada file dipilih (bisa multi)</span>
          </div>
          <div class="mt-4 flex items-center gap-2">
            <button id="btnUpload" class="btn-primary btn-xs" type="button"><i class="fa-solid fa-upload mr-1"></i> Upload &amp; Tampilkan</button>
            <button id="btnReset" class="btn-ghost btn-xs" type="button">Reset</button>
            <span id="statusSave" class="text-sm text-slate-500"></span>
          </div>
          <div id="notes" class="mt-2"></div>
        </div>
      </div>
    </div>

    <aside class="card">
      <div class="card-h">Template Excel</div>
      <div class="card-b">
        <div class="flex flex-col gap-1">
          <button class="btn-ghost btn-xs w-full" data-tpl="pemasaran"  type="button">Pemasaran (jenis kegiatan).xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="olahkab"    type="button">Pengolahan per Kab/Kota.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="olahjenis"  type="button">Pengolahan menurut Jenis.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="aki"        type="button">AKI Kab/Kota.xlsx</button>
          <button id="btnDownloadTemplates" class="btn-ghost btn-xs w-full" type="button"><i class="fa-solid fa-download mr-1"></i> Download Semua (4)</button>
        </div>
      </div>
    </aside>
  </div>
</section>

<!-- TABEL 0: AKI KAB/KOTA -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Angka Konsumsi Ikan (AKI) per Kabupaten/ Kota</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunAKIHead">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllAKI" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideAKI" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr>
          <th style="width:56px">No</th>
          <th>Kabupaten/ Kota</th>
          <th>KIDRT (AKI A)</th>
          <th>KILRT (AKI B)</th>
          <th>KTT (AKI C)</th>
          <th id="akiColHead">AKI (Total)</th>
        </tr>
      </thead>
      <tbody id="tbodyAKI"><tr><td colspan="6">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootAKI"></tfoot>
    </table>
  </div>
</section>

<!-- TABEL 1: PEMASARAN -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Jumlah Unit Pemasaran berdasarkan Jenis Kegiatan Pemasaran</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunPemasaranHead">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllPemasaran" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHidePemasaran" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr>
          <th rowspan="2">Kabupaten/ Kota</th>
          <th colspan="2">Jenis Kegiatan</th>
          <th rowspan="2">Jumlah Unit</th>
        </tr>
        <tr>
          <th>Pengecer</th>
          <th>Pengumpul/ Pedagang Besar/ Distributor</th>
        </tr>
      </thead>
      <tbody id="tbodyPemasaran"><tr><td colspan="4">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootPemasaran"></tfoot>
    </table>
  </div>
</section>

<!-- TABEL 2: PENGOLAHAN PER KAB/KOTA -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Jumlah Unit Pengolahan Ikan Menurut Kabupaten/ Kota</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunOlahanKabHead">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllOlahanKab" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideOlahanKab" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr>
          <th rowspan="2">Kabupaten/ Kota</th>
          <th colspan="10">Jenis Kegiatan</th>
          <th rowspan="2">Jumlah Unit</th>
        </tr>
        <tr>
          <th>Fermentasi</th>
          <th>Pelumatan Daging Ikan</th>
          <th>Pembekuan</th>
          <th>Pemindangan</th>
          <th>Penanganan Produk Segar</th>
          <th>Pengalengan</th>
          <th>Pengasapan/ Pemanggangan</th>
          <th>Pereduksian/ Ekstraksi</th>
          <th>Penggaraman/ Pengeringan</th>
          <th>Pengolahan Lainnya</th>
        </tr>
      </thead>
      <tbody id="tbodyOlahanKab"><tr><td colspan="12">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootOlahanKab"></tfoot>
    </table>
  </div>
</section>

<!-- TABEL 3: PENGOLAHAN MENURUT JENIS -->
<section class="bg-white rounded-2xl shadow">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Jumlah Unit Pengolahan Ikan menurut Jenis Kegiatan Pengolahan</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunOlahanJenisHead">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllOlahanJenis" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideOlahanJenis" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr><th>Jenis Kegiatan Pengolahan</th><th>Jumlah UPI</th></tr>
      </thead>
      <tbody id="tbodyOlahanJenis"><tr><td colspan="2">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootOlahanJenis"></tfoot>
    </table>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
/* ====== State & helpers ====== */
const YEAR_MIN=2000, YEAR_MAX=2100;
const MAX_INITIAL_ROWS = 10;
let YEAR_LOAD_SEQ=0;

const nf=new Intl.NumberFormat('id-ID',{maximumFractionDigits:2,minimumFractionDigits:0});
const fmt=v=>{ if(v===null||v===undefined||String(v).trim()==='') return '';
  const n=Number(String(v).replace(/[^\d.-]/g,'')); return isFinite(n)?nf.format(n):v; };

function parseNumID(v){
  if(v===null||v===undefined) return null;
  if(typeof v==='number' && Number.isFinite(v)) return v;
  let s=String(v).trim(); if(s==='') return null;
  s=s.replace(/\s+/g,''); // buang spasi termasuk newline di header berbaris
  const hasComma=s.includes(','), hasDot=s.includes('.');
  if(hasComma && hasDot){
    const lastComma=s.lastIndexOf(','), lastDot=s.lastIndexOf('.');
    const decSep=(lastComma>lastDot)?',':'.';
    const thouSep=decSep===','?'.':','; s=s.split(thouSep).join(''); if(decSep===',') s=s.replace(',', '.');
  }else if(hasComma){
    const parts=s.split(','); if(parts[1] && parts[1].length<=2){ s=parts[0].split('.').join('')+'.'+parts[1]; } else { s=s.replace(/,/g,''); }
  }else if(hasDot){
    const parts=s.split('.'); if(!(parts[1]&&parts[1].length<=2)){ s=s.replace(/\./g,''); }
  }
  s=s.replace(/[^0-9.\-]/g,''); if(s===''||s==='-'||s==='-.') return null;
  const n=parseFloat(s); return Number.isFinite(n)?n:null;
}

let __yt,__st;
function flashYear(msg,color="#0ea5e9",ms=3500){const el=document.getElementById('statusYear');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__yt);__yt=setTimeout(()=>{el.textContent='';},ms);}
function flashSave(msg,color="#059669",ms=4000){const el=document.getElementById('statusSave');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__st);__st=setTimeout(()=>{el.textContent='';},ms);}
function setStatusProgress(text,color='#64748b'){const el=document.getElementById('statusSave');if(!el)return;el.textContent=text;el.style.color=color;}
const notes=document.getElementById('notes');
function note(msg, ok=false){notes.innerHTML=`<div class="${ok?'note-ok':'note-err'}">${msg}</div>`;}
const getYear=()=> (document.getElementById('tahun')?.value||'').trim();
const isValidYear=y=>/^\d{4}$/.test(String(y))&&+y>=YEAR_MIN&&+y<=YEAR_MAX;
function setGlobal(ok, tahun){
  const el=document.getElementById('globalInfo');
  if(!el) return;
  if(!tahun || !/^\d{4}$/.test(tahun)){ el.innerHTML=''; return; }
  el.innerHTML = ok
    ? `<span class="badge-ok">Data tersedia untuk tahun ${tahun} ✓</span>`
    : `<span class="note-err">NOTED: Belum ada data untuk tahun ${tahun}.</span>`;
}

/* ====== File bag ====== */
const fAll=document.getElementById('fileAll'),fMore=document.getElementById('fileMore');
const pick=document.getElementById('pickAll'),addBtn=document.getElementById('addFile'),clrBtn=document.getElementById('clearFiles'),nameEl=document.getElementById('nameAll');
let bag=new DataTransfer();
function addFiles(list){for(const f of list) bag.items.add(f); fAll.files=bag.files; nameEl.textContent=[...bag.files].map(f=>f.name).join(', ')||'Belum ada file dipilih (bisa multi)';}
function clearFiles(){bag=new DataTransfer();fAll.value='';fAll.files=bag.files;nameEl.textContent='Belum ada file dipilih (bisa multi)';notes.innerHTML='';}
pick.onclick=()=>fAll.click(); fAll.onchange=()=>{if(fAll.files.length) addFiles(fAll.files);};
addBtn.onclick=()=>fMore.click(); fMore.onchange=()=>{if(fMore.files.length) addFiles(fMore.files); fMore.value='';};
clrBtn.onclick=clearFiles;

/* ====== Placeholder & tahun state ====== */
function placeholder(tbodyId, cols, msg){
  const tb=document.getElementById(tbodyId);
  tb.innerHTML=`<tr><td colspan="${cols}">${msg}</td></tr>`;
  const tf=document.getElementById(tbodyId.replace('tbody','tfoot')); if(tf) tf.innerHTML='';
}
function setYearState(){
  const y=getYear(), ok=isValidYear(y);
  ['tahunAKIHead','tahunPemasaranHead','tahunOlahanKabHead','tahunOlahanJenisHead'].forEach(id=>{const el=document.getElementById(id); if(el) el.textContent=ok?y:'-';});
  pick.disabled=!ok; addBtn.disabled=!ok; clrBtn.disabled=false;
  const btnUpload=document.getElementById('btnUpload'); if(btnUpload) btnUpload.disabled=!ok;
  placeholder('tbodyAKI',6, ok?'Belum ada data.':'Pilih tahun untuk melihat data.');
  placeholder('tbodyPemasaran',4, ok?'Belum ada data.':'Pilih tahun untuk melihat data.');
  placeholder('tbodyOlahanKab',12, ok?'Belum ada data.':'Pilih tahun untuk melihat data.');
  placeholder('tbodyOlahanJenis',2, ok?'Belum ada data.':'Pilih tahun untuk melihat data.');
  if(!ok){ setGlobal(false,''); }
}
document.addEventListener('DOMContentLoaded',()=>{ const y=document.getElementById('tahun'); if(y) y.value=''; setYearState(); });

/* ====== Show/Hide helpers (limit 10) ====== */
const MAX_ROWS = MAX_INITIAL_ROWS;
const withLimit = (arr, showAll) => showAll ? arr : arr.slice(0, MAX_ROWS);
function toggleVis(kind, total, limit){
  const showBtn=document.getElementById('btnShowAll'+kind);
  const hideBtn=document.getElementById('btnHide'+kind);
  if(!showBtn || !hideBtn) return;
  if(total>limit){ showBtn.style.display='inline-flex'; hideBtn.style.display='none'; }
  else { showBtn.style.display='none'; hideBtn.style.display='none'; }
}
let AKI_CACHE=[], PEMASARAN_CACHE=[], OLAHANKAB_CACHE=[], OLAHANJENIS_CACHE=[];
let SHOW_ALL_AKI=false, SHOW_ALL_PEMASARAN=false, SHOW_ALL_OLAHANKAB=false, SHOW_ALL_OLAHANJENIS=false;

/* ====== XLSX helpers ====== */
function readXlsx(f){return new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>{try{res(XLSX.read(e.target.result,{type:'array'}));}catch(err){rej(err)}};r.onerror=rej;r.readAsArrayBuffer(f);});}
function sheetToRows(ws){
  const aoa=XLSX.utils.sheet_to_json(ws,{header:1,raw:true,defval:''});
  const hdr=(aoa[0]||[]).map(h=>String(h||'').replace(/\u00A0/g,' ').replace(/\s+/g,' ').trim()); // rapikan header
  const data=aoa.slice(1).map(row=>Object.fromEntries(hdr.map((h,i)=>[h,row[i]])));
  return {cols:hdr,data:data.filter(r=>Object.values(r).some(v=>String(v).trim()!==''))};
}

/* ====== SAVE helper ====== */
async function saveRows(table, rows){
  try{
    if(!rows||!rows.length) return {ok:true,saved:0};
    setStatusProgress(`Menyimpan: ${table}…`);
    const res=await fetch('api/save_rows.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({table,rows})});
    const out=await res.json();
    if(!out.ok) throw new Error(out.error||'Gagal simpan');
    setStatusProgress(`TERSIMPAN (${table}) ✓`,'#059669'); setTimeout(()=>setStatusProgress(''),1200);
    flashSave(`TERSIMPAN (${table}) ✓`,'#059669',2500);
    return out;
  }catch(e){ console.warn('saveRows:', e); setStatusProgress(`GAGAL SIMPAN (${table})`,'#dc2626'); return {ok:false,error:String(e)}; }
}

/* ====== Renderers ====== */
const sumCol=(rows,k)=>rows.reduce((a,b)=>a+(parseNumID(b[k])||0),0);

// --- helper pembulatan & rata-rata ala Excel (per baris dibulatkan dulu) ---
const round2 = x => Math.round((Number(x) + Number.EPSILON) * 100) / 100;
const excelMean = (rows, key) => {
  const nums = rows.map(r => parseNumID(r[key])).filter(n => Number.isFinite(n));
  if (!nums.length) return null;
  const eachRounded = nums.map(round2);                // bulatkan per-baris
  const avg = eachRounded.reduce((a,b)=>a+b,0) / eachRounded.length;
  return round2(avg);                                   // bulatkan hasil akhir
};
const fmt2 = v => (v==null ? '' : Number(v).toLocaleString('id-ID', {
  minimumFractionDigits: 2, maximumFractionDigits: 2
}));

function renderAKIDB(rows){
  AKI_CACHE = Array.isArray(rows) ? rows.slice() : [];
  const tb = document.getElementById('tbodyAKI');
  const tf = document.getElementById('tfootAKI');

  if (!AKI_CACHE.length){
    placeholder('tbodyAKI', 6, 'Belum ada data.');
    toggleVis('AKI', 0, 0);
    return;
  }

  const view = withLimit(AKI_CACHE, SHOW_ALL_AKI);

  tb.innerHTML = view.map((r,i)=>`
    <tr>
      <td class="text-center">${i+1}</td>
      <td>${r.kab_kota || r.nama_kab_kota || ''}</td>
      <td class="text-right">${fmt(r.kidrt)}</td>
      <td class="text-right">${fmt(r.kilrt)}</td>
      <td class="text-right">${fmt(r.ktt)}</td>
      <td class="text-right">${fmt(r.aki)}</td>
    </tr>
  `).join('');

  // Footer: rata-rata ala Excel (tiap baris dibulatkan 2 desimal lebih dulu)
  tf.innerHTML = `
    <tr class="row-total">
      <td colspan="2" class="text-center">Rata-rata</td>
      <td class="text-right">${fmt2(excelMean(AKI_CACHE,'kidrt'))}</td>
      <td class="text-right">${fmt2(excelMean(AKI_CACHE,'kilrt'))}</td>
      <td class="text-right">${fmt2(excelMean(AKI_CACHE,'ktt'))}</td>
      <td class="text-right">${fmt2(excelMean(AKI_CACHE,'aki'))}</td>
    </tr>
  `;

  toggleVis('AKI', AKI_CACHE.length, MAX_ROWS);
}

function renderPemasaranDB(rows){
  PEMASARAN_CACHE = Array.isArray(rows)?rows.slice():[];
  const tb=document.getElementById('tbodyPemasaran'), tf=document.getElementById('tfootPemasaran');
  if(!PEMASARAN_CACHE.length){ placeholder('tbodyPemasaran',4,'Belum ada data.'); toggleVis('Pemasaran',0,0); return; }

  const view = withLimit(PEMASARAN_CACHE, SHOW_ALL_PEMASARAN);
  tb.innerHTML = view.map(r=>`
    <tr><td>${r.kab_kota||''}</td>
      <td class="text-right">${fmt(r.pengecer)}</td>
      <td class="text-right">${fmt(r.pengumpul)}</td>
      <td class="text-right">${fmt(r.jumlah_unit)}</td></tr>`).join('');
  tf.innerHTML = `<tr class="row-total"><td class="text-center">Jumlah</td>
    <td class="text-right">${fmt(sumCol(PEMASARAN_CACHE,'pengecer'))}</td>
    <td class="text-right">${fmt(sumCol(PEMASARAN_CACHE,'pengumpul'))}</td>
    <td class="text-right">${fmt(sumCol(PEMASARAN_CACHE,'jumlah_unit'))}</td></tr>`;

  toggleVis('Pemasaran', PEMASARAN_CACHE.length, MAX_ROWS);
}

function renderOlahanKabDB(rows){
  OLAHANKAB_CACHE = Array.isArray(rows)?rows.slice():[];
  const tb=document.getElementById('tbodyOlahanKab'), tf=document.getElementById('tfootOlahanKab');
  if(!OLAHANKAB_CACHE.length){ placeholder('tbodyOlahanKab',12,'Belum ada data.'); toggleVis('OlahanKab',0,0); return; }
  const K=['fermentasi','pelumatan_daging_ikan','pembekuan','pemindangan','penanganan_produk_segar','pengalengan','pengasapan_pemanggangan','pereduksian_ekstraksi','penggaraman_pengeringan','pengolahan_lainnya'];
  const view = withLimit(OLAHANKAB_CACHE, SHOW_ALL_OLAHANKAB);

  tb.innerHTML = view.map(r=>`
    <tr><td>${r.kab_kota||''}</td>
      ${K.map(k=>`<td class="text-right">${fmt(r[k])}</td>`).join('')}
      <td class="text-right">${fmt(r.jumlah_unit)}</td></tr>`).join('');
  tf.innerHTML = `<tr class="row-total"><td class="text-center">Jumlah</td>
    ${K.map(k=>`<td class="text-right">${fmt(sumCol(OLAHANKAB_CACHE,k))}</td>`).join('')}
    <td class="text-right">${fmt(sumCol(OLAHANKAB_CACHE,'jumlah_unit'))}</td></tr>`;

  toggleVis('OlahanKab', OLAHANKAB_CACHE.length, MAX_ROWS);
}

function renderOlahanJenisDB(rows){
  OLAHANJENIS_CACHE = Array.isArray(rows)?rows.slice():[];
  const tb=document.getElementById('tbodyOlahanJenis'), tf=document.getElementById('tfootOlahanJenis');
  if(!OLAHANJENIS_CACHE.length){ placeholder('tbodyOlahanJenis',2,'Belum ada data.'); toggleVis('OlahanJenis',0,0); return; }

  const view = withLimit(OLAHANJENIS_CACHE, SHOW_ALL_OLAHANJENIS);
  tb.innerHTML = view.map(r=>`
    <tr><td>${r.jenis_kegiatan_pengolahan||''}</td>
      <td class="text-right">${fmt(r.jumlah_upi)}</td></tr>`).join('');
  tf.innerHTML = `<tr class="row-total"><td class="text-center">Jumlah</td>
    <td class="text-right">${fmt(sumCol(OLAHANJENIS_CACHE,'jumlah_upi'))}</td></tr>`;

  toggleVis('OlahanJenis', OLAHANJENIS_CACHE.length, MAX_ROWS);
}

/* Tombol Show/Hide */
document.getElementById('btnShowAllAKI').onclick=()=>{ SHOW_ALL_AKI=true; renderAKIDB(AKI_CACHE); document.getElementById('btnShowAllAKI').style.display='none'; document.getElementById('btnHideAKI').style.display='inline-flex'; };
document.getElementById('btnHideAKI').onclick=()=>{ SHOW_ALL_AKI=false; renderAKIDB(AKI_CACHE); document.getElementById('btnHideAKI').style.display='none'; document.getElementById('btnShowAllAKI').style.display='inline-flex'; };
document.getElementById('btnShowAllPemasaran').onclick=()=>{ SHOW_ALL_PEMASARAN=true; renderPemasaranDB(PEMASARAN_CACHE); document.getElementById('btnShowAllPemasaran').style.display='none'; document.getElementById('btnHidePemasaran').style.display='inline-flex'; };
document.getElementById('btnHidePemasaran').onclick=()=>{ SHOW_ALL_PEMASARAN=false; renderPemasaranDB(PEMASARAN_CACHE); document.getElementById('btnHidePemasaran').style.display='none'; document.getElementById('btnShowAllPemasaran').style.display='inline-flex'; };
document.getElementById('btnShowAllOlahanKab').onclick=()=>{ SHOW_ALL_OLAHANKAB=true; renderOlahanKabDB(OLAHANKAB_CACHE); document.getElementById('btnShowAllOlahanKab').style.display='none'; document.getElementById('btnHideOlahanKab').style.display='inline-flex'; };
document.getElementById('btnHideOlahanKab').onclick=()=>{ SHOW_ALL_OLAHANKAB=false; renderOlahanKabDB(OLAHANKAB_CACHE); document.getElementById('btnHideOlahanKab').style.display='none'; document.getElementById('btnShowAllOlahanKab').style.display='inline-flex'; };
document.getElementById('btnShowAllOlahanJenis').onclick=()=>{ SHOW_ALL_OLAHANJENIS=true; renderOlahanJenisDB(OLAHANJENIS_CACHE); document.getElementById('btnShowAllOlahanJenis').style.display='none'; document.getElementById('btnHideOlahanJenis').style.display='inline-flex'; };
document.getElementById('btnHideOlahanJenis').onclick=()=>{ SHOW_ALL_OLAHANJENIS=false; renderOlahanJenisDB(OLAHANJENIS_CACHE); document.getElementById('btnHideOlahanJenis').style.display='none'; document.getElementById('btnShowAllOlahanJenis').style.display='inline-flex'; };

/* ====== Load from DB ====== */
async function loadFromDB(year, seq){
  if(typeof seq!=='number') seq=++YEAR_LOAD_SEQ; const mySeq=seq;
  try{
    ['tahunAKIHead','tahunPemasaranHead','tahunOlahanKabHead','tahunOlahanJenisHead'].forEach(id=>{const el=document.getElementById(id); if(el) el.textContent=year;});
    const resp = await fetch(`api/pengolahan_pemasaran_fetch.php?tahun=${encodeURIComponent(year)}&_=${Date.now()}`, {cache:'no-store'});
    const p = await resp.json(); if (mySeq !== YEAR_LOAD_SEQ) return;

    const aki        = Array.isArray(p.aki)?p.aki:[];
    const pemasaran  = Array.isArray(p.pemasaran)?p.pemasaran:[];
    const olahankab  = Array.isArray(p.olahankab)?p.olahankab:[];
    const olahjenis  = Array.isArray(p.olahjenis)?p.olahjenis:[];

    SHOW_ALL_AKI=false; SHOW_ALL_PEMASARAN=false; SHOW_ALL_OLAHANKAB=false; SHOW_ALL_OLAHANJENIS=false;

    renderAKIDB(aki);
    renderPemasaranDB(pemasaran);
    renderOlahanKabDB(olahankab);
    renderOlahanJenisDB(olahjenis);

    const hasAny = aki.length || pemasaran.length || olahankab.length || olahjenis.length;
    setGlobal(!!hasAny, year);
    flashYear(hasAny ? 'Data tersedia ✓' : 'Tidak ada data.', hasAny?'#059669':'#dc2626', 2500);
  }catch(e){
    if (mySeq !== YEAR_LOAD_SEQ) return;
    console.error(e); setGlobal(false, year); flashYear(`Error mengambil data tahun ${year}`,'#dc2626');
  }
}

/* ====== Detector & parsing dari Excel ====== */
const norm = s => (s||'').toString()
  .replace(/\u00A0/g,' ')     // NBSP -> space
  .replace(/\s+/g,' ')        // rapikan spasi & newline
  .toLowerCase()
  .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
  .replace(/[^\p{L}\p{N}]+/gu,' ')
  .trim();

function guessType(cols){
  const n=cols.map(norm);
  const has = kw => n.some(c=>c.includes(norm(kw)));
  const isAKI = (has('nama kabupaten kota')||has('kabupaten')||has('kota')||has('wilayah')||has('kab kota'))
             && (has('kidrt')||has('kilrt')||has('ktt')||has('aki'));
  const isPemasaran = (has('kabupaten')||has('kota')||has('kab kota')||has('wilayah')) && has('pengecer') && (has('pengumpul')||has('pedagang besar')||has('distributor'));
  const proses = ['fermentasi','pelumatan daging ikan','pembekuan','pemindangan','penanganan produk segar','pengalengan','pengasapan','pemanggangan','pereduksian','ekstraksi','penggaraman','pengeringan','pengolahan lainnya'];
  const prosesHit = proses.reduce((a,p)=>a+(has(p)?1:0),0);
  const isOlahanKab = (has('kabupaten')||has('kota')||has('kab kota')||has('wilayah')) && prosesHit>=2;
  const isOlahanJenis = has('jenis kegiatan pengolahan') && (has('jumlah')||has('upi')||has('total'));
  if(isAKI) return 'aki';
  if(isPemasaran) return 'pemasaran';
  if(isOlahanKab) return 'olahan_kab';
  if(isOlahanJenis) return 'olahan_jenis';
  return 'unknown';
}

/* ====== Upload → parse → simpan → tampil ====== */
document.getElementById('btnUpload').onclick = async () => {
  const tahun = getYear();
  if (!isValidYear(tahun)) {
    flashSave('Tahun wajib 4 digit (2000–2100).', '#dc2626');
    flashYear('Tahun tidak valid. Format YYYY 2000–2100.', '#dc2626');
    return;
  }
  ['tahunAKIHead','tahunPemasaranHead','tahunOlahanKabHead','tahunOlahanJenisHead']
    .forEach(id => document.getElementById(id).textContent = tahun);

  if ((bag.files || []).length === 0) {
    flashSave('Pilih minimal satu file.', '#dc2626');
    return;
  }

  setStatusProgress('Memproses file...'); 
  notes.innerHTML = '';
  const pack = { aki: [], pemasaran: [], olahankab: [], olahjenis: [] };

  const findCol = (cols, patterns, fallback) => {
    const ncols = cols.map(norm);
    for (let i = 0; i < ncols.length; i++) {
      const s = ncols[i];
      for (const rx of patterns) { if (rx.test(s)) return cols[i]; }
    }
    return fallback ?? null;
  };

  for (const f of bag.files) {
    try {
      const wb = await readXlsx(f);

      wb.SheetNames.forEach(name => {
        const ws = wb.Sheets[name];
        if (!ws || !ws['!ref']) return;

        const { cols, data } = sheetToRows(ws);
        if (!cols.length) return;

        const type = guessType(cols);

        if (type === 'aki') {
          const kabKey  = findCol(cols,
            [/^nama.*kab/i, /\bkab(\/|\s)*kota\b/i, /\bkabupaten\b/i, /\bkota\b/i, /\bwilayah\b/i],
            cols.find(c=>norm(c)==='kabupaten kota') || cols.find(c=>norm(c)==='kabupaten/ kota') || 'Kabupaten/ Kota'
          );

          const kidrtKey = findCol(cols, [
            /\bkidrt\b/i, /\baki[^a-z0-9]*a\b/i, /\baki\s*\(a\)\b/i, /\ba[^a-z0-9]*\(kidrt\)\b/i, /\bkidrt[^a-z0-9]*(aki|a)\b/i, /\b(aki|a)[^a-z0-9]*kidrt\b/i
          ], null);

          const kilrtKey = findCol(cols, [
            /\bkilrt\b/i, /\baki[^a-z0-9]*b\b/i, /\baki\s*\(b\)\b/i, /\bb[^a-z0-9]*\(kilrt\)\b/i, /\bkilrt[^a-z0-9]*(aki|b)\b/i, /\b(aki|b)[^a-z0-9]*kilrt\b/i
          ], null);

          const kttKey = findCol(cols, [
            /\bktt\b/i, /\baki[^a-z0-9]*c\b/i, /\baki\s*\(c\)\b/i, /\bc[^a-z0-9]*\(ktt\)\b/i, /\bktt[^a-z0-9]*(aki|c)\b/i, /\b(aki|c)[^a-z0-9]*ktt\b/i
          ], null);

          const akiKey = findCol(cols, [
            /^aki(\s*\d{4})?$/i, /aki[^a-z0-9]*(total|jumlah)/i, /angka\s*konsumsi\s*ikan/i
          ], null);

          data.forEach(r => {
            const nama = String(r[kabKey] ?? '').trim();
            if (nama === '') return;

            const a = kidrtKey ? parseNumID(r[kidrtKey]) : null;
            const b = kilrtKey ? parseNumID(r[kilrtKey]) : null;
            const c = kttKey   ? parseNumID(r[kttKey])   : null;

            let tot = akiKey ? parseNumID(r[akiKey]) : null;
            const hasABC = [a, b, c].some(v => v != null);
            if (tot == null && hasABC) tot = (a || 0) + (b || 0) + (c || 0);

            pack.aki.push({
              tahun : +tahun,
              kab_kota: nama,
              kidrt: a || 0,
              kilrt: b || 0,
              ktt  : c || 0,
              aki  : tot || 0
            });
          });

          note(`AKI parsed (sheet: ${name}): ${data.length} baris.`, true);

        } else if (type === 'pemasaran') {
          const kabKey       = cols.find(c => /kab|kota|wilayah/i.test(c)) || 'Kabupaten/ Kota';
          const pengecerKey  = cols.find(c => /pengecer/i.test(c)) || 'Pengecer';
          const pengumpulKey = cols.find(c => /pengumpul|pedagang besar|distributor/i.test(c)) || 'Pengumpul/ Pedagang Besar/ Distributor';
          const jumlahKey    = cols.find(c => /jumlah|total|unit/i.test(c)) || 'Jumlah Unit';

          data.forEach(r => {
            const a = parseNumID(r[pengecerKey]);
            const b = parseNumID(r[pengumpulKey]);
            const jRaw = r[jumlahKey];
            const j = (jRaw === '' || jRaw == null) ? ((a || 0) + (b || 0)) : parseNumID(jRaw);

            pack.pemasaran.push({
              tahun: +tahun,
              kab_kota: String(r[kabKey] ?? ''),
              pengecer: a || 0,
              pengumpul: b || 0,
              jumlah_unit: j || 0
            });
          });

          note(`PEMASARAN parsed (sheet: ${name}): ${data.length} baris.`, true);

        } else if (type === 'olahan_kab') {
          const kabKey = cols.find(c => /kab|kota|wilayah/i.test(c)) || 'Kabupaten/ Kota';
          const mapCols = [
            ['fermentasi','Fermentasi'],
            ['pelumatan_daging_ikan','Pelumatan Daging Ikan'],
            ['pembekuan','Pembekuan'],
            ['pemindangan','Pemindangan'],
            ['penanganan_produk_segar','Penanganan Produk Segar'],
            ['pengalengan','Pengalengan'],
            ['pengasapan_pemanggangan','Pengasapan/ Pemanggangan'],
            ['pereduksian_ekstraksi','Pereduksian/ Ekstraksi'],
            ['penggaraman_pengeringan','Penggaraman/ Pengeringan'],
            ['pengolahan_lainnya','Pengolahan Lainnya'],
          ];
          const jumlahKey = cols.find(c => /jumlah|total|unit/i.test(c)) || 'Jumlah Unit';

          data.forEach(r => {
            const rec = { tahun:+tahun, kab_kota:String(r[kabKey] ?? ''), jumlah_unit:0 };
            let sum = 0;
            mapCols.forEach(([key,label]) => { const v = parseNumID(r[label]); rec[key] = v || 0; sum += rec[key]; });
            rec.jumlah_unit = (r[jumlahKey] === '' || r[jumlahKey] == null) ? sum : (parseNumID(r[jumlahKey]) || 0);
            pack.olahankab.push(rec);
          });

          note(`OLAHAN per Kab/Kota parsed (sheet: ${name}): ${data.length} baris.`, true);

        } else if (type === 'olahan_jenis') {
          const jenisKey  = cols.find(c => /jenis.*pengolahan/i.test(c)) || 'Jenis Kegiatan Pengolahan';
          const jumlahKey = cols.find(c => /jumlah|upi|total/i.test(c))   || 'Jumlah UPI';

          data.forEach(r => {
            pack.olahjenis.push({
              tahun:+tahun,
              jenis_kegiatan_pengolahan:String(r[jenisKey] ?? ''),
              jumlah_upi: parseNumID(r[jumlahKey]) || 0
            });
          });

          note(`OLAHAN menurut Jenis parsed (sheet: ${name}): ${data.length} baris.`, true);
        }
      });
    } catch (e) {
      note(`NOTED: Gagal membaca ${f.name}: ${e.message || e}`, false);
    }
  }

  const tasks = [
    ['pengolahan_pemasaran_aki',       pack.aki],
    ['pengolahan_pemasaran_pemasaran', pack.pemasaran],
    ['pengolahan_pemasaran_olahankab', pack.olahankab],
    ['pengolahan_pemasaran_olahjenis', pack.olahjenis],
  ];
  for (const [tbl, rows] of tasks) { if (rows.length) await saveRows(tbl, rows); }

  setStatusProgress('');
  const seq = ++YEAR_LOAD_SEQ; 
  await loadFromDB(tahun, seq);
  if (seq === YEAR_LOAD_SEQ) { flashSave(`Data tahun ${tahun} diproses ✓`, '#059669'); }
};

/* ====== Tahun input binding + reset ====== */
function triggerLoad(){
  const y=getYear();
  if(!isValidYear(y)){ setYearState(); setGlobal(false,''); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return; }
  setYearState(); const seq=++YEAR_LOAD_SEQ; loadFromDB(y, seq);
}
const yearInput=document.getElementById('tahun');
yearInput.addEventListener('input',()=>{clearTimeout(window.__yearDebounce);window.__yearDebounce=setTimeout(triggerLoad,250);});
yearInput.addEventListener('change',triggerLoad);
yearInput.addEventListener('blur',triggerLoad);
yearInput.addEventListener('keydown',(e)=>{if(e.key==='Enter'){e.preventDefault();triggerLoad();}});

document.getElementById('btnReset').onclick=()=>{ clearFiles(); setYearState(); const st=document.getElementById('statusSave'); if(st) st.textContent=''; notes.innerHTML=''; setGlobal(false,''); };
document.getElementById('btnYearReset').onclick=()=>{ YEAR_LOAD_SEQ++; const y=document.getElementById('tahun'); if(y) y.value=''; try{ clearTimeout(window.__yearDebounce); }catch(_){}
  setYearState(); const sy=document.getElementById('statusYear'); if(sy){ sy.textContent=''; sy.removeAttribute('style'); } setGlobal(false,''); };

/* ====== Template (kosongan) ====== */
function wbDownload(name,sheets){
  const wb=XLSX.utils.book_new();
  sheets.forEach(s=>{const ws=XLSX.utils.aoa_to_sheet(s.aoa); if(s.widths){ws['!cols']=s.widths.map(w=>({wch:w}));} XLSX.utils.book_append_sheet(wb,ws,s.name);});
  XLSX.writeFile(wb,name);
}
function tpl_AKI(){
  return {
    name:'AKI',
    widths:[6,28,12,12,12,14],
    aoa:[['No','Kabupaten/ Kota','KIDRT (AKI A)','KILRT (AKI B)','KTT (AKI C)','AKI (Total)']]
  };
}
function tpl_Pemasaran(){return{ name:'Pemasaran',              widths:[20,12,32,14], aoa:[['Kabupaten/ Kota','Pengecer','Pengumpul/ Pedagang Besar/ Distributor','Jumlah Unit']] }; }
function tpl_OlahanKab(){return{ name:'Pengolahan per KabKota', widths:[20,12,18,12,14,18,12,24,20,22,18,14], aoa:[['Kabupaten/ Kota','Fermentasi','Pelumatan Daging Ikan','Pembekuan','Pemindangan','Penanganan Produk Segar','Pengalengan','Pengasapan/ Pemanggangan','Pereduksian/ Ekstraksi','Penggaraman/ Pengeringan','Pengolahan Lainnya','Jumlah Unit']] }; }
function tpl_OlahanJenis(){return{ name:'Pengolahan menurut Jenis', widths:[30,14], aoa:[['Jenis Kegiatan Pengolahan','Jumlah UPI']] }; }

function downloadTpl(k){
  const spec={
    aki      :{fname:'AKI KabKota (v2).xlsx',           sheets:[tpl_AKI()]},
    pemasaran:{fname:'Pemasaran (jenis kegiatan).xlsx', sheets:[tpl_Pemasaran()]},
    olahkab  :{fname:'Pengolahan per KabKota.xlsx',     sheets:[tpl_OlahanKab()]},
    olahjenis:{fname:'Pengolahan menurut Jenis.xlsx',   sheets:[tpl_OlahanJenis()]}
  }[k];
  if(spec) wbDownload(spec.fname,spec.sheets);
}
document.getElementById('akiColHead').textContent = 'AKI (Total)';
document.querySelectorAll('[data-tpl]').forEach(b=>b.onclick=()=>downloadTpl(b.dataset.tpl));
document.getElementById('btnDownloadTemplates').onclick=()=>['aki','pemasaran','olahkab','olahjenis'].forEach((k,i)=>setTimeout(()=>downloadTpl(k),i*150));

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
