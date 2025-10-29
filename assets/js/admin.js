(function($){
    // Appointments page: dynamic worker select
    $(document).on('change', '#service_id', function(){
        var service = $(this).val();
        var $worker = $('#staff_id');
        if(!service){
            $worker.prop('disabled', true).html('<option value="">Bitte zuerst Service wählen…</option>');
            return;
        }
        $worker.prop('disabled', true).html('<option value="">Lade Mitarbeiter…</option>');
        $.get(ValcodeAppointAdmin.ajax, {
            action:'valcode_get_workers',
            nonce: ValcodeAppointAdmin.nonce,
            service_id: service
        }).done(function(res){
            var items = (res && res.success && res.data && res.data.workers) ? res.data.workers : [];
            var html = '<option value="0">-</option>';
            html += '<option value="">' + (items.length ? 'Bitte wählen…' : 'Keine Mitarbeiter verfügbar') + '</option>';
            items.forEach(function(it){ html += '<option value="'+it.id+'">'+it.name+'</option>'; });
            $worker.html(html).prop('disabled', items.length === 0);
        }).fail(function(){
            $worker.html('<option value="">Fehler beim Laden</option>').prop('disabled', true);
        });
    });
})(jQuery);