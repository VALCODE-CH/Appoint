(function(){
  const root = document.getElementById('vc-appoint-widget');
  if (!root) return;

  const state = {
    step: 1,
    service: VC_APPOINT.service || '',
    staff: VC_APPOINT.staff || '',
    duration: Number(VC_APPOINT.duration || 30),
    date: new Date().toISOString().slice(0,10),
    time: '',
    email: '', phone:'', first_name:'', last_name:'', notes:'',
    slots: []
  };

  function el(tag, attrs={}, children=[]){
    const n = document.createElement(tag);
    Object.entries(attrs).forEach(([k,v]) => {
      if (k === 'class') n.className = v;
      else if (k === 'onclick') n.onclick = v;
      else if (k === 'oninput') n.oninput = v;
      else if (k === 'value') n.value = v;
      else n.setAttribute(k,v);
    });
    (Array.isArray(children)?children:[children]).forEach(c => {
      if (c==null) return;
      if (typeof c === 'string') n.appendChild(document.createTextNode(c));
      else n.appendChild(c);
    });
    return n;
  }

  async function fetchAvailability(){
    if (!state.staff){
      state.slots = [];
      render(); return;
    }
    const q = new URLSearchParams({date: state.date, service: state.service || '0', staff: state.staff, duration: String(state.duration)});
    const url = VC_APPOINT.rest.url + 'availability?' + q.toString();
    const res = await fetch(url);
    const data = await res.json();
    state.slots = data.slots || [];
    render();
  }

  async function submitBooking(){
    const res = await fetch(VC_APPOINT.rest.url + 'book', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({
        email: state.email, phone: state.phone,
        first_name: state.first_name, last_name: state.last_name,
        service_id: Number(state.service||0),
        staff_id: Number(state.staff||0),
        date: state.date, time: state.time,
        duration_minutes: Number(state.duration||30),
        notes: state.notes
      })
    });
    const data = await res.json().catch(()=>({}));
    if (!res.ok){
      alert('Fehler: ' + (data.message || res.statusText));
      return;
    }
    state.step = 5;
    state._confirmation = data;
    render();
  }

  function renderStep1(){
    return el('div', {class:'vc-card'}, [
      el('h3', {}, ['1/5 Service & Mitarbeiter']),
      el('div', {class:'vc-row'}, [
        el('input', {class:'vc-input', placeholder:'Service-ID', value: state.service, oninput: e=>{state.service=e.target.value}}),
        el('input', {class:'vc-input', placeholder:'Staff-ID', value: state.staff, oninput: e=>{state.staff=e.target.value}}),
        el('input', {class:'vc-input', placeholder:'Dauer (Minuten)', value: state.duration, oninput: e=>{state.duration=Number(e.target.value||30)}}),
      ]),
      el('div', {class:'vc-row'}, [
        el('button', {class:'vc-btn', onclick: ()=>{state.step=2; fetchAvailability();}}, ['Weiter →'])
      ])
    ]);
  }

  function renderStep2(){
    return el('div', {class:'vc-card'}, [
      el('h3', {}, ['2/5 Datum wählen']),
      el('input', {type:'date', class:'vc-input', value: state.date, oninput: e=>{state.date=e.target.value;}}),
      el('div', {class:'vc-row', style:'margin-top:12px'}, [
        el('button', {class:'vc-btn', onclick: ()=>{state.step=1; render();}}, ['← Zurück']),
        el('button', {class:'vc-btn', onclick: ()=>{state.step=3; fetchAvailability();}}, ['Weiter →'])
      ])
    ]);
  }

  function renderStep3(){
    const grid = el('div', {class:'vc-grid'}, state.slots.map(s=>{
      const b = el('div', {class:'vc-time' + (state.time===s?' active':''), onclick:()=>{state.time=s; render();}}, [s]);
      return b;
    }));
    return el('div', {class:'vc-card'}, [
      el('h3', {}, ['3/5 Uhrzeit wählen']),
      el('div', {}, [grid, state.slots.length?null:el('div', {class:'vc-badge'}, ['Keine Slots gefunden (Staff/Schedule prüfen)']) ]),
      el('div', {class:'vc-row', style:'margin-top:12px'}, [
        el('button', {class:'vc-btn', onclick: ()=>{state.step=2; render();}}, ['← Zurück']),
        el('button', {class:'vc-btn', onclick: ()=>{ if(!state.time){alert('Bitte Uhrzeit wählen');return;} state.step=4; render();}}, ['Weiter →'])
      ])
    ]);
  }

  function renderStep4(){
    return el('div', {class:'vc-card'}, [
      el('h3', {}, ['4/5 Deine Daten']),
      el('div', {class:'vc-row'}, [
        el('input', {class:'vc-input', placeholder:'Vorname', value: state.first_name, oninput: e=>{state.first_name=e.target.value}}),
        el('input', {class:'vc-input', placeholder:'Nachname', value: state.last_name, oninput: e=>{state.last_name=e.target.value}}),
      ]),
      el('div', {class:'vc-row'}, [
        el('input', {class:'vc-input', placeholder:'E-Mail', value: state.email, oninput: e=>{state.email=e.target.value}}),
        el('input', {class:'vc-input', placeholder:'Telefon (optional)', value: state.phone, oninput: e=>{state.phone=e.target.value}}),
      ]),
      el('textarea', {class:'vc-input', placeholder:'Notizen (optional)', oninput: e=>{state.notes=e.target.value}}, [state.notes]),
      el('div', {class:'vc-row', style:'margin-top:12px'}, [
        el('button', {class:'vc-btn', onclick: ()=>{state.step=3; render();}}, ['← Zurück']),
        el('button', {class:'vc-btn', onclick: submitBooking}, ['Buchen ✓'])
      ])
    ]);
  }

  function renderStep5(){
    const c = state._confirmation || {};
    return el('div', {class:'vc-card vc-success'}, [
      el('h3', {}, ['Buchung bestätigt ✔']),
      el('div', {}, ['Buchungs-ID: ', String(c.booking_id || '-')]),
      el('div', {}, ['Start: ', String(c.start || '-')]),
      el('div', {}, ['Ende: ', String(c.end || '-')]),
      el('div', {class:'vc-row', style:'margin-top:12px'}, [
        el('button', {class:'vc-btn', onclick: ()=>{state.step=1; render();}}, ['Neue Buchung'])
      ])
    ]);
  }

  function render(){
    root.innerHTML = '';
    let node;
    if (state.step===1) node = renderStep1();
    else if (state.step===2) node = renderStep2();
    else if (state.step===3) node = renderStep3();
    else if (state.step===4) node = renderStep4();
    else node = renderStep5();
    root.appendChild(node);
  }

  render();
})();
