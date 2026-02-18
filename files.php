<?php
// files.php — File Manager (khusus admin)
require_once __DIR__.'/auth_guard.php';  // cek login & fungsi guard
require_login();                         // harus login dulu
require_admin();                         // lalu harus admin

require_once __DIR__.'/protected_template.php'; 
start_protected_page('File Manager', 'files');
?>
<style>
:root{--navy:#0b2239;--navy2:#091a2e;--ink:#0f172a;--slate:#64748b;--line:#d1dae5;--row:#fff;--row2:#f9fafc;--hover:#eef3f9;--accent:#14b8a6;}
.panel{background:#fff;border-radius:16px;padding:16px;box-shadow:0 8px 24px rgba(2,8,20,.06)}
.input,.select{width:100%;border:1.5px solid #d7dee6;border-radius:10px;padding:.52rem .7rem;background:#fff;font-size:.95rem}
.input:focus,.select:focus{outline:none;border-color:#93c5fd;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border-radius:10px;padding:.42rem .7rem;font-weight:700;cursor:pointer;transition:.15s;border:1.5px solid #e3e9f0;background:#fff;color:var(--ink);font-size:.9rem}
.btn:hover{background:#f3f6fa}.btn-sm{padding:.32rem .56rem;font-size:.82rem;border-radius:8px}
.btn-primary{background:linear-gradient(135deg,var(--accent),#22d3ee);color:#fff;border-color:transparent;box-shadow:0 4px 14px rgba(20,184,166,.22)}
.btn-primary:hover{filter:brightness(.96)}
.muted{color:var(--slate)}
.panel-header-inline{display:flex;align-items:center;justify-content:space-between;gap:10px}

/* TABLE: navy header + full borders */
#files-table{width:100%;border-collapse:separate!important;border-spacing:0!important;border-radius:12px;overflow:hidden}
#files-table thead th{background:var(--navy);color:#fff;font-weight:800;padding:12px 14px;font-size:.9rem;border:1px solid #0e2e4d;border-bottom:3px solid var(--navy2);text-align:left}
#files-table thead th:first-child{text-align:center}
#files-table tbody td{padding:11px 14px;border:1px solid var(--line);font-size:.94rem;vertical-align:middle}
#files-table tbody td:first-child{text-align:center;font-weight:800;width:56px}
#files-table tbody tr:nth-child(even){background:var(--row2)}
#files-table tbody tr:nth-child(odd){background:var(--row)}
#files-table tbody tr:hover{background:var(--hover)}
#files-table tbody tr.sel{background:#e9f0fb!important;box-shadow:inset 3px 0 0 var(--navy)}
/* action icons kecil */
.action{display:inline-flex;gap:6px;justify-content:flex-end}
.act{border:none;background:none;padding:4px;margin:0 2px;cursor:pointer;font-size:15px;opacity:.9;transition:.15s}
.act:hover{opacity:1;transform:scale(1.15)}
.act.dl{color:#0ea5a5}.act.ren{color:#f59e0b}.act.del{color:#ef4444}

/* bulk & pager (tetap dipakai files.js) */
.bulkbar{position:absolute;right:18px;top:-18px;transform:translateY(-100%);display:none;gap:8px;align-items:center}
.bulkbar.show{display:flex}
.bulk-chip{background:#fff;border:1.6px solid #0ea5a5;color:#0ea5a5;border-radius:999px;padding:.28rem .6rem;font-weight:700;font-size:.82rem}
.pager{display:flex;align-items:center;justify-content:flex-end;gap:6px;margin-top:12px}
.page-btn{min-width:36px;height:36px;border:1.6px solid #e3e9f0;background:#fff;border-radius:9px;font-weight:700;font-size:.88rem;cursor:pointer}
.page-btn:hover{background:#eef3f9}.page-btn.active{background:var(--navy);color:#fff;border-color:var(--navy)}

/* upload modal: compact */
.modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;z-index:60}
.modal.show{display:flex}.modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.45)}
.modal-card{position:relative;background:#fff;border-radius:14px;width:100%;max-width:460px;box-shadow:0 10px 30px rgba(2,8,20,.2);display:flex;flex-direction:column;max-height:82vh}
.modal-header{padding:12px 14px 6px}.modal-title{font-weight:800;font-size:1rem}
.modal-body{padding:8px 14px;overflow:auto}.modal-footer{padding:12px 14px;display:flex;justify-content:flex-end;gap:8px;border-top:1.6px solid #e3e9f0}
.row{display:grid;grid-template-columns:1fr 1fr;gap:10px}@media(max-width:560px){.row{grid-template-columns:1fr}}
.chip{display:inline-flex;align-items:center;gap:6px;border:1.6px solid #e3e9f0;background:#fff;border-radius:999px;padding:3px 9px;font-size:.76rem;font-weight:700}
.chip .dot{width:7px;height:7px;border-radius:999px;background:#0ea5a5}
.dz{border:1.6px dashed #e3e9f0;border-radius:10px;padding:10px;text-align:center;cursor:pointer;background:#fff}
.dz.drag{border-color:#0ea5a5;background:#ecfeff}
.pill-list{border:1.6px solid #e3e9f0;border-radius:10px;overflow:auto;max-height:45vh;background:#fff}
.filepill{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center;padding:9px 11px;font-size:.88rem;border-bottom:1.6px solid #e3e9f0}
.filepill:last-child{border-bottom:none}
.filepill .nm{font-weight:700;color:var(--ink);line-height:1.2}
.filepill .meta{font-size:.76rem;color:var(--slate);margin-top:2px}
.filepill .progress{height:4px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:6px}
.filepill .bar{height:100%;width:0%;background:#0ea5a5}
</style>

<section class="panel mb-3">
  <div class="flex flex-col gap-3">
    <div class="panel-header-inline">
      <h2 class="font-semibold text-slate-800 text-lg">Manajemen File</h2>
      <div class="flex items-center gap-2">
        <button class="btn btn-sm" id="btn-reset" type="button" title="Reset"><i class="fa-solid fa-rotate-left"></i><span class="hidden md:inline"> Reset</span></button>
        <button class="btn btn-sm" id="btn-toggle-select" type="button" title="Mode pilih"><i class="fa-regular fa-square-check"></i></button>
        <button class="btn btn-primary" id="btn-open-upload" type="button" title="Upload"><i class="fa-solid fa-upload"></i> <span class="hidden md:inline">Upload</span></button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Pencarian</label>
        <input type="text" id="search-input" class="input" placeholder="Cari nama/bidang/tahun (realtime)">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Bidang</label>
        <select id="bidang-filter" class="select">
          <option value="">Semua Bidang</option>
          <option value="tangkap">Perikanan Tangkap</option>
          <option value="budidaya">Perikanan Budidaya</option>
          <option value="kpp">KPP</option>
          <option value="pengolahan">Pengolahan & Pemasaran</option>
          <option value="ekspor">Ekspor Perikanan</option>
          <option value="investasi">Investasi KP</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-2">Tahun (YYYY)</label>
        <input type="text" id="tahun-filter" class="input" placeholder="YYYY" pattern="\d{4}">
      </div>
    </div>

    <div class="flex justify-between items-center">
      <span id="active-hint" class="text-xs px-3 py-1 bg-slate-100 text-slate-600 rounded-full"></span>
    </div>
  </div>
</section>

<section class="panel" style="position:relative">
  <div id="bulkbar" class="bulkbar">
    <span class="bulk-chip"><span id="bulk-count">0</span> dipilih</span>
    <button class="btn btn-danger btn-sm" id="btn-bulk-delete" type="button" title="Hapus terpilih"><i class="fa-solid fa-trash"></i> <span class="hidden sm:inline">Hapus</span></button>
    <button class="btn btn-ghost btn-sm" id="btn-bulk-cancel" type="button">Batal</button>
  </div>

  <div id="file-table-container">
    <table id="files-table">
      <thead>
        <tr>
          <th style="width:56px;text-align:center">No.</th>
          <th>Nama</th>
          <th>Bidang</th>
          <th style="width:92px">Tahun</th>
          <th style="width:120px">Ukuran</th>
          <th style="width:210px">Diunggah</th>
          <th style="text-align:right;width:110px">Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="pager" class="pager"></div>
  </div>
</section>

<!-- Upload Modal -->
<div id="upload-modal" class="modal" aria-hidden="true">
  <div class="modal-backdrop"></div>
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Upload File</h3>
      <div class="tiny" id="modal-badges">
        <span class="chip" title="Tahun aktif"><span class="dot"></span><span id="badge-tahun">—</span></span>
        <span class="chip" title="Bidang aktif" style="margin-left:6px"><i class="fa-regular fa-folder"></i><span id="badge-bidang">—</span></span>
      </div>
    </div>
    <div class="modal-body">
      <div class="row">
        <div>
          <label class="block tiny mb-1">Bidang *</label>
          <select id="modal-bidang" class="select">
            <option value="">Semua Bidang</option>
            <option value="tangkap">Perikanan Tangkap</option>
            <option value="budidaya">Perikanan Budidaya</option>
            <option value="kpp">KPP</option>
            <option value="pengolahan">Pengolahan & Pemasaran</option>
            <option value="ekspor">Ekspor Perikanan</option>
            <option value="investasi">Investasi KP</option>
          </select>
        </div>
        <div>
          <label class="block tiny mb-1">Tahun (YYYY) *</label>
          <input type="text" id="modal-tahun" class="input" placeholder="YYYY" pattern="\d{4}">
        </div>
      </div>

      <div class="mt-3">
        <label class="block tiny mb-1">Pilih File</label>
        <div id="dropzone" class="dz">
          <button class="btn btn-ghost btn-sm" id="btn-choose" type="button"><i class="fa-solid fa-file"></i> Pilih File</button>
          <input type="file" id="modal-files" multiple hidden>
          <div class="muted tiny mt-1">Tarik & letakkan file ke sini atau klik “Pilih File”.</div>
        </div>
        <div id="pill-list" class="pill-list" style="margin-top:8px"></div>
      </div>
    </div>

    <div class="modal-footer">
      <button class="btn" id="btn-upload-cancel" type="button">Batal</button>
      <button class="btn btn-primary" id="btn-upload-submit" type="button"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<!-- Prefill open/close modal -->
<script>
document.addEventListener('click',e=>{
  const open=e.target.closest('#btn-open-upload'); if(!open) return;
  const m=document.getElementById('upload-modal');
  const bSel=document.getElementById('modal-bidang'), tInp=document.getElementById('modal-tahun');
  const fB=document.getElementById('bidang-filter'), fT=document.getElementById('tahun-filter');
  bSel.value=fB?.value||''; tInp.value=fT?.value||'';
  document.getElementById('badge-tahun').textContent=tInp.value||'—';
  document.getElementById('badge-bidang').textContent=bSel.options[bSel.selectedIndex]?.text||'—';
  m.classList.add('show'); document.body.style.overflow='hidden';
});
document.getElementById('upload-modal').addEventListener('click',e=>{
  if(e.target.classList.contains('modal-backdrop')||e.target.id==='btn-upload-cancel'){
    e.currentTarget.classList.remove('show'); document.body.style.overflow='auto';
  }
});
</script>

<!-- Enhancement: dblclick open + rename fallback (kelas .act.* dibuat oleh files.js v14) -->
<script>
document.addEventListener('dblclick',async e=>{
  const tr=e.target.closest('#files-table tbody tr'); if(!tr) return;
  const id=tr.dataset.id; if(!id) return;
  try{
    const r=await fetch(`/api/files.php?action=download&id=${encodeURIComponent(id)}`,{credentials:'same-origin'});
    const j=await r.json(); if(!j.success) throw new Error(j.message||'Gagal membuka file');
    window.open(j.url,'_blank'); // biarkan browser preview (pdf/img/docx via viewer)
  }catch(err){ alert(err.message); }
});

// rename kecil (kalau files.js sudah render <button class="act ren" data-id data-name>)
document.addEventListener('click',async e=>{
  const btn=e.target.closest('.act.ren,[data-rename]'); if(!btn) return;
  const tr=btn.closest('tr'); const id=btn.dataset.id||tr?.dataset.id; const oldName=btn.dataset.name||tr?.dataset.name||tr?.querySelector('td:nth-child(2)')?.textContent?.trim();
  const name=prompt('Ubah nama file:',oldName||''); if(!name||name===oldName) return;
  try{
    const fd=new FormData(); fd.append('action','rename'); fd.append('id',id); fd.append('new_name',name);
    const r=await fetch('/api/files.php',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json(); if(!j.success) throw new Error(j.message||'Rename gagal');
    location.reload();
  }catch(err){ alert(err.message); }
});
</script>

<!-- JS utama (render list, aksi, pagination, upload, dll) -->
<script src="/js/files.js?v=14" defer></script>
<?php end_protected_page(); ?>
