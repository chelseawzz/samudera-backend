<?php
// ekspor-perikanan.php — Upload + Viewer (khusus admin)
require_once __DIR__.'/auth_guard.php';  // 🔐 cek login & status admin
require_login();                         // wajib login dulu
require_admin();                         // hanya admin boleh

require_once __DIR__.'/protected_template.php';
start_protected_page('Ekspor Perikanan','ekspor');
?>
<style>
:root{--toska:#0097a7;--head:#0b1b2b;--line:#e2e8f0}
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
.icon-btn:hover{background:#f1fefe}.icon-btn[disabled],.btn-primary[disabled],.btn-ghost[disabled],.filebox .pick[disabled]{opacity:.5;cursor:not-allowed}
.card{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 2px 6px rgba(2,8,20,.04)}.card-h{padding:6px 10px;border-bottom:1px solid var(--line);font-weight:700}
.card-b{padding:8px 10px}
.panel-grid{display:grid;grid-template-columns:1fr;gap:20px}
@media (min-width:1024px){.panel-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
table{border-collapse:collapse;width:100%}
th,td{border:1px solid #e2e8f0;padding:.5rem .75rem;font-size:.875rem!important;line-height:1.25rem!important;background:#fff}
th{font-weight:700}
thead.bg-samudera th{background:#0b1b2b!important;color:#fff!important;border-color:#fff!important;font-weight:700!important;font-size:.875rem!important;letter-spacing:0!important;white-space:nowrap}
tr.row-total td{font-weight:700;background:#f8fafc}
.sec-head{display:flex;align-items:center;justify-content:space-between}
.small-actions{display:flex;gap:.4rem;align-items:center}
.subnote{font-size:.78rem;color:#64748b;font-style:italic}
.note-err{color:#dc2626;font-size:.85rem}.note-ok{color:#059669;font-size:.85rem}.badge-ok{font-size:.75rem;margin-left:.25rem;color:#059669}

/* Ring negara/komoditas */
.row-country td{background:#f1f5f9!important;border-top:2px solid #cbd5e1!important;font-weight:700}
.row-country.alt td{background:#e9f3f6!important}
.row-child td{padding-left:24px}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

<h1 class="text-2xl font-bold mb-2">Ekspor Perikanan</h1>
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
          <label class="field-label mb-2 block">Upload File Ekspor (.xlsx/.xls)</label>
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
          <button class="btn-ghost btn-xs w-full" data-tpl="total_vol" type="button">TOTAL VOLUME.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="total_usd" type="button">TOTAL NILAI.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="ring"  type="button">Ekspor Perikanan.xlsx</button>
          <button id="btnDownloadTemplates" class="btn-ghost btn-xs w-full" type="button"><i class="fa-solid fa-download mr-1"></i> Download Semua (3)</button>
        </div>
      </div>
    </aside>
  </div>
</section>

<!-- TOTAL EKSPOR — VOLUME -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">TOTAL EKSPOR — VOLUME</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunTotalVol">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllVol" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideVol" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr><th>No</th><th>Komoditas</th><th>Volume / Ton</th></tr>
      </thead>
      <tbody id="tbodyTotalVol"><tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootTotalVol"></tfoot>
    </table>
  </div>
</section>

<!-- TOTAL EKSPOR — NILAI -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">TOTAL EKSPOR — NILAI</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunTotalUsd">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllUsd" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideUsd" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr><th>No</th><th>Komoditas</th><th>Nilai (USD)</th></tr>
      </thead>
      <tbody id="tbodyTotalUsd"><tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootTotalUsd"></tfoot>
    </table>
  </div>
</section>

<!-- KOMODITAS UTAMA -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Komoditas Utama Ekspor (Top 10)</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunUtama">-</span></p>
      <p class="subnote">Diambil otomatis dari daftar TOTAL (Top 10 volume &amp; Top 10 nilai).</p>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr>
          <th>No</th><th>Komoditas</th><th>Volume / Ton</th>
          <th>No</th><th>Komoditas</th><th>Nilai (USD)</th>
        </tr>
      </thead>
      <tbody id="tbodyUtama"><tr><td colspan="6">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootUtama"></tfoot>
    </table>
  </div>
</section>

<!-- RINGKASAN NEGARA -->
<section class="bg-white rounded-2xl shadow">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Ekspor Perikanan</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunRing">-</span></p>
      <p class="subnote">Baris <b>huruf BESAR</b> = <b>Negara (total)</b>; baris <i>huruf kecil</i> = komoditas. Urutan mengikuti Excel (tidak diurut abjad).</p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllRing" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideRing" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr><th>No</th><th>Row Labels</th><th>Sum of JUMLAH (Ton)</th><th>Sum of NILAI (USD)</th></tr>
      </thead>
      <tbody id="tbodyRing"><tr><td colspan="4">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootRing"></tfoot>
    </table>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
/* ====== State & helpers ====== */
const YEAR_MIN=2000, YEAR_MAX=2100;
const MAX_INITIAL_ROWS = 10;
let YEAR_LOAD_SEQ=0;

let VOL_CACHE=[], USD_CACHE=[], RING_CACHE=[];
let VOL_SHOW_ALL=false, USD_SHOW_ALL=false, RING_SHOW_ALL=false;
let RING_ANCHOR_INDEX=0; // anchor negara agar ShowAll/Hide tetap start di negara yg sama

const notes=document.getElementById('notes');
function note(msg, ok=false){notes.innerHTML=`<div class="${ok?'note-ok':'note-err'}">${msg}</div>`;}
let __yt,__st;
function flashYear(msg,color="#0ea5e9",ms=3500){const el=document.getElementById('statusYear');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__yt);__yt=setTimeout(()=>{el.textContent='';},ms);}
function flashSave(msg,color="#059669",ms=4000){const el=document.getElementById('statusSave');if(!el)return;el.textContent=msg;el.style.color=color;clearTimeout(__st);__st=setTimeout(()=>{el.textContent='';},ms);}
function setStatusProgress(text,color='#64748b'){const el=document.getElementById('statusSave');if(!el)return;el.textContent=text;el.style.color=color;}
function setGlobal(ok, tahun){
  const el=document.getElementById('globalInfo');
  if(!el) return;
  if(!tahun || !/^\d{4}$/.test(tahun)){ el.innerHTML=''; return; }
  el.innerHTML = ok
    ? `<span class="badge-ok">Data tersedia untuk tahun ${tahun} ✓</span>`
    : `<span class="note-err">NOTED: Belum ada data untuk tahun ${tahun}.</span>`;
}
const nf=new Intl.NumberFormat('id-ID',{maximumFractionDigits:2,minimumFractionDigits:0});
const fmt=v=>{if(v===null||v===undefined||String(v).trim()==='')return'';const n=Number(String(v).replace(/[^\d.-]/g,''));return isFinite(n)?nf.format(n):v;};
function parseNumID(v){
  if(v===null||v===undefined) return null;
  if(typeof v==='number' && Number.isFinite(v)) return v;
  let s=String(v).trim(); if(s==='') return null;
  s=s.replace(/\s+/g,'');
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
const getYear=()=> (document.getElementById('tahun')?.value||'').trim();
const isValidYear=y=>/^\d{4}$/.test(String(y))&&+y>=YEAR_MIN&&+y<=YEAR_MAX;
const withLimit=(arr, showAll)=> showAll ? arr : arr.slice(0, MAX_INITIAL_ROWS);
function toggleVis(kind, total, limit){
  const showBtn=document.getElementById('btnShowAll'+kind);
  const hideBtn=document.getElementById('btnHide'+kind);
  if(total>limit){ showBtn.style.display='inline-flex'; hideBtn.style.display='none'; }
  else { showBtn.style.display='none'; hideBtn.style.display='none'; }
}

/* File bag */
const fAll=document.getElementById('fileAll'),fMore=document.getElementById('fileMore');
const pick=document.getElementById('pickAll'),addBtn=document.getElementById('addFile'),clrBtn=document.getElementById('clearFiles'),nameEl=document.getElementById('nameAll');
let bag=new DataTransfer();
function addFiles(list){for(const f of list) bag.items.add(f); fAll.files=bag.files; nameEl.textContent=[...bag.files].map(f=>f.name).join(', ')||'Belum ada file dipilih (bisa multi)';}
function clearFiles(){bag=new DataTransfer();fAll.value='';fAll.files=bag.files;nameEl.textContent='Belum ada file dipilih (bisa multi)';notes.innerHTML='';}
pick.onclick=()=>fAll.click(); fAll.onchange=()=>{if(fAll.files.length) addFiles(fAll.files);};
addBtn.onclick=()=>fMore.click(); fMore.onchange=()=>{if(fMore.files.length) addFiles(fMore.files); fMore.value='';};
clrBtn.onclick=clearFiles;

/* Tahun state */
function clearViews(){
  document.getElementById('tbodyTotalVol').innerHTML='<tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tfootTotalVol').innerHTML='';
  document.getElementById('tbodyTotalUsd').innerHTML='<tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tfootTotalUsd').innerHTML='';
  document.getElementById('tbodyUtama').innerHTML='<tr><td colspan="6">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tfootUtama').innerHTML='';
  document.getElementById('tbodyRing').innerHTML='<tr><td colspan="4">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tfootRing').innerHTML='';
  VOL_CACHE=[];USD_CACHE=[];RING_CACHE=[];VOL_SHOW_ALL=false;USD_SHOW_ALL=false;RING_SHOW_ALL=false;
  ['Vol','Usd','Ring'].forEach(k=>{ const s=document.getElementById('btnShowAll'+k), h=document.getElementById('btnHide'+k); if(s) s.style.display='none'; if(h) h.style.display='none'; });
}
function setYearState(){
  const y=getYear(), ok=isValidYear(y);
  ['tahunTotalVol','tahunTotalUsd','tahunUtama','tahunRing'].forEach(id=>{const el=document.getElementById(id); if(el) el.textContent=ok?y:'-';});
  pick.disabled=!ok; addBtn.disabled=!ok; clrBtn.disabled=false;
  const btnUpload=document.getElementById('btnUpload'); if(btnUpload) btnUpload.disabled=!ok;
  if(!ok){clearViews(); setGlobal(false,'');}
}
document.addEventListener('DOMContentLoaded',()=>{const y=document.getElementById('tahun'); if(y) y.value=''; setYearState();});

/* XLSX utils */
function readXlsx(f){return new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>{try{res(XLSX.read(e.target.result,{type:'array'}));}catch(err){rej(err)}};r.onerror=rej;r.readAsArrayBuffer(f);});}
function sheetToAoA(ws){return XLSX.utils.sheet_to_json(ws,{header:1,raw:true,defval:''});}

/* SAVE helper — path RELATIF */
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
  }catch(e){ console.warn('saveRows:', e); return {ok:false,error:String(e)}; }
}
async function wipeYearEkspor(tahun){
  try{
    await fetch('api/wipe_year.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ tahun:+tahun, tables:['ekspor_perikanan_total','ekspor_perikanan_utama','ekspor_perikanan_ringkasan'] })
    });
  }catch(e){ console.warn('wipeYearEkspor gagal:', e); }
}

/* ====== PARSER ====== */
function findHeader3(aoa, mode){ // mode: 'VOL' | 'USD'
  for (let i=0;i<aoa.length;i++){
    const rU=(aoa[i]||[]).map(x=>String(x??'').trim().toUpperCase());
    const jNo  = rU.indexOf('NO');
    const jKom = rU.indexOf('KOMODITAS', jNo>=0?jNo:0);
    const jVal = rU.findIndex((c,idx)=> idx>jKom && (
      mode==='VOL' ? (c.includes('VOLUME')||c.includes('VOLUME / TON')||c.includes('TON')||c.includes('JUMLAH'))
                   : (c.includes('USD')||c.includes('NILAI')||c.includes('$'))
    ));
    if (jNo>=0 && jKom>=0 && jVal>=0) return {row:i,jNo,jKom,jVal};
  }
  return null;
}
function scanRowsSimple(aoa, startRow, jNo, jKom, jVal){
  const out=[]; const clean = s => String(s??'').replace(/\u00A0/g,' ').trim();
  let seq=0;
  for (let i=startRow;i<aoa.length;i++){
    const row = aoa[i]||[];
    const kom = clean(row[jKom] ?? '');
    const val = parseNumID(row[jVal]);
    const isHdr = (String(row[jNo] ?? '').toUpperCase()==='NO') || (kom.toUpperCase()==='KOMODITAS');
    if (isHdr) continue;
    const blank = kom==='' && (val===null || Number.isNaN(val));
    if (blank) continue;
    if (/^TOTAL$/i.test(kom) || /^GRAND\s*TOTAL$/i.test(kom)) continue;
    out.push({ no: ++seq, komoditas: kom, angka: val });
  }
  return out;
}
function parse_TOTAL_SINGLE(aoa, mode){ const hdr=findHeader3(aoa, mode); return hdr ? scanRowsSimple(aoa, hdr.row+1, hdr.jNo, hdr.jKom, hdr.jVal) : []; }

/* deteksi tipe sheet ketat: header > nama sheet */
function detectSheetType(aoa, sheetName){
  const up = String(sheetName||'').trim().toUpperCase();
  const hasVOL = !!findHeader3(aoa,'VOL');
  const hasUSD = !!findHeader3(aoa,'USD');
  if (hasVOL && !hasUSD) return 'VOL';
  if (hasUSD && !hasVOL) return 'USD';
  const nameVOL = (up.includes('TOTAL') && (up.includes('VOLUME') || up.includes('TON'))) || up==='TOTAL VOLUME';
  const nameUSD = (up.includes('TOTAL') && (up.includes('NILAI')  || up.includes('USD') || up.includes('$'))) || up==='TOTAL NILAI';
  if (nameVOL && !nameUSD) return 'VOL';
  if (nameUSD && !nameVOL) return 'USD';
  return 'UNKNOWN';
}

/* UTAMA */
function parse_UTAMA_from_lists(volList, usdList){
  const topVol = (volList||[]).slice().sort((a,b)=>(b.angka||0)-(a.angka||0)).slice(0,10).map((r,i)=>({no:i+1,komoditas:r.komoditas,angka:r.angka,sisi:'VOL'}));
  const topUsd = (usdList||[]).slice().sort((a,b)=>(b.angka||0)-(a.angka||0)).slice(0,10).map((r,i)=>({no:i+1,komoditas:r.komoditas,angka:r.angka,sisi:'USD'}));
  return {vol: topVol, usd: topUsd};
}

/* ====== RENDERER ====== */
function render_TOTAL_single(list, target, which){ // which: 'Vol' | 'Usd'
  const tb=document.getElementById(`tbody${target}`), tf=document.getElementById(`tfoot${target}`);
  const cacheFlag = which==='Vol' ? 'VOL' : 'USD';
  if(cacheFlag==='VOL'){ VOL_CACHE=list.slice(); VOL_SHOW_ALL=false; }
  else { USD_CACHE=list.slice(); USD_SHOW_ALL=false; }

  const all = cacheFlag==='VOL' ? VOL_CACHE : USD_CACHE;
  const show = withLimit(all, cacheFlag==='VOL'?VOL_SHOW_ALL:USD_SHOW_ALL);

  if(!all.length){ tb.innerHTML='<tr><td colspan="3">Belum ada data.</td></tr>'; tf.innerHTML=''; toggleVis(which,0,0); return; }

  let rowsHtml=''; let sum=0;
  for(let i=0;i<show.length;i++){
    const r=show[i]||{};
    sum+=(parseNumID(r.angka)||0);
    rowsHtml+=`<tr>
      <td class="text-right">${r.no??(i+1)}</td>
      <td>${r.komoditas||''}</td>
      <td class="text-right">${fmt(r.angka)}</td>
    </tr>`;
  }
  tb.innerHTML=rowsHtml;
  const grand = all.reduce((a,x)=>a+(parseNumID(x.angka)||0),0);
  tf.innerHTML=`<tr class="row-total"><td></td><td class="text-right">TOTAL</td><td class="text-right">${fmt(grand)}</td></tr>`;

  toggleVis(which, all.length, MAX_INITIAL_ROWS);
}
document.getElementById('btnShowAllVol').onclick=()=>{ VOL_SHOW_ALL=true; render_TOTAL_single(VOL_CACHE,'TotalVol','Vol'); document.getElementById('btnShowAllVol').style.display='none'; document.getElementById('btnHideVol').style.display='inline-flex'; };
document.getElementById('btnHideVol').onclick=()=>{ VOL_SHOW_ALL=false; render_TOTAL_single(VOL_CACHE,'TotalVol','Vol'); document.getElementById('btnHideVol').style.display='none'; document.getElementById('btnShowAllVol').style.display='inline-flex'; };
document.getElementById('btnShowAllUsd').onclick=()=>{ USD_SHOW_ALL=true; render_TOTAL_single(USD_CACHE,'TotalUsd','Usd'); document.getElementById('btnShowAllUsd').style.display='none'; document.getElementById('btnHideUsd').style.display='inline-flex'; };
document.getElementById('btnHideUsd').onclick=()=>{ USD_SHOW_ALL=false; render_TOTAL_single(USD_CACHE,'TotalUsd','Usd'); document.getElementById('btnHideUsd').style.display='none'; document.getElementById('btnShowAllUsd').style.display='inline-flex'; };

function render_UTAMA6(pair){
  const tb=document.getElementById('tbodyUtama'), tf=document.getElementById('tfootUtama');
  const L=pair?.vol?.length||0, R=pair?.usd?.length||0, N=Math.max(L,R);
  if(N===0){ tb.innerHTML='<tr><td colspan="6">Belum ada data.</td></tr>'; tf.innerHTML=''; return; }
  let rowsHtml='';
  for(let i=0;i<N;i++){
    const lv=pair.vol[i]||{}, ru=pair.usd[i]||{};
    rowsHtml += `<tr>
      <td class="text-right">${lv.no??''}</td>
      <td>${lv.komoditas||''}</td>
      <td class="text-right">${fmt(lv.angka)}</td>
      <td class="text-right">${ru.no??''}</td>
      <td>${ru.komoditas||''}</td>
      <td class="text-right">${fmt(ru.angka)}</td>
    </tr>`;
  }
  tb.innerHTML = rowsHtml; tf.innerHTML = '';
}

/* ==== Ring: slice 10 baris pertama mulai dari NEGARA + anchor untuk ShowAll ==== */
function isCountryLabel(s){
  const letters = String(s??'').replace(/[^A-Za-z\u00C0-\u024F\u1E00-\u1EFF]/g,'');
  if(!letters) return false;
  return letters === letters.toUpperCase();
}
function renderRing(rows, overrideStart=null){
  RING_CACHE = rows.slice();
  const tb=document.getElementById('tbodyRing'), tf=document.getElementById('tfootRing');

  if(!RING_CACHE.length){
    tb.innerHTML='<tr><td colspan="4">Belum ada data.</td></tr>'; tf.innerHTML='';
    toggleVis('Ring',0,0); return;
  }

  let start = 0, end = RING_CACHE.length;

  if (overrideStart !== null) {
    start = Math.max(0, Math.min(overrideStart, RING_CACHE.length-1));
  } else if (!RING_SHOW_ALL) {
    const firstCountry = RING_CACHE.findIndex(r=>isCountryLabel(r.negara));
    start = Math.max(0, firstCountry);
    RING_ANCHOR_INDEX = start; // simpan anchor untuk ShowAll/Hide
  }

  if (!RING_SHOW_ALL) end = Math.min(start + MAX_INITIAL_ROWS, RING_CACHE.length);

  const view = RING_CACHE.slice(start, end);

  let html=''; let groupIndex = 0; let noNegara = 0;
  for (const r of view){
    const lab = String(r.negara||'').trim();
    const isCountry = isCountryLabel(lab);
    if(isCountry){
      groupIndex++; noNegara++;
      const zebra = (groupIndex % 2 === 0) ? ' alt' : '';
      html += `
        <tr class="row-country${zebra}">
          <td class="text-right">${noNegara}</td>
          <td><strong>${lab}</strong></td>
          <td class="text-right">${fmt(r.jumlah_ton)}</td>
          <td class="text-right">${fmt(r.nilai_usd)}</td>
        </tr>`;
    }else{
      html += `
        <tr class="row-child">
          <td></td>
          <td>${lab}</td>
          <td class="text-right">${fmt(r.jumlah_ton)}</td>
          <td class="text-right">${fmt(r.nilai_usd)}</td>
        </tr>`;
    }
  }

  tb.innerHTML = html;

  const tTon=RING_CACHE.reduce((a,r)=>a+(parseNumID(r.jumlah_ton)||0),0);
  const tUsd=RING_CACHE.reduce((a,r)=>a+(parseNumID(r.nilai_usd)||0),0);
  tf.innerHTML=`<tr class="row-total"><td class="text-center" colspan="2">Grand Total</td><td class="text-right">${fmt(tTon)}</td><td class="text-right">${fmt(tUsd)}</td></tr>`;

  toggleVis('Ring', RING_CACHE.length, MAX_INITIAL_ROWS);
}
document.getElementById('btnShowAllRing').onclick=()=>{ RING_SHOW_ALL=true; renderRing(RING_CACHE, RING_ANCHOR_INDEX); document.getElementById('btnShowAllRing').style.display='none'; document.getElementById('btnHideRing').style.display='inline-flex'; };
document.getElementById('btnHideRing').onclick=()=>{ RING_SHOW_ALL=false; renderRing(RING_CACHE, RING_ANCHOR_INDEX); document.getElementById('btnHideRing').style.display='none'; document.getElementById('btnShowAllRing').style.display='inline-flex'; };

/* ====== Template (download) ====== */
function wbDownload(name,sheets){
  const wb=XLSX.utils.book_new();
  sheets.forEach(s=>{const ws=XLSX.utils.aoa_to_sheet(s.aoa); if(s.widths){ws['!cols']=s.widths.map(w=>({wch:w}));} XLSX.utils.book_append_sheet(wb,ws,s.name);});
  XLSX.writeFile(wb,name);
}
function tpl_TOTAL_VOL(){return{ name:'TOTAL VOLUME', widths:[8,36,18], aoa:[['No','Komoditas','Volume / Ton']] };}
function tpl_TOTAL_USD(){return{ name:'TOTAL NILAI',   widths:[8,36,22], aoa:[['No','Komoditas','Nilai (USD)']] };}
function tpl_RING(){return{ name:'Ekspor Perikanan',   widths:[28,22,22], aoa:[['Row Labels','Sum of JUMLAH (Ton)','Sum of NILAI (USD)']] };}

function downloadTpl(k){
  const spec={
    total_vol:{fname:'TOTAL VOLUME.xlsx',sheets:[tpl_TOTAL_VOL()]},
    total_usd:{fname:'TOTAL NILAI.xlsx',sheets:[tpl_TOTAL_USD()]},
    ring     :{fname:'Ekspor Perikanan.xlsx',sheets:[tpl_RING()]}
  }[k];
  if(spec) wbDownload(spec.fname,spec.sheets);
}
document.querySelectorAll('[data-tpl]').forEach(b=>b.onclick=()=>downloadTpl(b.dataset.tpl));
document.getElementById('btnDownloadTemplates').onclick=()=>['total_vol','total_usd','ring'].forEach((k,i)=>setTimeout(()=>downloadTpl(k),i*150));

/* ====== Upload → parse → simpan ====== */
document.getElementById('btnUpload').onclick=async()=>{
  const tahun=getYear();
  if(!isValidYear(tahun)){ flashSave('Tahun wajib 4 digit (2000–2100).','#dc2626'); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return; }
  ['tahunTotalVol','tahunTotalUsd','tahunUtama','tahunRing'].forEach(id=>document.getElementById(id).textContent=tahun);
  if((bag.files||[]).length===0){ flashSave('Pilih minimal satu file.','#dc2626'); return; }

  clearViews();
  setStatusProgress('Menyelaraskan data tahun…');
  await wipeYearEkspor(tahun);
  setStatusProgress('Memproses file...'); notes.innerHTML='';

  let listVol=null, listUsd=null, ringList=null, handled=false;

  for(const f of bag.files){
    try{
      const wb=await readXlsx(f);
      wb.SheetNames.forEach(name=>{
        const ws=wb.Sheets[name]; if(!ws||!ws['!ref']) return;
        const aoa = sheetToAoA(ws);
        const kind = detectSheetType(aoa, name);

        if (kind==='VOL'){
          const rows = parse_TOTAL_SINGLE(aoa,'VOL');
          if (rows.length){ listVol = rows; note(`TOTAL VOLUME parsed (sheet: ${name}): ${rows.length} baris.`, true); handled = true; }
        } else if (kind==='USD'){
          const rows = parse_TOTAL_SINGLE(aoa,'USD');
          if (rows.length){ listUsd = rows; note(`TOTAL NILAI parsed (sheet: ${name}): ${rows.length} baris.`, true); handled = true; }
        } else {
          const up=String(name).trim().toUpperCase();
          const looksRING = (up==='EKSPOR PERIKANAN') || (up.includes('EKSPOR') && up.includes('PERIKANAN'));
          if (looksRING){
            const norm = v => String(v ?? '').replace(/\u00A0/g,' ').trim();
            const num  = v => parseNumID(v);
            let start = -1;
            for (let i=0;i<aoa.length;i++){
              const c0 = norm(aoa[i]?.[0]).toUpperCase();
              const c1 = norm(aoa[i]?.[1]).toUpperCase();
              const c2 = norm(aoa[i]?.[2]).toUpperCase();
              const ok0 = c0.includes('ROW') && c0.includes('LABEL');
              const ok1 = c1.includes('JUMLAH') || c1.includes('VOLUME') || c1.includes('TON');
              const ok2 = c2.includes('NILAI')  || c2.includes('USD')    || c2.includes('$');
              if (ok0 && ok1 && ok2){ start = i + 1; break; }
            }
            const rows=[];
            if (start >= 0){
              for (let i=start;i<aoa.length;i++){
                const lab = norm(aoa[i]?.[0]);
                const ton = num(aoa[i]?.[1]);
                const usd = num(aoa[i]?.[2]);
                if (/^GRAND\s*TOTAL$/i.test(lab)) break;
                if (lab==='' && (aoa[i]?.[1]===''||aoa[i]?.[1]==null) && (aoa[i]?.[2]===''||aoa[i]?.[2]==null)) continue;
                if (lab!==''){
                  rows.push({ negara: lab, jumlah_ton: ton ?? 0, nilai_usd: usd ?? 0 });
                }
              }
            }
            ringList = rows;
            note(`RING parsed (sheet: ${name}): ${rows.length} baris.`, true);
            handled = true;
          }
        }
      });
    }catch(e){ note(`NOTED: Gagal membaca ${f.name}: ${e.message||e}`, false); }
  }
  if(!handled){ flashSave('Tidak menemukan sheet yang sesuai (TOTAL VOLUME / TOTAL NILAI / Ekspor Perikanan).','#dc2626',5000); return; }

  try{
    // Gabungkan (by komoditas) untuk simpan ke DB total (nilai <=0 -> NULL)
    if(listVol || listUsd){
      const byKom=new Map();
      const normKey = s => String(s||'').replace(/\u00A0/g,' ').trim().toUpperCase();

      (listVol||[]).forEach(r=>{
        const key=normKey(r.komoditas); if(!key) return;
        const obj=byKom.get(key)||{komoditas:r.komoditas.replace(/\u00A0/g,' ').trim(),volume:null,usd:null};
        obj.volume = parseNumID(r.angka) ?? obj.volume; byKom.set(key,obj);
      });
      (listUsd||[]).forEach(r=>{
        const key=normKey(r.komoditas); if(!key) return;
        const obj=byKom.get(key)||{komoditas:r.komoditas.replace(/\u00A0/g,' ').trim(),volume:null,usd:null};
        obj.usd = parseNumID(r.angka) ?? obj.usd; byKom.set(key,obj);
      });

      const rowsTotal=[...byKom.values()].map(o=>({
        tahun:+tahun,
        komoditas:o.komoditas,
        volume_ton:(Number.isFinite(o.volume)&&o.volume>0)?o.volume:null,
        nilai_usd :(Number.isFinite(o.usd)   &&o.usd>0)?o.usd:null
      }));
      await saveRows('ekspor_perikanan_total', rowsTotal);

      const top=parse_UTAMA_from_lists(listVol||[], listUsd||[]);
      const rowsU=[];
      (top.vol||[]).forEach(r=>rowsU.push({tahun:+tahun,sisi:'VOL',no_urut:r.no,komoditas:r.komoditas,angka:parseNumID(r.angka)||0}));
      (top.usd||[]).forEach(r=>rowsU.push({tahun:+tahun,sisi:'USD',no_urut:r.no,komoditas:r.komoditas,angka:parseNumID(r.angka)||0}));
      await saveRows('ekspor_perikanan_utama', rowsU);

      // render UI (ikut urutan input)
      render_TOTAL_single((listVol||[]).map((o,i)=>({no:i+1,komoditas:o.komoditas,angka:o.angka})), 'TotalVol','Vol');
      render_TOTAL_single((listUsd||[]).map((o,i)=>({no:i+1,komoditas:o.komoditas,angka:o.angka})), 'TotalUsd','Usd');
      render_UTAMA6({vol: top.vol.map(x=>({no:x.no,komoditas:x.komoditas,angka:x.angka})),
                     usd: top.usd.map(x=>({no:x.no,komoditas:x.komoditas,angka:x.angka}))});
    }

    if(ringList && ringList.length){
      const rows=ringList.map((r,i)=>({
        tahun:+tahun,
        urut: i+1, // simpan urutan persis Excel (tidak ditampilkan di view)
        negara: (r.negara||'').toString(),
        jumlah_ton: parseNumID(r.jumlah_ton)||0,
        nilai_usd:  parseNumID(r.nilai_usd)||0
      })).filter(r=>r.negara!=='');
      await saveRows('ekspor_perikanan_ringkasan', rows);
      renderRing(ringList); // urutan sesuai Excel
    }
  }catch(e){ note('NOTED: '+(e.message||e), false); }

  await loadFromDB(tahun);
  flashSave(`Data tahun ${tahun} diproses ✓`,'#059669');
  setStatusProgress('');
};

/* ====== Viewer (fetch dari API) ====== */
async function loadFromDB(year, seq){
  if(typeof seq!=='number') seq=++YEAR_LOAD_SEQ; const mySeq=seq;
  try{
    if (mySeq !== YEAR_LOAD_SEQ) return;
    ['tahunTotalVol','tahunTotalUsd','tahunUtama','tahunRing'].forEach(id=>{const el=document.getElementById(id); if(el) el.textContent=year;});

    const resp = await fetch(`api/ekspor_fetch.php?tahun=${encodeURIComponent(year)}&_=${Date.now()}`, {cache:'no-store'});
    const p = await resp.json(); if (mySeq !== YEAR_LOAD_SEQ) return;

    clearViews();
    if(!p || p.ok !== true){ setGlobal(false, year); flashYear(`Error mengambil data tahun ${year}`,'#dc2626'); return; }

    // total → dua list (nilai > 0)
    const totalRows = p.total || [];
    const listVol = totalRows
      .map(x => ({ komoditas:x.komoditas, v: parseNumID(x.volume_ton) }))
      .filter(x => Number.isFinite(x.v) && x.v > 0)
      .map((x,i)=>({ no:i+1, komoditas:x.komoditas, angka:x.v }));
    const listUsd = totalRows
      .map(x => ({ komoditas:x.komoditas, u: parseNumID(x.nilai_usd) }))
      .filter(x => Number.isFinite(x.u) && x.u > 0)
      .map((x,i)=>({ no:i+1, komoditas:x.komoditas, angka:x.u }));

    render_TOTAL_single(listVol, 'TotalVol','Vol');
    render_TOTAL_single(listUsd, 'TotalUsd','Usd');

    const utamaRows = p.utama || [];
    const vol = utamaRows.filter(x=>String(x.sisi).toUpperCase()==='VOL')
                         .sort((a,b)=>(a.no_urut||0)-(b.no_urut||0))
                         .map(x=>({no:x.no_urut, komoditas:x.komoditas, angka:parseNumID(x.angka)}));
    const usdTop = utamaRows.filter(x=>String(x.sisi).toUpperCase()==='USD')
                            .sort((a,b)=>(a.no_urut||0)-(b.no_urut||0))
                            .map(x=>({no:x.no_urut, komoditas:x.komoditas, angka:parseNumID(x.angka)}));
    render_UTAMA6({vol, usd:usdTop});

    const ring = (p.ring||[]).map(x=>({
      negara: (x.negara ?? x.label ?? x.row_label ?? x.rowlabels ?? '').toString().replace(/\u00A0/g,' ').trim(),
      jumlah_ton: parseNumID(x.jumlah_ton ?? x.jumlah ?? x.ton ?? x.volume),
      nilai_usd:  parseNumID(x.nilai_usd   ?? x.nilai   ?? x.usd)
    }));
    renderRing(ring);

    const hasAny = listVol.length || listUsd.length || vol.length || usdTop.length || ring.length;
    setGlobal(!!hasAny, year);
    flashYear(hasAny ? 'Data tersedia ✓' : 'Tidak ada data.', hasAny?'#059669':'#dc2626', 2500);
  }catch(e){
    if (mySeq !== YEAR_LOAD_SEQ) return;
    console.error(e); setGlobal(false, year); flashYear(`Error mengambil data tahun ${year}`,'#dc2626');
  }
}

/* Tahun input binding + reset */
const yearInput=document.getElementById('tahun');
function triggerLoad(){
  const y=getYear();
  if(!isValidYear(y)){ setYearState(); setGlobal(false,''); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return; }
  setYearState(); const seq=++YEAR_LOAD_SEQ; clearViews(); loadFromDB(y, seq);
}
yearInput.addEventListener('input',()=>{clearTimeout(window.__yearDebounce);window.__yearDebounce=setTimeout(triggerLoad,250);});
yearInput.addEventListener('change',triggerLoad);
yearInput.addEventListener('blur',triggerLoad);
yearInput.addEventListener('keydown',(e)=>{if(e.key==='Enter'){e.preventDefault();triggerLoad();}});

document.getElementById('btnReset').onclick=()=>{ clearFiles(); clearViews(); setYearState(); const st=document.getElementById('statusSave'); if(st) st.textContent=''; notes.innerHTML=''; setGlobal(false,''); };
document.getElementById('btnYearReset').onclick=()=>{ YEAR_LOAD_SEQ++; const y=document.getElementById('tahun'); if(y) y.value=''; try{ clearTimeout(window.__yearDebounce); }catch(_){}
  setYearState(); const sy=document.getElementById('statusYear'); if(sy){ sy.textContent=''; sy.removeAttribute('style'); } setGlobal(false,''); };


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
