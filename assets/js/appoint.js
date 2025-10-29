
(function(){
    function q(id){ return document.getElementById(id); }
    function setOptions(select, items, placeholder){
        select.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder || 'Bitte wählen…';
        select.appendChild(opt);
        items.forEach(function(it){
            var o = document.createElement('option');
            o.value = it.value || it.id; o.textContent = it.label || it.name;
            select.appendChild(o);
        });
    }
    function msg(txt, ok){
        var p = q('va_msg'); if(!p) return;
        p.textContent = txt || '';
        p.hidden = !txt;
        p.className = 'va-msg ' + (ok ? 'ok' : 'err');
    }
    function goto(step){
        document.querySelectorAll('.va-step').forEach(function(el){
            el.hidden = el.getAttribute('data-step') !== String(step);
        });
    }
    function enable(el, yes){ if(el){ el.disabled = !yes; } }

    document.addEventListener('DOMContentLoaded', function(){
        var service = q('va_service');
        var worker  = q('va_worker');
        var dateInp = q('va_date');
        var slotSel = q('va_slot');
        var next1   = document.querySelector('.va-step[data-step="1"] .va-next');
        var next2   = document.querySelector('.va-step[data-step="2"] .va-next');
        var prev2   = document.querySelector('.va-step[data-step="2"] .va-prev');
        var prev3   = document.querySelector('.va-step[data-step="3"] .va-prev');
        var form    = q('va-booking');
        var startHidden = q('va_starts_at');

        function refreshNext1(){
            enable(next1, !!service.value && !!worker.value);
        }
        function refreshNext2(){
            enable(next2, !!slotSel.value);
        }

        if(service && worker){
            service.addEventListener('change', function(){
                worker.disabled = true;
                setOptions(worker, [], 'Lade Mitarbeiter…');
                setOptions(slotSel, [], 'Bitte Datum wählen…'); enable(slotSel,false);
                dateInp.value = '';
                fetch(ValcodeAppoint.ajax + '?action=valcode_get_workers&nonce=' + encodeURIComponent(ValcodeAppoint.nonce) + '&service_id=' + encodeURIComponent(service.value), { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        var items = (res && res.success && res.data && res.data.workers) ? res.data.workers : [];
                        items = items.map(function(w){ return { id: w.id, name: w.name }; });
                        setOptions(worker, items, items.length ? 'Bitte wählen…' : 'Keine Mitarbeiter verfügbar');
                        worker.disabled = items.length === 0;
                        refreshNext1();
                    }).catch(function(){
                        setOptions(worker, [], 'Fehler beim Laden'); worker.disabled = true; refreshNext1();
                    });
            });
            worker.addEventListener('change', refreshNext1);
        }

        if(dateInp){
            dateInp.addEventListener('change', function(){
                setOptions(slotSel, [], 'Lade Slots…'); enable(slotSel,false);
                refreshNext2();
                var params = new URLSearchParams();
                params.set('action','valcode_get_slots');
                params.set('nonce', ValcodeAppoint.nonce);
                params.set('service_id', service.value);
                params.set('staff_id', worker.value);
                params.set('date', dateInp.value);
                fetch(ValcodeAppoint.ajax + '?' + params.toString(), { credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if(res && res.success){
                            var items = (res.data.slots || []).map(function(s){ return { value: s.start, label: s.label }; });
                            setOptions(slotSel, items, items.length ? 'Bitte wählen…' : 'Keine freien Zeiten');
                            enable(slotSel, items.length>0);
                        }else{
                            setOptions(slotSel, [], 'Keine Slots gefunden'); enable(slotSel,false);
                        }
                        refreshNext2();
                    }).catch(function(){
                        setOptions(slotSel, [], 'Fehler beim Laden'); enable(slotSel,false); refreshNext2();
                    });
            });
            slotSel.addEventListener('change', function(){
                startHidden.value = slotSel.value;
                refreshNext2();
            });
        }

        if(next1){ next1.addEventListener('click', function(){ goto(2); }); }
        if(prev2){ prev2.addEventListener('click', function(){ goto(1); }); }
        if(next2){ next2.addEventListener('click', function(){ goto(3); }); }
        if(prev3){ prev3.addEventListener('click', function(){ goto(2); }); }

        if(form){
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var fd = new FormData(form);
                fd.append('action','valcode_create_appointment');
                fd.append('nonce', ValcodeAppoint.nonce);
                fetch(ValcodeAppoint.ajax, { method:'POST', credentials:'same-origin', body: fd })
                  .then(function(r){ return r.json(); }).then(function(res){
                    if(res && res.success){
                        msg(res.data && res.data.message ? res.data.message : '✅ Termin gespeichert!', true);
                        form.reset();
                        enable(worker,false); setOptions(worker, [], 'Bitte zuerst Service wählen…');
                        setOptions(slotSel, [], 'Bitte Datum wählen…'); enable(slotSel,false);
                        goto(1);
                    }else{
                        msg(res && res.data && res.data.message ? res.data.message : 'Fehler beim Speichern.', false);
                    }
                  }).catch(function(){ msg('Fehler beim Absenden.', false); });
            });
        }

        // init
        refreshNext1(); refreshNext2();
    });
})();
