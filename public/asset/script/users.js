$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".usersSideA").addClass("activeLi");

    $("#UsersTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        autoWidth: true,
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchAllUsers`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#UsersTable").on("click", ".addCoins", function (e) {
        e.preventDefault();

        var user_id = $(this).attr("data-id");
        $("#userId").val(user_id);
        $("#addCoinsModal").modal("show");
    });

    $(document).on("submit", "#addCoinsForm", function (e) {
        e.preventDefault();

        var formdata = new FormData($("#addCoinsForm")[0]);
        $(".loader").show();

        $.ajax({
            url: `${domainUrl}addCoinsToUserWalletFromAdmin`,
            type: "POST",
            data: formdata,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function (data) {
                $(".loader").hide();
                $("#addCoinsModal").modal("hide");
                if (data.success == 1) {
                    $("#addCoinsForm")[0].reset();
                    $("#UsersTable").DataTable().ajax.reload(null, false);
                    iziToast.success({
                        title: "Success!",
                        message: "Changes applied successfully!",
                        position: "topRight",
                    });
                } else {
                    iziToast.error({
                        title: "Error!",
                        message: data.message,
                        position: "topRight",
                    });
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                alert(errorThrown);
            },
        });
    });

    $("#StreamersTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchStreamerUsers`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    $("#FakeUsersTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7],
                orderable: false,
            },
        ],
        ajax: {
            url: `${domainUrl}fetchFakeUsers`,
            data: function (data) {},
            error: (error) => {
                console.log(error);
            },
        },
    });

    $(document).on("click", ".block", function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var id = $(this).attr("rel");
            swal({
                title: app.sure,
                icon: "error",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Yes"],
            }).then((deleteValue) => {
                if (deleteValue) {
                    if (deleteValue == true) {
                        console.log(true);
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}blockUser`,
                            dataType: "json",
                            data: {
                                user_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.thisUserHasBeenBlocked,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#UsersTable").DataTable().ajax.reload(null, false);
                                    $("#FakeUsersTable").DataTable().ajax.reload(null, false);
                                    $("#StreamersTable").DataTable().ajax.reload(null, false);
                                }
                            },
                        });
                    }
                }
            });
        } else {
            iziToast.show({
                title: `${app.Error}!`,
                message: app.tester,
                color: app.redToast,
                position: app.toastPosition,
                transitionIn: app.transitionInAction,
                transitionOut: app.transitionOutAction,
                timeout: app.timeout,
                animateInside: false,
                iconUrl: app.cancleIcon,
            });
        }
    });

    $(document).on("click", ".unblock", function (e) {
        e.preventDefault();
        if (user_type == 1) {
            var id = $(this).attr("rel");
            swal({
                title: app.sure,
                icon: "error",
                buttons: true,
                dangerMode: true,
                buttons: ["Cancel", "Yes"],
            }).then((deleteValue) => {
                if (deleteValue) {
                    if (deleteValue == true) {
                        console.log(true);
                        $.ajax({
                            type: "POST",
                            url: `${domainUrl}unblockUser`,
                            dataType: "json",
                            data: {
                                user_id: id,
                            },
                            success: function (response) {
                                if (response.status == false) {
                                    console.log(response.message);
                                } else if (response.status == true) {
                                    iziToast.show({
                                        title: app.Success,
                                        message: app.thisUserHasBeenUnblocked,
                                        color: app.greenToast,
                                        position: app.toastPosition,
                                        transitionIn: app.fadeInAction,
                                        transitionOut: app.fadeOutAction,
                                        timeout: app.timeout,
                                        animateInside: false,
                                        iconUrl: app.checkCircleIcon,
                                    });
                                    $("#UsersTable").DataTable().ajax.reload(null, false);
                                    $("#FakeUsersTable").DataTable().ajax.reload(null, false);
                                    $("#StreamersTable").DataTable().ajax.reload(null, false);
                                }
                            },
                        });
                    }
                }
            });
        } else {
            iziToast.show({
                title: `${app.Error}!`,
                message: app.tester,
                color: app.redToast,
                position: app.toastPosition,
                transitionIn: app.transitionInAction,
                transitionOut: app.transitionOutAction,
                timeout: app.timeout,
                animateInside: false,
                iconUrl: app.cancleIcon,
            });
        }
    });

    // Ban User Handler
    $(document).on("click", ".banUser", function (e) {
        e.preventDefault();
        var id = $(this).attr("data-id");
        var name = $(this).attr("data-name");
        swal({
            title: "Are you sure you want to ban " + name + "?",
            text: "This will disable their account, remove all posts, and block Firebase login.",
            icon: "warning",
            buttons: ["Cancel", "Yes, Ban"],
            dangerMode: true,
        }).then((confirmed) => {
            if (confirmed) {
                $(".loader").show();
                $.ajax({
                    type: "POST",
                    url: `${domainUrl}banUserFromAdmin`,
                    dataType: "json",
                    data: { user_id: id },
                    success: function (response) {
                        $(".loader").hide();
                        if (response.status) {
                            iziToast.success({
                                title: "Banned!",
                                message: response.message,
                                position: "topRight",
                            });
                            $("#UsersTable").DataTable().ajax.reload(null, false);
                        } else {
                            iziToast.error({
                                title: "Error",
                                message: response.message,
                                position: "topRight",
                            });
                        }
                    },
                    error: function () {
                        $(".loader").hide();
                        iziToast.error({ title: "Error", message: "Failed to ban user", position: "topRight" });
                    },
                });
            }
        });
    });

    // Unban User Handler
    $(document).on("click", ".unbanUser", function (e) {
        e.preventDefault();
        var id = $(this).attr("data-id");
        var name = $(this).attr("data-name");
        swal({
            title: "Are you sure you want to unban " + name + "?",
            text: "This will restore their account and posts.",
            icon: "info",
            buttons: ["Cancel", "Yes, Unban"],
        }).then((confirmed) => {
            if (confirmed) {
                $(".loader").show();
                $.ajax({
                    type: "POST",
                    url: `${domainUrl}unbanUserFromAdmin`,
                    dataType: "json",
                    data: { user_id: id },
                    success: function (response) {
                        $(".loader").hide();
                        if (response.status) {
                            iziToast.success({
                                title: "Unbanned!",
                                message: response.message,
                                position: "topRight",
                            });
                            $("#UsersTable").DataTable().ajax.reload(null, false);
                        } else {
                            iziToast.error({
                                title: "Error",
                                message: response.message,
                                position: "topRight",
                            });
                        }
                    },
                    error: function () {
                        $(".loader").hide();
                        iziToast.error({ title: "Error", message: "Failed to unban user", position: "topRight" });
                    },
                });
            }
        });
    });

    // Expire VIP Roles Button Handler
    $(document).on("click", "#expire-vip-roles", function(e) {
        e.preventDefault();
        
        if (user_type == 1) {
            // Show confirmation dialog
            if (confirm("Check and process all expired VIP roles and packages? This will convert expired VIPs back to Normal users and remove expired packages.")) {
                // Show loading state
                $(this).prop("disabled", true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Checking...');
                
                $.ajax({
                    url: `${domainUrl}expireVipRoles`,
                    type: "POST",
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: "json",
                    success: function (response) {
                        // Reset button state
                        $("#expire-vip-roles").prop("disabled", false).html('<i class="fas fa-search mr-1"></i>Check Expired VIPs & Packages');
                        
                        if (response.status) {
                            const output = response.output || response.message;
                            const isExpired = (output.includes("Expired 0") && output.includes("Total expired packages: 0")) ? false : true;
                            
                            iziToast.success({
                                title: isExpired ? "VIPs & Packages Processed" : "All VIPs & Packages Active",
                                message: isExpired ? output : "No expired VIP roles or packages found. All are still active.",
                                position: "topRight",
                                timeout: 5000,
                            });
                            
                            // Reload tables to show updated data
                            $("#UsersTable").DataTable().ajax.reload(null, false);
                            $("#StreamersTable").DataTable().ajax.reload(null, false);
                        } else {
                            iziToast.error({
                                title: "Error",
                                message: response.message || "Failed to expire VIP roles and packages",
                                position: "topRight",
                                timeout: 4000,
                            });
                        }
                    },
                    error: function (xhr, status, error) {
                        // Reset button state
                        $("#expire-vip-roles").prop("disabled", false).html('<i class="fas fa-search mr-1"></i>Check Expired VIPs & Packages');
                        
                        console.error("Error expiring VIP roles:", error);
                        iziToast.error({
                            title: "Error",
                            message: "Failed to expire VIP roles and packages",
                            position: "topRight",
                            timeout: 4000,
                        });
                    }
                });
            }
        } else {
            iziToast.error({
                title: "Tester Login",
                message: "You are tester",
                position: "topRight",
                timeout: 4000,
            });
        }
    });

});
