$(document).ready(function($) {
    $("#frame_roll_buttons").children("button").click(function() {
        var el = $("#"+$(this).data('input-target'));
        el.val($(this).data('input-value'));

        el.closest("form").submit();
    })
});