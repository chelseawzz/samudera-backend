<?php
// kpp.php — Upload + Viewer
require_once __DIR__.'/auth_guard.php';  // 🔐 cek login & status admin
require_login();                         // wajib login dulu

// ⤵️ Izinkan tampil readonly jika dari dashboard
$IS_DASH = isset($_GET['dashboard']) && $_GET['dashboard'] === '1';
if (!$IS_DASH) {
  require_admin(); // tetap wajib admin kalau buka langsung
}

require_once __DIR__.'/protected_template.php';
start_protected_page('KPP', 'kpp');
?>

<!-- ====== STYLE (selaras ekspor-perikanan) ====== -->
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
tr.row-total td{font-weight:700;background:#f8fafc}
.sec-head{display:flex;align-items:center;justify-content:space-between}
.small-actions{display:flex;gap:.4rem;align-items:center}
.subnote{font-size:.78rem;color:#64748b;font-style:italic}
.note-err{color:#dc2626;font-size:.85rem}.note-ok{color:#059669;font-size:.85rem}.badge-ok{font-size:.75rem;margin-left:.25rem;color:#059669}
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>

<!-- ====== HEADER ====== -->
<h1 class="text-2xl font-bold mb-2">KPP (Garam)</h1>
<p class="text-slate-600 mb-1"><b>Upload Excel (.xlsx/.xls). Sistem mendeteksi sheet &amp; menampilkan tabel.</b></p>
<p class="text-xs mb-4" id="globalInfo"></p>

<!-- ====== PANEL KONTROL ====== -->
<section id="panelUpload" class="bg-white rounded-2xl shadow p-5 mb-6">
  <div class="panel-grid">
    <div class="lg:col-span-2">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="field-label mb-2 block">Tahun</label>
          <div class="flex gap-2 items-center">
            <div class="nice-input"><input id="tahun" type="number" inputmode="numeric" min="2000" max="2100" placeholder="yyyy" autocomplete="off"/></div>
            <button id="btnYearReset" type="button" class="btn-ghost btn-xs whitespace-nowrap">Reset Tahun</button>
            <span id="statusYear" class="text-xs text-slate-500 ml-2"></span>
          </div>
          <p class="hint mt-2">Tanpa tahun valid, upload dinonaktifkan.</p>
        </div>

        <div class="md:col-span-2">
          <label class="field-label mb-2 block">Upload Data Garam (.xlsx/.xls)</label>
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
        <button id="btnDownloadTemplates" class="btn-ghost btn-xs w-full" type="button">
          <i class="fa-solid fa-download mr-1"></i> Data Garam.xlsx
        </button>
      </div>
    </aside>
  </div>
</section>

<!-- ====== TABEL GARAPAN ====== -->
<section class="bg-white rounded-2xl shadow">
  <div class="px-5 py-3 border-b sec-head">
    <div>
      <h3 class="font-bold">Data Produksi Garam</h3>
      <p class="text-xs text-slate-500">Tahun <span id="tahunGaram">-</span></p>
    </div>
    <div class="small-actions">
      <button id="btnShowAllGaram" class="btn-ghost btn-xs" style="display:none;">Tampilkan Semua</button>
      <button id="btnHideGaram" class="btn-ghost btn-xs" style="display:none;">Sembunyikan</button>
    </div>
  </div>
  <div class="overflow-auto">
    <table class="min-w-full text-sm">
      <thead class="bg-samudera text-center">
        <tr>
          <th>No</th>
          <th>Kab/Kota</th>
          <th>L Total (Ha)</th>
          <th>Σ Kelompok</th>
          <th>Σ Petambak</th>
          <th>Σ Prod (Ton)</th>
        </tr>
      </thead>
      <tbody id="tbodyGaram"><tr><td colspan="6">Pilih tahun untuk melihat data.</td></tr></tbody>
      <tfoot id="tfootGaram"></tfoot>
    </table>
  </div>
</section>

<!-- ====== LIB ====== -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

<!-- ====== LOGIC (disamakan dengan ekspor-perikanan) ====== -->
<script>
/* ====== State & helpers (format/flash/notes) ====== */
const YEAR_MIN=2000, YEAR_MAX=2100;
const MAX_INITIAL_ROWS = 10;
let YEAR_LOAD_SEQ=0;

let GAR_CACHE=[]; // cache tampilan
let GAR_SHOW_ALL=false;

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
function toggleVis(total, limit){
  const showBtn=document.getElementById('btnShowAllGaram');
  const hideBtn=document.getElementById('btnHideGaram');
  if(total>limit){ showBtn.style.display='inline-flex'; hideBtn.style.display='none'; }
  else { showBtn.style.display='none'; hideBtn.style.display='none'; }
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

/* ====== Tahun state ====== */
function clearViews(){
  document.getElementById('tbodyGaram').innerHTML='<tr><td colspan="6">Pilih tahun untuk melihat data.</td></tr>';
  document.getElementById('tfootGaram').innerHTML='';
  GAR_CACHE=[]; GAR_SHOW_ALL=false;
  toggleVis(0,0);
}
function setYearState(){
  const y=getYear(), ok=isValidYear(y);
  const tg=document.getElementById('tahunGaram'); if(tg) tg.textContent=ok?y:'-';
  pick.disabled=!ok; addBtn.disabled=!ok; clrBtn.disabled=false;
  const btnUpload=document.getElementById('btnUpload'); if(btnUpload) btnUpload.disabled=!ok;
  if(!ok){clearViews(); setGlobal(false,'');}
}
document.addEventListener('DOMContentLoaded',()=>{const y=document.getElementById('tahun'); if(y) y.value=''; setYearState();});

/* ====== XLSX utils ====== */
function readXlsx(f){return new Promise((res,rej)=>{const r=new FileReader();r.onload=e=>{try{res(XLSX.read(e.target.result,{type:'array'}));}catch(err){rej(err)}};r.onerror=rej;r.readAsArrayBuffer(f);});}
function sheetToRows(ws){
  // Cari baris header terbaik (banyak isi) di 8 baris awal
  const range=XLSX.utils.decode_range(ws['!ref']); let best=0,score=-1;
  for(let r=0;r<Math.min(8,(range.e.r-range.s.r+1));r++){
    let s=0; for(let c=range.s.c;c<=range.e.c;c++){ const v=ws[XLSX.utils.encode_cell({r,c})]?.v; if(v!==undefined&&String(v).trim()!=='') s++; }
    if(s>score){score=s;best=r;}
  }
  const hdr=XLSX.utils.sheet_to_json(ws,{header:1,raw:true,defval:''})[best]||[];
  return XLSX.utils.sheet_to_json(ws,{defval:'',header:hdr,range:best+1});
}

/* ====== Template (download) ====== */
function wbDownload(name,sheets){ const wb=XLSX.utils.book_new(); sheets.forEach(s=>{ const ws=XLSX.utils.aoa_to_sheet(s.aoa); if(s.widths) ws['!cols']=s.widths.map(w=>({wch:w})); XLSX.utils.book_append_sheet(wb,ws,s.name); }); XLSX.writeFile(wb,name); }
function tplGaram(){return {name:'Data Garam',widths:[6,26,16,16,16,18],aoa:[['No','Kab/Kota','L Total (Ha)','Σ Kelompok','Σ Petambak','Σ Prod (Ton)']]};}
document.getElementById('btnDownloadTemplates').onclick=()=>wbDownload('Data Garam.xlsx',[tplGaram()]);

/* ====== Mapping row ====== */
function mapRow(r){
  const get=(...keys)=>{ for(const k of keys){ if(r[k]!=null && String(r[k]).trim()!=='') return r[k]; if(r[String(k).toLowerCase()]!=null && String(r[String(k).toLowerCase()]).trim()!=='') return r[String(k).toLowerCase()]; } return ''; };
  return {
    kab: get('Kab/Kota','Kab / Kota','Kabupaten/Kota','Kabupaten','Kota','Wilayah'),
    ltotal: get('L Total (Ha)','L Total','Luas Lahan (Ha)','Luas Lahan','Luas (Ha)','Lahan (Ha)','Lahan'),
    kelompok: get('Σ Kelompok','Kelompok','Jml Kelompok','Jumlah Kelompok'),
    petambak: get('Σ Petambak','Jumlah Petambak','Petambak','Jumlah Petani'),
    produksi: get('Σ Prod (Ton)','Volume Produksi (Ton)','Produksi (Ton)','Produksi','Volume Produksi'),
  };
}

/* ====== SAVE helper (relatif, seragam) ====== */
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
  }catch(e){ setStatusProgress(''); flashSave('Gagal menyimpan data.','#dc2626'); return {ok:false,error:String(e)}; }
}

/* ====== RENDERER GARAN ====== */
function renderGaram(list){
  GAR_CACHE = list.slice();
  const tb=document.getElementById('tbodyGaram'), tf=document.getElementById('tfootGaram');

  if(!GAR_CACHE.length){
    tb.innerHTML='<tr><td colspan="6">Belum ada data.</td></tr>'; tf.innerHTML='';
    toggleVis(0,0); return;
  }

  const view = withLimit(GAR_CACHE, GAR_SHOW_ALL);

  let html=''; let sumL=0,sumK=0,sumP=0,sumV=0;
  view.forEach((r,i)=>{
    const l=parseNumID(r.ltotal)||0;
    const k=parseNumID(r.kelompok)||0;
    const p=parseNumID(r.petambak)||0;
    const v=parseNumID(r.produksi)||0;
    sumL+=l; sumK+=k; sumP+=p; sumV+=v;
    html += `<tr>
      <td class="text-right">${i+1}</td>
      <td>${r.kab||''}</td>
      <td class="text-right">${fmt(l)}</td>
      <td class="text-right">${fmt(k)}</td>
      <td class="text-right">${fmt(p)}</td>
      <td class="text-right">${fmt(v)}</td>
    </tr>`;
  });

  tb.innerHTML = html;
  const grandL = GAR_CACHE.reduce((a,x)=>a+(parseNumID(x.ltotal)||0),0);
  const grandK = GAR_CACHE.reduce((a,x)=>a+(parseNumID(x.kelompok)||0),0);
  const grandP = GAR_CACHE.reduce((a,x)=>a+(parseNumID(x.petambak)||0),0);
  const grandV = GAR_CACHE.reduce((a,x)=>a+(parseNumID(x.produksi)||0),0);
  tf.innerHTML = `<tr class="row-total">
    <td class="text-center" colspan="2">TOTAL</td>
    <td class="text-right">${fmt(grandL)}</td>
    <td class="text-right">${fmt(grandK)}</td>
    <td class="text-right">${fmt(grandP)}</td>
    <td class="text-right">${fmt(grandV)}</td>
  </tr>`;

  toggleVis(GAR_CACHE.length, MAX_INITIAL_ROWS);
}
document.getElementById('btnShowAllGaram').onclick=()=>{ GAR_SHOW_ALL=true; renderGaram(GAR_CACHE); document.getElementById('btnShowAllGaram').style.display='none'; document.getElementById('btnHideGaram').style.display='inline-flex'; };
document.getElementById('btnHideGaram').onclick=()=>{ GAR_SHOW_ALL=false; renderGaram(GAR_CACHE); document.getElementById('btnHideGaram').style.display='none'; document.getElementById('btnShowAllGaram').style.display='inline-flex'; };

/* ====== Upload → parse → simpan → render ====== */
document.getElementById('btnUpload').onclick=async()=>{
  const tahun=getYear();
  if(!isValidYear(tahun)){ flashSave('Tahun wajib 4 digit (2000–2100).','#dc2626'); flashYear('Tahun tidak valid. Format YYYY 2000–2100.','#dc2626'); return; }
  document.getElementById('tahunGaram').textContent=tahun;
  if((bag.files||[]).length===0){ flashSave('Pilih minimal satu file.','#dc2626'); return; }

  clearViews(); setStatusProgress('Memproses file...'); notes.innerHTML='';
  let rows=[]; let handled=false; let parsedCount=0;

  for(const f of bag.files){
    try{
      const wb=await readXlsx(f);
      wb.SheetNames.forEach(name=>{
        const ws=wb.Sheets[name]; if(!ws||!ws['!ref']) return;
        const arr=sheetToRows(ws).filter(r=>Object.values(r).some(v=>String(v).trim()!==''));
        if(arr.length){ rows=rows.concat(arr); handled=true; parsedCount += arr.length; }
      });
    }catch(e){ note(`NOTED: Gagal membaca ${f.name}: ${e.message||e}`, false); }
  }
  if(!handled || rows.length===0){ flashSave('Tidak ada data terbaca.','#dc2626'); setStatusProgress(''); return; }

  // Normalisasi & render UI sesuai urutan input
  const clean = rows.map(mapRow).filter(r => String(r.kab||'').trim()!=='' || String(r.produksi||'').trim()!=='');
  renderGaram(clean);
  note(`Parsed ${parsedCount} baris dari file Excel.`, true);

  // SIMPAN ke DB
  const payload = clean.map(r=>({
    tahun:+tahun,
    kab_kota: String(r.kab||''),
    // kompat lama
    luas_lahan_ha: parseNumID(r.ltotal)||0,
    jumlah_petambak: parseNumID(r.petambak)||0,
    volume_produksi_ton: parseNumID(r.produksi)||0,
    // kolom header excel (baru)
    l_total_ha: parseNumID(r.ltotal)||0,
    jumlah_kelompok: parseNumID(r.kelompok)||0,
    sigma_petambak: parseNumID(r.petambak)||0,
    sigma_prod_ton: parseNumID(r.produksi)||0
  }));
  await saveRows('kpp_garam', payload);
  flashSave(`Data tahun ${tahun} diproses ✓`,'#059669');
  setStatusProgress('');

  await loadFromDB(tahun);
};

/* ====== Viewer (fetch dari API) ====== */
async function loadFromDB(year, seq){
  if(typeof seq!=='number') seq=++YEAR_LOAD_SEQ; const mySeq=seq;
  try{
    if (mySeq !== YEAR_LOAD_SEQ) return;
    document.getElementById('tahunGaram').textContent=year;

    const resp = await fetch(`api/kpp_fetch.php?tahun=${encodeURIComponent(year)}&_=${Date.now()}`, {cache:'no-store'});
    const p = await resp.json(); if (mySeq !== YEAR_LOAD_SEQ) return;

    clearViews();
    if(!p || p.ok !== true){ setGlobal(false, year); flashYear(`Tidak ada data untuk tahun ${year}.`,'#dc2626'); return; }

    const rows = Array.isArray(p.garam)? p.garam : [];
    const norm = rows.map(r=>({
      kab: (r.kab_kota||''),
      ltotal: parseNumID(r.l_total_ha ?? r.luas_lahan_ha),
      kelompok: parseNumID(r.jumlah_kelompok ?? r.kelompok ?? r.sigma_kelompok),
      petambak: parseNumID(r.sigma_petambak ?? r.jumlah_petambak),
      produksi: parseNumID(r.sigma_prod_ton ?? r.volume_produksi_ton),
    }));

    renderGaram(norm);
    const hasAny = norm.length>0;
    setGlobal(!!hasAny, year);
    flashYear(hasAny ? 'Data tersedia ✓' : 'Tidak ada data.', hasAny?'#059669':'#dc2626', 2500);
  }catch(e){
    if (mySeq !== YEAR_LOAD_SEQ) return;
    console.error(e); setGlobal(false, year); flashYear(`Error mengambil data tahun ${year}`,'#dc2626');
  }
}

/* ====== Tahun input binding + reset ====== */
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

