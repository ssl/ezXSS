function request(action, data) {
    data["action"] = action;
    return $.ajax({
        type: "post",
        dataType: "json",
        url: "/manage/request",
        data: data
    });
}

$(document).ready(function() {

    $("form.form").submit(function(Form) {
        $("#alert").slideUp();
        Form.preventDefault();
        var inputs = {};
        $("form#" + this.id + " :input").each(function() {
            if (this.id) {
                inputs[this.id] = this.value;
            }
        });
        request(this.id, inputs).then(function(r) {
            if (!r.redirect) {
                $("#alert").html('<div class="alert" role="alert"><p class="close">×</p>' + r.echo + '</div>');
                $("#alert").hide();
                $("#alert").slideDown("slow");
            } else {
                window.location.href = r.redirect;
            }
        });
    });

    $(".delete-selected").click(function() {
        var ids = [];
        $.each($("input[name='selected']:checked"), function(){
            ids.push($(this).val());
        });
        request("delete-selected", {ids:ids,csrf:csrf}).then(function(r) {
            $.each( ids, function( i, val ) {
                $("#" + val).fadeOut( "slow", function() {});
            });
        });
    });

    $(".archive-selected").click(function() {
        var ids = [];
        $.each($("input[name='selected']:checked"), function(){
            ids.push($(this).val());
        });
        var archive = 0;
        if(location.toString().split('/').pop() == 'reports') {
            var archive = 1;
        }
        request("archive-selected", {ids:ids,csrf:csrf,archive:archive}).then(function(r) {
            $.each( ids, function( i, val ) {
                $("#" + val).fadeOut( "slow", function() {});
            });
        });
    });

    $(".delete").click(function() {
        var id = $(this).attr('report-id');
        request("delete-report", {id:id,csrf:csrf}).then(function(r) {
            var loc = location.toString().split('/').pop();
            if(loc == 'reports' || loc == 'archive') {
                $("#"+id).fadeOut( "slow", function() {});
            } else {
                window.location.href = '/manage/reports';
            }
        });
    });

    $(".archive").click(function() {
        var id = $(this).attr('report-id');
        request("archive-report", {id:id,csrf:csrf}).then(function(r) {
            $("#"+id).fadeOut( "slow", function() {});
        });
    });

    $(".share").click(function() {
        $('#reportid').val( $(this).attr('report-id') );
        $('#shareid').val("https://" + window.location.hostname + "/manage/report/" + $(this).attr('share-id') );
    });

    $('.left-nav-toggle a').on('click', function(event){
        event.preventDefault();
        $("body").toggleClass("nav-toggle");
    });

    $('.nav-second').on('show.bs.collapse', function () {
        $('.nav-second.in').collapse('hide');
    });

    $('.panel-toggle').on('click', function(event){
        event.preventDefault();
        var hpanel = $(event.target).closest('div.panel');
        var icon = $(event.target).closest('i');
        var body = hpanel.find('div.panel-body');
        var footer = hpanel.find('div.panel-footer');
        body.slideToggle(300);
        footer.slideToggle(200);

        icon.toggleClass('fa-chevron-up').toggleClass('fa-chevron-down');
        hpanel.toggleClass('').toggleClass('panel-collapse');
        setTimeout(function () {
            hpanel.resize();
            hpanel.find('[id^=map-]').resize();
        }, 50);
    });

    $('.panel-close').on('click', function(event){
        event.preventDefault();
        var hpanel = $(event.target).closest('div.panel');
        hpanel.remove();
    });

    var lastChecked = null;
    var $chkboxes = $('.chkbox');
    $chkboxes.click(function(e) {
        if (!lastChecked) {
            lastChecked = this;
            return;
        }

        if (e.shiftKey) {
            var start = $chkboxes.index(this);
            var end = $chkboxes.index(lastChecked);

            $chkboxes.slice(Math.min(start, end), Math.max(start, end) + 1).prop('checked', this.checked);
        }

        lastChecked = this;
    });
});


$("#alert").on("click", ".close", function() {
    $("#alert").slideUp();
});

$('#select-all').click(function(event) {
    if(this.checked) {
        $(':checkbox').each(function() {
            this.checked = true;
        });
    } else {
        $(':checkbox').each(function() {
            this.checked = false;
        });
    }
});