function request(action, data) {
    data["action"] = action;
    return $.ajax({
        type: "post",
        dataType: "json",
        url: "request",
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
});

$("#alert").on("click", ".close", function() {
    $("#alert").slideUp();
});
