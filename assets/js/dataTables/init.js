$(document).ready(function () {
    $.fn.dataTable.ext.errMode = 'none';
    
    new DataTable('#reports', {
        "ajax": {
            "url": "/manage/api/reports",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                d.id = window.location.pathname.split('/').filter(Boolean).pop() === 'all' ? 0 : window.location.pathname.split('/').filter(Boolean).pop();
                d.archive = (window.location.search.indexOf('archive=1') !== -1 ? 1 : 0);
                return JSON.stringify(d);
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
            { data: 'id' },
            { data: 'uri' },
            { data: 'ip' },
            { data: 'browser' },
            { data: 'payload' },
            { data: 'last' }
        ],
        columnDefs: [{ targets: 2, className: "truncate" }],
        createdRow: function (row, data, dataIndex) {
            $(row).attr('id', data.id);
            var td = $(row).find(".truncate");
            td.attr("title", td.html());
        },
        order: [[1, 'desc']],
    });

    var dataTablePersistent = new DataTable('#persistent', {
        "ajax": {
            "url": "/manage/api/sessions",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                d.id = window.location.pathname.split('/').filter(Boolean).pop() === 'all' ? 0 : window.location.pathname.split('/').filter(Boolean).pop();
                return JSON.stringify(d);
            }
        },
        columns: [
            {
                width: 110,
                className: 'dt-body-left persistent-column',
                data: 'clientid',
                orderable: false,
                render: function (data, type, row) {
                    return `<label class="reports-checkbox-label" for="chk_` + row.id + `">
                    <input class="chkbox" type="checkbox" name="selected"
                        value="` + row.id + `" id="chk_` + row.id + `"
                        url="/manage/persistent/session/` + data + `~` + row.origin + `">
                    <span class="reports-checkbox-custom rectangular" style="top:7px"></span>
                </label>
                <a href="/manage/persistent/session/` + data + `~` + row.origin + `">` + data + `</a>`;
                }
            },
            { data: 'browser', },
            { data: 'ip' },
            { data: 'shorturi' },
            { data: 'payload' },
            { data: 'requests' },
            { 
                data: 'last',
                render: function (data, type, row, meta) {
                    if (type === 'sort') {
                        return row.time;
                    }
                    return data;
                }
            },
        ],
        order: [[6, 'desc']],
    });

    if (location.toString().split('/')[4] === "persistent") {
        var elapsedTime = 0;
        function updateTimer() {
            setInterval(function () {
                elapsedTime += 1;
                $("#last").text(elapsedTime + "s ago");
            }, 1000);
        }
        updateTimer();
        setInterval(function () {
            dataTablePersistent.ajax.reload(null, false);
            elapsedTime = 0;
        }, 120000);
    }

    new DataTable('#logs', {
        "ajax": {
            "url": "/manage/api/logs",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                return JSON.stringify(d);
            }
        },
        columns: [
            { data: 'user', },
            { data: 'description', },
            { data: 'ip', },
            { 
                data: 'date',
                render: function (data, type, row, meta) {
                    if (type === 'sort') {
                        return row.time;
                    }
                    return data;
                }
            },
        ],
        order: [[3, 'desc']],
    });

    new DataTable('#users', {
        "ajax": {
            "url": "/manage/api/users",
            "contentType": "application/json",
            "type": "POST",
            "data": function (d) {
                return JSON.stringify(d);
            }
        },
        columns: [
            { data: 'id', },
            { data: 'username', },
            { data: 'rank', },
            { data: 'payloads', },
            { 
                data: 'id',
                orderable: false,
                render: function (data, type, row) {
                    return `<a href="/manage/users/edit/` + data + `">Edit</a>`;
                }
            },
            { 
                data: 'id',
                orderable: false,
                render: function (data, type, row) {
                    return `<a href="/manage/users/delete/` + data + `">Delete</a>`;
                }
            },
        ],
        order: [[0, 'asc']],
        scrollY: false,
        scrollX: false,
    });

    $('#simple-table').DataTable({
        columnDefs: [
            { orderable: false, targets: [0] }
        ],
            "pageLength": 25,
        order: [[0, 'desc']],
    });
});