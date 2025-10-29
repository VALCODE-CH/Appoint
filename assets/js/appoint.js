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
            o.value = it.id; o.textContent = it.name;
            select.appendChild(o);
        });
    }
    function msg(txt, ok){
        var p = q('va_msg'); if(!p) return;
        p.textContent = txt; p.hidden = false; p.style.color = ok ? 'green' : 'crimson';
    }

    document.addEventListener('DOMContentLoaded', function(){
        var service = q('va_service');
        var worker  = q('va_worker');
        var form    = q('va-booking');
        if(service && worker){
            service.addEventListener('change', function(){
                var val = service.value;
                if(!val){
                    worker.disabled = true;
                    setOptions(worker, [], 'Bitte zuerst Service wählen…');
                    return;
                }
                worker.disabled = true;
                setOptions(worker, [], 'Lade Mitarbeiter…');

                var url = (ValcodeAppoint && ValcodeAppoint.ajax) + '?action=valcode_get_workers&nonce=' + encodeURIComponent(ValcodeAppoint.nonce) + '&service_id=' + encodeURIComponent(val);
                fetch(url, { credentials: 'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        var items = (res && res.success && res.data && res.data.workers) ? res.data.workers : [];
                        setOptions(worker, items, items.length ? 'Bitte wählen…' : 'Keine Mitarbeiter verfügbar');
                        worker.disabled = items.length === 0;
                    })
                    .catch(function(){
                        setOptions(worker, [], 'Fehler beim Laden');
                        worker.disabled = true;
                    });
            });
        }
        if(form){
            form.addEventListener('submit', function(e){
                e.preventDefault();
                var fd = new FormData(form);
                fd.append('action','valcode_create_appointment');
                fd.append('nonce', ValcodeAppoint.nonce);

                fetch(ValcodeAppoint.ajax, {
                    method:'POST',
                    credentials:'same-origin',
                    body: fd
                }).then(function(r){ return r.json(); }).then(function(res){
                    if(res && res.success){
                        msg('✅ Termin gespeichert! Wir melden uns per E-Mail.', true);
                        form.reset();
                        if(worker){ worker.disabled = true; setOptions(worker, [], 'Bitte zuerst Service wählen…'); }
                    }else{
                        msg(res && res.data && res.data.message ? res.data.message : 'Fehler beim Speichern.', false);
                    }
                }).catch(function(){
                    msg('Fehler beim Absenden.', false);
                });
            });
        }
    });
})();