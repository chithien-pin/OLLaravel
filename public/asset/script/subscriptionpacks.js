$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".subscriptionpackSideA").addClass("activeLi");

    $(".addModalBtn").on("click", function (event) {
        event.preventDefault();
        $("#addForm")[0].reset();
    });

    $("#subscriptionTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[1, "asc"]], // Sort by amount ascending
        scrollX: true,
        responsive: true,
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchSubscriptionPackages`,
            data: function (data) {},
        },
        columns: [
            {
                data: "plan_type",
                render: function (data, type, row) {
                    const colors = {
                        'starter': 'success',
                        'monthly': 'primary', 
                        'yearly': 'info',
                        'millionaire': 'warning',
                        'billionaire': 'dark'
                    };
                    const color = colors[data] || 'secondary';
                    return `<span class="badge badge-${color}">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: "amount",
                render: function (data, type, row) {
                    return `<strong>$${parseFloat(data).toFixed(2)}</strong>`;
                }
            },
            {
                data: "interval_type",
                render: function (data, type, row) {
                    const color = data === 'month' ? 'info' : 'secondary';
                    return `<span class="badge badge-${color}">${data}</span>`;
                }
            },
            {
                data: "type",
                render: function (data, type, row) {
                    const color = data === 'role' ? 'success' : 'warning';
                    const icon = data === 'role' ? 'crown' : 'star';
                    return `<span class="badge badge-${color}">
                                <i class="fas fa-${icon}"></i> ${data.toUpperCase()}
                            </span>`;
                }
            },
            {
                data: "ios_product_id",
                render: function (data, type, row) {
                    if (!data) return '<span class="text-muted">N/A</span>';
                    // Truncate long product IDs for display
                    const truncated = data.length > 25 ? data.substring(0, 25) + '...' : data;
                    return `<small class="text-monospace" title="${data}">${truncated}</small>`;
                }
            },
            {
                data: "first_time_only",
                render: function (data, type, row) {
                    if (data == 1) {
                        return '<span class="badge badge-warning"><i class="fas fa-star"></i> FIRST ONLY</span>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            {
                data: "id",
                render: function (data, type, row) {
                    return `<div class="action-buttons">
                                <button class="btn btn-sm btn-outline-primary" onclick="editSubscriptionPack(${data})" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteSubscriptionPack(${data})" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`;
                }
            }
        ],
        language: {
            processing: "Loading...",
            emptyTable: "No subscription packs found",
            info: "Showing _START_ to _END_ of _TOTAL_ packs",
            infoEmpty: "Showing 0 to 0 of 0 packs",
            search: "Search packs:",
            lengthMenu: "Show _MENU_ packs per page"
        }
    });
});

function addSubmit() {
    if (user_type == 1) {
        let formData = new FormData($("#addForm")[0]);
        $.ajax({
            type: "POST",
            url: `${domainUrl}addSubscriptionPack`,
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.status == true) {
                    iziToast.show({
                        title: "Success",
                        message: "Subscription pack added successfully",
                        color: "green",
                        position: "topCenter",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        animateInside: false,
                    });
                    $("#subscriptionTable").DataTable().ajax.reload(null, false);
                    $("#addSubscriptionPack").modal("hide");
                    $("#addForm")[0].reset();
                } else {
                    iziToast.show({
                        title: "Error",
                        message: response.message || "Failed to add subscription pack",
                        color: "red",
                        position: "topCenter",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        animateInside: false,
                    });
                }
            },
            error: function (xhr) {
                let message = "An error occurred";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    message = Object.values(errors).flat().join(', ');
                }
                
                iziToast.show({
                    title: "Error",
                    message: message,
                    color: "red",
                    position: "topCenter",
                    transitionIn: "fadeInDown",
                    transitionOut: "fadeOutUp",
                    timeout: 5000,
                    animateInside: false,
                });
            }
        });
    }
}

function editSubscriptionPack(id) {
    $.ajax({
        type: "GET",
        url: `${domainUrl}getSubscriptionPackById/${id}`,
        success: function (data) {
            $("#edit_id").val(data.id);
            $("#edit_plan_type").val(data.plan_type);
            $("#edit_amount").val(data.amount);
            $("#edit_currency").val(data.currency);
            $("#edit_ios_product_id").val(data.ios_product_id);
            $("#edit_android_product_id").val(data.android_product_id);
            $("#edit_interval_type").val(data.interval_type);
            $("#edit_type").val(data.type);
            $("#editSubscriptionPack").modal("show");
        },
    });
}

function updateSubmit() {
    if (user_type == 1) {
        let formData = new FormData($("#editForm")[0]);
        $.ajax({
            type: "POST",
            url: `${domainUrl}updateSubscriptionPack`,
            data: formData,
            contentType: false,
            processData: false,
            success: function (response) {
                if (response.status == true) {
                    iziToast.show({
                        title: "Success",
                        message: "Subscription pack updated successfully",
                        color: "green",
                        position: "topCenter",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        animateInside: false,
                    });
                    $("#subscriptionTable").DataTable().ajax.reload(null, false);
                    $("#editSubscriptionPack").modal("hide");
                } else {
                    iziToast.show({
                        title: "Error",
                        message: response.message || "Failed to update subscription pack",
                        color: "red",
                        position: "topCenter",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        animateInside: false,
                    });
                }
            },
            error: function (xhr) {
                let message = "An error occurred";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    const errors = xhr.responseJSON.errors;
                    message = Object.values(errors).flat().join(', ');
                }
                
                iziToast.show({
                    title: "Error",
                    message: message,
                    color: "red",
                    position: "topCenter",
                    transitionIn: "fadeInDown",
                    transitionOut: "fadeOutUp",
                    timeout: 5000,
                    animateInside: false,
                });
            }
        });
    }
}

function deleteSubscriptionPack(id) {
    iziToast.question({
        timeout: false,
        close: false,
        overlay: true,
        displayMode: "once",
        id: "question",
        zindex: 999,
        title: "Confirm",
        message: "Are you sure you want to delete this subscription pack?",
        position: "center",
        buttons: [
            [
                "<button><b>YES</b></button>",
                function (instance, toast) {
                    instance.hide({ transitionOut: "fadeOut" }, toast, "button");
                    if (user_type == 1) {
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}deleteSubscriptionPack`,
                            data: { id: id },
                            success: function (response) {
                                if (response.status == true) {
                                    iziToast.show({
                                        title: "Success",
                                        message: "Subscription pack deleted successfully",
                                        color: "green",
                                        position: "topCenter",
                                        transitionIn: "fadeInDown",
                                        transitionOut: "fadeOutUp",
                                        timeout: 3000,
                                        animateInside: false,
                                    });
                                    $("#subscriptionTable").DataTable().ajax.reload(null, false);
                                } else {
                                    iziToast.show({
                                        title: "Error",
                                        message: response.message || "Failed to delete subscription pack",
                                        color: "red",
                                        position: "topCenter",
                                        transitionIn: "fadeInDown",
                                        transitionOut: "fadeOutUp",
                                        timeout: 3000,
                                        animateInside: false,
                                    });
                                }
                            },
                        });
                    }
                },
                true,
            ],
            [
                "<button>NO</button>",
                function (instance, toast) {
                    instance.hide({ transitionOut: "fadeOut" }, toast, "button");
                },
            ],
        ],
    });
}