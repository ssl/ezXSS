$(document).ready(function () {
    $('#reports').DataTable({
        columnDefs: [
            { orderable: false, targets: [0, 1] }
        ],
        order: [[2, 'desc']]
    });
});