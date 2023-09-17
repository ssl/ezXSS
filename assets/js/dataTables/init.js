$(document).ready(function () {
    new DataTable('#reports', {
        "ajax": {
            "url": "/manage/api/reports",
            "type": "POST",
            "data": function (d) {
                d.id = window.location.pathname.split('/').filter(Boolean).pop() === 'all' ? 0 : window.location.pathname.split('/').filter(Boolean).pop();
                d.archive = (window.location.search.indexOf('archive=1') !== -1 ? '1' : '0');
                d.csrf = csrf;
            }
        },
        columns: [
            {
                width: 110,
                className: 'dt-body-left',
                data: 'shareid',
                orderable: false,
                render: function (data, type, row) {
                    return `<label class="reports-checkbox-label" for="chk_` + row.id + `">
                        <input class="chkbox" type="checkbox" name="selected"
                            value="`+ row.id + `" id="chk_` + row.id + `"
                            report-id="`+ row.id + `">
                        <span class="reports-checkbox-custom rectangular"></span>
                    </label>
                    <div class="btn-group btn-view" style="width:100px;max-width:100px;" role=group>
                        <a href="/manage/reports/view/`+ row.id + `" class="btn pretty-border">View</a>
                        <div class=btn-group role=group>
                            <button type=button
                                class="btn btn-default dropdown-toggle pretty-border"
                                data-toggle=dropdown aria-haspopup=true aria-expanded=true>
                                <span class=caret></span>
                            </button>
                            <div class=dropdown-backdrop></div>
                            <ul class=dropdown-menu>
                                <li><a class="share" report-id="`+ row.id + `" share-id="` + data + `" data-toggle="modal" data-target="#shareModal">Share</a></li>
                                <li><a class="delete" report-id="`+ row.id + `">Delete</a></li>
                                <li><a class="archive" report-id="`+ row.id + `">Archive</a></li>
                            </ul>
                        </div>
                    </div>`;
                }
            },
            { data: 'id', },
            { data: 'uri', },
            { data: 'ip', },
            { data: 'payload', className: 'dt-body-right' }
        ],
        columnDefs: [{ targets: 2, className: "truncate" }],
        createdRow: function (row, data, dataIndex) {
            $(row).attr('id', data.id);
            var td = $(row).find(".truncate");
            td.attr("title", td.html());
        },
        order: [[1, 'desc']],
    });

    $('#persistent').DataTable({
        columnDefs: [
            { orderable: false, targets: [0] }
        ],
        "pageLength": 25,
        order: [[6, 'desc']],
    });

    $('#logs').DataTable({
        "pageLength": 25,
        order: [[3, 'desc']],
    });
});