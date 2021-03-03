function request(action, data) {
    data["action"] = action;
    data["csrf"] = csrf;
    return $.ajax({
        type: "post",
        dataType: "json",
        url: "/"+adminURL()+"/request",
        data: data
    });
}

function adminURL() {
    var path = $(location).attr('pathname');
    path.indexOf(1);
    path.toLowerCase();
    return path.split("/")[1];
}

$(document).ready(function() {

    if(location.toString().split('/').pop() == 'dashboard') {
        request('statistics', {}).then(function(r) {
            $.each( r, function( key, value ) {
                $("#"+key).html(value);
            });
        });
    }

    $("form.form").submit(function(Form) {
        $("#alert").slideUp();
        Form.preventDefault();
        var inputs = {};
        $("form#" + this.id + " :input").each(function() {
            if(this.id === 'customjs') {
                inputs[this.id] = btoa(this.value);
            }
            else if(this.name == 'selected') {
                $.each($("input[name='selected']:checked"), function(){
                    inputs[this.id] = this.value;
                });
                if(this.id == 'csrf') {
                    inputs[this.id] = this.value;
                }
            }
            else if (this.id) {
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
        request("delete-selected", {ids:ids}).then(function(r) {
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
        request("archive-selected", {ids:ids,archive:archive}).then(function(r) {
            $.each( ids, function( i, val ) {
                $("#" + val).fadeOut( "slow", function() {});
            });
        });
    });

    $(".delete").click(function() {
        var id = $(this).attr('report-id');
        request("delete-report", {id:id}).then(function(r) {
            if(location.toString().split('/').slice(-2)[0] !== 'report') {
                $("#"+id).fadeOut( "slow", function() {});
            } else {
                window.location.href = '/'+adminURL()+'/reports';
            }
        });
    });

    $(".archive").click(function() {
        var id = $(this).attr('report-id');
        request("archive-report", {id:id}).then(function(r) {
            $("#"+id).fadeOut( "slow", function() {});
        });
    });

    $(".share").click(function() {
        $('#reportid').val( $(this).attr('report-id') );
        $('#shareid').val("https://" + window.location.hostname + "/"+adminURL()+"/report/" + $(this).attr('share-id') );
    });

    $("#openGetChatId").click(function() {
        var bottoken = $("#telegram_bottoken").val();
        request("getchatid", {bottoken:bottoken}).then(function(r) {
            if(r.echo.startsWith('chatId:')) {
                $('#getChatId').modal('hide');
                $("#telegram_chatid").val(r.echo.replace('chatId:', ''));
            } else {
                $('#getChatId').modal('show');
                $("#getChatIdBody").html(r.echo);
            }
        });
    });

    $(".remove-whitelist").click(function() {
        var id = $(this).attr('d');
        var divid = $(this).attr('divid');
        request("remove-domain", {id:id,type:'whitelist'}).then(function(r) {
            $("#"+divid).fadeOut( "slow", function() {});
            if (r.redirect) {
                window.location.href = r.redirect;
            }
        });
    });

    $(".remove-blacklist").click(function() {
        var id = $(this).attr('d');
        var divid = $(this).attr('divid');
        request("remove-domain", {id:id,type:'blacklist'}).then(function(r) {
            $("#"+divid).fadeOut( "slow", function() {});
            if (r.redirect) {
                window.location.href = r.redirect;
            }
        });
    });

    $(".remove-page").click(function() {
        var id = $(this).attr('d');
        var divid = $(this).attr('divid');
        request("remove-domain", {id:id,type:'page'}).then(function(r) {
            $("#"+divid).fadeOut( "slow", function() {});
            if (r.redirect) {
                window.location.href = r.redirect;
            }
        });
    });

    $(".copycookies").click(function() {
        var cookies = $("#cookies").text();
        var split = cookies.split('; ');
        var json = '[';
        var origin = $(this).attr('report-origin');

        $.each( split, function( index, value ) {
            var cookieData = value.split('=');
            var cookieName = cookieData[0];
            var cookieValue = cookieData[1];

            json += '{"domain":"'+origin+'","expirationDate":'+(Date.now() / 1000 + 31556926)+',"hostOnly":true,"httpOnly":false,"name":"'+cookieName+'","path":"/","sameSite":"unspecified","secure":false,"session":false,"storeId": "0","value":"'+cookieValue+'","id":"'+(index+1)+'"},';
        });

        json = json.substring(0, json.length - 1);
        json += ']';

        var $temp = $("<input>");
        $("body").append($temp);
        $temp.val(json).select();
        document.execCommand("copy");
        $temp.remove();
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