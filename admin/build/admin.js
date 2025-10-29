(function($){
  const root = $('#vc-appoint-admin');
  const state = { tab: 'calendar', date: new Date().toISOString().slice(0,10), bookings: [], staff: '', schedules: [], exceptions: [], settings: {} };

  function tabs(){
    const names = [
      ['calendar','Kalender'],
      ['schedules','Schedules'],
      ['settings','Einstellungen']
    ];
    const wrap = $('<div class="vc-tabs"></div>');
    names.forEach(([key,label])=>{
      const t = $('<div class="vc-tab">'+label+'</div>').toggleClass('active', state.tab===key).on('click', ()=>{ state.tab = key; render(); });
      wrap.append(t);
    });
    return wrap;
  }

  async function fetchBookings(){
    const q = new URLSearchParams({date: state.date});
    const url = VC_APPOINT_ADMIN.rest.url + 'admin/bookings?' + q.toString();
    const res = await fetch(url);
    state.bookings = await res.json();
  }

  async function fetchSchedules(){
    if (!state.staff){ state.schedules=[]; state.exceptions=[]; return; }
    const url = VC_APPOINT_ADMIN.rest.url + 'admin/schedules?staff='+encodeURIComponent(state.staff);
    const res = await fetch(url);
    const data = await res.json();
    state.schedules = data.ranges || [];
    state.exceptions = data.exceptions || [];
  }

  async function saveSchedules(){
    const payload = { staff: Number(state.staff||0), ranges: state.schedules };
    const res = await fetch(VC_APPOINT_ADMIN.rest.url + 'admin/schedules', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    if (!res.ok){ alert('Fehler beim Speichern'); return; }
    alert('Gespeichert');
  }

  async function saveSettings(){
    const payload = state.settings;
    const res = await fetch(VC_APPOINT_ADMIN.rest.url + 'admin/settings', {
      method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)
    });
    if (!res.ok){ alert('Fehler'); return; }
    alert('Gespeichert');
  }

  function calendarPanel(){
    const panel = $('<div class="vc-panel"></div>');
    const top = $('<div class="vc-row"></div>');
    const date = $('<input type="date" class="vc-input">').val(state.date).on('input', e=>{state.date=e.target.value});
    const reload = $('<button class="vc-btn">Laden</button>').on('click', async ()=>{ await fetchBookings(); render(); });
    top.append(date, reload);

    const table = $('<table class="vc-table"><thead><tr><th>Zeit</th><th>Service</th><th>Staff</th><th>Kunde</th><th>Status</th></tr></thead><tbody></tbody></table>');
    const tb = table.find('tbody');
    (state.bookings||[]).forEach(b=>{
      const tr = $('<tr></tr>');
      tr.append($('<td></td>').text((b.start||'').slice(11,16)+'–'+(b.end||'').slice(11,16)));
      tr.append($('<td></td>').text('#'+(b.service_id||'-')));
      tr.append($('<td></td>').text('#'+(b.staff_id||'-')));
      tr.append($('<td></td>').text((b.first_name||'')+' '+(b.last_name||'')+' <'+(b.email||'')+'>'));
      tr.append($('<td></td>').text(b.status||''));
      tb.append(tr);
    });

    panel.append(top, $('<div style="margin-top:8px"></div>'), table);
    return panel;
  }

  function schedulesPanel(){
    const panel = $('<div class="vc-panel"></div>');
    const top = $('<div class="vc-row"></div>');
    const staff = $('<input class="vc-input" placeholder="Staff-ID">').val(state.staff).on('input', e=>{state.staff=e.target.value});
    const load = $('<button class="vc-btn">Laden</button>').on('click', async ()=>{await fetchSchedules(); render();});
    const save = $('<button class="vc-btn">Speichern</button>').on('click', async ()=>{await saveSchedules();});
    top.append(staff, load, save);

    const days = ['So','Mo','Di','Mi','Do','Fr','Sa'];
    const list = $('<div></div>');
    for (let w=0; w<7; w++){
      const r = state.schedules.find(x=>Number(x.weekday)===w) || {weekday:w,start:'09:00',end:'17:00',breaks:'[]'};
      const wrap = $('<div class="vc-row"></div>');
      const label = $('<div style="width:40px"></div>').text(days[w]);
      const start = $('<input class="vc-input" placeholder="Start">').val(r.start).on('input', e=>{r.start=e.target.value; upsert(w,r)});
      const end = $('<input class="vc-input" placeholder="Ende">').val(r.end).on('input', e=>{r.end=e.target.value; upsert(w,r)});
      wrap.append(label, start, end);
      list.append(wrap);
    }

    function upsert(w, row){
      const idx = state.schedules.findIndex(x=>Number(x.weekday)===w);
      if (idx>=0) state.schedules[idx] = row;
      else state.schedules.push(row);
    }

    panel.append(top, list);
    return panel;
  }

  function settingsPanel(){
    const panel = $('<div class="vc-panel"></div>');
    const fields = [
      ['salon_name','Salon Name'],
      ['location','Ort/Adresse'],
      ['from_email','Absender E-Mail'],
      ['font','--vc-font'],
      ['radius','--vc-radius (z.B. 14px)'],
      ['bg','--vc-bg'],
      ['surface','--vc-surface'],
      ['text','--vc-text'],
      ['accent','--vc-accent'],
      ['muted','--vc-muted'],
      ['border','--vc-border']
    ];
    const form = $('<div class="vc-row"></div>');
    fields.forEach(([key,label])=>{
      const input = $('<input class="vc-input">').attr('placeholder', label).val(state.settings[key]||'').on('input', e=>{state.settings[key]=e.target.value});
      form.append(input);
    });
    const save = $('<button class="vc-btn">Einstellungen speichern</button>').on('click', saveSettings);
    panel.append(form, $('<div style="margin-top:8px"></div>'), save);
    return panel;
  }

  async function init(){
    await fetchBookings();
    render();
  }

  function render(){
    root.empty();
    root.append(tabs());
    if (state.tab==='calendar') root.append(calendarPanel());
    if (state.tab==='schedules') root.append(schedulesPanel());
    if (state.tab==='settings') root.append(settingsPanel());
  }

  init();
})(jQuery);
