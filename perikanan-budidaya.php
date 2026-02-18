<?php
// perikanan-budidaya.php — Upload + Viewer (7 template Excel)
// DB: budidaya_ringkasan, budidaya_volume_bulanan, budidaya_nilai_bulanan, budidaya_luas, budidaya_produksi_kabkota,
//     budidaya_pembudidaya, budidaya_komoditas_unggulan

require_once __DIR__.'/auth_guard.php';  // 🔐 cek login & status admin
require_login();                         // wajib login dulu

// IZINKAN read-only jika dipanggil dari dashboard (?dashboard=1)
$IS_ADMIN  = (($_SESSION['userType'] ?? '') === 'admin');
$DASHBOARD = isset($_GET['dashboard']) && $_GET['dashboard'] !== '0';

if (!$IS_ADMIN && !$DASHBOARD) {
  // akses langsung oleh non-admin tetap ditolak (sama efeknya dengan require_admin)
  require_admin(); // akan 403 kalau bukan admin
}

// Saat di-embed di dashboard, batasi iframe ke origin sendiri
if ($DASHBOARD) {
  header('X-Frame-Options: SAMEORIGIN');
}

require_once __DIR__.'/protected_template.php';
start_protected_page('Perikanan Budidaya','budidaya');
?>

<style>
:root{--toska:#0097a7;--head:#0b1b2b;--head-2:#0f2134;--head-3:#132a44;--line:#e2e8f0}
*{font-family:ui-sans-serif,system-ui}table{border-collapse:collapse;width:100%}
th,td{border:1px solid #e2e8f0;padding:.5rem .75rem;font-size:.875rem;line-height:1.25rem;background:#fff;text-align:left}
th{font-weight:700;text-align:center}.text-right{text-align:right}.text-center{text-align:center}
thead.bg-samudera th{background:#0b1b2b;color:#fff;border-color:#fff;font-size:.875rem}
.thead-matrix tr.tier-1 th,.thead-matrix tr.tier-2 th{background:var(--head);color:#fff;text-transform:uppercase;border-color:#fff}
.thead-matrix tr.tier-3 th{background:var(--head-3);color:rgba(255,255,255,.85);font-style:italic;border-color:#fff}
tr.row-total td{font-weight:700;background:#f8fafc;border-top:2px solid #e2e8f0;border-bottom:2px solid #e2e8f0}
tr.row-subtotal td{font-weight:700;background:#f1f5f9;border-top:2px solid #e2e8f0;border-bottom:2px solid #e2e8f0}
td.cell-total{font-weight:700}.field-label{font-weight:700;color:#0f172a}.hint{font-size:.78rem;color:#64748b}
.nice-input{display:flex;align-items:center;gap:.5rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:.6rem .75rem;box-shadow:0 2px 6px rgba(2,8,20,.04)}
.nice-input input{width:100%;border:none;outline:none;background:transparent;font-size:.95rem;appearance:textfield;text-align:center}
.nice-input input::-webkit-outer-spin-button,.nice-input input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.filebox{display:flex;align-items:center;gap:.6rem;background:#fff;border:1px solid var(--line);border-radius:12px;padding:.45rem .5rem;box-shadow:0 2px 6px rgba(0,151,167,.04)}
.filebox input[type=file]{display:none}
.filebox .pick{display:inline-flex;align-items:center;gap:.45rem;background:var(--toska);color:#fff;font-weight:700;border-radius:10px;padding:.55rem .9rem;box-shadow:0 6px 18px rgba(0,151,167,.25)}
.filebox .pick:hover{background:#008a9d}.filebox .name{flex:1;color:#475569;font-size:.9rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.btn-primary{background:var(--toska);color:#fff;font-weight:700;border-radius:12px;padding:.55rem .9rem;box-shadow:0 6px 18px rgba(0,151,167,.25)}
.btn-ghost{background:#fff;color:#0097a7;font-weight:700;border:1px solid #0097a7;border-radius:12px;padding:.55rem .9rem}
.btn-ghost:hover{background:#f1fefe}.btn-xs{padding:.3rem .5rem;border-radius:10px;font-size:.78rem}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border:1px solid #0097a7;color:#0b7285;border-radius:10px;background:#fff}
.icon-btn:hover{background:#f1fefe}.icon-btn[disabled],.btn-primary[disabled],.btn-ghost[disabled],.filebox .pick[disabled]{opacity:.5;cursor:not-allowed}
.card{background:#fff;border:1px solid(var(--line));border-radius:14px;box-shadow:0 2px 6px rgba(2,8,20,.04)}.card-h{padding:6px 10px;border-bottom:1px solid var(--line);font-weight:700}.card-b{padding:8px 10px}
.panel-grid{display:grid;grid-template-columns:1fr;gap:20px}@media(min-width:1024px){.panel-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
.sec-head{display:flex;align-items:center;justify-content:space-between}.small-actions{display:flex;gap:.4rem;align-items:center}
.unit-note{font-size:.75rem;color:#64748b;border:1px dashed #cbd5e1;padding:.15rem .4rem;border-radius:8px}
.komo-main td{font-weight:700;background:#f8fafc}.komo-note td{font-style:italic;color:#64748b}.komo-sub td{font-weight:400}
.note-err{color:#dc2626;font-size:.85rem}.note-ok{color:#059669;font-size:.85rem}.badge-ok{font-size:.75rem;margin-left:.25rem;color:#059669}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
<h1 class="text-2xl font-bold mb-2">Perikanan Budidaya</h1>
<p class="text-slate-600 mb-1"><b>Upload Excel (.xlsx/.xls). Sistem mendeteksi template & menampilkan tabel.</b></p>
<p class="text-xs mb-4" id="globalInfo"></p>

<section id="panelUpload" class="bg-white rounded-2xl shadow p-5 mb-6">
  <div class="panel-grid">
    <div class="lg:col-span-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="field-label mb-2 block">Tahun</label>
          <div class="flex gap-2 items-center">
            <div class="nice-input"><input id="tahun" name="tahun" type="number" min="2000" max="2100" placeholder="yyyy" autocomplete="off"/></div>
            <button id="btnYearReset" type="button" class="btn-ghost btn-xs">Reset Tahun</button>
            <span id="statusYear" class="text-xs text-slate-500 ml-2"></span>
          </div>
          <p class="hint mt-2">Tanpa tahun valid, upload dinonaktifkan.</p>
        </div>
        <div></div>
        <div class="md:col-span-2">
          <label class="field-label mb-2 block">Upload File Budidaya (.xlsx/.xls)</label>
          <div class="filebox">
            <input id="fileAll" type="file" accept=".xlsx,.xls" multiple>
            <button id="pickAll" class="pick" type="button"><i class="fa-solid fa-file-arrow-up"></i> Pilih File</button>
            <input id="fileMore" type="file" accept=".xlsx,.xls" multiple style="display:none">
            <button id="addFile" class="icon-btn" type="button" title="Tambah"><i class="fa-solid fa-plus"></i></button>
            <button id="clearFiles" class="icon-btn" type="button" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            <span id="nameAll" class="name">Belum ada file dipilih (bisa multi)</span>
          </div>
          <div class="mt-4 flex items-center gap-2">
            <button id="btnUpload" class="btn-primary btn-xs" type="button"><i class="fa-solid fa-upload mr-1"></i> Upload & Tampilkan</button>
            <button id="btnReset" class="btn-ghost btn-xs" type="button">Reset</button>
            <span id="statusSave" class="text-sm text-slate-500"></span>
          </div>
        </div>
      </div>
    </div>
    <aside class="card">
      <div class="card-h">Template Excel (dummy)</div>
      <div class="card-b">
        <div class="flex flex-col gap-1">
          <button id="btnDownloadTemplates" class="btn-ghost btn-xs w-full" type="button"><i class="fa-solid fa-download mr-1"></i> Download Semua (7)</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="ringkasan" type="button">Ringkasan Budidaya.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="volume" type="button">Volume per Bulan per Wadah.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="nilai" type="button">Nilai per Bulan per Wadah.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="luas" type="button">Luas Lahan Budidaya.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="prodkk" type="button">Produksi Budidaya KabKota.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="pemb" type="button">Pembudidaya.xlsx</button>
          <button class="btn-ghost btn-xs w-full" data-tpl="kom" type="button">Komoditas Unggulan.xlsx</button>
        </div>
      </div>
    </aside>
  </div>
</section>

<!-- RINGKASAN -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Ringkasan Data Perikanan Budidaya</h3><p class="text-xs text-slate-500">Tahun <span id="tahunRingkasan">-</span></p></div>
    <div class="small-actions"><button id="showRingkasan" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hideRingkasan" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center"><tr><th>Uraian</th><th>Nilai</th><th>Satuan</th></tr></thead>
      <tbody id="tbodyRingkasan"><tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr></tbody>
    </table>
  </div>
</section>

<!-- VOLUME -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Volume Produksi Perwadah Per Bulan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunVol">-</span></p></div>
    <div class="small-actions"><span class="unit-note">Satuan = Ton</span><button id="showVol" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hideVol" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center" id="theadVol"></thead>
      <tbody id="tbodyVol"><tr><td colspan="13">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootVol"></tfoot>
    </table>
  </div>
</section>

<!-- NILAI -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Nilai Produksi Perwadah Per Bulan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunNil">-</span></p></div>
    <div class="small-actions"><span class="unit-note">Satuan = (Rp. 1000,-)</span><button id="showNil" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hideNil" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center" id="theadNil"></thead>
      <tbody id="tbodyNil"><tr><td colspan="13">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootNil"></tfoot>
    </table>
  </div>
</section>

<!-- LUAS -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Luas Lahan Budidaya</h3><p class="text-xs text-slate-500">Tahun <span id="tahunLuas">-</span></p></div>
    <div class="small-actions"><button id="showLuas" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hideLuas" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <table class="min-w-full text-sm">
    <thead class="bg-samudera text-center" id="theadLuas"></thead>
    <tbody id="tbodyLuas"><tr><td colspan="2">Pilih tahun untuk melihat data.</td></tr></tbody>
  </table>
</section>

<!-- PRODUKSI KK -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Produksi Budidaya per Kabupaten/Kota</h3><p class="text-xs text-slate-500">Tahun <span id="tahunProdKK">-</span></p></div>
    <div class="small-actions"><button id="showProdKK" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hideProdKK" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <table class="min-w-full text-sm">
    <thead class="text-center thead-matrix" id="theadProdKK"></thead>
    <tbody id="tbodyProdKK"><tr><td colspan="8">Pilih tahun untuk melihat data.</td></tr></tbody>
  </table>
</section>

<!-- PEMBUDIDAYA -->
<section class="bg-white rounded-2xl shadow mb-6">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">Jumlah Pembudidaya Ikan</h3><p class="text-xs text-slate-500">Tahun <span id="tahunPemb">-</span></p></div>
    <div class="small-actions"><button id="showPemb" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hidePemb" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <table class="min-w-full text-sm">
    <thead class="bg-samudera text-center" id="theadPembRingkas"><tr><th>Uraian</th><th>Jumlah Pembudidaya</th></tr></thead>
    <tbody id="tbodyPembRingkas"><tr><td colspan="2">Pilih tahun untuk melihat data.</td></tr></tbody>
  </table>
</section>

<!-- KOMODITAS -->
<section class="bg-white rounded-2xl shadow">
  <div class="px-5 py-3 border-b sec-head">
    <div><h3 class="font-bold">KOMODITAS UNGGULAN PERIKANAN BUDIDAYA</h3><p class="text-xs text-slate-500">Tahun <span id="tahunKomoditas">-</span></p></div>
    <div class="small-actions"><span class="unit-note">Satuan = Ton</span><button id="showKom" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button><button id="hideKom" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button></div>
  </div>
  <table class="min-w-full text-sm">
    <thead class="bg-samudera text-center" id="theadKomoditas"></thead>
    <tbody id="tbodyKomoditas"><tr><td colspan="3">Pilih tahun untuk melihat data.</td></tr></tbody>
    <tfoot id="tfootKomoditas"></tfoot>
  </table>
</section>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
/* ========= TEMPLATE GENERATOR ========= */
const TEMPLATE_MAP={ringkasan:{filename:'Ringkasan Budidaya.xlsx',sheet:'Ringkasan',headers:['Uraian','Nilai','Satuan']},volume:{filename:'Volume per Bulan per Wadah.xlsx',sheet:'Volume',headers:['Uraian','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember','Total']},nilai:{filename:'Nilai per Bulan per Wadah.xlsx',sheet:'Nilai',headers:['Uraian','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember','Total']},luas:{filename:'Luas Lahan Budidaya.xlsx',sheet:'Luas',headers:['Uraian','Luas Bersih (Ha)']},prodkk:{filename:'Produksi Budidaya KabKota.xlsx',sheet:'Produksi KK',headers:['KABUPATEN/KOTA','Jumlah','Laut','Tambak','Kolam','Mina Padi','Karamba','Japung']},pemb:{filename:'Pembudidaya.xlsx',sheet:'Pembudidaya',headers:['Uraian','Jumlah Pembudidaya']},kom:{filename:'Komoditas Unggulan.xlsx',sheet:'Komoditas',headers:['No','Komoditas/Kab/Kota','Volume (Ton)']}}; 
function downloadBlankTemplate(key){const spec=TEMPLATE_MAP[key]; if(!spec){alert('Template tidak dikenali');return;} const wb=XLSX.utils.book_new(); const ws=XLSX.utils.aoa_to_sheet([spec.headers]); ws['!cols']=spec.headers.map(h=>({wch:Math.max(12,String(h).length+2)})); XLSX.utils.book_append_sheet(wb,ws,spec.sheet||'Sheet1'); XLSX.writeFile(wb,spec.filename);}
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('[data-tpl]').forEach(b=>b.addEventListener('click',()=>downloadBlankTemplate(b.getAttribute('data-tpl')))); const allBtn=document.getElementById('btnDownloadTemplates'); if(allBtn){allBtn.addEventListener('click',async()=>{for(const k of ['ringkasan','volume','nilai','luas','prodkk','pemb','kom']){downloadBlankTemplate(k);await new Promise(r=>setTimeout(r,250));}});}});

/* ===== Helpers ===== */
const YEAR_MIN=2000,YEAR_MAX=2100,MAX_ROWS=10,MONTHS=['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'];
const $=id=>document.getElementById(id),fmt=v=>(v===null||v===undefined)?'':String(v),norm=s=>String(s||'').toLowerCase().replace(/[^\p{L}\p{N}]+/gu,' ').trim();
const readXlsx=f=>new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>{try{res(XLSX.read(e.target.result,{type:'array'}));}catch(err){rej(err)}};r.onerror=rej;r.readAsArrayBuffer(f);});
const sheetToRows=ws=>{const R=XLSX.utils.decode_range(ws['!ref']);let best=0,bestScore=-1;for(let r=0;r<Math.min(8,R.e.r-R.s.r+1);r++){let s=0;for(let c=R.s.c;c<=R.e.c;c++){const v=ws[XLSX.utils.encode_cell({r,c})]?.v;if(v!==undefined&&String(v).trim()!=='')s++;}if(s>bestScore){bestScore=s;best=r;}}const hdr=XLSX.utils.sheet_to_json(ws,{header:1,range:best})[0]||[];const data=XLSX.utils.sheet_to_json(ws,{defval:'',header:hdr,range:best+1});return {cols:(hdr||[]).map(h=>String(h||'').trim()),data};};
let __yt,__st,YEAR_LOAD_SEQ=0;
function flashYear(msg,color="#0ea5e9",ms=3000){const el=$('statusYear'); if(!el)return; el.textContent=msg; el.style.color=color; clearTimeout(__yt); __yt=setTimeout(()=>el.textContent='',ms);}
function flashSave(msg,color="#059669",ms=3500){const el=$('statusSave'); if(!el)return; el.textContent=msg; el.style.color=color; clearTimeout(__st); __st=setTimeout(()=>el.textContent='',ms);}
function setStatus(text,color='#64748b'){const el=$('statusSave'); if(!el)return; el.textContent=text; el.style.color=color;}
const getYear=()=>($('tahun')?.value||'').trim(),isValidYear=y=>/^\d{4}$/.test(y)&&+y>=YEAR_MIN&&+y<=YEAR_MAX;

/* ===== File bag ===== */
const fAll=$('fileAll'),fMore=$('fileMore'),pick=$('pickAll'),addBtn=$('addFile'),clrBtnEl=$('clearFiles'),nameEl=$('nameAll'); let bag=new DataTransfer();
function refreshLabel(){nameEl.textContent=[...bag.files].map(f=>f.name).join(', ')||'Belum ada file dipilih (bisa multi)';}
function addFiles(list){for(const f of list)bag.items.add(f); fAll.files=bag.files; refreshLabel();}
function clearFiles(){bag=new DataTransfer(); fAll.value=''; fAll.files=bag.files; refreshLabel();}
pick.onclick=()=>fAll.click(); fAll.onchange=()=>{if(fAll.files.length)addFiles(fAll.files);}; addBtn.onclick=()=>fMore.click(); fMore.onchange=()=>{if(fMore.files.length)addFiles(fMore.files); fMore.value='';}; clrBtnEl.onclick=clearFiles;

/* ===== Tahun ===== */
function setYearState(){const y=getYear(),ok=isValidYear(y);['tahunRingkasan','tahunVol','tahunNil','tahunLuas','tahunProdKK','tahunPemb','tahunKomoditas'].forEach(id=>{const el=$(id); if(el)el.textContent=ok?y:'-';}); pick.disabled=!ok; addBtn.disabled=!ok; $('btnUpload').disabled=!ok;}
function setGlobal(ok,tahun){const el=$('globalInfo'); if(!tahun||!/^\d{4}$/.test(tahun)){el.innerHTML='';return;} el.innerHTML=ok?`<span class="badge-ok">Data tersedia untuk tahun ${tahun} ✓</span>`:`<span class="note-err">NOTED: Belum ada data untuk tahun ${tahun}.</span>`;}

/* ===== Default headers (agar thead tidak "hilang") ===== */
function setDefaultHeaders(){
  const mh=['Uraian','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $('theadVol').innerHTML=`<tr>${mh.map(h=>`<th>${h}</th>`).join('')}</tr>`; $('tbodyVol').innerHTML=`<tr><td colspan="13" class="text-slate-500">Pilih tahun untuk melihat data.</td></tr>`; $('tfootVol').innerHTML='';
  $('theadNil').innerHTML=`<tr>${mh.map(h=>`<th>${h}</th>`).join('')}</tr>`; $('tbodyNil').innerHTML=`<tr><td colspan="13" class="text-slate-500">Pilih tahun untuk melihat data.</td></tr>`; $('tfootNil').innerHTML='';
  $('theadLuas').innerHTML=`<tr><th>Uraian</th><th>Luas Bersih (Ha)</th></tr>`; $('tbodyLuas').innerHTML=`<tr><td colspan="2" class="text-slate-500">Pilih tahun untuk melihat data.</td></tr>`;
  const theadProd=$('theadProdKK'); theadProd.classList.add('thead-matrix'); theadProd.innerHTML=`
    <tr class="tier-1"><th rowspan="2">KABUPATEN/KOTA</th><th rowspan="2">Jumlah</th><th colspan="6">SUB SEKTOR PERIKANAN – B U D I D A Y A</th></tr>
    <tr class="tier-2"><th>LAUT</th><th>TAMBAK</th><th>KOLAM</th><th>MINA PADI</th><th>KARAMBA</th><th>JAPUNG</th></tr>
    <tr class="tier-3"><th>District</th><th>TOTAL</th><th>Marine Pond</th><th>Brackishwater</th><th>Freshwater Pond</th><th>Paddy Field</th><th>Cage Pond</th><th>Floating net</th></tr>`;
  $('tbodyProdKK').innerHTML=`<tr><td colspan="8" class="text-slate-500">Pilih tahun untuk melihat data.</td></tr>`;
  $('theadKomoditas').innerHTML=`<tr><th>No</th><th>Komoditas/Kab/Kota</th><th>Volume (Ton)</th></tr>`; $('tbodyKomoditas').innerHTML=`<tr><td colspan="3" class="text-slate-500">Pilih tahun untuk melihat data.</td></tr>`; $('tfootKomoditas').innerHTML='';
}

/* ===== Show/Hide ===== */
const CACHE={},SHOWALL={},BTN_MAP={};
function mountShowHide(tbodyId,btnShowId,btnHideId){BTN_MAP[tbodyId]={show:btnShowId,hide:btnHideId}; const s=$(btnShowId),h=$(btnHideId); if(!s||!h)return; s.onclick=()=>{SHOWALL[tbodyId]=true; renderSlice(tbodyId)}; h.onclick=()=>{SHOWALL[tbodyId]=false; renderSlice(tbodyId)};}
function _getBtns(tbodyId){const m=BTN_MAP[tbodyId]||{}; const s=$(m.show)||$('show'+tbodyId.replace('tbody','')); const h=$(m.hide)||$('hide'+tbodyId.replace('tbody','')); return [s,h];}
function renderSlice(tbodyId){const tb=$(tbodyId),all=(CACHE[tbodyId]||[]),showAll=!!SHOWALL[tbodyId]; if(!tb)return; const [s,h]=_getBtns(tbodyId);
  if(!all.length){tb.innerHTML='<tr><td colspan="99">Belum ada data.</td></tr>'; if(s)s.style.display='none'; if(h)h.style.display='none'; return;}
  const view=showAll?all:all.slice(0,MAX_ROWS); tb.innerHTML=view.join(''); if(s)s.style.display=all.length>MAX_ROWS&&!showAll?'inline-flex':'none'; if(h)h.style.display=all.length>MAX_ROWS&&showAll?'inline-flex':'none';
}

/* ===== Universal number tools (for ALL tables) ===== */
const nfGEN=new Intl.NumberFormat('id-ID',{minimumFractionDigits:0,maximumFractionDigits:2});
function parseFlex(v){
  if(v===null||v===undefined) return NaN;
  if(typeof v==='number') return v;
  let s=String(v).trim(); if(!s) return NaN;
  s=s.replace(/\s+/g,'').replace(/[^0-9,.\-]/g,'');
  const hasC=s.includes(','),hasD=s.includes('.');
  if(hasC&&hasD){const lc=s.lastIndexOf(','),ld=s.lastIndexOf('.');const dec=(lc>ld)?',':'.';const thou=dec===','?'.':',';s=s.split(thou).join(''); if(dec===',') s=s.replace(/,/g,'.');}
  else if(hasC){const p=s.split(','); s=(p[1]&&p[1].length<=2)?(p[0].split('.').join('')+'.'+p[1]):s.replace(/,/g,'');}
  else if(hasD){const p=s.split('.'); if(!(p[1]&&p[1].length<=2)) s=s.replace(/\./g,'');}
  if(s===''||s==='-'||s==='-.') return NaN; return parseFloat(s);
}
function fmtGEN(v){const n=parseFlex(v); return Number.isFinite(n)?nfGEN.format(n):(v===null||v===undefined?'':String(v));}
function fmtCell(v,isFirstCol){if(isFirstCol) return (v===null||v===undefined)?'':String(v); const n=parseFlex(v); return Number.isFinite(n)?nfGEN.format(n):(v===null||v===undefined?'':String(v));}

/* ===== Generic renderer (pakai fmtCell) ===== */
function renderExactTable(theadId,tbodyId,cols,rows,{autoTotal=false}={}) {
  $(theadId).innerHTML=`<tr>${cols.map(h=>`<th>${h}</th>`).join('')}</tr>`;
  const tIdx=cols.map(c=>norm(c)).findIndex(c=>/total/.test(c));
  const first=cols[0]; const rr=[];
  rows.forEach(r=>{
    const label=String(r[first]??'').trim();
    const isSub=/\bsub\b.*\b(jumlah|total)\b/i.test(label);
    const isTot=/\b(jumlah\s*-\s*total|jumlah|total)\b/i.test(label);
    const cls=isTot?'row-total':(isSub?'row-subtotal':'');
    rr.push(`<tr class="${cls}">${
      cols.map((h,i)=>{
        const v=r[h]??r[h?.toLowerCase()]??'';
        const tc=(i===tIdx?' cell-total':'');
        const txt=(i===0&&(isTot||isSub))?`<strong>${String(v)}</strong>`:fmtCell(v,i===0);
        return `<td class="${i===0?'':'text-right'}${tc}">${txt}</td>`;
      }).join('')
    }</tr>`);
  });
  CACHE[tbodyId]=rr; SHOWALL[tbodyId]=false; renderSlice(tbodyId);
}

/* ===== Save queue ===== */
let SAVE_PROMISES=[];

/* ===== Monthly renderer + footer TOTAL (pakai parser/formatter baru) ===== */
function renderMonthlyWithFooter(theadId,tbodyId,tfootId,cols,rows,tableName){
  const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
  $(theadId).innerHTML=`<tr>${['Uraian',...months].map(h=>`<th>${h}</th>`).join('')}</tr>`;
  const body=[];
  for(const r of rows){
    const ura=r['Uraian']??r['uraian']??'';
    const tds=[`<td>${ura?String(ura):''}</td>`];
    for(const m of months){const raw=r[m]??r[m.toLowerCase()]??''; tds.push(`<td class="text-right">${fmtGEN(raw)}</td>`);}
    body.push(`<tr>${tds.join('')}</tr>`);
  }
  CACHE[tbodyId]=body; SHOWALL[tbodyId]=false; renderSlice(tbodyId);

  const totals=months.map(m=>rows.reduce((s,r)=>{const n=parseFlex(r[m]??r[m.toLowerCase()]); return s+(Number.isFinite(n)?n:0)},0));
  $(tfootId).innerHTML=`<tr class="row-total"><td class="text-center"><strong>TOTAL</strong></td>${totals.map(x=>`<td class="text-right cell-total"><strong>${fmtGEN(x)}</strong></td>`).join('')}</tr>`;

  const tahun=getYear();
  const payload=rows.map(r=>{const o={tahun,uraian:String(r['Uraian']??r['uraian']??'')}; months.forEach(m=>{const key=m.slice(0,3).toLowerCase().replace('Agu','Agu').replace('agu','agu'); o[key]=String(r[m]??r[m.toLowerCase()]??'')}); if('Total'in r)o.total=String(r['Total']); return o;});
  SAVE_PROMISES.push(saveRows(tableName,payload));
}

async function saveRows(table,rows){
  try{
    if(!rows||!rows.length) return {ok:true,saved:0};
    setStatus(`Menyimpan: ${table}…`);
    const r=await fetch('/api/save_rows.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({table,rows})});
    const out=await r.json(); if(!out.ok) throw new Error(out.error||'Gagal simpan');
    setStatus(`TERSIMPAN (${table}) ✓`,'#059669'); setTimeout(()=>setStatus(''),1200);
    return out;
  }catch(e){setStatus(`GAGAL SIMPAN (${table})`,'#dc2626'); return {ok:false,error:String(e)}}
}

/* ===== Ringkasan ===== */
function renderRingkasan(rows){
  const tahun=getYear();
  const payload=rows.map(r=>({tahun,uraian:String(r['Uraian']||r['uraian']||''),nilai:String(r['Nilai']??r['nilai']??''),satuan:String(r['Satuan']||r['satuan']||'')}));
  SAVE_PROMISES.push(saveRows('budidaya_ringkasan',payload));
  CACHE.tbodyRingkasan=(rows||[]).map(r=>`<tr><td>${fmt(r['Uraian']||r['uraian']||'')}</td><td class="text-right">${fmtGEN(r['Nilai']??r['nilai'])}</td><td>${fmt(r['Satuan']||r['satuan']||'')}</td></tr>`);
  SHOWALL.tbodyRingkasan=false; renderSlice('tbodyRingkasan');
}

/* ===== Pembudidaya ===== */
function renderPembRingkas(rows){
  const tahun=getYear();
  const clean=(rows||[]).filter(r=>r&&Object.values(r).some(v=>String(v).trim()!==''));
  const payload=clean.map(r=>({tahun,uraian:String(r['Uraian']||r['uraian']||r['Uraian/Kegiatan']||r['Kegiatan']||''),jumlah:String(r['Jumlah Pembudidaya']??r['jumlah pembudidaya']??r['Jumlah']??'')}));
  SAVE_PROMISES.push(saveRows('budidaya_pembudidaya',payload));
  CACHE.tbodyPembRingkas=clean.map(r=>{
    const ura=r['Uraian']||r['uraian']||r['Uraian/Kegiatan']||r['Kegiatan']||''; const j=r['Jumlah Pembudidaya']??r['jumlah pembudidaya']??r['Jumlah']??r['jumlah']??r['Total']??'';
    const isTot=/\b(jumlah|total)\b/i.test(String(ura));
    return `<tr class="${isTot?'row-total':''}"><td>${isTot?`<strong>${ura}</strong>`:ura}</td><td class="text-right">${fmtGEN(j)}</td></tr>`;
  });
  SHOWALL.tbodyPembRingkas=false; renderSlice('tbodyPembRingkas');
}

/* ===== Luas ===== */
function renderLuas(cols,rows){
  renderExactTable('theadLuas','tbodyLuas',cols,rows);
  const tahun=getYear();
  const payload=rows.map(r=>({tahun,uraian:String(r['Uraian']??r['uraian']??''),luas_bersih_ha:String(r['Luas Bersih (Ha)']??r['luas bersih (ha)']??r['luas']??'')}));
  SAVE_PROMISES.push(saveRows('budidaya_luas',payload));
}

/* ===== Produksi Kab/Kota ===== */
function renderProdKK(_cols,rows){
  const thead=$('theadProdKK'); thead.classList.add('thead-matrix'); thead.innerHTML=`
    <tr class="tier-1"><th rowspan="2">KABUPATEN/KOTA</th><th rowspan="2">Jumlah</th><th colspan="6">SUB SEKTOR PERIKANAN – B U D I D A Y A</th></tr>
    <tr class="tier-2"><th>LAUT</th><th>TAMBAK</th><th>KOLAM</th><th>MINA PADI</th><th>KARAMBA</th><th>JAPUNG</th></tr>
    <tr class="tier-3"><th>District</th><th>TOTAL</th><th>Marine Pond</th><th>Brackishwater</th><th>Freshwater Pond</th><th>Paddy Field</th><th>Cage Pond</th><th>Floating net</th></tr>`;
  const cols=['KABUPATEN/KOTA','Jumlah','Laut','Tambak','Kolam','Mina Padi','Karamba','Japung'], rr=[];
  for(const r of rows){
    const nama=r[cols[0]]??r['kabupaten/kota']??r['District']??'';
    const isTot=/\b(jumlah\s*-\s*total|jumlah|total)\b/i.test(String(nama));
    rr.push(`<tr class="${isTot?'row-total':''}">
      <td>${fmt(nama)}</td>
      ${cols.slice(1).map(k=>`<td class="text-right">${fmtGEN(r[k]??'')}</td>`).join('')}
    </tr>`);
  }
  CACHE.tbodyProdKK=rr; SHOWALL.tbodyProdKK=false; renderSlice('tbodyProdKK');

  const tahun=getYear();
  const payload=rows.map(r=>({tahun,kabkota:String(r['KABUPATEN/KOTA']??r['kabupaten/kota']??r['District']??''),jumlah:String(r['Jumlah']??''),laut:String(r['Laut']??''),tambak:String(r['Tambak']??''),kolam:String(r['Kolam']??''),minapadi:String(r['Mina Padi']??''),karamba:String(r['Karamba']??''),japung:String(r['Japung']??'')}));
  SAVE_PROMISES.push(saveRows('budidaya_produksi_kabkota',payload));
}

/* ===== Komoditas Unggulan ===== */
function renderKomoditas(cols,rows){
  $('theadKomoditas').innerHTML=`<tr><th>No</th><th>Komoditas/Kab/Kota</th><th>Volume (Ton)</th></tr>`;
  const get=(r,k)=> r[k] ?? r[(k||'').toLowerCase()];
  const kNo=cols.find(c=>/^no$/i.test(c))||cols[0]||'No';
  const kKom=cols.find(c=>/komodit/i.test(c))||cols[1]||'Komoditas/Kab/Kota';
  const kVol=cols.find(c=>/volume|ton/i.test(c))||cols[2]||'Volume (Ton)';
  const isFlagged=rows.length&&('is_sub'in rows[0]||'is_note'in rows[0]);

  const reNoOnly=/^\s*(\d{1,2})\s*$/, reNoPref=/^\s*(\d{1,2})\s*[\.\)\-]?\s*(.+)$/, reNote=/penghasil\s+(.+?)\s+tertinggi/i;
  const groups=[],byKom=new Map(); let anchor=null;
  const push=(no,kom,vol)=>{const g={no:+no,main:{kom,vol},rows:[]}; groups.push(g); byKom.set(String(kom||'').toLowerCase().trim(),g)};

  if(isFlagged){
    for(const r of rows){const no=String(get(r,kNo)||'').trim(); if(no) push(no,String(get(r,kKom)||'').trim(),get(r,kVol));}
    for(const r of rows){
      const no=String(get(r,kNo)||'').trim(); if(no) continue;
      const kom=String(get(r,kKom)||'').trim(); const vol=get(r,kVol);
      if(+r.is_note){const m=kom.match(reNote); anchor=byKom.get((m?m[1]:'').toLowerCase().trim())||groups[groups.length-1]||null; if(anchor) anchor.rows.push({type:'note',text:kom}); continue;}
      if(+r.is_sub){const target=anchor||groups[groups.length-1]||null; if(target) target.rows.push({type:'sub',name:kom,vol});}
    }
  }else{
    let cur=null;
    for(const r of rows){
      const no=String(get(r,kNo)||'').trim(); const kom=String(get(r,kKom)||'').trim(); const vol=get(r,kVol);
      if(reNoOnly.test(no)){push(RegExp.$1,kom,vol); cur=byKom.get(kom.toLowerCase().trim()); cur?.rows.push({type:'note',text:`Kabupaten/Kota penghasil ${kom} tertinggi`}); continue;}
      const m=kom.match(reNoPref);
      if(m){push(m[1],m[2],vol); cur=byKom.get(m[2].toLowerCase().trim()); cur?.rows.push({type:'note',text:`Kabupaten/Kota penghasil ${m[2]} tertinggi`}); continue;}
      if(reNote.test(kom)){const m2=kom.match(reNote); cur=byKom.get((m2?m2[1]:'').toLowerCase().trim())||cur; if(cur) cur.rows.push({type:'note',text:kom}); continue;}
      if(kom&&cur){cur.rows.push({type:'sub',name:kom,vol});}
    }
  }

  let rowsHtml=[], grand=0;
  for(const g of groups.sort((a,b)=>a.no-b.no)){
    const mv=parseFlex(g.main.vol); if(Number.isFinite(mv)) grand+=mv;
    rowsHtml.push(`<tr class="komo-main"><td class="text-right">${g.no}</td><td>${g.main.kom||''}</td><td class="text-right">${fmtGEN(g.main.vol||'')}</td></tr>`);
    g.rows.filter(x=>x.type==='note').forEach(n=>rowsHtml.push(`<tr class="komo-note"><td></td><td colspan="2">${n.text}</td></tr>`));
    g.rows.filter(x=>x.type==='sub').forEach(s=>{const sv=parseFlex(s.vol); if(Number.isFinite(sv)) grand+=sv; rowsHtml.push(`<tr class="komo-sub"><td></td><td>${s.name||''}</td><td class="text-right">${fmtGEN(s.vol||'')}</td></tr>`);});
  }
  CACHE.tbodyKomoditas=rowsHtml; SHOWALL.tbodyKomoditas=false; renderSlice('tbodyKomoditas');
  $('tfootKomoditas').innerHTML=`<tr class="row-total"><td class="text-center" colspan="2">GRAND TOTAL</td><td class="text-right"><strong>${fmtGEN(grand)}</strong></td></tr>`;

  // simpan hanya saat input excel (ketika dari DB sudah terstruktur)
  // (opsi: hapus blok simpan ini jika tidak dibutuhkan)
  if(!isFlagged){
    const tahun=getYear(); const payload=[];
    for(const g of groups){
      payload.push({tahun,no:g.no,komoditas:g.main.kom,volume:String(g.main.vol||''),is_sub:0,is_note:0});
      g.rows.forEach(row=>{
        if(row.type==='note') payload.push({tahun,no:null,komoditas:row.text,volume:'',is_sub:0,is_note:1});
        else payload.push({tahun,no:null,komoditas:row.name,volume:String(row.vol||''),is_sub:1,is_note:0});
      });
    }
    SAVE_PROMISES.push(saveRows('budidaya_komoditas',payload));
  }
}

/* ===== Specific renderers + save ===== */
const renderVolMonthly=(c,r)=>renderMonthlyWithFooter('theadVol','tbodyVol','tfootVol',c,r,'budidaya_volume_bulanan'),
      renderNilMonthly=(c,r)=>renderMonthlyWithFooter('theadNil','tbodyNil','tfootNil',c,r,'budidaya_nilai_bulanan');

/* ===== Detector ===== */
function guessTemplate(cols,meta={}){const name=(meta.file||'')+' '+(meta.sheet||''); const low=cols.map(c=>String(c||'').toLowerCase().trim()); const has=k=>low.some(c=>c.includes(k)),isSet=need=>need.every(k=>has(k));
  if(low.length>=3&&has('uraian')&&has('nilai')&&has('satuan'))return 'ringkasan';
  if(isSet(['uraian'])&&(has('luas bersih')||has('(ha)')||has('ha')))return 'luas_lahan';
  if(has('kab')&&has('kota')&&isSet(['jumlah'])&&(has('laut')||has('marine'))&&(has('tambak')||has('brackish'))&&(has('kolam')||has('fresh'))&&(has('mina')&&has('padi'))&&(has('karamba')||has('cage'))&&(has('japung')||has('floating')))return 'produksi_kabkota';
  if(has('uraian')&&(has('jumlah pembudidaya')||(has('jumlah')&&has('pembudidaya'))))return 'pembudidaya_ringkas';
  if((has('komoditas')||has('komoditas/kab/kota')||has('komoditas kab kota'))&&(has('volume')||has('ton')))return 'komoditas_unggulan';
  const monthKeys=['januari','februari','maret','april','mei','juni','juli','agustus','september','oktober','november','desember'],hasMonths=monthKeys.every(m=>has(m));
  if(has('uraian')&&hasMonths){const nm=name.toLowerCase(); if(nm.includes('nilai')||nm.includes('rp')||nm.includes('harga'))return 'nilai_perwadah_bulanan'; if(nm.includes('volume')||nm.includes('ton')||nm.includes('vol'))return 'volume_perwadah_bulanan'; return 'volume_perwadah_bulanan';}
  return null;
}

/* ===== Upload ===== */
$('btnUpload').onclick=async()=>{const tahun=getYear(); if(!isValidYear(tahun)){flashSave('Tahun wajib 4 digit (2000–2100).','#dc2626'); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return;}
  ['tahunRingkasan','tahunVol','tahunNil','tahunLuas','tahunProdKK','tahunPemb','tahunKomoditas'].forEach(id=>$(id).textContent=tahun);
  if(bag.files.length===0){flashSave('Pilih minimal satu file.','#dc2626'); return;}
  setDefaultHeaders(); setStatus('Memproses file...'); SAVE_PROMISES=[]; let handledSheets=0,unknownSheets=0,processed=[];
  for(const f of bag.files){try{const wb=await readXlsx(f); wb.SheetNames.forEach(name=>{const ws=wb.Sheets[name]; if(!ws||!ws['!ref'])return; const {cols,data}=sheetToRows(ws); const rows=data.filter(r=>Object.values(r).some(v=>String(v).trim()!=='')); if(!rows.length)return;
        const type=guessTemplate(cols,{file:f.name,sheet:name});
        if(type==='ringkasan'){renderRingkasan(rows); processed.push('budidaya_ringkasan'); handledSheets++;}
        else if(type==='volume_perwadah_bulanan'){renderVolMonthly(cols,rows); processed.push('budidaya_volume_bulanan'); handledSheets++;}
        else if(type==='nilai_perwadah_bulanan'){renderNilMonthly(cols,rows); processed.push('budidaya_nilai_bulanan'); handledSheets++;}
        else if(type==='luas_lahan'){renderLuas(cols,rows); processed.push('budidaya_luas'); handledSheets++;}
        else if(type==='produksi_kabkota'){renderProdKK(cols,rows); processed.push('budidaya_produksi_kabkota'); handledSheets++;}
        else if(type==='pembudidaya_ringkas'){renderPembRingkas(rows); processed.push('budidaya_pembudidaya'); handledSheets++;}
        else if(type==='komoditas_unggulan'){renderKomoditas(cols,rows); processed.push('budidaya_komoditas'); handledSheets++;}
        else unknownSheets++;});}catch(e){console.warn('Gagal',f.name,e);}}
  try{await Promise.allSettled(SAVE_PROMISES);}catch(_){} setStatus('');
  if(!handledSheets){flashSave('Gagal upload: Template tidak dikenali / tidak sesuai.','#dc2626',5000); return;}
  flashSave(`Data tahun ${tahun} diproses ✓ (${[...new Set(processed)].join(', ')})`,'#059669'); if(unknownSheets>0)flashYear(`${unknownSheets} sheet di-skip (template tidak sesuai).`,'#f59e0b'); clearFiles(); const seq=++YEAR_LOAD_SEQ; setTimeout(()=>loadFromDB(tahun,seq),250);
};

/* ===== Reset ===== */
$('btnReset').onclick=()=>{clearFiles(); ['tbodyRingkasan','tbodyVol','tbodyNil','tbodyLuas','tbodyProdKK','tbodyPembRingkas','tbodyKomoditas','tfootKomoditas','tfootVol','tfootNil'].forEach(id=>{$(id).innerHTML='';}); setDefaultHeaders(); $('statusSave').textContent='';}

/* ===== Fetch viewer ===== */
async function loadFromDB(year,seq){if(typeof seq!=='number')seq=++YEAR_LOAD_SEQ; const mySeq=seq;
  try{if(mySeq!==YEAR_LOAD_SEQ)return; ['tahunRingkasan','tahunVol','tahunNil','tahunLuas','tahunProdKK','tahunPemb','tahunKomoditas'].forEach(id=>$(id).textContent=year);
    const r=await fetch(`/api/budidaya_fetch.php?tahun=${encodeURIComponent(year)}&_=${Date.now()}`,{cache:'no-store'}); const p=await r.json(); if(mySeq!==YEAR_LOAD_SEQ)return; setDefaultHeaders();
    ['tbodyRingkasan','tbodyVol','tbodyNil','tbodyLuas','tbodyProdKK','tbodyPembRingkas','tbodyKomoditas','tfootKomoditas','tfootVol','tfootNil'].forEach(id=>{$(id).innerHTML='';});
    let hasAny=false;
    if(Array.isArray(p.data?.ringkasan)&&p.data.ringkasan.length){renderRingkasan(p.data.ringkasan.map(x=>({Uraian:x.uraian,Nilai:x.nilai,Satuan:x.satuan}))); hasAny=true;}
    if(Array.isArray(p.data?.volume)&&p.data.volume.length){const cols=['Uraian','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
      const rows=p.data.volume.map(x=>({'Uraian':x.uraian,'Januari':x.jan,'Februari':x.feb,'Maret':x.mar,'April':x.apr,'Mei':x.mei,'Juni':x.jun,'Juli':x.jul,'Agustus':x.agu,'September':x.sep,'Oktober':x.okt,'November':x.nov,'Desember':x.des})); renderVolMonthly(cols,rows); hasAny=true;}
    if(Array.isArray(p.data?.nilai)&&p.data.nilai.length){const cols=['Uraian','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
      const rows=p.data.nilai.map(x=>({'Uraian':x.uraian,'Januari':x.jan,'Februari':x.feb,'Maret':x.mar,'April':x.apr,'Mei':x.mei,'Juni':x.jun,'Juli':x.jul,'Agustus':x.agu,'September':x.sep,'Oktober':x.okt,'November':x.nov,'Desember':x.des})); renderNilMonthly(cols,rows); hasAny=true;}
    if(Array.isArray(p.data?.luas)&&p.data.luas.length){const cols=['Uraian','Luas Bersih (Ha)']; const rows=p.data.luas.map(x=>({'Uraian':x.uraian,'Luas Bersih (Ha)':x.luas_bersih_ha})); renderLuas(cols,rows); hasAny=true;}
    if(Array.isArray(p.data?.prodkk)&&p.data.prodkk.length){const rows=p.data.prodkk.map(x=>({'KABUPATEN/KOTA':x.kabkota,'Jumlah':x.jumlah,'Laut':x.laut,'Tambak':x.tambak,'Kolam':x.kolam,'Mina Padi':x.minapadi,'Karamba':x.karamba,'Japung':x.japung})); renderProdKK(null,rows); hasAny=true;}
    if(Array.isArray(p.data?.pemb)&&p.data.pemb.length){CACHE.tbodyPembRingkas=p.data.pemb.map(x=>{const isTot=/\b(jumlah|total)\b/i.test(x.uraian); const cls=isTot?'row-total':''; const label=isTot?`<strong>${x.uraian}</strong>`:x.uraian; return `<tr class="${cls}"><td>${label}</td><td class="text-right">${fmtGEN(x.jumlah)}</td></tr>`}); SHOWALL.tbodyPembRingkas=false; renderSlice('tbodyPembRingkas'); hasAny=true;}
    if(Array.isArray(p.data?.komoditas)&&p.data.komoditas.length){const cols=['No','Komoditas/Kab/Kota','Volume (Ton)'];
      const rows=p.data.komoditas.map(x=>({'No':x.no??'','Komoditas/Kab/Kota':x.komoditas,'Volume (Ton)':x.volume??'',is_sub:+x.is_sub?1:0,is_note:+x.is_note?1:0})); renderKomoditas(cols,rows); hasAny=true;}
    setGlobal(!!hasAny,year); if(hasAny){flashYear(`Data tersedia ✓`,'#059669',2500);}else{setDefaultHeaders(); flashYear(`DATA BELUM TERSEDIA (Tahun ${year}).`,'#64748b');}
  }catch(e){if(seq!==YEAR_LOAD_SEQ)return; console.error(e); flashYear(`Error mengambil data tahun ${year}`,'#dc2626');}
}

/* ===== Tahun input binding ===== */
function triggerLoad(){const y=getYear(); if(!isValidYear(y)){setYearState(); setGlobal(false,''); if(y)flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return;} setYearState(); const seq=++YEAR_LOAD_SEQ; loadFromDB(y,seq);}
$('tahun').addEventListener('input',()=>{clearTimeout(window.__yearDebounce); window.__yearDebounce=setTimeout(triggerLoad,250);});
$('tahun').addEventListener('change',triggerLoad); $('tahun').addEventListener('blur',triggerLoad);
$('tahun').addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();triggerLoad();}});
$('btnYearReset').onclick=()=>{YEAR_LOAD_SEQ++; $('tahun').value=''; try{clearTimeout(window.__yearDebounce);}catch(_){ } setYearState(); $('statusYear').textContent=''; $('statusYear').removeAttribute('style'); setGlobal(false,'');};

/* ===== Onload ===== */
document.addEventListener('DOMContentLoaded',()=>{$('tahun').value=''; setYearState(); setDefaultHeaders();
  [['tbodyRingkasan','showRingkasan','hideRingkasan'],['tbodyVol','showVol','hideVol'],['tbodyNil','showNil','hideNil'],['tbodyLuas','showLuas','hideLuas'],['tbodyProdKK','showProdKK','hideProdKK'],['tbodyPembRingkas','showPemb','hidePemb'],['tbodyKomoditas','showKom','hideKom']].forEach(([t,s,h])=>mountShowHide(t,s,h));
});

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
