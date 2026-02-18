<?php
// pengaturan-akun.php — VIEW (tanpa Departemen & Bio)
// (versi ini menambahkan: tombol Segarkan bekerja via /api/login_audit_list.php,
//  tombol navigasi pakai ikon ⟨ ⟩ agar hemat tempat, dan render lokasi/ikon status)

require_once __DIR__ . '/auth_guard.php';  // 🔐 cek login
require_login();                           // wajib login dulu

require_once __DIR__ . '/protected_template.php';
require_once __DIR__ . '/api/db.php';

start_protected_page('Pengaturan Akun', 'akun');
?>

<style>
  .glass{background:#fff;border-radius:18px;box-shadow:0 12px 30px rgba(2,8,20,.06)}
  .card-head{padding-bottom:.6rem;border-bottom:1px solid #e5e7eb;margin-bottom:.8rem}
  .nice-input{border:1px solid #e2e8f0;border-radius:12px;padding:.65rem .8rem;width:100%;transition: box-shadow .15s, border-color .15s}
  .nice-input:focus{outline:none;border-color:#94d9ff;box-shadow:0 0 0 4px rgba(0,188,212,.15)}
  .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.15rem .55rem;border-radius:999px;font-size:.72rem}
  .badge.good{background:#ecfdf5;color:#065f46}
  .badge.bad{background:#fef2f2;color:#991b1b}
  .badge.warn{background:#fff7ed;color:#9a3412}
  .badge.info{background:#eef2ff;color:#3730a3}
  .strength{height:8px;border-radius:8px;background:#e5e7eb;overflow:hidden}
  .strength>span{display:block;height:100%;width:0;transition:width .25s ease}
  .toast{position:fixed;right:1rem;bottom:1rem;z-index:60;padding:.9rem 1.1rem;border-radius:14px;color:#0b1b2b;background:linear-gradient(90deg,#e6fffb,#e0f7ff);box-shadow:0 14px 30px rgba(0,0,0,.15);display:none}
  .toast.show{display:block;animation:fadeIn .2s ease}
  @keyframes fadeIn{from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)}}
  .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:36px;height:32px;border-radius:8px;border:1px solid #e5e7eb;background:#fff}
  .icon-btn:disabled{opacity:.45}
</style>

<main class="max-w-7xl mx-auto px-4 py-6 space-y-6">
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- PROFIL -->
    <section class="glass p-5 lg:col-span-2">
      <div class="card-head">
        <h3 class="font-bold text-lg">Profil Pengguna</h3>
        <p class="text-xs text-slate-500">Kelola nama, kontak, dan foto profil</p>
      </div>

      <form id="formProfile" class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-3">
        <div class="md:col-span-2 flex items-center gap-4">
          <label for="avatarFile" class="relative group cursor-pointer">
            <img id="avatarPreview" src="images/avatar-placeholder.png" alt="avatar" class="w-20 h-20 rounded-full object-cover bg-slate-100 border border-slate-200">
            <span class="absolute bottom-0 right-0 bg-white rounded-full p-1 shadow-md text-slate-700 group-hover:scale-105 transform transition">
              <i class="fa-solid fa-camera"></i>
            </span>
            <input id="avatarFile" type="file" accept="image/*" class="hidden"/>
          </label>
          <div>
            <p class="text-sm">Unggah foto profil (JPG/PNG ≤ 1MB)</p>
            <div id="dropZone" class="mt-2 text-xs text-slate-600 border border-dashed border-slate-300 rounded-lg p-2">Drag & drop foto ke sini</div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">Nama Lengkap</label>
          <input id="name" class="nice-input" placeholder="Nama"/>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Email</label>
          <input id="email" class="nice-input bg-gray-50" disabled/>
        </div>

        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-2">Nomor Telepon</label>
          <input id="phone" class="nice-input" placeholder="+62 ..."/>
        </div>

        <div class="md:col-span-2">
          <button type="submit" class="px-4 py-2 rounded-lg font-semibold text-white" style="background:#006f7a">Simpan Profil</button>
          <span id="statusProfile" class="text-sm text-slate-500 ml-2"></span>
        </div>
      </form>
    </section>

    <!-- PASSWORD -->
    <section class="glass p-5">
      <div class="card-head">
        <h3 class="font-bold text-lg">Keamanan: Ganti Password</h3>
        <p class="text-xs text-slate-500">Gunakan kata sandi kuat (≥ 8 karakter)</p>
      </div>

      <form id="formPassword" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">Password Saat Ini</label>
          <input id="curPwd" type="password" class="nice-input"/>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Password Baru</label>
          <input id="newPwd" type="password" class="nice-input"/>
          <div class="strength mt-2" aria-hidden="true"><span id="meter"></span></div>
          <p id="hint" class="text-xs text-slate-500 mt-1">Gabungkan huruf besar, kecil, angka, dan simbol</p>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">Konfirmasi Password Baru</label>
          <input id="newPwd2" type="password" class="nice-input"/>
        </div>
        <button class="w-full px-4 py-2 rounded-lg font-bold text-white" style="background:#dc2626" type="submit">
          <i class="fa-solid fa-key mr-2"></i>Ubah Password
        </button>
        <p id="statusPwd" class="text-sm text-slate-500"></p>
      </form>
    </section>
  </div>

  <!-- AKTIVITAS LOGIN -->
  <section class="glass p-5">
    <div class="card-head flex items-center justify-between">
      <div>
        <h3 class="font-bold text-lg">Aktivitas Login Terakhir</h3>
        <p class="text-xs text-slate-500">Pantau sesi login terbaru untuk keamanan akun</p>
      </div>
      <!-- ikon saja supaya hemat tempat -->
      <button id="refreshLogins" class="icon-btn" title="Segarkan">
        <i class="fa-solid fa-rotate"></i>
      </button>
    </div>

    <div class="overflow-auto">
      <table class="min-w-full text-sm">
        <thead style="background:#0b1b2b;color:#fff" class="text-center">
          <tr>
            <th class="px-3 py-2 border text-left">Waktu</th>
            <th class="px-3 py-2 border text-left">IP</th>
            <th class="px-3 py-2 border text-left">Perangkat / Browser</th>
            <th class="px-3 py-2 border text-left">Lokasi (perkiraan)</th>
            <th class="px-3 py-2 border text-left">Status</th>
          </tr>
        </thead>
        <tbody id="tbodyLogins"></tbody>
      </table>
    </div>

    <div class="flex items-center justify-between mt-3">
      <p id="loginInfo" class="text-xs text-slate-500"></p>
      <!-- ikon ⟨ ⟩ saja -->
      <div class="flex items-center gap-2">
        <button id="prevPage" class="icon-btn" title="Sebelumnya"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="nextPage" class="icon-btn" title="Berikutnya"><i class="fa-solid fa-chevron-right"></i></button>
      </div>
    </div>
  </section>
</main>

<div id="toast" class="toast"><i class="fa-solid fa-circle-check mr-2"></i><span id="toastMsg">Tersimpan</span></div>

<script>
  const el = id => document.getElementById(id);
  const api = (a, opt={}) => fetch(`api.php?action=${a}`, opt).then(r=>r.json());

  // 1) profil + CSRF
  async function loadProfile(){
    const r = await api('profile_get');
    if(!r.ok){ toast(r.error||'Gagal memuat profil'); return; }
    window.CSRF = r.csrf;
    el('name').value  = r.profile.name||'';
    el('email').value = r.profile.email||'';
    el('phone').value = r.profile.phone||'';
    document.getElementById('avatarPreview').src = r.profile.avatar_url;
  }
  loadProfile();

  // helper fetch dengan CSRF
  const Fx = (a, opt={}) => api(a, Object.assign({ headers:{ 'X-CSRF': window.CSRF||'' } }, opt));

  // 2) avatar upload
  const dropZone = document.getElementById('dropZone');
  const avatarFile= document.getElementById('avatarFile');
  const avatarPreview=document.getElementById('avatarPreview');

  ['dragenter','dragover'].forEach(ev=>dropZone?.addEventListener(ev, e=>{e.preventDefault(); dropZone.classList.add('bg-sky-50');}));
  ;['dragleave','drop'].forEach(ev=>dropZone?.addEventListener(ev, e=>{e.preventDefault(); dropZone.classList.remove('bg-sky-50');}));
  dropZone?.addEventListener('drop', e=>{ const f=e.dataTransfer.files?.[0]; if(f) uploadAvatar(f); });
  avatarFile?.addEventListener('change', e=>{ const f=e.target.files?.[0]; if(f) uploadAvatar(f); });

  async function uploadAvatar(file){
    if(!file.type.startsWith('image/')) return toast('File harus gambar');
    if(file.size>1024*1024) return toast('Ukuran maks 1MB');
    const fd = new FormData(); fd.append('avatar', file);
    const r = await fetch('api.php?action=avatar_upload', { method:'POST', headers:{'X-CSRF':window.CSRF}, body:fd }).then(r=>r.json());
    if(!r.ok) return toast(r.error||'Upload gagal');
    avatarPreview.src = r.avatar_url + '?t=' + Date.now();
    toast('Avatar diperbarui');
  }

  // 3) simpan profil
  document.getElementById('formProfile').addEventListener('submit', async e=>{
    e.preventDefault();
    const r = await Fx('profile_save', {
      method:'POST',
      body: JSON.stringify({ name: el('name').value.trim(), phone: el('phone').value.trim() })
    });
    document.getElementById('statusProfile').textContent = r.ok ? 'Profil tersimpan.' : (r.error||'Gagal menyimpan');
    if(r.ok) toast('Profil berhasil disimpan');
  });

  // 4) meter & ganti password
  const meter=document.getElementById('meter'), hint=document.getElementById('hint');
  document.getElementById('newPwd').addEventListener('input', e=>{
    const v=e.target.value||''; let s=0;
    if(v.length>=8) s++; if(/[A-Z]/.test(v)) s++; if(/[a-z]/.test(v)) s++; if(/[0-9]/.test(v)) s++; if(/[^\w]/.test(v)) s++;
    const pct=Math.min(100,s*20); meter.style.width=pct+'%';
    meter.style.background = pct<40?'#fca5a5': pct<80?'#fcd34d':'#86efac';
    hint.textContent = pct<40 ? 'Kata sandi lemah' : pct<80 ? 'Cukup kuat' : 'Kuat';
  });

  document.getElementById('formPassword').addEventListener('submit', async e=>{
    e.preventDefault();
    const r = await Fx('password_change', {
      method:'POST',
      body: JSON.stringify({
        current: el('curPwd').value,
        new1: el('newPwd').value,
        new2: el('newPwd2').value
      })
    });
    document.getElementById('statusPwd').textContent = r.ok ? 'Password berhasil diperbarui.' : (r.error||'Gagal mengubah password');
    if(r.ok){
      el('curPwd').value=el('newPwd').value=el('newPwd2').value='';
      meter.style.width='0'; hint.textContent='Gabungkan huruf besar, kecil, angka, dan simbol';
      toast('Password berhasil diubah');
    }
  });

  // 5) login activity + pagination (pakai /api/login_audit_list.php)
  let curPage=1, pageSize=10, total=0;

  function statusBadge(row){
    if (row.status==='active') return `<span class="badge info"><i class="fa-solid fa-signal"></i>Aktif</span>`;
    if (row.status==='logout') return `<span class="badge warn"><i class="fa-solid fa-right-from-bracket"></i>Logout</span>`;
    if (row.status==='fail' || row.status==='failed') return `<span class="badge bad"><i class="fa-solid fa-circle-xmark"></i>Gagal</span>`;
    return `<span class="badge good"><i class="fa-solid fa-circle-check"></i>Sukses</span>`;
  }

  async function loadLogins(page=1){
    const url = `/api/login_audit_list.php?page=${page}&limit=${pageSize}`;
    let j;
    try{
      j = await fetch(url, {credentials:'same-origin'}).then(r=>r.json());
    }catch(e){
      // fallback ke api lama bila ada
      j = await api(`login_list&page=${page}&size=${pageSize}`);
      if (!j.ok){ renderLoginError('Gagal memuat aktivitas'); return; }
      // adapt struktur lama ke baru agar render tetap jalan
      j = {
        ok:true,
        page:j.page, limit:pageSize, total:j.total||j.items?.length||0,
        data:(j.items||[]).map(x=>({
          time:x.event_time, ip:x.ip, device:x.device, location:x.location, status:x.status||'success', session_id:x.session_id||null
        }))
      };
    }
    if (!j.ok){ renderLoginError('Gagal memuat aktivitas'); return; }

    const tbody = document.getElementById('tbodyLogins'); tbody.innerHTML='';
    const rows = j.data||[];
    if(rows.length===0){
      tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Belum ada data</td></tr>`;
    }else{
      rows.forEach(r=>{
        const tr=document.createElement('tr'); tr.className='hover:bg-slate-50';
        tr.innerHTML = `
          <td class="px-3 py-2 border">${r.time? new Date(r.time).toLocaleString('id-ID',{hour12:false}) : '-'}</td>
          <td class="px-3 py-2 border">${r.ip||'-'}</td>
          <td class="px-3 py-2 border">${r.device||'-'}</td>
          <td class="px-3 py-2 border">${r.location || '<span class="text-slate-400">—</span>'}</td>
          <td class="px-3 py-2 border">${statusBadge(r)}</td>
        `;
        tbody.appendChild(tr);
      });
    }
    curPage = j.page||1; total = j.total||0;
    const maxPage = Math.max(1, Math.ceil((total||0)/(j.limit||pageSize)));
    document.getElementById('loginInfo').textContent = `Halaman ${curPage} dari ${maxPage} • Menampilkan ${rows.length} data`;
    document.getElementById('prevPage').disabled = curPage<=1;
    document.getElementById('nextPage').disabled = curPage>=maxPage;
  }

  function renderLoginError(msg){
    const tbody = document.getElementById('tbodyLogins');
    tbody.innerHTML = `<tr><td colspan="5" class="px-3 py-4 text-center text-red-600">${msg}</td></tr>`;
    document.getElementById('loginInfo').textContent = '';
  }

  document.getElementById('refreshLogins').addEventListener('click', ()=>loadLogins(curPage));
  document.getElementById('prevPage').addEventListener('click', ()=>{ if(curPage>1) loadLogins(curPage-1); });
  document.getElementById('nextPage').addEventListener('click', ()=>{ loadLogins(curPage+1); });
  loadLogins();

  function toast(msg){
    document.getElementById('toastMsg').textContent=msg;
    const t=document.getElementById('toast'); t.classList.add('show');
    setTimeout(()=>t.classList.remove('show'),2200);
  }
</script>

<?php end_protected_page(); ?>
