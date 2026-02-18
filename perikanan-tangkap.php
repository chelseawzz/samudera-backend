<?php
// perikanan-tangkap.php — Upload + Viewer
require_once __DIR__.'/auth_guard.php';
require_login();

// ⤵️ izinkan readonly kalau dipanggil dari dashboard (iframe)
$IS_DASH = isset($_GET['dashboard']) && $_GET['dashboard'] === '1';
if (!$IS_DASH) {
  require_admin();   // tetap wajib admin kalau buka langsung (bukan dashboard)
}

require_once __DIR__.'/protected_template.php';
start_protected_page('Perikanan Tangkap','tangkap');
?>

<style>
:root{--toska:#0097a7;--head:#0b1b2b;--head-2:#0f2134;--head-3:#132a44;--line:#e2e8f0}
.field-label{font-weight:700;color:#0f172a}.hint{font-size:.78rem;color:#64748b}
.nice-input{display:flex;align-items:center;gap:.5rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:.6rem .75rem;box-shadow:0 2px 6px rgba(2,8,20,.04)}
.nice-input input{width:100%;border:none;outline:none;background:transparent;font-size:.95rem;appearance:textfield;text-align:center}
.nice-input input::-webkit-outer-spin-button,.nice-input input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.filebox{display:flex;align-items:center;gap:.6rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:.45rem .5rem;box-shadow:0 2px 6px rgba(0,8,20,.04)}
.filebox input[type=file]{display:none}
.filebox .pick{display:inline-flex;align-items:center;gap:.45rem;background:var(--toska);color:#fff;font-weight:700;border-radius:10px;padding:.55rem .9rem;box-shadow:0 6px 18px rgba(0,151,167,.25)}
.filebox .pick:hover{background:#008a9d}.filebox .name{flex:1;color:#475569;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.btn-primary{background:var(--toska);color:#fff;font-weight:700;border-radius:12px;padding:.65rem 1rem;box-shadow:0 6px 18px rgba(0,151,167,.25)}
.btn-ghost{background:#fff;color:#0097a7;font-weight:700;border:1px solid var(--toska);border-radius:12px;padding:.65rem 1rem}
.btn-ghost:hover{background:#f1fefe}.btn-xs{padding:.32rem .5rem;border-radius:10px;font-size:.78rem}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid var(--toska);color:#0b7285;border-radius:10px;background:#fff}
.icon-btn[disabled],.btn-primary[disabled],.btn-ghost[disabled],.filebox .pick[disabled]{opacity:.5;cursor:not-allowed}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 2px 6px rgba(2,8,20,.04)}.card-h{padding:6px 10px;border-bottom:1px solid var(--line);font-weight:700}.card-b{padding:8px 10px}
.panel-grid{display:grid;grid-template-columns:1fr;gap:20px}
@media (min-width:1024px){.panel-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #e2e8f0;padding:.5rem .75rem;font-size:.875rem;line-height:1.25rem;background:#fff}
thead.bg-samudera th{background:#0b1b2b!important;color:#fff!important;border-color:#fff!important;font-weight:700!important;font-size:.875rem!important;white-space:nowrap}
.thead-matrix th{white-space:nowrap;color:#fff!important;border-color:#fff!important;font-weight:700!important;font-size:.875rem}
.thead-matrix tr:nth-child(1) th{background:var(--head)!important;text-transform:uppercase}
.thead-matrix tr:nth-child(2) th{background:var(--head-2)!important;text-transform:uppercase}
.thead-matrix tr:nth-child(3) th{background:var(--head-3)!important;color:rgba(255,255,255,.85)!important;font-style:italic}
tr.row-total td{font-weight:700;background:#f8fafc}
.subnote{font-size:.78rem;color:#93a3b5;font-style:italic}
.sec-head{display:flex;align-items:center;justify-content:space-between}
.small-actions{display:flex;gap:.4rem;align-items:center}
.row-komo td{background:#f1f5f9!important;border-top:2px solid #cbd5e1!important;font-weight:700}
.row-child td{padding-left:22px}
.bold-first td:first-child{font-weight:700}
.col-jumlah{font-weight:700}
/* ===== NOTED styles (SAMAKAN dengan Ekspor) ===== */
.note-err{color:#dc2626;font-size:.85rem}
.note-ok{color:#059669;font-size:.85rem}
.badge-ok{font-size:.75rem;margin-left:.25rem;color:#059669}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

<h1 class="text-2xl font-bold mb-1">Perikanan Tangkap</h1>
<p class="text-slate-600 mb-1"><b>Upload Excel (.xlsx/.xls). Sistem mendeteksi template &amp; menampilkan tabel</b></p>
<p class="text-xs mb-4" id="globalInfo"></p>

<section id="panelUpload" class="bg-white rounded-2xl shadow p-5 mb-6">
  <div class="panel-grid">
    <div class="lg:col-span-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="field-label mb-2 block">Tahun</label>
          <div class="flex gap-2 items-center">
            <div class="nice-input"><input id="tahun" type="number" min="2000" max="2100" placeholder="yyyy" autocomplete="off"/></div>
            <button id="btnYearReset" type="button" class="btn-ghost btn-xs">Reset Tahun</button>
            <span id="statusYear" class="text-xs text-slate-500 ml-2"></span>
          </div>
          <p class="hint mt-2">Pilih tahun dulu. Tanpa tahun valid, upload dinonaktifkan.</p>
        </div>
        <div></div>
        <div class="md:col-span-2">
          <label class="field-label mb-2 block">Upload File Tangkap (.xlsx/.xls)</label>
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
      <div class="card-h">Template Excel (header saja)</div>
      <div class="card-b">
        <div class="flex flex-col gap-1">
          <button id="btnDownloadTemplates" class="btn-ghost btn-xs w-full" type="button"><i class="fa-solid fa-download mr-1"></i> Download Semua (5)</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="ringkasan"  type="button">Ringkasan.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="matrix"     type="button">Produksi (Matrix).xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="volume"     type="button">Volume per Bulan/Wadah.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="nilai"      type="button">Nilai per Bulan/Wadah.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="komoditas"  type="button">Komoditas Unggulan.xlsx</button>
        </div>
      </div>
    </aside>
  </div>
</section>

<!-- RINGKASAN -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Ringkasan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunRingkasan">-</span></p></div>
    <div class="small-actions"><button id="btnShowAllRingkasan" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="btnHideRingkasan" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-center thead-matrix">
        <tr><th rowspan="3">CABANG USAHA</th><th rowspan="3">NELAYAN<br><span class="subnote">(ORANG)</span></th><th rowspan="3">RTP/PP<br><span class="subnote">(ORANG/UNIT)</span></th><th rowspan="3">ARMADA PERIKANAN<br><span class="subnote">(BUAH)</span></th><th rowspan="3">ALAT TANGKAP<br><span class="subnote">(UNIT)</span></th><th colspan="2">PRODUKSI</th></tr>
        <tr><th colspan="2">IKAN SEGAR</th></tr>
        <tr><th>VOLUME<br><span class="subnote">(TON)</span></th><th>NILAI<br><span class="subnote">(RP. 1.000,-)</span></th></tr>
      </thead>
      <tbody id="tbodyRingkasan"><tr><td colspan="7">Pilih tahun untuk melihat data.</td></tr></tbody>
    </table>
  </div>
</section>

<!-- MATRIX -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Produksi Menurut Sub Sektor &amp; Kabupaten/Kota</h3><p class="text-xs text-slate-500"><span class="italic">Satuan: Ton</span> — Tahun <span id="tahunProduksi">-</span></p></div>
    <div class="small-actions"><button id="btnShowAllMatrix" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="btnHideMatrix" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="text-center thead-matrix"><tr id="hdrRow1"></tr><tr id="hdrRow2"></tr><tr id="hdrRow3"></tr></thead>
      <tbody id="tbodyMatrix"><tr><td colspan="4">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootMatrix"></tfoot>
    </table>
  </div>
</section>

<!-- VOLUME BULANAN -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Volume Produksi Perwadah Per Bulan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunVol">-</span></p></div>
    <div class="small-actions"><button id="btnShowAllVol" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="btnHideVol" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center" id="theadVol"></thead>
      <tbody id="tbodyVol"><tr><td colspan="14">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootVol"></tfoot>
    </table>
  </div>
</section>

<!-- NILAI BULANAN -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Nilai Produksi Perwadah Per Bulan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunNil">-</span></p></div>
    <div class="small-actions"><button id="btnShowAllNil" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="btnHideNil" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center" id="theadNil"></thead>
      <tbody id="tbodyNil"><tr><td colspan="14">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootNil"></tfoot>
    </table>
  </div>
</section>

<!-- KOMODITAS -->
<section class="bg-white rounded-2xl shadow">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Komoditas Unggulan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunKomoditas">-</span></p></div>
    <div class="small-actions"><button id="btnShowAllKom" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="btnHideKom" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center"><tr><th>No</th><th>Komoditas</th><th>Volume (Ton)</th></tr></thead>
      <tbody id="tbodyKomoditas"><tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr></tbody>
    </table>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
/* ===== Helpers ===== */
const MONTHS=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
const getYear=()=> (document.getElementById('tahun')?.value||'').trim();
const isValidYear=y=>/^\d{4}$/.test(String(y))&&+y>=2000&&+y<=2100;
const nf  = new Intl.NumberFormat('id-ID',{maximumFractionDigits:2,minimumFractionDigits:0});
function parseNumID(v){
  if(v===null||v===undefined) return null;
  if(typeof v==='number' && Number.isFinite(v)) return v;
  let s=String(v).trim(); if(s==='') return null;
  s=s.replace(/\s+/g,'');
  const hasComma=s.includes(','), hasDot=s.includes('.');
  if(hasComma && hasDot){
    const lastComma=s.lastIndexOf(','), lastDot=s.lastIndexOf('.');
    const decSep=(lastComma>lastDot)?',':'.';
    const thouSep=decSep===','?'.':'.';
    s=s.split(thouSep).join('');
    if(decSep===',') s=s.replace(',', '.');
  }else if(hasComma){
    const parts=s.split(',');
    if(parts[1] && parts[1].length<=3){ s=parts[0].split('.').join('')+'.'+parts[1]; }
    else { s=s.replace(/,/g,''); }
  }else if(hasDot){
    const parts=s.split('.');
    if(!(parts[1]&&parts[1].length<=3)){ s=s.replace(/\./g,''); }
  }
  s=s.replace(/[^0-9.\-]/g,'');
  if(s===''||s==='-'||s==='-.') return null;
  const n=parseFloat(s);
  return Number.isFinite(n)?n:null;
}
const fmt=v=>{ if(v===null||v===undefined||String(v).trim()==='') return ''; const n=parseNumID(v); return n===null ? '' : nf.format(n); }
let __yt,__st;
function flashYear(msg,color="#0ea5e9",ms=3500){const el=document.getElementById('statusYear');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__yt);__yt=setTimeout(()=>{el.textContent='';},ms);} 
function flashSave(msg,color="#059669",ms=4000){const el=document.getElementById('statusSave');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__st);__st=setTimeout(()=>{el.textContent='';},ms);} 
function setStatusProgress(text,color='#64748b'){const el=document.getElementById('statusSave');if(!el)return;el.textContent=text;el.style.color=color;}

/* ===== NOTED helpers (SAMAKAN dengan Ekspor) ===== */
const notes=document.getElementById('notes');
function note(msg, ok=false){ if(!notes) return; notes.innerHTML=`<div class="${ok?'note-ok':'note-err'}">${msg}</div>`; }
function setGlobal(ok, tahun){
  const el=document.getElementById('globalInfo');
  if(!el) return;
  if(!tahun || !/^\d{4}$/.test(tahun)){ el.innerHTML=''; return; }
  el.innerHTML = ok
    ? `<span class="badge-ok">Data tersedia untuk tahun ${tahun} ✓</span>`
    : `<span class="note-err">NOTED: Belum ada data untuk tahun ${tahun}.</span>`;
}

/* ===== Show/Hide limit ===== */
const LIMIT=10, cache={ringkasan:[],matrix:[],vol:[],nil:[],kom:[]}, showAll={ringkasan:false,matrix:false,vol:false,nil:false,kom:false};
function applyRows(kind,total,showBtnId,hideBtnId){
  const showBtn=document.getElementById(showBtnId), hideBtn=document.getElementById(hideBtnId);
  if(!showBtn||!hideBtn)return;
  if(total<=LIMIT){ showBtn.style.display='none'; hideBtn.style.display='none'; return; }
  if(showAll[kind]){ showBtn.style.display='none'; hideBtn.style.display='inline-flex'; }
  else{ showBtn.style.display='inline-flex'; hideBtn.style.display='none'; }
}
function bindToggle(kindKey, showId, hideId, renderFn){
  const s=document.getElementById(showId), h=document.getElementById(hideId);
  if(s) s.onclick=()=>{showAll[kindKey]=true; renderFn();};
  if(h) h.onclick=()=>{showAll[kindKey]=false; renderFn();};
}

/* ===== File bag ===== */
const fAll=document.getElementById('fileAll'),fMore=document.getElementById('fileMore');
const pick=document.getElementById('pickAll'),addBtn=document.getElementById('addFile'),clrBtn=document.getElementById('clearFiles'),nameEl=document.getElementById('nameAll');
let bag=new DataTransfer();
function addFiles(list){for(const f of list) bag.items.add(f); fAll.files=bag.files; nameEl.textContent=[...bag.files].map(f=>f.name).join(', ')||'Belum ada file dipilih (bisa multi)';}
function clearFiles(){bag=new DataTransfer();fAll.value='';fAll.files=bag.files;nameEl.textContent='Belum ada file dipilih (bisa multi)';notes.innerHTML='';}
pick.onclick=()=>fAll.click();
fAll.onchange=()=>{if(fAll.files.length) addFiles(fAll.files);} ;
addBtn.onclick=()=>fMore.click();
fMore.onchange=()=>{if(fMore.files.length) addFiles(fMore.files); fMore.value='';};
clrBtn.onclick=clearFiles;

/* ===== Layout defaults (HEADER TETAP) ===== */
const DEFAULT_SUBSECTORS=['JUMLAH - Total','Laut - Non Pelabuhan','Perairan Umum - Open Water'];
function drawMatrixHeader(subCols){
  const r1=document.getElementById('hdrRow1'),r2=document.getElementById('hdrRow2'),r3=document.getElementById('hdrRow3');
  r1.innerHTML=`<th rowspan="3">KABUPATEN/KOTA<br><span class="subnote">(District)</span></th><th colspan="${subCols.length}">SUB SEKTOR PERIKANAN</th>`;
  r2.innerHTML=`<th colspan="${subCols.length}">PENANGKAPAN</th>`;
  r3.innerHTML=subCols.map((n,i)=>`<th class="${/jumlah/i.test(n)?'col-jumlah':''}">${n}</th>`).join('');
}
function setDefaultHeaders(){
  const monthHeads=['Uraian',...MONTHS,'Jumlah'];
  document.getElementById('theadVol').innerHTML=`<tr>${monthHeads.map((h,i)=>`<th class="${i===monthHeads.length-1?'col-jumlah':''}">${h}</th>`).join('')}</tr>`;
  document.getElementById('theadNil').innerHTML=`<tr>${monthHeads.map((h,i)=>`<th class="${i===monthHeads.length-1?'col-jumlah':''}">${h}</th>`).join('')}</tr>`;
  drawMatrixHeader(DEFAULT_SUBSECTORS);
}

/* ===== SAVE ke DB (dipakai saat render dari Excel) ===== */
async function saveRows(table,rows){
  try{
    if(!rows||!rows.length) return {ok:true,saved:0};
    setStatusProgress(`Menyimpan: ${table}…`);
    const res=await fetch('/api/save_rows.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({table,rows})});
    const out=await res.json();
    if(!out.ok) throw new Error(out.error||'Gagal simpan');
    flashSave(`TERSIMPAN (${table}) ✓`,'#059669',2500);
    return out;
  }catch(e){console.error(e);flashSave(`GAGAL SIMPAN (${table})`,'#dc2626',4000);return {ok:false,error:String(e)};}
}

/* ===== Util total ===== */
const num=v=>+String(v??'').replace(/[^\d.-]/g,'')||0;
const sumObj=(rows,keys)=>keys.reduce((a,k)=>(a[k]=(rows||[]).reduce((s,r)=>s+num(r[k]??0),0),a),{});

/* ===== Ringkasan ===== */
function renderRingkasan(rows){
  cache.ringkasan=rows.slice();
  const cols={cab:'CABANG USAHA',nel:'Nelayan (Orang)',rtp:'RTP/PP (Orang/Unit)',arm:'Armada Perikanan (Buah)',alat:'Alat Tangkap (Unit)',vol:'Volume (Ton)',val:'Nilai (Rp 1.000)'};
  const total=sumObj(rows,[cols.nel,cols.rtp,cols.arm,cols.alat,cols.vol,cols.val]);
  const mk=(r,top=false)=>`<tr class="${top?'row-total bold-first':''}">
    <td class="font-bold">${top?'JUMLAH':(r[cols.cab]||r['Cabang Usaha']||r['Uraian']||'')}</td>
    <td class="text-right">${fmt(top?total[cols.nel]:r[cols.nel]??r['Nelayan'])}</td>
    <td class="text-right">${fmt(top?total[cols.rtp]:r[cols.rtp]??r['RTP/PP']??r['RTP']??r['PP'])}</td>
    <td class="text-right">${fmt(top?total[cols.arm]:r[cols.arm]??r['Armada'])}</td>
    <td class="text-right">${fmt(top?total[cols.alat]:r[cols.alat]??r['Alat Tangkap'])}</td>
    <td class="text-right">${fmt(top?total[cols.vol]:r[cols.vol]??r['Ikan Segar (Ton)']??r['Ton'])}</td>
    <td class="text-right">${fmt(top?total[cols.val]:r[cols.val]??r['Nilai'])}</td></tr>`;
  const list=showAll.ringkasan?cache.ringkasan:cache.ringkasan.slice(0,LIMIT);
  document.getElementById('tbodyRingkasan').innerHTML=mk({},true)+list.map(r=>mk(r)).join('');
  applyRows('ringkasan',cache.ringkasan.length,'btnShowAllRingkasan','btnHideRingkasan');

  const tahun=getYear();
  saveRows('tangkap_ringkasan',rows.map(r=>({tahun,cabang_usaha:String(r[cols.cab]||r['Cabang Usaha']||r['Uraian']||''),nelayan_orang:num(r[cols.nel]??r['Nelayan']),rtp_pp:num(r[cols.rtp]??r['RTP/PP']??r['RTP']??r['PP']),armada_buah:num(r[cols.arm]??r['Armada']),alat_tangkap_unit:num(r[cols.alat]??r['Alat Tangkap']),volume_ton:num(r[cols.vol]??r['Ikan Segar (Ton)']??r['Ton']),nilai_rp_1000:num(r[cols.val]??r['Nilai'])})));
}
bindToggle('ringkasan','btnShowAllRingkasan','btnHideRingkasan',()=>renderRingkasan(cache.ringkasan));

/* ===== Matrix ===== */
function renderProduksiMatrix(subCols,rows){
  if(!subCols||!subCols.length) subCols=DEFAULT_SUBSECTORS;
  drawMatrixHeader(subCols);
  cache.matrix=rows.slice();
  const totals={}; subCols.forEach(s=>totals[s]=rows.reduce((a,r)=>a+num(r[s]),0));
  const topRow=`<tr class="row-total"><td class="font-bold">JUMLAH - Total</td>${subCols.map(s=>`<td class="text-right ${/jumlah/i.test(s)?'col-jumlah':''}">${fmt(totals[s])}</td>`).join('')}</tr>`;
  const list=showAll.matrix?cache.matrix:cache.matrix.slice(0,LIMIT);
  document.getElementById('tbodyMatrix').innerHTML=topRow+list.map(r=>`<tr><td>${r.Wilayah}</td>${subCols.map(s=>`<td class="text-right ${/jumlah/i.test(s)?'col-jumlah':''}">${fmt(r[s])}</td>`).join('')}</tr>`).join('');
  document.getElementById('tfootMatrix').innerHTML=`<tr class="row-total"><td class="font-bold">TOTAL</td>${subCols.map(s=>`<td class="text-right ${/jumlah/i.test(s)?'col-jumlah':''}">${fmt(totals[s])}</td>`).join('')}</tr>`;
  applyRows('matrix',cache.matrix.length,'btnShowAllMatrix','btnHideMatrix');

  const tahun=getYear(); const flat=[]; cache.matrix.forEach(r=>subCols.forEach(s=>flat.push({tahun,kab_kota:String(r.Wilayah),subsektor:s,volume_ton:num(r[s])}))); saveRows('tangkap_produksi_matrix',flat);
}
bindToggle('matrix','btnShowAllMatrix','btnHideMatrix',()=>renderProduksiMatrix(DEFAULT_SUBSECTORS,cache.matrix));

/* ===== Bulanan ===== */
const monthOrder=['Uraian',...MONTHS,'Jumlah'];
function renderMonthly(kind,rows,tfId,tbId){
  cache[kind]=rows.slice();
  const list=showAll[kind]?cache[kind]:cache[kind].slice(0,LIMIT);
  document.getElementById(tbId).innerHTML=list.map(r=>{
    const jumlah=MONTHS.reduce((a,m)=>a+(parseNumID(r[m]||r[m?.toLowerCase()])||0),0);
    const tds=monthOrder.map((h,i)=>{
      if(h==='Uraian') return `<td class="font-bold">${r[h]??r[h?.toLowerCase()]??''}</td>`;
      if(i===monthOrder.length-1) return `<td class="text-right col-jumlah">${fmt(jumlah)}</td>`;
      const val = r[h]??r[h?.toLowerCase()];
      return `<td class="text-right">${fmt(val)}</td>`;
    });
    return `<tr>${tds.join('')}</tr>`;
  }).join('');
  const totals={}; MONTHS.forEach(m=>totals[m]=(rows||[]).reduce((a,r)=>a+(parseNumID(r[m]||r[m?.toLowerCase()])||0),0));
  const totalJumlah=MONTHS.reduce((a,m)=>a+(totals[m]||0),0);
  document.getElementById(tfId).innerHTML=`<tr class="row-total"><td class="font-bold">TOTAL</td>${MONTHS.map(m=>`<td class="text-right">${fmt(totals[m])}</td>`).join('')}<td class="text-right col-jumlah">${fmt(totalJumlah)}</td></tr>`;
  applyRows(kind,cache[kind].length,kind==='vol'?'btnShowAllVol':'btnShowAllNil',kind==='vol'?'btnHideVol':'btnHideNil');
}
const renderVolumeBulanan=(c,r)=>{renderMonthly('vol',r,'tfootVol','tbodyVol');const tahun=getYear();saveRows('tangkap_volume_bulanan',r.map(x=>{const o={tahun,uraian:String(x['Uraian']||'')};MONTHS.forEach(m=>o[m.toLowerCase()]=parseNumID(x[m]||x[m?.toLowerCase()])||0);o.jumlah=MONTHS.reduce((a,m)=>a+(parseNumID(x[m]||x[m?.toLowerCase()])||0),0);return o;}));}
const renderNilaiBulanan =(c,r)=>{renderMonthly('nil',r,'tfootNil','tbodyNil');const tahun=getYear();saveRows('tangkap_nilai_bulanan',r.map(x=>{const o={tahun,uraian:String(x['Uraian']||'')};MONTHS.forEach(m=>o[m.toLowerCase()]=parseNumID(x[m]||x[m?.toLowerCase()])||0);o.jumlah=MONTHS.reduce((a,m)=>a+(parseNumID(x[m]||x[m?.toLowerCase()])||0),0);return o;}));}
bindToggle('vol','btnShowAllVol','btnHideVol',()=>renderMonthly('vol',cache.vol,'tfootVol','tbodyVol'));
bindToggle('nil','btnShowAllNil','btnHideNil',()=>renderMonthly('nil',cache.nil,'tfootNil','tbodyNil'));

/* ===== Komoditas (parent–child & semua format) ===== */
function normalizeKomoditasRows(rows){
  if(!Array.isArray(rows)) return { groups: [] };

  // FORMAT DB
  if('no' in (rows[0]||{}) && 'komoditas' in (rows[0]||{}) && ('volume' in (rows[0]||{}) || 'Volume' in (rows[0]||{})) && 'is_sub' in (rows[0]||{})){
    const parents = new Map();
    const groups  = [];
    let lastParent = null;

    const safeNum = v => { const n = parseFloat(String(v).replace(/[^\d.-]/g,'')); return Number.isFinite(n) ? n : null; };

    const sorted = rows.slice().sort((a,b)=>{
      const ai = Number(a.is_sub||0), bi = Number(b.is_sub||0);
      if(ai!==bi) return ai-bi;
      const an = safeNum(a.no), bn = safeNum(b.no);
      if(an===null && bn!==null) return 1;
      if(an!==null && bn===null) return -1;
      if(an!==null && bn!==null && an!==bn) return an-bn;
      return String(a.komoditas||'').localeCompare(String(b.komoditas||''));
    });

    sorted.forEach(r=>{
      const isSub = Number(r.is_sub||0)===1;
      const vol   = parseNumID(r.volume ?? r.Volume);
      const noVal = safeNum(r.no);
      const label = String(r.komoditas||'').trim();

      if(!isSub){
        const g = { no: noVal, komoditas: label, vol: vol, items: [] };
        groups.push(g);
        lastParent = g;
        if(noVal!==null) parents.set(noVal, g);
      }else{
        let target = null;
        if(noVal!==null && parents.has(noVal)) target = parents.get(noVal);
        else target = lastParent;
        if(!target){
          target = { no: null, komoditas: '', vol: 0, items: [] };
          groups.push(target);
          lastParent = target;
        }
        target.items.push({ label, vol });
      }
    });

    return { groups };
  }

  // FORMAT EXCEL FLAT (UPPERCASE = parent)
  const get = (r,k) => r[k] ?? r[k?.toLowerCase()];
  const isUpper = s => {
    const letters=String(s||'').replace(/[^A-Za-z\u00C0-\u024F\u1E00-\u1EFF]/g,'');
    return letters && letters === letters.toUpperCase();
  };

  const groups=[];
  let current=null;

  (rows||[]).forEach(r=>{
    const no  = parseNumID(get(r,'No'));
    const kom = String(get(r,'Komoditas') ?? '').trim();
    const vol = parseNumID(get(r,'Volume (Ton)') ?? get(r,'Volume'));

    if(!kom) return;

    if(Number.isFinite(no) || isUpper(kom)){
      current = { no: Number.isFinite(no)? no : null, komoditas: kom, vol: vol, items: [] };
      groups.push(current);
    }else if(current){
      current.items.push({ label: kom, vol: vol });
    }else{
      current = { no: null, komoditas: kom, vol: vol, items: [] };
      groups.push(current);
    }
  });

  return { groups: groups.filter(g => String(g.komoditas).trim()!=='') };
}

function renderKomoditas(rows){
  const { groups } = normalizeKomoditasRows(rows);
  cache.kom = groups.slice();

  const list = showAll.kom ? cache.kom : cache.kom.slice(0, LIMIT);

  let html = '';
  list.forEach(g=>{
    html += `<tr class="row-komo">
      <td class="text-right">${g.no==null?'':fmt(g.no)}</td>
      <td class="font-bold">${g.komoditas}</td>
      <td class="text-right">${g.vol==null||g.vol===undefined? '' : fmt(g.vol)}</td>
    </tr>`;
    (g.items||[]).forEach(it=>{
      html += `<tr class="row-child">
        <td></td>
        <td>${it.label}</td>
        <td class="text-right">${it.vol==null||it.vol===undefined? '' : fmt(it.vol)}</td>
      </tr>`;
    });
  });

  document.getElementById('tbodyKomoditas').innerHTML =
    html || '<tr><td colspan="3">Belum ada data.</td></tr>';

  applyRows('kom', cache.kom.length, 'btnShowAllKom', 'btnHideKom');

  const tahun = getYear();
  if (rows.length && !('is_sub' in (rows[0]||{}))) {
    const payload = [];
    cache.kom.forEach(g => {
      if (String(g.komoditas).trim() !== '') {
        payload.push({
          tahun,
          no: g.no,
          komoditas: g.komoditas,
          volume: (g.vol==null||g.vol===undefined) ? '' : String(g.vol),
          is_sub: 0
        });
        (g.items||[]).forEach(it => {
          payload.push({
            tahun,
            no: g.no,
            komoditas: it.label,
            volume: (it.vol==null||it.vol===undefined) ? '' : String(it.vol),
            is_sub: 1
          });
        });
      }
    });
    if (payload.length) saveRows('tangkap_komoditas', payload);
  }
}
bindToggle('kom','btnShowAllKom','btnHideKom',()=>renderKomoditas([].concat(...cache.kom.map(g=>[
  {No:g.no,Komoditas:g.komoditas,'Volume (Ton)':g.vol},
  ...(g.items||[]).map(it=>({No:'',Komoditas:it.label,'Volume (Ton)':it.vol}))
]))));

/* ===== Excel utils ===== */
function readXlsx(f){return new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>{try{res(XLSX.read(e.target.result,{type:'array'}));}catch(err){rej(err)}};r.onerror=rej;r.readAsArrayBuffer(f);});}
function sheetToRows(ws){
  const aoa=XLSX.utils.sheet_to_json(ws,{header:1,raw:true,defval:''});
  if(!aoa.length) return {cols:[],data:[]};
  let hdr=0;for(let i=0;i<Math.min(10,aoa.length);i++){const filled=(aoa[i]||[]).filter(x=>String(x).trim()!=='').length;if(filled>=2){hdr=i;break;}}
  const cols=(aoa[hdr]||[]).map(h=>String(h||'').trim());
  const data=aoa.slice(hdr+1).map(r=>{const o={};cols.forEach((h,i)=>o[h]=r?.[i]??'');return o;});
  return {cols,data};
}
const norm=s=>String(s??'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim();
function guessTemplate(cols,sheet='',file='',ws=null){
  const n=cols.map(norm),has=k=>n.some(c=>c.includes(k));
  const monthHit=['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'].filter(m=>has(m)).length;
  let gridSaysNilai=false;
  if(ws&&ws['!ref']){const r=XLSX.utils.decode_range(ws['!ref']);const rMax=Math.min(r.s.r+9,r.e.r),cMax=Math.min(r.s.c+9,r.e.c);
    outer:for(let rr=r.s.r;rr<=rMax;rr++){for(let cc=r.s.c;cc<=cMax;cc++){const v=ws[XLSX.utils.encode_cell({r:rr,c:cc})]?.v;if(v&&/nilai|rp|rupiah|1\.?000/i.test(String(v))){gridSaysNilai=true;break outer;}}}}
  const isRing=(has('cabang')||has('usaha'))&&has('nelayan')&&(has('rtp')||has('pp'))&&has('armada')&&has('alat tangkap')&&has('volume')&&(has('nilai')||gridSaysNilai);
  const isMatrix=(has('kab')||has('kota')||has('district'))&&(has('jumlah')||has('total'))&&(has('laut')||has('perairan umum')||has('open water'));
  const isMonthly=(has('uraian')||has('wadah'))&&monthHit>=6;
  const looksNilai=gridSaysNilai||n.join(' ').includes('nilai');
  const isKom=has('komoditas')&&(has('volume')||has('ton'));
  if(isRing) return 'ringkasan';
  if(isMatrix) return 'matrix';
  if(isMonthly&&looksNilai) return 'nilai_bulanan';
  if(isMonthly) return 'volume_bulanan';
  if(isKom) return 'komoditas';
  return 'unknown';
}

/* ===== Parse & Render driver ===== */
function parseSubsektorMatrix(ws){
  const A=XLSX.utils.sheet_to_json(ws,{header:1,raw:true,defval:''}); if(!A.length) return null;
  const n=s=>String(s||'').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/\s+/g,' ').trim();
  let cWil=-1,rHdr=-1;
  outer:for(let r=0;r<Math.min(A.length,60);r++){const row=A[r]||[];for(let c=0;c<row.length;c++){const t=n(row[c]);if(t==='kabupaten/kota'||t==='district'||/^kab(?:\.|upaten)?\s*\/?>?\s*kota$/.test(t)){cWil=c;rHdr=r;break outer;}}}
  if(cWil<0) return null;
  let r3=-1;
  for(let r=rHdr;r<Math.min(rHdr+10,A.length);r++){
    const row=(A[r]||[]).map(n);
    if(row.some(x=>/^jumlah/.test(x))&&(row.some(x=>/^laut|non pelabuhan/.test(x))||row.some(x=>/^perairan umum|open water/.test(x)))){r3=r;break;}
  }
  if(r3<0) return null;
  const raw=(A[r3]||[]).map(x=>String(x||'').trim()), subs=[];
  for(let c=cWil+1;c<raw.length;c++){let name=raw[c]||''; name=name.replace(/^\s*sub\s*sektor.*-\s*|^\s*penangkapan\s*-\s*/i,'').trim(); if(name) subs.push({idx:c,name});}
  const rows=[];
  for(let r=r3+1;r<A.length;r++){
    const wil=String((A[r]||[])[cWil]||'').trim(); if(!wil) continue;
    const rec={Wilayah:wil}; let any=false;
    subs.forEach(s=>{const v=(A[r]||[])[s.idx]; const nV = parseNumID(v); if(nV!==null){rec[s.name]=nV; any=true;}});
    if(any) rows.push(rec);
  }
  return rows.length?{subsectors:subs.map(s=>s.name),rows}:null;
}
function guessAndRender(ws,fileName,matrixRenderedRef,processed){
  const {cols,data}=sheetToRows(ws); const rows=data.filter(r=>Object.values(r).some(v=>String(v).trim()!==''));
  if(!rows.length) return false;
  const type=guessTemplate(cols,ws?.name||'',fileName||'',ws);
  if(!matrixRenderedRef.done){const m=parseSubsektorMatrix(ws); if(m&&m.rows?.length){renderProduksiMatrix(m.subsectors,m.rows); matrixRenderedRef.done=true; processed.push('tangkap_produksi_matrix'); return true;}}
  if(type==='ringkasan'){renderRingkasan(rows); processed.push('tangkap_ringkasan'); return true;}
  if(type==='volume_bulanan'){renderVolumeBulanan(cols,rows); processed.push('tangkap_volume_bulanan'); return true;}
  if(type==='nilai_bulanan'){renderNilaiBulanan(cols,rows); processed.push('tangkap_nilai_bulanan'); return true;}
  if(type==='komoditas'){renderKomoditas(rows); processed.push('tangkap_komoditas'); return true;}
  return false;
}

/* ===== Upload ===== */
document.getElementById('btnUpload').onclick=async()=>{
  const tahun=getYear();
  if(!isValidYear(tahun)){flashSave('Tahun wajib 4 digit (2000–2100).','#dc2626');flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626');return;}
  ['tahunRingkasan','tahunProduksi','tahunVol','tahunNil','tahunKomoditas'].forEach(id=>document.getElementById(id).textContent=tahun);
  if(bag.files.length===0){flashSave('Pilih minimal satu file.','#dc2626');return;}
  setDefaultHeaders(); setStatusProgress('Memproses file...'); notes.innerHTML='';
  const matrixFlag={done:false}; const processed=[]; let handled=0,unknown=0;
  for(const f of bag.files){
    try{const wb=await readXlsx(f); wb.SheetNames.forEach(n=>{const ws=wb.Sheets[n]; if(!ws||!ws['!ref']) return; ws.name=n; const ok=guessAndRender(ws,f.name,matrixFlag,processed); if(ok) handled++; else unknown++;});}
    catch(e){console.error('Gagal membaca',f.name,e); note('NOTED: Gagal membaca '+f.name, false);}
  }
  setStatusProgress('');
  if(!handled){flashSave('Gagal upload: Template tidak dikenali / tidak sesuai.','#dc2626',5000);return;}
  flashSave(`Data tahun ${tahun} diproses ✓ (${[...new Set(processed)].join(', ')})`,'#059669');
  if(unknown>0){flashYear(`${unknown} sheet di-skip (template tidak sesuai).`,'#f59e0b');}
  clearFiles(); if(tahun){loadFromDB(tahun);} 
};

/* ===== Reset ===== */
document.getElementById('btnReset').onclick=()=>{
  clearFiles();
  ['tbodyRingkasan','tbodyMatrix','tfootMatrix','tbodyVol','tfootVol','tbodyNil','tfootNil','tbodyKomoditas'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML='';});
  setDefaultHeaders(); const st=document.getElementById('statusSave'); if(st) st.textContent=''; setGlobal(false,'');
};

/* ===== Templates ===== */
function wbDownload(name,sheets){const wb=XLSX.utils.book_new(); sheets.forEach(s=>{const ws=XLSX.utils.aoa_to_sheet(s.aoa);if(s.widths){ws['!cols']=s.widths.map(w=>({wch:w}));} XLSX.utils.book_append_sheet(wb,ws,s.name);}); XLSX.writeFile(wb,name);} 
const dRing=()=>({name:'Ringkasan',widths:[30,18,20,22,19,16,18],aoa:[['CABANG USAHA','Nelayan (Orang)','RTP/PP (Orang/Unit)','Armada Perikanan (Buah)','Alat Tangkap (Unit)','Volume (Ton)','Nilai (Rp 1.000)']]});
const dMatrix=()=>({name:'Matrix',widths:[28,18,24,22],aoa:[['KABUPATEN/KOTA','','SUB SEKTOR PERIKANAN'],['District','','PENANGKAPAN'],['','JUMLAH - Total','Laut - Non Pelabuhan','Perairan Umum - Open Water']]});
const dVol=()=>({name:'Volume per Bulan',widths:[24,14,14,14,14,14,14,14,14,14,14,14,14,14],aoa:[['Uraian',...MONTHS,'Jumlah']]});
const dNil=()=>({name:'Nilai per Bulan',widths:[24,12,12,12,12,12,12,12,12,16,16,16,16,16],aoa:[['Uraian',...MONTHS,'Jumlah']]});
const dKom=()=>({name:'Komoditas Unggulan',widths:[6,36,16],aoa:[['No','Komoditas','Volume (Ton)']]});
function downloadTpl(k){const m={ringkasan:{name:'Ringkasan.xlsx',sheets:[dRing()]},matrix:{name:'PRODUKSI TANGKAP (MATRIX).xlsx',sheets:[dMatrix()]},volume:{name:'Volume per Bulan per Wadah.xlsx',sheets:[dVol()]},nilai:{name:'Nilai per Bulan per Wadah.xlsx',sheets:[dNil()]},komoditas:{name:'Komoditas Unggulan.xlsx',sheets:[dKom()]}}[k]; if(m) wbDownload(m.name,m.sheets);} 
document.querySelectorAll('[data-tpl]').forEach(b=>b.onclick=()=>downloadTpl(b.dataset.tpl));
document.getElementById('btnDownloadTemplates').onclick=()=>['ringkasan','matrix','volume','nilai','komoditas'].forEach((k,i)=>setTimeout(()=>downloadTpl(k),i*150));

/* ===== Fetch from DB ===== */
async function loadFromDB(year){
  try{
    const r=await fetch(`/api/tangkap_fetch.php?tahun=${encodeURIComponent(year)}&_=${Date.now()}`); const p=await r.json();
    setDefaultHeaders();
    const hasRing = Array.isArray(p.ringkasan)&&p.ringkasan.length;
    const hasMat  = p.matrix && p.matrix.rows && p.matrix.rows.length;
    const hasVol  = Array.isArray(p.volume_bulanan)&&p.volume_bulanan.length;
    const hasNil  = Array.isArray(p.nilai_bulanan)&&p.nilai_bulanan.length;
    const hasKom  = Array.isArray(p.komoditas)&&p.komoditas.length;

    if(hasRing) renderRingkasan(p.ringkasan);
    if(hasMat)  renderProduksiMatrix(p.matrix.subsectors||DEFAULT_SUBSECTORS,p.matrix.rows);
    if(hasVol)  renderVolumeBulanan(null,p.volume_bulanan);
    if(hasNil)  renderNilaiBulanan(null,p.nilai_bulanan);
    if(hasKom)  renderKomoditas(p.komoditas); // pastikan API kirim kolom is_sub

    const any = hasRing||hasMat||hasVol||hasNil||hasKom;
    setGlobal(!!any, year);
    flashYear(any ? 'Data tersedia ✓' : 'Tidak ada data.', any?'#059669':'#dc2626', 2500);
  }catch(e){console.error(e);setGlobal(false, year);flashYear(`Error mengambil data tahun ${year}`,'#dc2626');}
}

/* ===== Tahun & Dashboard ===== */
function setYearState(){
  const t=getYear(), ok=isValidYear(t);
  ['tahunRingkasan','tahunProduksi','tahunVol','tahunNil','tahunKomoditas'].forEach(id=>{const el=document.getElementById(id); if(el) el.textContent=ok?t:'-';});
  pick.disabled=!ok; addBtn.disabled=!ok; document.getElementById('btnUpload').disabled=!ok; clrBtn.disabled=false;
  if(!ok){ setGlobal(false,''); }
}
document.addEventListener('DOMContentLoaded',()=>{
  const y=document.getElementById('tahun'); if(y) y.value='';
  setYearState(); setDefaultHeaders();
});
const yearInput=document.getElementById('tahun');
function triggerLoad(){const y=getYear(); if(!isValidYear(y)){setYearState(); setGlobal(false,''); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return;} setYearState(); loadFromDB(y);} 
yearInput.addEventListener('input',()=>{clearTimeout(window.__yd); window.__yd=setTimeout(triggerLoad,250);});
yearInput.addEventListener('change',triggerLoad);
yearInput.addEventListener('blur',triggerLoad);
yearInput.addEventListener('keydown',(e)=>{if(e.key==='Enter'){e.preventDefault();triggerLoad();}});
document.getElementById('btnYearReset').onclick=()=>{const y=document.getElementById('tahun'); if(y) y.value=''; try{clearTimeout(window.__yd);}catch(_){} setYearState(); const sy=document.getElementById('statusYear'); if(sy){sy.textContent=''; sy.removeAttribute('style');} setGlobal(false,'');};

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
    try{ parent.postMessage({type:'resizeFrame', height:h}, location.origin); }catch(_){}}
  ;
  window.addEventListener('load', postH);
  new ResizeObserver(postH).observe(document.body);
  setTimeout(postH,200); setTimeout(postH,800);
})();
</script>

<?php end_protected_page(); ?>
