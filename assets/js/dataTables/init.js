$(document).ready(function () {
    $('#reports').DataTable({
        columnDefs: [
            { orderable: false, targets: [0, 1] }
        ],
        order: [[1, 'desc']],
        orderMulti: false,
        columns: [
            {
                width: 110
            },{},{},{},{width:50}
        ]
    });
});