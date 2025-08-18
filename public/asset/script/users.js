$(document).ready(function () {
    $(".sideBarli").removeClass("activeLi");
    $(".usersSideA").addClass("activeLi");

    $("#UsersTable").dataTable({
        processing: true,
        serverSide: true,
        serverMethod: "post",
        aaSorting: [[0, "desc"]],
        columnDefs: [
            {
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
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
                targets: [0, 1, 2, 3, 4, 5, 6, 7, 8],
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

    // Role Management
    $("#UsersTable").on("click", ".assignRole", function (e) {
        e.preventDefault();

        var user_id = $(this).attr("data-id");
        var currentRole = $(this).attr("data-role");
        
        $("#roleUserId").val(user_id);
        
        // Reset form
        $("#roleType").val("");
        $("#duration").val("");
        $("#durationGroup").show();
        $("#durationHint").text("");
        
        // Set current role if exists
        if (currentRole && currentRole !== 'null') {
            var roleData = JSON.parse(currentRole);
            $("#roleType").val(roleData.role_type);
            updateDurationOptions(roleData.role_type);
        }
        
        $("#roleModal").modal("show");
    });

    // Handle role type change
    $("#roleType").change(function() {
        var roleType = $(this).val();
        updateDurationOptions(roleType);
    });

    function updateDurationOptions(roleType) {
        var durationSelect = $("#duration");
        var durationGroup = $("#durationGroup");
        var hint = $("#durationHint");
        
        durationSelect.empty();
        durationSelect.append('<option value="">Select Duration</option>');
        
        if (roleType === 'VIP') {
            durationSelect.append('<option value="1_month">1 Month</option>');
            durationSelect.append('<option value="1_year">1 Year</option>');
            durationGroup.show();
            hint.text("VIP can be set for 1 month or 1 year");
        } else if (roleType === 'Millionaire' || roleType === 'Billionaire') {
            durationSelect.append('<option value="1_year">1 Year</option>');
            durationSelect.val('1_year');
            durationGroup.show();
            hint.text("Can only be set for 1 year");
        } else if (roleType === 'Celebrity') {
            durationGroup.hide();
            hint.text("Celebrity role is permanent");
        } else {
            durationGroup.show();
            hint.text("");
        }
    }

    // Handle role form submission
    $(document).on("submit", "#roleForm", function (e) {
        e.preventDefault();

        var formdata = new FormData($("#roleForm")[0]);
        $(".loader").show();

        $.ajax({
            url: "/api/admin/assignRole",
            type: "POST",
            data: formdata,
            dataType: "json",
            contentType: false,
            cache: false,
            processData: false,
            success: function (data) {
                $(".loader").hide();
                $("#roleModal").modal("hide");
                
                if (data.status == true) {
                    iziToast.show({
                        title: "Success",
                        message: data.message,
                        color: "#1DC9A0",
                        position: "topRight",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        iconUrl: "/asset/img/check-circle.svg",
                    });
                    $("#UsersTable").DataTable().ajax.reload(null, false);
                    $("#StreamersTable").DataTable().ajax.reload(null, false);
                } else {
                    iziToast.show({
                        title: "Error",
                        message: data.message,
                        color: "#F56C6C",
                        position: "topRight",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        iconUrl: "/asset/img/x.svg",
                    });
                }
            },
            error: function(xhr, status, error) {
                $(".loader").hide();
                iziToast.show({
                    title: "Error",
                    message: "Failed to assign role",
                    color: "#F56C6C",
                    position: "topRight",
                    transitionIn: "fadeInDown",
                    transitionOut: "fadeOutUp",
                    timeout: 3000,
                    iconUrl: "/asset/img/x.svg",
                });
            }
        });
    });

    // Handle revoke role
    $("#revokeRoleBtn").click(function(e) {
        e.preventDefault();
        
        var userId = $("#roleUserId").val();
        
        if (confirm("Are you sure you want to revoke this user's role?")) {
            $(".loader").show();
            
            $.ajax({
                url: "/api/admin/revokeRole",
                type: "POST",
                data: {
                    user_id: userId,
                    _token: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: "json",
                success: function (data) {
                    $(".loader").hide();
                    $("#roleModal").modal("hide");
                    
                    if (data.status == true) {
                        iziToast.show({
                            title: "Success",
                            message: data.message,
                            color: "#1DC9A0",
                            position: "topRight",
                            transitionIn: "fadeInDown",
                            transitionOut: "fadeOutUp",
                            timeout: 3000,
                            iconUrl: "/asset/img/check-circle.svg",
                        });
                        $("#UsersTable").DataTable().ajax.reload(null, false);
                        $("#StreamersTable").DataTable().ajax.reload(null, false);
                    } else {
                        iziToast.show({
                            title: "Error", 
                            message: data.message,
                            color: "#F56C6C",
                            position: "topRight",
                            transitionIn: "fadeInDown",
                            transitionOut: "fadeOutUp",
                            timeout: 3000,
                            iconUrl: "/asset/img/x.svg",
                        });
                    }
                },
                error: function(xhr, status, error) {
                    $(".loader").hide();
                    iziToast.show({
                        title: "Error",
                        message: "Failed to revoke role",
                        color: "#F56C6C",
                        position: "topRight",
                        transitionIn: "fadeInDown",
                        transitionOut: "fadeOutUp",
                        timeout: 3000,
                        iconUrl: "/asset/img/x.svg",
                    });
                }
            });
        }
    });

});
